<?php
/**
 * ActivationController Tests
 *
 * Covers what the controller owns: the requirements gate, the `init` trigger, the version marker behind it,
 * the activation guard around setup() and the failure notice. The registry, the cursor and the lock are
 * covered in tests/Migrations/test-migration-service.php.
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Controllers;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\Controller\ActivationController;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Covers activation and the update-time migration path.
 */
class ActivationControllerTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Older than any release, so the gate opens whatever the header version is bumped to.
	 */
	private const VERSION_BEHIND = '0.0.1';

	private $container;
	private $pricing_service;
	private $migration_runner;

	/**
	 * Steps in the order they were invoked.
	 *
	 * @var string[]
	 */
	private $calls = [];

	public function set_up() {
		parent::set_up();

		// The failure record is written by the plugin's own controller on the bootstrap's `init`, which
		// happens before the first test opens its transaction, so it would survive one.
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

		$this->calls = [];

		$this->pricing_service  = $this->createMock( TieredPricingService::class );
		$this->migration_runner = $this->createMock( MigrationRunner::class );

		$this->migration_runner->method( 'run' )->willReturnCallback(
			function () {
				$this->calls[] = 'run';
			}
		);

		$this->container = $this->createStub( Container::class );
		$this->container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingService::class, $this->pricing_service ],
				[ MigrationRunner::class, $this->migration_runner ],

				[ PluginInfo::class, Container::instance()->get( PluginInfo::class ) ],
			]
		);
		$this->install_container( $this->container );
	}

	private function plugin_version(): string {
		return Container::instance()->get( PluginInfo::class )->get_plugin_version();
	}

	private function get_instance(): ActivationController {
		return new ActivationController();
	}

	/**
	 * Store the version marker, then record every later write to it, so the order against the runner is
	 * assertable.
	 *
	 * @param string $version Stored version; empty for none.
	 */
	private function store_version( string $version ) {
		if ( '' === $version ) {
			delete_option( ActivationController::PREF_VERSION );
		} else {
			update_option( ActivationController::PREF_VERSION, $version );
		}

		add_filter(
			'pre_update_option_' . ActivationController::PREF_VERSION,
			function ( $value ) {
				$this->calls[] = 'set_version:' . $value;

				return $value;
			}
		);
	}

	/**
	 * The test suite loads WooCommerce, so the requirement is met here.
	 */
	public function test_requirements_met_with_the_bundled_woocommerce() {
		$this->assertTrue( $this->get_instance()->requirements_met() );
	}

	/**
	 * Activation creates the tables.
	 */
	public function test_activate_sets_up_the_database() {
		$this->pricing_service->expects( $this->once() )->method( 'setup' );

		$this->get_instance()->activate();
	}

	/**
	 * The property the whole migration system exists for, asserted at the one place that used to break it.
	 *
	 * activate() calls setup() itself -- a deliberate second door, so a host without CREATE rights fails
	 * loudly during activation. What it must not do is record that migration 1 ran, because it does not go
	 * through the runner. A cursor written here would tell the next `init` there was nothing pending, and
	 * an install that reactivated the plugin after an update would have that update's schema work
	 * suppressed for good -- exactly the failure a version marker written at activation caused.
	 */
	public function test_activate_does_not_advance_the_migration_cursor() {
		$this->store_version( self::VERSION_BEHIND );
		$this->migration_runner->expects( $this->never() )->method( 'set_migration_id' );

		$this->get_instance()->activate();

		$this->assertSame( [], $this->calls );
	}

	/**
	 * A failing setup() must not leave the plugin half-installed without saying so.
	 */
	public function test_activate_dies_when_setup_fails() {
		$this->pricing_service->method( 'setup' )->willReturnCallback(
			function () {
				throw new \Exception( 'Error creating database table for Tiers' );
			}
		);

		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'Error creating database table for Tiers' );

		$this->get_instance()->activate();
	}

	/**
	 * The requirements notice is a warning notice, and rendering it leaves every
	 * plugin's activation state alone.
	 */
	public function test_requirements_notice_does_not_change_active_plugins() {
		$before = get_option( 'active_plugins' );

		ob_start();
		$this->get_instance()->print_requirements_notice();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'WooCommerce', $output );
		$this->assertSame( $before, get_option( 'active_plugins' ) );
	}

	/**
	 * The shipped wiring, asserted against the controller the bootstrap actually resolved rather than one
	 * built here: the registration moved out of the constructor and behind the requirements gate, so a
	 * locally constructed instance is hooked to nothing and could not tell the two shapes apart.
	 *
	 * The suite loads WooCommerce, so the gate is open and both hooks must be live. Priority 11 puts
	 * migrate() after WooCommerce's own `init` work, registered at priority 0, which the failure path
	 * needs for wc_get_logger().
	 */
	public function test_bootstrap_hooks_migrate_and_the_notice_on_a_supported_site() {
		$this->restore_container();
		$controller = Container::instance()->get( ActivationController::class );

		$this->assertSame( 11, has_action( 'init', [ $controller, 'migrate' ] ) );
		$this->assertNotFalse( has_action( 'admin_notices', [ $controller, 'show_migration_failure_notice' ] ) );
	}

	/**
	 * The gate is in the bootstrap, so what this can assert is the half that makes gating possible: a
	 * controller does not hook itself up merely by existing. It used to, from its constructor, which runs
	 * on every request whatever WooCommerce is doing -- so an install left active but unsupported went on
	 * migrating its schema for a build that had declared itself inert, and answered the requirements
	 * notice with a second notice contradicting it.
	 */
	public function test_constructing_a_controller_hooks_nothing() {
		$controller = $this->get_instance();

		$this->assertFalse( has_action( 'init', [ $controller, 'migrate' ] ) );
		$this->assertFalse( has_action( 'admin_notices', [ $controller, 'show_migration_failure_notice' ] ) );
	}

	/**
	 * migrate() is reached from `init`, where there is no wp_die() and a throwable fatals every request on
	 * the site, storefront included. The cursor read it opens with is the first trip into the option layer
	 * in the request, so a filter throwing there is the ordinary path, not a contrived one.
	 *
	 * Driven through the real service rather than the mock, because the guarantee belongs to run() and a
	 * mocked run() would assert nothing about it.
	 */
	public function test_migrate_does_not_throw_when_the_option_layer_does() {
		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ MigrationRunner::class, new MigrationRunner() ],
				[ PluginInfo::class, Container::instance()->get( PluginInfo::class ) ],
			]
		);
		$this->install_container( $container );

		$blow_up = function () {
			throw new \RuntimeException( 'option layer blew up' );
		};
		add_filter( 'pre_option_' . MigrationRunner::PREF_MIGRATION_ID, $blow_up );

		try {
			( new ActivationController() )->migrate();
		} finally {
			remove_filter( 'pre_option_' . MigrationRunner::PREF_MIGRATION_ID, $blow_up );
		}

		$this->assertTrue( true, 'migrate() returned instead of taking the request down.' );
	}

	/**
	 * Schema work is gated on the migration cursor, which the service owns, and never on the stored plugin
	 * version. A migration left behind by a failure has to run on a release that changed nothing else.
	 */
	public function test_runner_is_called_whatever_the_stored_version() {
		$this->store_version( $this->plugin_version() );
		$this->migration_runner->expects( $this->once() )->method( 'run' );

		$this->get_instance()->migrate();
	}

	/**
	 * The steady state. This runs on every request, so an install already carrying this release must
	 * write nothing.
	 */
	public function test_current_version_writes_no_marker() {
		$this->store_version( $this->plugin_version() );

		$this->get_instance()->migrate();

		$this->assertSame( [ 'run' ], $this->calls );
	}

	/**
	 * A downgrade must not write over the newer state a later build left behind.
	 */
	public function test_newer_stored_version_writes_no_marker() {
		// Greater than the header version whatever that is bumped to.
		$this->store_version( $this->plugin_version() . '.1' );

		$this->get_instance()->migrate();

		$this->assertSame( [ 'run' ], $this->calls );
	}

	/**
	 * The runner goes first, so anything a future release seeds here can depend on a migrated table.
	 */
	public function test_outdated_version_runs_the_runner_then_marks_the_version() {
		$this->store_version( self::VERSION_BEHIND );

		$this->get_instance()->migrate();

		$this->assertSame( [ 'run', 'set_version:' . $this->plugin_version() ], $this->calls );
	}

	/**
	 * A fresh install has no stored version; the read returns an empty string, which compares as older
	 * than any release, so the full path runs.
	 */
	public function test_absent_stored_version_marks_the_version() {
		$this->store_version( '' );

		$this->get_instance()->migrate();

		$this->assertSame( [ 'run', 'set_version:' . $this->plugin_version() ], $this->calls );
	}

	/**
	 * migrate() is hooked on `init`, where there is no wp_die() to turn a fatal into a recoverable error
	 * page. A throw escaping the marker path would take down every request on the site, the storefront
	 * included, and keep doing it -- nothing here advances state to stop it recurring.
	 *
	 * An \Error rather than an \Exception on purpose: a TypeError out of the option layer is exactly the
	 * case a catch ( \Exception ) would miss. The throwable escaping would surface here as a test error
	 * rather than a failure.
	 *
	 * No failure record for this path: it is not a migration, so PREF_MIGRATION_FAILURE would make the
	 * admin notice name the wrong thing.
	 */
	public function test_throw_while_marking_does_not_escape() {
		update_option( ActivationController::PREF_VERSION, self::VERSION_BEHIND );
		add_filter(
			'pre_update_option_' . ActivationController::PREF_VERSION,
			function () {
				$this->calls[] = 'set_version';

				throw new \TypeError( 'bad argument out of the option layer' );
			}
		);

		$this->get_instance()->migrate();

		$this->assertSame( [ 'run', 'set_version' ], $this->calls );
		$this->assertFalse(
			get_option( MigrationRunner::PREF_MIGRATION_FAILURE ),
			'A marker failure is not a migration failure and must not be recorded as one.'
		);
	}

	/**
	 * Log in as someone who passes the notice's capability gate.
	 *
	 * `manage_woocommerce` is not a WordPress capability: WooCommerce grants it to the administrator role
	 * from its own install routine, which a PHPUnit run only reaches as a side effect of loading the plugin.
	 * Granting it on the user keeps the gate under this test's control.
	 */
	private function login_as_shop_admin() {
		$user = self::factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user->ID );
	}

	/**
	 * Record a failure the way both runner paths do, so every notice test starts from the same state.
	 */
	private function record_failure() {
		update_option(
			MigrationRunner::PREF_MIGRATION_FAILURE,
			[
				'id'      => 7,
				'kind'    => 'throwable',
				'type'    => 'RuntimeException',
				'message' => 'Table wp_alondra_tiers is missing',
			]
		);
	}

	/**
	 * Put the request on one of the plugin's admin screens. The screen the notice checks is the page slug in
	 * the query string, the same identification the toolkit's PrefPage uses; WP_UnitTestCase empties $_GET
	 * in set_up(), so nothing has to undo this.
	 *
	 * @param string $slug Page slug.
	 */
	private function on_admin_page( $slug ) {
		$_GET['page'] = $slug;
	}

	/**
	 * What the notice is for: the schema gate makes the rules listing render empty, and only an explanation
	 * on that screen separates a failing migration from data loss. It says the tables are unavailable, that
	 * it is retrying itself, and where the detail lives -- and nothing more.
	 *
	 * Not dismissible on purpose. The record describes a live broken schema that is rewritten on the next
	 * request, so a dismissal would either be undone immediately or hide a database that is still broken.
	 */
	public function test_notice_explains_the_paused_state_on_the_plugin_screens() {
		$this->login_as_shop_admin();
		$this->record_failure();
		$this->on_admin_page( 'alondra-tiered-pricing' );

		$notice = get_echo( [ $this->get_instance(), 'show_migration_failure_notice' ] );

		$this->assertStringContainsString( 'notice-warning', $notice );
		$this->assertStringContainsString( 'retrying automatically', $notice );
		$this->assertStringContainsString( 'Status', $notice );
		$this->assertStringNotContainsString( 'is-dismissible', $notice );
	}

	/**
	 * The raw exception message is a support detail, not shop-owner copy. It goes to the WooCommerce log,
	 * and the migration id goes with it.
	 */
	public function test_notice_does_not_leak_the_raw_failure_detail() {
		$this->login_as_shop_admin();
		$this->record_failure();
		$this->on_admin_page( 'alondra-tiered-pricing' );

		$notice = get_echo( [ $this->get_instance(), 'show_migration_failure_notice' ] );

		$this->assertStringNotContainsString( 'Table wp_alondra_tiers is missing', $notice );
		$this->assertStringNotContainsString( 'RuntimeException', $notice );
		$this->assertStringNotContainsString( '7', $notice );
	}

	/**
	 * admin_notices fires on every admin screen, but the notice only explains screens this plugin renders.
	 * On WooCommerce's own screens, or anywhere with no page slug at all like the dashboard, it is noise.
	 */
	public function test_no_notice_on_an_unrelated_admin_screen() {
		$this->login_as_shop_admin();
		$this->record_failure();

		$this->assertSame( '', get_echo( [ $this->get_instance(), 'show_migration_failure_notice' ] ), 'Dashboard has no page slug.' );

		$this->on_admin_page( 'wc-orders' );
		$this->assertSame( '', get_echo( [ $this->get_instance(), 'show_migration_failure_notice' ] ) );
	}

	/**
	 * Gated on the capability the plugin's own admin surfaces already use, not on being logged in.
	 * A subscriber who reaches the screen gets the empty listing without the explanation.
	 */
	public function test_notice_is_hidden_from_users_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->record_failure();
		$this->on_admin_page( 'alondra-tiered-pricing' );

		$this->assertSame( '', get_echo( [ $this->get_instance(), 'show_migration_failure_notice' ] ) );
	}

	/**
	 * The steady state: a healthy install prints nothing on the plugin's own screens either.
	 */
	public function test_no_notice_without_a_failure_record() {
		$this->login_as_shop_admin();
		$this->on_admin_page( 'alondra-tiered-pricing' );

		$this->assertSame( '', get_echo( [ $this->get_instance(), 'show_migration_failure_notice' ] ) );
	}

	public function tear_down() {
		delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

		parent::tear_down();
	}
}
