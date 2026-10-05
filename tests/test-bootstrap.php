<?php
/**
 * Bootstrap Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests;

use Closure;
use Freemius;
use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\Controller\ActivationController;
use Midrinet\Alondra\Infrastructure\Controller\AssetController;
use Midrinet\Alondra\Infrastructure\Controller\PreferencesController;
use Midrinet\Alondra\Infrastructure\Controller\TieredPricingController;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use Midrinet\Alondra\Tests\Support\Recording_Controller;
use ReflectionFunction;
use WP_UnitTestCase;

require_once __DIR__ . '/Support/trait-container-seam.php';
require_once __DIR__ . '/Support/class-recording-controller.php';

/**
 * Covers alondra.php: the extension filters, the controller list, the activation hook and Freemius.
 *
 * The callbacks under test are the closures alondra.php registered when the suite loaded it, found by the
 * file that declares them, so what runs here is the shipped bootstrap and not a copy of it.
 */
class BootstrapTest extends WP_UnitTestCase {

	use Container_Seam;

	private function plugin_file(): string {
		return \dirname( __DIR__ ) . '/alondra.php';
	}

	/**
	 * The callback alondra.php hooked on the given hook and priority.
	 *
	 * @param string $hook     Hook name.
	 * @param int    $priority Priority it was added at.
	 * @return Closure
	 */
	private function bootstrap_callback( string $hook, int $priority ): Closure {
		global $wp_filter;

		foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] ?? [] as $callback ) {
			$function = $callback['function'];
			if ( $function instanceof Closure && ( new ReflectionFunction( $function ) )->getFileName() === $this->plugin_file() ) {
				return $function;
			}
		}

		$this->fail( "alondra.php registered nothing on {$hook} at priority {$priority}." );
	}

	/**
	 * Run the plugins_loaded:20 callback against a container that is not built yet.
	 *
	 * @return void
	 */
	private function boot() {
		$this->reset_container();
		$this->bootstrap_callback( 'plugins_loaded', 20 )();
	}

	/**
	 * Bind a Recording_Controller and add it to the controller list, as an add-on would.
	 *
	 * @return Recording_Controller
	 */
	private function add_recording_controller(): Recording_Controller {
		$recording = new Recording_Controller();

		add_filter(
			'alondra_di_definitions',
			function ( array $definitions ) use ( $recording ) {
				$definitions[ Recording_Controller::class ] = fn() => $recording;
				return $definitions;
			}
		);
		add_filter(
			'alondra_controllers',
			function ( array $controllers ) {
				$controllers[] = Recording_Controller::class;
				return $controllers;
			}
		);

		return $recording;
	}

	public function test_a_binding_replaced_through_the_filter_wins() {
		$service = $this->createStub( TieredPricingService::class );
		add_filter(
			'alondra_di_definitions',
			function ( array $definitions ) use ( $service ) {
				$definitions[ TieredPricingService::class ] = fn() => $service;
				return $definitions;
			}
		);

		$this->boot();

		$this->assertSame( $service, Container::instance()->get( TieredPricingService::class ) );
	}

	public function test_a_controller_added_through_the_filter_is_registered() {
		$recording = $this->add_recording_controller();

		$this->boot();

		$this->assertSame( 1, $recording->registered );
	}

	public function test_an_entry_that_is_not_a_controller_is_skipped() {
		$this->setExpectedIncorrectUsage( 'alondra_controllers' );
		add_filter(
			'alondra_controllers',
			function ( array $controllers ) {
				$controllers[] = \stdClass::class;
				$controllers[] = 42;
				return $controllers;
			}
		);
		$recording = $this->add_recording_controller();

		$this->boot();

		$this->assertSame( 1, $recording->registered, 'The entries after a bad one still register.' );
	}

	/**
	 * Nothing is read at include time: the bootstrap test itself adds its listeners long after alondra.php was
	 * included, and they apply. What the one read cannot see is a listener added after it.
	 */
	public function test_listeners_apply_until_the_build_and_not_after() {
		$recording = $this->add_recording_controller();

		$this->boot();

		$late = $this->createStub( TieredPricingService::class );
		add_filter(
			'alondra_di_definitions',
			function ( array $definitions ) use ( $late ) {
				$definitions[ TieredPricingService::class ] = fn() => $late;
				return $definitions;
			}
		);

		$this->assertSame( 1, $recording->registered );
		$this->assertNotSame( $late, Container::instance()->get( TieredPricingService::class ) );
	}

	public function test_with_no_listener_the_plugin_registers_exactly_its_own_controllers() {
		$seen = null;
		add_filter(
			'alondra_controllers',
			function ( array $controllers ) use ( &$seen ) {
				$seen = $controllers;
				return $controllers;
			},
			PHP_INT_MAX
		);

		$this->boot();

		$this->assertSame( [ ActivationController::class, AssetController::class, TieredPricingController::class, PreferencesController::class ], $seen );

		$container = Container::instance();
		$this->assertSame( 11, has_action( 'init', [ $container->get( ActivationController::class ), 'migrate' ] ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', [ $container->get( AssetController::class ), 'enqueue_admin' ] ) );
		$this->assertNotFalse( has_action( 'rest_api_init', [ $container->get( TieredPricingController::class ), 'register_rest_routes' ] ) );
	}

	/**
	 * The activation request includes the plugin file after plugins_loaded has fired, so nothing has built the
	 * container by the time the hook runs.
	 */
	public function test_activation_on_a_clean_install_builds_the_container_on_demand() {
		$activation = $this->createMock( ActivationController::class );
		$activation->expects( $this->once() )->method( 'activate' );
		add_filter(
			'alondra_di_definitions',
			function ( array $definitions ) use ( $activation ) {
				$definitions[ ActivationController::class ] = fn() => $activation;
				return $definitions;
			}
		);
		$this->reset_container();

		$this->bootstrap_callback( 'activate_' . plugin_basename( $this->plugin_file() ), 10 )();

		$this->assertSame( $activation, Container::instance()->get( ActivationController::class ) );
	}

	public function test_the_freemius_instance_resolves_under_its_named_key() {
		$fs = Container::instance()->get_named( 'alondra/freemius', Freemius::class );

		$this->assertSame( 'alondra', $fs->get_slug() );
		$this->assertGreaterThan( 0, did_action( 'alondra_loaded' ) );
	}
}
