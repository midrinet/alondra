<?php
/**
 * TieredPricingRepo Tests
 *
 * @package Midrinet\Alondra\Tests
 */

namespace Midrinet\Alondra\Tests\Repositories;

use Midrinet\Alondra\Application\Service\TieredPricingService;
use Midrinet\Alondra\Domain\Datamapper\TieredPricingDatamapper;
use Midrinet\Alondra\Domain\Entity\Rule;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\Config\PluginInfo;
use Midrinet\Alondra\Application\Service\PreferencesService;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Tests\Support\Container_Seam;
use WP_UnitTestCase;

use WP_User;

require_once __DIR__ . '/../Support/trait-container-seam.php';

/**
 * The contract: the same question is not asked of the database twice, a different question is
 * never answered from a neighbour's entry, and a write makes the next question fresh again.
 * Only the $wpdb->num_queries deltas prove the cache was consulted at all.
 */
class TieredPricingRepoTest extends WP_UnitTestCase {

	use Container_Seam;

	/**
	 * Repository under test.
	 *
	 * @var TieredPricingRepo
	 */
	private $repo;

	/**
	 * Product covered by a group with tiers for 1 to 9 units only.
	 *
	 * @var int
	 */
	private $product_a;

	/**
	 * Product covered by a group with tiers for any quantity.
	 *
	 * @var int
	 */
	private $product_b;

	/**
	 * Product covered by a group restricted to one user.
	 *
	 * @var int
	 */
	private $product_c;

	/**
	 * Product no group covers.
	 *
	 * @var int
	 */
	private $product_none;

	/**
	 * User the group for product C applies to.
	 *
	 * @var int
	 */
	private $user_a;

	/**
	 * User no group applies to.
	 *
	 * @var int
	 */
	private $user_b;

	/**
	 * Group matching product A, tiers 1-9 at 10.0.
	 *
	 * @var TieredPricing
	 */
	private $tp_a;

	/**
	 * Group matching product B, tiers 1-MAX at 20.0.
	 *
	 * @var TieredPricing
	 */
	private $tp_b;

	/**
	 * Group matching product C for user A only, tiers 1-MAX at 7.0.
	 *
	 * @var TieredPricing
	 */
	private $tp_c;

	/**
	 * Create the real tables once, before the first test opens its transaction.
	 *
	 * The schema and SHOW TABLES do not survive the CREATE TEMPORARY TABLE rewrite. Here
	 * also keeps DDL out of the tests, where it would commit and escape the rollback.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		// Start from nothing, so a table another test left behind cannot alter the schema.
		self::drop_tables();
		( new TieredPricingRepo() )->setup_database();
	}

	public static function tear_down_after_class() {
		self::drop_tables();
		parent::tear_down_after_class();
	}

	/**
	 * Drop the plugin tables.
	 *
	 * @return void
	 */
	private static function drop_tables() {
		global $wpdb;
		foreach ( [ 'alondra_tiers', 'alondra_rules', 'alondra_tiered_pricing' ] as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
		}
	}

	public function set_up() {
		parent::set_up();

		// Its own instance: the generation memo must not survive into the next test.
		$this->repo = new TieredPricingRepo();

		$this->product_a    = self::factory()->post->create( [ 'post_type' => 'product' ] );
		$this->product_b    = self::factory()->post->create( [ 'post_type' => 'product' ] );
		$this->product_c    = self::factory()->post->create( [ 'post_type' => 'product' ] );
		$this->product_none = self::factory()->post->create( [ 'post_type' => 'product' ] );
		$this->user_a       = self::factory()->user->create();
		$this->user_b       = self::factory()->user->create();

		$this->tp_a = $this->save_group( 'A', [ $this->product_a ], [], 1, 9, 10.0 );
		$this->tp_b = $this->save_group( 'B', [ $this->product_b ], [], 1, Tier::MAX_UNITS, 20.0 );
		$this->tp_c = $this->save_group( 'C', [ $this->product_c ], [ $this->user_a ], 1, Tier::MAX_UNITS, 7.0 );
	}

	/**
	 * The CREATE TABLE of a child table, as the server reports it back.
	 *
	 * @param string $table Unprefixed child table name.
	 * @return string
	 */
	private function show_create_table( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW CREATE TABLE %i', $wpdb->prefix . $table ), 1 );
	}

	/**
	 * Fail unless the plugin is the only thing holding the guarantees the tests below
	 * assert: a constraint would let the server pass them on the plugin's behalf.
	 *
	 * @return void
	 */
	private function assert_no_foreign_keys() {
		foreach ( [ 'alondra_tiers', 'alondra_rules' ] as $table ) {
			$this->assertStringNotContainsString( 'FOREIGN KEY', $this->show_create_table( $table ), "Table $table must not delegate this to the schema." );
		}
	}

	/**
	 * Rows a child table holds for a Tiered Pricing.
	 *
	 * @param string $table Unprefixed child table name.
	 * @param int    $tiered_pricing_id Tiered Pricing ID.
	 * @return int
	 */
	private function child_rows( $table, $tiered_pricing_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %d', $wpdb->prefix . $table, 'tiered_pricing_id', $tiered_pricing_id )
		);
	}

	/**
	 * Persist a published group with a single rule and a single tier.
	 *
	 * @param string $title Group title.
	 * @param int[]  $products Products the rule matches.
	 * @param int[]  $users Users the rule matches.
	 * @param int    $min_units Tier lower bound, inclusive.
	 * @param int    $max_units Tier upper bound, inclusive.
	 * @param float  $value Fixed price per unit.
	 * @param Tier[] $extra_tiers Further tiers to persist alongside the one described above.
	 * @return TieredPricing
	 */
	private function save_group( $title, $products, $users, $min_units, $max_units, $value, $extra_tiers = [] ) {
		$rule  = new Rule(
			0,
			0,
			Rule::RELATIONSHIP_ANY,
			Rule::RELATIONSHIP_ANY,
			Rule::RELATIONSHIP_ANY,
			Rule::RELATIONSHIP_OR,
			Rule::RELATIONSHIP_OR,
			[],
			[],
			$products,
			$users,
			[]
		);
		$item  = new TieredPricing(
			0,
			$title,
			TieredPricing::MIN_PRIORITY,
			TieredPricing::STATUS_PUBLISH,
			'0000-00-00 00:00:00',
			array_merge( [ new Tier( 0, 0, $min_units, $max_units, true, $value ) ], $extra_tiers ),
			[ $rule ]
		);
		$saved = $this->repo->save( $item );
		$this->assertInstanceOf( TieredPricing::class, $saved, "Fixture group $title must persist." );
		return $saved;
	}

	/**
	 * Queries run so far in the request.
	 *
	 * @return int
	 */
	private function query_count() {
		global $wpdb;
		return (int) $wpdb->num_queries;
	}

	/**
	 * Persist a published group with one rule of the caller's making and a tier for any quantity.
	 *
	 * @param string $title Group title.
	 * @param Rule   $rule The group's only rule.
	 * @param int    $priority Group priority.
	 * @return TieredPricing
	 */
	private function save_group_with_rule( $title, Rule $rule, $priority = TieredPricing::MIN_PRIORITY ) {
		$item  = new TieredPricing( 0, $title, $priority, TieredPricing::STATUS_PUBLISH, '0000-00-00 00:00:00', [ new Tier( 0, 0, 1, Tier::MAX_UNITS, true, 1.0 ) ], [ $rule ] );
		$saved = $this->repo->save( $item );
		$this->assertInstanceOf( TieredPricing::class, $saved, "Fixture group $title must persist." );
		return $saved;
	}

	/**
	 * A rule targeting products, categories or roles only.
	 *
	 * @param int[]    $products Products.
	 * @param int[]    $categories Categories.
	 * @param string[] $roles Roles.
	 * @return Rule
	 */
	private function rule_for( $products = [], $categories = [], $roles = [] ) {
		return new Rule( 0, 0, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_OR, Rule::RELATIONSHIP_OR, [], $categories, $products, [], $roles );
	}

	/**
	 * Record every query that reads the group table, for asserting its ORDER BY.
	 *
	 * @return \ArrayObject<int, string>
	 */
	private function record_group_queries() {
		global $wpdb;
		$table   = $wpdb->prefix . 'alondra_tiered_pricing';
		$queries = new \ArrayObject();
		add_filter(
			'query',
			function ( $query ) use ( $queries, $table ) {
				if ( false !== strpos( $query, "SELECT `$table`.*" ) ) {
					$queries[] = $query;
				}
				return $query;
			}
		);
		return $queries;
	}

	/**
	 * Queries each lookup spends on a fresh instance, in the order asked.
	 *
	 * @return int[]
	 */
	private function queries_per_lookup() {
		$cat = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		wp_set_object_terms( $this->product_a, [ $cat ], 'product_cat' );
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$counts = [];
		foreach ( [ [ $this->product_a, 0, 1 ], [ $this->product_a, 0, 50 ], [ $this->product_c, $this->user_b, 1 ], [ $this->product_c, $this->user_a, 1 ], [ $this->product_none, $subscriber, 1 ] ] as $lookup ) {
			$repo   = new TieredPricingRepo();
			$before = $this->query_count();
			$repo->find_matching( ...$lookup );
			$counts[] = $this->query_count() - $before;
		}
		foreach ( [ [ $this->product_a, 0 ], [ $this->product_c, $this->user_b ], [ $this->product_c, $this->user_a ] ] as $lookup ) {
			$repo   = new TieredPricingRepo();
			$before = $this->query_count();
			$repo->find_all_matching( ...$lookup );
			$counts[] = $this->query_count() - $before;
		}
		return $counts;
	}

	/**
	 * ID of the group matching a lookup, failing when nothing matched.
	 *
	 * @param int $product_id Product ID.
	 * @param int $user_id User ID.
	 * @param int $quantity Quantity.
	 * @return int
	 */
	private function matching_id( $product_id, $user_id, $quantity ) {
		$item = $this->repo->find_matching( $product_id, $user_id, $quantity );
		$this->assertInstanceOf( TieredPricing::class, $item, 'Expected a matching group.' );
		return $item->id;
	}

	/**
	 * Key the repository stores a find_matching() answer under.
	 *
	 * Duplicated on purpose: planting a payload needs an address, and assert_key_is_warm()
	 * makes a drift in the format fail loudly.
	 *
	 * @param int $product_id Product ID.
	 * @param int $user_id User ID.
	 * @param int $quantity Quantity.
	 * @return string
	 */
	private function cache_key_one( $product_id, $user_id, $quantity ) {
		return Container::instance()->get( PluginInfo::class )->get_plugin_version() . '_g' . $this->cache_generation() . '_one_' . (int) $product_id . '_' . (int) $user_id . '_' . (float) $quantity;
	}

	/**
	 * Key the repository stores a find_all_matching() answer under.
	 *
	 * @param int $product_id Product ID.
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function cache_key_all( $product_id, $user_id ) {
		return Container::instance()->get( PluginInfo::class )->get_plugin_version() . '_g' . $this->cache_generation() . '_all_' . (int) $product_id . '_' . (int) $user_id;
	}

	/**
	 * A key exactly as the repository builds it.
	 *
	 * @param TieredPricingRepo $repo Repository.
	 * @param string            $method One of the private cache_key_*() methods.
	 * @param mixed             ...$args Its arguments.
	 * @return string
	 */
	private function repo_key( TieredPricingRepo $repo, $method, ...$args ) {
		$reflection = new \ReflectionMethod( TieredPricingRepo::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invoke( $repo, ...$args );
	}

	/**
	 * The one, all and entity keys of a fresh repository.
	 *
	 * @return array<string, string>
	 */
	private function keys_of_a_fresh_repo() {
		$repo = new TieredPricingRepo();
		return [
			'one'    => $this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1 ),
			'all'    => $this->repo_key( $repo, 'cache_key_all', $this->product_a, 0 ),
			'entity' => $this->repo_key( $repo, 'cache_key_entity', $this->tp_a->id, true ),
		];
	}

	/**
	 * Generation token the repository is currently folding into its keys.
	 *
	 * @return string
	 */
	private function cache_generation() {
		$stored = get_option( TieredPricingRepo::CACHE_GENERATION );
		return \is_string( $stored ) ? $stored : '';
	}

	/**
	 * Assert a key addresses an entry the repository itself wrote.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	private function assert_key_is_warm( $key ) {
		$found = false;
		wp_cache_get( $key, TieredPricingRepo::CACHE_GROUP, false, $found );
		$this->assertTrue( $found, "Key $key must address the entry the repository just wrote." );
	}

	/**
	 * Populate the product_a lookup on an instance of its own.
	 *
	 * Fresh instances on both ends of an invalidation test: only the option carries the
	 * generation between them, so one instance's memo cannot hide what another wrote.
	 *
	 * @return void
	 */
	private function warm_lookup() {
		$repo = new TieredPricingRepo();
		$this->assertInstanceOf( TieredPricing::class, $repo->find_matching( $this->product_a, 0, 1 ), 'Expected a group to warm.' );
	}

	/**
	 * Queries a fresh instance spends answering the lookup warm_lookup() populated.
	 *
	 * @return int
	 */
	private function queries_to_reanswer() {
		$repo   = new TieredPricingRepo();
		$before = $this->query_count();
		$repo->find_matching( $this->product_a, 0, 1 );
		return $this->query_count() - $before;
	}

	public function test_repeated_find_matching_is_served_from_the_cache() {
		$first = $this->repo->find_matching( $this->product_a, 0, 1 );
		$this->assertInstanceOf( TieredPricing::class, $first );

		$before  = $this->query_count();
		$second  = $this->repo->find_matching( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'An identical lookup must not reach the database again.' );
		$this->assertEquals( $first, $second, 'The cached answer must equal the queried one.' );
	}

	/**
	 * With the cache switched off every lookup reaches the database and nothing is written to the cache.
	 */
	public function test_a_repo_with_the_cache_off_queries_every_time() {
		$this->repo->use_cache( false );
		$this->repo->find_matching( $this->product_a, 0, 1 );

		$before = $this->query_count();
		$this->assertInstanceOf( TieredPricing::class, $this->repo->find_matching( $this->product_a, 0, 1 ) );
		$this->assertGreaterThan( 0, $this->query_count() - $before, 'A lookup with the cache off must query.' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'The cache-off lookup must not have warmed the cache.' );
	}

	/**
	 * The container switches the repo it hands out, whoever asks for it.
	 */
	public function test_a_repo_from_the_container_follows_the_enable_cache_preference() {
		update_option( PreferencesService::PREF_OPTION, [ PreferencesService::PREF_ENABLE_CACHE => '0' ] );
		$this->reset_container();
		$repo = Container::build( \dirname( __DIR__, 2 ) . '/alondra.php' )->get( TieredPricingRepo::class );

		$repo->find_matching( $this->product_a, 0, 1 );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'The lookup must not have warmed the cache.' );
	}

	public function test_repeated_find_all_matching_is_served_from_the_cache() {
		$first = $this->repo->find_all_matching( $this->product_a, 0 );
		$this->assertCount( 1, $first );

		$before  = $this->query_count();
		$second  = $this->repo->find_all_matching( $this->product_a, 0 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'An identical lookup must not reach the database again.' );
		$this->assertEquals( $first, $second, 'The cached answer must equal the queried one.' );
	}

	public function test_repeated_find_is_served_from_the_cache() {
		$first = $this->repo->find( $this->tp_a->id, true );
		$this->assertCount( 1, $first );

		$before  = $this->query_count();
		$second  = $this->repo->find( $this->tp_a->id, true );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'An identical lookup must not reach the database again.' );
		$this->assertEquals( $first, $second, 'The cached answer must equal the queried one.' );
	}

	public function test_lookups_for_different_products_do_not_collide() {
		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
		$this->assertSame( $this->tp_b->id, $this->matching_id( $this->product_b, 0, 1 ) );
		// Reading the first one back proves the second lookup did not overwrite it.
		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
	}

	public function test_lookups_for_different_users_do_not_collide() {
		// The group for product C applies to user A only.
		$this->assertSame( $this->tp_c->id, $this->matching_id( $this->product_c, $this->user_a, 1 ) );
		$this->assertNull( $this->repo->find_matching( $this->product_c, $this->user_b, 1 ), 'User B must not be served user A price.' );
		$this->assertSame( $this->tp_c->id, $this->matching_id( $this->product_c, $this->user_a, 1 ) );
	}

	public function test_lookups_for_different_quantities_do_not_collide() {
		// The group for product A only has a tier for 1 to 9 units.
		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
		$this->assertNull( $this->repo->find_matching( $this->product_a, 0, 50 ), 'A quantity outside every tier must not match.' );
		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
	}

	public function test_find_with_and_without_relationships_do_not_collide() {
		// The flag is part of the key, so these are two different questions.
		$full = $this->repo->find( $this->tp_a->id, true );
		$this->assertCount( 1, $full );
		$this->assertCount( 1, $full[0]->tiers, 'The full entity must carry its tiers.' );
		$this->assertCount( 1, $full[0]->rules, 'The full entity must carry its rules.' );

		$partial = $this->repo->find( $this->tp_a->id, false );
		$this->assertCount( 1, $partial );
		$this->assertSame( $this->tp_a->id, $partial[0]->id );
		$this->assertSame( [], $partial[0]->tiers, 'The partial entity must not be served the full payload.' );
		$this->assertSame( [], $partial[0]->rules, 'The partial entity must not be served the full payload.' );

		// Reading the full one back proves the partial lookup did not overwrite it.
		$again = $this->repo->find( $this->tp_a->id, true );
		$this->assertCount( 1, $again[0]->tiers );
		$this->assertCount( 1, $again[0]->rules );
	}

	public function test_save_invalidates_the_cache() {
		$warm = $this->repo->find_matching( $this->product_a, 0, 1 );
		$this->assertInstanceOf( TieredPricing::class, $warm );
		$this->assertSame( 10.0, $warm->tiers[0]->value );

		$this->tp_a->tiers[0]->value = 3.0;
		$this->repo->save( $this->tp_a );

		$before  = $this->query_count();
		$after   = $this->repo->find_matching( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertGreaterThan( 0, $queries, 'A save must force the next lookup to query again.' );
		$this->assertInstanceOf( TieredPricing::class, $after );
		$this->assertSame( 'A', $after->title, 'The lookup must return the saved group.' );
		$this->assertSame( 3.0, $after->tiers[0]->value, 'The lookup must return the saved price.' );
	}

	/**
	 * End to end through the SQL prefilter: the role LIKE selects the group, and the rule then refuses it
	 * because its only product-side targets are bundle pairs this plugin does not match.
	 */
	public function test_a_rule_of_bundle_pairs_and_a_role_prices_no_plain_product() {
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$rule = new Rule( 0, 0, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_ANY, Rule::RELATIONSHIP_OR, Rule::RELATIONSHIP_OR, [], [], [], [], [ 'subscriber' ], [ '61:' . $this->product_none ] );
		$item = new TieredPricing( 0, 'Bundle pairs only', TieredPricing::MIN_PRIORITY, TieredPricing::STATUS_PUBLISH, '0000-00-00 00:00:00', [ new Tier( 0, 0, 1, 10, true, 1.0 ) ], [ $rule ] );
		$this->assertInstanceOf( TieredPricing::class, $this->repo->save( $item ) );

		$this->assertNull( $this->repo->find_matching( $this->product_none, $user, 1 ) );
	}

	/**
	 * save() deletes the orphaned tiers and rules before the datamapper writes, so a
	 * second save in the same request — same title, priority, status, and a date_updated gmdate()
	 * regenerates to the same second — must not read its 0 affected parent rows as a failure and
	 * skip the children it has already orphaned.
	 *
	 * Nothing here asserts the affected-row count: crossing a second boundary between the two
	 * saves makes the parent row differ and costs the reproduction, not the assertions.
	 */
	public function test_a_second_save_persists_the_tiers_when_no_parent_column_changed() {
		$group = $this->save_group( 'D', [ $this->product_none ], [], 1, 9, 10.0, [ new Tier( 0, 0, 10, Tier::MAX_UNITS, true, 8.0 ) ] );
		$this->assertCount( 2, $group->tiers );
		$surviving = $group->tiers[0]->id;

		// One tier repriced, the other dropped so save() deletes it as an orphan first.
		$group->tiers[0]->value = 4.0;
		$group->tiers           = [ $group->tiers[0] ];
		$saved                  = $this->repo->save( $group );

		$this->assertInstanceOf( TieredPricing::class, $saved, 'A save that changed no parent column must not report failure.' );
		$this->assertNotContains( null, $saved->tiers, 'A failed child save must not leave a null in the collection.' );
		$this->assertNotContains( null, $saved->rules, 'A failed child save must not leave a null in the collection.' );

		$stored = $this->repo->find( $group->id, true );
		$this->assertCount( 1, $stored );
		$this->assertCount( 1, $stored[0]->tiers, 'The dropped tier must be gone from the table.' );
		$this->assertSame( $surviving, $stored[0]->tiers[0]->id );
		$this->assertSame( 4.0, $stored[0]->tiers[0]->value, 'The surviving tier must carry the new value.' );
		$this->assertCount( 1, $stored[0]->rules, 'The rule must survive the second save too.' );

		$this->assertInstanceOf( TieredPricing::class, $this->repo->save( $group ), 'A third consecutive save must still succeed.' );
	}

	public function test_delete_invalidates_the_cache() {
		$this->assertSame( $this->tp_b->id, $this->matching_id( $this->product_b, 0, 1 ) );

		$this->assertSame( 1, $this->repo->delete( $this->tp_b->id ) );

		$before  = $this->query_count();
		$after   = $this->repo->find_matching( $this->product_b, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertGreaterThan( 0, $queries, 'A delete must force the next lookup to query again.' );
		$this->assertNull( $after, 'A deleted group must stop matching.' );
	}

	public function test_no_match_is_cached_and_read_back_as_a_negative() {
		$this->assertNull( $this->repo->find_matching( $this->product_none, 0, 1 ) );

		$before  = $this->query_count();
		$second  = $this->repo->find_matching( $this->product_none, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'A cached null must read as a hit, not as a miss.' );
		$this->assertNull( $second );
	}

	public function test_no_matches_list_is_cached_and_read_back_as_a_negative() {
		$this->assertSame( [], $this->repo->find_all_matching( $this->product_none, 0 ) );

		$before  = $this->query_count();
		$second  = $this->repo->find_all_matching( $this->product_none, 0 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'A cached empty list must read as a hit, not as a miss.' );
		$this->assertSame( [], $second );
	}

	public function test_missing_id_is_cached_and_read_back_as_a_negative() {
		$missing = $this->tp_c->id + 1000;
		$this->assertSame( [], $this->repo->find( $missing, true ) );

		$before  = $this->query_count();
		$second  = $this->repo->find( $missing, true );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'A cached missing entity must read as a hit, not as a miss.' );
		$this->assertSame( [], $second );
	}

	public function test_flush_cache_invalidates_the_cache() {
		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );

		$this->repo->flush_cache();

		$before  = $this->query_count();
		$after   = $this->matching_id( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertGreaterThan( 0, $queries, 'flush_cache() must force the next lookup to query again.' );
		$this->assertSame( $this->tp_a->id, $after );
	}

	public function test_flush_cache_leaves_the_rest_of_the_object_cache_intact() {
		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
		wp_cache_set( 'neighbour', 'value', 'some_other_plugin' );

		$this->repo->flush_cache();

		$before = $this->query_count();
		$this->matching_id( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 'value', wp_cache_get( 'neighbour', 'some_other_plugin' ), 'Invalidation must not reach another group.' );
		$this->assertGreaterThan( 0, $queries, 'Invalidation must still reach our own entries.' );
	}

	/**
	 * Tokens a filter, a half-written option or an uninstall could leave behind.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function unusable_generations() {
		return [
			'empty string' => [ '' ],
			'array'        => [ [ 'x' ] ],
			'integer'      => [ 9 ],
			'false'        => [ false ],
		];
	}

	/**
	 * @dataProvider unusable_generations
	 *
	 * @param mixed $stored Unusable option value.
	 */
	public function test_an_unusable_generation_token_is_replaced_and_persisted( $stored ) {
		update_option( TieredPricingRepo::CACHE_GENERATION, $stored );

		$this->warm_lookup();

		$token = get_option( TieredPricingRepo::CACHE_GENERATION );
		$this->assertIsString( $token );
		$this->assertNotSame( '', $token, 'An unusable token must be replaced by a usable one.' );
		$this->assertSame( 0, $this->queries_to_reanswer(), 'And persisted, or every instance would coin its own and never hit.' );
	}

	public function test_a_missing_generation_token_is_replaced_and_persisted() {
		delete_option( TieredPricingRepo::CACHE_GENERATION );

		$this->warm_lookup();

		$this->assertIsString( get_option( TieredPricingRepo::CACHE_GENERATION ), 'An absent token must be written, not improvised per request.' );
		$this->assertSame( 0, $this->queries_to_reanswer(), 'The next instance must reuse it.' );
	}

	public function test_flush_cache_is_not_lost_to_a_generation_another_request_stored() {
		$mine = new TieredPricingRepo();
		$mine->find_matching( $this->product_a, 0, 1 );

		// Another request invalidates and repopulates while this instance holds the old generation.
		$other = new TieredPricingRepo();
		$other->flush_cache();
		$other->find_matching( $this->product_a, 0, 1 );

		$mine->flush_cache();

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'A flush must invalidate what the other request cached, not collide with its generation.' );
	}

	/**
	 * The suite is single-site, so switch_to_blog() is not even defined; $blog_id is the
	 * only thing it moves that this memo keys on, and the tables and the generation option
	 * it addresses are per-site too.
	 */
	public function test_the_generation_memo_does_not_survive_a_blog_switch() {
		global $blog_id;

		$repo = new TieredPricingRepo();
		$repo->find_matching( $this->product_a, 0, 1 );

		$home = $blog_id;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing in for switch_to_blog(); restored below.
		$blog_id = $home + 1;
		update_option( TieredPricingRepo::CACHE_GENERATION, 'a-generation-only-the-other-site-wrote' );

		$before = $this->query_count();
		$repo->find_matching( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Undoing the switch above.
		$blog_id = $home;

		$this->assertGreaterThan( 0, $queries, 'A memo carried across a blog switch addresses entries the other site never wrote.' );
	}

	/**
	 * The key format is duplicated in cache_key_one(), so dropping the version from the
	 * repository's own keys leaves this address cold.
	 */
	public function test_the_cache_key_carries_the_plugin_version() {
		$this->warm_lookup();

		$this->assert_key_is_warm( $this->cache_key_one( $this->product_a, 0, 1 ) );
	}

	public function test_assigning_product_terms_invalidates_the_cache() {
		$term = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		$this->warm_lookup();

		wp_set_object_terms( $this->product_a, [ $term ], 'product_cat' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'A term the product did not have must invalidate.' );
	}

	public function test_reassigning_the_same_product_terms_does_not_invalidate() {
		$term = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		wp_set_object_terms( $this->product_a, [ $term ], 'product_cat' );
		$this->warm_lookup();

		wp_set_object_terms( $this->product_a, [ $term ], 'product_cat' );

		$this->assertSame( 0, $this->queries_to_reanswer(), 'A term assignment that changed nothing must not invalidate.' );
	}

	public function test_removing_product_terms_invalidates_the_cache() {
		$term = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		wp_set_object_terms( $this->product_a, [ $term ], 'product_cat' );
		$this->warm_lookup();

		wp_remove_object_terms( $this->product_a, [ $term ], 'product_cat' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'Removing a term must invalidate too, not only assigning one.' );
	}

	public function test_deleting_a_product_term_invalidates_the_cache() {
		$term = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		wp_set_object_terms( $this->product_a, [ $term ], 'product_cat' );
		$this->warm_lookup();

		wp_delete_term( $term, 'product_cat' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'Deleting a targeted category must invalidate.' );
	}

	public function test_terms_outside_the_product_taxonomies_leave_the_cache_alone() {
		$post = self::factory()->post->create();
		$term = self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );
		$this->warm_lookup();

		wp_set_object_terms( $post, [ $term ], 'post_tag' );
		$this->assertSame( 0, $this->queries_to_reanswer(), 'Assigning a term of a taxonomy pricing cannot see must not invalidate.' );

		wp_remove_object_terms( $post, [ $term ], 'post_tag' );
		$this->assertSame( 0, $this->queries_to_reanswer(), 'Nor must removing one.' );
	}

	public function test_adding_a_user_role_invalidates_the_cache() {
		$user = new WP_User( $this->user_b );
		$this->warm_lookup();
		$this->assertSame( 0, $this->queries_to_reanswer(), 'The lookup must be warm before the role change.' );

		$user->add_role( 'editor' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'A role the user did not have must invalidate.' );
	}

	public function test_removing_a_user_role_invalidates_the_cache() {
		$user = new WP_User( $this->user_b );
		$this->warm_lookup();
		$this->assertSame( 0, $this->queries_to_reanswer(), 'The lookup must be warm before the role change.' );

		$user->remove_role( 'subscriber' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'Removing a role must invalidate too, not only adding one.' );
	}

	public function test_changing_a_user_role_invalidates_the_cache() {
		$user = new WP_User( $this->user_b );
		$this->warm_lookup();
		$this->assertSame( 0, $this->queries_to_reanswer(), 'The lookup must be warm before the role change.' );

		$user->set_role( 'editor' );

		$this->assertGreaterThan( 0, $this->queries_to_reanswer(), 'A role set that changed must invalidate.' );
	}

	public function test_setting_the_role_the_user_already_has_does_not_invalidate() {
		// Pins core's short-circuit: WP_User::set_role() returns before firing anything when the role is already the only one.
		$user = new WP_User( $this->user_b );
		$this->assertSame( [ 'subscriber' ], $user->roles, 'The fixture user must hold exactly the role being re-applied.' );
		$this->warm_lookup();
		$this->assertSame( 0, $this->queries_to_reanswer(), 'The lookup must be warm before the role change.' );

		$user->set_role( 'subscriber' );

		$this->assertSame( 0, $this->queries_to_reanswer(), 'A role set that changed nothing must not invalidate.' );
	}

	public function test_mutating_a_returned_entity_cannot_corrupt_the_cache() {
		// The first call returns the very object that populated the entry.
		$first = $this->repo->find_matching( $this->product_a, 0, 1 );
		$this->assertInstanceOf( TieredPricing::class, $first );
		$first->tiers[0]->value = 111.0;

		$before  = $this->query_count();
		$second  = $this->repo->find_matching( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'The second lookup must still be a cache hit.' );
		$this->assertInstanceOf( TieredPricing::class, $second );
		$this->assertSame( 10.0, $second->tiers[0]->value, 'The caller that populated the entry must not reach the cached graph.' );

		// The second call returns a copy the cache handed out.
		$second->tiers[0]->value = 222.0;
		$third                   = $this->repo->find_matching( $this->product_a, 0, 1 );
		$this->assertInstanceOf( TieredPricing::class, $third );
		$this->assertSame( 10.0, $third->tiers[0]->value, 'A reader must not reach the cached graph either.' );
	}

	public function test_mutating_a_returned_list_cannot_corrupt_the_cache() {
		// wp_cache_set() never clones an array payload, so this path shares its tiers unaided.
		$first = $this->repo->find_all_matching( $this->product_a, 0 );
		$this->assertCount( 1, $first );
		$first[0]->tiers[0]->value = 111.0;

		$before  = $this->query_count();
		$second  = $this->repo->find_all_matching( $this->product_a, 0 );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'The second lookup must still be a cache hit.' );
		$this->assertCount( 1, $second );
		$this->assertSame( 10.0, $second[0]->tiers[0]->value, 'The caller that populated the entry must not reach the cached graph.' );

		$second[0]->tiers[0]->value = 222.0;
		$third                      = $this->repo->find_all_matching( $this->product_a, 0 );
		$this->assertCount( 1, $third );
		$this->assertSame( 10.0, $third[0]->tiers[0]->value, 'A reader must not reach the cached graph either.' );
	}

	public function test_mutating_a_found_entity_cannot_corrupt_the_cache() {
		$first = $this->repo->find( $this->tp_a->id, true );
		$this->assertCount( 1, $first );
		$first[0]->tiers[0]->value = 111.0;

		$before  = $this->query_count();
		$second  = $this->repo->find( $this->tp_a->id, true );
		$queries = $this->query_count() - $before;

		$this->assertSame( 0, $queries, 'The second lookup must still be a cache hit.' );
		$this->assertSame( 10.0, $second[0]->tiers[0]->value, 'The caller that populated the entry must not reach the cached graph.' );

		$second[0]->tiers[0]->value = 222.0;
		$third                      = $this->repo->find( $this->tp_a->id, true );
		$this->assertSame( 10.0, $third[0]->tiers[0]->value, 'A reader must not reach the cached graph either.' );
	}

	public function test_malformed_cached_entity_degrades_to_a_miss() {
		$this->repo->find_matching( $this->product_a, 0, 1 );
		$key = $this->cache_key_one( $this->product_a, 0, 1 );
		$this->assert_key_is_warm( $key );

		// What an older version could have serialized; cloning it would raise a TypeError.
		$broken = new TieredPricing(
			$this->tp_a->id,
			'A',
			TieredPricing::MIN_PRIORITY,
			TieredPricing::STATUS_PUBLISH,
			'0000-00-00 00:00:00',
			[ null ],
			[]
		);
		wp_cache_set( $key, $broken, TieredPricingRepo::CACHE_GROUP );

		$before  = $this->query_count();
		$item    = $this->repo->find_matching( $this->product_a, 0, 1 );
		$queries = $this->query_count() - $before;

		$this->assertGreaterThan( 0, $queries, 'A malformed payload must be a miss, not a price.' );
		$this->assertInstanceOf( TieredPricing::class, $item );
		$this->assertSame( $this->tp_a->id, $item->id );
		$this->assertSame( 10.0, $item->tiers[0]->value );
	}

	public function test_malformed_cached_list_degrades_to_a_miss() {
		$this->repo->find_all_matching( $this->product_a, 0 );
		$key = $this->cache_key_all( $this->product_a, 0 );
		$this->assert_key_is_warm( $key );

		wp_cache_set( $key, [ 'not an entity' ], TieredPricingRepo::CACHE_GROUP );

		$before  = $this->query_count();
		$items   = $this->repo->find_all_matching( $this->product_a, 0 );
		$queries = $this->query_count() - $before;

		$this->assertGreaterThan( 0, $queries, 'A malformed payload must be a miss, not a price.' );
		$this->assertCount( 1, $items );
		$this->assertSame( $this->tp_a->id, $items[0]->id );
		$this->assertSame( 10.0, $items[0]->tiers[0]->value );
	}

	public function test_child_tables_index_the_parent_id_without_a_foreign_key() {
		$this->assert_no_foreign_keys();
		foreach ( [ 'alondra_tiers', 'alondra_rules' ] as $table ) {
			$this->assertStringContainsString(
				'KEY `tiered_pricing_id` (`tiered_pricing_id`)',
				$this->show_create_table( $table ),
				"Table $table must index the column its rows are looked up and deleted by."
			);
		}
	}

	public function test_delete_removes_the_tiers_and_rules_of_the_group() {
		$this->assert_no_foreign_keys();
		$id = $this->tp_a->id;
		$this->assertSame( 1, $this->child_rows( 'alondra_tiers', $id ), 'The fixture group must own a tier to lose.' );
		$this->assertSame( 1, $this->child_rows( 'alondra_rules', $id ), 'The fixture group must own a rule to lose.' );

		$this->assertSame( 1, $this->repo->delete( $id ) );

		$this->assertSame( 0, $this->child_rows( 'alondra_tiers', $id ), 'The tiers must go with the group.' );
		$this->assertSame( 0, $this->child_rows( 'alondra_rules', $id ), 'The rules must go with the group.' );
		$this->assertSame( 1, $this->child_rows( 'alondra_tiers', $this->tp_b->id ), 'Another group must keep its tier.' );
		$this->assertSame( 1, $this->child_rows( 'alondra_rules', $this->tp_b->id ), 'Another group must keep its rule.' );
	}

	public function test_save_naming_an_unknown_parent_fails_and_writes_no_children() {
		$this->assert_no_foreign_keys();
		$missing = $this->tp_c->id + 1000;

		$item = new TieredPricing(
			$missing,
			'Ghost',
			TieredPricing::MIN_PRIORITY,
			TieredPricing::STATUS_PUBLISH,
			'0000-00-00 00:00:00',
			[ new Tier( 0, 0, 1, 9, true, 5.0 ) ],
			[
				new Rule(
					0,
					0,
					Rule::RELATIONSHIP_ANY,
					Rule::RELATIONSHIP_ANY,
					Rule::RELATIONSHIP_ANY,
					Rule::RELATIONSHIP_OR,
					Rule::RELATIONSHIP_OR,
					[],
					[],
					[ $this->product_none ],
					[],
					[]
				),
			]
		);

		$this->assertEmpty( $this->repo->save( $item ), 'A save naming a parent that does not exist must not report success.' );
		$this->assertSame( 0, $this->child_rows( 'alondra_tiers', $missing ), 'No tier may be written against an id no row carries.' );
		$this->assertSame( 0, $this->child_rows( 'alondra_rules', $missing ), 'No rule may be written against an id no row carries.' );
	}

	public function test_save_still_inserts_and_updates_a_group_that_exists() {
		$this->assert_no_foreign_keys();

		$inserted = $this->save_group( 'E', [ $this->product_none ], [], 1, 9, 5.0 );
		$this->assertGreaterThan( 0, $inserted->id );
		$this->assertSame( 1, $this->child_rows( 'alondra_tiers', $inserted->id ) );
		$this->assertSame( 1, $this->child_rows( 'alondra_rules', $inserted->id ) );

		$inserted->title           = 'E renamed';
		$inserted->tiers[0]->value = 6.0;
		$this->assertInstanceOf( TieredPricing::class, $this->repo->save( $inserted ), 'An update naming a parent that exists must still succeed.' );

		$stored = $this->repo->find( $inserted->id, true );
		$this->assertCount( 1, $stored );
		$this->assertSame( 'E renamed', $stored[0]->title );
		$this->assertSame( 6.0, $stored[0]->tiers[0]->value );
		$this->assertCount( 1, $stored[0]->rules );
	}

	/**
	 * The ORDER BY the listing sends for a sort key, read off the paged query.
	 *
	 * @param TieredPricingService $service Service to list through.
	 * @param string               $sort    Sort key.
	 * @param string               $dir     Sort direction.
	 * @return string
	 */
	private function listing_order_by( TieredPricingService $service, string $sort, string $dir ): string {
		$queries = $this->record_group_queries();
		$service->get_paged_results( '', null, 1, 20, $sort, $dir );
		$all   = $queries->getArrayCopy();
		$query = (string) end( $all );
		return trim( substr( $query, (int) strpos( $query, 'ORDER BY' ) ) );
	}

	public function test_the_listing_sorts_free_keys_as_before() {
		global $wpdb;
		$table   = $wpdb->prefix . 'alondra_tiered_pricing';
		$service = new TieredPricingService();

		$this->assertSame( "ORDER BY `$table`.`title` ASC, `$table`.`id` ASC LIMIT 20", $this->listing_order_by( $service, 'title', 'asc' ) );
		$this->assertSame( "ORDER BY `$table`.`id` DESC LIMIT 20", $this->listing_order_by( $service, 'id', 'desc' ) );
		$this->assertSame( "ORDER BY `$table`.`id` ASC LIMIT 20", $this->listing_order_by( $service, 'priority', 'asc' ), 'A key free does not list falls back to id.' );
	}

	public function test_a_subclass_can_sort_the_listing_by_another_column() {
		$service = new class() extends TieredPricingService {
			protected function sortable_columns(): array {
				return [ 'id', 'title', 'priority' ];
			}
		};
		$rule    = fn() => $this->rule_for( [ $this->product_none ] );
		$a       = $this->save_group_with_rule( 'Ranked', $rule(), 5 )->id;
		$b       = $this->save_group_with_rule( 'Ranked', $rule(), 1 )->id;
		$c       = $this->save_group_with_rule( 'Ranked', $rule(), 5 )->id;
		$d       = $this->save_group_with_rule( 'Ranked', $rule(), 1 )->id;
		$ids     = fn( string $dir ) => wp_list_pluck( $service->get_paged_results( 'Ranked', null, 1, 20, 'priority', $dir )['items'], 'id' );

		$this->assertSame( [ $b, $d, $a, $c ], $ids( 'asc' ) );
		$this->assertSame( [ $a, $c, $b, $d ], $ids( 'desc' ), 'Descending flips the priority only; id stays ascending.' );
		$this->assertTrue( $service->is_sortable( 'priority' ) );
	}

	public function test_paging_a_sort_over_tied_titles_returns_every_group_once() {
		$expected = [];
		for ( $i = 0; $i < 8; $i++ ) {
			$expected[] = $this->save_group( 'Tied', [ $this->product_none ], [], 1, 9, 1.0 + $i )->id;
		}

		$total = \count( $expected );
		$seen  = [];
		for ( $offset = 0; $offset < $total; $offset += 2 ) {
			foreach ( $this->repo->list_matching_substring( 'Tied', TieredPricing::STATUS_PUBLISH, $offset, 2, 'title', 'asc' ) as $item ) {
				$seen[] = $item->id;
			}
		}

		sort( $seen );
		sort( $expected );
		$this->assertSame( $expected, $seen, 'Walking every page of a sort over tied titles must yield each group exactly once.' );

		// Whether tied rows come back in a stable order is the server's business, and
		// on some engines they happen to. The plugin's own guarantee is the total order
		// it asks for, so assert the clause rather than trusting the engine.
		global $wpdb;
		$this->assertMatchesRegularExpression(
			'/ORDER BY .+`title` ASC, .+`id` ASC/',
			$wpdb->last_query,
			'A sort over a column that repeats must carry `id` as its last key.'
		);
	}

	public function test_a_cache_key_context_scopes_the_matching_keys_only() {
		$plain = $this->keys_of_a_fresh_repo();
		add_filter( 'alondra_cache_key_context', fn() => 'p2.0.0' );
		$scoped = $this->keys_of_a_fresh_repo();

		$this->assertNotSame( $plain['one'], $scoped['one'], 'The context must scope the find_matching() key.' );
		$this->assertNotSame( $plain['all'], $scoped['all'], 'The context must scope the find_all_matching() key.' );
		$this->assertSame( $plain['entity'], $scoped['entity'], 'The context must leave the entity key alone.' );
	}

	public function test_without_a_context_or_line_the_matching_keys_are_unchanged() {
		$repo = new TieredPricingRepo();
		$repo->find_matching( $this->product_a, 0, 1, [] );
		$repo->find_all_matching( $this->product_a, 0, [] );

		$this->assert_key_is_warm( $this->cache_key_one( $this->product_a, 0, 1 ) );
		$this->assert_key_is_warm( $this->cache_key_all( $this->product_a, 0 ) );
	}

	public function test_different_lines_do_not_collide() {
		$repo  = new TieredPricingRepo();
		$empty = $this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1, [] );
		$one   = $this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1, [ 'bundled_by' => 'abc' ] );
		$two   = $this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1, [ 'bundled_by' => 'xyz' ] );
		$all   = $this->repo_key( $repo, 'cache_key_all', $this->product_a, 0, [ 'bundled_by' => 'abc' ] );

		$this->assertNotSame( $empty, $one, 'A line must scope the key.' );
		$this->assertNotSame( $one, $two, 'Two lines must not share a key.' );
		$this->assertNotSame( $this->repo_key( $repo, 'cache_key_all', $this->product_a, 0 ), $all, 'A line must scope the list key.' );
		$this->assertSame( $one, $this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1, [ 'bundled_by' => 'abc' ] ), 'The same line must find its key again.' );
	}

	/**
	 * Lines JSON cannot encode still get keys of their own.
	 */
	public function test_lines_json_cannot_encode_do_not_collide() {
		$repo = new TieredPricingRepo();
		$deep = fn( $leaf ) => array_reduce( range( 1, 600 ), fn( $carry ) => [ $carry ], $leaf );

		$this->assertNotSame(
			$this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1, [ 'x' => NAN ] ),
			$this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1, [ 'x' => INF ] )
		);
		$this->assertNotSame(
			$this->repo_key( $repo, 'cache_key_all', $this->product_a, 0, [ 'x' => $deep( 1 ) ] ),
			$this->repo_key( $repo, 'cache_key_all', $this->product_a, 0, [ 'x' => $deep( 2 ) ] )
		);
	}

	public function test_the_same_line_in_another_key_order_shares_a_key() {
		$repo = new TieredPricingRepo();
		$this->assertSame(
			$this->repo_key(
				$repo,
				'cache_key_one',
				$this->product_a,
				0,
				1,
				[
					'a' => 1,
					'b' => 2,
				] 
			),
			$this->repo_key(
				$repo,
				'cache_key_one',
				$this->product_a,
				0,
				1,
				[
					'b' => 2,
					'a' => 1,
				] 
			)
		);
	}

	public function test_a_non_string_context_is_ignored() {
		$plain = $this->keys_of_a_fresh_repo();
		add_filter( 'alondra_cache_key_context', fn() => [ 'p1' ] );

		$this->assertSame( $plain, $this->keys_of_a_fresh_repo() );
	}

	public function test_a_dirty_context_keeps_only_safe_characters() {
		$plain = $this->keys_of_a_fresh_repo();
		add_filter( 'alondra_cache_key_context', fn() => 'p 1.0/x_<y>' );

		$this->assertSame( $plain['one'] . '_cp1.0xy', $this->keys_of_a_fresh_repo()['one'] );
	}

	public function test_the_cache_key_context_is_read_once_per_blog() {
		global $blog_id;

		$calls = 0;
		add_filter(
			'alondra_cache_key_context',
			function () use ( &$calls ) {
				++$calls;
				return 'p1';
			}
		);

		$repo = new TieredPricingRepo();
		$this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1 );
		$this->repo_key( $repo, 'cache_key_all', $this->product_a, 0 );
		$this->assertSame( 1, $calls, 'The filter must run once per blog per request.' );

		$home = $blog_id;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing in for switch_to_blog(); restored below.
		$blog_id = $home + 1;
		$this->repo_key( $repo, 'cache_key_one', $this->product_a, 0, 1 );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Undoing the switch above.
		$blog_id = $home;

		$this->assertSame( 2, $calls, 'Another blog must read the filter again.' );
	}

	public function test_without_listeners_the_lookups_run_the_queries_they_ran_before_the_filters() {
		// Measured on the code before the matching filters existed.
		$this->assertSame( [ 7, 3, 4, 3, 3, 3, 2, 3 ], $this->queries_per_lookup() );
	}

	public function test_without_listeners_candidates_are_ordered_by_id_only() {
		$queries = $this->record_group_queries();
		$this->repo->find_matching( $this->product_a, 0, 1 );

		$this->assertCount( 1, $queries );
		$this->assertMatchesRegularExpression( '/ORDER BY `[^`]+`\.`id` ASC\s*$/', trim( $queries[0] ) );
	}

	public function test_matching_args_can_narrow_the_candidates() {
		$cat = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		wp_set_object_terms( $this->product_a, [ $cat ], 'product_cat' );
		$by_category = $this->save_group_with_rule( 'By category', $this->rule_for( [], [ $cat ] ) );
		$this->assertSame( [ $this->tp_a->id, $by_category->id ], wp_list_pluck( $this->repo->find_all_matching( $this->product_a, 0 ), 'id' ) );

		add_filter(
			'alondra_matching_args',
			function ( $args ) {
				unset( $args['cat_id'] );
				return $args;
			}
		);
		add_filter( 'alondra_cache_key_context', fn() => 'no-categories' );

		$this->assertSame( [ $this->tp_a->id ], wp_list_pluck( ( new TieredPricingRepo() )->find_all_matching( $this->product_a, 0 ), 'id' ), 'A group selected only by the dropped key must not be a candidate.' );
	}

	public function test_matching_args_can_widen_the_candidates() {
		add_filter(
			'alondra_matching_args',
			function ( $args ) {
				$args['product_id'][] = $this->product_a;
				return $args;
			}
		);
		$candidates = [];
		add_filter(
			'alondra_tiered_pricing_matches',
			function ( $matches, $item ) use ( &$candidates ) {
				$candidates[] = $item->id;
				return true;
			},
			10,
			2
		);

		$item = $this->repo->find_matching( $this->product_none, 0, 1 );

		$this->assertSame( [ $this->tp_a->id ], $candidates, 'The widened args must select a group free alone would not.' );
		$this->assertInstanceOf( TieredPricing::class, $item );
		$this->assertSame( $this->tp_a->id, $item->id );
	}

	public function test_matching_args_the_listener_receives() {
		$received = [];
		add_filter(
			'alondra_matching_args',
			function ( ...$params ) use ( &$received ) {
				$received = $params;
				return $params[0];
			},
			10,
			4
		);

		$this->repo->find_matching( $this->product_c, $this->user_a, 1, [ 'bundled_by' => 'abc' ] );

		$this->assertSame(
			[
				[
					'product_id' => [ $this->product_c ],
					'user_id'    => [ $this->user_a ],
					'role'       => [ 'subscriber' ],
				],
				$this->product_c,
				$this->user_a,
				[ 'bundled_by' => 'abc' ],
			],
			$received
		);
	}

	public function test_unknown_matching_args_keys_are_dropped() {
		add_filter(
			'alondra_matching_args',
			fn( $args ) => $args + [
				'title'              => [ 'x' ],
				'id` = 0 OR 1=1 -- ' => [ 'x' ],
				0                    => [ 'x' ],
				'tiered_pricing_id'  => [ 1 ],
			]
		);
		$queries = $this->record_group_queries();

		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
		$this->assertStringNotContainsString( 'title', $queries[0] );
		$this->assertStringNotContainsString( '1=1', $queries[0] );
		$this->assertStringNotContainsString( 'tiered_pricing_id` LIKE', $queries[0] );
	}

	public function test_non_array_matching_args_are_ignored() {
		add_filter( 'alondra_matching_args', fn() => 'product_id' );

		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
	}

	public function test_an_invalid_matching_args_value_falls_back_to_free_value() {
		add_filter(
			'alondra_matching_args',
			fn() => [
				'product_id' => (string) $this->product_b,
				'cat_id'     => [ [ 1 ] ],
			]
		);

		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ), 'Free must keep its own product ids.' );
	}

	public function test_an_empty_matching_args_list_falls_back_to_free_value() {
		global $wpdb;
		$queries = $this->record_group_queries();
		$free    = wp_list_pluck( ( new TieredPricingRepo() )->find_all_matching( $this->product_a, 0 ), 'id' );
		$first   = $queries->getArrayCopy();
		$this->assertNotEmpty( $first );

		add_filter(
			'alondra_matching_args',
			function ( $args ) {
				$args['product_id'] = [];
				return $args;
			}
		);
		add_filter( 'alondra_cache_key_context', fn() => 'empty-list' );

		$this->assertSame( $free, wp_list_pluck( ( new TieredPricingRepo() )->find_all_matching( $this->product_a, 0 ), 'id' ) );
		$this->assertSame( '', $wpdb->last_error );
		$this->assertSame( $first, \array_slice( $queries->getArrayCopy(), \count( $first ) ) );
	}

	public function test_a_match_on_empty_lists_only_adds_no_clause() {
		global $wpdb;
		$mapper = Container::instance()->get( TieredPricingDatamapper::class );

		$this->assertSame( $mapper->count_all_matching(), $mapper->count_all_matching( [ 'product_id' => [] ] ) );
		$this->assertSame( '', $wpdb->last_error );
	}

	public function test_matching_order_columns_can_flip_the_winner() {
		$first  = $this->save_group_with_rule( 'First', $this->rule_for( [ $this->product_none ] ), 5 );
		$second = $this->save_group_with_rule( 'Second', $this->rule_for( [ $this->product_none ] ), 1 );
		$this->assertSame( $first->id, $this->matching_id( $this->product_none, 0, 1 ), 'Without a listener the lower id wins.' );

		add_filter( 'alondra_matching_order_columns', fn() => [ 'priority' ] );
		$repo = new TieredPricingRepo();
		add_filter( 'alondra_cache_key_context', fn() => 'priority' );

		$this->assertSame( $second->id, $repo->find_matching( $this->product_none, 0, 1 )->id );
		$this->assertSame( [ $second->id, $first->id ], wp_list_pluck( $repo->find_all_matching( $this->product_none, 0 ), 'id' ) );
	}

	public function test_id_is_appended_to_the_order_columns_once() {
		add_filter( 'alondra_matching_order_columns', fn() => [ 'id', 'priority', 'priority', 'id' ] );
		$queries = $this->record_group_queries();
		$this->repo->find_matching( $this->product_a, 0, 1 );

		$this->assertMatchesRegularExpression( '/ORDER BY `[^`]+`\.`priority` ASC, `[^`]+`\.`id` ASC\s*$/', trim( $queries[0] ) );
	}

	/**
	 * @dataProvider invalid_order_columns
	 *
	 * @param mixed $columns Filter output.
	 */
	public function test_invalid_order_columns_fall_back_to_id_order( $columns ) {
		add_filter( 'alondra_matching_order_columns', fn() => $columns );
		$queries = $this->record_group_queries();

		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ) );
		$this->assertMatchesRegularExpression( '/ORDER BY `[^`]+`\.`id` ASC\s*$/', trim( $queries[0] ) );
	}

	/**
	 * Order column outputs free must not trust.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function invalid_order_columns() {
		return [
			'non-array'      => [ 'priority' ],
			'unknown column' => [ [ 'product_id' ] ],
			'injection'      => [ [ 'priority` DESC, (SELECT 1) -- ' ] ],
			'non-string'     => [ [ [ 'priority' ], 1 ] ],
		];
	}

	public function test_matches_can_reject_a_group_free_found_fulfilled() {
		$later = $this->save_group( 'A2', [ $this->product_a ], [], 1, 9, 9.0 );
		add_filter( 'alondra_tiered_pricing_matches', fn( $matches, $item ) => $item->id !== $this->tp_a->id && $matches, 10, 2 );

		$this->assertSame( $later->id, $this->matching_id( $this->product_a, 0, 1 ), 'The next group must win.' );
		$this->assertSame( [ $later->id ], wp_list_pluck( $this->repo->find_all_matching( $this->product_a, 0 ), 'id' ) );
	}

	public function test_matches_rejecting_every_group_finds_none() {
		add_filter( 'alondra_tiered_pricing_matches', '__return_false' );

		$this->assertNull( $this->repo->find_matching( $this->product_a, 0, 1 ) );
		$this->assertSame( [], $this->repo->find_all_matching( $this->product_a, 0 ) );
	}

	public function test_matches_can_accept_a_group_free_found_unfulfilled() {
		// Group C applies to user A only.
		add_filter( 'alondra_tiered_pricing_matches', '__return_true' );

		$item = $this->repo->find_matching( $this->product_c, $this->user_b, 1 );
		$this->assertInstanceOf( TieredPricing::class, $item );
		$this->assertSame( $this->tp_c->id, $item->id );
		$this->assertCount( 1, $item->tiers, 'An accepted group must carry its tiers.' );
	}

	public function test_an_accepted_group_still_needs_a_tier_for_the_quantity() {
		add_filter( 'alondra_tiered_pricing_matches', '__return_true' );

		$this->assertNull( $this->repo->find_matching( $this->product_a, 0, 50 ) );
	}

	public function test_matches_receives_quantity_and_line_in_its_facts() {
		$facts = [];
		add_filter(
			'alondra_tiered_pricing_matches',
			function ( $matches, $item, $received ) use ( &$facts ) {
				$facts[] = $received;
				return $matches;
			},
			10,
			3
		);
		$line = [ 'bundled_by' => 'abc' ];

		$this->repo->find_matching( $this->product_c, $this->user_a, 3, $line );
		$this->repo->find_all_matching( $this->product_c, $this->user_a, $line );

		$expected = [
			'product_id'     => $this->product_c,
			'user_id'        => $this->user_a,
			'quantity'       => 3,
			'line'           => $line,
			'roles'          => [ 'subscriber' ],
			'categories_ids' => [],
			'tags_ids'       => [],
		];
		$this->assertSame( [ $expected, array_merge( $expected, [ 'quantity' => null ] ) ], $facts );
	}

	/**
	 * @dataProvider non_bool_matches
	 *
	 * @param mixed $output Filter output.
	 */
	public function test_a_non_bool_match_falls_back_to_free_result( $output ) {
		add_filter( 'alondra_tiered_pricing_matches', fn() => $output );

		$this->assertSame( $this->tp_a->id, $this->matching_id( $this->product_a, 0, 1 ), 'A fulfilled group must stay fulfilled.' );
		$this->assertNull( $this->repo->find_matching( $this->product_c, $this->user_b, 1 ), 'An unfulfilled group must stay unfulfilled.' );
	}

	/**
	 * Match outputs free must not trust.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function non_bool_matches() {
		return [
			'null'   => [ null ],
			'int'    => [ 0 ],
			'string' => [ 'yes' ],
			'array'  => [ [ true ] ],
		];
	}

	public function test_matches_is_not_called_on_the_cached_path() {
		$calls = 0;
		add_filter(
			'alondra_tiered_pricing_matches',
			function ( $matches ) use ( &$calls ) {
				++$calls;
				return $matches;
			}
		);

		$this->repo->find_matching( $this->product_a, 0, 1 );
		$this->repo->find_all_matching( $this->product_a, 0 );
		$this->assertSame( 2, $calls );

		$this->repo->find_matching( $this->product_a, 0, 1 );
		$this->repo->find_all_matching( $this->product_a, 0 );
		$this->assertSame( 2, $calls, 'A cached answer must not run the filter again.' );
	}

	public function test_tiers_a_listener_set_are_not_queried_again() {
		$own = new Tier( 0, $this->tp_c->id, 1, Tier::MAX_UNITS, true, 1.5 );
		add_filter(
			'alondra_tiered_pricing_matches',
			function ( $matches, $item ) use ( $own ) {
				$item->tiers = [ $own ];
				return true;
			},
			10,
			2
		);

		$item = $this->repo->find_matching( $this->product_c, $this->user_b, 1 );

		$this->assertInstanceOf( TieredPricing::class, $item );
		$this->assertSame( 1.5, $item->tiers[0]->value, 'Tiers already set must not be replaced by the stored ones.' );
	}

	public function test_a_listener_decides_between_a_product_group_and_a_category_group() {
		$cat = self::factory()->term->create( [ 'taxonomy' => 'product_cat' ] );
		wp_set_object_terms( $this->product_none, [ $cat ], 'product_cat' );
		$by_product  = $this->save_group_with_rule( 'By product', $this->rule_for( [ $this->product_none ] ), 5 );
		$by_category = $this->save_group_with_rule( 'By category', $this->rule_for( [], [ $cat ] ), 1 );

		$this->assertSame( $by_product->id, $this->matching_id( $this->product_none, 0, 1 ), 'Free picks the lower id.' );
		$this->assertSame( [ $by_product->id, $by_category->id ], wp_list_pluck( $this->repo->find_all_matching( $this->product_none, 0 ), 'id' ), 'Free keeps both under OR.' );

		add_filter( 'alondra_matching_order_columns', fn() => [ 'priority' ] );
		add_filter( 'alondra_cache_key_context', fn() => 'priority' );

		$this->assertSame( $by_category->id, ( new TieredPricingRepo() )->find_matching( $this->product_none, 0, 1 )->id, 'The listener ordering must pick the category group.' );
	}

	public function test_a_listener_decides_between_a_product_group_and_a_role_group() {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$by_product = $this->save_group_with_rule( 'By product', $this->rule_for( [ $this->product_none ] ) );
		$by_role    = $this->save_group_with_rule( 'By role', $this->rule_for( [], [], [ 'subscriber' ] ) );

		$this->assertSame( $by_product->id, $this->matching_id( $this->product_none, $subscriber, 1 ), 'Free picks the lower id.' );

		// Role groups outrank product groups for users holding the role.
		add_filter(
			'alondra_tiered_pricing_matches',
			fn( $matches, $item, $facts ) => $matches && ! ( empty( $item->rules[0]->roles ) && \in_array( 'subscriber', $facts['roles'], true ) ),
			10,
			3
		);
		add_filter( 'alondra_cache_key_context', fn() => 'roles-first' );

		$repo = new TieredPricingRepo();
		$this->assertSame( $by_role->id, $repo->find_matching( $this->product_none, $subscriber, 1 )->id );
		$this->assertSame( $by_product->id, $repo->find_matching( $this->product_none, 0, 1 )->id, 'Without the role the product group still wins.' );
	}
}
