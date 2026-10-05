<?php
/**
 * Provision plugin copy task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Copies the installed plugin directory to a disposable sibling
 * (`alondra-uninstall-copy`) so E2E tests can delete it through the real
 * wp-admin flow without touching the bind-mounted source. The copy lands in the
 * plugins volume, not the host mount.
 */
class ProvisionPluginCopyTask extends Task {

	private const SOURCE_DIR = 'alondra';
	private const COPY_DIR   = 'alondra-uninstall-copy';

	/**
	 * Top-level entries of the source that are repository tooling, not plugin: the live
	 * plugin is mounted from the repo root.
	 */
	private const SKIP_TOP_LEVEL = [ '.env', '.git', 'dev', 'docker', 'release' ];

	/**
	 * Execute the task
	 *
	 * @param array<string, mixed> $args Unused.
	 * @throws \Exception If the source is missing or the copy fails.
	 */
	public function execute( array $args = [] ): void {
		$source = WP_PLUGIN_DIR . '/' . self::SOURCE_DIR;
		$dest   = WP_PLUGIN_DIR . '/' . self::COPY_DIR;

		if ( ! is_dir( $source ) ) {
			throw new \Exception( 'Source plugin directory not found', 500 );
		}

		if ( is_dir( $dest ) ) {
			return; // Idempotent: copy already provisioned.
		}

		$this->copy_dir( $source, $dest, self::SKIP_TOP_LEVEL );
	}

	/**
	 * Recursively copy a directory, skipping node_modules (large and irrelevant
	 * to the runtime plugin) and any `$skip` entry directly under `$source`.
	 *
	 * @param string   $source Directory to copy.
	 * @param string   $dest   Destination directory.
	 * @param string[] $skip   Entry names to leave out at this level only.
	 * @throws \Exception If a directory or file cannot be created/copied.
	 */
	private function copy_dir( string $source, string $dest, array $skip = [] ): void {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir -- creating the disposable plugin copy is what this dev-only task exists for; WP_Filesystem cannot be used before wp-admin is loaded and the destination is always the plugins volume, never the bind-mounted source.
		if ( ! is_dir( $dest ) && ! mkdir( $dest, 0755, true ) && ! is_dir( $dest ) ) {
			throw new \Exception( "Could not create directory: $dest", 500 );
		}

		$items = scandir( $source );
		if ( false === $items ) {
			throw new \Exception( "Could not read directory: $source", 500 );
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || 'node_modules' === $item || in_array( $item, $skip, true ) ) {
				continue;
			}
			$src_path  = "$source/$item";
			$dest_path = "$dest/$item";
			if ( is_dir( $src_path ) ) {
				$this->copy_dir( $src_path, $dest_path );
			} elseif ( ! copy( $src_path, $dest_path ) ) {
				throw new \Exception( "Could not copy file: $src_path", 500 );
			}
		}
	}
}
