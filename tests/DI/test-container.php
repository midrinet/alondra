<?php
/**
 * Container Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\DI;

use ArrayObject;
use Error;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use stdClass;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Unit tests for the DI container.
 */
class ContainerTest extends WP_UnitTestCase {

	use Container_Seam;

	public function test_a_class_string_definition_is_instantiated_once() {
		$container = new Container( [ ArrayObject::class => ArrayObject::class ] );

		$first = $container->get( ArrayObject::class );

		$this->assertInstanceOf( ArrayObject::class, $first );
		$this->assertSame( $first, $container->get( ArrayObject::class ) );
	}

	public function test_a_callable_definition_is_invoked_once_with_no_arguments() {
		$calls     = 0;
		$container = new Container(
			[
				stdClass::class => function ( ...$args ) use ( &$calls ) {
					++$calls;
					$object       = new stdClass();
					$object->args = $args;
					return $object;
				},
			]
		);

		$first = $container->get( stdClass::class );

		$this->assertSame( [], $first->args );
		$this->assertSame( $first, $container->get( stdClass::class ) );
		$this->assertSame( 1, $calls );
	}

	public function test_get_refuses_a_binding_of_another_class() {
		$container = new Container( [ ArrayObject::class => fn() => new stdClass() ] );

		$this->expectException( Error::class );
		$this->expectExceptionMessage( 'is not an instance of it' );

		$container->get( ArrayObject::class );
	}

	/**
	 * get_named() shares the cache with get(), so a key that is also a class name can be warmed with an
	 * instance of another class. The cache hit must be checked like a build.
	 */
	public function test_get_refuses_a_cached_instance_of_another_class() {
		$container = new Container( [ ArrayObject::class => fn() => new stdClass() ] );
		$container->get_named( ArrayObject::class, stdClass::class );

		$this->expectException( Error::class );
		$this->expectExceptionMessage( 'is not an instance of it' );

		$container->get( ArrayObject::class );
	}

	public function test_get_throws_for_a_class_that_does_not_exist() {
		$this->expectException( Error::class );
		$this->expectExceptionMessage( 'No binding found for class' );

		( new Container( [] ) )->get( 'Midrinet\\Alondra\\Tests\\DI\\Missing_Class' );
	}

	public function test_get_instantiates_a_class_with_no_definition_once() {
		$container = new Container( [] );

		$first = $container->get( ArrayObject::class );

		$this->assertInstanceOf( ArrayObject::class, $first );
		$this->assertSame( $first, $container->get( ArrayObject::class ) );
	}

	public function test_a_definition_wins_over_instantiating_the_class() {
		$object    = new ArrayObject( [ 'bound' ] );
		$container = new Container( [ ArrayObject::class => fn() => $object ] );

		$this->assertSame( $object, $container->get( ArrayObject::class ) );
	}

	public function test_get_named_returns_the_instance_bound_under_the_key() {
		$object    = new ArrayObject();
		$container = new Container( [ 'vendor/thing' => fn() => $object ] );

		$this->assertSame( $object, $container->get_named( 'vendor/thing', ArrayObject::class ) );
		$this->assertSame( $object, $container->get_named( 'vendor/thing', ArrayObject::class ) );
	}

	public function test_get_named_refuses_a_mismatched_class() {
		$container = new Container( [ 'vendor/thing' => fn() => new stdClass() ] );

		$this->expectException( Error::class );
		$this->expectExceptionMessage( 'is not an instance of ' . ArrayObject::class );

		$container->get_named( 'vendor/thing', ArrayObject::class );
	}

	/**
	 * A second caller expecting another class must not get the object the first caller cached.
	 */
	public function test_get_named_refuses_a_mismatched_class_on_a_cache_hit() {
		$container = new Container( [ 'vendor/thing' => fn() => new stdClass() ] );
		$container->get_named( 'vendor/thing', stdClass::class );

		$this->expectException( Error::class );

		$container->get_named( 'vendor/thing', ArrayObject::class );
	}

	public function test_get_named_refuses_a_definition_that_is_not_callable() {
		$container = new Container( [ 'vendor/thing' => ArrayObject::class ] );

		$this->expectException( Error::class );
		$this->expectExceptionMessage( 'is not callable' );

		$container->get_named( 'vendor/thing', ArrayObject::class );
	}

	public function test_get_named_throws_for_a_missing_binding() {
		$this->expectException( Error::class );
		$this->expectExceptionMessage( 'No binding found for key' );

		( new Container( [] ) )->get_named( 'vendor/thing', stdClass::class );
	}

	public function test_instance_throws_before_the_container_is_built() {
		$this->reset_container();

		$this->expectException( \RuntimeException::class );

		Container::instance();
	}

	public function test_build_applies_the_definitions_filter_once() {
		$this->reset_container();

		$applied = 0;
		$count   = function ( $definitions ) use ( &$applied ) {
			++$applied;
			return $definitions;
		};
		add_filter( 'alondra_di_definitions', $count );

		$plugin_file = \dirname( __DIR__, 2 ) . '/alondra.php';
		$first       = Container::build( $plugin_file );

		$this->assertSame( $first, Container::build( $plugin_file ) );
		$this->assertSame( $first, Container::instance() );
		$this->assertSame( 1, $applied );
	}
}
