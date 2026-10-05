<?php
/**
 * The table-creation Migration
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Creates the plugin's tables.
 *
 * Expressed as a migration rather than as a separate install routine so a fresh install and an upgrade run the
 * same code. There is no install-only path left to rot.
 *
 * @since      1.0.3
 */
class CreateTablesMigration implements Migration {

	/**
	 * Create the tables.
	 *
	 * Safe to re-enter, as the interface requires: setup() declares each table through dbDelta, which adds what
	 * is missing and leaves what already matches alone.
	 *
	 * @return void
	 */
	public function execute(): void {
		Container::instance()->get( TieredPricingService::class )->setup();
	}
}
