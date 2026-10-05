<?php
/**
 * The Tiered Pricing List Table class
 *
 * @package Midrinet\Alondra\Infrastructure\View
 */

namespace Midrinet\Alondra\Infrastructure\View;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\Controller\TieredPricingController;
use Midrinet\Alondra\Infrastructure\DI\Container;

class TieredPricingListTableAdapter {

	private const DEFAULT_PER_PAGE = 20;
	private const SINGULAR         = 'tiered_pricing';
	private const PLURAL           = 'tiered_pricings';
	private const VIEW_ALL         = 'all';

	/**
	 * Current page items
	 *
	 * @since    1.0.0
	 *
	 * @var      TieredPricing[]
	 */
	private array $page_items = [];

	/**
	 * Total items
	 *
	 * @since    1.0.0
	 */
	private int $total_items = 0;

	/**
	 * Page size
	 *
	 * @since    1.0.0
	 *
	 * @var      int
	 */
	private int $per_page = self::DEFAULT_PER_PAGE;

	/**
	 * Per page option name
	 *
	 * @since    1.0.0
	 */
	private string $per_page_option = '';

	/**
	 * Page number
	 *
	 * @since    1.0.0
	 */
	private int $page = 0;

	/**
	 * Page slug in WordPress admin
	 *
	 * @since    1.0.0
	 */
	private string $page_slug = '';

	/**
	 * Get total items
	 *
	 * @return int
	 */
	public function get_total_items() {
		return $this->total_items;
	}

	/**
	 * Get current page items
	 *
	 * @return TieredPricing[]
	 */
	public function get_page_items() {
		return $this->page_items;
	}

	/**
	 * Get page size
	 *
	 * @return int
	 */
	public function get_per_page() {
		return $this->per_page;
	}

	/**
	 * Set page size
	 *
	 * @param int $per_page Page size.
	 * @return void
	 */
	public function set_per_page( int $per_page ) {
		$this->per_page = $per_page;
	}

	/**
	 * Set page slug
	 *
	 * @param string $page_slug Page slug.
	 * @return void
	 */
	public function set_page_slug( $page_slug ) {
		$this->page_slug = $page_slug;
	}

	/**
	 * Set per page option name
	 *
	 * @param string $option_name Option name.
	 * @return void
	 */
	public function set_per_page_option( string $option_name ) {
		$this->per_page_option = $option_name;
		// Required to save per page option.
		add_filter( "set_screen_option_{$option_name}", [ $this, 'filter_get_per_page' ], 10, 3 );
	}

	/**
	 * Filter get per page
	 *
	 * @param mixed  $status Status.
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return int
	 */
	public function filter_get_per_page( $status, $option, $value ) {
		if ( ! empty( $value ) && is_numeric( $value ) ) {
			$this->set_per_page( (int) $value );
		}
		return $this->get_per_page();
	}

	/**
	 * Get per page option name
	 *
	 * @return string
	 */
	public function get_per_page_option() {
		return $this->per_page_option;
	}

	/**
	 * Get page number
	 *
	 * @return int
	 */
	public function get_page() {
		return $this->page;
	}

	/**
	 * Prepare items for table
	 *
	 * @param int $per_page Current page size.
	 * @return TieredPricing[]
	 */
	public function get_items( $per_page ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return [];
		}

		$status = $this->get_current_view();

		// phpcs:disable WordPress.Security.NonceVerification -- sorting, paging and search only choose what to read; the capability check above is the gate.
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['orderby'] ) ) : 'id'; // @phpstan-ignore cast.string
		$order   = ! empty( $_GET['order'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['order'] ) ) : 'asc'; // @phpstan-ignore cast.string
		$page    = ! empty( $_POST['paged'] ) ? (int) $_POST['paged'] : ( ! empty( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // @phpstan-ignore-line
		$search  = ! empty( $_POST['s'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['s'] ) ) : ''; // @phpstan-ignore cast.string
		// phpcs:enable WordPress.Security.NonceVerification

		$paged             = Container::instance()->get( TieredPricingService::class )->get_paged_results( $search, ( self::VIEW_ALL === $status ? null : $status ), $page, $per_page, $orderby, $order );
		$this->page_items  = $paged['items'];
		$this->total_items = $paged['count'];
		$this->per_page    = $per_page;
		$this->page        = $page;

		return $this->get_page_items();
	}

	/**
	 * Get table columns
	 *
	 * @param array<string, string> $columns Default provided columns.
	 * @return array<string, string>
	 */
	public function get_columns( $columns ) {
		$columns['title']  = esc_html__( 'Title', 'alondra' );
		$columns['status'] = esc_html__( 'Status', 'alondra' );
		$columns['date']   = esc_html__( 'Date', 'alondra' );
		return $columns;
	}

	/**
	 * Get hidden columns
	 *
	 * @return array<string, string>
	 */
	public function get_hidden() {
		return [];
	}

	/**
	 * Get sortable columns
	 *
	 * The format is:
	 * - `'internal-name' => 'orderby'`
	 * - `'internal-name' => [ 'orderby', 'asc' ]` - The second element sets the initial sorting order.
	 * - `'internal-name' => [ 'orderby', true ]`  - The second element makes the initial order descending.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function get_sortable() {
		return [
			'title'  => [ 'title', 'asc' ],
			'status' => [ 'status', 'asc' ],
			'date'   => [ 'date', 'asc' ],
		];
	}

	/**
	 * Return a data from item for column
	 *
	 * @param TieredPricing $item The item.
	 * @param string         $column_name The column name.
	 * @return string
	 */
	public function get_column_data( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return \sprintf(
					'<strong><a class="row-title" href="?page=%1$s&action=%2$s&id=%3$s" aria-label="%4$s (%5$s)">%4$s</a></strong>', 
					esc_attr( $this->page_slug ),
					'edit',
					esc_attr( (string) $item->id ),
					esc_attr( $item->title ),
					esc_attr__( 'Edit', 'alondra' ) 
				);
			case 'status':
				return $this->output_status( $item->status );
			case 'date':
				return $item->get_date_in_current_tz();
			default:
				return '';
		}
	}

	/**
	 * Build the listing URL for an action on an item.
	 *
	 * @param string $action Action. See TieredPricingController::ACTION_*.
	 * @param int    $id     Item ID.
	 * @return string
	 */
	private function action_url( $action, $id ) {
		return \sprintf( '?page=%s&action=%s&id=%d', $this->page_slug, $action, $id );
	}

	/**
	 * Build a nonced link for an action that changes an item.
	 *
	 * @param string $action Action. See TieredPricingController::ACTION_*.
	 * @param int    $id     Item ID.
	 * @param string $label  Link text.
	 * @return string
	 */
	private function write_action_link( $action, $id, $label ) {
		$url = wp_nonce_url( $this->action_url( $action, $id ), \sprintf( TieredPricingController::ROW_ACTION_NONCE, $action ) );
		return \sprintf( '<a href="%s">%s</a>', $url, $label );
	}

	/**
	 * Get column actions if any
	 *
	 * @param TieredPricing $item The item.
	 * @param string         $column_name The column name.
	 * @return array<string, string>|false
	 */
	public function get_column_actions( $item, $column_name ) {
		if ( 'title' === $column_name ) {
			$id      = (int) $item->id;
			$options = [
				TieredPricingController::ACTION_EDIT    => \sprintf( '<a href="%s">%s</a>', esc_url( $this->action_url( TieredPricingController::ACTION_EDIT, $id ) ), esc_html__( 'Edit', 'alondra' ) ),
				TieredPricingController::ACTION_DRAFT   => $this->write_action_link( TieredPricingController::ACTION_DRAFT, $id, esc_html__( 'Unpublish', 'alondra' ) ),
				TieredPricingController::ACTION_TRASH   => $this->write_action_link( TieredPricingController::ACTION_TRASH, $id, esc_html__( 'Trash', 'alondra' ) ),
				TieredPricingController::ACTION_UNTRASH => $this->write_action_link( TieredPricingController::ACTION_UNTRASH, $id, esc_html__( 'Restore', 'alondra' ) ),
				TieredPricingController::ACTION_DELETE  => $this->write_action_link( TieredPricingController::ACTION_DELETE, $id, esc_html__( 'Delete Permanently', 'alondra' ) ),
			];

			switch ( $item->status ) {
				case TieredPricing::STATUS_PUBLISH:
					unset( $options[ TieredPricingController::ACTION_UNTRASH ] );
					unset( $options[ TieredPricingController::ACTION_DELETE ] );
					break;
				case TieredPricing::STATUS_TRASH:
					unset( $options[ TieredPricingController::ACTION_DRAFT ] );
					unset( $options[ TieredPricingController::ACTION_EDIT ] );
					unset( $options[ TieredPricingController::ACTION_TRASH ] );
					break;
				default:
					unset( $options[ TieredPricingController::ACTION_DRAFT ] );
					unset( $options[ TieredPricingController::ACTION_UNTRASH ] );
					unset( $options[ TieredPricingController::ACTION_DELETE ] );
					break;
			}

			return $options;
		}
		return false;
	}

	/**
	 * Return a data from item for column
	 *
	 * @param TieredPricing $item The item.
	 * @param string         $cb Default checkbox HTML provided by ListTable.
	 * @return string
	 */
	public function column_cb( $item, $cb ) {
		return \sprintf( $cb, (string) $item->id );
	}

	/**
	 * Get args for constructor
	 *
	 * @return array<string, mixed>|string {
	 *     Array or string of arguments.
	 *
	 *     @type string $plural   Plural value used for labels and the objects being listed.
	 *                            This affects things such as CSS class-names and nonces used
	 *                            in the list table, e.g. 'posts'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'post'.
	 *                            Default empty
	 *     @type bool   $ajax     Whether the list table supports Ajax. This includes loading
	 *                            and sorting data, for example. If true, the class will call
	 *                            the _js_vars() method in the footer to provide variables
	 *                            to any scripts handling Ajax events. Default false.
	 *     @type string $screen   String containing the hook name used to determine the current
	 *                            screen. If left null, the current screen will be automatically set.
	 *                            Default null.
	 * }
	 */
	public function get_args_for_constructor() {
		return [
			'singular' => self::SINGULAR,
			'plural'   => self::PLURAL,
			'ajax'     => false,
		];
	}

	/**
	 * Get current view
	 *
	 * @return string
	 */
	protected function get_current_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- picks which status to list, changes nothing.
		return isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['status'] ) ) : self::VIEW_ALL; // @phpstan-ignore cast.string
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
	 *     ]
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions() {
		$current_view = $this->get_current_view();
		switch ( $current_view ) {
			case TieredPricing::STATUS_TRASH:
				return [
					TieredPricingController::ACTION_UNTRASH_ALL => __( 'Restore', 'alondra' ),
					TieredPricingController::ACTION_DELETE_ALL  => __( 'Delete Permanently', 'alondra' ),
				];
			case TieredPricing::STATUS_PUBLISH:
				return [
					TieredPricingController::ACTION_DRAFT_ALL => __( 'Unpublish', 'alondra' ),
					TieredPricingController::ACTION_TRASH_ALL => __( 'Move to Trash', 'alondra' ),
				];
			default:
				return [
					TieredPricingController::ACTION_TRASH_ALL => __( 'Move to Trash', 'alondra' ),
				];
		}
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
	public function get_views() {
		$views        = [];
		$current_view = $this->get_current_view();
		$status_i18n  = [
			self::VIEW_ALL                => __( 'All', 'alondra' ), // shows tiered pricing with status publish and draft.
			TieredPricing::STATUS_PUBLISH => __( 'Published', 'alondra' ),
			TieredPricing::STATUS_DRAFT   => __( 'Unpublished', 'alondra' ),
			TieredPricing::STATUS_TRASH   => _x( 'Trash', 'post status', 'alondra' ),
		];

		foreach ( [ self::VIEW_ALL, TieredPricing::STATUS_PUBLISH, TieredPricing::STATUS_DRAFT, TieredPricing::STATUS_TRASH ] as $status ) {
			$url_param        = self::VIEW_ALL === $status ? '' : "&status=$status";
			$html             = $status === $current_view ? ' class="current" aria-current="page"' : '';
			$count            = Container::instance()->get( TieredPricingService::class )->count_total_results( '', self::VIEW_ALL === $status ? null : $status );
			$views[ $status ] = \sprintf( '<a href="%s" %s>%s (%d)</a>', esc_url( "?page=$this->page_slug$url_param" ), $html, esc_html( $status_i18n[ $status ] ), $count );
		}

		return $views;
	}

	/**
	 * Generate HTML for status column in table
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function output_status( $status ) {
		switch ( $status ) {
			case TieredPricing::STATUS_PUBLISH:
				return '<span class="midri-wtp__status midri-wtp__status--publish" data-tip="' . esc_attr__( 'Published', 'alondra' ) . '">' . esc_html__( 'Published', 'alondra' ) . '</span>';
			case TieredPricing::STATUS_DRAFT:
				return '<span class="midri-wtp__status midri-wtp__status--draft" data-tip="' . esc_attr__( 'Unpublished', 'alondra' ) . '">' . esc_html__( 'Unpublished', 'alondra' ) . '</span>';
			case TieredPricing::STATUS_TRASH:
				return '<span class="midri-wtp__status midri-wtp__status--trash" data-tip="' . esc_attr_x( 'Trash', 'post status', 'alondra' ) . '">' . esc_html_x( 'Trash', 'post status', 'alondra' ) . '</span>';
			default:
				return '<span class="midri-wtp__status" data-tip="' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
		}
	}
}
