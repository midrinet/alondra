<?php
/**
 * The Tiered Pricing List Table class
 *
 * @package Midrinet\Alondra\Infrastructure\View
 */

namespace Midrinet\Alondra\Infrastructure\View;

use Midrinet\Alondra\Domain\Entity\TieredPricing;

\defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ListTable extends \WP_List_Table {

	private TieredPricingListTableAdapter $adapter;

	/**
	 * @param TieredPricingListTableAdapter $adapter Adapter with methods required by the list table.
	 */
	public function __construct( $adapter ) {
		/** @var array{plural?: string, singular?: string, ajax?: bool, screen?: string} $args */
		$args = $adapter->get_args_for_constructor();
		parent::__construct( $args );
		$this->adapter = $adapter;
	}

	/**
	 * Check if bulk action nonce is present and valid.
	 *
	 * @return bool
	 */
	public function verify_bulk_action_nonce() {
		// @phpstan-ignore cast.string
		return isset( $_REQUEST['_wpnonce'] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) ), 'bulk-' . $this->_args['plural'] );
	}

	/**
	 * Gets a list of columns.
	 *
	 * The format is:
	 * - `'internal-name' => 'Title'`
	 *
	 * @override
	 * @return array<string, string>
	 */
	public function get_columns() {
		$columns = [ 'cb' => '<input type="checkbox" />' ];
		return $this->adapter->get_columns( $columns );
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @uses WP_List_Table::set_pagination_args()
	 * @override
	 * @return void
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = $this->adapter->get_hidden();
		$sortable              = $this->adapter->get_sortable();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$per_page = $this->get_items_per_page( $this->adapter->get_per_page_option(), $this->adapter->get_per_page() );

		$this->items = $this->adapter->get_items( $per_page );

		$total_items = $this->adapter->get_total_items();

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			]
		);
	}

	/**
	 * Gets a list of sortable columns.
	 *
	 * The format is:
	 * - `'internal-name' => 'orderby'`
	 * - `'internal-name' => [ 'orderby', 'asc' ]` - The second element sets the initial sorting order.
	 * - `'internal-name' => [ 'orderby', true ]`  - The second element makes the initial order descending.
	 *
	 * @since 3.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_sortable_columns() {
		return $this->adapter->get_sortable();
	}

	/**
	 * Return a data from item for column
	 *
	 * @param TieredPricing $item The item.
	 * @param string $column_name The column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		$text    = $this->adapter->get_column_data( $item, $column_name );
		$actions = $this->adapter->get_column_actions( $item, $column_name );
		if ( ! empty( $actions ) ) {
			$text = \sprintf( '%1$s %2$s', $text, $this->row_actions( $actions ) );
		}
		return $text;
	}

	/**
	 * Return a checkbox for column
	 *
	 * @param TieredPricing $item The item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		$cb = '<input type="checkbox" name="element[]" value="%s" />';
		return $this->adapter->column_cb( $item, $cb );
	}

	/**
	 * Retrieves the list of bulk actions available for this table.
	 *
	 * The format is an associative array where each element represents either a top level option value and label, or
	 * an array representing an optgroup and its options.
	 *
	 * For a standard option, the array element key is the field value and the array element value is the field label.
	 *
	 * For an optgroup, the array element key is the label and the array element value is an associative array of
	 * options as above.
	 *
	 * Example:
	 *
	 *     [
	 *         'edit'         => 'Edit',
	 *         'delete'       => 'Delete',
	 *         'Change State' => [
	 *             'feature' => 'Featured',
	 *             'sale'    => 'On Sale',
	 *         ]
	 *     ]
	 *
	 * @since 3.1.0
	 * @since 5.6.0 A bulk action can now contain an array of options in order to create an optgroup.
	 *
	 * @return array<string, string|array<string, string>>
	 */
	protected function get_bulk_actions() {
		return $this->adapter->get_bulk_actions();
	}

	/**
	 * Gets the list of views available on this table.
	 *
	 * The format is an associative array:
	 * - `'id' => 'link'`
	 *
	 * @since 3.1.0
	 *
	 * @return array<string, string>
	 */
	protected function get_views() {
		return $this->adapter->get_views();
	}
}
