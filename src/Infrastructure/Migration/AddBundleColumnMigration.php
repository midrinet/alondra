<?php
/**
 * The bundle-scoped rule target Migration
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/Migration
 */

namespace Midrinet\Alondra\Infrastructure\Migration;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Adds the rules table's bundle_product column, which holds "<bundle_id>:<product_id>" pairs.
 *
 * Carries no ALTER despite being an add-a-column migration: dbDelta is declarative, so re-running the
 * datamapper's own CREATE TABLE adds the one column the live table is missing and leaves the rest alone.
 * That also makes it re-entrant by construction, as the interface requires, and keeps a single declaration
 * of the schema rather than a CREATE and an ALTER that can drift apart.
 *
 * @since      2.0.0
 */
class AddBundleColumnMigration implements Migration {

	public function execute(): void {
		Container::instance()->get( TieredPricingService::class )->setup();
	}
}
