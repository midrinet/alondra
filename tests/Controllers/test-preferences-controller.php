<?php
/**
 * PreferencesController Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Controllers;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\Controller\PreferencesController;
use Midrinet\Alondra\Infrastructure\Controller\TieredPricingController;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\View\Toolkit\PrefPage;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

/**
 * Covers the settings page: the PATCH save, the mixed-field rule, the rendered fields and the banner.
 */
class PreferencesControllerTest extends WP_UnitTestCase {

	use Container_Seam;

	private const UPSELL = 'Alondra Plus adds percentage prices, group priorities, AND/OR operators inside and between rule groups, pills and list layouts, live price and total updates, and Product Bundles pricing.';

	/**
	 * Stored keys this page does not render, with values an add-on could store.
	 *
	 * @var array<string, string>
	 */
	private const UNRENDERED = [
		PreferencesService::PREF_PRICING_LAYOUT         => 'pills',
		PreferencesService::PREF_COLOR                  => '#111111',
		PreferencesService::PREF_BG_COLOR               => '#222222',
		PreferencesService::PREF_BORDER_COLOR           => '#333333',
		PreferencesService::PREF_HIGHLIGHT_COLOR        => '#444444',
		PreferencesService::PREF_HIGHLIGHT_BG_COLOR     => '#555555',
		PreferencesService::PREF_HIGHLIGHT_BORDER_COLOR => '#666666',
		PreferencesService::PREF_DISABLE_STYLES         => '1',
		'live_product_price'                            => '1',
		'live_total_price'                              => '1',
		'strikethrough_price'                           => '1',
	];

	/**
	 * A submission of every rendered key, as the unchanged free defaults.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_SUBMISSION = [
		PreferencesService::PREF_LAYOUT_POSITION         => PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN,
		PreferencesService::PREF_CLICKABLE_LAYOUT        => '1',
		PreferencesService::PREF_HIGHLIGHT_PRICING       => '1',
		PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE => '1',
		PreferencesService::PREF_ENABLE_CACHE            => '1',
	];

	/**
	 * The admin menu globals as the test found them, restored because registering a page writes them.
	 *
	 * @var array<string, mixed>
	 */
	private array $menu_globals = [];

	public function set_up() {
		parent::set_up();
		foreach ( [ 'submenu', '_parent_pages', '_registered_pages' ] as $name ) {
			$this->menu_globals[ $name ] = $GLOBALS[ $name ] ?? null;
		}
		// Created before the container swap: a new user fires role hooks the tiered pricing controller listens to.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->install_container( $this->container() );
		$_POST['option_page'] = PreferencesController::OPTION_GROUP;
	}

	public function tear_down() {
		unset( $_POST['option_page'] );
		foreach ( $this->menu_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}
		delete_option( PreferencesService::PREF_OPTION );
		parent::tear_down();
	}

	/**
	 * A container with a fresh PreferencesService, so every test reads the option it stored.
	 *
	 * @param callable|null $freemius Definition of the `alondra/freemius` binding, none when null.
	 * @return Container
	 */
	private function container( ?callable $freemius = null ): Container {
		$config      = ( $this->replaced_container ?? Container::instance() )->get( PluginInfo::class );
		$definitions = [
			PreferencesService::class => fn() => new PreferencesService(),
			PluginInfo::class         => fn() => $config,
		];
		if ( null !== $freemius ) {
			$definitions['alondra/freemius'] = $freemius;
		}
		return new Container( $definitions );
	}

	/**
	 * Store $stored, then save $submitted as options.php would.
	 *
	 * @param array<string, mixed> $stored    Option before the save.
	 * @param array<string, mixed> $submitted Submitted values.
	 * @return array<mixed> The option after the save.
	 */
	private function save( array $stored, array $submitted ): array {
		if ( [] !== $stored ) {
			update_option( PreferencesService::PREF_OPTION, $stored );
		}
		$controller = new PreferencesController();
		$controller->register_setting();
		update_option( PreferencesService::PREF_OPTION, $submitted );

		$saved = get_option( PreferencesService::PREF_OPTION );
		$this->assertIsArray( $saved );
		return $saved;
	}

	private function render_content(): string {
		ob_start();
		( new PreferencesController() )->show_content();
		return (string) ob_get_clean();
	}

	private function render_header( PreferencesController $controller ): string {
		ob_start();
		$controller->show_header();
		return (string) ob_get_clean();
	}

	public function test_the_settings_link_is_hooked_on_the_plugin_row() {
		$controller = new PreferencesController();
		$controller->register();

		$basename = Container::instance()->get( PluginInfo::class )->get_plugin_basename();
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . $basename, [ $controller, 'add_action_links' ] ) );
	}

	public function test_the_plugin_row_drops_freemius_license_link() {
		$controller = new PreferencesController();
		$controller->register();
		$basename = Container::instance()->get( PluginInfo::class )->get_plugin_basename();
		// The key Freemius gives its link on this plugin's row.
		$freemius = static fn( array $actions ): array => $actions + [ 'activate-license alondra' => '<a href="#">Activate License</a>' ];

		foreach ( [ 'plugin_action_links_', 'network_admin_plugin_action_links_' ] as $hook ) {
			add_filter( $hook . $basename, $freemius );
			$actions = (array) apply_filters( $hook . $basename, [ 'deactivate' => 'd' ], $basename ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- runs core's filter, defines no hook.
			remove_filter( $hook . $basename, $freemius );

			$this->assertArrayNotHasKey( 'activate-license alondra', $actions, $hook );
			$this->assertArrayNotHasKey( 'upgrade', $actions, $hook );
			$this->assertSame( 'd', $actions['deactivate'], $hook );
		}
		$this->assertArrayHasKey( 'alondra_plus', (array) apply_filters( 'plugin_action_links_' . $basename, [], $basename ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- runs core's filter, defines no hook.
	}

	public function test_the_settings_link_points_at_the_registered_page() {
		add_submenu_page( 'options-general.php', 'x', 'x', 'manage_options', PreferencesController::PAGE_SLUG, '__return_null' );

		$actions = ( new PreferencesController() )->add_action_links( [ 'deactivate' => 'd' ] );

		$this->assertSame( 'd', $actions['deactivate'] );
		$this->assertStringContainsString( esc_url( menu_page_url( PreferencesController::PAGE_SLUG, false ) ), $actions['settings'] );
		$this->assertStringContainsString( 'Settings', $actions['settings'] );
	}

	public function test_the_page_is_registered_under_settings() {
		global $submenu;

		$components = ( new PreferencesController() )->register_settings_page( [] );
		$components[0]->register_menu();

		$entry = array_values(
			array_filter(
				$submenu['options-general.php'] ?? [],
				fn( $item ) => PreferencesController::PAGE_SLUG === $item[2]
			)
		);
		$this->assertCount( 1, $entry );
		$this->assertSame( 'Alondra', $entry[0][0] );
		$this->assertSame( 'Alondra', $entry[0][3] );
		$this->assertArrayNotHasKey( PreferencesController::PAGE_SLUG, array_column( $submenu['woocommerce'] ?? [], 2, 2 ) );
	}

	public function test_the_footer_shows_the_free_edition_the_links_ko_fi_and_the_rating() {
		$controller = new PreferencesController();
		$version    = Container::instance()->get( PluginInfo::class )->get_plugin_version();
		ob_start();
		$controller->show_footer();
		$html = (string) ob_get_clean();

		$this->assertNotSame( '', $version );
		$this->assertStringContainsString( '<span class="alondra-preferences__footer-edition">FREE v' . $version . '</span>', $html );
		$this->assertStringContainsString( 'href="' . PreferencesController::CHANGELOG_URL . '" target="_blank" rel="noopener">Changelog</a>', $html );
		$this->assertStringContainsString( 'href="' . PreferencesController::SUPPORT_URL . '" target="_blank" rel="noopener">Get help</a>', $html );
		$this->assertStringContainsString( '<a class="alondra-kofi" href="' . PreferencesController::KOFI_URL . '" target="_blank" rel="noopener" aria-label="Support Us on Ko-fi"><svg ', $html );
		$this->assertStringContainsString( '<a class="alondra-rate" href="' . PreferencesController::REVIEWS_URL . '" target="_blank" rel="noopener"><svg ', $html );
		$this->assertSame( 4, substr_count( $html, 'target="_blank" rel="noopener"' ) );

		$page   = $controller->register_settings_page( [] )[0];
		$footer = new \ReflectionProperty( PrefPage::class, 'footer' );
		$footer->setAccessible( true );
		$this->assertSame( [ $controller, 'show_footer' ], $footer->getValue( $page ) );
		$titlebar = new \ReflectionProperty( PrefPage::class, 'titlebar_options' );
		$titlebar->setAccessible( true );
		$this->assertNull( $titlebar->getValue( $page ) );
	}

	public function test_free_shows_one_settings_tab_holding_the_form() {
		$html = $this->render_content();

		$this->assertSame( 1, substr_count( $html, 'class="alondra-tab ' ) + substr_count( $html, 'class="alondra-tab"' ) );
		$this->assertStringStartsWith( '<div class="alondra-tab__container"><div class="alondra-tab alondra-active" data-target="#alondra-tab-settings">Settings</div></div><div id="alondra-tab-settings" class="alondra-tab__content alondra-active"><form ', $html );
		$this->assertStringEndsWith( '</form></div>', $html );
		$this->assertSame( 1, substr_count( $html, '<form ' ) );
	}

	/**
	 * Under Settings, WordPress prints the saved notice itself (options-head.php).
	 */
	public function test_the_content_does_not_print_the_settings_errors() {
		add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'success' );

		$this->assertStringNotContainsString( 'Settings saved.', $this->render_content() );
	}

	public function test_the_settings_link_is_skipped_without_the_page() {
		$actions = ( new PreferencesController() )->add_action_links( [ 'deactivate' => 'd' ] );

		$this->assertArrayNotHasKey( 'settings', $actions );
		$this->assertSame( 'd', $actions['deactivate'] );
	}

	public function test_the_plugin_row_links_to_the_addon_checkout() {
		$url     = 'https://checkout.freemius.com/plugin/38115/plan/63451/?billing_cycle=annual&title=Alondra%20Plus&cancel_url=' . rawurlencode( admin_url( 'options-general.php?page=' . PreferencesController::PAGE_SLUG ) );
		$actions = ( new PreferencesController() )->add_action_links( [] );

		$this->assertSame( '<a href="' . $url . '">Get Alondra Plus</a>', html_entity_decode( $actions['alondra_plus'] ) );
	}

	public function test_the_plugin_row_drops_the_checkout_with_the_banner() {
		$controller = new class() extends PreferencesController {
			protected function upsell_banner(): string {
				return '';
			}
		};

		$this->assertArrayNotHasKey( 'alondra_plus', $controller->add_action_links( [] ) );
	}

	/**
	 * Free's own pricing page would sell a plan that unlocks nothing, so its menu item, action link and tab stay hidden.
	 */
	public function test_free_hides_its_own_pricing_page() {
		$this->assertFalse( \Freemius::get_instance_by_id( 10153 )->is_pricing_page_visible() );
	}

	/**
	 * Whatever would open free's pricing page, such as the checkout's way back, opens the settings page.
	 */
	public function test_free_pricing_links_open_the_settings_page() {
		$settings = admin_url( 'options-general.php?page=' . PreferencesController::PAGE_SLUG );

		$this->assertSame( $settings, \Freemius::get_instance_by_id( 10153 )->pricing_url() );
		$this->assertSame( $settings, \Freemius::get_instance_by_id( 10153 )->get_upgrade_url() );

		// The SDK asks with null and registers the hidden pricing page, which serves the checkout, only while null comes back.
		$this->assertNull( \Freemius::get_instance_by_id( 10153 )->apply_filters( 'pricing_url', null ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- runs the SDK's filter, defines no hook.
	}

	public function test_a_save_leaves_the_unrendered_keys_intact() {
		$saved = $this->save( self::UNRENDERED, self::DEFAULT_SUBMISSION );

		foreach ( self::UNRENDERED as $key => $value ) {
			$this->assertSame( $value, $saved[ $key ] ?? null, $key );
		}
	}

	public function test_a_changed_free_option_is_written() {
		$submitted = self::DEFAULT_SUBMISSION;
		unset( $submitted[ PreferencesService::PREF_CLICKABLE_LAYOUT ], $submitted[ PreferencesService::PREF_ENABLE_CACHE ] );
		$submitted[ PreferencesService::PREF_UNINSTALL_CLEANUP ] = '1';

		$saved = $this->save( [ PreferencesService::PREF_CLICKABLE_LAYOUT => '1' ], $submitted );

		$this->assertSame( '0', $saved[ PreferencesService::PREF_CLICKABLE_LAYOUT ], 'An unchecked box is absent from the post and saves as 0.' );
		$this->assertSame( '0', $saved[ PreferencesService::PREF_ENABLE_CACHE ] );
		$this->assertSame( '1', $saved[ PreferencesService::PREF_UNINSTALL_CLEANUP ] );
		$this->assertSame( '1', $saved[ PreferencesService::PREF_HIGHLIGHT_PRICING ] );
	}

	public function test_a_first_save_writes_the_submitted_keys() {
		$submitted = self::DEFAULT_SUBMISSION;
		$submitted[ PreferencesService::PREF_LAYOUT_POSITION ]   = PreferencesService::LAYOUT_POSITION_HIDE;
		$submitted[ PreferencesService::PREF_HIGHLIGHT_PRICING ] = '0';

		$saved = $this->save( [], $submitted );

		$this->assertSame( PreferencesService::LAYOUT_POSITION_HIDE, $saved[ PreferencesService::PREF_LAYOUT_POSITION ] );
		$this->assertSame( '0', $saved[ PreferencesService::PREF_HIGHLIGHT_PRICING ] );
		$this->assertSame( '0', $saved[ PreferencesService::PREF_UNINSTALL_CLEANUP ] );
	}

	/**
	 * A stored position this page does not offer displays as the default. Submitting that default back is not a change.
	 */
	public function test_an_untouched_mixed_field_keeps_the_stored_value() {
		$saved = $this->save( [ PreferencesService::PREF_LAYOUT_POSITION => 'after_add_to_cart_btn' ], self::DEFAULT_SUBMISSION );

		$this->assertSame( 'after_add_to_cart_btn', $saved[ PreferencesService::PREF_LAYOUT_POSITION ] );
	}

	public function test_a_changed_mixed_field_is_written() {
		$submitted = self::DEFAULT_SUBMISSION;
		$submitted[ PreferencesService::PREF_LAYOUT_POSITION ] = PreferencesService::LAYOUT_POSITION_HIDE;

		$saved = $this->save( [ PreferencesService::PREF_LAYOUT_POSITION => 'after_add_to_cart_btn' ], $submitted );

		$this->assertSame( PreferencesService::LAYOUT_POSITION_HIDE, $saved[ PreferencesService::PREF_LAYOUT_POSITION ] );
	}

	public function test_a_mixed_field_changed_back_to_the_default_is_written() {
		$saved = $this->save( [ PreferencesService::PREF_LAYOUT_POSITION => PreferencesService::LAYOUT_POSITION_HIDE ], self::DEFAULT_SUBMISSION );

		$this->assertSame( PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN, $saved[ PreferencesService::PREF_LAYOUT_POSITION ] );
	}

	public function test_unknown_and_unrendered_submitted_keys_are_ignored() {
		$submitted = self::DEFAULT_SUBMISSION + [
			'not_a_preference'                      => 'x',
			PreferencesService::PREF_PRICING_LAYOUT => 'list',
			PreferencesService::PREF_COLOR          => '#ffffff',
			PreferencesService::PREF_DISABLE_STYLES => '0',
			'strikethrough_price'                   => '0',
		];

		$saved = $this->save( self::UNRENDERED, $submitted );

		$this->assertArrayNotHasKey( 'not_a_preference', $saved );
		foreach ( self::UNRENDERED as $key => $value ) {
			$this->assertSame( $value, $saved[ $key ], $key );
		}
	}

	public function test_invalid_values_are_sanitized() {
		$submitted = self::DEFAULT_SUBMISSION;
		$submitted[ PreferencesService::PREF_CLICKABLE_LAYOUT ]  = 'yes';
		$submitted[ PreferencesService::PREF_HIGHLIGHT_PRICING ] = [ '1' ];
		$submitted[ PreferencesService::PREF_LAYOUT_POSITION ]   = 'after_add_to_cart_btn';
		$submitted[ PreferencesService::PREF_UNINSTALL_CLEANUP ] = '<script>';

		$saved = $this->save( [ PreferencesService::PREF_LAYOUT_POSITION => PreferencesService::LAYOUT_POSITION_HIDE ], $submitted );

		$this->assertSame( '0', $saved[ PreferencesService::PREF_CLICKABLE_LAYOUT ] );
		$this->assertSame( '0', $saved[ PreferencesService::PREF_HIGHLIGHT_PRICING ] );
		$this->assertSame( '0', $saved[ PreferencesService::PREF_UNINSTALL_CLEANUP ] );
		$this->assertSame( PreferencesService::LAYOUT_POSITION_HIDE, $saved[ PreferencesService::PREF_LAYOUT_POSITION ], 'A position the page does not offer is not written.' );
	}

	/**
	 * Any other write of the option is not this page's submission and passes through as given.
	 */
	public function test_a_write_from_elsewhere_is_not_patched() {
		unset( $_POST['option_page'] );
		$value = [ PreferencesService::PREF_PRICING_LAYOUT => 'list' ];

		$this->assertSame( $value, ( new PreferencesController() )->sanitize_option( $value ) );
	}

	public function test_the_page_renders_only_the_free_fields() {
		$html = $this->render_content();

		foreach ( [ PreferencesService::PREF_LAYOUT_POSITION, PreferencesService::PREF_CLICKABLE_LAYOUT, PreferencesService::PREF_HIGHLIGHT_PRICING, PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE, PreferencesService::PREF_ENABLE_CACHE, PreferencesService::PREF_UNINSTALL_CLEANUP ] as $key ) {
			$this->assertStringContainsString( 'name="alondra[' . $key . ']"', $html, $key );
		}
		foreach ( array_keys( self::UNRENDERED ) as $key ) {
			$this->assertStringNotContainsString( $key, $html, "$key has no control, placeholder or hidden input." );
		}
		$this->assertSame( 1, preg_match_all( '/<option /', $html, $unused ) - 1, 'The position select offers two options.' );
		$this->assertStringContainsString( 'value="' . PreferencesService::LAYOUT_POSITION_HIDE . '"', $html );
		$this->assertStringNotContainsString( 'type="hidden" name="alondra[', $html );
		$this->assertStringContainsString( "name='option_page' value='" . PreferencesController::OPTION_GROUP . "'", $html );
	}

	public function test_the_page_shows_the_stored_values() {
		update_option(
			PreferencesService::PREF_OPTION,
			[
				PreferencesService::PREF_ENABLE_CACHE    => '0',
				PreferencesService::PREF_LAYOUT_POSITION => PreferencesService::LAYOUT_POSITION_HIDE,
			] 
		);

		$html = $this->render_content();

		$this->assertMatchesRegularExpression( '/name="alondra\[enable_cache\]" value="1" \/>/', $html, 'The cache box is unchecked.' );
		$this->assertMatchesRegularExpression( '/name="alondra\[clickable_layout\]" value="1" checked="checked"/', $html );
		$this->assertStringContainsString( 'value="hide" selected="selected"', $html );
	}

	/**
	 * Before the site opts in or skips, Freemius registers no pricing page, so the link goes to the hosted
	 * checkout of the add-on directly, its way back to the settings page.
	 */
	public function test_the_banner_shows_only_the_upgrade() {
		$html = $this->render_header( new PreferencesController() );
		$url  = 'https://checkout.freemius.com/plugin/38115/plan/63451/?billing_cycle=annual&title=Alondra%20Plus&cancel_url=' . rawurlencode( admin_url( 'options-general.php?page=' . PreferencesController::PAGE_SLUG ) );

		// WP 6.8 and 7.1 escape the query's ampersand differently, so compare the decoded markup.
		$this->assertSame(
			'<div class="notice notice-info alondra-upsell"><p>' . self::UPSELL . ' <a href="' . $url . '" target="_self">Get Alondra Plus</a></p></div>',
			html_entity_decode( $html )
		);
		$this->assertStringNotContainsString( 'Ko-fi', $html );
	}

	/**
	 * Once Freemius registers its hidden pricing page, the link asks free's instance for the add-on's checkout there.
	 */
	public function test_the_upgrade_link_is_the_freemius_checkout_of_the_addon() {
		$url      = admin_url( 'admin.php?page=alondra-pricing&checkout=true&billing_cycle=annual&plugin_id=38115&plan_id=63451' );
		$freemius = $this->createMock( \Freemius::class );
		$freemius->expects( $this->once() )
			->method( 'checkout_url' )
			->with(
				'annual',
				false,
				[
					'plugin_id' => 38115,
					'plan_id'   => 63451,
					'title'     => 'Alondra Plus',
				]
			)
			->willReturn( $url );
		$freemius->expects( $this->never() )->method( 'get_upgrade_url' );
		$this->install_container( $this->container( fn() => $freemius ) );
		add_submenu_page( '', 'Pricing', 'Pricing', 'manage_options', 'alondra-pricing', '__return_null' );

		$html = $this->render_header( new PreferencesController() );

		// WP 6.8 and 7.1 escape the query's ampersand differently, so compare the decoded markup.
		$this->assertStringContainsString( 'href="' . $url . '" target="_self"', html_entity_decode( $html ) );
	}

	public function test_a_subclass_can_drop_the_banner() {
		$controller = new class() extends PreferencesController {
			protected function upsell_banner(): string {
				return '';
			}
		};

		$this->assertSame( '', $this->render_header( $controller ) );
	}

	public function test_the_tiered_pricing_pages_show_the_banner() {
		$controllers = [
			'free'     => new TieredPricingController(),
			'no-offer' => new class() extends TieredPricingController {
				protected function upsell_banner(): string {
					return '';
				}
			},
		];

		foreach ( $controllers as $name => $controller ) {
			ob_start();
			$controller->show_header();
			$html[ $name ] = (string) ob_get_clean();
		}

		$this->assertStringContainsString( self::UPSELL, $html['free'] );
		$this->assertStringContainsString( 'target="_self">Get Alondra Plus</a>', $html['free'] );
		$this->assertStringNotContainsString( 'Ko-fi', $html['free'] );
		$this->assertStringNotContainsString( 'is-dismissible', $html['free'] );
		$this->assertSame( '', $html['no-offer'] );
	}

	public function test_both_pages_register_their_banner_as_the_page_header() {
		$components = ( new PreferencesController() )->register_settings_page( [] );
		$components = ( new TieredPricingController() )->register_tiered_pricing_listing_page( $components );

		$this->assertCount( 2, $components );
		$header = new \ReflectionProperty( PrefPage::class, 'header' );
		$header->setAccessible( true );
		foreach ( $components as $page ) {
			$this->assertInstanceOf( PrefPage::class, $page );
			ob_start();
			( $header->getValue( $page ) )( 'id' );
			$this->assertStringContainsString( '<div class="notice notice-info alondra-upsell">', (string) ob_get_clean() );
		}
	}
}
