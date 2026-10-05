<?php
/**
 * DI Container
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/DI
 */

namespace Midrinet\Alondra\Infrastructure\DI;

use Error;
use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;

/**
 * Resolves singletons from definitions, or by instantiating the class when it has none.
 *
 * A definition is a class-string, instantiated with no arguments, or a zero-argument callable. Only a class that
 * needs wiring has one.
 */
class Container {

	private static ?Container $instance = null;

	/**
	 * Definitions, keyed by class-string or by a `vendor/name` key.
	 *
	 * @var array<string, callable|class-string>
	 */
	private array $definitions;

	/**
	 * Instances already built, keyed like the definitions.
	 *
	 * @var array<string, object>
	 */
	private array $resolved = [];

	/**
	 * One-time build of the container. The `alondra_di_definitions` filter is applied here and only here.
	 *
	 * @param string $plugin_file Absolute path to the plugin's main file.
	 * @return Container
	 */
	public static function build( string $plugin_file ): Container {
		if ( null !== self::$instance ) {
			return self::$instance;
		}

		/**
		 * Add or replace container bindings. Read once, at plugins_loaded:20 or on activation.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, callable|class-string> $definitions Definitions keyed by class-string or name.
		 */
		$definitions = (array) apply_filters(
			'alondra_di_definitions',
			[
				\wpdb::class             => function () {
					global $wpdb;
					return $wpdb;
				},
				PluginInfo::class        => fn() => new PluginInfo( $plugin_file ),
				// Switched here rather than by its callers, which do not all go through TieredPricingService.
				TieredPricingRepo::class => function () {
					$repo = new TieredPricingRepo();
					$repo->use_cache( self::instance()->get( PreferencesService::class )->enable_cache() );
					return $repo;
				},
			]
		);

		self::$instance = new Container( $definitions );
		return self::$instance;
	}

	/**
	 * The built container.
	 *
	 * @throws \RuntimeException When build() has not run yet.
	 * @return Container
	 */
	public static function instance(): Container {
		if ( null === self::$instance ) {
			throw new \RuntimeException( 'Container has not been built yet.' );
		}
		return self::$instance;
	}

	/**
	 * Test-only seam: install a container, or null so the next build() runs again, and get the previous one back.
	 *
	 * @internal
	 *
	 * @param Container|null $container Container to install.
	 * @return Container|null
	 */
	public static function set_instance( ?Container $container ): ?Container {
		$previous       = self::$instance;
		self::$instance = $container;
		return $previous;
	}

	/**
	 * Public so tests can build one from their own definitions; production code goes through build().
	 *
	 * @param array<string, callable|class-string> $definitions Definitions keyed by class-string or name.
	 */
	public function __construct( array $definitions ) {
		$this->definitions = $definitions;
	}

	/**
	 * Get an instance, built on first use and cached after. A class with no definition is instantiated with no
	 * arguments.
	 *
	 * @template T of object
	 * @param class-string<T> $class_name Class to resolve.
	 * @throws Error If the class has no definition and does not exist, or the instance is not a $class_name.
	 * @return T
	 */
	public function get( string $class_name ) {
		$instance = $this->resolved[ $class_name ] ?? null;

		if ( null === $instance ) {
			if ( ! \array_key_exists( $class_name, $this->definitions ) && ! class_exists( $class_name ) ) {
				throw new Error( esc_html( "No binding found for class $class_name." ) );
			}

			$definition = $this->definitions[ $class_name ] ?? $class_name;

			/** @var object $instance */
			$instance                      = \is_callable( $definition ) ? $definition() : new $definition();
			$this->resolved[ $class_name ] = $instance;
		}

		// Checked on the cache-hit path too: a warmed cache must not hand back an unverified object.
		if ( ! $instance instanceof $class_name ) {
			throw new Error( esc_html( "Binding for class $class_name is not an instance of it." ) );
		}

		return $instance;
	}

	/**
	 * Get an instance bound under a plain key such as `alondra/freemius`, built on first use and cached after.
	 *
	 * Keys carry a `/`, which a class name cannot, so they never collide with a class-string in the shared
	 * cache. Only callable definitions are reachable this way: a key is not a class name to instantiate.
	 *
	 * @template T of object
	 * @param string          $key        Binding key.
	 * @param class-string<T> $class_name Expected class, checked at runtime and returned as the static type.
	 * @throws Error If no binding is found for the key, its definition is not callable, or the instance is not a $class_name.
	 * @return T
	 */
	public function get_named( string $key, string $class_name ) {
		$instance = $this->resolved[ $key ] ?? null;

		if ( null === $instance ) {
			if ( ! \array_key_exists( $key, $this->definitions ) ) {
				throw new Error( esc_html( "No binding found for key $key." ) );
			}

			$definition = $this->definitions[ $key ];
			if ( ! \is_callable( $definition ) ) {
				throw new Error( esc_html( "Binding for key $key is not callable: a key is not a class name, so it cannot be instantiated." ) );
			}

			/** @var object $instance */
			$instance               = $definition();
			$this->resolved[ $key ] = $instance;
		}

		// Checked on the cache-hit path too: two callers can expect different classes under one key.
		if ( ! $instance instanceof $class_name ) {
			throw new Error( esc_html( "Binding for key $key is not an instance of $class_name." ) );
		}

		return $instance;
	}
}
