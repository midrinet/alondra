<?php
/**
 * The Tiered Prices Controller
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Controller
 */

namespace Midrinet\Alondra\Infrastructure\Controller;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\View\Admin\TieredPricingEditView;
use Midrinet\Alondra\Infrastructure\View\Admin\TieredPricingListTableView;
use Midrinet\Alondra\Infrastructure\View\Admin\TitlebarOptionsEditView;
use Midrinet\Alondra\Infrastructure\View\Admin\TitlebarOptionsListView;
use Midrinet\Alondra\Infrastructure\View\Admin\TitlebarOptionsTrashView;
use Midrinet\Alondra\Infrastructure\View\Front\PricingLayoutViewFactory;
use Midrinet\Alondra\Infrastructure\View\ListTable;
use Midrinet\Alondra\Infrastructure\View\TieredPricingListTableAdapter;
use Midrinet\Alondra\Infrastructure\View\Toolkit\LoaderTemplate;
use Midrinet\Alondra\Infrastructure\View\Toolkit\PrefPage;
use Midrinet\Alondra\Infrastructure\View\Toolkit\Toolkit;
use WP_Error;
use WP_REST_Response;

/**
 * The Tiered Prices Controller
 *
 * @since      1.0.0
 */
class TieredPricingController extends Controller {

	public const REST_NAMESPACE = 'alondra/v1';

	public const ACTION_EDIT        = 'edit';
	private const ACTION_ADD        = 'add';
	public const ACTION_DELETE      = 'delete';
	public const ACTION_DRAFT       = 'draft';
	public const ACTION_TRASH       = 'trash';
	public const ACTION_UNTRASH     = 'untrash';
	public const ACTION_DELETE_ALL  = 'delete_all';
	public const ACTION_DRAFT_ALL   = 'draft_all';
	public const ACTION_TRASH_ALL   = 'trash_all';
	public const ACTION_UNTRASH_ALL = 'untrash_all';

	// Nonce action for a single row action, %s being the action.
	public const ROW_ACTION_NONCE = 'alondra_row_%s';

	private const TIERED_PRICING_PAGE_ID   = 'alondra_tiered_pricing';
	private const TIERED_PRICING_PAGE_SLUG = 'alondra-tiered-pricing';
	private const PER_PAGE_OPTION          = 'alondra_tiered_pricing_per_page';

	private ?TieredPricingListTableAdapter $list_table_adapter = null;

	/**
	 * Each cart line's price before the first repricing in this request, with the product it was read from,
	 * keyed by cart item key. WooCommerce can total the cart more than once per request, and by then the
	 * product already carries the tier.
	 *
	 * @var array<string, array{product: \WC_Product, price: float}>
	 */
	private array $cart_basis = [];

	private function get_tiered_pricing_service(): TieredPricingService {
		return Container::instance()->get( TieredPricingService::class );
	}

	private function get_preference_service(): PreferencesService {
		return Container::instance()->get( PreferencesService::class );
	}

	private function get_list_table_adapter(): TieredPricingListTableAdapter {
		if ( ! $this->list_table_adapter ) {    
			$adapter = Container::instance()->get( TieredPricingListTableAdapter::class );
			$adapter->set_per_page_option( self::PER_PAGE_OPTION );
			$adapter->set_page_slug( self::TIERED_PRICING_PAGE_SLUG );
			$this->list_table_adapter = $adapter;
		}
		return $this->list_table_adapter;
	}

	/**
	 * Template loader, passed to alondra_components for its listeners. Free renders through views.
	 */
	public function get_loader(): LoaderTemplate {
		return Container::instance()->get( LoaderTemplate::class );
	}

	/**
	 * Register the admin screen, REST routes, pricing filters and cache invalidation.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		add_action( 'init', [ $this, 'register_product_tiers_render' ] );
		add_filter( 'woocommerce_available_variation', [ $this, 'render_variation_tiers' ], 99, 3 );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_tiered_prices' ] );

		add_filter( 'woocommerce_get_price_html', [ $this, 'set_product_price' ], 99, 2 );
		add_filter( 'woocommerce_variable_price_html', [ $this, 'set_variable_price' ], 99, 2 );

		add_filter( 'alondra_components', [ $this, 'register_tiered_pricing_listing_page' ] );

		// Cache invalidation. Rules, roles and product terms are the three inputs to matching,
		// and the repo already covers rules from its own writes.
		add_action( 'set_user_role', [ $this, 'flush_cache' ] );
		add_action( 'add_user_role', [ $this, 'flush_cache' ] );
		add_action( 'remove_user_role', [ $this, 'flush_cache' ] );
		add_action( 'set_object_terms', [ $this, 'flush_cache_on_terms_set' ], 10, 6 );
		add_action( 'deleted_term_relationships', [ $this, 'flush_cache_on_terms_removed' ], 10, 3 );

		// Late init of toolkit.
		add_action( 'init', [ $this, 'register_components' ], 11 );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/tiered-pricing',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_tiered_pricing' ],
					'permission_callback' => [ $this, 'check_rest_auth' ],
					'args'                => $this->list_tiered_pricing_args(),
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'add_tiered_pricing' ],
					'permission_callback' => [ $this, 'check_rest_auth' ],
					'args'                => $this->add_tiered_pricing_args(),
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_tiered_pricing' ],
					'permission_callback' => [ $this, 'check_rest_auth' ],
					'args'                => $this->update_tiered_pricing_args(),
				],
			]
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/tiered-pricing/product',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_products' ],
				'permission_callback' => [ $this, 'check_rest_auth' ],
				'args'                => $this->list_products_args(),
			]
		);
	}

	/**
	 * Collect the toolkit components and register them.
	 *
	 * @return void
	 */
	public function register_components() {
		/**
		 * Filter components to be added to the toolkit.
		 *
		 * @since 1.0.0
		 *
		 * @param array          $components The components.
		 * @param LoaderTemplate $loader_template The loader template.
		 *
		 * @return array The components.
		 */
		$components = apply_filters( 'alondra_components', [], $this->get_loader() );

		if ( empty( $components ) ) {
			return;
		}

		$toolkit = Container::instance()->get( Toolkit::class );
		$toolkit->add( $components );
		$toolkit->register();
	}

	/**
	 * Get tiered pricing
	 *
	 * @since    1.0.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_tiered_pricing( $request ) {
		$search    = (string) $request->get_param( 'search' ); // @phpstan-ignore cast.string
		$page      = (int) $request->get_param( 'page' ); // @phpstan-ignore cast.int
		$page_size = (int) $request->get_param( 'limit' ); // @phpstan-ignore cast.int
		$order_by  = (string) $request->get_param( 'sort' ); // @phpstan-ignore cast.string
		$order     = (string) $request->get_param( 'order' ); // @phpstan-ignore cast.string

		$page_results = $this->get_tiered_pricing_service()->get_paged_results( 
			$search, 
			null, 
			$page, 
			$page_size, 
			$order_by, 
			$order 
		);

		return new WP_REST_Response( $page_results );
	}

	/**
	 * Args of REST endpoint for get tiered pricing
	 *
	 * @return array<string, mixed>
	 */
	public function list_tiered_pricing_args() {
		return [
			'search' => [
				'description'       => 'Substring to filter',
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => is_string( $param ),
			],
			'page'   => [
				'description'       => 'Page number',
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => (int) $param > 0,
			],
			'limit'  => [
				'description'       => 'Number of items per page',
				'type'              => 'integer',
				'required'          => false,
				'default'           => 20,
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => (int) $param > 0,
			],
			'sort'   => [
				'description'       => 'Attribute used to sort',
				'type'              => 'string',
				'required'          => false,
				'default'           => 'id',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => \is_string( $param ) && $this->get_tiered_pricing_service()->is_sortable( $param ),
			],
			'order'  => [
				'description'       => 'Sort order',
				'type'              => 'string',
				'required'          => false,
				'default'           => 'asc',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => \in_array( $param, [ 'asc', 'desc' ], true ),
			],
		];
	}

	/**
	 * Create tiered pricing
	 *
	 * @since    1.0.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_tiered_pricing( $request ) {
		$entity = $this->create_from_request( $request );
		$entity = $this->get_tiered_pricing_service()->save( $entity );
		if ( is_wp_error( $entity ) ) {
			return new WP_REST_Response( $entity, 400 );
		}
		return new WP_REST_Response( $this->entity_dto( $entity ), 200 );
	}

	/**
	 * Create tiered pricing
	 *
	 * @since    1.0.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add_tiered_pricing( $request ) {
		$entity = $this->create_from_request( $request );
		$entity = $this->get_tiered_pricing_service()->save( $entity );
		if ( is_wp_error( $entity ) ) {
			return new WP_REST_Response( $entity, 400 );
		}
		return new WP_REST_Response( $this->entity_dto( $entity ), 201 );
	}

	/**
	 * The representation of a group that the REST responses and the editor receive.
	 *
	 * @param TieredPricing $entity The group.
	 * @return array<string, mixed>
	 */
	protected function entity_dto( TieredPricing $entity ): array {
		return get_object_vars( $this->get_tiered_pricing_service()->make_dto( $entity ) );
	}

	/**
	 * Args of REST endpoint for Create tiered pricing
	 *
	 * @return array<string, mixed>
	 */
	public function add_tiered_pricing_args() {
		return [
			'title'  => [
				'description' => 'Title',
				'type'        => 'string',
				'required'    => true,
			],
			'status' => [
				'description'       => 'Status',
				'type'              => 'string',
				'required'          => false,
				'default'           => 'publish',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => TieredPricing::STATUS_PUBLISH === $param || TieredPricing::STATUS_DRAFT === $param,
			],
			'tiers'  => [
				'description'       => 'Tier list',
				'type'              => 'array',
				'required'          => false,
				'default'           => [],
				// A key a client omits keeps what is stored, so every one of them is optional here and
				// only what is actually sent gets checked.
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => function ( $param, $request, $key ) {
					foreach ( $param as $tier ) {
						if ( isset( $tier['minQuantity'] ) && (int) $tier['minQuantity'] <= 0 ) {
							return false;
						}
						if ( isset( $tier['maxQuantity'], $tier['minQuantity'] ) && ( (int) $tier['maxQuantity'] < (int) $tier['minQuantity'] ) ) {
							return false;
						}
						if ( isset( $tier['value'] ) && ! is_numeric( $tier['value'] ) ) {
							return false;
						}
					}

					return true;
				},
			],
			'rules'  => [
				'description'       => 'Rule list',
				'type'              => 'array',
				'required'          => false,
				'default'           => [],
				// Every key is optional: one a client omits keeps what is stored. Only what is sent is checked.
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => function ( $param, $request, $key ) {
					foreach ( $param as $rule ) {
						// Keys containing an int[].
						foreach ( [ 'categories', 'products', 'tags', 'users' ] as $key ) {
							if ( ! isset( $rule[ $key ] ) ) {
								continue;
							}
							if ( ! \is_array( $rule[ $key ] ) ) {
								return false;
							}
							foreach ( $rule[ $key ] as $id ) {
								if ( ! is_numeric( $id ) ) {
									return false;
								}
							}
						}
						if ( isset( $rule['roles'] ) && ! \is_array( $rule['roles'] ) ) {
							return false;
						}
					}
					return true;
				},
			],
		];
	}

	/**
	 * Args of REST endpoint for Update tiered pricing
	 *
	 * @return array<string, mixed>
	 */
	public function update_tiered_pricing_args() {
		$args = $this->add_tiered_pricing_args();
		// An update is a partial one: a field the payload leaves out keeps the value already stored, so
		// nothing but the id it applies to is required.
		$args['title'] = [
			'description' => 'Title',
			'type'        => 'string',
			'required'    => false,
		];
		$args['id']    = [
			'description' => 'Tiered pricing ID',
			'type'        => 'integer',
			'required'    => true,
		];
		return $args;
	}

	/**
	 * REST endpoint for get products
	 *
	 * @since    1.0.0
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_products( $request ) {
		$search      = sanitize_text_field( (string) $request->get_param( 'search' ) ); // @phpstan-ignore cast.string
		$exclude_raw = sanitize_text_field( (string) $request->get_param( 'ignore' ) ); // @phpstan-ignore cast.string
		$exclude     = [];
		if ( ! empty( $exclude_raw ) ) {
			$exclude = array_map( 'intval', explode( ',', $exclude_raw ) );
		}

		$products = $this->get_tiered_pricing_service()->get_products( $search, $exclude );

		return new WP_REST_Response( $products );
	}

	/**
	 * Checks if a given request has access to read a user.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, otherwise WP_Error object.
	 */
	public function check_rest_auth( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$code = function_exists( 'rest_authorization_required_code' ) ? rest_authorization_required_code() : 401;
			return new WP_Error( 'rest_forbidden', __( 'You cannot view the resource.', 'alondra' ), [ 'status' => $code ] );
		}
		return true;
	}

	/**
	 * Args of REST endpoint for get products
	 *
	 * @return array<string, mixed>
	 */
	public function list_products_args() {
		return [
			'search' => [
				'description'       => 'Substring to filter',
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => is_string( $param ),
			],
			'ignore' => [
				'description'       => 'Products IDs to exclude',
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				'validate_callback' => fn( $param, $request, $key ) => preg_match( '/^(\d+,)*\d+$/', $param ),
			],
		];
	}

	/**
	 * Show the header of the tiered pricing pages: the upsell banner.
	 *
	 * @return void
	 */
	public function show_header() {
		$this->kses_and_echo( $this->upsell_banner() );
	}

	/**
	 * Show custom content for tiered pricing page
	 *
	 * @since    1.0.0
	 * @param string $page_id Page ID which is currently loaded. Use to compare if this is the page we want to show.
	 * @return void
	 */
	public function show_content( $page_id ) {
		if ( self::TIERED_PRICING_PAGE_ID !== $page_id ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alondra' ) );
		}

		if ( $this->is_action( self::ACTION_EDIT ) || $this->is_action( self::ACTION_ADD ) ) {
			$entity = $this->get_entity_from_request();
			if ( null === $entity ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::TIERED_PRICING_PAGE_SLUG ) );
				exit;
			}
			$this->enqueue_scripts( $this->entity_dto( $entity ) );

			$this->show_edit_form();
			return;
		}

		$this->show_table();
	}

	/**
	 * Read entity ID from request args and fetch from database.
	 *
	 * @return TieredPricing|null
	 */
	protected function get_entity_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads one item to render it, changes nothing.
		$entity = ! empty( $_GET['id'] ) ? $this->get_tiered_pricing_service()->get( (int) $_GET['id'] ) : new TieredPricing(); // @phpstan-ignore cast.int
		return $entity;
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param array<string, mixed>|null $entity The group as entity_dto() represents it.
	 * @return void
	 */
	protected function enqueue_scripts( $entity = null ) {
		! wp_style_is( AssetController::HANDLE_ADMIN ) && wp_enqueue_style( AssetController::HANDLE_ADMIN );

		wp_localize_script(
			AssetController::HANDLE_ADMIN,
			Container::instance()->get( PluginInfo::class )->get_plugin_slug() . '_tiered_pricing',
			[
				'entity' => $entity,
				'url'    => [
					'trash'  => $this->row_action_url( self::ACTION_TRASH ),
					'delete' => $this->row_action_url( self::ACTION_DELETE ),
				],
				'text'   => [

					'publish_published'         => esc_html__( 'Save', 'alondra' ),
					'publish_new'               => esc_html__( 'Publish', 'alondra' ),
					'publish_draft'             => esc_html__( 'Publish', 'alondra' ),
					'save'                      => esc_html__( 'Save', 'alondra' ),
					'draft'                     => esc_html__( 'Save as unpublished', 'alondra' ),
					'title_view'                => esc_html__( 'View Tiered Pricing', 'alondra' ),
					'title_edit'                => esc_html__( 'Edit Tiered Pricing', 'alondra' ),
					'title_add'                 => esc_html__( 'Add New Tiered Pricing', 'alondra' ),
					'modal_title_save_draft'    => esc_html__( 'Save as unpublished', 'alondra' ),
					'modal_subtitle_save_draft' => esc_html__( 'Are you sure you want to save this tiered pricing as unpublished?', 'alondra' ),
					'modal_title_delete'        => esc_html__( 'Yes, delete', 'alondra' ),
					'modal_subtitle_delete'     => esc_html__( 'Are you sure you want to permanently delete this tiered pricing?', 'alondra' ),
					'success_saved_draft'       => esc_html__( 'Saved as unpublished', 'alondra' ),
					'success_restored'          => esc_html__( 'Successfully restored from trash', 'alondra' ),
					'success_published'         => esc_html__( 'Successfully published', 'alondra' ),
					'cancel'                    => esc_html__( 'Cancel', 'alondra' ),
					'error'                     => esc_html__( 'Error has ocurred', 'alondra' ),
					'error_title'               => esc_html__( 'Title is required', 'alondra' ),
					'error_no_tier'             => esc_html__( 'At least one tier is required', 'alondra' ),
					'error_wrong_tier'          => esc_html__( 'Some tiers are not valid', 'alondra' ),
					'error_no_rule'             => esc_html__( 'At least one rule is required', 'alondra' ),
					'error_wrong_rule'          => esc_html__( 'Some rules are not valid', 'alondra' ),
					'error_overlapping_tier'    => esc_html__( 'At least two price rows affect the same range of units', 'alondra' ),
				],
			]
		);

		! wp_script_is( AssetController::HANDLE_ADMIN ) && wp_enqueue_script( AssetController::HANDLE_ADMIN );
	}

	/**
	 * Show content for edit tiered pricing page
	 *
	 * @return void
	 */
	protected function show_edit_form() {
		$this->kses_and_echo( ( new TieredPricingEditView() )->render( get_woocommerce_currency_symbol() ) );
	}

	/**
	 * Show content for table of tiered pricing page
	 *
	 * @return void
	 */
	protected function show_table() {
		$this->enqueue_scripts();

		$table = new ListTable( $this->get_list_table_adapter() );
		$this->manage_current_action( $table );

		$this->kses_and_echo( ( new TieredPricingListTableView() )->render( $table ) );
	}

	/**
	 * Sanitize and echo admin markup
	 *
	 * @param string $html Markup to echo.
	 * @return void
	 */
	private function kses_and_echo( string $html ) {
		$allowed_html             = wp_kses_allowed_html( 'post' );
		$allowed_html['input']    = [
			'type'         => true,
			'name'         => true,
			'value'        => true,
			'class'        => true,
			'placeholder'  => true,
			'required'     => true,
			'disabled'     => true,
			'readonly'     => true,
			'maxlength'    => true,
			'minlength'    => true,
			'size'         => true,
			'autocomplete' => true,
			'autofocus'    => true,
			'pattern'      => true,
			'form'         => true,
			'multiple'     => true,
			'checked'      => true,
			'step'         => true,
		];
		$allowed_html['label']    = [
			'for'   => true,
			'class' => true,
		];
		$allowed_html['select']   = [
			'name'  => true,
			'class' => true,
		];
		$allowed_html['option']   = [
			'value'    => true,
			'selected' => true,
		];
		$allowed_html['textarea'] = [
			'name'        => true,
			'class'       => true,
			'placeholder' => true,
			'required'    => true,
		];
		$allowed_html['button']   = [
			'type'  => true,
			'class' => true,
		];
		$allowed_html['template'] = [
			'id' => true,
		];
		$allowed_html['form']     = [
			'id'     => true,
			'method' => true,
		];
		echo wp_kses( $html, $allowed_html );
	}

	/**
	 * Build the admin URL for an action on a single item, nonce included. The
	 * item id is appended by the script, and may be an id created after this URL
	 * was built, so the nonce covers the action alone. wp_nonce_url() is not
	 * used here because it escapes the URL for HTML, and this one is read by a
	 * script.
	 *
	 * @param string $action Action. See TieredPricingController::ACTION_*.
	 * @return string
	 */
	private function row_action_url( $action ) {
		$url   = get_admin_url( null, 'admin.php?page=' . self::TIERED_PRICING_PAGE_SLUG . '&action=' . $action );
		$nonce = wp_create_nonce( \sprintf( self::ROW_ACTION_NONCE, $action ) );
		return add_query_arg( '_wpnonce', $nonce, $url ) . '&id=';
	}

	/**
	 * Read the item id for a single item action, or 0 when the request carries
	 * no valid nonce for that action.
	 *
	 * @param string $action Action. See TieredPricingController::ACTION_*.
	 * @return int
	 */
	private function get_verified_item_id( $action ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		$item_id = empty( $_REQUEST['id'] ) ? 0 : (int) $_REQUEST['id']; // @phpstan-ignore cast.int
		$nonce   = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_wpnonce'] ) ) : ''; // @phpstan-ignore cast.string
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $item_id || false === wp_verify_nonce( $nonce, \sprintf( self::ROW_ACTION_NONCE, $action ) ) ) {
			return 0;
		}

		return $item_id;
	}

	/**
	 * Manage current bulk action from tiered pricing list table
	 *
	 * @param ListTable $table List table instance.
	 * @return void
	 */
	protected function manage_current_action( ListTable $table ) {
		$action   = $table->current_action();
		$item_id  = \is_string( $action ) ? $this->get_verified_item_id( $action ) : 0;
		$item_ids = [];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified right above.
		if ( $table->verify_bulk_action_nonce() && ! empty( $_REQUEST['element'] ) && \is_array( $_REQUEST['element'] ) ) {
			// @phpstan-ignore-next-line
			$item_ids = array_map( 'intval', $_REQUEST['element'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		ob_start();
		// todo: refactor this to functions instead of switch.
		switch ( $action ) {
			case self::ACTION_DELETE:
				$total = $item_id ? $this->get_tiered_pricing_service()->delete( $item_id ) : 0;
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred deleting item', 'alondra' ), true, 'error' );
				} else {
					$this->show_notice( esc_html__( 'Successfully deleted item', 'alondra' ), true, 'success' );
				}
				break;
			case self::ACTION_DRAFT:
				$total = $item_id ? $this->get_tiered_pricing_service()->set_status( $item_id, TieredPricing::STATUS_DRAFT ) : 0;
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred changing item to unpublished', 'alondra' ), true, 'error' );
				} else {
					$this->show_notice( esc_html__( 'Successfully changed item to unpublished ', 'alondra' ), true, 'success' );
				}
				break;
			case self::ACTION_TRASH:
				$total = $item_id ? $this->get_tiered_pricing_service()->set_status( $item_id, TieredPricing::STATUS_TRASH ) : 0;
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred moving the item to the trash', 'alondra' ), true, 'error' );
				} else {
					$this->show_notice( esc_html__( 'Successfully moved item to the trash', 'alondra' ), true, 'success' );
				}
				break;
			case self::ACTION_UNTRASH:
				$total = $item_id ? $this->get_tiered_pricing_service()->set_status( $item_id, TieredPricing::STATUS_DRAFT ) : 0;
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred restoring the item from the trash', 'alondra' ), true, 'error' );
				} else {
					$this->show_notice( esc_html__( 'Successfully restored item from trash', 'alondra' ), true, 'success' );
				}
				break;
			case self::ACTION_DELETE_ALL:
				$total = $this->get_tiered_pricing_service()->delete( $item_ids );
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred deleting item(s)', 'alondra' ), true, 'error' );
				} else {
					// translators: %d is the number of items deleted.
					$this->show_notice( \sprintf( esc_html__( 'Successfully deleted %d item(s)', 'alondra' ), $total ), true, 'success' );
				}
				break;
			case self::ACTION_DRAFT_ALL:
				$total = $this->get_tiered_pricing_service()->set_status( $item_ids, TieredPricing::STATUS_DRAFT );
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred changing item(s) to unpublished', 'alondra' ), true, 'error' );
				} else {
					// translators: %d is the number of items changed to unpublished.
					$this->show_notice( \sprintf( esc_html__( 'Successfully changed %d item(s) to unpublished ', 'alondra' ), $total ), true, 'success' );
				}
				break;
			case self::ACTION_TRASH_ALL:
				$total = $this->get_tiered_pricing_service()->set_status( $item_ids, TieredPricing::STATUS_TRASH );
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred moving the item(s) to the trash', 'alondra' ), true, 'error' );
				} else {
					// translators: %d is the number of items moved to trash.
					$this->show_notice( \sprintf( esc_html__( 'Successfully moved %d item(s) to the trash', 'alondra' ), $total ), true, 'success' );
				}
				break;
			case self::ACTION_UNTRASH_ALL:
				$total = $this->get_tiered_pricing_service()->set_status( $item_ids, TieredPricing::STATUS_DRAFT );
				if ( ! $total ) {
					$this->show_notice( esc_html__( 'An error occurred restoring the item(s) from the trash', 'alondra' ), true, 'error' );
				} else {
					// translators: %d is the number of items restored from trash.
					$this->show_notice( \sprintf( esc_html__( 'Successfully restored %d item(s) from trash', 'alondra' ), $total ), true, 'success' );
				}
				break;
		}
		ob_end_flush();
	}

	/**
	 * Show titlebar options for tiered pricing page
	 *
	 * @since    1.0.0
	 * @param string $page_id Page ID which is currently loaded. Use to compare if this is the page we want to show.
	 * @return void
	 */
	public function show_titlebar_options( $page_id ) {
		if ( self::TIERED_PRICING_PAGE_ID !== $page_id ) {
			return;
		}

		if ( $this->is_action( self::ACTION_EDIT ) || $this->is_action( self::ACTION_ADD ) ) {
			$entity = $this->get_entity_from_request();
			if ( null === $entity ) {
				return;
			}
			if ( TieredPricing::STATUS_TRASH === $entity->status ) {
				$html = ( new TitlebarOptionsTrashView() )->render();
			} else {
				$html = ( new TitlebarOptionsEditView() )->render( $entity->id ? __( 'Save', 'alondra' ) : __( 'Publish', 'alondra' ) );
			}
		} else {
			$html = ( new TitlebarOptionsListView() )->render(
				get_admin_url( null, 'admin.php?page=' . self::TIERED_PRICING_PAGE_SLUG . '&action=' . self::ACTION_ADD )
			);
			add_screen_option(
				'per_page',
				[
					'label'   => __( 'Number of items per page:', 'alondra' ),
					'default' => $this->get_list_table_adapter()->get_per_page(),
					'option'  => self::PER_PAGE_OPTION,
				]
			);
		}

		$this->kses_and_echo( $html );
	}

	/**
	 * Get the title for the tiered pricing page based on context
	 *
	 * @return string
	 */
	public function get_menu_title() {
		if ( $this->is_action( self::ACTION_EDIT ) ) {
			return __( 'Edit Tiered Pricing', 'alondra' );
		} elseif ( $this->is_action( self::ACTION_ADD ) ) {
			return __( 'Add New Tiered Pricing', 'alondra' );
		} else {
			return esc_attr__( 'Tiered Pricing', 'alondra' );
		}
	}

	/**
	 * Check if the current action is the given action
	 *
	 * @param string $action Action to check. See TieredPricingController::ACTION_*.
	 * @return bool
	 */
	protected function is_action( $action ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- routing only; the actions that write verify their own nonce.
		return ! empty( $_GET['action'] ) && sanitize_text_field( (string) $_GET['action'] ) === $action; // @phpstan-ignore cast.string
	}

	/**
	 * Creates a new Tiered Pricing from request body.
	 *
	 * @param \WP_REST_Request $request The request object. Body Must be an JSON array with the following keys:
	 *                'title' => string,
	 *                'status' => string,
	 *                'tiers' => [
	 *                    0 => [
	 *                        'minQuantity' => int,
	 *                        'maxQuantity' => int|null,
	 *                        'value' => float,
	 *                    ],
	 *                ],
	 *                'rules' => [
	 *                    0 => [
	 *                        'categories' => int[],
	 *                        'products' => int[],
	 *                        'tags' => int[],
	 *                        'users' => int[],
	 *                        'roles' => string[],
	 *                    ],
	 *                ].
	 * @return TieredPricing
	 */
	protected function create_from_request( $request ) {
		/**
		 * @var array{
		 *   id?: scalar,
		 *   title?: string,
		 *   status?: string,
		 *   tiers?: array<array{id?: scalar, minQuantity?: scalar, maxQuantity?: scalar, value?: scalar}>,
		 *   rules?: array<array{id?: scalar, tags?: int[], categories?: int[], products?: int[], users?: int[], roles?: string[]}>
		 * } $data
		 */
		$data   = json_decode( $request->get_body(), true, 512, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$id     = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$stored = $id > 0 ? $this->get_tiered_pricing_service()->get( $id ) : null;

		$entity = new TieredPricing(
			$id,
			isset( $data['title'] ) ? (string) $data['title'] : ( $stored ? $stored->title : '' ),
			// No priority control here, so the column is carried through from storage and never
			// written: a value an add-on stored must survive the save.
			$stored ? $stored->priority : TieredPricing::MIN_PRIORITY,
			isset( $data['status'] ) ? (string) $data['status'] : ( $stored ? $stored->status : TieredPricing::STATUS_PUBLISH ),
			gmdate( 'Y-m-d H:i:s' )
		);
		$entity->tiers = $this->patch_tiers( $data, $id, $stored );
		$entity->rules = $this->patch_rules( $data, $id, $stored );

		return $entity;
	}

	/**
	 * The group's tiers after a partial update.
	 *
	 * A key the payload omits keeps what is stored. This matters because the same rows may have been
	 * written by a build whose editor renders more controls than this one -- a merchant moves between
	 * builds on one store -- so rebuilding the row from the request alone would silently blank every
	 * column this UI cannot show. Omitting the whole `tiers` key keeps the stored list; sending it makes
	 * the list authoritative, so a tier left out of it is a deletion.
	 *
	 * @param array{tiers?: array<array{id?: scalar, minQuantity?: scalar, maxQuantity?: scalar, value?: scalar}>} $data Decoded request body.
	 * @param int                 $id Group id.
	 * @param TieredPricing|null $stored The group as it stands on disk.
	 * @return Tier[]
	 */
	protected function patch_tiers( array $data, $id, $stored ) {
		if ( ! \array_key_exists( 'tiers', $data ) || ! \is_array( $data['tiers'] ) ) {
			return $stored ? $stored->tiers : [];
		}

		$current = [];
		foreach ( $stored ? $stored->tiers : [] as $tier ) {
			$current[ $tier->id ] = $tier;
		}

		$tiers = [];
		foreach ( $data['tiers'] as $tier ) {
			// An id this group does not own is not an update: honouring it would move another group's row here.
			$tier_id = isset( $tier['id'], $current[ (int) $tier['id'] ] ) ? (int) $tier['id'] : 0;
			$base    = $tier_id ? $current[ $tier_id ] : new Tier( 0, $id, 0, Tier::MAX_UNITS, true, 0 );
			$tiers[] = $this->tier_from_payload( $tier, $base, $id );
		}

		return $tiers;
	}

	/**
	 * One tier of the payload applied over its base: the stored tier, or the defaults for a new one.
	 *
	 * @param array{id?: scalar, minQuantity?: scalar, maxQuantity?: scalar, value?: scalar} $payload One entry of the payload's `tiers`.
	 * @param Tier                                                               $base What a key the payload omits keeps.
	 * @param int                                                                $id Group id.
	 * @return Tier
	 */
	protected function tier_from_payload( array $payload, Tier $base, int $id ): Tier {
		return new Tier(
			$base->id,
			$id,
			isset( $payload['minQuantity'] ) ? (int) $payload['minQuantity'] : $base->min_units,
			\array_key_exists( 'maxQuantity', $payload ) ? ( empty( $payload['maxQuantity'] ) ? Tier::MAX_UNITS : (int) $payload['maxQuantity'] ) : $base->max_units,
			// No type control here, so a stored percent tier keeps its type and a new tier is
			// fixed, which is the only kind this UI can express.
			$base->is_fixed,
			isset( $payload['value'] ) ? (float) $payload['value'] : $base->value
		);
	}

	/**
	 * The group's rules after a partial update. Same contract as patch_tiers().
	 *
	 * @param array{rules?: array<array{id?: scalar, tags?: int[], categories?: int[], products?: int[], users?: int[], roles?: string[]}>} $data Decoded request body.
	 * @param int                 $id Group id.
	 * @param TieredPricing|null $stored The group as it stands on disk.
	 * @return Rule[]
	 */
	protected function patch_rules( array $data, $id, $stored ) {
		if ( ! \array_key_exists( 'rules', $data ) || ! \is_array( $data['rules'] ) ) {
			return $stored ? $stored->rules : [];
		}

		$current = [];
		foreach ( $stored ? $stored->rules : [] as $rule ) {
			$current[ $rule->id ] = $rule;
		}

		$rules = [];
		foreach ( $data['rules'] as $rule ) {
			$rule_id = isset( $rule['id'], $current[ (int) $rule['id'] ] ) ? (int) $rule['id'] : 0;
			// A rule the payload is creating has nothing stored to fall back on, so its base is the
			// permissive side of every relationship -- the only side this UI can express.
			$base    = $rule_id ? $current[ $rule_id ] : new Rule( 0, $id, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_OR, Rule::RELATIONSHIP_OR );
			$rules[] = $this->rule_from_payload( $rule, $base, $id );
		}

		return $rules;
	}

	/**
	 * One rule of the payload applied over its base. Same contract as tier_from_payload().
	 *
	 * @param array{id?: scalar, tags?: int[], categories?: int[], products?: int[], users?: int[], roles?: string[]} $payload One entry of the payload's `rules`.
	 * @param Rule                                                                                         $base What a key the payload omits keeps.
	 * @param int                                                                                          $id Group id.
	 * @return Rule
	 */
	protected function rule_from_payload( array $payload, Rule $base, int $id ): Rule {
		return new Rule(
			$base->id,
			$id,
			// No relationship controls here: the five columns are carried through untouched.
			$base->roles_rel,
			$base->cats_rel,
			$base->tags_rel,
			$base->tags_with_cats_rel,
			$base->prods_cats_tags_with_roles_users_rel,
			isset( $payload['tags'] ) ? $payload['tags'] : $base->tags,
			isset( $payload['categories'] ) ? $payload['categories'] : $base->categories,
			isset( $payload['products'] ) ? $payload['products'] : $base->products,
			isset( $payload['users'] ) ? $payload['users'] : $base->users,
			isset( $payload['roles'] ) ? $payload['roles'] : $base->roles,
			$base->bundle_products
		);
	}

	/**
	 * Whether a bundle puts all of its money on its children and leaves the container at 0, unmasked.
	 *
	 * The instanceof is not redundant: `woocommerce_product_class` and the deprecated $product_type
	 * property both let any WC_Product subclass report type 'bundle', and contains() is not on WC_Product.
	 *
	 * @param \WC_Product $product The product.
	 * @return bool
	 */
	private function is_per_item_bundle( $product ) {
		return $product->is_type( 'bundle' )
			&& $product instanceof \WC_Product_Bundle
			&& (bool) $product->contains( 'priced_individually' );
	}

	/**
	 * Whether the product is being rendered or priced as an item of a real bundle.
	 *
	 * The cart leaves bundled lines to Product Bundles, so the display must not quote a tier for them
	 * either. Bundle-sells render through a runtime bundle carrying the host's id and are sold as
	 * standalone lines, so they keep their tier.
	 *
	 * @param \WC_Product $product The product.
	 * @return bool
	 */
	private function is_bundled_line( $product ) {
		if ( ! class_exists( 'WC_PB_Product_Prices', false ) ) {
			return false;
		}
		$bundled_item = \WC_PB_Product_Prices::get_filtered_bundled_item( $product );
		if ( ! $bundled_item instanceof \WC_Bundled_Item ) {
			return false;
		}
		$bundle = $bundled_item->get_bundle();
		return $bundle instanceof \WC_Product_Bundle && 'bundle' === \WC_Product_Factory::get_product_type( $bundle->get_id() );
	}

	/**
	 * Set tier price to products in cart
	 *
	 * @param \WC_Cart $cart The cart.
	 * @return void
	 */
	public function set_tiered_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$current_user_id = (int) get_current_user_id();
		foreach ( $cart->get_cart() as $item_key => $cart_item ) {
			// Product Bundles prices its own children: repricing one double-discounts the bundle.
			// Read the cart item keys rather than call wc_pb_maybe_is_bundled_cart_item(), so this
			// stays free of any dependency on the plugin -- the keys simply never exist without it.
			if ( ! empty( $cart_item['bundled_by'] ) && ! empty( $cart_item['bundled_item_id'] ) && ! empty( $cart_item['stamp'] ) ) {
				continue;
			}
			if ( $this->is_per_item_bundle( $cart_item['data'] ) ) {
				continue;
			}
			// A removed and re-added line reuses its key with a fresh product, so the product is compared too.
			if ( ! isset( $this->cart_basis[ $item_key ] ) || $this->cart_basis[ $item_key ]['product'] !== $cart_item['data'] ) {
				$this->cart_basis[ $item_key ] = [
					'product' => $cart_item['data'],
					'price'   => (float) $cart_item['data']->get_price(),
				];
			}
			$price = $this->get_tiered_pricing_service()->get_tiered_price(
				(int) $cart_item['data']->get_id(),
				(int) $cart_item['quantity'],
				$current_user_id,
				$this->cart_basis[ $item_key ]['price']
			);
			// This block is for compatibility with Woocommerce Product Addons plugin.
			if ( ! empty( $cart_item['addons'] ) ) {
				foreach ( $cart_item['addons'] as $addon ) {
					if ( isset( $addon['price'] ) && (float) $addon['price'] > 0 ) {
						$price += (float) $addon['price'];
					}
				}
			}
			$cart_item['data']->set_sale_price( $price );
			$cart_item['data']->set_price( $price );
		}
	}

	/**
	 * Register hook for rendering product tiers in frontend
	 *
	 * The prices table is either above the Add to Cart button or not rendered at all, so the only
	 * question left is whether to hook it up.
	 *
	 * @return void
	 */
	public function register_product_tiers_render() {
		if ( PreferencesService::LAYOUT_POSITION_HIDE === $this->get_preference_service()->get_layout_pos() ) {
			return;
		}

		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_product_tiers' ], 10 );
	}

	/**
	 * Render simple product tiers in frontend
	 *
	 * @return void
	 */
	public function render_product_tiers() {
		global $product;
		echo '<div class="' . esc_attr( AssetController::WRAPPER_CLASS ) . '">';
		// A per-item container reports its own base price of 0, so every reference price the table
		// strikes through and every percentage it quotes would be meaningless.
		if ( ! $product->is_type( 'variable' ) && ! $this->is_per_item_bundle( $product ) ) {
			$tiers_html = $this->tiers_html( $product );
			if ( ! empty( $tiers_html ) ) {
				$allowed_html             = wp_kses_allowed_html( 'post' );
				$allowed_html['input']    = [
					'type'         => true,
					'name'         => true,
					'value'        => true,
					'class'        => true,
					'placeholder'  => true,
					'required'     => true,
					'disabled'     => true,
					'readonly'     => true,
					'maxlength'    => true,
					'minlength'    => true,
					'size'         => true,
					'autocomplete' => true,
					'autofocus'    => true,
					'pattern'      => true,
					'form'         => true,
					'multiple'     => true,
					'checked'      => true,
					'step'         => true,
				];
				$allowed_html['label']    = [
					'for' => true,
				];
				$allowed_html['select']   = [
					'name'  => true,
					'class' => true,
				];
				$allowed_html['option']   = [
					'value'    => true,
					'selected' => true,
				];
				$allowed_html['textarea'] = [
					'name'        => true,
					'class'       => true,
					'placeholder' => true,
					'required'    => true,
				];
				$allowed_html['button']   = [
					'type'  => true,
					'class' => true,
				];
				$allowed_html['template'] = [
					'id' => true,
				];
				echo wp_kses( $tiers_html, $allowed_html );
			}
		}
		echo '</div>';
	}

	/**
	 * Render variation product tiers in frontend
	 *
	 * @param array<string, mixed>  $data The variation data.
	 * @param \WC_Product           $product The product.
	 * @param \WC_Product_Variation $variation The variation.
	 * @return array<string, mixed>
	 */
	public function render_variation_tiers( $data, $product, $variation ) {
		if ( $this->is_bundled_line( $variation ) ) {
			return $data;
		}

		$tiers_html = $this->tiers_html( $variation );
		if ( ! empty( $tiers_html ) ) {
			$class              = AssetController::VARIATION_TIERS_CLASS;
			$data['price_html'] = "<template class=\"$class\">$tiers_html</template>";
		}
		return $data;
	}

	/**
	 * Get Tiers HTML for a product
	 *
	 * @param \WC_Product $product The Product.
	 * @return string The HTML as string or NULL if tiers are not enabled
	 */
	protected function tiers_html( $product ) {
		$tiers = $this->get_tiered_pricing_service()->get_tiers( 
			(int) $product->get_id(), 
			(int) get_current_user_id(), 
			(float) $product->get_price(), 
			(float) $product->get_regular_price() 
		);

		! wp_style_is( AssetController::HANDLE_FRONT ) && wp_enqueue_style( AssetController::HANDLE_FRONT );
		! wp_script_is( AssetController::HANDLE_FRONT ) && wp_enqueue_script( AssetController::HANDLE_FRONT );

		$preferences = $this->get_preference_service();

		return Container::instance()->get( PricingLayoutViewFactory::class )
			->create( $preferences->get_pricing_layout() )
			->render( $tiers, $preferences->is_price_clickable() ? AssetController::IS_CLICKABLE_CLASS : '' );
	}

	/**
	 * Register the tiered pricing listing page.
	 *
	 * @param array<int|string, mixed> $components The components.
	 * @return array<int|string, mixed>
	 */
	public function register_tiered_pricing_listing_page( $components ) {
		$page = ( new PrefPage( 'alondra_tiered_pricing' ) )
				->set_menu_title( esc_attr__( 'Tiered Pricing', 'alondra' ) )
				->set_page_title( $this->get_menu_title() )
				->set_menu_slug( self::TIERED_PRICING_PAGE_SLUG )
				->set_parent_slug( 'woocommerce' )
				->set_header( [ $this, 'show_header' ] )
				->set_content( [ $this, 'show_content' ] )
				->set_titlebar_options( [ $this, 'show_titlebar_options' ] );

		$components[] = $page;
		return $components;
	}

	/**
	 * Custom price for product
	 *
	 * @param string      $price The current HTML price.
	 * @param \WC_Product $product The product.
	 * @return string HTML price.
	 */
	public function set_product_price( $price, $product ) {
		// Product Bundles composes a per-item bundle's price from its children; rebuilding it from the
		// container's own price fabricates a figure the cart never charges. A static bundle carries the
		// whole price on the container, and the same gate tiers its cart line and renders its tier
		// table, so leaving the html alone here would quote a price those two contradict.
		if ( $product->is_type( 'variable' ) || $this->is_per_item_bundle( $product ) || $this->is_bundled_line( $product ) ) {
			return $price;
		}
		return $this->get_tiered_pricing_service()->get_price( $price, $product, (int) get_current_user_id() );
	}

	/**
	 * Custom price for product variation
	 *
	 * @param string               $price The current HTML price.
	 * @param \WC_Product_Variable $product The product.
	 * @return string HTML price.
	 */
	public function set_variable_price( $price, $product ) {
		if ( $this->is_bundled_line( $product ) ) {
			return $price;
		}
		return $this->get_tiered_pricing_service()->get_variable_price( $price, $product, (int) get_current_user_id() );
	}

	/**
	 * Invalidate every cached tiered-pricing lookup
	 *
	 * @return void
	 */
	public function flush_cache() {
		$this->get_tiered_pricing_service()->flush_cache();
	}

	/**
	 * Invalidate every cached tiered-pricing lookup when product terms are assigned
	 *
	 * @param int                    $object_id The object the terms were set on.
	 * @param array<int, int|string> $terms The terms that were set.
	 * @param int[]                  $tt_ids The term taxonomy IDs now related.
	 * @param string                 $taxonomy The taxonomy the terms belong to.
	 * @param bool                   $append Whether the terms were appended to the old ones.
	 * @param int[]                  $old_tt_ids The term taxonomy IDs related before.
	 * @return void
	 */
	public function flush_cache_on_terms_set( $object_id, $terms, $tt_ids, $taxonomy, $append = false, $old_tt_ids = [] ) {
		if ( ! \in_array( $taxonomy, [ 'product_cat', 'product_tag' ], true ) ) {
			return;
		}
		// Every product creation fires this for both taxonomies, one of them with nothing to say.
		if ( $this->same_relationships( $tt_ids, $old_tt_ids ) ) {
			return;
		}
		$this->get_tiered_pricing_service()->flush_cache();
	}

	/**
	 * Invalidate every cached tiered-pricing lookup when product terms are removed
	 *
	 * Removals fire this instead of set_object_terms, and wp_delete_term() goes through
	 * wp_remove_object_terms() for every object the term was on.
	 *
	 * @param int    $object_id The object the terms were removed from.
	 * @param int[]  $tt_ids The term taxonomy IDs removed.
	 * @param string $taxonomy The taxonomy the terms belong to.
	 * @return void
	 */
	public function flush_cache_on_terms_removed( $object_id, $tt_ids, $taxonomy ) {
		if ( ! \in_array( $taxonomy, [ 'product_cat', 'product_tag' ], true ) ) {
			return;
		}
		$this->get_tiered_pricing_service()->flush_cache();
	}

	/**
	 * Whether two term-taxonomy ID lists describe the same relationships.
	 *
	 * Core hands them over as strings as readily as ints, and in no particular order.
	 *
	 * @param array<int, int|string> $tt_ids One list.
	 * @param array<int, int|string> $old_tt_ids The other.
	 * @return bool
	 */
	private function same_relationships( array $tt_ids, array $old_tt_ids ) {
		$new = array_map( 'intval', $tt_ids );
		$old = array_map( 'intval', $old_tt_ids );
		sort( $new );
		sort( $old );
		return $new === $old;
	}
}
