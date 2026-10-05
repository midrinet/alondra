<?php
/**
 * Seed demo store task
 *
 * @package Alondra/Helper
 */

namespace Midrinet\Alondra\Helper\Task;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Runs the demo store seeder over HTTP, so a store the E2E suite has cleared can be restored with a
 * curl instead of a shell in the container.
 *
 * It clears the plugin's three tables first, which also deletes any pricing group made by hand. The
 * suite calls clear_data at the *start* of a spec, so the last spec's group outlives the run holding
 * id 1, and a FREE install takes the first matching group by creation id ascending
 * (TieredPricingRepo::get_matching_order_columns()) -- that leftover would outrank the
 * re-seeded demo group on the same product and the page would quote its tiers instead. Seeding
 * without clearing is not a restore.
 *
 * scripts/setup reaches the seeder through `wp eval-file` instead, so a developer's own groups survive a
 * normal setup; this webhook is the only restore door. Nothing outside those three tables is
 * removed -- the seeder resolves every product, term and user before writing and reports
 * "unchanged" for them.
 */
class SeedDemoStoreTask extends Task {

	/**
	 * Execute the task. Emits its own JSON response and exits, so the caller reads the seeder's
	 * report instead of the generic envelope.
	 *
	 * The seeder runs on include, and aborts by throwing, which the webhook dispatcher turns into
	 * a 500 carrying the reason.
	 *
	 * @param array<string, mixed> $args Unused.
	 */
	public function execute( array $args = [] ): void {
		( new ClearDataTask() )->execute();

		require_once dirname( __DIR__, 2 ) . '/demo-store/seed.php';

		wp_send_json_success( [ 'report' => \alondra_demo_line() ] );
	}
}
