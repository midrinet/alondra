<?php
/**
 * The Preferences Controller
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Controller
 */

namespace Midrinet\Alondra\Infrastructure\Controller;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\View\Preferences\CheckboxFieldView;
use Midrinet\Alondra\Infrastructure\View\Preferences\SelectFieldView;
use Midrinet\Alondra\Infrastructure\View\Preferences\SettingsFooterView;
use Midrinet\Alondra\Infrastructure\View\Preferences\SettingsFormView;
use Midrinet\Alondra\Infrastructure\View\Preferences\SettingsSectionView;
use Midrinet\Alondra\Infrastructure\View\Preferences\TabsView;
use Midrinet\Alondra\Infrastructure\View\Toolkit\PrefPage;

/**
 * The settings page: the free preferences, saved into the `alondra` option through the Settings API.
 *
 * The save is a PATCH: only the keys this page renders are written, every other stored key is kept as
 * it is. A subclass renders more fields by extending sections() and sanitize_field().
 *
 * @phpstan-type Field array{type: string, label: string, description: string, options: array<string, string>, value: string}
 * @phpstan-type Section array{title: string, fields: array<string, Field>}
 */
class PreferencesController extends Controller {

	public const CHANGELOG_URL = 'https://alondra.midri.net/changelog';
	public const REVIEWS_URL   = 'https://wordpress.org/support/plugin/alondra/reviews/';
	public const SUPPORT_URL   = 'https://alondra.midri.net/support/';
	public const KOFI_URL      = 'https://ko-fi.com/midrinet';

	public const PAGE_ID      = 'alondra_settings';
	public const PAGE_SLUG    = 'alondra-settings';
	public const OPTION_GROUP = 'alondra_settings';

	private const TYPE_CHECKBOX = 'checkbox';
	private const TYPE_SELECT   = 'select';

	protected function get_preference_service(): PreferencesService {
		return Container::instance()->get( PreferencesService::class );
	}

	/**
	 * Register the setting and the page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		add_filter( 'alondra_components', [ $this, 'register_settings_page' ] );
		add_filter(
			'plugin_action_links_' . Container::instance()->get( PluginInfo::class )->get_plugin_basename(),
			[ $this, 'add_action_links' ]
		);
	}

	/**
	 * Add the Settings link to the plugin's row in the plugins list, and the Alondra Plus checkout
	 * wherever the upsell banner shows.
	 *
	 * @param array<string, string> $actions The row's action links.
	 * @return array<string, string>
	 */
	public function add_action_links( array $actions ): array {
		$url = menu_page_url( self::PAGE_SLUG, false );
		if ( '' !== $url ) {
			$actions['settings'] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'alondra' ) );
		}
		if ( '' !== $this->upsell_banner() ) {
			$actions['alondra_plus'] = sprintf( '<a href="%s">%s</a>', esc_url( $this->upgrade_url() ), esc_html__( 'Get Alondra Plus', 'alondra' ) );
		}

		return $actions;
	}

	/**
	 * Register the `alondra` option with the Settings API.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			PreferencesService::PREF_OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_option' ],
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Add the settings page to the toolkit components.
	 *
	 * @param array<int|string, mixed> $components The components.
	 * @return array<int|string, mixed>
	 */
	public function register_settings_page( $components ) {
		$components[] = ( new PrefPage( self::PAGE_ID ) )
			->set_menu_title( esc_attr__( 'Alondra', 'alondra' ) )
			->set_page_title( esc_attr__( 'Alondra', 'alondra' ) )
			->set_menu_slug( self::PAGE_SLUG )
			->set_parent_slug( 'options-general.php' )
			->set_header( [ $this, 'show_header' ] )
			->set_content( [ $this, 'show_content' ] )
			->set_footer( [ $this, 'show_footer' ] );

		return $components;
	}

	/**
	 * Print the page footer.
	 *
	 * @return void
	 */
	public function show_footer() {
		echo $this->footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes its own markup; kses would strip the SVG.
	}

	/**
	 * The page footer: FREE and free's version, the changelog and help links, Ko-fi and the rating.
	 *
	 * @return string
	 */
	protected function footer(): string {
		return ( new SettingsFooterView() )->render(
			__( 'FREE', 'alondra' ),
			Container::instance()->get( PluginInfo::class )->get_plugin_version(),
			self::CHANGELOG_URL,
			self::SUPPORT_URL,
			self::REVIEWS_URL,
			self::KOFI_URL
		);
	}

	/**
	 * Print the page header: the upsell banner.
	 *
	 * @return void
	 */
	public function show_header() {
		echo wp_kses_post( $this->upsell_banner() );
	}

	/**
	 * Print the tabs.
	 *
	 * @return void
	 */
	public function show_content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'alondra' ) );
		}

		echo ( new TabsView() )->render( $this->tabs() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the views escape their own markup.
	}

	/**
	 * The page's tabs in order, keyed by id. The first one opens on load.
	 *
	 * @return array<string, array{title: string, content: string}>
	 */
	protected function tabs(): array {
		return [
			'settings' => [
				'title'   => __( 'Settings', 'alondra' ),
				'content' => $this->settings_form(),
			],
		];
	}

	protected function settings_form(): string {
		ob_start();
		settings_fields( self::OPTION_GROUP );
		$fields = (string) ob_get_clean();

		$sections = '';
		foreach ( $this->sections() as $section ) {
			$rows = '';
			foreach ( $section['fields'] as $key => $field ) {
				$rows .= $this->render_field( $key, $field );
			}
			$sections .= ( new SettingsSectionView() )->render( $section['title'], $rows );
		}

		return ( new SettingsFormView() )->render( admin_url( 'options.php' ), $fields, $sections );
	}

	/**
	 * Render one field row.
	 *
	 * @param string $key   Preference key.
	 * @param array  $field Field definition.
	 * @phpstan-param Field $field
	 * @return string
	 */
	protected function render_field( string $key, array $field ): string {
		$name = PreferencesService::PREF_OPTION . '[' . $key . ']';
		$id   = 'alondra-' . str_replace( '_', '-', $key );

		if ( self::TYPE_SELECT === $field['type'] ) {
			return ( new SelectFieldView() )->render( $name, $id, $field['label'], $field['options'], $field['value'] );
		}
		return ( new CheckboxFieldView() )->render( $name, $id, $field['label'], $field['description'], '1' === $field['value'] );
	}

	/**
	 * The rendered sections with their fields, each field carrying the value the form displays.
	 *
	 * @return array<string, array>
	 * @phpstan-return array<string, Section>
	 */
	protected function sections(): array {
		$prefs = $this->get_preference_service();

		return [
			'general'  => [
				'title'  => __( 'User interface', 'alondra' ),
				'fields' => [
					PreferencesService::PREF_LAYOUT_POSITION   => $this->select( __( 'Show prices at', 'alondra' ), $this->layout_positions(), $prefs->get_layout_pos() ),
					PreferencesService::PREF_CLICKABLE_LAYOUT  => $this->checkbox( __( 'Interactive prices', 'alondra' ), __( 'Set the quantity to add to the cart when the customer clicks a price', 'alondra' ), $prefs->is_price_clickable() ),
					PreferencesService::PREF_HIGHLIGHT_PRICING => $this->checkbox( __( 'Highlight current price', 'alondra' ), __( 'Show which price applies to the quantity the customer has chosen', 'alondra' ), $prefs->highlight_prices() ),
				],
			],
			'product'  => [
				'title'  => __( 'Product price', 'alondra' ),
				'fields' => [
					PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE => $this->checkbox( __( 'Replace default price', 'alondra' ), __( 'Show the product price according to the active pricing groups', 'alondra' ), $prefs->overwrite_price() ),
				],
			],
			'advanced' => [
				'title'  => __( 'Advanced', 'alondra' ),
				'fields' => [
					PreferencesService::PREF_ENABLE_CACHE => $this->checkbox( __( 'Enable cache', 'alondra' ), __( 'Keep price lookups in the WordPress object cache', 'alondra' ), $prefs->enable_cache() ),
					PreferencesService::PREF_UNINSTALL_CLEANUP => $this->checkbox( __( 'Uninstall cleanup', 'alondra' ), __( 'Delete the pricing groups, their prices and rules when the plugin is uninstalled', 'alondra' ), $prefs->uninstall_cleanup() ),
				],
			],
		];
	}

	/**
	 * Where the prices table can go on the product page.
	 *
	 * @return array<string, string> Labels keyed by position.
	 */
	protected function layout_positions(): array {
		return [
			PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN => __( 'Above the Add to Cart button', 'alondra' ),
			PreferencesService::LAYOUT_POSITION_HIDE => __( 'Not visible', 'alondra' ),
		];
	}

	/**
	 * Every rendered field, keyed by preference key.
	 *
	 * @return array<string, array>
	 * @phpstan-return array<string, Field>
	 */
	protected function fields(): array {
		$fields = [];
		foreach ( $this->sections() as $section ) {
			$fields = array_merge( $fields, $section['fields'] );
		}
		return $fields;
	}

	/**
	 * The keys a save writes: exactly the rendered ones.
	 *
	 * @return string[]
	 */
	protected function rendered_keys(): array {
		return array_keys( $this->fields() );
	}

	/**
	 * Settings API sanitize callback for the `alondra` option.
	 *
	 * Also runs on every other update_option() of the option, so only this page's own submission is
	 * patched; anything else passes through untouched.
	 *
	 * @param mixed $value Submitted value.
	 * @return mixed
	 */
	public function sanitize_option( $value ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verified this group's nonce before saving.
		if ( ! isset( $_POST['option_page'] ) || self::OPTION_GROUP !== $_POST['option_page'] ) {
			return $value;
		}
		return $this->sanitize( \is_array( $value ) ? $value : [] );
	}

	/**
	 * Merge the submitted rendered keys over the stored option. Unrendered and unknown keys are ignored,
	 * so a stored key this page does not render always survives the save.
	 *
	 * @param array<mixed> $submitted Submitted values.
	 * @return array<mixed> The option to store.
	 */
	protected function sanitize( array $submitted ): array {
		$stored = get_option( PreferencesService::PREF_OPTION );
		$stored = \is_array( $stored ) ? $stored : [];
		$fields = $this->fields();

		foreach ( $this->rendered_keys() as $key ) {
			$value = $this->sanitize_field( $key, $fields[ $key ], $submitted[ $key ] ?? null );
			if ( null !== $value ) {
				$stored[ $key ] = $value;
			}
		}
		return $stored;
	}

	/**
	 * Sanitize one rendered field.
	 *
	 * @param string $key       Preference key.
	 * @param array  $field     Field definition, its value the one the form displayed.
	 * @param mixed  $submitted Submitted value, null when absent.
	 * @phpstan-param Field $field
	 * @return string|null The value to store, or null to keep the stored one.
	 */
	protected function sanitize_field( string $key, array $field, $submitted ): ?string {
		if ( self::TYPE_SELECT === $field['type'] ) {
			return $this->sanitize_select( $field, $submitted );
		}
		return $this->sanitize_checkbox( $submitted );
	}

	/**
	 * An unchecked box is absent from the submission, so anything but '1' is '0'.
	 *
	 * @param mixed $submitted Submitted value.
	 * @return string
	 */
	protected function sanitize_checkbox( $submitted ): string {
		return '1' === $submitted ? '1' : '0';
	}

	/**
	 * The mixed-field rule. A select may display a fallback rather than what is stored (a stored position
	 * this page does not offer shows as the default), so writing back what it displayed would overwrite
	 * the stored value on every save. The value it displayed is recomputed from the stored option on
	 * this request, and the submitted one is written only when it differs from it and is one of the
	 * options. No hidden input carries the stored value.
	 *
	 * @param array $field     Field definition, its value the one the form displayed.
	 * @param mixed $submitted Submitted value.
	 * @phpstan-param Field $field
	 * @return string|null The value to store, or null to keep the stored one.
	 */
	protected function sanitize_select( array $field, $submitted ): ?string {
		if ( ! \is_string( $submitted ) || ! \array_key_exists( $submitted, $field['options'] ) || $submitted === $field['value'] ) {
			return null;
		}
		return $submitted;
	}

	/**
	 * A checkbox field.
	 *
	 * @param string $label       Row label.
	 * @param string $description Text next to the checkbox.
	 * @param bool   $checked     Current value.
	 * @phpstan-return Field
	 * @return array<string, mixed>
	 */
	protected function checkbox( string $label, string $description, bool $checked ): array {
		return [
			'type'        => self::TYPE_CHECKBOX,
			'label'       => $label,
			'description' => $description,
			'options'     => [],
			'value'       => $checked ? '1' : '0',
		];
	}

	/**
	 * A select field.
	 *
	 * @param string                $label   Row label.
	 * @param array<string, string> $options Labels keyed by value.
	 * @param string                $value   The value the form displays.
	 * @phpstan-return Field
	 * @return array<string, mixed>
	 */
	protected function select( string $label, array $options, string $value ): array {
		return [
			'type'        => self::TYPE_SELECT,
			'label'       => $label,
			'description' => '',
			'options'     => $options,
			'value'       => $value,
		];
	}
}
