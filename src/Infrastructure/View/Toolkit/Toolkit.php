<?php
/**
 * The Toolkit (simplified for Alondra)
 *
 * Manages registration of Toolkit Components (preference pages).
 * Stripped of Field_Service, AJAX, meta filters, and metabox features
 * that Alondra does not use.
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Infrastructure/View/Toolkit
 */

namespace Midrinet\Alondra\Infrastructure\View\Toolkit;

/**
 * The Toolkit
 *
 * @since 1.0.0
 */
class Toolkit {

	/**
	 * Registered components.
	 *
	 * @since 1.0.0
	 *
	 * @var PrefPage[]
	 */
	private array $components;

	/**
	 * Construct
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->components = [];
	}

	/**
	 * Add a List or a Single Preferences Page Component
	 *
	 * @since 1.0.0
	 *
	 * @param PrefPage|PrefPage[] $component_s Preferences Page or list of pages.
	 */
	public function add( $component_s ): void {
		if ( $component_s instanceof PrefPage ) {
			$this->components[] = $component_s;
		} elseif ( \is_array( $component_s ) ) {
			foreach ( $component_s as $ic ) {
				if ( $ic instanceof PrefPage ) {
					$this->components[] = $ic;
				}
			}
		}
	}

	/**
	 * Register Components
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		foreach ( $this->components as $c ) {
			$c->register();
		}
	}
}
