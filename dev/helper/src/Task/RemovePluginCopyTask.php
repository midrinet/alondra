<?php
/**
 * Remove plugin copy task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Deletes the disposable plugin copy (`alondra-uninstall-copy`) provisioned by
 * ProvisionPluginCopyTask. Teardown safety net when the wp-admin Delete flow
 * did not run. Only ever touches the copy directory, never the source.
 */
class RemovePluginCopyTask extends Task {

	private const COPY_DIR = 'alondra-uninstall-copy';

	/**
	 * Execute the task
	 *
	 * @param array<string, mixed> $args Unused.
	 */
	public function execute( array $args = [] ): void {
		$dest = WP_PLUGIN_DIR . '/' . self::COPY_DIR;
		if ( is_dir( $dest ) ) {
			$this->remove_dir( $dest );
		}
	}

	/**
	 * Recursively delete a directory and its contents. Surfaces a failure rather
	 * than leaving a partial copy that ProvisionPluginCopyTask's idempotency
	 * guard would then silently reuse.
	 *
	 * @throws \Exception If the directory cannot be read or an entry cannot be removed.
	 */
	private function remove_dir( string $dir ): void {
		$items = scandir( $dir );
		if ( false === $items ) {
			throw new \Exception( "Could not read directory: $dir", 500 );
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = "$dir/$item";
			if ( is_dir( $path ) ) {
				$this->remove_dir( $path );
			} elseif ( ! unlink( $path ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- deleting the disposable plugin copy is what this dev-only task exists for; the walk starts at WP_PLUGIN_DIR/alondra-uninstall-copy, the copy ProvisionPluginCopyTask made, never at the bind-mounted source.
				throw new \Exception( "Could not delete file: $path", 500 );
			}
		}
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- the directory this empties is the one the loop above just cleared, under WP_PLUGIN_DIR/alondra-uninstall-copy.
		if ( ! rmdir( $dir ) ) {
			throw new \Exception( "Could not remove directory: $dir", 500 );
		}
	}
}
