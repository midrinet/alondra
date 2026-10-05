<?php
/**
 * Tiered pricing list table
 *
 * @package Midrinet\Alondra\Infrastructure\View\Admin
 */

namespace Midrinet\Alondra\Infrastructure\View\Admin;

use Midrinet\Alondra\Infrastructure\View\ListTable;

class TieredPricingListTableView {

	public function render( ListTable $table ): string {
		ob_start();
		$table->views();
		echo "\n<form method=\"POST\">\n\t";
		$table->prepare_items();
		$table->search_box( __( 'Search', 'alondra' ), 'search_id' );
		$table->display();
		echo "</form>\n";
		return (string) ob_get_clean();
	}
}
