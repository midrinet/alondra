<?php
/**
 * TierDatamapper Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Datamappers;

use Midrinet\Alondra\Domain\Datamapper\TierDatamapper;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Wp\UpgradeWrapper;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Unit tests covering Midrinet\Alondra\Domain\Datamapper\TierDatamapper class functionality.
 */
class TierDatamapperTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Rule datamapper
	 *
	 * @var TierDatamapper
	 */
	private $datamapper;

	/**
	 * Mock of wpdb
	 *
	 * @var mixed
	 */
	private $wpdb;

	/**
	 * Mock of upgrade wrapper
	 *
	 * @var mixed
	 */
	private $upgrade;

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		$this->wpdb         = $this->createMock( \wpdb::class );
		$this->upgrade      = $this->createMock( UpgradeWrapper::class );
		$this->wpdb->prefix = 'wptests_';
		$this->install_container( $this->make_container() );

		$this->datamapper = new TierDatamapper();
	}

	/**
	 * Build a Container stub that provides wpdb and UpgradeWrapper.
	 *
	 * @return Container
	 */
	private function make_container(): Container {
		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ \wpdb::class, $this->wpdb ],
				[ UpgradeWrapper::class, $this->upgrade ],
			]
		);
		return $container;
	}

	/**
	 * Test case for table
	 */
	public function testTable() {
		$this->assertEquals( 'wptests_alondra_tiers', $this->datamapper->table() );
	}

	/**
	 * Test case for col_id
	 */
	public function testColId() {
		$this->assertEquals( 'id', $this->datamapper->col_id() );
	}

	/**
	 * Test case for col_tiered_pricing_id
	 */
	public function testColTieredPricingId() {
		$this->assertEquals( 'tiered_pricing_id', $this->datamapper->col_tiered_pricing_id() );
	}

	/**
	 * Test case for min_units
	 */
	public function testColMinUnits() {
		$this->assertEquals( 'min_units', $this->datamapper->col_min_units() );
	}

	/**
	 * Test case for max_units
	 */
	public function testColCategory() {
		$this->assertEquals( 'max_units', $this->datamapper->col_max_units() );
	}

	/**
	 * Test case for is_fixed
	 */
	public function testColIsFixed() {
		$this->assertEquals( 'is_fixed', $this->datamapper->col_is_fixed() );
	}

	/**
	 * Test case for col_value
	 */
	public function testColValue() {
		$this->assertEquals( 'value', $this->datamapper->col_value() );
	}

	/**
	 * Test case for save
	 *
	 * @dataProvider provider_save_do_insert
	 * @param Tier     $entity Entity to save.
	 * @param int|bool $result Result of save method. Number of affected rows or false.
	 * @param int      $inserted_id Id of inserted entity.
	 * @param Tier     $return_entity Returns value for entity method.
	 */
	public function testSave_onEmptyId_doInsert( $entity, $result, $inserted_id, $return_entity ) {

		$this->wpdb->insert_id = $inserted_id;
		$this->wpdb->expects( $this->once() )
		->method( 'insert' )
		->with(
			$this->callback(
				function ( $table_name ) {
						return \is_string( $table_name ) && ! empty( $table_name );
				}
			),
			$this->callback(
				function ( $data ) {
						return \is_array( $data ) && ! empty( $data );
				}
			),
			$this->equalTo( [ '%d', '%d', '%d', '%d', '%f' ] ),
		)
		->willReturn( $result );

		$this->assertEquals( $this->datamapper->save( $entity ), $return_entity );
	}

	/**
	 * Test case for save
	 *
	 * @dataProvider provider_save_do_update
	 * @param Tier     $entity Entity to save.
	 * @param int|bool $result Result of save method. Number of affected rows or false.
	 * @param Tier     $return_entity Returns value for entity method.
	 */
	public function testSave_withId_doUpdate( $entity, $result, $return_entity ) {

		$this->wpdb->expects( $this->once() )
		->method( 'update' )
		->with(
			$this->callback(
				function ( $table_name ) {
						return \is_string( $table_name ) && ! empty( $table_name );
				}
			),
			$this->callback(
				function ( $data ) {
						return \is_array( $data ) && ! empty( $data );
				}
			),
			$this->callback(
				function ( $where ) use ( $entity ) {
						return \is_array( $where ) && count( $where ) === 1 && array_search( $entity->id, $where, true ) !== false;
				}
			),
			$this->equalTo( [ '%d', '%d', '%d', '%d', '%f' ] ),
			$this->equalTo( [ '%d' ] )
		)
		->willReturn( $result );

		$this->assertEquals( $this->datamapper->save( $entity ), $return_entity );
	}

	/**
	 * Test case for delete
	 *
	 * @dataProvider provider_delete_with_object
	 * @param Tier $entity Entity to delete.
	 * @param bool $result Result of delete method.
	 */
	public function testDelete_withObject_doDelete( $entity, $result ) {

		$this->wpdb->expects( $this->once() )
		->method( 'delete' )
		->with(
			$this->callback(
				function ( $table_name ) {
						return \is_string( $table_name ) && ! empty( $table_name );
				}
			),
			$this->callback(
				function ( $where ) use ( $entity ) {
						return \is_array( $where ) && \count( $where ) === 1 && array_search( $entity->id, $where, true ) !== false;
				}
			),
			$this->equalTo( [ '%d' ] )
		)
		->willReturn( $result );

		$this->assertEquals( $this->datamapper->delete( $entity ), $result );
	}

	/**
	 * Test case for delete
	 *
	 * @dataProvider provider_delete_with_id
	 * @param int  $entity_id Entity ID to delete.
	 * @param bool $result Result of delete method.
	 */
	public function testDelete_withId_doDelete( $entity_id, $result ) {

		$this->wpdb->expects( $this->once() )
		->method( 'delete' )
		->with(
			$this->callback(
				function ( $table_name ) {
						return \is_string( $table_name ) && ! empty( $table_name );
				}
			),
			$this->callback(
				function ( $where ) use ( $entity_id ) {
						return \is_array( $where ) && \count( $where ) === 1 && array_search( $entity_id, $where, true ) !== false;
				}
			),
			$this->equalTo( [ '%d' ] )
		)
		->willReturn( $result );

		$this->assertEquals( $this->datamapper->delete( $entity_id ), $result );
	}

	/**
	 * Test case for find
	 *
	 * @dataProvider provider_find_not_exists
	 * @param int    $id ID of the entity to find.
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param bool   $with_relationships Whether to load relationships or not.
	 * @param Tier   $result Result of find method.
	 */
	public function testFind_notExists_returnsNull( $id, $table, $col_id, $with_relationships, $result ) {

		// Method only calls others methods, so we need to mock them .
		$datamapper_mock = $this->getMockBuilder( TierDatamapper::class )
		->setMethodsExcept( [ 'find' ] )
		->getMock();

		$datamapper_mock->expects( $this->once() )
		->method( 'table' )
		->willReturn( $table );

		$datamapper_mock->expects( $this->once() )
		->method( 'col_id' )
		->willReturn( $col_id );

		$this->wpdb->expects( $this->once() )
			->method( 'prepare' )
			->with( 'SELECT * FROM %i WHERE %i = %d LIMIT 1', $table, $col_id, (int) $id )
			->willReturn( 'test_query' );

		$this->wpdb->expects( $this->once() )
			->method( 'get_results' )
			->with( 'test_query' )
			->willReturn( null );

		$datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( [] )
			->willReturn( [] );

		$this->assertEquals( $result, $datamapper_mock->find( $id, $with_relationships ) );
	}

	/**
	 * Test case for find
	 *
	 * @dataProvider provider_find_exists_without_relationships
	 * @param int    $id ID of the entity to find.
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param Tier   $result Result of find method.
	 */
	public function testFind_existsWithoutRelationships_returnsRule( $id, $table, $col_id, $result ) {

		// Method only calls others methods, so we need to mock them .
		$datamapper_mock = $this->getMockBuilder( TierDatamapper::class )
		->setMethodsExcept( [ 'find' ] )
		->getMock();

		$datamapper_mock->expects( $this->once() )
		->method( 'table' )
		->willReturn( $table );

		$datamapper_mock->expects( $this->once() )
		->method( 'col_id' )
		->willReturn( $col_id );

		$raw_rows = [ (object) [] ];

		$this->wpdb->expects( $this->once() )
			->method( 'prepare' )
			->with( 'SELECT * FROM %i WHERE %i = %d LIMIT 1', $table, $col_id, (int) $id )
			->willReturn( 'test_query' );

		$this->wpdb->expects( $this->once() )
			->method( 'get_results' )
			->with( 'test_query' )
			->willReturn( $raw_rows );

		$datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( $raw_rows )
			->willReturn( [ $result ] );

		$this->assertEquals( $result, $datamapper_mock->find( $id, false ) );
	}

	/**
	 * Test case for find
	 *
	 * @dataProvider provider_find_exists_with_relationships
	 * @param int    $id ID of the entity to find.
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param Tier   $entity Entity to find.
	 * @param Tier   $result Result of find method.
	 */
	public function testFind_existsWithRelationships_returnsTierWithRelationships( $id, $table, $col_id, $entity, $result ) {

		// Method only calls others methods, so we need to mock them.
		$datamapper_mock = $this->getMockBuilder( TierDatamapper::class )
		->setMethodsExcept( [ 'find' ] )
		->getMock();

		$datamapper_mock->expects( $this->once() )
		->method( 'table' )
		->willReturn( $table );

		$datamapper_mock->expects( $this->once() )
		->method( 'col_id' )
		->willReturn( $col_id );

		$raw_rows = [ (object) [] ];

		$this->wpdb->expects( $this->once() )
			->method( 'prepare' )
			->with( 'SELECT * FROM %i WHERE %i = %d LIMIT 1', $table, $col_id, (int) $id )
			->willReturn( 'test_query' );

		$this->wpdb->expects( $this->once() )
			->method( 'get_results' )
			->with( 'test_query' )
			->willReturn( $raw_rows );

		$datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( $raw_rows )
			->willReturn( [ $entity ] );

		$datamapper_mock->expects( $this->once() )
		->method( 'set_relationships' )
		->with( $entity )
		->willReturn( $result );

		$this->assertEquals( $result, $datamapper_mock->find( $id, true ) );
	}

	/**
	 * Test case for find_by_tiered_pricing
	 *
	 * @dataProvider provider_find_by_tiered_pricing
	 * @param int    $tiered_pricing_id Tiered pricing id.
	 * @param string $return_table Returns value for table method.
	 * @param string $return_col_tiered_pricing_id Returns value for col_tiered_pricing_id method.
	 * @param string $find_by_arg Argument for find_by method.
	 * @param Tier[] $return_find_by Returns value for find_by method.
	 */
	public function testFindByTieredPricing( $tiered_pricing_id, $return_table, $return_col_tiered_pricing_id, $find_by_arg, $return_find_by ) {
		// Method only calls others methods, so we need to mock them.
		$datamapper_mock = $this->getMockBuilder( TierDatamapper::class )
		->setMethodsExcept( [ 'find_by_tiered_pricing' ] )
		->getMock();

		$datamapper_mock->expects( $this->once() )
		->method( 'table' )
		->willReturn( $return_table );

		$datamapper_mock->expects( $this->once() )
		->method( 'col_tiered_pricing_id' )
		->willReturn( $return_col_tiered_pricing_id );

		$this->wpdb->expects( $this->once() )
			->method( 'prepare' )
			->with( 'SELECT * FROM %i WHERE %i = %d', $return_table, $return_col_tiered_pricing_id, $tiered_pricing_id )
			->willReturn( $find_by_arg );

		$this->wpdb->expects( $this->once() )
			->method( 'get_results' )
			->with( $find_by_arg )
			->willReturn( null );

		$datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( [] )
			->willReturn( $return_find_by );

		$result = $datamapper_mock->find_by_tiered_pricing( $tiered_pricing_id );
		$this->assertEquals( $return_find_by, $result );
	}

	/**
	 * Test case for set_relationships
	 *
	 * @dataProvider provider_set_relationships
	 * @param Tier $entity Entity to set relationships.
	 * @param Tier $result Result of set_relationships method.
	 */
	public function testSet_relationships( $entity, $result ) {
		$this->assertEquals( $this->datamapper->set_relationships( $entity ), $result );
	}

	/**
	 * Test case for set_up
	 *
	 * @dataProvider provider_set_up
	 * @param string $table Table name.
	 * @param string $col_id Column id.
	 * @param string $col_tiered_pricing_id Column tiered pricing id.
	 * @param string $col_min_units Column min units.
	 * @param string $col_max_units Column max units.
	 * @param string $col_is_fixed Column is fixed.
	 * @param string $col_value Column value.
	 * @param string $get_charset_collate Get charset collate.
	 * @param string $get_var Get var.
	 * @param string $result Result.
	 */
	public function testSetUp( $table, $col_id, $col_tiered_pricing_id, $col_min_units, $col_max_units, $col_is_fixed, $col_value, $get_charset_collate, $get_var, $result ) {

		// prepare wpdb mock.
		$this->wpdb->expects( $this->once() )
			->method( 'get_charset_collate' )
			->willReturn( $get_charset_collate );

		// prepare wpdb mock for get_var.
		$this->wpdb->expects( $this->once() )
			->method( 'get_var' )
			->with( "SHOW TABLES LIKE '$table'" )
			->willReturn( $get_var );

		// The table name reaches SHOW TABLES LIKE through esc_like(); pass it through unchanged.
		$this->wpdb->method( 'esc_like' )->willReturnArgument( 0 );

		$prepared_sql  = "CREATE TABLE `$table` (\n"
			. "`$col_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "`$col_tiered_pricing_id` bigint(20) unsigned NOT NULL,\n"
			. "`$col_min_units` int(10) unsigned NOT NULL,\n"
			. "`$col_max_units` int(10) unsigned NOT NULL,\n"
			. "`$col_is_fixed` tinyint(1) NOT NULL,\n"
			. "`$col_value` float NOT NULL,\n"
			. "PRIMARY KEY  (`$col_id`),\n"
			. "KEY `$col_tiered_pricing_id` (`$col_tiered_pricing_id`)\n)";
		$invoked_count = $this->exactly( 2 );
		$this->wpdb->expects( $invoked_count )
			->method( 'prepare' )
			->willReturnCallback(
				function (
					$sql,
					...$args 
				) use ( 
					$prepared_sql,
					$invoked_count,
					$table,
					$col_id,
					$col_tiered_pricing_id,
					$col_min_units,
					$col_max_units,
					$col_is_fixed,
					$col_value
				) {
					if ( 1 === $invoked_count->getInvocationCount() ) {
						$this->assertEquals(
							"CREATE TABLE %i (\n"
							. "%i bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
							. "%i bigint(20) unsigned NOT NULL,\n"
							. "%i int(10) unsigned NOT NULL,\n"
							. "%i int(10) unsigned NOT NULL,\n"
							. "%i tinyint(1) NOT NULL,\n"
							. "%i float NOT NULL,\n"
							. "PRIMARY KEY  (%i),\n"
							. "KEY %i (%i)\n)",
							$sql
						);
						$this->assertEquals( $table, $args[0] );
						$this->assertEquals( $col_id, $args[1] );
						$this->assertEquals( $col_tiered_pricing_id, $args[2] );
						$this->assertEquals( $col_min_units, $args[3] );
						$this->assertEquals( $col_max_units, $args[4] );
						$this->assertEquals( $col_is_fixed, $args[5] );
						$this->assertEquals( $col_value, $args[6] );
						$this->assertEquals( $col_id, $args[7] );
						$this->assertEquals( $col_tiered_pricing_id, $args[8] );
						$this->assertEquals( $col_tiered_pricing_id, $args[9] );
						return $prepared_sql;
					} else {
						$this->assertEquals( 'SHOW TABLES LIKE %s', $sql );
						$this->assertEquals( $table, $args[0] );
						return "SHOW TABLES LIKE '$table'";
					}
				}
			);

		// prepare upgrade mock. set_up() asks dbDelta twice once the table checks out: the
		// schema apply, then the verification dry run. It stops after the apply when SHOW
		// TABLES finds no table.
		$this->upgrade->method( 'db_delta' )
			->willReturnCallback(
				function ( $sql ) use ( $prepared_sql, $get_charset_collate ) {
					$this->assertEquals( "$prepared_sql $get_charset_collate", $sql );
					return [];
				}
			);

		// Method only calls others methods, so we need to mock them.
		$datamapper = $this->getMockBuilder( TierDatamapper::class )
		->setMethodsExcept( [ 'set_up' ] )
		->getMock();

		$datamapper->expects( $this->exactly( 3 ) )
			->method( 'table' )
			->willReturn( $table );

		$datamapper->expects( $this->exactly( 2 ) )
			->method( 'col_id' )
			->willReturn( $col_id );

		$datamapper->expects( $this->exactly( 3 ) )
			->method( 'col_tiered_pricing_id' )
			->willReturn( $col_tiered_pricing_id );

		$datamapper->expects( $this->once() )
			->method( 'col_min_units' )
			->willReturn( $col_min_units );

		$datamapper->expects( $this->once() )
			->method( 'col_max_units' )
			->willReturn( $col_max_units );

		$datamapper->expects( $this->once() )
			->method( 'col_is_fixed' )
			->willReturn( $col_is_fixed );

		$datamapper->expects( $this->once() )
			->method( 'col_is_fixed' )
			->willReturn( $col_is_fixed );

		$datamapper->expects( $this->once() )
			->method( 'col_value' )
			->willReturn( $col_value );

		$this->assertEquals( $datamapper->set_up(), $result );
	}

	/**
	 * Data provider for testFindByTieredPricing_onSuccess_returnsArray method.
	 */
	public function provider_find_by_tiered_pricing() {
		return [
			[
				1,
				'wptests_alondra_tiers1',
				'tiered_pricing_id1',
				'SELECT * FROM `wptests_alondra_tiers1` WHERE `tiered_pricing_id1` = 1',
				[ new Tier( 1, 1 ), new Tier( 2, 2 ), new Tier( 1, 3 ) ],
			],
			[
				99,
				'wptests_alondra_tiers2',
				'tiered_pricing_id2',
				'SELECT * FROM `wptests_alondra_tiers2` WHERE `tiered_pricing_id2` = 99',
				[],
			],
		];
	}

	/**
	 * Data provider for testFind_notExists_returnsNull method.
	 */
	public function provider_find_not_exists() {
		return [
			[ 1, 'wptests_alondra_tiers1', 'tiered_pricing_id1', true, null ],
			[ 99, 'wptests_alondra_tiers2', 'tiered_pricing_id2', false, null ],
		];
	}

	/**
	 * Data provider for testFind_existsWithoutRelationships_returnsNull method.
	 */
	public function provider_find_exists_without_relationships() {
		return [
			[ 1, 'wptests_alondra_tiers1', 'tiered_pricing_id1', new Tier( 1, 2 ) ],
			[ 99, 'wptests_alondra_tiers2', 'tiered_pricing_id2', new Tier( 99, 100 ) ],
		];
	}

	/**
	 * Data provider for testFind_existsWithRelationships_returnsTierWithRelationships method.
	 */
	public function provider_find_exists_with_relationships() {
		return [
			[ 1, 'wptests_alondra_tiers1', 'tiered_pricing_id1', new Tier( 1, 2 ), new Tier( 4, 5 ) ],
			[ 99, 'wptests_alondra_tiers2', 'tiered_pricing_id2', new Tier( 99, 100 ), new Tier( 3, 8 ) ],
		];
	}

	/**
	 * Data provider for testSetUp method.
	 */
	public function provider_set_up() {

		return [
			[
				'table1',
				'id',
				'col_tiered_pricing_id6',
				'min_units5',
				'max_units7',
				'is_fixed2',
				'value9',
				'get_charset_collate UTF8',
				'table1',
				true,
			],
			[
				'table1',
				'id',
				'col_tiered_pricing_id6',
				'min_units5',
				'max_units7',
				'is_fixed2',
				'value9',
				'get_charset_collate UTF8',
				'table0',
				false,
			],
			[
				'table1',
				'id',
				'col_tiered_pricing_id6',
				'min_units5',
				'max_units7',
				'is_fixed2',
				'value9',
				'get_charset_collate UTF8',
				null,
				false,
			],
		];
	}

	/**
	 * Data provider for testSave_onEmptyId_doInsert method.
	 */
	public function provider_save_do_insert() {
		return [
			[ new Tier( 0, 1 ), 1, 1, new Tier( 1, 1 ) ],
			[ new Tier(), 0, 0, new Tier() ],
			[ new Tier(), false, 0, null ],
		];
	}

	/**
	 * Data provider for testSave_withId_doUpdate method.
	 */
	public function provider_save_do_update() {
		return [
			[ new Tier( 1, 1 ), 1, new Tier( 1, 1 ) ],
			[ new Tier( 1 ), 0, new Tier( 1 ) ],
			[ new Tier( 1 ), false, null ],
		];
	}

	/**
	 * Data provider for testDelete_withObject_doDelete method.
	 */
	public function provider_delete_with_object() {
		return [
			[ new Tier( 1 ), true ],
			[ new Tier(), false ],
		];
	}

	/**
	 * Data provider for testDelete_withId_doDelete method.
	 */
	public function provider_delete_with_id() {
		return [
			[ 1, true ],
			[ 0, false ],
		];
	}

	/**
	 * Data provider for testSet_relationships method.
	 */
	public function provider_set_relationships() {
		return [
			[ new Tier( 1, 2 ), new Tier( 1, 2 ) ],
			[ new Tier( 99, 100 ), new Tier( 99, 100 ) ],
		];
	}
}
