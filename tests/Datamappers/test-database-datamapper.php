<?php
/**
 * DatabaseDatamapper Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Datamappers;

use Midrinet\Alondra\Domain\Datamapper\DatabaseDatamapper;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Wp\UpgradeWrapper;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Unit tests covering Midrinet\Alondra\Domain\Datamapper\DatabaseDatamapper class functionality.
 */
class DatabaseDatamapperTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Rule datamapper
	 *
	 * @var mixed
	 */
	private $database_datamapper;

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

		$this->database_datamapper = $this->getMockBuilder( DatabaseDatamapper::class )
		->setMethodsExcept( [ 'map', 'count', 'all', 'find_by' ] )
		->onlyMethods( [ 'get_where_for_serialized' ] )
		->getMock();
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
	 * Test case for map
	 *
	 * @dataProvider dataProviderForTestMap_emptyData_returnsNull
	 * @param mixed $empty_data Empty data.
	 */
	public function testMap_emptyData_returnsNull( $empty_data ) {
		$this->assertNull( $this->database_datamapper->map( $empty_data ) );
	}

	/**
	 * Test case for map
	 *
	 * @dataProvider dataProviderForTestMap_arrayDataWithoutRelationships_returnsEntity
	 * @param array $data Data.
	 * @param mixed $entity Entity.
	 */
	public function testMap_arrayDataWithoutRelationships_returnsEntity( $data, $entity ) {
		$this->database_datamapper->expects( $this->once() )
			->method( 'map_array' )
			->with( $data, false )
			->willReturn( $entity );

		$this->assertEquals( $this->database_datamapper->map( $data ), $entity );
	}

	/**
	 * Test case for map
	 *
	 * @dataProvider dataProviderForTestMap_arrayDataWithRelationships_returnsEntityWithRelationships
	 * @param array  $data Data.
	 * @param object $entity Entity.
	 * @param object $entity_with_relationships Entity with relationships.
	 */
	public function testMap_arrayDataWithRelationships_returnsEntityWithRelationships( $data, $entity, $entity_with_relationships ) {
		$this->database_datamapper->expects( $this->once() )
			->method( 'map_array' )
			->with( $data, true )
			->willReturn( $entity );

		$this->database_datamapper->expects( $this->once() )
			->method( 'set_relationships' )
			->with( $entity )
			->willReturn( $entity_with_relationships );

		$this->assertEquals( $this->database_datamapper->map( $data, true ), $entity_with_relationships );
	}

	/**
	 * Test case for map
	 *
	 * @dataProvider dataProviderForTestMap_objectDataWithoutRelationships_returnsEntity
	 * @param array $data Data.
	 * @param mixed $entity Entity.
	 */
	public function testMap_objectDataWithoutRelationships_returnsEntity( $data, $entity ) {
		$this->database_datamapper->expects( $this->once() )
		->method( 'map_object' )
		->with( $data, false )
		->willReturn( $entity );

		$this->assertEquals( $this->database_datamapper->map( $data ), $entity );
	}

	/**
	 * Test case for map
	 *
	 * @dataProvider dataProviderForTestMap_objectDataWithRelationships_returnsEntityWithRelationships
	 * @param array  $data Data.
	 * @param object $entity Entity.
	 * @param object $entity_with_relationships Entity with relationships.
	 */
	public function testMap_objectDataWithRelationships_returnsEntityWithRelationships( $data, $entity, $entity_with_relationships ) {
		$this->database_datamapper->expects( $this->once() )
		->method( 'map_object' )
		->with( $data, true )
		->willReturn( $entity );

		$this->database_datamapper->expects( $this->once() )
		->method( 'set_relationships' )
		->with( $entity )
		->willReturn( $entity_with_relationships );

		$this->assertEquals( $this->database_datamapper->map( $data, true ), $entity_with_relationships );
	}

	/**
	 * Test case for count
	 *
	 * @dataProvider dataProviderForTestCount
	 * @param string       $table_name Table name.
	 * @param string|false $get_var Result of get_var.
	 * @param int          $result Result.
	 * @param string       $sub_query Sub query.
	 */
	public function testCount( $table_name, $get_var, $result, $sub_query ) {

		// prepare get_table mock.
		$this->database_datamapper->expects( $this->exactly( null === $sub_query ? 1 : 0 ) )
			->method( 'table' )
			->willReturn( $table_name );

		// prepare wpdb mock.
		$prepared_sql = "SELECT COUNT(*) FROM $table_name";
		$this->wpdb->expects( $this->exactly( null === $sub_query ? 1 : 0 ) )
			->method( 'prepare' )
			->with( '%i', $table_name )
			->willReturn( $table_name );

		$this->wpdb->expects( $this->once() )
			->method( 'get_var' )
			->with( $prepared_sql )
			->willReturn( $get_var );

		$this->assertEquals( $this->database_datamapper->count( $sub_query ), $result );
	}

	/**
	 * Test case for all
	 *
	 * @dataProvider dataProviderForTestAll
	 * @param string        $table_name Table name.
	 * @param object[]|null $with_relationships Results.
	 * @param bool          $with_relationships With relationships.
	 * @param array         $result Result.
	 */
	public function testAll( $table_name, $get_results, $with_relationships, $result ) {
		// prepare get_table mock.
		$this->database_datamapper->expects( $this->once() )
			->method( 'table' )
			->willReturn( $table_name );

		// prepare wpdb mock. The table name is bound, not interpolated.
		$prepared_sql = "SELECT * FROM `$table_name`";
		$this->wpdb->expects( $this->once() )
			->method( 'prepare' )
			->with( 'SELECT * FROM %i', $table_name )
			->willReturn( $prepared_sql );

		$this->wpdb->expects( $this->once() )
			->method( 'get_results' )
			->with( $prepared_sql, OBJECT )
			->willReturn( $get_results );

		// prepare map mock.
		$this->database_datamapper->expects( $this->once() )
			->method( 'map_all' )
			->with( $get_results ?? [], $with_relationships )
			->willReturn( $result );

		$this->assertEquals( $this->database_datamapper->all( $with_relationships ), $result );
	}

	/**
	 * Data provider for testFind_by
	 *
	 * @return array
	 */
	public function dataProviderForTestFindBy() {
		return [
			[ 'query001', false, null, [] ],
			[ 'query example', true, null, [] ],
			[ 'another query', true, [ 1, 2, '3' ], [ (object) [ 'example' => 'test' ] ] ],
		];
	}

	/**
	 * Data provider for testAll
	 *
	 * @return array
	 */
	public function dataProviderForTestAll() {
		return [
			[ 'table086', null, false, [] ],
			[ '45table789', null, true, [] ],
			[
				'984table',
				[ (object) [ 'attr' => 0 ] ],
				false,
				[
					(object) [
						'attr01' => 0.5,
						'other'  => 'asd',
					],
				],
			],
			[ 'table1', [ (object) [ 'attr' => 1 ] ], true, [ (object) [ 'attr03' => false ] ] ],
		];
	}

	/**
	 * Data provider for testCount
	 *
	 * @return array
	 */
	public function dataProviderForTestCount() {
		return [
			[ '`test`', '99', 99, null ],
			[ '`test2`', null, 0, null ],
			[ '`test2`', '0', 0, null ],
			[ '(DUMMY_SUB_QUERY) AS `sub_query`', '0', 0, 'DUMMY_SUB_QUERY' ],
		];
	}

	/**
	 * Data provider for testMap_arrayDataWithRelationships_returnsEntityWithRelationships
	 *
	 * @return array
	 */
	public function dataProviderForTestMap_objectDataWithRelationships_returnsEntityWithRelationships() {
		return [
			[
				(object) [ 1 ],
				(object) [
					'id'   => 99,
					'name' => 'test0',
				],
				(object) [
					'id'   => 82,
					'name' => 'test1',
					'attr' => 'test2',
				],
			],
			[
				(object) [ 'x', 'y' ],
				(object) [
					'name' => 'test45',
				],
				(object) [
					'id' => 82,
				],
			],
		];
	}

	/**
	 * Data provider for testMap_arrayDataWithoutRelationships_returnsEntity
	 *
	 * @return array
	 */
	public function dataProviderForTestMap_objectDataWithoutRelationships_returnsEntity() {
		return [
			[
				(object) [ 1 ],
				(object) [
					'id'   => 99,
					'name' => 'test0',
				],
			],
			[ (object) [ 'a', 'b', 'c' ], (object) [ 'attr' => 'test1' ] ],
		];
	}

	/**
	 * Data provider for testMap_emptyData_returnsNull
	 *
	 * @return array
	 */
	public function dataProviderForTestMap_emptyData_returnsNull() {
		return [
			[ null ],
			[ '' ],
			[ [] ],
			[ 0 ],
			[ false ],
		];
	}

	/**
	 * Data provider for testMap_arrayDataWithRelationships_returnsEntityWithRelationships
	 *
	 * @return array
	 */
	public function dataProviderForTestMap_arrayDataWithRelationships_returnsEntityWithRelationships() {
		return [
			[
				[ 1 ],
				(object) [
					'id'   => 99,
					'name' => 'test0',
				],
				(object) [
					'id'   => 82,
					'name' => 'test1',
					'attr' => 'test2',
				],
			],
			[
				[ 'x', 'y' ],
				(object) [
					'name' => 'test45',
				],
				(object) [
					'id' => 82,
				],
			],
		];
	}

	/**
	 * Data provider for testMap_arrayDataWithoutRelationships_returnsEntity
	 *
	 * @return array
	 */
	public function dataProviderForTestMap_arrayDataWithoutRelationships_returnsEntity() {
		return [
			[
				[
					[ 1 ],
					(object) [
						'id'   => 99,
						'name' => 'test0',
					],
				],
				[ [ 'a', 'b', 'c' ], (object) [ 'attr' => 'test1' ] ],
			],
		];
	}
}
