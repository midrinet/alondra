<?php
/**
 * AssetController Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Controllers;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\Controller\AssetController;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Covers asset registration and the inline style built from the preferences.
 */
class AssetControllerTest extends WP_UnitTestCase {

	use Container_Seam;

	private $container;
	private $preferences_service;

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		$this->preferences_service = $this->createMock( PreferencesService::class );

		$this->container = $this->createStub( Container::class );
		$this->container->method( 'get' )->willReturnMap(
			[
				[ PreferencesService::class, $this->preferences_service ],
				[ PluginInfo::class, Container::instance()->get( PluginInfo::class ) ],
			]
		);
		$this->install_container( $this->container );
	}

	private function get_instance() {
		return new AssetController();
	}

	/**
	 * Test case for get_assets_dir_url
	 */
	public function testGetAssetsDirUrl() {
		$this->assertEquals( $this->get_instance()->get_assets_dir_url(), plugin_dir_url( \dirname( __DIR__, 2 ) . '/alondra.php' ) . 'assets' );
	}

	/**
	 * The admin bundle is enqueued on the plugin's own screens.
	 */
	public function testEnqueueAdminOnPluginScreen() {
		$this->get_instance()->enqueue_admin( 'toplevel_page_alondra-tiered-pricing' );

		$this->assertTrue( wp_style_is( AssetController::HANDLE_ADMIN, 'enqueued' ) );
		$this->assertTrue( wp_script_is( AssetController::HANDLE_ADMIN, 'enqueued' ) );
	}

	/**
	 * On every other admin screen it is only registered, so the bundle is
	 * available on demand without being shipped to unrelated pages.
	 */
	public function testEnqueueAdminOnForeignScreenOnlyRegisters() {
		$this->get_instance()->enqueue_admin( 'edit.php' );

		$this->assertTrue( wp_style_is( AssetController::HANDLE_ADMIN, 'registered' ) );
		$this->assertTrue( wp_script_is( AssetController::HANDLE_ADMIN, 'registered' ) );
		$this->assertFalse( wp_style_is( AssetController::HANDLE_ADMIN, 'enqueued' ) );
		$this->assertFalse( wp_script_is( AssetController::HANDLE_ADMIN, 'enqueued' ) );
	}

	/**
	 * The front script is always registered; the stylesheet only when styles are
	 * not disabled.
	 *
	 * @dataProvider data_provider_single_boolean
	 */
	public function testEnqueueFront( $disable_styles ) {
		$this->preferences_service->expects( $this->exactly( 1 ) )
		->method( 'disable_styles' )
		->willReturn( $disable_styles );

		$this->get_instance()->enqueue_front();

		$this->assertSame( ! $disable_styles, wp_style_is( AssetController::HANDLE_FRONT, 'registered' ) );
		$this->assertTrue( wp_script_is( AssetController::HANDLE_FRONT, 'registered' ) );
	}

	/**
	 * Without the highlight preference the highlight colors fall back to the
	 * regular ones, so the active row is not visually distinct.
	 *
	 * @dataProvider data_provider_single_boolean
	 */
	public function testHighlightColorsFallBackWhenHighlightIsOff( $highlight ) {
		$this->preferences_service->method( 'disable_styles' )->willReturn( false );
		$this->preferences_service->method( 'highlight_prices' )->willReturn( $highlight );
		$this->preferences_service->method( 'get_color' )->willReturn( '#000000' );
		$this->preferences_service->method( 'get_bg_color' )->willReturn( '#ffffff' );
		$this->preferences_service->method( 'get_border_color' )->willReturn( '#111111' );
		$this->preferences_service->method( 'get_highlight_color' )->willReturn( '#ff0000' );
		$this->preferences_service->method( 'get_highlight_bg_color' )->willReturn( '#00ff00' );
		$this->preferences_service->method( 'get_highlight_border_color' )->willReturn( '#0000ff' );

		$this->get_instance()->enqueue_front();

		$inline = wp_styles()->get_data( AssetController::HANDLE_FRONT, 'after' );
		$this->assertIsArray( $inline );
		$css = implode( '', $inline );

		$this->assertStringContainsString( '--alondra-h-color: ' . ( $highlight ? '#ff0000' : '#000000' ), $css );
		$this->assertStringContainsString( '--alondra-h-bg-color: ' . ( $highlight ? '#00ff00' : '#ffffff' ), $css );
		$this->assertStringContainsString( '--alondra-h-bd-color: ' . ( $highlight ? '#0000ff' : '#111111' ), $css );
	}

	public function tear_down() {
		wp_deregister_style( AssetController::HANDLE_FRONT );
		wp_deregister_script( AssetController::HANDLE_FRONT );
		wp_dequeue_style( AssetController::HANDLE_ADMIN );
		wp_dequeue_script( AssetController::HANDLE_ADMIN );
		wp_deregister_style( AssetController::HANDLE_ADMIN );
		wp_deregister_script( AssetController::HANDLE_ADMIN );
	}

	public function data_provider_single_boolean() {
		return [
			[ true ],
			[ false ],
		];
	}
}
