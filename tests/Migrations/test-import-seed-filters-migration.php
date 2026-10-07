<?php
/**
 * ImportSeedFiltersMigration Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Migrations;

use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\ImportSeedFiltersMigration;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;
use WP_UnitTestCase;

/**
 * Tests for the one-time import of the retired seed filters into the preferences option.
 */
class ImportSeedFiltersMigrationTest extends WP_UnitTestCase {

	private const PREFS_FILTER     = 'alondra_default_prefs';
	private const UNINSTALL_FILTER = 'alondra_delete_data_on_uninstall';

	/**
	 * How many times each retired filter was applied, keyed by filter name.
	 *
	 * @var array<string, int>
	 */
	private $calls = [];

	public function set_up() {
		parent::set_up();

		// The bootstrap's `init` already ran the import with no listener, before any transaction opened.
		delete_option( PreferencesService::PREF_OPTION );
		delete_transient( MigrationRunner::MIGRATION_LOCK );
		$this->calls = [
			self::PREFS_FILTER     => 0,
			self::UNINSTALL_FILTER => 0,
		];
	}

	public function tear_down() {
		// The service memoises the cursor, which the transaction rollback cannot reach.
		Container::instance()->get( MigrationRunner::class )->set_migration_id( MigrationRunner::IMPORT_SEED_FILTERS );
		delete_transient( MigrationRunner::MIGRATION_LOCK );
		parent::tear_down();
	}

	/**
	 * Register counting listeners returning the given values.
	 *
	 * @param mixed $prefs     What the preferences filter returns.
	 * @param mixed $uninstall What the uninstall filter returns.
	 */
	private function listen( $prefs, $uninstall ): void {
		add_filter(
			self::PREFS_FILTER,
			function () use ( $prefs ) {
				++$this->calls[ self::PREFS_FILTER ];
				return $prefs;
			}
		);
		add_filter(
			self::UNINSTALL_FILTER,
			function () use ( $uninstall ) {
				++$this->calls[ self::UNINSTALL_FILTER ];
				return $uninstall;
			}
		);
	}

	private function execute(): void {
		( new ImportSeedFiltersMigration() )->execute();
	}

	public function test_filtered_values_land_in_the_option() {
		$this->listen(
			[
				PreferencesService::PREF_LAYOUT_POSITION  => PreferencesService::LAYOUT_POSITION_HIDE,
				PreferencesService::PREF_CLICKABLE_LAYOUT => '0',
				PreferencesService::PREF_COLOR            => '#ff0000',
			],
			true
		);

		$this->execute();

		$this->assertSame(
			[
				PreferencesService::PREF_LAYOUT_POSITION   => PreferencesService::LAYOUT_POSITION_HIDE,
				PreferencesService::PREF_CLICKABLE_LAYOUT  => '0',
				PreferencesService::PREF_UNINSTALL_CLEANUP => '1',
			],
			get_option( PreferencesService::PREF_OPTION )
		);
	}

	public function test_without_listeners_only_the_uninstall_default_is_stored() {
		$this->execute();

		$this->assertSame( [ PreferencesService::PREF_UNINSTALL_CLEANUP => '0' ], get_option( PreferencesService::PREF_OPTION ) );
	}

	public function test_stored_values_win() {
		update_option(
			PreferencesService::PREF_OPTION,
			[
				PreferencesService::PREF_CLICKABLE_LAYOUT  => '1',
				PreferencesService::PREF_UNINSTALL_CLEANUP => '0',
				PreferencesService::PREF_PRICING_LAYOUT    => 'pills',
			]
		);
		$this->listen(
			[
				PreferencesService::PREF_CLICKABLE_LAYOUT  => '0',
				PreferencesService::PREF_HIGHLIGHT_PRICING => '0',
			],
			true
		);

		$this->execute();

		$this->assertSame(
			[
				PreferencesService::PREF_CLICKABLE_LAYOUT  => '1',
				PreferencesService::PREF_UNINSTALL_CLEANUP => '0',
				PreferencesService::PREF_PRICING_LAYOUT    => 'pills',
				PreferencesService::PREF_HIGHLIGHT_PRICING => '0',
				PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE => '0',
				PreferencesService::PREF_ENABLE_CACHE      => '0',
			],
			get_option( PreferencesService::PREF_OPTION )
		);
	}

	/**
	 * An older settings screen saved an unchecked box by leaving it out, and read that as off.
	 */
	public function test_a_legacy_option_keeps_its_unchecked_boxes_off() {
		update_option(
			PreferencesService::PREF_OPTION,
			[
				PreferencesService::PREF_LAYOUT_POSITION   => PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN,
				PreferencesService::PREF_PRICING_LAYOUT    => 'table',
				PreferencesService::PREF_COLOR             => '#000000',
				PreferencesService::PREF_CLICKABLE_LAYOUT  => '1',
				PreferencesService::PREF_HIGHLIGHT_PRICING => '1',
				PreferencesService::PREF_ENABLE_CACHE      => '1',
			]
		);
		$this->listen( [ PreferencesService::PREF_LAYOUT_POSITION => PreferencesService::LAYOUT_POSITION_HIDE ], false );

		$this->execute();
		$first = get_option( PreferencesService::PREF_OPTION );
		$this->execute();

		$this->assertSame( '0', $first[ PreferencesService::PREF_OVERWRITE_PRODUCT_PRICE ] );
		$this->assertSame( PreferencesService::LAYOUT_POSITION_BEFORE_ADD_TO_CART_BTN, $first[ PreferencesService::PREF_LAYOUT_POSITION ] );
		$this->assertFalse( ( new PreferencesService() )->overwrite_price() );
		$this->assertTrue( ( new PreferencesService() )->enable_cache() );
		$this->assertSame( $first, get_option( PreferencesService::PREF_OPTION ) );
	}

	public function test_listener_reads_the_free_defaults_it_was_handed() {
		add_filter(
			self::PREFS_FILTER,
			function ( $prefs ) {
				$prefs[ PreferencesService::PREF_CLICKABLE_LAYOUT ] = $prefs[ PreferencesService::PREF_HIGHLIGHT_PRICING ] ? '0' : '1';
				return $prefs;
			}
		);

		$this->execute();

		$this->assertSame(
			[
				PreferencesService::PREF_CLICKABLE_LAYOUT  => '0',
				PreferencesService::PREF_UNINSTALL_CLEANUP => '0',
			],
			get_option( PreferencesService::PREF_OPTION )
		);
	}

	public function test_a_listener_returning_its_input_imports_nothing() {
		add_filter(
			self::PREFS_FILTER,
			function ( $prefs ) {
				return $prefs;
			}
		);

		$this->execute();

		$this->assertSame( [ PreferencesService::PREF_UNINSTALL_CLEANUP => '0' ], get_option( PreferencesService::PREF_OPTION ) );
	}

	/**
	 * A re-entry finds every key it would write already there.
	 */
	public function test_a_second_execution_changes_nothing() {
		$this->listen( [ PreferencesService::PREF_CLICKABLE_LAYOUT => '0' ], true );
		$this->execute();
		$first = get_option( PreferencesService::PREF_OPTION );

		remove_all_filters( self::PREFS_FILTER );
		remove_all_filters( self::UNINSTALL_FILTER );
		$this->listen( [ PreferencesService::PREF_CLICKABLE_LAYOUT => '1' ], false );
		$this->execute();

		$this->assertSame( $first, get_option( PreferencesService::PREF_OPTION ) );
	}

	/**
	 * @dataProvider provide_odd_filter_output
	 *
	 * @param mixed                $prefs     What the preferences filter returns.
	 * @param mixed                $uninstall What the uninstall filter returns.
	 * @param array<string, mixed> $expected  The stored option.
	 */
	public function test_odd_filter_output_is_tolerated( $prefs, $uninstall, array $expected ) {
		$this->listen( $prefs, $uninstall );

		$this->execute();

		$this->assertSame( $expected, get_option( PreferencesService::PREF_OPTION ) );
	}

	public function provide_odd_filter_output(): array {
		return [
			'non-array prefs, truthy string' => [ 'nope', 'yes', [ PreferencesService::PREF_UNINSTALL_CLEANUP => '1' ] ],
			'null prefs, integer zero'       => [ null, 0, [ PreferencesService::PREF_UNINSTALL_CLEANUP => '0' ] ],
			'non-scalar value, empty array'  => [
				[
					PreferencesService::PREF_LAYOUT_POSITION   => [ 'hide' ],
					PreferencesService::PREF_HIGHLIGHT_PRICING => 0,
				],
				[],
				[
					PreferencesService::PREF_HIGHLIGHT_PRICING => 0,
					PreferencesService::PREF_UNINSTALL_CLEANUP => '0',
				],
			],
		];
	}

	public function test_import_is_migration_four_at_the_head_of_free_chain() {
		$this->assertSame( 4, MigrationRunner::IMPORT_SEED_FILTERS );
		$this->assertSame( ImportSeedFiltersMigration::class, MigrationRunner::MIGRATIONS[ MigrationRunner::IMPORT_SEED_FILTERS ] );
		$this->assertSame( MigrationRunner::IMPORT_SEED_FILTERS, max( array_keys( MigrationRunner::MIGRATIONS ) ) );
	}

	/**
	 * Upgrading from cursor 3 imports once; a later run neither calls the filters nor touches the option.
	 */
	public function test_the_runner_imports_once_at_upgrade() {
		$runner = Container::instance()->get( MigrationRunner::class );
		$this->listen( [ PreferencesService::PREF_LAYOUT_POSITION => PreferencesService::LAYOUT_POSITION_HIDE ], true );
		$runner->set_migration_id( MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN );

		$runner->run();
		$stored = get_option( PreferencesService::PREF_OPTION );

		$this->assertSame( MigrationRunner::IMPORT_SEED_FILTERS, (int) get_option( MigrationRunner::PREF_MIGRATION_ID ) );
		$this->assertSame(
			[
				PreferencesService::PREF_LAYOUT_POSITION   => PreferencesService::LAYOUT_POSITION_HIDE,
				PreferencesService::PREF_UNINSTALL_CLEANUP => '1',
			],
			$stored
		);

		$runner->run();

		$this->assertSame( $stored, get_option( PreferencesService::PREF_OPTION ) );
		$this->assertSame(
			[
				self::PREFS_FILTER     => 1,
				self::UNINSTALL_FILTER => 1,
			],
			$this->calls
		);
	}

	/**
	 * Nothing but the import may use either retired filter name.
	 */
	public function test_no_reference_to_the_retired_filters_remains() {
		$root  = \dirname( __DIR__, 2 );
		$files = [ $root . '/uninstall.php', $root . '/readme.txt', $root . '/alondra.php' ];

		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$files[] = $file->getPathname();
		}

		$allowed = $root . '/src/Infrastructure/Migration/ImportSeedFiltersMigration.php';
		foreach ( $files as $file ) {
			if ( $allowed === $file ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			$contents = (string) file_get_contents( $file );
			foreach ( [ self::PREFS_FILTER, self::UNINSTALL_FILTER ] as $filter ) {
				$this->assertStringNotContainsString( $filter, $contents, "$file still references $filter." );
			}
		}
	}
}
