<?php
/**
 * CreateTablesMigration Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Migrations;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\CreateTablesMigration;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Tests for the table-creation migration.
 */
class CreateTablesMigrationTest extends WP_UnitTestCase {

	use Container_Seam;

	private $container;
	private $pricing_service;

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		parent::set_up();

		$this->pricing_service = $this->createMock( TieredPricingService::class );

		$this->container = $this->createStub( Container::class );
		$this->container->method( 'get' )->willReturnMap(
			[
				[ TieredPricingService::class, $this->pricing_service ],
			]
		);
		$this->install_container( $this->container );
	}

	private function get_instance() {
		return new CreateTablesMigration();
	}

	/**
	 * The migration owns no schema of its own: it delegates to the service that already declares the tables.
	 */
	public function testExecuteDelegatesToTheServiceSetupOnce() {
		$this->pricing_service->expects( $this->once() )->method( 'setup' );

		$this->get_instance()->execute();
	}
}
