<?php
/**
 * Task class
 * 
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Task class
 */
class Task {

	/**
	 * Execute the task
	 * 
	 * @throws \Exception If the task fails
	 */
	public function execute( array $args = [] ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the signature every Task subclass overrides and Plugin::handle_webhook() dispatches against; this base body only throws.
		throw new \Exception( 'Task not implemented', 500 );
	}

	/**
	 * Decode a required JSON-array webhook argument.
	 *
	 * @param array<string, mixed> $args Webhook request args.
	 * @param string                $key Argument name.
	 * @throws \Exception If the argument is missing or not valid JSON array.
	 * @return array<int|string, mixed>
	 */
	protected function get_json_array_arg( array $args, string $key ): array {
		if ( ! isset( $args[ $key ] ) ) {
			throw new \Exception( "Missing \"$key\" argument", 400 );
		}

		$value = json_decode( (string) wp_unslash( $args[ $key ] ), true );
		if ( ! \is_array( $value ) ) {
			throw new \Exception( "Invalid \"$key\" JSON", 400 );
		}

		return $value;
	}

	/**
	 * Get the table name for rules table
	 */
	protected function get_rules_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'alondra_rules';
	}

	/**
	 * Get the table name for tier table
	 */
	protected function get_tiers_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'alondra_tiers';
	}

	/**
	 * Get the table name for tiered pricing table
	 */
	protected function get_tiered_pricing_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'alondra_tiered_pricing';
	}

	/**
	 * Invalidate the Alondra plugin's tiered-pricing cache after writing the
	 * alondra_* tables directly with $wpdb, bypassing its repository.
	 *
	 * Goes through the plugin's own TieredPricingRepo::flush_cache(). Its
	 * container is only built on `plugins_loaded`, and its class is not even
	 * autoloaded while the plugin is deactivated — either is a no-op rather
	 * than fatal, since a 500 here breaks the E2E suite worse than a stale
	 * cache would, and with the plugin unavailable nothing is serving prices
	 * from that cache anyway.
	 */
	protected function flush_alondra_cache(): void {
		// ::class is a compile-time string, safe to reference even when the
		// class in question is not loaded (deactivated alondra); the real
		// static call below is guarded by class_exists() first.
		if ( ! class_exists( \Midrinet\Alondra\Infrastructure\DI\Container::class ) ) {
			return;
		}
		try {
			$container = \Midrinet\Alondra\Infrastructure\DI\Container::instance();
		} catch ( \RuntimeException $e ) {
			return;
		}
		$container->get( \Midrinet\Alondra\Domain\Repository\TieredPricingRepo::class )->flush_cache();
	}
}
