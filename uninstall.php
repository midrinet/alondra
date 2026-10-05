<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * The plugin options are always removed. The pricing data -- the three tables and the migration cursor
 * that describes them -- survives by default, so an uninstall/reinstall cycle finds the groups intact;
 * a site that wants it gone opts in through the `uninstall_cleanup` preference.
 *
 * @package Midrinet/Alondra
 */

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Domain\Datamapper\DatabaseDatamapper;
use Midrinet\Alondra\Infrastructure\Controller\ActivationController;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;

if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

require_once __DIR__ . '/vendor/autoload.php';

// Read before the option holding it goes.
$alondra_uninstall_cleanup = ( new PreferencesService() )->uninstall_cleanup();

delete_option( PreferencesService::PREF_OPTION );
delete_option( ActivationController::PREF_VERSION );
delete_option( TieredPricingRepo::CACHE_GENERATION );

// The failure record describes an attempt against an install that no longer exists. Left behind, it would
// put the failure notice in front of the next install before it had failed at anything.
delete_option( MigrationRunner::PREF_MIGRATION_FAILURE );

// Migration bookkeeping rather than anything the shop owns, so it goes either way. It expires on its own
// within minutes; deleting it only avoids leaving behind a row nobody can account for.
delete_transient( MigrationRunner::MIGRATION_LOCK );

// Default off: the tables and the migration cursor survive an uninstall, so reinstalling finds the pricing
// groups, tiers and rules intact. On a network install WordPress runs uninstall.php once, and both the tables
// and the options are per-site, so only the site the uninstall runs on is cleared.
if ( $alondra_uninstall_cleanup ) {
	// A closure, so these stay local: WordPress includes this file inside uninstall_plugin(), but a
	// static analyser reads it as global scope and every variable here as an unprefixed global.
	( function () {
		global $wpdb;

		// Children before the parent. Current installs have no foreign keys left -- DropForeignKeysMigration
		// removed them -- but a database restored from an older dump still carries them and would refuse the
		// parent while its children reference it.
		$tables = [
			DatabaseDatamapper::TABLE_TIERS,
			DatabaseDatamapper::TABLE_RULES,
			DatabaseDatamapper::TABLE_TIERED_PRICING,
		];

		$dropped = true;

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$result = $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );

			// Strictly false, never falsy: a DROP TABLE that succeeds reports 0 affected rows, and reading that
			// as a failure is the trap this repository has already been caught by twice.
			if ( false === $result ) {
				$dropped = false;

				// The uninstall is the last thing this plugin ever runs -- there is no later request to show a
				// notice on, and nothing left for the reader to act on if there were. The log is the only
				// channel that outlives it, and it is where the migration system already reports schema
				// trouble, so a shop that finds the tables still present can see why.
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error(
						sprintf(
							'Uninstall could not drop %1$s: %2$s',
							$wpdb->prefix . $table,
							$wpdb->last_error
						),
						[ 'source' => MigrationRunner::LOG_SOURCE ]
					);
				}
			}
		}

		// The cursor goes with the tables it describes, and only with them. A drop that failed -- a database
		// user without DROP, a foreign key from an older restored dump still pointing at the parent -- leaves
		// the data in place, and deleting the cursor then would tell the next install its schema is current
		// while the tables still sit at whatever migration they stopped at -- so every migration would replay
		// over data that has already been through them. Keeping it is the half that cannot lose anything.
		if ( $dropped ) {
			delete_option( MigrationRunner::PREF_MIGRATION_ID );
		}
	} )();
}

// Otherwise the cursor stays, because the tables stay and the two have to agree. Clearing it would make the
// next install replay every migration over data that has already been through them -- and a migration only
// has to tolerate failing part-way through *itself*, before its id is recorded. Nothing promises one
// already recorded as applied can be run again from the start.
