<?php
/**
 * Plugin identity
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Config
 */

namespace Midrinet\Alondra\Infrastructure\Config;

/**
 * The plugin's identity, read from the main file's header so WordPress and the code share one source.
 */
class PluginInfo {

	/**
	 * URL of the plugin directory, with a trailing slash.
	 */
	private string $plugin_url;

	/**
	 * Plugin basename, e.g. `alondra/alondra.php`.
	 */
	private string $plugin_basename;

	/**
	 * `Version` header.
	 */
	private string $plugin_version;

	/**
	 * `Text Domain` header, which is also the plugin slug.
	 */
	private string $plugin_slug;

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_url      = plugin_dir_url( $plugin_file );
		$this->plugin_basename = plugin_basename( $plugin_file );

		$metadata = get_file_data(
			$plugin_file,
			[
				'version'     => 'Version',
				'text_domain' => 'Text Domain',
			]
		);

		$this->plugin_version = $metadata['version'];
		$this->plugin_slug    = $metadata['text_domain'];
	}

	/**
	 * URL of the plugin directory, with a trailing slash.
	 */
	public function get_plugin_url(): string {
		return $this->plugin_url;
	}

	/**
	 * Plugin basename, e.g. `alondra/alondra.php`.
	 */
	public function get_plugin_basename(): string {
		return $this->plugin_basename;
	}

	public function get_plugin_version(): string {
		return $this->plugin_version;
	}

	/**
	 * Plugin slug. Every admin page slug, script object and cache group the plugin owns starts with it.
	 */
	public function get_plugin_slug(): string {
		return $this->plugin_slug;
	}
}
