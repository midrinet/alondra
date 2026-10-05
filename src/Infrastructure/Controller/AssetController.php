<?php
/**
 * Registers and enqueues the plugin assets
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Controller
 */

namespace Midrinet\Alondra\Infrastructure\Controller;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Registers and enqueues the plugin assets
 *
 * @since   0.1.0
 */
class AssetController extends Controller {

	public const HANDLE_ADMIN = 'admin';
	public const HANDLE_FRONT = 'front';

	public const IS_CLICKABLE_CLASS    = 'alondra-pricing__option--clickable';
	private const OPTION_ACTIVE_CLASS  = 'alondra-pricing__option--active';
	private const OPTION_CLASS         = 'alondra-pricing__option';
	public const WRAPPER_CLASS         = 'alondra-pricing__wrapper';
	public const VARIATION_TIERS_CLASS = 'alondra-variation-tiers';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front' ] );
	}

	/**
	 * Getter
	 *
	 * @since    1.0.0
	 *
	 * @return string
	 */
	public function get_assets_dir_url() {
		return $this->plugin_info()->get_plugin_url() . 'assets';
	}

	private function plugin_info(): PluginInfo {
		return Container::instance()->get( PluginInfo::class );
	}

	/**
	 * Enqueue styles and scripts only in Admin.
	 *
	 * Registration stays global so any screen can enqueue the bundle on demand,
	 * the way the front-end handles do. Only the plugin's own screens get it
	 * enqueued here, which keeps the ~200KB bundle off every other admin page.
	 *
	 * @since    0.1.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin( $hook_suffix = '' ) {
		wp_register_style( self::HANDLE_ADMIN, "{$this->get_assets_dir_url()}/css/admin.css", [], $this->plugin_info()->get_plugin_version() );
		wp_register_script( self::HANDLE_ADMIN, "{$this->get_assets_dir_url()}/js/admin.js", [], $this->plugin_info()->get_plugin_version(), true );

		if ( false === strpos( (string) $hook_suffix, $this->plugin_info()->get_plugin_slug() ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE_ADMIN );
		$this->localize_admin();
		wp_enqueue_script( self::HANDLE_ADMIN );
	}

	/**
	 * Localize Admin
	 *
	 * @since    0.1.0
	 * @return void
	 */
	private function localize_admin() {
		$l10n  = [
			'api'  => [
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'root'      => esc_url_raw( rest_url() ),
				'namespace' => TieredPricingController::REST_NAMESPACE,
			],
			'text' => [
				'prevent_leaving' => __( 'You have unsaved changes. Are you sure you want to leave?', 'alondra' ),
			],
		];
		$roles = [];
		foreach ( wp_roles()->roles as $role => $data ) {
			$roles[] = [
				'role' => $role,
				'name' => $data['name'],
			];
		}
		$l10n['roles'] = $roles;
		wp_localize_script( self::HANDLE_ADMIN, $this->plugin_info()->get_plugin_slug(), $l10n );
	}

	/**
	 * Enqueue styles and scripts only in Front
	 *
	 * @since    0.1.0
	 * @return void
	 */
	public function enqueue_front() {

		$prefs = Container::instance()->get( PreferencesService::class );

		if ( ! $prefs->disable_styles() ) {
			wp_register_style( self::HANDLE_FRONT, "{$this->get_assets_dir_url()}/css/front.css", [], $this->plugin_info()->get_plugin_version() );

			$highlight_on = $prefs->highlight_prices();

			$color   = $prefs->get_color();
			$bg      = $prefs->get_bg_color();
			$bd      = $prefs->get_border_color();
			$h_color = $highlight_on ? $prefs->get_highlight_color() : $color;
			$h_bg    = $highlight_on ? $prefs->get_highlight_bg_color() : $bg;
			$h_bd    = $highlight_on ? $prefs->get_highlight_border_color() : $bd;

			wp_add_inline_style( self::HANDLE_FRONT, ":root{--alondra-color: $color; --alondra-bg-color: $bg;--alondra-bd-color: $bd;--alondra-h-color: $h_color; --alondra-h-bg-color: $h_bg;--alondra-h-bd-color: $h_bd;}" );
		}

		wp_register_script( self::HANDLE_FRONT, "{$this->get_assets_dir_url()}/js/front.js", [], $this->plugin_info()->get_plugin_version(), true );
		$l10n = [
			'is_clickable_class'    => self::IS_CLICKABLE_CLASS,
			'option_class'          => self::OPTION_CLASS,
			'option_active_class'   => self::OPTION_ACTIVE_CLASS,
			'variation_tiers_class' => self::VARIATION_TIERS_CLASS,
			'wrapper_class'         => self::WRAPPER_CLASS,
		];

		wp_localize_script( self::HANDLE_FRONT, 'alondra', $l10n );
	}
}
