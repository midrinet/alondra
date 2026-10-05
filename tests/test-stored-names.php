<?php
/**
 * Stored names Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests;

use Midrinet\Alondra\Domain\Datamapper\DatabaseDatamapper;
use Midrinet\Alondra\Infrastructure\Controller\ActivationController;
use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use WP_UnitTestCase;

/**
 * Names already written to existing installs: renaming one orphans what is stored under it.
 */
class StoredNamesTest extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public function stored_names(): array {
		return [
			'preferences option'       => [ PreferencesService::PREF_OPTION, 'alondra' ],
			'version option'           => [ ActivationController::PREF_VERSION, 'alondra_version' ],
			'migration cursor option'  => [ MigrationRunner::PREF_MIGRATION_ID, 'alondra_migration_id' ],
			'migration failure option' => [ MigrationRunner::PREF_MIGRATION_FAILURE, 'alondra_migration_failure' ],
			'cache generation option'  => [ TieredPricingRepo::CACHE_GENERATION, 'alondra_cache_generation' ],
			'cache group'              => [ TieredPricingRepo::CACHE_GROUP, 'alondra' ],
			'migration lock transient' => [ MigrationRunner::MIGRATION_LOCK, 'alondra_migration_lock' ],
			'rules table'              => [ DatabaseDatamapper::TABLE_RULES, 'alondra_rules' ],
			'tiers table'              => [ DatabaseDatamapper::TABLE_TIERS, 'alondra_tiers' ],
			'tiered pricing table'     => [ DatabaseDatamapper::TABLE_TIERED_PRICING, 'alondra_tiered_pricing' ],
			'clickable layout pref'    => [ PreferencesService::PREF_CLICKABLE_LAYOUT, 'clickable_layout' ],
			'layout position pref'     => [ PreferencesService::PREF_LAYOUT_POSITION, 'layout_pos' ],
			'pricing layout pref'      => [ PreferencesService::PREF_PRICING_LAYOUT, 'pricing_layout' ],
			'highlight pricing pref'   => [ PreferencesService::PREF_HIGHLIGHT_PRICING, 'highlight_pricing' ],
			'highlight bg colour pref' => [ PreferencesService::PREF_HIGHLIGHT_BG_COLOR, 'highlight_bg_color' ],
			'highlight colour pref'    => [ PreferencesService::PREF_HIGHLIGHT_COLOR, 'highlight_color' ],
			'highlight border pref'    => [ PreferencesService::PREF_HIGHLIGHT_BORDER_COLOR, 'highlight_border_color' ],
			'bg colour pref'           => [ PreferencesService::PREF_BG_COLOR, 'bg_color' ],
			'colour pref'              => [ PreferencesService::PREF_COLOR, 'color' ],
			'border colour pref'       => [ PreferencesService::PREF_BORDER_COLOR, 'border_color' ],
			'overwrite price pref'     => [ PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE, 'overwrite_product_price' ],
			'disable styles pref'      => [ PreferencesService::PREF_DISABLE_STYLES, 'disable_styles' ],
			'enable cache pref'        => [ PreferencesService::PREF_ENABLE_CACHE, 'enable_cache' ],
			'uninstall cleanup pref'   => [ PreferencesService::PREF_UNINSTALL_CLEANUP, 'uninstall_cleanup' ],
			'table layout value'       => [ PreferencesService::PRICING_LAYOUT_TABLE, 'table' ],
			'hidden position value'    => [ PreferencesService::LAYOUT_POSITION_HIDE, 'hide' ],
			'before button position'   => [ PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN, 'before_add_to_cart_btn' ],
		];
	}

	/**
	 * @dataProvider stored_names
	 *
	 * @param string $name    Name the code uses.
	 * @param string $literal Name stored on existing installs.
	 */
	public function test_a_stored_name_never_changes( string $name, string $literal ) {
		$this->assertSame( $literal, $name );
	}
}
