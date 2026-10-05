<?php
/**
 * MigrationRunner Tests
 *
 * Covers the runner: the chains, their cursors, the shared lock and the failure record. The trigger hook, the
 * preference-seeding path and the admin notice belong to the activation controller and are covered there.
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Migrations;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\AddBundleColumnMigration;
use Midrinet\Alondra\Infrastructure\Migration\CreateTablesMigration;
use Midrinet\Alondra\Infrastructure\Migration\DropForeignKeysMigration;
use Midrinet\Alondra\Infrastructure\Migration\ImportSeedFiltersMigration;
use Midrinet\Alondra\Infrastructure\Migration\Migration;
use Midrinet\Alondra\Infrastructure\Migration\MigrationChain;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use Midrinet\Alondra\Tests\Support\Addon_Migration_Runner;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';
require_once __DIR__ . '/../Support/class-addon-migration-runner.php';

/**
 * Tests for the migration runner.
 */
class MigrationRunnerTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Registry entries for the substituted registries below.
	 *
	 * A registry value is only ever a key handed to the container, so a label works where production uses a
	 * class-string, and it keeps the three doubles tellable apart in $this->calls. Three
	 * createMock( Migration::class ) doubles could not: PHPUnit caches one generated class per mocked type.
	 */
	private const MIGRATION_ONE   = 'migration:1';
	private const MIGRATION_TWO   = 'migration:2';
	private const MIGRATION_THREE = 'migration:3';

	/**
	 * Label the shipped foreign-key migration's double records itself under.
	 *
	 * A double where the table creation above is the real class: that one reaches its work through a service
	 * this file already mocks, while this one issues DDL straight at the database and is covered against a
	 * live one of its own.
	 */
	private const DROP_FOREIGN_KEYS = 'drop-foreign-keys';

	/**
	 * Label the shipped seed filters import's double records itself under. Covered on its own.
	 */
	private const IMPORT_SEED_FILTERS = 'import-seed-filters';

	/**
	 * The second chain the multi-chain cases append after free's.
	 */
	private const ADDON_CHAIN  = 'addon';
	private const ADDON_CURSOR = 'addon_migration_id';

	private $container;
	private $pricing_service;

	/**
	 * Migration steps in the order they were invoked.
	 *
	 * @var string[]
	 */
	private $calls = [];

	/**
	 * Cursor values record_cursor() carries forward, keyed by cursor option.
	 *
	 * @var array<string, int>
	 */
	private $cursors = [];

	/**
	 * Whether cursor writes are recorded into $calls and $cursors.
	 *
	 * @var bool
	 */
	private $recording = false;

	/**
	 * Cursor writes since the cursors were last seeded.
	 *
	 * @var int
	 */
	private $cursor_writes = 0;

	/**
	 * Set by expect_no_migration(), checked in tear_down().
	 *
	 * @var bool
	 */
	private $forbid_cursor_writes = false;

	/**
	 * Throwables the registry doubles raise, keyed by registry entry and cleared on the first throw, so a
	 * migration that failed once succeeds when the next request retries it.
	 *
	 * @var array<string, \Throwable>
	 */
	private $migration_throws = [];

	/**
	 * Lines the substituted logger was handed, message and context as passed.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $log_lines = [];

	/**
	 * Argument lists `alondra_migration_failed` fired with.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private $action_payloads = [];

	/**
	 * Whether the record the admin notice reads was already stored when the log call arrived.
	 *
	 * @var bool|null
	 */
	private $record_when_logged = null;

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		parent::set_up();

		// The lock is global state, and the plugin's own controller runs a migration on the
		// bootstrap's `init`. Start from a released lock rather than inheriting one. The failure
		// record is written on the same run and equally survives the per-test transaction, since
		// the bootstrap's migration happens before the first test opens one.
		delete_transient( MigrationRunner::MIGRATION_LOCK );
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

		$this->calls            = [];
		$this->migration_throws = [];
		$this->log_lines        = [];
		$this->action_payloads  = [];

		$this->record_when_logged = null;

		$this->pricing_service = $this->createMock( TieredPricingService::class );

		$this->recording            = false;
		$this->cursor_writes        = 0;
		$this->forbid_cursor_writes = false;
		$this->seed_cursors( 0, 0 );

		// pre_update_option fires before update_option() compares values, so a write of the same value counts.
		foreach ( [ MigrationRunner::PREF_MIGRATION_ID, self::ADDON_CURSOR ] as $option ) {
			add_filter(
				'pre_update_option_' . $option,
				function ( $value ) use ( $option ) {
					return $this->on_cursor_write( $option, $value );
				}
			);
		}

		$this->container = $this->createStub( Container::class );
		$this->container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingService::class, $this->pricing_service ],

				// The real migration, resolving its own dependency from this same stub, so the
				// recorded setup() call still proves the registry reaches the schema work.
				[ CreateTablesMigration::class, new CreateTablesMigration() ],

				[ DropForeignKeysMigration::class, $this->migration_double( self::DROP_FOREIGN_KEYS ) ],

				// Real as well: the column is added by the same setup() call, so it records 'setup' again.
				[ AddBundleColumnMigration::class, new AddBundleColumnMigration() ],

				[ ImportSeedFiltersMigration::class, $this->migration_double( self::IMPORT_SEED_FILTERS ) ],

				[ self::MIGRATION_ONE, $this->migration_double( self::MIGRATION_ONE ) ],
				[ self::MIGRATION_TWO, $this->migration_double( self::MIGRATION_TWO ) ],
				[ self::MIGRATION_THREE, $this->migration_double( self::MIGRATION_THREE ) ],
			]
		);
		$this->install_container( $this->container );
	}

	/**
	 * A Migration double that records its label when the runner executes it.
	 *
	 * @param string $label Registry entry this double is bound to.
	 * @return Migration
	 */
	private function migration_double( string $label ) {
		$double = $this->createMock( Migration::class );

		$double->method( 'execute' )->willReturnCallback(
			function () use ( $label ) {
				$this->calls[] = $label;

				if ( isset( $this->migration_throws[ $label ] ) ) {
					$throwable = $this->migration_throws[ $label ];
					unset( $this->migration_throws[ $label ] );

					throw $throwable;
				}
			}
		);

		return $double;
	}

	private function get_instance() {
		return new MigrationRunner();
	}

	/**
	 * A runner whose only chain is free's, running the given registry instead of the shipped one.
	 *
	 * The shipped registry is too short to express resume-after-failure, or an entry declared out of id order.
	 *
	 * @param array<int, string> $migrations Registry to run, declaration order preserved.
	 * @return MigrationRunner
	 */
	private function get_instance_with_migrations( array $migrations ) {
		return $this->get_instance_with_chains(
			new MigrationChain( MigrationRunner::CHAIN, MigrationRunner::PREF_MIGRATION_ID, $migrations )
		);
	}

	/**
	 * A runner running the given chains, in order. chains() is the seam, overridden the way the datamapper
	 * tests already override get_table().
	 *
	 * @param MigrationChain ...$chains Chains to run.
	 * @return MigrationRunner
	 */
	private function get_instance_with_chains( MigrationChain ...$chains ) {
		$service = $this->getMockBuilder( MigrationRunner::class )
			->onlyMethods( [ 'chains' ] )
			->getMock();

		$service->method( 'chains' )->willReturn( $chains );

		return $service;
	}

	/**
	 * Free's chain of migrations one and two, then a second chain of migration three.
	 *
	 * @return MigrationRunner
	 */
	private function get_instance_with_two_chains() {
		return $this->get_instance_with_chains(
			new MigrationChain(
				MigrationRunner::CHAIN,
				MigrationRunner::PREF_MIGRATION_ID,
				[
					1 => self::MIGRATION_ONE,
					2 => self::MIGRATION_TWO,
				]
			),
			new MigrationChain( self::ADDON_CHAIN, self::ADDON_CURSOR, [ 1 => self::MIGRATION_THREE ] )
		);
	}

	/**
	 * Store both cursors without counting the writes. A runner built after this reads them.
	 *
	 * @param int $start       Free's cursor value.
	 * @param int $addon_start The second chain's cursor value.
	 */
	private function seed_cursors( int $start, int $addon_start = 0 ) {
		$recording       = $this->recording;
		$this->recording = false;

		$this->cursors = [
			MigrationRunner::PREF_MIGRATION_ID => $start,
			self::ADDON_CURSOR                 => $addon_start,
		];
		foreach ( $this->cursors as $option => $id ) {
			update_option( $option, $id );
		}

		$this->cursor_writes = 0;
		$this->recording     = $recording;
	}

	/**
	 * Seed the cursors and record each later write.
	 *
	 * Carrying the value forward is what gives the resume case its meaning: the second run() call has to
	 * see the cursor the first one left behind, exactly as the next request would.
	 *
	 * Free's cursor writes record as `cursor:<id>`, any other chain's as `cursor:<option>:<id>`.
	 *
	 * @param int $start       Free's cursor value before anything runs.
	 * @param int $addon_start The second chain's cursor value before anything runs.
	 */
	private function record_cursor( int $start = 0, int $addon_start = 0 ) {
		$this->recording = false;
		$this->seed_cursors( $start, $addon_start );
		$this->recording = true;
	}

	/**
	 * Count a cursor write and, while recording, log it.
	 *
	 * @param string $option Cursor option.
	 * @param mixed  $value  Value being written.
	 * @return mixed The value, unchanged.
	 */
	private function on_cursor_write( string $option, $value ) {
		if ( ! $this->recording && ! $this->forbid_cursor_writes ) {
			return $value;
		}

		++$this->cursor_writes;

		if ( $this->recording ) {
			$this->cursors[ $option ] = (int) $value;
			$this->calls[]            = MigrationRunner::PREF_MIGRATION_ID === $option ? 'cursor:' . $value : "cursor:{$option}:{$value}";
		}

		return $value;
	}

	/**
	 * Make the shipped registry's schema work record itself, so the order is assertable.
	 *
	 * @param \Throwable|null $first_setup_throws Thrown out of the first setup() call, if given.
	 */
	private function record_setup( $first_setup_throws = null ) {
		$this->pricing_service->method( 'setup' )->willReturnCallback(
			function () use ( $first_setup_throws ) {
				$is_first      = ! in_array( 'setup', $this->calls, true );
				$this->calls[] = 'setup';

				if ( null !== $first_setup_throws && $is_first ) {
					throw $first_setup_throws;
				}
			}
		);
	}

	/**
	 * Substitute a logger this test can read for the one WooCommerce would build.
	 *
	 * `woocommerce_logging_class` is still the seam in the installed WooCommerce, and wc_get_logger() accepts
	 * an object from it as well as a class name -- so the double needs no class of its own, and it cannot
	 * outlive the filter: the static cache is only reused while `is_a( $logger, $class )` holds, and once the
	 * filter is gone `$class` is `WC_Logger` again, which this double is not.
	 */
	private function capture_log() {
		$logger = $this->createMock( \WC_Logger_Interface::class );

		$logger->method( 'error' )->willReturnCallback(
			function ( $message, $context = [] ) {
				$this->log_lines[]        = [
					'message' => $message,
					'context' => $context,
				];
				$this->record_when_logged = is_array( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
			}
		);

		add_filter( 'woocommerce_logging_class', static fn() => $logger );
	}

	/**
	 * Record what the failure action fires with, which is all a monitoring integration ever sees.
	 */
	private function capture_action() {
		add_action(
			'alondra_migration_failed',
			function ( ...$args ) {
				$this->action_payloads[] = $args;
			},
			10,
			5
		);
	}

	/**
	 * Everything a clean run of the shipped registry records, in order.
	 *
	 * @return string[]
	 */
	private function shipped_registry_calls() {
		return [
			'setup',
			'cursor:' . MigrationRunner::CREATE_TABLES,
			self::DROP_FOREIGN_KEYS,
			'cursor:' . MigrationRunner::DROP_FOREIGN_KEYS,
			'setup',
			'cursor:' . MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN,
			self::IMPORT_SEED_FILTERS,
			'cursor:' . MigrationRunner::IMPORT_SEED_FILTERS,
		];
	}

	/**
	 * Assert neither the schema work nor a cursor write is reachable.
	 */
	private function expect_no_migration() {
		$this->pricing_service->expects( $this->never() )->method( 'setup' );
		$this->forbid_cursor_writes = true;
	}

	/**
	 * The registry runs entries whose id is strictly greater than the stored cursor, and 0 is the value the
	 * cursor reads when nothing has been applied. An id of 0 could therefore never execute on any install.
	 */
	public function testMigrationIdsStartAboveTheEmptyCursorSentinel() {
		foreach ( $this->shipped_and_addon_chains() as $chain ) {
			$this->assertGreaterThanOrEqual(
				1,
				min( array_keys( $chain->migrations ) ),
				"0 is the \"nothing applied\" cursor, so a migration registered under it in {$chain->name} could never run."
			);
		}
	}

	/**
	 * Free's chain as shipped, then the one the test add-on appends after it.
	 *
	 * @return MigrationChain[]
	 */
	private function shipped_and_addon_chains() {
		return ( new Addon_Migration_Runner() )->exposed_chains();
	}

	/**
	 * Free's chain keeps the name and cursor option every install already carries, and ahead of any other.
	 * A renamed option reads 0 and replays every migration; a chain ahead of free's would run against a
	 * schema free has not built yet.
	 */
	public function testFreeChainIsFirstAndKeepsItsCursorOption() {
		$chains = $this->shipped_and_addon_chains();

		$this->assertSame( MigrationRunner::CHAIN, $chains[0]->name );
		$this->assertSame( 'alondra', $chains[0]->name );
		$this->assertSame( 'alondra_migration_id', $chains[0]->cursor_option );
		$this->assertSame( MigrationRunner::MIGRATIONS, $chains[0]->migrations );
	}

	/**
	 * Two chains sharing a name or a cursor would share a cursor, which is the thing chains exist to avoid.
	 */
	public function testChainNamesAndCursorOptionsAreUnique() {
		$chains = $this->shipped_and_addon_chains();

		$names   = array_map( fn( $chain ) => $chain->name, $chains );
		$options = array_map( fn( $chain ) => $chain->cursor_option, $chains );

		$this->assertSame( $names, array_values( array_unique( $names ) ) );
		$this->assertSame( $options, array_values( array_unique( $options ) ) );
	}

	/**
	 * A shipped id is permanent, so an appended id at or below an existing one would be skipped by every install
	 * that already recorded the higher one — the migration would silently never run there.
	 *
	 * Only the ordering is asserted. Uniqueness needs no assertion of its own and could not fail one: array keys
	 * are unique by construction, so a duplicated literal is dropped by PHP before the test can see it. PHPStan
	 * rejects that literal at the constant, which is where it has to be caught.
	 */
	public function testMigrationIdsAreDeclaredInAscendingOrder() {
		foreach ( $this->shipped_and_addon_chains() as $chain ) {
			$ids = array_keys( $chain->migrations );

			$ascending = $ids;
			sort( $ascending, SORT_NUMERIC );

			$this->assertSame( $ascending, $ids, "Migration ids of {$chain->name} must be declared in ascending order." );
		}
	}

	/**
	 * A shipped id is permanent. This fails when one is renumbered, removed or reused.
	 */
	public function testShippedMigrationIdsAreUnmoved() {
		$this->assertSame(
			[
				1 => CreateTablesMigration::class,
				2 => DropForeignKeysMigration::class,
				3 => AddBundleColumnMigration::class,
				4 => ImportSeedFiltersMigration::class,
			],
			MigrationRunner::MIGRATIONS
		);
	}

	/**
	 * The stored state outlives a class rename, so it holds ids and messages, never a plugin class name.
	 */
	public function testMigrationStateStoresNoPluginClassName() {
		update_option( MigrationRunner::PREF_MIGRATION_ID, 0 );
		$this->migration_throws[ self::DROP_FOREIGN_KEYS ] = new \Exception( 'migration 2 blew up' );

		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ self::MIGRATION_ONE, $this->migration_double( self::MIGRATION_ONE ) ],
				[ DropForeignKeysMigration::class, $this->migration_double( self::DROP_FOREIGN_KEYS ) ],
			]
		);
		$this->install_container( $container );

		$this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => DropForeignKeysMigration::class,
			]
		)->run();

		$this->assertSame( 1, (int) get_option( MigrationRunner::PREF_MIGRATION_ID ) );
		$this->assertIsNumeric( get_option( MigrationRunner::PREF_MIGRATION_ID ) );
		$this->assertNotFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
		$this->assertStringNotContainsString( 'Midrinet\\', maybe_serialize( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) ) );
	}

	/**
	 * The registry names classes but does not construct them: the runner hands each one to the container. So a
	 * registry entry naming a class that does not exist throws an Error on the first request after the update,
	 * run() catches it, and the install records a failure and never migrates.
	 *
	 * Every other test in this file resolves the registry through a container stub, which answers for whatever the
	 * test put in it. This one asks the container the bootstrap built by loading alondra.php, as an install does.
	 */
	public function testEveryShippedMigrationResolvesFromTheRealContainer() {
		$this->restore_container();
		$container = Container::instance();

		foreach ( MigrationRunner::MIGRATIONS as $id => $class ) {
			$this->assertInstanceOf(
				Migration::class,
				$container->get( $class ),
				"Migration {$id}'s binding must build something the runner can execute()."
			);
		}
	}

	/**
	 * Two requests racing the gate after an update must not both migrate, and the loser must
	 * leave the winner's lock alone.
	 */
	public function testHeldLockSkipsMigration() {
		$this->seed_cursors( 0 );
		$this->expect_no_migration();
		set_transient( MigrationRunner::MIGRATION_LOCK, true, HOUR_IN_SECONDS );

		$this->get_instance()->run();

		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * A throw must not fatal the request. The exception escaping would surface here as a test error
	 * rather than a failure.
	 *
	 * The lock is kept deliberately: a migration that threw will throw again, and re-running it on every
	 * page load costs three dbDelta passes and a failing ALTER each time. The lock's TTL becomes the retry
	 * interval instead.
	 */
	public function testFailedMigrationRetainsLock() {
		$this->record_cursor();
		$this->record_setup( new \Exception( 'schema setup failed' ) );

		$this->get_instance()->run();

		$this->assertSame( [ 'setup' ], $this->calls );
		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * An \Error is not an \Exception. run() is reached from `init`, so one escaping it would fatal
	 * every request on the site, storefront included, with nothing left to advance the cursor and
	 * stop the retry loop.
	 */
	public function testFailedMigrationWithErrorIsCaught() {
		$this->record_cursor();
		$this->record_setup( new \TypeError( 'bad argument out of the database layer' ) );

		$this->get_instance()->run();

		$this->assertSame( [ 'setup' ], $this->calls );
		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * The retained lock is the throttle on the throw path. A second request inside the TTL must do no work
	 * at all rather than replay a migration that is going to fail again. The fatal path is the same, and is
	 * covered by testRetainedLockThrottlesTheRetryAfterAFatal() below.
	 */
	public function testRetainedLockThrottlesTheRetry() {
		$this->record_cursor();
		$this->record_setup( new \Exception( 'schema setup failed' ) );

		$service = $this->get_instance();
		$service->run();
		$service->run();

		$this->assertSame( [ 'setup' ], $this->calls, 'The second run must not reach the migration.' );
	}

	/**
	 * The throttle is an interval, not a dead end: once the TTL has taken the lock away, the next request
	 * retries and recovers.
	 */
	public function testMigrationRetriesOnceTheLockHasExpired() {
		$this->record_cursor();
		$this->record_setup( new \Exception( 'schema setup failed' ) );

		$service = $this->get_instance();
		$service->run();

		// What LOCK_TTL does on its own, without the test waiting five minutes for it.
		delete_transient( MigrationRunner::MIGRATION_LOCK );

		$service->run();

		$this->assertSame(
			[
				'setup',
				'setup',
				'cursor:' . MigrationRunner::CREATE_TABLES,
				self::DROP_FOREIGN_KEYS,
				'cursor:' . MigrationRunner::DROP_FOREIGN_KEYS,
				'setup',
				'cursor:' . MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN,
				self::IMPORT_SEED_FILTERS,
				'cursor:' . MigrationRunner::IMPORT_SEED_FILTERS,
			],
			$this->calls
		);
		$this->assertEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * The other half of retaining it on failure: success still releases it immediately, so an install that
	 * migrated cleanly never waits out a TTL that exists only for failures.
	 *
	 * The recorded calls also pin the named id: CREATE_TABLES has to be the registry entry that actually
	 * creates the tables, or every `is_applied( CREATE_TABLES )` call site gates on the wrong thing.
	 */
	public function testSuccessfulRunReleasesLock() {
		$this->record_cursor();
		$this->record_setup();

		$this->get_instance()->run();

		$this->assertSame( $this->shipped_registry_calls(), $this->calls );
		$this->assertEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * The id orders execution, not the order the entries happen to sit in the source. A hotfix appended above
	 * an entry branched in later must still run in id order on every install.
	 */
	public function testMigrationsRunInIdOrderRegardlessOfDeclarationOrder() {
		$this->seed_cursors( 0 );

		$this->get_instance_with_migrations(
			[
				3 => self::MIGRATION_THREE,
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		)->run();

		$this->assertSame(
			[
				self::MIGRATION_ONE,
				self::MIGRATION_TWO,
				self::MIGRATION_THREE,
			],
			$this->calls
		);
	}

	/**
	 * The cursor records the highest id applied, so an install that already ran the earlier migrations must
	 * not replay them.
	 */
	public function testOnlyMigrationsAboveTheCursorRun() {
		$this->record_cursor( 2 );

		$this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
				3 => self::MIGRATION_THREE,
			]
		)->run();

		$this->assertSame( [ self::MIGRATION_THREE, 'cursor:3' ], $this->calls );
	}

	/**
	 * Written after each migration rather than once at the end. A single write at the end would record work
	 * that never happened as soon as anything in the sequence threw.
	 */
	public function testCursorIsWrittenAfterEachMigration() {
		$this->record_cursor();

		$this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
				3 => self::MIGRATION_THREE,
			]
		)->run();

		$this->assertSame(
			[
				self::MIGRATION_ONE,
				'cursor:1',
				self::MIGRATION_TWO,
				'cursor:2',
				self::MIGRATION_THREE,
				'cursor:3',
			],
			$this->calls
		);
	}

	/**
	 * The case the per-step cursor exists for. A throw in the middle of a sequence must leave the cursor on
	 * the last migration that finished: the failed one is retried, the ones after it never ran and must not
	 * be skipped, and the ones before it must not run twice.
	 */
	public function testThrowMidSequenceResumesAtTheFailedMigration() {
		$this->record_cursor();
		$this->migration_throws[ self::MIGRATION_TWO ] = new \Exception( 'migration 2 failed' );

		$service = $this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
				3 => self::MIGRATION_THREE,
			]
		);

		$service->run();

		$this->assertSame(
			[ self::MIGRATION_ONE, 'cursor:1', self::MIGRATION_TWO ],
			$this->calls,
			'The cursor must stop at 1, and migration 3 must not run ahead of the one that failed.'
		);

		// The retained lock throttles the retry to one attempt per TTL; expiring it is what the next
		// request past that interval sees.
		delete_transient( MigrationRunner::MIGRATION_LOCK );

		$this->calls = [];
		$service->run();

		$this->assertSame(
			[
				self::MIGRATION_TWO,
				'cursor:2',
				self::MIGRATION_THREE,
				'cursor:3',
			],
			$this->calls,
			'The retry resumes at the failure and must not replay migration 1.'
		);

		$this->assertEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * A registry of two whose second entry throws on its first call only, so the first run() fails
	 * and a second one recovers.
	 *
	 * @return MigrationRunner
	 */
	private function get_instance_failing_on_migration_two() {
		$this->record_cursor();
		$this->migration_throws[ self::MIGRATION_TWO ] = new \Exception( 'migration 2 blew up' );

		return $this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		);
	}

	/**
	 * The catch cannot stay silent: nothing else on the site reports a migration that is failing every
	 * request. The runner is the only place that knows *which* migration was running, so the record has to
	 * carry the id as well as the message.
	 */
	public function testFailedMigrationRecordsTheFailingIdAndMessage() {
		$this->get_instance_failing_on_migration_two()->run();

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => 2,
				'kind'    => 'throwable',
				'type'    => 'Exception',
				'message' => 'migration 2 blew up',
			],
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE )
		);
	}

	/**
	 * The record describes the attempt that just failed, not the first one that ever did. A retry that fails
	 * differently -- the table got created and the ALTER is what breaks now, a timeout where there was a
	 * permission error -- has to replace the message, because the notice is the only channel there is and a
	 * stale one sends an administrator after an error the database has stopped reporting.
	 *
	 * The write is the only thing standing between those two states, and it is only ever exercised against an
	 * absent option elsewhere in this file, where a write that refuses to overwrite looks identical to one that
	 * does.
	 */
	public function testASecondFailureReplacesTheRecordedOne() {
		$service = $this->get_instance_failing_on_migration_two();

		$service->run();
		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => 2,
				'kind'    => 'throwable',
				'type'    => 'Exception',
				'message' => 'migration 2 blew up',
			],
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE ),
			'Precondition: the first attempt has to leave a record for the second one to replace.'
		);

		// The failure kept the lock; letting the TTL's effect through is what lets the retry run.
		delete_transient( MigrationRunner::MIGRATION_LOCK );

		// The double clears its throw once it has raised it, so the retry needs a fresh one.
		$this->migration_throws[ self::MIGRATION_TWO ] = new \Exception( 'migration 2 blew up differently' );

		$service->run();

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => 2,
				'kind'    => 'throwable',
				'type'    => 'Exception',
				'message' => 'migration 2 blew up differently',
			],
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE )
		);
	}

	/**
	 * A recovered install must stop warning. The retry is the only dismissal the notice has.
	 */
	public function testSuccessfulRunClearsTheRecord() {
		$service = $this->get_instance_failing_on_migration_two();

		$service->run();
		$this->assertIsArray(
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE ),
			'Precondition: the first run has to fail, or the clear proves nothing.'
		);

		// The failure kept the lock; letting the TTL's effect through is what lets the retry run.
		delete_transient( MigrationRunner::MIGRATION_LOCK );

		$service->run();

		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
	}

	/**
	 * The clear has to be reachable from the steady state as well, because that is where the record a fatal
	 * leaves behind ends up: a request that dies after the last migration wrote its cursor leaves an install
	 * with every table present, the cursor at the head, and a failure record that the success path's own
	 * delete_option can never reach again -- has_pending() is false on every later request, so the early
	 * return is the only path there is. The notice does not dismiss, so recovery meant editing the database.
	 *
	 * Admin, because that is the only context the clear runs in -- see the storefront case below.
	 */
	public function testStaleRecordIsClearedOnceNothingIsPending() {
		set_current_screen( 'dashboard' );
		$this->seed_cursors( 2 );

		update_option(
			MigrationRunner::PREF_MIGRATION_FAILURE,
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => 2,
				'kind'    => 'fatal',
				'type'    => 'E_ERROR',
				'message' => 'Maximum execution time of 5 seconds exceeded',
			]
		);

		$this->expect_no_migration();

		$this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		)->run();

		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
	}

	/**
	 * The clear is admin only, because the record is read nowhere but the notice and reaching for an
	 * option that normally does not exist costs a query per request. The next admin request clears it.
	 */
	public function testStaleRecordSurvivesAStorefrontRequest() {
		$this->seed_cursors( 2 );

		$record = [
			'chain'   => MigrationRunner::CHAIN,
			'id'      => 2,
			'kind'    => 'fatal',
			'type'    => 'E_ERROR',
			'message' => 'Maximum execution time of 5 seconds exceeded',
		];
		update_option( MigrationRunner::PREF_MIGRATION_FAILURE, $record );

		$this->expect_no_migration();

		$this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		)->run();

		$this->assertSame( $record, get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
	}

	/**
	 * A migration that ran cleanly is not a failure, so it must not leave a warning behind.
	 */
	public function testCleanRunRecordsNoFailure() {
		$this->record_cursor();

		$this->get_instance_with_migrations( [ 1 => self::MIGRATION_ONE ] )->run();

		$this->assertFalse( get_option( MigrationRunner::PREF_MIGRATION_FAILURE ) );
	}

	/**
	 * The no-op release. A build that registers no migration the install has not already applied must not
	 * instantiate a migration or write the cursor back over itself.
	 */
	public function testCursorAtTheNewestMigrationDoesNoWork() {
		$this->seed_cursors( 2 );
		$this->forbid_cursor_writes = true;

		$this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		)->run();

		$this->assertSame( [], $this->calls );
	}

	/**
	 * An older install at cursor 2 stops at the foreign-key drop, so upgrading it runs the column migration, then
	 * the seed filters import.
	 */
	public function testCursorAtTwoRunsTheBundleColumnMigrationThenTheImport() {
		$this->record_cursor( MigrationRunner::DROP_FOREIGN_KEYS );
		$this->record_setup();

		$this->get_instance()->run();

		$this->assertSame(
			[
				'setup',
				'cursor:' . MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN,
				self::IMPORT_SEED_FILTERS,
				'cursor:' . MigrationRunner::IMPORT_SEED_FILTERS,
			],
			$this->calls
		);
		$this->assertSame( MigrationRunner::IMPORT_SEED_FILTERS, $this->cursors[ MigrationRunner::PREF_MIGRATION_ID ] );
	}

	/**
	 * An older install that already recorded id 3 for the same column, so only the import runs.
	 */
	public function testCursorAtThreeLikeAFreemiusInstallRunsOnlyTheImport() {
		$this->record_cursor( MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN );
		$this->pricing_service->expects( $this->never() )->method( 'setup' );

		$this->get_instance()->run();

		$this->assertSame( [ self::IMPORT_SEED_FILTERS, 'cursor:' . MigrationRunner::IMPORT_SEED_FILTERS ], $this->calls );
		$this->assertSame( MigrationRunner::IMPORT_SEED_FILTERS, $this->cursors[ MigrationRunner::PREF_MIGRATION_ID ] );
	}

	/**
	 * An install that already ran the import runs nothing again.
	 */
	public function testCursorAtFourDoesNoWork() {
		$this->record_cursor( MigrationRunner::IMPORT_SEED_FILTERS );
		$this->expect_no_migration();

		$this->get_instance()->run();

		$this->assertSame( [], $this->calls );
	}

	/**
	 * The question the runner's own gate asks, exposed so feature code can ask it too.
	 */
	public function testHasPendingWhenTheCursorIsBehind() {
		$this->seed_cursors( 1 );

		$service = $this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		);

		$this->assertTrue( $service->has_pending() );
	}

	/**
	 * The steady state, which is what makes calling this on every request affordable.
	 */
	public function testHasNoPendingWhenTheCursorIsAtTheNewest() {
		$this->seed_cursors( 2 );

		$service = $this->get_instance_with_migrations(
			[
				1 => self::MIGRATION_ONE,
				2 => self::MIGRATION_TWO,
			]
		);

		$this->assertFalse( $service->has_pending() );
	}

	/**
	 * A cursor past a migration means that migration ran, so anything it created is there.
	 */
	public function testIsAppliedBelowTheCursor() {
		$this->seed_cursors( 3 );

		$this->assertTrue( $this->get_instance()->is_applied( MigrationRunner::CREATE_TABLES ) );
	}

	/**
	 * The cursor holds the highest id applied, so the id it names is applied, not pending.
	 */
	public function testIsAppliedAtTheCursor() {
		$this->seed_cursors( MigrationRunner::CREATE_TABLES );

		$this->assertTrue( $this->get_instance()->is_applied( MigrationRunner::CREATE_TABLES ) );
	}

	/**
	 * The case the gate exists for: a fresh or failed install reads 0, so the tables the caller is about to
	 * query may not be there.
	 */
	public function testIsNotAppliedAboveTheCursor() {
		$this->seed_cursors( 0 );

		$this->assertFalse( $this->get_instance()->is_applied( MigrationRunner::CREATE_TABLES ) );
	}

	/**
	 * Put the service in the exact state a fatal leaves behind - lock held, runner armed with the id it is
	 * inside - and invoke the shutdown handler there.
	 *
	 * What is simulated is the *fatal*, in two places: the handler is called inline instead of by PHP at the
	 * moment the request dies, and it is handed the array error_get_last() would have returned rather than
	 * reading it. Neither can be provoked from inside PHPUnit, since a real overrun would take the test
	 * process with it. PHP's dispatch itself is not simulated and needs no HTTP request to see: this suite's
	 * own bootstrap runs a real migration, so a live handler is armed for the whole run and PHP invokes it
	 * at process end - drop the `= null` from the parameter and the run dies with an ArgumentCountError at
	 * `#0 [internal function]`. Everything else is real too: the arming comes from the runner, and the lock
	 * and the record the handler touches are the live ones.
	 *
	 * Passing null is how a test asks for the case where PHP has nothing to report: the handler reads
	 * error_get_last() itself, and the clear below makes that answer null rather than whatever diagnostic
	 * some earlier test in the process happened to leave behind.
	 *
	 * @param array{type: int, message: string, file: string, line: int}|null $error Error handed to the handler,
	 *                                                                              or null for none reported.
	 * @return array<string, mixed> Lock and record as the handler left them, read before the run unwound.
	 */
	private function state_left_by_the_handler( ?array $error ) {
		$this->record_cursor();

		$service = $this->get_instance();
		$state   = [];

		$this->pricing_service->method( 'setup' )->willReturnCallback(
			function () use ( $service, &$state, $error ) {
				// A fatal ends the request, so only the first setup() -- migration 1's -- dies. Migration 3
				// calls setup() again once the double has returned, and must not fire the handler twice.
				if ( [] !== $state ) {
					return;
				}

				error_clear_last();

				$service->record_failure_on_fatal( $error );

				$state = [
					'lock'   => get_transient( MigrationRunner::MIGRATION_LOCK ),
					'record' => get_option( MigrationRunner::PREF_MIGRATION_FAILURE ),
				];
			}
		);

		$service->run();

		return $state;
	}

	/**
	 * The case the handler exists for, and its whole job. A max_execution_time or memory_limit overrun is an
	 * E_ERROR, not a Throwable, so neither the catch nor the finally runs and nothing else can write the
	 * record the admin notice reads.
	 *
	 * The lock it must leave alone: releasing it handed the retry to the very next request, which re-entered
	 * the migration and died the same way, so every request 500ed - wp-login.php included - and the notice
	 * this same handler had just written could not be reached.
	 */
	public function testShutdownHandlerRecordsTheFailureAndKeepsTheLockOnAFatal() {
		$state = $this->state_left_by_the_handler(
			[
				'type'    => E_ERROR,
				'message' => 'Maximum execution time of 5 seconds exceeded',
				'file'    => 'CreateTablesMigration.php',
				'line'    => 42,
			]
		);

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => MigrationRunner::CREATE_TABLES,
				'kind'    => 'fatal',
				'type'    => 'E_ERROR',
				'message' => 'Maximum execution time of 5 seconds exceeded',
			],
			$state['record'],
			'The record must name the migration that was running and carry PHP\'s own message.'
		);
		$this->assertNotEmpty(
			$state['lock'],
			'The lock has to outlive the request that died, so the next one short-circuits on it.'
		);
	}

	/**
	 * The other half of keeping it: the retained lock throttles the fatal path exactly as it throttles the
	 * throw path, one attempt per TTL rather than one per request.
	 *
	 * The next request is made from inside the migration because the death cannot be: letting the callback
	 * return would run the success path, which is the one thing that does release the lock.
	 */
	public function testRetainedLockThrottlesTheRetryAfterAFatal() {
		$this->record_cursor();

		$dying = $this->get_instance();
		$next  = $this->get_instance();

		$this->pricing_service->method( 'setup' )->willReturnCallback(
			function () use ( $dying, $next ) {
				$is_first      = ! in_array( 'setup', $this->calls, true );
				$this->calls[] = 'setup';

				if ( ! $is_first ) {
					return;
				}

				$dying->record_failure_on_fatal(
					[
						'type'    => E_ERROR,
						'message' => 'Maximum execution time of 5 seconds exceeded',
						'file'    => 'CreateTablesMigration.php',
						'line'    => 42,
					]
				);

				$next->run();
			}
		);

		$dying->run();

		$this->assertSame(
			$this->shipped_registry_calls(),
			$this->calls,
			'The request after the fatal must not reach the migration a second time.'
		);
	}

	/**
	 * A warning is not what tells the handler a migration failed, and letting it decide gave a real fatal a
	 * way to leave nothing behind at all. Shutdown functions run in registration order and core registers
	 * shutdown_action_hook (wp-settings.php:166) long before plugins load (582), so `do_action( 'shutdown' )`
	 * always runs before this handler -- and WooCommerce's own neighbours there (wc_webhook_execute_queue,
	 * Action Scheduler, WC_Session_Handler::save_data) are exactly the kind of code that emits a diagnostic.
	 * error_get_last() is updated by any of them, `@`-suppressed and error_reporting-excluded ones included,
	 * so measured over HTTP against a real CPU-bound fatal an E_WARNING or an E_DEPRECATED on `shutdown` cost
	 * the record, the log line and the action alike.
	 *
	 * The armed migration id is the whole test instead, and it excludes every orderly ending on its own:
	 * run()'s finally and each cursor write null it, so this is only ever reached on a request that really
	 * did die inside a migration. What PHP last reported stays as enrichment, and a type this path cannot
	 * produce is named as unknown rather than asserted as the cause.
	 */
	public function testShutdownHandlerRecordsAFatalBehindANonFatalLastError() {
		$state = $this->state_left_by_the_handler(
			[
				'type'    => E_WARNING,
				'message' => 'Undefined array key "colour"',
				'file'    => 'PreferencesService.php',
				'line'    => 7,
			]
		);

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => MigrationRunner::CREATE_TABLES,
				'kind'    => 'fatal',
				'type'    => 'unknown',
				'message' => 'Undefined array key "colour"',
			],
			$state['record'],
			'A diagnostic raised after the fatal must not silence the record, nor be named as the cause.'
		);
		$this->assertNotEmpty( $state['lock'], 'The lock the runner still holds is nobody else\'s to touch.' );
	}

	/**
	 * The other end of the same requirement: PHP may have nothing to report either, so there is no type,
	 * message, file or line to be had. The record is still the only account of the death that reaches
	 * wp-admin, so it is written anyway -- with an unknown type and no file or line invented for it.
	 */
	public function testShutdownHandlerRecordsAFatalPhpReportsNothingFor() {
		$state = $this->state_left_by_the_handler( null );

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => MigrationRunner::CREATE_TABLES,
				'kind'    => 'fatal',
				'type'    => 'unknown',
				'message' => 'The request died inside the migration with no error reported.',
			],
			$state['record']
		);
		$this->assertNotEmpty( $state['lock'] );
	}

	/**
	 * The same overwrite requirement on the handler's own write, which is the path that reaches it: an install
	 * whose migration threw is already carrying a record when the retry a TTL later dies of a budget instead.
	 * Leaving the earlier message in place would name a cause the request no longer has, and the notice is the
	 * only thing an administrator has to go on until they act on it.
	 */
	public function testShutdownHandlerReplacesAnEarlierFailureRecord() {
		update_option(
			MigrationRunner::PREF_MIGRATION_FAILURE,
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => MigrationRunner::CREATE_TABLES,
				'message' => 'the error the previous attempt reported',
			]
		);

		$state = $this->state_left_by_the_handler(
			[
				'type'    => E_ERROR,
				'message' => 'Allowed memory size of 134217728 bytes exhausted',
				'file'    => 'CreateTablesMigration.php',
				'line'    => 42,
			]
		);

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => MigrationRunner::CREATE_TABLES,
				'kind'    => 'fatal',
				'type'    => 'E_ERROR',
				'message' => 'Allowed memory size of 134217728 bytes exhausted',
			],
			$state['record']
		);
	}

	/**
	 * The arming is what scopes the handler to the migration. Once the runner has unwound, the failure path
	 * has already recorded what happened, so a fatal raised later in the same request - in a theme, in
	 * another plugin - must not overwrite that record with a message from somewhere else entirely, nor
	 * disturb the lock the failure path kept on purpose.
	 */
	public function testShutdownHandlerIsInertOnceTheRunnerHasUnwound() {
		$this->record_cursor();
		$this->record_setup( new \Exception( 'schema setup failed' ) );

		$service = $this->get_instance();
		$service->run();

		$service->record_failure_on_fatal(
			[
				'type'    => E_ERROR,
				'message' => 'Allowed memory size exhausted somewhere else entirely',
				'file'    => 'header.php',
				'line'    => 1,
			]
		);

		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => MigrationRunner::CREATE_TABLES,
				'kind'    => 'throwable',
				'type'    => 'Exception',
				'message' => 'schema setup failed',
			],
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE )
		);
		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * A migration that has returned and written its cursor did not kill the request. Disarming only in
	 * run()'s finally left the handler still naming it for the rest of the run, so a fatal in run()'s own
	 * option write, or in the next migration's constructor, was recorded against a migration that had
	 * already succeeded -- and past the last id that record is also unclearable, since every following
	 * request stops at the has_pending() gate.
	 *
	 * The success path's lock release is the seam: it is the last thing run() does, so a handler invoked
	 * from there is inside the window and nothing afterwards can delete what it wrote.
	 */
	public function testFatalAfterAMigrationSucceededRecordsNothing() {
		$this->record_cursor();
		$this->record_setup();

		$service = $this->get_instance();

		add_action(
			'delete_transient_' . MigrationRunner::MIGRATION_LOCK,
			function () use ( $service ) {
				$service->record_failure_on_fatal(
					[
						'type'    => E_ERROR,
						'message' => 'Allowed memory size of 134217728 bytes exhausted',
						'file'    => 'header.php',
						'line'    => 1,
					]
				);
			}
		);

		$service->run();

		$this->assertSame(
			$this->shipped_registry_calls(),
			$this->calls,
			'Precondition: the migration has to have returned and written its cursor.'
		);
		$this->assertFalse(
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE ),
			'A migration that already succeeded must not be named by a fatal raised after it.'
		);
	}

	/**
	 * A store run over REST or WP-CLI never sees an admin notice, and the notice's own record is overwritten
	 * by the next attempt. The log is the only account of a failure that survives the retry and reaches
	 * somebody who was not sitting in wp-admin, so it has to carry everything needed to diagnose one: which
	 * migration, what kind of failure, and where it came from.
	 *
	 * The source is asserted as a literal, not as the constant: renaming the constant's *value* renames the
	 * file in WooCommerce > Status > Logs, which is the one thing an administrator was told to look for.
	 */
	public function testThrownFailureIsLogged() {
		$this->record_cursor();
		$this->capture_log();

		// Deliberately not an Exception: the type is read off the throwable, never assumed.
		$boom = new \RuntimeException( 'ALTER TABLE denied for user' );
		$this->record_setup( $boom );

		$this->get_instance()->run();

		$this->assertSame(
			[
				[
					'message' => sprintf(
						'Migration alondra/%1$d failed (throwable RuntimeException): ALTER TABLE denied for user in %2$s:%3$d',
						MigrationRunner::CREATE_TABLES,
						$boom->getFile(),
						$boom->getLine()
					),
					'context' => [ 'source' => 'alondra-migrations' ],
				],
			],
			$this->log_lines
		);
	}

	/**
	 * The path with no exception to read: PHP reports an integer type and no class at all, so the log line
	 * has to name the error constant instead. Without this the two failure kinds are indistinguishable in the
	 * log, which was the whole reason for recording a kind.
	 *
	 * Asserted after the run has unwound, so a log line written on the success path would show up here too.
	 */
	public function testFatalFailureIsLogged() {
		$this->capture_log();

		$this->state_left_by_the_handler(
			[
				'type'    => E_ERROR,
				'message' => 'Maximum execution time of 5 seconds exceeded',
				'file'    => '/var/www/html/CreateTablesMigration.php',
				'line'    => 42,
			]
		);

		$this->assertSame(
			[
				[
					'message' => sprintf(
						'Migration alondra/%1$d failed (fatal E_ERROR): Maximum execution time of 5 seconds exceeded'
							. ' in /var/www/html/CreateTablesMigration.php:42',
						MigrationRunner::CREATE_TABLES
					),
					'context' => [ 'source' => 'alondra-migrations' ],
				],
			],
			$this->log_lines
		);
	}

	/**
	 * On the fatal path all three destinations run inside zend.hard_timeout's CPU grace, so their order is a
	 * decision about which one survives losing the rest. The option is first because it is the only one that
	 * reaches a shop owner who was never told to look in a log, and the only one a later success clears.
	 */
	public function testTheRecordIsStoredBeforeTheLogIsWritten() {
		$this->record_cursor();
		$this->capture_log();
		$this->record_setup( new \RuntimeException( 'ALTER TABLE denied for user' ) );

		$this->get_instance()->run();

		$this->assertTrue(
			$this->record_when_logged,
			'The admin notice must already have something to read by the time the log line is attempted.'
		);
	}

	/**
	 * The action is the only way a site can route a migration failure into its own monitoring without
	 * patching the plugin, so its payload is a contract: the id names the migration, the kind says which
	 * path failed, and the type is what makes a TypeError out of the database layer tellable from a
	 * deliberate RuntimeException.
	 */
	public function testThrownFailureFiresTheAction() {
		$this->record_cursor();
		$this->capture_action();
		$this->record_setup( new \RuntimeException( 'ALTER TABLE denied for user' ) );

		$this->get_instance()->run();

		$this->assertSame(
			[
				[
					MigrationRunner::CREATE_TABLES,
					'ALTER TABLE denied for user',
					'throwable',
					'RuntimeException',
					MigrationRunner::CHAIN,
				],
			],
			$this->action_payloads
		);
	}

	/**
	 * The same contract on the path a monitoring integration most wants to hear about: the request died
	 * where neither a catch nor a finally runs, so the action fired from the shutdown handler is the only
	 * notification of it that leaves the site.
	 */
	public function testFatalFailureFiresTheAction() {
		$this->capture_action();

		$this->state_left_by_the_handler(
			[
				'type'    => E_ERROR,
				'message' => 'Allowed memory size of 134217728 bytes exhausted',
				'file'    => '/var/www/html/CreateTablesMigration.php',
				'line'    => 42,
			]
		);

		$this->assertSame(
			[
				[
					MigrationRunner::CREATE_TABLES,
					'Allowed memory size of 134217728 bytes exhausted',
					'fatal',
					'E_ERROR',
					MigrationRunner::CHAIN,
				],
			],
			$this->action_payloads
		);
	}

	/**
	 * WooCommerce is a hard dependency, and this asserts the code survives losing it anyway: core hides the
	 * Deactivate link for a plugin others require but does not deactivate the dependents, and nothing gates
	 * WP-CLI, so an install can be running this on `init` on every request with wc_get_logger() gone.
	 *
	 * Asserted against the source rather than by behaviour on purpose: `function_exists()` takes a string, so
	 * it always resolves against the global namespace, and a suite whose bootstrap loads WooCommerce has no
	 * way to make that function absent. Reading the guard is the only thing left that fails when it is
	 * removed -- and it does fail, which is what this is for.
	 */
	public function testEveryLogWriteIsGuardedOnWooCommerceStillBeingLoaded() {
		$file = ( new \ReflectionClass( MigrationRunner::class ) )->getFileName();
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- reading this repository's own source file in a test, never a remote one.
		$source = (string) file_get_contents( (string) $file );

		// The arrow is part of the needle on purpose: a comment naming wc_get_logger() is not a call
		// site, and counting one as unguarded would fail this for prose.
		$calls = substr_count( $source, 'wc_get_logger()->' );
		$this->assertGreaterThan( 0, $calls, 'Precondition: there has to be a call to find a guard for.' );

		// Each call site is checked against the guard that opens closest above it, rather than against a
		// fixed source shape: the two differ -- one logs a migration failure directly, the other wraps its
		// write in a catch of its own -- and a regex pinning either shape would just have to be rewritten
		// the next time one moves.
		$guarded = 0;
		foreach ( explode( 'wc_get_logger()->', $source ) as $i => $before ) {
			if ( $i === $calls ) {
				break;
			}
			$guard = strrpos( $before, "function_exists( 'wc_get_logger' )" );
			if ( false !== $guard && false === strpos( substr( $before, $guard ), '}' ) ) {
				++$guarded;
			}
		}

		$this->assertSame( $calls, $guarded, 'Every wc_get_logger() call must sit inside a function_exists() guard.' );
	}

	/**
	 * run() is reached from `init`, where a throwable fatals every request on the site, storefront
	 * included. Its own try used to open only after the cursor read and the lock read and write, all of
	 * which are option and transient layer code a filter can make throw -- and the cursor read on `init` is
	 * the first one of the request, so it is the ordinary path into that layer.
	 *
	 * An \Error rather than an \Exception on purpose: a TypeError out of the option layer is exactly what a
	 * catch ( \Exception ) would miss. The throwable escaping surfaces here as a test error, not a failure.
	 */
	public function testRunDoesNotThrowWhenTheOptionLayerDoes() {
		add_filter(
			'pre_option_' . MigrationRunner::PREF_MIGRATION_ID,
			function () {
				throw new \TypeError( 'option layer blew up' );
			}
		);
		$this->expect_no_migration();

		$this->get_instance()->run();

		$this->assertTrue( true, 'run() returned instead of taking the request down.' );
	}

	/**
	 * The runner's own plumbing failing is not a migration failing: there is no id to name, the notice
	 * would promise a retry nothing here can promise, and the option store is the layer under suspicion.
	 * The log is the one channel that neither invents a migration nor writes to the thing that just broke.
	 */
	public function testARunnerFailureIsLoggedAndNotRecordedAsAMigrationFailure() {
		$this->capture_log();
		$this->capture_action();

		$boom = new \RuntimeException( 'option layer blew up' );
		add_filter(
			'pre_option_' . MigrationRunner::PREF_MIGRATION_ID,
			function () use ( $boom ) {
				throw $boom;
			}
		);

		$this->get_instance()->run();

		$this->assertSame(
			[
				[
					'message' => sprintf(
						'Migration runner failed outside any migration (RuntimeException): option layer blew up in %1$s:%2$d',
						$boom->getFile(),
						$boom->getLine()
					),
					'context' => [ 'source' => 'alondra-migrations' ],
				],
			],
			$this->log_lines
		);

		$this->assertFalse(
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE ),
			'There is no migration to name, so the notice must not be given one.'
		);
		$this->assertSame( [], $this->action_payloads, 'The action payload opens with a migration id there is none of.' );
	}

	/**
	 * Each chain reads and advances only its own cursor, so an add-on migrating its storage never moves
	 * free's, and free's head does not make the add-on's migrations look applied.
	 */
	public function testTwoChainsAdvanceIndependentCursors() {
		$this->record_cursor( 2, 0 );

		$this->get_instance_with_two_chains()->run();

		$this->assertSame( [ self::MIGRATION_THREE, 'cursor:' . self::ADDON_CURSOR . ':1' ], $this->calls );
		$this->assertSame(
			[
				MigrationRunner::PREF_MIGRATION_ID => 2,
				self::ADDON_CURSOR                 => 1,
			],
			$this->cursors
		);
	}

	/**
	 * Free's chain completes before the next one starts: an add-on migration may query free's schema.
	 */
	public function testFreeChainRunsBeforeTheSecondChain() {
		$this->record_cursor();

		$this->get_instance_with_two_chains()->run();

		$this->assertSame(
			[
				self::MIGRATION_ONE,
				'cursor:1',
				self::MIGRATION_TWO,
				'cursor:2',
				self::MIGRATION_THREE,
				'cursor:' . self::ADDON_CURSOR . ':1',
			],
			$this->calls
		);
		$this->assertEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * One lock covers the whole run, so a request holding it keeps every chain out, not just the one it is in.
	 */
	public function testHeldLockSkipsEveryChain() {
		$this->record_cursor();
		set_transient( MigrationRunner::MIGRATION_LOCK, true, HOUR_IN_SECONDS );

		$this->get_instance_with_two_chains()->run();

		$this->assertSame( [], $this->calls );
		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
	}

	/**
	 * The first failure ends the run: the chains after it may depend on the schema it did not finish.
	 */
	public function testFailureInFreeChainKeepsTheLockAndStopsTheRun() {
		$this->record_cursor();
		$this->migration_throws[ self::MIGRATION_TWO ] = new \Exception( 'free migration 2 failed' );

		$this->get_instance_with_two_chains()->run();

		$this->assertSame( [ self::MIGRATION_ONE, 'cursor:1', self::MIGRATION_TWO ], $this->calls );
		$this->assertSame( 1, $this->cursors[ MigrationRunner::PREF_MIGRATION_ID ] );
		$this->assertSame( 0, $this->cursors[ self::ADDON_CURSOR ] );
		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
		$this->assertSame(
			[
				'chain'   => MigrationRunner::CHAIN,
				'id'      => 2,
				'kind'    => 'throwable',
				'type'    => 'Exception',
				'message' => 'free migration 2 failed',
			],
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE )
		);
	}

	/**
	 * A failure in a later chain leaves free's completed work recorded and names the chain that failed.
	 */
	public function testFailureInSecondChainKeepsItsCursorAndLeavesFreesAdvanced() {
		$this->record_cursor();
		$this->capture_log();
		$this->capture_action();
		$boom = new \RuntimeException( 'addon migration failed' );

		$this->migration_throws[ self::MIGRATION_THREE ] = $boom;

		$this->get_instance_with_two_chains()->run();

		$this->assertSame(
			[ self::MIGRATION_ONE, 'cursor:1', self::MIGRATION_TWO, 'cursor:2', self::MIGRATION_THREE ],
			$this->calls
		);
		$this->assertSame( 2, $this->cursors[ MigrationRunner::PREF_MIGRATION_ID ] );
		$this->assertSame( 0, $this->cursors[ self::ADDON_CURSOR ] );
		$this->assertNotEmpty( get_transient( MigrationRunner::MIGRATION_LOCK ) );
		$this->assertSame(
			[
				'chain'   => self::ADDON_CHAIN,
				'id'      => 1,
				'kind'    => 'throwable',
				'type'    => 'RuntimeException',
				'message' => 'addon migration failed',
			],
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE )
		);
		$this->assertSame(
			sprintf( 'Migration addon/1 failed (throwable RuntimeException): addon migration failed in %1$s:%2$d', $boom->getFile(), $boom->getLine() ),
			$this->log_lines[0]['message']
		);
		$this->assertSame(
			[ [ 1, 'addon migration failed', 'throwable', 'RuntimeException', self::ADDON_CHAIN ] ],
			$this->action_payloads
		);
	}

	/**
	 * Ids are only unique within a chain, so the question has to name one; free's is the default.
	 */
	public function testIsAppliedAnswersPerChain() {
		$this->record_cursor( 2, 0 );

		$service = $this->get_instance_with_two_chains();

		$this->assertTrue( $service->is_applied( 1 ) );
		$this->assertTrue( $service->is_applied( 1, MigrationRunner::CHAIN ) );
		$this->assertFalse( $service->is_applied( 1, self::ADDON_CHAIN ) );
		$this->assertFalse( $service->is_applied( 1, 'no-such-chain' ), 'An unknown chain has nothing applied.' );
	}

	/**
	 * Pending in any chain is pending: free at its head does not hide an add-on migration still to run.
	 */
	public function testHasPendingWhenOnlyTheSecondChainIsBehind() {
		$this->record_cursor( 2, 0 );
		$this->assertTrue( $this->get_instance_with_two_chains()->has_pending() );

		$this->record_cursor( 2, 1 );
		$this->assertFalse( $this->get_instance_with_two_chains()->has_pending() );
	}

	/**
	 * The TTL is the only thing that heals a SIGKILLed worker, which runs no shutdown handler at all. An
	 * hour of serving unmigrated pages is too long to be that backstop.
	 */
	public function testLockTtlIsFiveMinutes() {
		$this->assertSame( 5 * 60, MigrationRunner::LOCK_TTL );

		$this->record_cursor();
		$this->record_setup( new \Exception( 'schema setup failed' ) );

		// A failure keeps the lock, which is what leaves an expiry behind to read.
		$this->get_instance()->run();

		$timeout = get_option( '_transient_timeout_' . MigrationRunner::MIGRATION_LOCK );

		$this->assertNotFalse( $timeout, 'The lock has to still be held for its expiry to mean anything.' );
		$this->assertEqualsWithDelta( time() + MigrationRunner::LOCK_TTL, (int) $timeout, 5 );
	}

	public function tear_down() {
		if ( $this->forbid_cursor_writes ) {
			$this->assertSame( 0, $this->cursor_writes, 'No cursor write was expected.' );
		}

		delete_transient( MigrationRunner::MIGRATION_LOCK );
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

		parent::tear_down();
	}
}
