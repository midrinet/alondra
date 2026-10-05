<?php
/**
 * ListTable Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Views;

use Midrinet\Alondra\Infrastructure\View\ListTable;
use Midrinet\Alondra\Infrastructure\View\TieredPricingListTableAdapter;
use WP_UnitTestCase;

/**
 * Covers the bulk action nonce gate. Bulk item ids are only read when it
 * passes, so a helper that answers the wrong way opens the write path.
 */
class ListTableTest extends WP_UnitTestCase {

	private const PLURAL = 'tiered_pricings';

	private function get_instance(): ListTable {
		$adapter = $this->createMock( TieredPricingListTableAdapter::class );
		$adapter->method( 'get_args_for_constructor' )->willReturn(
			[
				'plural'   => self::PLURAL,
				'singular' => 'tieredpricing',
				'ajax'     => false,
				'screen'   => 'toplevel_page_alondra',
			]
		);

		return new ListTable( $adapter );
	}

	public function tear_down() {
		unset( $_REQUEST['_wpnonce'] );
		parent::tear_down();
	}

	public function test_present_and_valid_nonce_is_verified() {
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'bulk-' . self::PLURAL );

		$this->assertTrue( $this->get_instance()->verify_bulk_action_nonce() );
	}

	public function test_missing_nonce_is_not_verified() {
		$this->assertFalse( $this->get_instance()->verify_bulk_action_nonce() );
	}

	public function test_invalid_nonce_is_not_verified() {
		$_REQUEST['_wpnonce'] = 'not-a-nonce';

		$this->assertFalse( $this->get_instance()->verify_bulk_action_nonce() );
	}

	public function test_nonce_created_for_another_action_is_not_verified() {
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'bulk-somethingelse' );

		$this->assertFalse( $this->get_instance()->verify_bulk_action_nonce() );
	}
}
