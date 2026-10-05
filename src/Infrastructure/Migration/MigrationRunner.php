<?php
/**
 * The Migration Runner
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Runs the migration chains, each against its own cursor, under one shared lock
 *
 * Two kinds of consumer: the activation controller drives it from `init`, and feature code asks whether
 * the schema it is about to query has been applied. Free's chain always runs first; an add-on appends its
 * own by subclassing this and replacing the binding through `alondra_di_definitions`.
 *
 * @since      1.0.3
 */
class MigrationRunner {

	// Kept apart from the preferences option, so a preferences save can never clobber them.
	public const PREF_MIGRATION_ID      = 'alondra_migration_id';
	public const PREF_MIGRATION_FAILURE = 'alondra_migration_failure';

	/**
	 * Name of free's chain
	 */
	public const CHAIN = 'alondra';

	/**
	 * Creates the plugin's three tables.
	 *
	 * Named so a call site reads as intent rather than as a magic number. The ids themselves are permanent;
	 * see the registry below.
	 */
	public const CREATE_TABLES = 1;

	/**
	 * Drops the foreign keys the two child tables used to carry
	 */
	public const DROP_FOREIGN_KEYS = 2;

	/**
	 * Adds the rules table's bundle_product column, the same id 1.1.1 shipped it under
	 */
	public const ADD_BUNDLE_PRODUCT_COLUMN = 3;

	/**
	 * Stores what the retired seed filters returned into the preferences option
	 */
	public const IMPORT_SEED_FILTERS = 4;

	/**
	 * Migration Lock, shared by every chain
	 *
	 * A per-blog transient, not a site-wide one. The cursor is an option and the tables are
	 * `{$wpdb->prefix}alondra_*`, both of which are per blog, so a network-activated multisite migrates
	 * each blog separately. A network-scoped lock would let one blog's migration block every other blog
	 * from touching its own tables -- and a blog whose migration fails deterministically would take the
	 * lock again every TTL, starving the rest indefinitely.
	 */
	public const MIGRATION_LOCK = 'alondra_migration_lock';

	/**
	 * How long the lock survives with nobody left to release it
	 *
	 * Not a budget for how long a migration may take: the work runs to completion regardless. Only the
	 * success path releases the lock explicitly, so this covers every other ending - a migration that threw,
	 * a request that died of a fatal, and the exit that runs no PHP at all and so reaches nothing (a
	 * SIGKILLed worker: the OOM killer, a pool recycle, `docker stop`). It is therefore how long such an
	 * install stays unmigrated, which is why it is minutes rather than the hour it used to be, and it
	 * doubles as the retry interval for both failure paths below.
	 */
	public const LOCK_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Source the failure log is written under, which is the name it appears as in WooCommerce > Status > Logs
	 */
	public const LOG_SOURCE = 'alondra-migrations';

	/**
	 * Names for the error types that end the request without unwinding, so neither a catch nor a finally runs
	 *
	 * A lookup, not a gate: whether a failure is recorded is decided by the armed migration id alone, and
	 * this only names the type when PHP happens to have reported one this path can produce. Anything else
	 * - or nothing at all - is recorded as an unknown type rather than dropped. See
	 * record_failure_on_fatal() for why error_get_last() cannot be trusted to decide.
	 *
	 * WordPress core's own list, from WP_Fatal_Error_Handler::should_handle_error(). E_CORE_ERROR is absent
	 * from it for the reason it is absent here: it fires during startup, before this code exists to have
	 * taken a lock. E_RECOVERABLE_ERROR is unreachable on this path - PHP 7 throws it as an \Error, which
	 * run() already catches - and is kept only so the list stays recognisable as core's.
	 *
	 * Keyed by the type PHP reports, valued with the constant's own name: the fatal path has no class to
	 * record, and a bare integer in the record would say nothing to whoever reads the log or the hook.
	 *
	 * @var array<int, string>
	 */
	private const FATAL_ERROR_TYPES = [
		E_ERROR             => 'E_ERROR',
		E_PARSE             => 'E_PARSE',
		E_USER_ERROR        => 'E_USER_ERROR',
		E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
		E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
	];

	/**
	 * Free's migrations, keyed by the id stored as its chain's cursor.
	 *
	 * A shipped id is permanent: never reuse it, lower it or remove it, or an install that already recorded it
	 * skips every migration below it. Append with the next integer. A test asserts the ids stay in ascending
	 * order, so a hotfix branched off an older release cannot slip one in underneath; PHPStan rejects both a
	 * duplicated id and a class that is not a Migration.
	 *
	 * @var array<int, class-string<Migration>>
	 */
	public const MIGRATIONS = [
		self::CREATE_TABLES             => CreateTablesMigration::class,
		self::DROP_FOREIGN_KEYS         => DropForeignKeysMigration::class,
		self::ADD_BUNDLE_PRODUCT_COLUMN => AddBundleColumnMigration::class,
		self::IMPORT_SEED_FILTERS       => ImportSeedFiltersMigration::class,
	];

	/**
	 * Registry id currently executing, or null whenever the runner is not inside a migration
	 *
	 * Answers both questions the shutdown handler has, and is the only thing it gates on: whether this request
	 * died mid-migration, and which migration to name in the record. Cleared after each migration's cursor
	 * write, so only a migration that has yet to finish is ever named, and again in run()'s finally, which is
	 * reached on the success path and on the caught-throwable path but on neither fatal path.
	 *
	 * @var int|null
	 */
	private ?int $running_id = null;

	/**
	 * Name of the chain $running_id belongs to
	 */
	private string $running_chain = '';

	/**
	 * The last applied migration id, keyed by cursor option
	 *
	 * Memoised because the schema gate asks for it on every price render, several times per rendered
	 * product, and the option carries no guarantee of being autoloaded.
	 *
	 * @var array<string, int>
	 */
	private array $migration_ids = [];

	/**
	 * Apply every chain's migrations above its stored cursor, chain by chain, in id order
	 *
	 * Does not throw. Callers reach this from `init`, where there is no wp_die() to turn a fatal into a
	 * recoverable error page, so containing every failure is part of the contract rather than the caller's
	 * problem.
	 *
	 * @return void
	 */
	public function run(): void {
		try {
			$this->run_pending();
		} catch ( \Throwable $e ) {
			// Everything run_pending() does before its own try opens -- reading the cursor, reading and
			// writing the lock -- is option and transient layer code, and a filter on any of it can
			// throw. The cursor read on `init` is the first one in the request, so this is the ordinary
			// path into that layer, not a contrived one.
			//
			// Only the log, deliberately. The failure record drives a notice promising an automatic
			// retry that nothing here can promise; writing an option is the very layer under suspicion;
			// and the failure action's payload opens with a migration id, of which there is none yet.
			$this->log_runner_failure( $e );
		}
	}

	/**
	 * Apply every chain's migrations above its stored cursor, chain by chain, in id order
	 *
	 * @return void
	 */
	private function run_pending(): void {
		// This runs on every request, so the gate stays one option read and one comparison. Reading the cursor
		// against the highest registered id costs no instantiation. A fresh install reads 0, which opens the
		// path; harmless, since the table creation is idempotent.
		if ( ! $this->has_pending() ) {
			// The only path a stale record can be cleared from, and the same window produces both: a
			// fatal after the last migration wrote its cursor leaves the record below unreachable,
			// since every later request stops here. Admin only: the record is read nowhere but the
			// notice, and it normally does not exist, so it is absent from alloptions and reaching
			// for it costs a query per request. init runs before admin_notices, so the first admin
			// page still clears it before the notice renders.
			if ( is_admin() && false !== get_option( self::PREF_MIGRATION_FAILURE ) ) {
				delete_option( self::PREF_MIGRATION_FAILURE );
			}

			return;
		}

		if ( $this->is_migration_running() ) {
			return;
		}

		$this->set_migration_lock();

		try {
			// Stops at the first failure: a later chain may depend on the schema an earlier one builds.
			foreach ( $this->chains() as $chain ) {
				$this->run_migrations( $chain );
			}

			// Reached only once every pending migration has returned, so any record an earlier
			// attempt left behind describes a failure that has since been recovered from.
			delete_option( self::PREF_MIGRATION_FAILURE );

			// The only release there is. Both failure paths keep the lock, which makes it the retry
			// throttle: one attempt per LOCK_TTL instead of one per request, where a migration that
			// fails deterministically would cost three dbDelta passes and a failing ALTER per page
			// load, and one that fatals would cost a 500 on every request, wp-login.php included.
			$this->clear_migration_lock();
		} catch ( \Throwable $e ) {
			// A failed migration must not fatal every request, storefront included, and this is reached
			// from `init`, where there is no wp_die() to make a fatal recoverable. \Throwable rather than
			// \Exception: a TypeError out of the database layer would otherwise escape with nothing left
			// to advance the cursor and stop the retry loop. Whatever did not complete is left behind, so
			// the next request retries it. run_migrations() has already recorded the failure for the
			// admin notice, and the lock it deliberately keeps throttles the retry.
			unset( $e );
		} finally {
			// Disarms the shutdown handler. Reached on both paths above and on neither fatal one, so a
			// fatal raised later in the request - in a theme, in another plugin - can neither be
			// recorded as a migration failure nor overwrite the more specific record the catch above
			// has just left for the notice.
			$this->running_id = null;
		}
	}

	public function has_pending(): bool {
		foreach ( $this->chains() as $chain ) {
			if ( $this->cursor( $chain ) < max( array_keys( $chain->migrations ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the given migration of the given chain has been applied
	 *
	 * @param int    $id    Registry id, e.g. self::CREATE_TABLES.
	 * @param string $chain Chain name. An unknown chain has nothing applied.
	 * @return bool
	 */
	public function is_applied( int $id, string $chain = self::CHAIN ): bool {
		foreach ( $this->chains() as $candidate ) {
			if ( $candidate->name === $chain ) {
				return $this->cursor( $candidate ) >= $id;
			}
		}

		return false;
	}

	/**
	 * The highest id of the chain applied to this install
	 *
	 * 0 when nothing has been applied, which is why no registered id may be 0.
	 *
	 * @param MigrationChain $chain Chain to read.
	 * @return int
	 */
	private function cursor( MigrationChain $chain ): int {
		return $this->get_migration_id( $chain->cursor_option );
	}

	/**
	 * Get the last applied migration id of a chain.
	 *
	 * @param string $option Cursor option of the chain.
	 * @return int The migration id. 0 when no migration has been applied.
	 */
	public function get_migration_id( string $option = self::PREF_MIGRATION_ID ): int {
		if ( ! isset( $this->migration_ids[ $option ] ) ) {
			$this->migration_ids[ $option ] = (int) get_option( $option, 0 ); // @phpstan-ignore cast.int
		}

		return $this->migration_ids[ $option ];
	}

	/**
	 * Store the last applied migration id. Must be called after the migration itself succeeded.
	 *
	 * Returns nothing on purpose: update_option() reports false both for a failed write and for a value that
	 * did not change, so a boolean here could not be read as success or failure.
	 *
	 * @param int    $id     Migration id to store.
	 * @param string $option Cursor option of the chain.
	 */
	public function set_migration_id( int $id, string $option = self::PREF_MIGRATION_ID ): void {
		update_option( $option, $id );
		$this->migration_ids[ $option ] = $id;
	}

	/**
	 * Run every migration of the chain above its stored cursor, in id order
	 *
	 * @param MigrationChain $chain Chain to run.
	 * @return void
	 * @throws \Throwable Whatever the migration threw, once it has been recorded for the admin notice.
	 */
	private function run_migrations( MigrationChain $chain ): void {
		$last = $this->cursor( $chain );

		$migrations = $chain->migrations;

		// Execution order is the id, never the order the entries happen to be declared in.
		ksort( $migrations );

		foreach ( $migrations as $id => $class ) {
			if ( $id <= $last ) {
				continue;
			}

			$this->running_id    = $id;
			$this->running_chain = $chain->name;

			try {
				Container::instance()->get( $class )->execute();
			} catch ( \Throwable $e ) {
				// The id only exists inside this loop, which is why the record is written here rather
				// than in run()'s catch. That one keeps the request alive; this one only names what
				// was running before handing the throwable straight on.
				$this->record_failure(
					$chain->name,
					$id,
					'throwable',
					get_class( $e ),
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				);

				throw $e;
			}

			// Written after each one, so a throw part-way through a sequence leaves the cursor on the last
			// migration that finished and the next request resumes at the one that failed.
			$this->set_migration_id( $id, $chain->cursor_option );

			// Disarms the handler between migrations. This one has returned and its cursor is written,
			// so a fatal from here on - in the next migration's constructor, in run()'s own option
			// write, anywhere later in the request - is not this migration's failure and must not be
			// recorded as one. Past the last id that record would also be unclearable, since every
			// following request stops at run()'s has_pending() gate.
			$this->running_id = null;
		}
	}

	/**
	 * The chains to run, in order, free's first
	 *
	 * The extension point: an add-on overrides this to append its own chain after the parent's, and tests
	 * override it to substitute registries the shipped one is too short to express. A chain's name and
	 * cursor option are permanent for the same reason its ids are.
	 *
	 * @return non-empty-array<int, MigrationChain>
	 */
	protected function chains(): array {
		return [ new MigrationChain( self::CHAIN, self::PREF_MIGRATION_ID, self::MIGRATIONS ) ];
	}

	private function is_migration_running(): bool {
		return ! empty( get_transient( self::MIGRATION_LOCK ) );
	}

	private function clear_migration_lock(): void {
		delete_transient( self::MIGRATION_LOCK );
	}

	private function set_migration_lock(): void {
		set_transient( self::MIGRATION_LOCK, true, self::LOCK_TTL );

		// A max_execution_time or memory_limit overrun is an E_ERROR, not a Throwable: run()'s catch and
		// finally are both skipped, so without this the lock would sit out its TTL - which is what the
		// TTL is for - with nothing recorded to say why. Shutdown functions do still run there, with
		// roughly zend.hard_timeout seconds of CPU grace, which the handler's option write, log line and
		// action fit inside; record_failure() runs them in the order that survives losing the rest.
		register_shutdown_function( [ $this, 'record_failure_on_fatal' ] );
	}

	/**
	 * Record the failure when the request died inside a migration
	 *
	 * Its whole job, and the only thing that can do it: a fatal skips run()'s catch and its finally, so
	 * without this a request that dies inside a migration leaves nothing at all behind to say what happened
	 * and the admin notice has nothing to read. It does not touch the lock, which the success path alone
	 * releases, so the fatal path is throttled by the TTL exactly as the throw path is.
	 *
	 * That symmetry is the point. Releasing the lock here handed the retry to the very next request, which
	 * re-entered the same migration and died the same way: measured over HTTP, every request 500ed,
	 * wp-login.php included, so nobody could reach the notice this handler had just written. Retaining it
	 * costs one 500 per TTL and the site answers normally in between, which is what makes the notice
	 * readable and the failure recoverable.
	 *
	 * The armed id is the whole test. run()'s finally and each cursor write null it, so every orderly
	 * ending disarms this and reaching the body at all means the request died inside a migration.
	 * error_get_last() is only enrichment and must never gate the record: it holds whatever diagnostic
	 * was raised *last*, of any severity, including ones excluded from error_reporting and ones
	 * suppressed with `@`. Shutdown functions run in registration order, and core registers
	 * shutdown_action_hook (wp-settings.php:166) long before plugins load (582), so `do_action(
	 * 'shutdown' )` always runs before this - and WooCommerce's own neighbours there
	 * (wc_webhook_execute_queue, Action Scheduler, WC_Session_Handler::save_data) are exactly the kind
	 * of code that raises one. Measured over HTTP against a real CPU-bound fatal: gating on the type
	 * meant a single E_WARNING or E_DEPRECATED emitted on `shutdown` cost the record, the log line and
	 * the action, and the install was left with nothing at all to say what had happened.
	 *
	 * Registered by set_migration_lock(). Public only because PHP's dispatch of a shutdown function on a
	 * fatal cannot be provoked from inside PHPUnit, so a test has to call it.
	 *
	 * @param array{type: int, message: string, file: string, line: int}|null $error Last error raised, or null
	 *                                                                              to ask PHP for it.
	 * @return void
	 */
	public function record_failure_on_fatal( ?array $error = null ): void {
		if ( null === $this->running_id ) {
			return;
		}

		if ( null === $error ) {
			$error = error_get_last();
		}

		// Same shape as the runner writes, so the admin notice, the log and the action read a fatal
		// without knowing a fatal is what produced it. Only the kind tells them apart. What PHP last
		// reported fills in the detail when it is there and this path could have produced it;
		// otherwise the type says so rather than dressing somebody else's notice up as the cause.
		$this->record_failure(
			$this->running_chain,
			$this->running_id,
			'fatal',
			self::FATAL_ERROR_TYPES[ $error['type'] ?? 0 ] ?? 'unknown',
			$error['message'] ?? 'The request died inside the migration with no error reported.',
			$error['file'] ?? 'unknown',
			$error['line'] ?? 0
		);
	}

	/**
	 * Report a failure of the runner itself, raised outside any migration
	 *
	 * Separate from record_failure() because there is no migration to name: this is the runner's own
	 * plumbing failing, so the id, kind and type that record's consumers expect do not exist.
	 *
	 * @param \Throwable $e Whatever the option or transient layer raised.
	 * @return void
	 */
	private function log_runner_failure( \Throwable $e ): void {
		// Same reasoning as record_failure()'s guard: WooCommerce is a hard dependency only for
		// activation, and this runs on `init` on every request.
		if ( function_exists( 'wc_get_logger' ) ) {
			try {
				wc_get_logger()->error(
					sprintf(
						'Migration runner failed outside any migration (%1$s): %2$s in %3$s:%4$d',
						get_class( $e ),
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					),
					[ 'source' => self::LOG_SOURCE ]
				);
			} catch ( \Throwable $ignored ) {
				// The last thing standing between a broken option layer and a fatal on `init`:
				// wc_get_logger() reads options of its own, so it can fail the same way.
				unset( $ignored );
			}
		}
	}

	/**
	 * Report a failed migration everywhere it has to surface
	 *
	 * Three destinations, in a deliberate order, because on the fatal path all three run inside
	 * zend.hard_timeout's grace (2 s of CPU here) and may not all finish:
	 *
	 * 1. The option, because the admin notice is the only channel that reaches a shop owner who has not
	 *    been told to look anywhere, and it is what the next successful run clears.
	 * 2. The log, because a store administered over REST or WP-CLI never sees an admin notice at all, and
	 *    because the option is overwritten by the next attempt while the log keeps every one of them.
	 * 3. The action last: monitoring is somebody else's code, and it must not be able to cost either record.
	 *
	 * @param string $chain   Name of the chain the migration belongs to.
	 * @param int    $id      Registry id of the migration that was running.
	 * @param string $kind    Which failure path produced this: 'throwable' or 'fatal'.
	 * @param string $type    Exception class on the throw path, E_* constant name on the fatal one.
	 * @param string $message Message the throwable or PHP reported.
	 * @param string $file    File it was raised in.
	 * @param int    $line    Line it was raised on.
	 * @return void
	 */
	private function record_failure( string $chain, int $id, string $kind, string $type, string $message, string $file, int $line ): void {
		update_option(
			self::PREF_MIGRATION_FAILURE,
			[
				'chain'   => $chain,
				'id'      => $id,
				'kind'    => $kind,
				'type'    => $type,
				'message' => $message,
			]
		);

		// WooCommerce is a hard dependency, but only for activation: core hides the Deactivate link for a
		// plugin others require and does not deactivate the dependents, and nothing gates WP-CLI at all. This
		// runs on `init` on every request, so an install that lost WooCommerce must not fatal here as well.
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error(
				sprintf(
					'Migration %1$s/%2$d failed (%3$s %4$s): %5$s in %6$s:%7$d',
					$chain,
					$id,
					$kind,
					$type,
					$message,
					$file,
					$line
				),
				[ 'source' => self::LOG_SOURCE ]
			);
		}

		/**
		 * Fires when a migration fails, on both the throw and the fatal path.
		 *
		 * @since 1.0.3
		 * @param int    $id      Registry id of the migration that failed.
		 * @param string $message Message the throwable or PHP reported.
		 * @param string $kind    Which failure path produced this: 'throwable' or 'fatal'.
		 * @param string $type    Exception class on the throw path, E_* constant name on the fatal one.
		 * @param string $chain   Name of the chain the migration belongs to. Since 2.0.0.
		 */
		do_action( 'alondra_migration_failed', $id, $message, $kind, $type, $chain );
	}
}
