<?php
/**
 * Recording controller
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Support;

use Midrinet\Alondra\Infrastructure\Controller\Controller;

/**
 * A controller an add-on could contribute, counting its register() calls.
 */
class Recording_Controller extends Controller {

	/**
	 * How many times register() ran.
	 *
	 * @var int
	 */
	public int $registered = 0;

	/**
	 * Count the call.
	 *
	 * @return void
	 */
	public function register(): void {
		++$this->registered;
	}
}
