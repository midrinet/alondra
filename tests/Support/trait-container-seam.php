<?php
/**
 * Container seam
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Support;

use Midrinet\Alondra\Infrastructure\DI\Container;

/**
 * Installs a test double as Container::instance(), or drops it, and puts the plugin's own container back after the test.
 */
trait Container_Seam {

	/**
	 * The container the first install replaced.
	 *
	 * @var Container|null
	 */
	private ?Container $replaced_container = null;

	/**
	 * Make $container the one Container::instance() returns until the test ends.
	 *
	 * @param Container $container Container to install.
	 * @return void
	 */
	protected function install_container( Container $container ) {
		$previous = Container::set_instance( $container );
		if ( null === $this->replaced_container ) {
			$this->replaced_container = $previous;
		}
	}

	/**
	 * Drop the built container, so the next Container::build() runs again, until the test ends.
	 *
	 * @return void
	 */
	protected function reset_container() {
		$previous = Container::set_instance( null );
		if ( null === $this->replaced_container ) {
			$this->replaced_container = $previous;
		}
	}

	/**
	 * Restore the container the test replaced.
	 *
	 * @after
	 * @return void
	 */
	public function restore_container() {
		if ( null !== $this->replaced_container ) {
			Container::set_instance( $this->replaced_container );
			$this->replaced_container = null;
		}
	}
}
