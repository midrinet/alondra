<?php
/**
 * RuleDatamapper Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Datamappers;

use Midrinet\Alondra\Domain\Datamapper\RuleDatamapper;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Wp\UpgradeWrapper;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * Unit tests covering Midrinet\Alondra\Domain\Datamapper\RuleDatamapper class functionality.
 */
class RuleDatamapperTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Rule datamapper
	 *
	 * @var RuleDatamapper
	 */
	private $rule_datamapper;

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

		$this->rule_datamapper = new RuleDatamapper();
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
		$this->assertEquals( 'wptests_alondra_rules', $this->rule_datamapper->table() );
	}

	/**
	 * Test case for col_id
	 */
	public function testColId() {
		$this->assertEquals( 'id', $this->rule_datamapper->col_id() );
	}

	/**
	 * Test case for col_tiered_pricing_id
	 */
	public function testColTieredPricingId() {
		$this->assertEquals( 'tiered_pricing_id', $this->rule_datamapper->col_tiered_pricing_id() );
	}

	/**
	 * Test case for col_tag
	 */
	public function testColTag() {
		$this->assertEquals( 'tag_id', $this->rule_datamapper->col_tag() );
	}

	/**
	 * Test case for col_category
	 */
	public function testColCategory() {
		$this->assertEquals( 'cat_id', $this->rule_datamapper->col_category() );
	}

	/**
	 * Test case for col_product
	 */
	public function testColProduct() {
		$this->assertEquals( 'product_id', $this->rule_datamapper->col_product() );
	}

	/**
	 * Test case for col_user
	 */
	public function testColUser() {
		$this->assertEquals( 'user_id', $this->rule_datamapper->col_user() );
	}

	/**
	 * Test case for col_role
	 */
	public function testColRole() {
		$this->assertEquals( 'role', $this->rule_datamapper->col_role() );
	}

	/**
	 * Test case for col_bundle_product
	 */
	public function testColBundleProduct() {
		$this->assertEquals( 'bundle_product', $this->rule_datamapper->col_bundle_product() );
	}

	/**
	 * Test case for col_roles_rel
	 */
	public function testColRolesRel() {
		$this->assertEquals( 'roles_rel', $this->rule_datamapper->col_roles_rel() );
	}

	/**
	 * Test case for col_cats_rel
	 */
	public function testColCatsRel() {
		$this->assertEquals( 'cats_rel', $this->rule_datamapper->col_cats_rel() );
	}

	/**
	 * Test case for col_tags_rel
	 */
	public function testColTagsRel() {
		$this->assertEquals( 'tags_rel', $this->rule_datamapper->col_tags_rel() );
	}

	/**
	 * Test case for col_tags_with_cats_rel
	 */
	public function testColTagsWithCatsRel() {
		$this->assertEquals( 'tags_with_cats_rel', $this->rule_datamapper->col_tags_with_cats_rel() );
	}

	/**
	 * Test case for col_prods_cats_tags_with_roles_users_rel
	 */
	public function testColProdsCatsTagsWithRolesUsersRel() {
		$this->assertEquals( 'prods_cats_tags_with_roles_users_rel', $this->rule_datamapper->col_prods_cats_tags_with_roles_users_rel() );
	}

	/**
	 * Test case for find_by_tiered_pricing
	 *
	 * @dataProvider provider_find_by_tiered_pricing
	 * @param int    $tiered_pricing_id Tiered pricing id.
	 * @param array  $match_any Match any parameter.
	 * @param string $return_get_where_for_serialized Returns value for get_where_for_serialized method.
	 * @param string $return_table Returns value for table method.
	 * @param string $return_col_tiered_pricing_id Returns value for col_tiered_pricing_id method.
	 * @param string $find_by_arg Argument for find_by method.
	 * @param Rule[] $return_find_by Returns value for find_by method.
	 */
	public function testFindByTieredPricing( $tiered_pricing_id, $match_any, $return_get_where_for_serialized, $return_table, $return_col_tiered_pricing_id, $find_by_arg, $return_find_by ) {
		// Method only calls others methods, so we need to mock them.
		$rule_datamapper_mock = $this->getMockBuilder( RuleDatamapper::class )
		->setMethodsExcept( [ 'find_by_tiered_pricing' ] )
		->onlyMethods( [ 'get_where_for_serialized' ] )
		->getMock();

		// set mock expectations.
		$rule_datamapper_mock->expects( $this->once() )
			->method( 'get_where_for_serialized' )
			->with( $match_any )
			->willReturn( $return_get_where_for_serialized );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'table' )
			->willReturn( $return_table );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_tiered_pricing_id' )
			->willReturn( $return_col_tiered_pricing_id );

		$partial_query = empty( $return_get_where_for_serialized )
			? $find_by_arg
			: substr( $find_by_arg, 0, strlen( $find_by_arg ) - strlen( ' ' . $return_get_where_for_serialized ) );

		$this->wpdb->expects( $this->once() )
			->method( 'prepare' )
			->with( 'SELECT * FROM %i WHERE %i = %d', $return_table, $return_col_tiered_pricing_id, $tiered_pricing_id )
			->willReturn( $partial_query );

		$this->wpdb->expects( $this->once() )
			->method( 'get_results' )
			->with( $find_by_arg )
			->willReturn( null );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( [] )
			->willReturn( $return_find_by );

		$result = $rule_datamapper_mock->find_by_tiered_pricing( $tiered_pricing_id, $match_any );
		$this->assertEquals( $return_find_by, $result );
	}

	/**
	 * Test case for save
	 *
	 * @dataProvider provider_save_do_insert
	 * @param Rule     $entity Entity to save.
	 * @param int|bool $result Result of save method. Number of affected rows or false.
	 * @param int      $inserted_id Id of inserted entity.
	 * @param Rule     $return_entity Returns value for entity method.
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
				$this->equalTo( [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] ),
			)
			->willReturn( $result );

		$this->assertEquals( $this->rule_datamapper->save( $entity ), $return_entity );
	}

	/**
	 * Test case for save
	 *
	 * @dataProvider provider_save_do_update
	 * @param Rule     $entity Entity to save.
	 * @param int|bool $result Result of save method. Number of affected rows or false.
	 * @param Rule     $return_entity Returns value for entity method.
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
				$this->equalTo( [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] ),
				$this->equalTo( [ '%d' ] )
			)
			->willReturn( $result );

		$this->assertEquals( $this->rule_datamapper->save( $entity ), $return_entity );
	}

	/**
	 * Test case for delete
	 *
	 * @dataProvider provider_delete_with_object
	 * @param Rule $entity Entity to delete.
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
						return \is_array( $where ) && count( $where ) === 1 && array_search( $entity->id, $where, true ) !== false;
					}
				),
				$this->equalTo( [ '%d' ] )
			)
			->willReturn( $result );

		$this->assertEquals( $this->rule_datamapper->delete( $entity ), $result );
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

		$this->assertEquals( $this->rule_datamapper->delete( $entity_id ), $result );
	}

	/**
	 * Test case for find
	 *
	 * @dataProvider provider_find_not_exists
	 * @param int    $id ID of the entity to find.
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param bool   $with_relationships Whether to load relationships or not.
	 * @param Rule   $result Result of find method.
	 */
	public function testFind_notExists_returnsNull( $id, $table, $col_id, $with_relationships, $result ) {

		// Method only calls others methods, so we need to mock them.
		$rule_datamapper_mock = $this->getMockBuilder( RuleDatamapper::class )
		->setMethodsExcept( [ 'find' ] )
		->getMock();

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'table' )
			->willReturn( $table );

		$rule_datamapper_mock->expects( $this->once() )
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

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( [] )
			->willReturn( [] );

		$this->assertEquals( $result, $rule_datamapper_mock->find( $id, $with_relationships ) );
	}

	/**
	 * Test case for find
	 *
	 * @dataProvider provider_find_exists_without_relationships
	 * @param int    $id ID of the entity to find.
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param Rule   $result Result of find method.
	 */
	public function testFind_existsWithoutRelationships_returnsRule( $id, $table, $col_id, $result ) {

		// Method only calls others methods, so we need to mock them.
		$rule_datamapper_mock = $this->getMockBuilder( RuleDatamapper::class )
		->setMethodsExcept( [ 'find' ] )
		->getMock();

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'table' )
			->willReturn( $table );

		$rule_datamapper_mock->expects( $this->once() )
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

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( $raw_rows )
			->willReturn( [ $result ] );

		$this->assertEquals( $result, $rule_datamapper_mock->find( $id, false ) );
	}

	/**
	 * Test case for find
	 *
	 * @dataProvider provider_find_exists_with_relationships
	 * @param int    $id ID of the entity to find.
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param Rule   $result Result of find method.
	 */
	public function testFind_existsWithRelationships_returnsRuleWithRelationships( $id, $table, $col_id, $entity, $result ) {

		// Method only calls others methods, so we need to mock them.
		$rule_datamapper_mock = $this->getMockBuilder( RuleDatamapper::class )
		->setMethodsExcept( [ 'find' ] )
		->getMock();

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'table' )
			->willReturn( $table );

		$rule_datamapper_mock->expects( $this->once() )
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

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'map_all' )
			->with( $raw_rows )
			->willReturn( [ $entity ] );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'set_relationships' )
			->with( $entity )
			->willReturn( $result );

		$this->assertEquals( $result, $rule_datamapper_mock->find( $id, true ) );
	}

	/**
	 * Test case for set_relationships
	 *
	 * @dataProvider provider_set_relationships
	 * @param Rule $entity Entity to set relationships.
	 * @param Rule $result Result of set_relationships method.
	 */
	public function testSet_relationships( $entity, $result ) {
		$this->assertEquals( $this->rule_datamapper->set_relationships( $entity ), $result );
	}

	/**
	 * Test case for set_up
	 *
	 * @dataProvider provider_set_up
	 * @param string $table table name.
	 * @param string $col_id col id.
	 * @param string $col_tiered_pricing_id col tiered pricing id.
	 * @param string $col_product col product.
	 * @param string $col_tag col tag.
	 * @param string $col_category col category.
	 * @param string $col_user col user.
	 * @param string $col_role col role.
	 * @param string $col_bundle_product col bundle product.
	 * @param string $col_roles_rel col roles rel.
	 * @param string $col_tags_rel col tags rel.
	 * @param string $col_cats_rel col cats rel.
	 * @param string $col_tags_with_cats_rel col tags with cats rel.
	 * @param string $col_prods_cats_tags_with_roles_users_rel col prods cats tags with roles users rel.
	 * @param string $get_charset_collate get_charset_collate return.
	 * @param string $get_var get_var return.
	 */
	public function testSetUp( $table, $col_id, $col_tiered_pricing_id, $col_product, $col_tag, $col_category, $col_user, $col_role, $col_bundle_product, $col_roles_rel, $col_tags_rel, $col_cats_rel, $col_tags_with_cats_rel, $col_prods_cats_tags_with_roles_users_rel, $get_charset_collate, $get_var, $result ) {

		$this->wpdb->expects( $this->once() )
			->method( 'get_charset_collate' )
			->willReturn( $get_charset_collate );

		$this->wpdb->expects( $this->once() )
			->method( 'get_var' )
			->with( "SHOW TABLES LIKE '$table'" )
			->willReturn( $get_var );

		// The table name reaches SHOW TABLES LIKE through esc_like(); pass it through unchanged.
		$this->wpdb->method( 'esc_like' )->willReturnArgument( 0 );

		$prepared_sql = "CREATE TABLE `$table` (\n"
			. "`{$col_id}` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "`{$col_tiered_pricing_id}` bigint(20) unsigned NOT NULL,\n"
			. "`{$col_product}` text,\n"
			. "`{$col_tag}` text,\n"
			. "`{$col_category}` text,\n"
			. "`{$col_user}` text,\n"
			. "`{$col_role}` text,\n"
			. "`{$col_bundle_product}` text,\n"
			. "`{$col_roles_rel}` varchar(3) NOT NULL,\n"
			. "`{$col_tags_rel}` varchar(3) NOT NULL,\n"
			. "`{$col_cats_rel}` varchar(3) NOT NULL,\n"
			. "`{$col_tags_with_cats_rel}` varchar(3) NOT NULL,\n"
			. "`{$col_prods_cats_tags_with_roles_users_rel}` varchar(3) NOT NULL,\n"
			. "PRIMARY KEY  (`{$col_id}`),\n"
			. "KEY `{$col_tiered_pricing_id}` (`{$col_tiered_pricing_id}`)\n)";

		$invoked_count = $this->exactly( 2 );
		$this->wpdb->expects( $invoked_count )
			->method( 'prepare' )
			->willReturnCallback(
				function (
					$sql,
					...$args 
				) use ( 
					$invoked_count,
					$table,
					$col_id,
					$col_tiered_pricing_id,
					$col_product,
					$col_tag,
					$col_category,
					$col_user,
					$col_role,
					$col_bundle_product,
					$col_roles_rel,
					$col_tags_rel,
					$col_cats_rel,
					$col_tags_with_cats_rel,
					$col_prods_cats_tags_with_roles_users_rel
				) {
					if ( 1 === $invoked_count->getInvocationCount() ) {
						$this->assertEquals(
							$sql,
							"CREATE TABLE %i (\n"
							. "%i bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
							. "%i bigint(20) unsigned NOT NULL,\n"
							. "%i text,\n"
							. "%i text,\n"
							. "%i text,\n"
							. "%i text,\n"
							. "%i text,\n"
							. "%i text,\n"
							. "%i varchar(3) NOT NULL,\n"
							. "%i varchar(3) NOT NULL,\n"
							. "%i varchar(3) NOT NULL,\n"
							. "%i varchar(3) NOT NULL,\n"
							. "%i varchar(3) NOT NULL,\n"
							. "PRIMARY KEY  (%i),\n"
							. "KEY %i (%i)\n)"
						);
						$this->assertEquals( $table, $args[0] );
						$this->assertEquals( $col_id, $args[1] );
						$this->assertEquals( $col_tiered_pricing_id, $args[2] );
						$this->assertEquals( $col_product, $args[3] );
						$this->assertEquals( $col_tag, $args[4] );
						$this->assertEquals( $col_category, $args[5] );
						$this->assertEquals( $col_user, $args[6] );
						$this->assertEquals( $col_role, $args[7] );
						$this->assertEquals( $col_bundle_product, $args[8] );
						$this->assertEquals( $col_roles_rel, $args[9] );
						$this->assertEquals( $col_tags_rel, $args[10] );
						$this->assertEquals( $col_cats_rel, $args[11] );
						$this->assertEquals( $col_tags_with_cats_rel, $args[12] );
						$this->assertEquals( $col_prods_cats_tags_with_roles_users_rel, $args[13] );
						$this->assertEquals( $col_id, $args[14] );
						$this->assertEquals( $col_tiered_pricing_id, $args[15] );
						$this->assertEquals( $col_tiered_pricing_id, $args[16] );
					} else {
						$this->assertEquals( 'SHOW TABLES LIKE %s', $sql );
						$this->assertEquals( $table, $args[0] );
					}
					global $wpdb;
					// phpcs:ignore
					return $wpdb->prepare( $sql, $args );
				}
			);

		// set_up() asks dbDelta twice once the table checks out: the schema apply, then the
		// verification dry run. It stops after the apply when SHOW TABLES finds no table.
		$this->upgrade->method( 'db_delta' )
			->willReturnCallback(
				function ( $sql ) use ( $prepared_sql, $get_charset_collate ) {
					$this->assertEquals( "$prepared_sql {$get_charset_collate}", $sql );
					return [];
				}
			);

		// Method only calls others methods, so we need to mock them.
		$rule_datamapper_mock = $this->getMockBuilder( RuleDatamapper::class )
		->setMethodsExcept( [ 'set_up' ] )
		->getMock();

		$rule_datamapper_mock->expects( $this->exactly( 3 ) )
			->method( 'table' )
			->willReturn( $table );

		$rule_datamapper_mock->expects( $this->exactly( 2 ) )
			->method( 'col_id' )
			->willReturn( $col_id );

		$rule_datamapper_mock->expects( $this->exactly( 3 ) )
			->method( 'col_tiered_pricing_id' )
			->willReturn( $col_tiered_pricing_id );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_product' )
			->willReturn( $col_product );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_tag' )
			->willReturn( $col_tag );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_category' )
			->willReturn( $col_category );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_user' )
			->willReturn( $col_user );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_role' )
			->willReturn( $col_role );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_bundle_product' )
			->willReturn( $col_bundle_product );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_tags_rel' )
			->willReturn( $col_tags_rel );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_cats_rel' )
			->willReturn( $col_cats_rel );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_tags_with_cats_rel' )
			->willReturn( $col_tags_with_cats_rel );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_prods_cats_tags_with_roles_users_rel' )
			->willReturn( $col_prods_cats_tags_with_roles_users_rel );

		$rule_datamapper_mock->expects( $this->once() )
			->method( 'col_roles_rel' )
			->willReturn( $col_roles_rel );

		$this->assertEquals( $rule_datamapper_mock->set_up(), $result );
	}

	/**
	 * The row an entity is written as, in the shape a later SELECT reads it back in.
	 *
	 * Everything but the id comes from the real save() path; the id is the one column the insert
	 * leaves to AUTO_INCREMENT, so a read always carries it and the written row never does.
	 *
	 * @param Rule $entity Entity to save.
	 * @return array<string, mixed>
	 */
	private function saved_row( Rule $entity ) {
		$row = [];
		$this->wpdb->method( 'insert' )->willReturnCallback(
			function ( $table, $data ) use ( &$row ) {
				$row = $data;
				return 1;
			}
		);
		$this->wpdb->insert_id = 1;
		$this->rule_datamapper->save( $entity );

		return [ $this->rule_datamapper->col_id() => 1 ] + $row;
	}

	/**
	 * Test case for bundle-scoped targets surviving a save and a read back.
	 *
	 * @dataProvider provider_bundle_products_round_trip
	 * @param string[] $bundle_products Pairs to store.
	 */
	public function testSave_bundleScopedTargets_roundTripThroughMapObject( $bundle_products ) {
		$rule = new Rule( 0, 1, 'ANY', 'ANY', 'ANY', 'OR', 'OR', [], [], [], [], [], $bundle_products );

		$row = $this->saved_row( $rule );

		$this->assertArrayHasKey( $this->rule_datamapper->col_bundle_product(), $row );

		$mapped = $this->rule_datamapper->map_all( [ (object) $row ] );
		$this->assertSame( $bundle_products, $mapped[0]->bundle_products );
	}

	/**
	 * Test case for the same round trip through the array branch of map().
	 *
	 * map_array() and map_object() are separate implementations, so a column wired into one and not the
	 * other reads back empty from whichever branch was missed.
	 *
	 * @dataProvider provider_bundle_products_round_trip
	 * @param string[] $bundle_products Pairs to store.
	 */
	public function testSave_bundleScopedTargets_roundTripThroughMapArray( $bundle_products ) {
		$rule = new Rule( 0, 1, 'ANY', 'ANY', 'ANY', 'OR', 'OR', [], [], [], [], [], $bundle_products );

		$mapped = $this->rule_datamapper->map_all( [ $this->saved_row( $rule ) ] );

		$this->assertSame( $bundle_products, $mapped[0]->bundle_products );
	}

	/**
	 * Test case for a row written before the column existed.
	 *
	 * dbDelta adds the column as NULL on an existing install, so every rule saved before the migration
	 * reads back through this path.
	 */
	public function testMap_nullBundleProductColumn_readsAsEmptyArray() {
		$row = $this->saved_row( new Rule( 0, 1, 'ANY', 'ANY', 'ANY', 'OR', 'OR' ) );
		$row[ $this->rule_datamapper->col_bundle_product() ] = null;

		$mapped = $this->rule_datamapper->map_all( [ (object) $row, $row ] );

		$this->assertSame( [], $mapped[0]->bundle_products );
		$this->assertSame( [], $mapped[1]->bundle_products );
	}

	/**
	 * Data provider for the bundle_products round trip cases.
	 */
	public function provider_bundle_products_round_trip() {
		return [
			'one pair'      => [ [ '61:15' ] ],
			'several pairs' => [ [ '61:15', '7:15', '61:20' ] ],
			'empty list'    => [ [] ],
		];
	}

	/**
	 * Data provider for testFindByTieredPricing_onSuccess_returnsArray method.
	 */
	public function provider_find_by_tiered_pricing() {
		return [
			[
				1,
				[ 1, 2, 3 ],
				'where1',
				'wptests_alondra_rules1',
				'tiered_pricing_id1',
				'SELECT * FROM wptests_alondra_rules1 WHERE tiered_pricing_id1 = 1 where1',
				[ new Rule( 1, 1 ), new Rule( 2, 2 ), new Rule( 1, 3 ) ],
			],
			[
				99,
				[ 'categories', 'products' ],
				'where2',
				'wptests_alondra_rules2',
				'tiered_pricing_id2',
				'SELECT * FROM wptests_alondra_rules2 WHERE tiered_pricing_id2 = 99 where2',
				[],
			],
		];
	}

	/**
	 * Data provider for testSave_onEmptyId_doInsert method.
	 */
	public function provider_save_do_insert() {
		return [
			[ new Rule( 0, 1 ), 1, 1, new Rule( 1, 1 ) ],
			[ new Rule(), 0, 0, new Rule() ],
			[ new Rule(), false, 0, null ],
		];
	}

	/**
	 * Data provider for testSave_withId_doUpdate method.
	 */
	public function provider_save_do_update() {
		return [
			[ new Rule( 1, 1 ), 1, new Rule( 1, 1 ) ],
			[ new Rule( 1 ), 0, new Rule( 1 ) ],
			[ new Rule( 1 ), false, null ],
		];
	}

	/**
	 * Data provider for testDelete_withObject_doDelete method.
	 */
	public function provider_delete_with_object() {
		return [
			[ new Rule( 1 ), true ],
			[ new Rule(), false ],
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
	 * Data provider for testFind_notExists_returnsNull method.
	 */
	public function provider_find_not_exists() {
		return [
			[ 1, 'wptests_alondra_rules1', 'tiered_pricing_id1', true, null ],
			[ 99, 'wptests_alondra_rules2', 'tiered_pricing_id2', false, null ],
		];
	}

	/**
	 * Data provider for testFind_existsWithoutRelationships_returnsNull method.
	 */
	public function provider_find_exists_without_relationships() {
		return [
			[ 1, 'wptests_alondra_rules1', 'tiered_pricing_id1', new Rule( 1, 2 ) ],
			[ 99, 'wptests_alondra_rules2', 'tiered_pricing_id2', new Rule( 99, 100 ) ],
		];
	}

	/**
	 * Data provider for testFind_existsWithRelationships_returnsRuleWithRelationships method.
	 */
	public function provider_find_exists_with_relationships() {
		return [
			[ 1, 'wptests_alondra_rules1', 'tiered_pricing_id1', new Rule( 1, 2 ), new Rule( 4, 5 ) ],
			[ 99, 'wptests_alondra_rules2', 'tiered_pricing_id2', new Rule( 99, 100 ), new Rule( 3, 8 ) ],
		];
	}

	/**
	 * Data provider for testSet_relationships method.
	 */
	public function provider_set_relationships() {
		return [
			[ new Rule( 1, 2 ), new Rule( 1, 2 ) ],
			[ new Rule( 99, 100 ), new Rule( 99, 100 ) ],
		];
	}

	/**
	 * Data provider for testSet_up method.
	 */
	public function provider_set_up() {

		return [
			[
				'rule_table1',
				'col_id1',
				'col_tiered_pricing_id6',
				'col_product3',
				'col_tag2',
				'col_category0',
				'col_user2',
				'col_role7',
				'col_bundle_product4',
				'col_roles_rel3',
				'col_tags_rel2',
				'col_cats_rel6',
				'col_tags_with_cats_rel9',
				'col_prods_cats_tags_with_roles_users_rel0',
				'get_charset_collate UTF8',
				'rule_table1',
				true,
			],
			[
				'rule_table1',
				'col_id6',
				'col_tiered_pricing_id9',
				'col_product1',
				'col_tag0',
				'col_category3',
				'col_user5',
				'col_role8',
				'col_bundle_product2',
				'col_roles_rel1',
				'col_tags_rel0',
				'col_cats_rel7',
				'col_tags_with_cats_rel4',
				'col_prods_cats_tags_with_roles_users_rel5',
				'get_charset_collate UTF8',
				'rule_table0',
				false,
			],
			[
				'rule_table1',
				'col_id1',
				'col_tiered_pricing_id6',
				'col_product3',
				'col_tag2',
				'col_category0',
				'col_user2',
				'col_role7',
				'col_bundle_product4',
				'col_roles_rel3',
				'col_tags_rel2',
				'col_cats_rel6',
				'col_tags_with_cats_rel9',
				'col_prods_cats_tags_with_roles_users_rel0',
				'get_charset_collate UTF8',
				null,
				false,
			],
		];
	}
}
