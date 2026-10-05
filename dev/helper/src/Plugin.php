<?php
/**
 * Alondra Helper Plugin
 * 
 * @package Alondra_Helper
 */

namespace Midrinet\Alondra\Helper;

use Midrinet\Alondra\Helper\Task\ActivatePluginTask;
use Midrinet\Alondra\Helper\Task\ChangeStatusToTieredPricingTask;
use Midrinet\Alondra\Helper\Task\ClearDataTask;
use Midrinet\Alondra\Helper\Task\CreateSampleTieredPricingTask;
use Midrinet\Alondra\Helper\Task\EmptyCartTask;
use Midrinet\Alondra\Helper\Task\PluginStatusTask;
use Midrinet\Alondra\Helper\Task\ProvisionPluginCopyTask;
use Midrinet\Alondra\Helper\Task\RemovePluginCopyTask;
use Midrinet\Alondra\Helper\Task\SeedDemoStoreTask;
use Midrinet\Alondra\Helper\Task\SetProductCategoriesTask;
use Midrinet\Alondra\Helper\Task\SetProductTagsTask;
use Midrinet\Alondra\Helper\Task\SetUserRolesTask;
use Midrinet\Alondra\Helper\Task\Task;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Recommended

/**
 * Alondra Helper Plugin
 */
class Plugin {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'handle_webhook' ] );
		// This disable entirely the coming soon page functionality from WooCommerce that affects the E2E tests.
		add_filter( 'woocommerce_coming_soon_exclude', '__return_true', 10 );
		// Prevent automatic wizard redirect.
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true' );
	}

	/**
	 * Get task for webhook
	 *
	 * @param string $webhook Webhook name
	 * @return Task Task instance
	 */
	private function get_task_for_webhook( $webhook ): Task {

		/**
		 * Webhook name => Task subclass map. Add-ons append their own; an
		 * add-on's entry may override a built-in name to re-scope it.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, class-string<Task>> $tasks Webhook name => Task class name.
		 */
		$tasks = (array) apply_filters(
			'alondra_helper_tasks',
			[
				'clear_data'                      => ClearDataTask::class,
				'create_sample_tiered_pricing'    => CreateSampleTieredPricingTask::class,
				'change_status_to_tiered_pricing' => ChangeStatusToTieredPricingTask::class,
				'empty_cart'                      => EmptyCartTask::class,
				'set_product_tags'                => SetProductTagsTask::class,
				'set_product_categories'          => SetProductCategoriesTask::class,
				'set_user_roles'                  => SetUserRolesTask::class,
				'provision_plugin_copy'           => ProvisionPluginCopyTask::class,
				'remove_plugin_copy'              => RemovePluginCopyTask::class,
				'plugin_status'                   => PluginStatusTask::class,
				'activate_plugin'                 => ActivatePluginTask::class,
				'seed_demo_store'                 => SeedDemoStoreTask::class,
			]
		);

		if ( ! isset( $tasks[ $webhook ] ) ) {
			return new Task();
		}

		if ( ! \is_string( $tasks[ $webhook ] ) || ! is_subclass_of( $tasks[ $webhook ], Task::class ) ) {
			_doing_it_wrong( 'alondra_helper_tasks', 'Every entry must name a Task subclass.', '2.0.0' );
			return new Task();
		}

		return new $tasks[ $webhook ]();
	}

	/**
	 * Handle webhook
	 * Example: curl -X POST  http://localhost:8080/?alondra-webhook=clear_data
	 */
	public function handle_webhook(): void {
		if ( ! isset( $_GET['alondra-webhook'] ) ) {
			return;
		}

		header( 'Content-Type: application/json' );

		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json_error( [ 'message' => 'Invalid request method' ], 405 );
		}

		$task = $this->get_task_for_webhook( sanitize_text_field( $_GET['alondra-webhook'] ) );
		try {
			$task->execute( $_REQUEST );
			wp_send_json_success( [ 'message' => 'Task executed' ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ], $e->getCode() );
		}
	}
}
