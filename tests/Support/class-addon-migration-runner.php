<?php
/**
 * A runner an add-on would bind in place of free's
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Support;

use Midrinet\Alondra\Infrastructure\Migration\MigrationChain;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;

require_once __DIR__ . '/class-addon-test-migration.php';

/**
 * Appends one chain after free's, the way an add-on extends the runner.
 */
class Addon_Migration_Runner extends MigrationRunner {

	public const ADDON_CHAIN  = 'alondra-addon-test';
	public const ADDON_CURSOR = 'alondra_addon_test_migration_id';

	/**
	 * Free's chains, then the add-on's.
	 *
	 * @return non-empty-array<int, MigrationChain>
	 */
	protected function chains(): array {
		$chains   = parent::chains();
		$chains[] = new MigrationChain( self::ADDON_CHAIN, self::ADDON_CURSOR, [ 1 => Addon_Test_Migration::class ] );

		return $chains;
	}

	/**
	 * The chains, readable from a test.
	 *
	 * @return non-empty-array<int, MigrationChain>
	 */
	public function exposed_chains(): array {
		return $this->chains();
	}
}
