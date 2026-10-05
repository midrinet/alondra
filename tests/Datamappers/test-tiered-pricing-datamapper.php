<?php
/**
 * TieredPricingDatamapper Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Datamappers;

use Midrinet\Alondra\Domain\Datamapper\RuleDatamapper;
use Midrinet\Alondra\Domain\Datamapper\TierDatamapper;
use Midrinet\Alondra\Domain\Datamapper\TieredPricingDatamapper;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Wp\UpgradeWrapper;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Unit tests covering Midrinet\Alondra\Domain\Datamapper\TieredPricingDatamapper class functionality.
 */
class TieredPricingDatamapperTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Rule datamapper
	 *
	 * @var TieredPricingDatamapper
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
	 * Mock of tier datamapper
	 *
	 * @var mixed
	 */
	private $tier_datamapper;

	/**
	 * Mock of rule datamapper
	 *
	 * @var mixed
	 */
	private $rule_datamapper;

	/**
	 * Setup values before each test case
	 */
	public function set_up() {
		$this->wpdb            = $this->createMock( \wpdb::class );
		$this->upgrade         = $this->createMock( UpgradeWrapper::class );
		$this->tier_datamapper = $this->createMock( TierDatamapper::class );
		$this->rule_datamapper = $this->createMock( RuleDatamapper::class );
		$this->wpdb->prefix    = 'wptests_';
		$this->install_container( $this->make_container() );

		$this->datamapper = new TieredPricingDatamapper();
	}

	/**
	 * Build a Container stub that provides all datamapper dependencies.
	 *
	 * @return Container
	 */
	private function make_container(): Container {
		$container = $this->createStub( Container::class );
		$container->method( 'get' )->willReturnMap(
			[
				[ \wpdb::class, $this->wpdb ],
				[ UpgradeWrapper::class, $this->upgrade ],
				[ TierDatamapper::class, $this->tier_datamapper ],
				[ RuleDatamapper::class, $this->rule_datamapper ],
			]
		);
		return $container;
	}

	/**
	 * Test case for table
	 */
	public function testTable() {
		$this->assertEquals( 'wptests_alondra_tiered_pricing', $this->datamapper->table() );
	}

	/**
	 * Test case for col_id
	 */
	public function testColId() {
		$this->assertEquals( 'id', $this->datamapper->col_id() );
	}

	/**
	 * Test case for col_title
	 */
	public function testColTitle() {
		$this->assertEquals( 'title', $this->datamapper->col_title() );
	}

	/**
	 * Test case for col_priority
	 */
	public function testColPriority() {
		$this->assertEquals( 'priority', $this->datamapper->col_priority() );
	}

	/**
	 * Test case for save
	 * 
	 * @dataProvider data_provider_entity
	 */
	public function testSave_onInsertError_returnsNull( $entity ) {
		$this->wpdb->expects( $this->once() )->method( 'insert' )->willReturn( false );

		$this->rule_datamapper->expects( $this->never() )->method( 'save' );
		$this->tier_datamapper->expects( $this->never() )->method( 'save' );

		$this->assertEquals( $this->datamapper->save( $entity ), null );
	}

	public function data_provider_entity() {
		return [
			[ new TieredPricing( 0, 'title00', 2, TieredPricing::STATUS_DRAFT, '0000-00-00 00:00:00', [ new Tier() ], [ new Rule() ] ) ],
			[ new TieredPricing( 0, 'title01', 3, TieredPricing::STATUS_DRAFT, '0000-00-00 00:00:00', [ new Tier() ], [ new Rule() ] ) ],
		];
	}

	/**
	 * Test case for save
	 *
	 * @dataProvider data_provider_save_do_insert
	 * @param TieredPricing $entity Entity to save.
	 * @param int|bool       $result Result of save method. Number of affected rows or false.
	 * @param int            $inserted_id Id of inserted entity.
	 * @param TieredPricing $return_entity Returns value for entity method.
	 */
	public function testSave_onEmptyId_doInsert( $entity, $result, $inserted_id, $return_entity ) {

		$this->wpdb->insert_id       = $inserted_id;
		$date                        = gmdate( 'Y-m-d H:i:s' );
		$return_entity->date_updated = $date;
		$this->wpdb->expects( $this->once() )
		->method( 'insert' )
		->with(
			'wptests_alondra_tiered_pricing',
			[
				'title'        => $entity->title,
				'priority'     => $entity->priority,
				'status'       => $entity->status,
				'date_updated' => $date,
			],
			[ '%s', '%d', '%s', '%s' ]
		)
		->willReturn( $result );

		// Setup consecutive calls for tier datamapper.
		if ( ! empty( $entity->tiers ) ) {
			$return_tiers = $return_entity->tiers;
			$tier_index   = 0;
			$this->tier_datamapper->expects( $this->exactly( count( $entity->tiers ) ) )
			->method( 'save' )
			->willReturnCallback(
				function () use ( $return_tiers, &$tier_index ) {
					return $return_tiers[ $tier_index++ ];
				} 
			);
		}

		// Setup consecutive calls for rule datamapper.
		if ( ! empty( $entity->rules ) ) {
			$return_rules = $return_entity->rules;
			$rule_index   = 0;
			$this->rule_datamapper->expects( $this->exactly( count( $entity->rules ) ) )
			->method( 'save' )
			->willReturnCallback(
				function () use ( $return_rules, &$rule_index ) {
					return $return_rules[ $rule_index++ ];
				} 
			);
		}

		$this->assertEquals( $this->datamapper->save( $entity ), $return_entity );
	}

	/**
	 * Data provider for testSave_onEmptyId_doInsert method.
	 */
	public function data_provider_save_do_insert() {
		return [
			[ new TieredPricing( 0, 'title0', 2 ), 1, 1, new TieredPricing( 1, 'title0', 2 ) ],
			[
				new TieredPricing(
					0,
					'title1',
					99,
					'draft',
					'0000-00-00 00:00:00',
					[ new Tier( 0, 50 ), new Tier( 8, 50 ) ],
					[ new Rule( 0, 50 ), new Rule( 99, 50 ), new Rule( 10, 50 ) ]
				),
				1,
				50,
				new TieredPricing(
					50,
					'title1',
					99,
					'draft',
					'0000-00-00 00:00:00',
					[ new Tier( 0, 50 ), new Tier( 8, 50 ) ],
					[ new Rule( 0, 50 ), new Rule( 99, 50 ), new Rule( 10, 50 ) ]
				),
			],
		];
	}

	/**
	 * Test case for save
	 */
	public function testSave_onUpdateError_returnsNull() {
		$date = gmdate( 'Y-m-d H:i:s' );
		$this->wpdb->expects( $this->exactly( 1 ) )
		->method( 'update' )
		->with(
			'wptests_alondra_tiered_pricing',
			[
				'title'        => 'title00',
				'priority'     => 2,
				'status'       => 'draft',
				'date_updated' => $date,
			],
			[ 'id' => 1 ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ],
		)
		->willReturn( false );

		$this->rule_datamapper->expects( $this->never() )->method( 'save' );
		$this->tier_datamapper->expects( $this->never() )->method( 'save' );

		$this->assertEquals(
			$this->datamapper->save( 
				new TieredPricing( 
					1, 
					'title00', 
					2,
					'draft',
					'0000-00-00 00:00:00',
					[ new Tier() ], 
					[ new Rule() ] 
				) 
			), 
			null 
		);
	}

	/**
	 * Test case for save
	 *
	 * An unchanged parent row still has to persist its children: update() returning
	 * 0 affected rows means the values already matched, not an error.
	 */
	public function testSave_onUpdateAffectingNoRows_returnsEntity() {
		$date = gmdate( 'Y-m-d H:i:s' );
		$this->wpdb->expects( $this->once() )
		->method( 'update' )
		->with(
			'wptests_alondra_tiered_pricing',
			[
				'title'        => 'title00',
				'priority'     => 2,
				'status'       => 'draft',
				'date_updated' => $date,
			],
			[ 'id' => 1 ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ],
		)
		->willReturn( 0 );

		$this->tier_datamapper->expects( $this->once() )->method( 'save' )->willReturnArgument( 0 );
		$this->rule_datamapper->expects( $this->once() )->method( 'save' )->willReturnArgument( 0 );

		$entity = new TieredPricing(
			1,
			'title00',
			2,
			'draft',
			'0000-00-00 00:00:00',
			[ new Tier() ],
			[ new Rule() ]
		);

		$saved = $this->datamapper->save( $entity );

		$this->assertInstanceOf( TieredPricing::class, $saved );
		$this->assertSame( $entity, $saved );
	}

	/**
	 * Test case for save
	 *
	 * A child datamapper returning null on failure must not overwrite the tier/rule
	 * instances held in the entity's collections.
	 */
	public function testSave_onChildSaveFailure_keepsEntitiesInCollections() {
		$this->wpdb->expects( $this->once() )->method( 'update' )->willReturn( 1 );

		$this->tier_datamapper->expects( $this->once() )->method( 'save' )->willReturn( null );
		$this->rule_datamapper->expects( $this->once() )->method( 'save' )->willReturn( null );

		$entity = new TieredPricing(
			1,
			'title00',
			2,
			'draft',
			'0000-00-00 00:00:00',
			[ new Tier() ],
			[ new Rule() ]
		);

		$saved = $this->datamapper->save( $entity );

		$this->assertCount( 1, $saved->tiers );
		$this->assertCount( 1, $saved->rules );
		$this->assertContainsOnlyInstancesOf( Tier::class, $saved->tiers );
		$this->assertContainsOnlyInstancesOf( Rule::class, $saved->rules );
		$this->assertNotContains( null, $saved->tiers );
		$this->assertNotContains( null, $saved->rules );
	}

	/**
	 * Test case for save
	 *
	 * @dataProvider provider_save_do_update
	 * @param TieredPricing $entity Entity to save.
	 * @param int|bool       $result Result of save method. Number of affected rows or false.
	 * @param TieredPricing $return_entity Returns value for entity method.
	 */
	public function testSave_withId_doUpdate( $entity, $result, $return_entity ) {
		$this->wpdb->expects( $this->once() )
		->method( 'update' )
		->with(
			'wptests_alondra_tiered_pricing',
			$this->callback(
				function ( $data ) use ( $entity ) {
					return $data['title'] === $entity->title
						&& $data['priority'] === $entity->priority
						&& $data['status'] === $entity->status
						&& ! empty( $data['date_updated'] );
				}
			),
			[ 'id' => $entity->id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		)
		->willReturn( $result );

		// Setup consecutive calls for tier datamapper.
		if ( ! empty( $entity->tiers ) ) {
			$this->tier_datamapper->expects( $this->exactly( count( $entity->tiers ) ) )
			->method( 'save' )
			->willReturnArgument( 0 );
		}

		// Setup consecutive calls for rule datamapper.
		if ( ! empty( $entity->rules ) ) {
			$this->rule_datamapper->expects( $this->exactly( count( $entity->rules ) ) )
			->method( 'save' )
			->willReturnArgument( 0 );
		}

		$saved = $this->datamapper->save( $entity );
		$this->assertNotEmpty( $saved->date_updated );
		$return_entity->date_updated = $saved->date_updated;
		$this->assertEquals( $saved, $return_entity );
	}

	/**
	 * Data provider for testSave_withId_doUpdate method.
	 */
	public function provider_save_do_update() {
		return [
			[ new TieredPricing( 1, 'title0', 2, 'draft' ), 1, new TieredPricing( 1, 'title0', 2, 'draft', gmdate( 'Y-m-d H:i:s' ) ) ],
			[
				new TieredPricing(
					50,
					'title1',
					99,
					'publish',
					'0000-00-00 00:00:00',
					[ new Tier( 0, 50 ), new Tier( 8, 50 ) ],
					[ new Rule( 0, 50 ), new Rule( 99, 50 ), new Rule( 10, 50 ) ]
				),
				1,
				new TieredPricing(
					50,
					'title1',
					99,
					'publish',
					gmdate( 'Y-m-d H:i:s' ),
					[ new Tier( 0, 50 ), new Tier( 8, 50 ) ],
					[ new Rule( 0, 50 ), new Rule( 99, 50 ), new Rule( 10, 50 ) ]
				),
			],
		];
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
			'wptests_alondra_tiered_pricing',
			[ 'id' => $entity->id ],
			[ '%d' ]
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

		$this->wpdb->expects( $this->once() )->method( 'delete' )
		->with(
			'wptests_alondra_tiered_pricing',
			[ 'id' => $entity_id ],
			[ '%d' ]
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
		$datamapper_mock = $this->getMockBuilder( TieredPricingDatamapper::class )
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
		$datamapper_mock = $this->getMockBuilder( TieredPricingDatamapper::class )
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
	 * @param int              $id ID of the entity to find.
	 * @param string           $table table name.
	 * @param string           $col_id col id.
	 * @param TieredPricing[] $entities Entity to find.
	 * @param TieredPricing   $result Result of find method.
	 */
	public function testFind_existsWithRelationships_returnsEntity( $id, $table, $col_id, $entities, $result ) {

		// Method only calls others methods, so we need to mock them.
		$datamapper_mock = $this->getMockBuilder( TieredPricingDatamapper::class )
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

		if ( empty( $entities ) ) {
			$this->wpdb->expects( $this->once() )
				->method( 'get_results' )
				->with( 'test_query' )
				->willReturn( null );

			$datamapper_mock->expects( $this->once() )
				->method( 'map_all' )
				->with( [] )
				->willReturn( [] );
		} else {
			$raw_rows = [ (object) [] ];

			$this->wpdb->expects( $this->once() )
				->method( 'get_results' )
				->with( 'test_query' )
				->willReturn( $raw_rows );

			$datamapper_mock->expects( $this->once() )
				->method( 'map_all' )
				->with( $raw_rows )
				->willReturn( $entities );

			$datamapper_mock->expects( $this->once() )
				->method( 'set_relationships' )
				->with( $entities[0] )
				->willReturn( $result );
		}

		$this->assertEquals( $result, $datamapper_mock->find( $id, true ) );
	}

	/**
	 * Test case for set_relationships
	 */
	public function testSetRelationships() {
		$mock = $this->getMockBuilder( TieredPricingDatamapper::class )
		->setMethodsExcept( [ 'set_relationships' ] )
		->disableOriginalConstructor()
		->getMock();

		$obj = new TieredPricing( 1, 'title0', 2 );

		$mock->expects( $this->once() )->method( 'set_tiers' )->with( $obj );

		$mock->expects( $this->once() )->method( 'set_rules' )->with( $obj );

		$this->assertEquals( $mock->set_relationships( $obj ), $obj );
	}

	public function data_provider_set_rules() {
		$entity = new TieredPricing( 2, 'title0', 1, TieredPricing::STATUS_DRAFT, gmdate( 'Y-m-d H:i:s' ) );
		return [
			[ $entity, [ 'c', 3, false ], [] ],
			[ $entity, [ 'a', 'b', null ], [ new Rule( 1 ), new Rule( 2 ) ] ],
			[ $entity, [ 1, 3 ], [ new Rule( 2, 3 ), new Rule( 4, 5 ) ] ],
		];
	}

	/**
	 * Test case for set_rules
	 * 
	 * @dataProvider data_provider_set_rules
	 */
	public function testSetRules( $entity, $match_any, $rules ) {
		$mock = $this->getMockBuilder( TieredPricingDatamapper::class )
		->setMethodsExcept( [ 'set_rules' ] )
		->getMock();

		// set up the mock for the rule datamapper.
		$this->rule_datamapper->expects( $this->exactly( 1 ) )
		->method( 'find_by_tiered_pricing' )
		->with( $entity->id, $match_any )
		->willReturn( $rules );

		$this->assertEquals( $mock->set_rules( $entity, $match_any )->rules, $rules );
	}

	public function data_provider_set_tiers() {
		$entity = new TieredPricing( 2, 'title0', 1, TieredPricing::STATUS_DRAFT, gmdate( 'Y-m-d H:i:s' ) );
		return [
			[ $entity, [] ],
			[ $entity, [ new Tier( 1 ), new Tier( 2 ) ] ],
			[ $entity, [ new Tier( 2, 3 ), new Tier( 4, 5 ) ] ],
		];
	}

	/**
	 * Test case for set_tiers
	 * 
	 * @dataProvider data_provider_set_tiers
	 */
	public function testSetTiers( $entity, $tiers ) {
		$mock = $this->getMockBuilder( TieredPricingDatamapper::class )
		->setMethodsExcept( [ 'set_tiers' ] )
		->getMock();

		// st up the mock for the rule datamapper.
		$this->tier_datamapper->expects( $this->exactly( 1 ) )
		->method( 'find_by_tiered_pricing' )
		->with( $entity->id )
		->willReturn( $tiers );

		$this->assertEquals( $mock->set_tiers( $entity )->tiers, $tiers );
	}

	/**
	 * Test case for set_up
	 *
	 * @dataProvider provider_set_up
	 * @param string $table Table name.
	 * @param string $col_id Column id.
	 * @param string $col_title Column title.
	 * @param string $col_priority Column priority.
	 * @param string $col_date_updated Column date updated.
	 * @param string $col_status Column status.
	 * @param string $get_charset_collate Return value for get_charset_collate.
	 * @param string $get_var Return value for get_var.
	 * @param string $result Expected result.
	 */
	public function testSetUp( $table, $col_id, $col_title, $col_priority, $col_date_updated, $col_status, $get_charset_collate, $get_var, $result ) {

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
			. "`$col_title` varchar(255) NOT NULL,\n"
			. "`$col_priority` int(10) unsigned NOT NULL,\n"
			. "`{$col_date_updated}` datetime NOT NULL,\n"
			. "`{$col_status}` varchar(20) NOT NULL,\n"
			. "PRIMARY KEY  (`$col_id`)\n)";
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
					$col_title,
					$col_priority,
					$col_date_updated,
					$col_status
				) {
					if ( 1 === $invoked_count->getInvocationCount() ) {
						$this->assertEquals(
							"CREATE TABLE %i (\n"
							. "%i bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
							. "%i varchar(255) NOT NULL,\n"
							. "%i int(10) unsigned NOT NULL,\n"
							. "%i datetime NOT NULL,\n"
							. "%i varchar(20) NOT NULL,\n"
							. "PRIMARY KEY  (%i)\n)",
							$sql
						);
						$this->assertEquals( $table, $args[0] );
						$this->assertEquals( $col_id, $args[1] );
						$this->assertEquals( $col_title, $args[2] );
						$this->assertEquals( $col_priority, $args[3] );
						$this->assertEquals( $col_date_updated, $args[4] );
						$this->assertEquals( $col_status, $args[5] );
						$this->assertEquals( $col_id, $args[6] );
						return $prepared_sql;
					} else {
						$this->assertEquals( 'SHOW TABLES LIKE %s', $sql );
						$this->assertEquals( $table, $args[0] );
						return "SHOW TABLES LIKE '$table'";
					}
					global $wpdb;
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					return $wpdb->prepare( $sql, $args );
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
		$datamapper = $this->getMockBuilder( TieredPricingDatamapper::class )
		->setMethodsExcept( [ 'set_up' ] )
		->onlyMethods( [ 'get_table' ] )
		->getMock();

		$datamapper->expects( $this->exactly( 3 ) )
			->method( 'table' )
			->willReturn( $table );

		$datamapper->expects( $this->exactly( 2 ) )
			->method( 'col_id' )
			->willReturn( $col_id );

		$datamapper->expects( $this->exactly( 1 ) )
			->method( 'col_title' )
			->willReturn( $col_title );

		$datamapper->expects( $this->exactly( 1 ) )
			->method( 'col_priority' )
			->willReturn( $col_priority );

		$datamapper->expects( $this->exactly( 1 ) )
			->method( 'col_date_updated' )
			->willReturn( $col_date_updated );

		$datamapper->expects( $this->exactly( 1 ) )
			->method( 'col_status' )
			->willReturn( $col_status );

		$this->assertEquals( $datamapper->set_up(), $result );
	}

	// test case for all_matching method.

	/**
	 * Test case for all_matching method.
	 *
	 * @dataProvider provider_all_matching
	 * @param bool   $match_any Match any.
	 * @param string|string[] $order_by Order by column(s).
	 * @param string $order Order.
	 * @param int    $limit Limit.
	 * @param int    $offset Offset.
	 * @param bool   $with_relationships With relationships.
	 * @param string $table Table name.
	 * @param string $col_id Column id.
	 * @param string $query Query.
	 * @param string $get_results Return value for get_results.
	 * @param array  $result Expected result.
	 * @param string $expects_table Expected table name.
	 * @param string $expects_col_id Expected column id.
	 * @param string $expects_rule_datamapper_table Expected rule datamapper table name.
	 * @param string $rule_datamapper_table Return value for rule datamapper table.
	 * @param string $expects_rule_datamapper_col_tiered_pricing_id Expected rule datamapper column tiered pricing id.
	 * @param string $rule_datamapper_col_tiered_pricing_id Return value for rule datamapper column tiered pricing id.
	 * @param string $where_serialized Expected where serialized.
	 */
	public function testAllMatching_onSuccess_returnsArray(
		$match_any,
		$status,
		$order_by,
		$order,
		$limit,
		$offset,
		$with_relationships,
		$table,
		$col_id,
		$query,
		$full_query,
		$get_results,
		$result,
		$expects_table,
		$expects_col_id,
		$expects_rule_datamapper_table,
		$rule_datamapper_table,
		$expects_rule_datamapper_col_tiered_pricing_id,
		$rule_datamapper_col_tiered_pricing_id,
		$where_serialized
	) {

		// Prepare wpdb mock.
		$this->wpdb->expects( $this->once() )
		->method( 'get_results' )
		->with( $full_query )
		->willReturn( $get_results );

		$expected_columns = empty( $order_by ) ? [] : (array) $order_by;

		$times  = ! empty( $limit ) ? 1 : 0;
		$times += ! empty( $offset ) && 1 === $times ? 1 : 0;
		$times += \count( $expected_columns );

		$this->wpdb->expects( $this->exactly( $times ) )
			->method( 'prepare' )
			->willReturnCallback(
				// Dispatching on $sql rather than the invocation count is what lets an
				// order-by list assert one %i.%i per column, in the requested order.
				function (
					$sql,
					...$args
				) use (
					&$expected_columns,
					$table,
					$offset,
					$limit
				) {
					if ( 'LIMIT %d' === $sql ) {
						$this->assertEquals( (int) $limit, $args[0] );
					} elseif ( ' OFFSET %d' === $sql ) {
						$this->assertEquals( (int) $offset, $args[0] );
					} elseif ( '%i.%i' === $sql ) {
						$this->assertEquals( $table, $args[0] );
						$this->assertEquals( (string) array_shift( $expected_columns ), $args[1] );
					}
					global $wpdb;
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					return $wpdb->prepare( $sql, $args );
				}
			);

		// Prepare rule datamapper mock.
		$this->rule_datamapper->method( 'table' )
		->willReturn( $rule_datamapper_table );

		$this->rule_datamapper->method( 'col_tiered_pricing_id' )
		->willReturn( $rule_datamapper_col_tiered_pricing_id );

		// Method only calls others methods, so we need to mock them.
		$datamapper = $this->getMockBuilder( TieredPricingDatamapper::class )
		->onlyMethods( [ 'get_where_for_serialized', 'get_all_matching_query', 'col_status', 'table', 'col_id', 'map_all' ] )
		->getMock();

		$datamapper->method( 'col_status' )
		->willReturn( 'status' );

		$datamapper->method( 'get_all_matching_query' )
		->willReturn( $query );

		$datamapper->method( 'table' )
		->willReturn( $table );

		$datamapper->method( 'col_id' )
		->willReturn( $col_id );

		// $datamapper->expects( $this->exactly( 1 ) )
		// ->method( 'get_where_for_serialized' )
		// ->with( $match_any )
		// ->willReturn( $where_serialized );

		$datamapper->expects( $this->exactly( 1 ) )
		->method( 'map_all' )
		->with( $get_results, $with_relationships )
		->willReturn( $result );

		$this->assertEquals(
			$datamapper->all_matching( $match_any, $status, $order_by, $order, $limit, $offset, $with_relationships ),
			$result
		);
	}

	/**
	 * Data provider for testAllMatching_onSuccess_returnsArray method.
	 */
	public function provider_all_matching() {
		return [
			[
				[], // $match_any.
				null, // status.
				null, // $order_by.
				null, // $order.
				null, // $limit.
				null, // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` WHERE `table0`.`status` IN ('publish','draft')", // $query.
				"SELECT `table0`.* FROM `table0` WHERE `table0`.`status` IN ('publish','draft')  ", // $full_query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				2, // $expects_table.
				0, // $expects_col_id.
				0, // $expects_rule_datamapper_table.
				'', // $rule_datamapper_table.
				0, // $expects_rule_datamapper_col_tiered_pricing_id.
				'', // $rule_datamapper_col_tiered_pricing_id.
				'', // $where_serialized.
			],
			[
				[], // $match_any.
				'draft', // status.
				null, // $order_by.
				null, // $order.
				null, // $limit.
				null, // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` WHERE `table0`.`status` IN ('draft')", // $query.
				"SELECT `table0`.* FROM `table0` WHERE `table0`.`status` IN ('draft')  ", // $full_query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				2, // $expects_table.
				0, // $expects_col_id.
				0, // $expects_rule_datamapper_table.
				'', // $rule_datamapper_table.
				0, // $expects_rule_datamapper_col_tiered_pricing_id.
				'', // $rule_datamapper_col_tiered_pricing_id.
				'', // $where_serialized.
			],
			[
				[ 'a', 'b', 'c' ], // $match_any.
				null, // status.
				null, // $order_by.
				null, // $order.
				null, // $limit.
				null, // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0`", // $query.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0`  ", // $query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				4, // $expects_table.
				2, // $expects_col_id.
				2, // $expects_rule_datamapper_table.
				'rule_datamapper_table0', // $rule_datamapper_table.
				1, // $expects_rule_datamapper_col_tiered_pricing_id.
				'rule_datamapper_col_tiered_pricing_id1', // $rule_datamapper_col_tiered_pricing_id.
				'AND where_serialized0', // $where_serialized.
			],
			[
				[ 'b', 1 ], // $match_any.
				null, // status.
				'order_by45', // $order_by.
				'DESC', // $order.
				null, // $limit.
				null, // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') where_serialized0 GROUP BY `table0`.`id0`", // $query.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') where_serialized0 GROUP BY `table0`.`id0` ORDER BY `table0`.`order_by45` DESC ", // $query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				5, // $expects_table.
				2, // $expects_col_id.
				2, // $expects_rule_datamapper_table.
				'rule_datamapper_table0', // $rule_datamapper_table.
				1, // $expects_rule_datamapper_col_tiered_pricing_id.
				'rule_datamapper_col_tiered_pricing_id1', // $rule_datamapper_col_tiered_pricing_id.
				'where_serialized0', // $where_serialized.
			],
			[
				[ 'b', 1 ], // $match_any.
				null, // status.
				'order_by45', // $order_by.
				'DESC', // $order.
				null, // $limit.
				'offset00', // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') where_serialized0 GROUP BY `table0`.`id0`", // $query.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') where_serialized0 GROUP BY `table0`.`id0` ORDER BY `table0`.`order_by45` DESC ", // $query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				5, // $expects_table.
				2, // $expects_col_id.
				2, // $expects_rule_datamapper_table.
				'rule_datamapper_table0', // $rule_datamapper_table.
				1, // $expects_rule_datamapper_col_tiered_pricing_id.
				'rule_datamapper_col_tiered_pricing_id1', // $rule_datamapper_col_tiered_pricing_id.
				'where_serialized0', // $where_serialized.
			],
			[
				[ 'b', 1 ], // $match_any.
				null, // status.
				'order_by45', // $order_by.
				'DESC', // $order.
				'6', // $limit.
				null, // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0`", // $query.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0` ORDER BY `table0`.`order_by45` DESC LIMIT 6", // $query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				5, // $expects_table.
				2, // $expects_col_id.
				2, // $expects_rule_datamapper_table.
				'rule_datamapper_table0', // $rule_datamapper_table.
				1, // $expects_rule_datamapper_col_tiered_pricing_id.
				'rule_datamapper_col_tiered_pricing_id1', // $rule_datamapper_col_tiered_pricing_id.
				'AND where_serialized0', // $where_serialized.
			],
			[
				[ 'b', 1 ], // $match_any.
				null, // status.
				'order_by45', // $order_by.
				'DESC', // $order.
				'10', // $limit.
				'5', // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0`", // $query.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0` ORDER BY `table0`.`order_by45` DESC LIMIT 10 OFFSET 5", // $query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				5, // $expects_table.
				2, // $expects_col_id.
				2, // $expects_rule_datamapper_table.
				'rule_datamapper_table0', // $rule_datamapper_table.
				1, // $expects_rule_datamapper_col_tiered_pricing_id.
				'rule_datamapper_col_tiered_pricing_id1', // $rule_datamapper_col_tiered_pricing_id.
				'AND where_serialized0', // $where_serialized.
			],
			[
				[ 'b', 1 ], // $match_any.
				null, // status.
				'order_by45', // $order_by.
				'DESC', // $order.
				'10', // $limit.
				'5', // $offset.
				true, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0`", // $query.
				"SELECT `table0`.* FROM `table0` LEFT JOIN `rule_datamapper_table0` ON `rule_datamapper_table0`.`rule_datamapper_col_tiered_pricing_id1` = `table0`.`id0` WHERE `table0`.`status` IN ('publish','draft') AND where_serialized0 GROUP BY `table0`.`id0` ORDER BY `table0`.`order_by45` DESC LIMIT 10 OFFSET 5", // $query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				5, // $expects_table.
				2, // $expects_col_id.
				2, // $expects_rule_datamapper_table.
				'rule_datamapper_table0', // $rule_datamapper_table.
				1, // $expects_rule_datamapper_col_tiered_pricing_id.
				'rule_datamapper_col_tiered_pricing_id1', // $rule_datamapper_col_tiered_pricing_id.
				'AND where_serialized0', // $where_serialized.
			],
			[
				[], // $match_any.
				null, // status.
				[ 'title0', 'id0' ], // $order_by.
				'ASC', // $order.
				null, // $limit.
				null, // $offset.
				false, // $with_relationships.
				'table0', // $table.
				'id0', // $col_id.
				"SELECT `table0`.* FROM `table0` WHERE `table0`.`status` IN ('publish','draft')", // $query.
				"SELECT `table0`.* FROM `table0` WHERE `table0`.`status` IN ('publish','draft') ORDER BY `table0`.`title0` ASC, `table0`.`id0` ASC ", // $full_query.
				[ 1, 2, 3 ], // $get_results.
				[ new TieredPricing( 1 ), new TieredPricing( 99 ) ], // $result.
				3, // $expects_table.
				0, // $expects_col_id.
				0, // $expects_rule_datamapper_table.
				'', // $rule_datamapper_table.
				0, // $expects_rule_datamapper_col_tiered_pricing_id.
				'', // $rule_datamapper_col_tiered_pricing_id.
				'', // $where_serialized.
			],
		];
	}

	/**
	 * Data provider for testFindByTieredPricing_onSuccess_returnsArray method.
	 */
	public function provider_find_by_tiered_pricing() {
		return [
			[
				1,
				'wptests_alondra_tiered_pricing1',
				'tiered_pricing_id1',
				'SELECT * FROM `wptests_alondra_tiered_pricing1` WHERE `tiered_pricing_id1` = 1',
				[ new Tier( 1, 1 ), new Tier( 2, 2 ), new Tier( 1, 3 ) ],
			],
			[
				99,
				'wptests_alondra_tiered_pricing2',
				'tiered_pricing_id2',
				'SELECT * FROM `wptests_alondra_tiered_pricing2` WHERE `tiered_pricing_id2` = 99',
				[],
			],
		];
	}

	/**
	 * Data provider for testFind_notExists_returnsNull method.
	 */
	public function provider_find_not_exists() {
		return [
			[ 1, 'wptests_alondra_tiered_pricing1', 'tiered_pricing_id1', true, null ],
			[ 99, 'wptests_alondra_tiered_pricing2', 'tiered_pricing_id2', false, null ],
		];
	}

	/**
	 * Data provider for testFind_existsWithoutRelationships_returnsNull method.
	 */
	public function provider_find_exists_without_relationships() {
		return [
			[ 1, 'wptests_alondra_tiered_pricing1', 'tiered_pricing_id1', new Rule( 1, 2 ) ],
			[ 99, 'wptests_alondra_tiered_pricing2', 'tiered_pricing_id2', new Rule( 99, 100 ) ],
		];
	}

	/**
	 * Data provider for testFind_existsWithRelationships_returnsRuleWithRelationships method.
	 */
	public function provider_find_exists_with_relationships() {
		return [
			[ 1, 'wptests_alondra_tiered_pricing1', 'id1', null, null ],
			[ 50, 'wptests_alondra_tiered_pricing2', 'id2', [ new TieredPricing( 50 ) ], new TieredPricing( 50 ) ],
		];
	}

	/**
	 * Data provider for testSetUp method.
	 */
	public function provider_set_up() {
		return [
			[
				'table1',
				'col_id1',
				'col_title6',
				'col_priority5',
				'col_date_updated4',
				'col_status3',
				'get_charset_collate UTF8',
				'table1',
				true,
			],
			[
				'table1',
				'col_id1',
				'col_title6',
				'col_priority5',
				'col_date_updated1',
				'col_status0',
				'get_charset_collate UTF8',
				'table0',
				false,
			],
			[
				'table1',
				'col_id1',
				'col_title6',
				'col_priority5',
				'col_date_updated99',
				'col_status7',
				'get_charset_collate UTF8',
				null,
				false,
			],
		];
	}

	/**
	 * Data provider for testDelete_withObject_doDelete method.
	 */
	public function provider_delete_with_object() {
		return [
			[ new TieredPricing( 1 ), true ],
			[ new TieredPricing(), false ],
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
