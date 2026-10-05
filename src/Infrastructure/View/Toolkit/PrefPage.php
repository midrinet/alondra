<?php
/**
 * Preferences Page
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/View/Toolkit
 */

namespace Midrinet\Alondra\Infrastructure\View\Toolkit;

use Midrinet\Alondra\Infrastructure\View\Preferences\PreferencesPageCloseView;
use Midrinet\Alondra\Infrastructure\View\Preferences\PreferencesPageOpenView;
use Midrinet\Alondra\Infrastructure\View\Preferences\PreferencesPageTitlebarView;

/**
 * Preferences Page Class
 *
 * @since 1.0.0
 */
class PrefPage {

	/**
	 * Preferences Page ID.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Title for the page.
	 *
	 * @var string
	 */
	protected $page_title = '';

	/**
	 * Title for the menu.
	 *
	 * @var string
	 */
	protected $menu_title;

	/**
	 * Capability.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Page menu slug.
	 *
	 * @var string
	 */
	protected $menu_slug;

	/**
	 * Parent menu slug.
	 *
	 * @var string|null
	 */
	protected $parent_slug = null;

	/**
	 * Icon url.
	 *
	 * @var string
	 */
	protected $icon_url = 'dashicons-admin-plugins';

	/**
	 * Position.
	 *
	 * @var int|null
	 */
	protected $position = null;

	/**
	 * Prints the header area. Called with the page id.
	 *
	 * @var callable|null
	 */
	protected $header = null;

	/**
	 * Prints the content area. Called with the page id.
	 *
	 * @var callable|null
	 */
	protected $content = null;

	/**
	 * Prints the titlebar options area. Called with the page id.
	 *
	 * @var callable|null
	 */
	protected $titlebar_options = null;

	/**
	 * Prints the footer area. Called with the page id.
	 *
	 * @var callable|null
	 */
	protected $footer = null;

	/**
	 * @since 1.0.0
	 *
	 * @param string|null $id The ID.
	 */
	public function __construct( $id = null ) {
		$this->id         = null !== $id ? $id : \uniqid();
		$this->menu_title = $this->id;
		$this->menu_slug  = $this->id;
	}

	/**
	 * Set page_title
	 *
	 * @param string $page_title Page title.
	 *
	 * @return PrefPage
	 */
	public function set_page_title( $page_title ) {
		$this->page_title = $page_title;
		return $this;
	}

	/**
	 * Set menu_title
	 *
	 * @param string $menu_title Menu title.
	 *
	 * @return PrefPage
	 */
	public function set_menu_title( $menu_title ) {
		$this->menu_title = $menu_title;
		return $this;
	}

	/**
	 * Set menu_slug
	 *
	 * @param string $menu_slug Menu slug.
	 *
	 * @return PrefPage
	 */
	public function set_menu_slug( $menu_slug ) {
		$this->menu_slug = $menu_slug;
		return $this;
	}

	/**
	 * Set parent_slug
	 *
	 * @param string $parent_slug Parent slug.
	 *
	 * @return PrefPage
	 */
	public function set_parent_slug( $parent_slug ) {
		$this->parent_slug = $parent_slug;
		return $this;
	}

	/**
	 * Set the callback that prints the header area
	 *
	 * @param callable $header Called with the page id.
	 *
	 * @return PrefPage
	 */
	public function set_header( callable $header ) {
		$this->header = $header;
		return $this;
	}

	/**
	 * Set the callback that prints the content area
	 *
	 * @param callable $content Called with the page id.
	 *
	 * @return PrefPage
	 */
	public function set_content( callable $content ) {
		$this->content = $content;
		return $this;
	}

	/**
	 * Set the callback that prints the titlebar options area
	 *
	 * @param callable $titlebar_options Called with the page id.
	 *
	 * @return PrefPage
	 */
	public function set_titlebar_options( callable $titlebar_options ) {
		$this->titlebar_options = $titlebar_options;
		return $this;
	}

	/**
	 * Set the callback that prints the footer area
	 *
	 * @param callable $footer Called with the page id.
	 *
	 * @return PrefPage
	 */
	public function set_footer( callable $footer ) {
		$this->footer = $footer;
		return $this;
	}

	/**
	 * Register this page using WordPress hooks
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checks which admin page is being rendered.
		if ( isset( $_GET['page'] ) && $this->menu_slug === $_GET['page'] ) {
			add_action( 'in_admin_header', [ $this, 'embed_page_header' ] );
		}
	}

	/**
	 * Register menu
	 *
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		if ( empty( $this->parent_slug ) ) {
			add_menu_page( $this->page_title, $this->menu_title, $this->capability, $this->menu_slug, [ $this, 'output_settings_page' ], $this->icon_url );
		} else {
			add_submenu_page( $this->parent_slug, $this->page_title, $this->menu_title, $this->capability, $this->menu_slug, [ $this, 'output_settings_page' ], $this->position );
		}
	}

	/**
	 * Output settings page
	 *
	 * @since 1.0.0
	 */
	public function output_settings_page(): void {
		$header  = $this->capture( $this->header );
		$content = $this->capture( $this->content );
		$footer  = $this->capture( $this->footer );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- the views escape their own markup.
		echo ( new PreferencesPageOpenView() )->render( $this->id, $header, $content );
		echo ( new PreferencesPageCloseView() )->render( $footer );
		// phpcs:enable
	}

	/**
	 * Embed html for title bar in preference page
	 *
	 * @since 1.0.0
	 */
	public function embed_page_header(): void {
		$options = $this->capture( $this->titlebar_options );

		echo ( new PreferencesPageTitlebarView() )->render( $this->id, get_admin_page_title(), $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes its own markup.
	}

	/**
	 * Run an area callback and return what it printed.
	 *
	 * @param callable|null $callback Area callback, called with the page id.
	 * @return string
	 */
	private function capture( $callback ): string {
		if ( null === $callback ) {
			return '';
		}
		ob_start();
		$callback( $this->id );
		return (string) ob_get_clean();
	}
}
