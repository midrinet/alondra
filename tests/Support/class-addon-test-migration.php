<?php
/**
 * A migration of the test add-on's chain
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Support;

use Midrinet\Alondra\Infrastructure\Migration\Migration;

/**
 * Counts its runs, so a test can tell the add-on's chain reached it.
 */
class Addon_Test_Migration implements Migration {

	/**
	 * Times execute() ran.
	 *
	 * @var int
	 */
	public static $runs = 0;

	/**
	 * Count the run.
	 */
	public function execute(): void {
		++self::$runs;
	}
}
