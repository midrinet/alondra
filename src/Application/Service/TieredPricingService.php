<?php
/**
 * The Tiered Prices Service
 *
 * @package    Midrinet/Alondra
 * @subpackage Midrinet/Alondra/Application/Service
 */

namespace Midrinet\Alondra\Application\Service;

use Midrinet\Alondra\Application\Dto\CategoryDto;
use Midrinet\Alondra\Application\Dto\ProductDto;
use Midrinet\Alondra\Application\Dto\RuleDto;
use Midrinet\Alondra\Application\Dto\SimpleTierDto;
use Midrinet\Alondra\Application\Dto\TagDto;
use Midrinet\Alondra\Application\Dto\TierDto;
use Midrinet\Alondra\Application\Dto\TieredPricingDto;
use Midrinet\Alondra\Application\Dto\UserDto;
use Midrinet\Alondra\Domain\Entity\ProductResult;
use Midrinet\Alondra\Domain\Entity\Tier;
use Midrinet\Alondra\Domain\Entity\TieredPricing;
use Midrinet\Alondra\Domain\Repository\ProductRepo;
use Midrinet\Alondra\Domain\Repository\TieredPricingRepo;
use Midrinet\Alondra\Infrastructure\DI\Container;
use Midrinet\Alondra\Infrastructure\Migration\MigrationRunner;

/**
 * The Tiered Prices Service
 *
 * @since      1.0.0
 */
class TieredPricingService {

	private ?TieredPricingRepo $tiered_pricing_repo = null;

	private function get_preferences(): PreferencesService {
		return Container::instance()->get( PreferencesService::class );
	}

	/**
	 * Whether the schema this service reads and writes has been migrated
	 *
	 * The read paths below query tables no code creates until migration CREATE_TABLES has run, and nothing
	 * stops that migration failing -- a host without CREATE rights, a fatal part-way through. Ungated, every
	 * one of them would then put a database error on a customer's product page or in the rules screen. So each
	 * one asks this first and takes the path it already has for "nothing matched", which leaves the plugin
	 * invisible rather than broken.
	 *
	 * The gate is the LATEST migration, not the first. ADD_BUNDLE_PRODUCT_COLUMN's `bundle_product` sits in
	 * RuleDatamapper's universal column lists -- every rule save writes it and every rule read selects it -- so
	 * an install stalled between the two migrations would lose saving: the insert errors, the repo's
	 * `if ( $saved )` guard swallows it, and the merchant's rules disappear behind a 200. is_applied() compares
	 * against a cursor, so asking for the highest id asks for every id below it too.
	 *
	 * Cheap enough for a hook that fires several times per rendered price: two array lookups in the container
	 * plus is_applied()'s comparison against a cursor PreferencesService memoises per request.
	 */
	private function schema_ready(): bool {
		return Container::instance()->get( MigrationRunner::class )->is_applied( MigrationRunner::ADD_BUNDLE_PRODUCT_COLUMN );
	}

	private function get_tiered_pricing_repo(): TieredPricingRepo {
		if ( null === $this->tiered_pricing_repo ) {
			$this->tiered_pricing_repo = Container::instance()->get( TieredPricingRepo::class );
		}
		return $this->tiered_pricing_repo;
	}

	private function get_product_repo(): ProductRepo {
		return Container::instance()->get( ProductRepo::class );
	}

	/**
	 * Group table columns the listing may be sorted by.
	 *
	 * @return string[]
	 */
	protected function sortable_columns(): array {
		return [ 'id', 'title' ];
	}

	public function is_sortable( string $column ): bool {
		return \in_array( $column, $this->sortable_columns(), true );
	}

	/**
	 * Get Tiered Pricing results matching parameters
	 *
	 * @param string $search Substring to search for.
	 * @param string|null $status Status to filter by.
	 * @param int    $page Page number. Default 1.
	 * @param int    $page_size Page size. Default 20.
	 * @param string $sort Sort column, one of sortable_columns(); anything else sorts by id.
	 * @param string $sort_dir Sort direction. Either 'asc' or 'desc'.
	 *
	 * @return array{count: int, items: TieredPricing[]}
	 */
	public function get_paged_results( $search, $status, $page, $page_size, $sort, $sort_dir ) {
		$sort_dir = strtolower( $sort_dir );
		if ( ! \in_array( $sort_dir, [ 'asc', 'desc' ], true ) ) {
			$sort_dir = 'asc';
		}
		$sort = strtolower( $sort );
		if ( ! $this->is_sortable( $sort ) ) {
			$sort = 'id';
		}
		$page_size = (int) $page_size;
		if ( $page_size < 1 ) {
			$page_size = 20;
		}
		$page = (int) $page;
		if ( $page < 1 ) {
			$page = 1;
		}

		$offset = ( $page - 1 ) * $page_size;

		$total_matching = $this->count_total_results( $search, $status );

		$result = empty( $total_matching ) ? [] : $this->get_tiered_pricing_repo()->list_matching_substring( $search, $status ?? '', $offset, $page_size, $sort, $sort_dir );

		return [
			'count' => $total_matching,
			'items' => $result,
		];
	}

	/**
	 * Count Tiered Pricing results matching parameters
	 *
	 * @param string $search Substring to search for.
	 * @param string $status Status to filter by.
	 *
	 * @return int
	 */
	public function count_total_results( $search, $status = null ) {
		// The listing screen's only door to the table, so this one guard empties it: the list-table adapter
		// asks for a count per status view, and get_paged_results() already skips its own query on a zero
		// count. The REST list endpoint goes through the same pair.
		if ( ! $this->schema_ready() ) {
			return 0;
		}

		return $this->get_tiered_pricing_repo()->count_matching_substring( $search, $status );
	}

	/**
	 * Setup all required markup for data to be stored and retrieved.
	 *
	 * @return void
	 */
	public function setup() {
		$this->get_tiered_pricing_repo()->setup_database();
	}

	/**
	 * Delete one or more Tiered Pricing by ID.
	 *
	 * @param int|int[] $id Tiered Pricing IDs.
	 * @return int|false Number of deleted Tiered Pricing or false if failed.
	 */
	public function delete( $id ) {
		// Reachable with a live id even from an emptied listing: a row-action nonce stays valid for around a
		// day, so an admin whose schema broke with the screen still open can click Trash or Delete out of
		// history. false is what the repo already returns for an id it cannot act on.
		if ( ! $this->schema_ready() ) {
			return false;
		}

		return $this->get_tiered_pricing_repo()->delete( $id );
	}

	/**
	 * Invalidate every cached lookup.
	 *
	 * @return void
	 */
	public function flush_cache() {
		$this->get_tiered_pricing_repo()->flush_cache();
	}

	/**
	 * Set status of one or more Tiered Pricing by ID.
	 *
	 * @param int|int[] $id Tiered Pricing IDs.
	 * @param string    $status Status to set. Use TieredPricing::STATUS_* constants.
	 * @return int|false Number of updated Tiered Pricing or false if failed.
	 */
	public function set_status( $id, $status ) {
		if ( ! \in_array( $status, [ TieredPricing::STATUS_DRAFT, TieredPricing::STATUS_PUBLISH, TieredPricing::STATUS_TRASH ], true ) || empty( $id ) ) {
			return false;
		}

		// Same reachability as delete(), and this one runs a find() and a save() per id. false is what the
		// invalid-argument path above already returns, so every caller handles it.
		if ( ! $this->schema_ready() ) {
			return false;
		}

		$count = 0;
		foreach ( $this->get_tiered_pricing_repo()->find( $id, true ) as $item ) {
			$item->status = $status;
			if ( $this->get_tiered_pricing_repo()->save( $item ) ) {
				++$count;
			}
		}

		return empty( $count ) ? false : $count;
	}

	/**
	 * Get Products matching search term.
	 *
	 * @param string $search Optional. Substring to search for. Is compared against product title, ID and SKU.
	 * @param int[]  $exclude Optional. Array of product IDs to exclude from results.
	 * @param int    $limit Optional. Max number of results. Default 5.
	 * @return ProductResult[]
	 */
	public function get_products( $search = '', $exclude = [], $limit = 5 ) {
		return $this->get_product_repo()->get_products( $search, $exclude, $limit );
	}

	/**
	 * Update or create a new Tiered Pricing.
	 *
	 * @param TieredPricing $entity Tiered Pricing object.
	 * @return TieredPricing|\WP_Error
	 */
	public function save( $entity ) {
		$error = $entity->validate();
		if ( $error ) {
			return $error;
		}

		// The Add form needs no read to render -- an empty entity comes from `new`, not the table -- so it is
		// the one path that survives the read gates, and an ungated INSERT into a missing table would discard
		// everything the user typed without saying so. A WP_Error does say so: both REST handlers already turn
		// one into a 400 the editor surfaces.
		if ( ! $this->schema_ready() ) {
			return new \WP_Error( 'tiered-pricing-create-failed', __( 'Error saving Tiered Pricing.', 'alondra' ) );
		}

		$entity = $this->get_tiered_pricing_repo()->save( $entity );

		if ( ! $entity ) {
			return new \WP_Error( 'tiered-pricing-create-failed', __( 'Error saving Tiered Pricing.', 'alondra' ) );
		}

		return $entity;
	}

	/**
	 * Get Tiered Pricing by ID.
	 *
	 * @param int $id Tiered Pricing ID.
	 * @return TieredPricing|null
	 */
	public function get( $id ) {
		// The edit screen's only door. Both callers already handle null: show_content() sends the request back
		// to the listing, show_titlebar_options() renders nothing.
		if ( ! $this->schema_ready() ) {
			return null;
		}

		$result = $this->get_tiered_pricing_repo()->find( $id, true );
		return ! empty( $result[0] ) ? $result[0] : null;
	}

	/**
	 * Factory method to create a new Tiered Pricing DTO from an entity.
	 *
	 * @param TieredPricing $entity Tiered Pricing object.
	 * @return TieredPricingDto
	 */
	public function make_dto( TieredPricing $entity ) {
		$dto = new TieredPricingDto(
			$entity->id,
			$entity->title,
			$entity->date_updated,
			$entity->status
		);

		foreach ( $entity->tiers as $tier ) {
			$dto->tiers[] = new TierDto(
				$tier->id,
				$tier->min_units,
				Tier::MAX_UNITS === $tier->max_units ? null : $tier->max_units,
				$tier->value
			);
		}

		foreach ( $entity->rules as $rule ) {
			$rule_dto = new RuleDto(
				$rule->id,
				$rule->roles_rel,
				$rule->cats_rel,
				$rule->tags_rel,
				$rule->tags_with_cats_rel,
				$rule->prods_cats_tags_with_roles_users_rel
			);

			foreach ( $this->get_tiered_pricing_repo()->get_rule_tags( $rule ) as $term ) {
				$rule_dto->tags[] = new TagDto( $term->term_id, $term->name );
			}
			foreach ( $this->get_tiered_pricing_repo()->get_rule_categories( $rule ) as $term ) {
				$rule_dto->categories[] = new CategoryDto( $term->term_id, $term->name );
			}
			foreach ( $this->get_tiered_pricing_repo()->get_rule_users( $rule ) as $user ) {
				$rule_dto->users[] = new UserDto( $user->ID, $user->display_name );
			}
			foreach ( $this->get_tiered_pricing_repo()->get_rule_products( $rule ) as $prod ) {
				$rule_dto->products[] = new ProductDto( $prod->ID, $prod->post_title );
			}

			$rule_dto->roles = $rule->roles;

			$dto->rules[] = $rule_dto;
		}

		return $dto;
	}

	/**
	 * Get tiered price value by product, quantity and user.
	 *
	 * @param int   $product_id Product ID.
	 * @param int   $quantity Quantity.
	 * @param int   $user_id User ID.
	 * @param float $price Current product price.
	 * @return float Tiered price value or current price if was not found.
	 */
	public function get_tiered_price( $product_id, $quantity, $user_id, $price ) {
		if ( ! $this->schema_ready() ) {
			return $price;
		}

		$entity = $this->get_tiered_pricing_repo()->find_matching( $product_id, $user_id, $quantity );

		if ( ! empty( $entity ) ) {
			$tier = $entity->get_tier_for_quantity( $quantity );
			if ( null !== $tier ) {
				$price = $this->tier_price( $tier, $price ) ?? $price;
			}
		}

		return $price;
	}

	/**
	 * Price of one tier, the single door the cart and the tier table both go through.
	 *
	 * A tier that is not a fixed price (a percentage stored by another build) is declined unless a
	 * listener prices it. Filter output is untrusted: anything but null or a finite, non-negative
	 * number falls back to that default.
	 *
	 * @param Tier  $tier The tier.
	 * @param float $basis Active product price the tier is computed from.
	 * @return float|null Null when the tier is declined.
	 */
	private function tier_price( Tier $tier, $basis ) {
		$default = $tier->is_fixed ? $tier->value : null;

		/**
		 * Filters the unit price of a tier, in the cart and in the tier table alike.
		 *
		 * Return null to decline the tier: the cart line keeps its active price for that quantity
		 * and the tier table drops the row. The declined range is not filled by a lower group.
		 *
		 * @since 2.0.0
		 *
		 * @param float|null $price Free's price: the tier's fixed value, or null when the tier is not fixed.
		 * @param Tier       $tier  The tier.
		 * @param float      $basis Active product price the tier is computed from.
		 */
		$filtered = apply_filters( 'alondra_tier_price', $default, $tier, (float) $basis );

		if ( null === $filtered ) {
			return null;
		}
		if ( ( \is_int( $filtered ) || \is_float( $filtered ) ) && is_finite( (float) $filtered ) && 0 <= $filtered ) {
			return (float) $filtered;
		}
		return $default;
	}

	/**
	 * Get tiers by product and user.
	 *
	 * @param int        $product_id Product ID.
	 * @param int        $user_id User ID.
	 * @param float      $product_price Current product price.
	 * @param float|null $regular_price Price the tiers are struck through against. Defaults to $product_price.
	 * @return SimpleTierDto[] Tiers.
	 */
	public function get_tiers( $product_id, $user_id, $product_price, $regular_price = null ) {
		// Every other storefront path -- get_price(), get_variable_price() and the controller's tier tables --
		// reads the tables through here, so this is the one guard they all need.
		if ( ! $this->schema_ready() ) {
			return [];
		}


		$entities = $this->get_tiered_pricing_repo()->find_all_matching( $product_id, $user_id );

		if ( empty( $entities ) ) {
			return [];
		}

		$regular_price = ( null === $regular_price || $regular_price <= 0 ) ? $product_price : $regular_price;

		/** @var SimpleTierDto[] $dtos *///phpcs:ignore
		$dtos = [];

		foreach ( $entities as $entity ) {
			usort(
				$entity->tiers,
				function ( $a, $b ) {
					return $a->min_units - $b->min_units;
				}
			);
			foreach ( $entity->tiers as $tier ) {
				$min_units = $tier->min_units;
				$max_units = $tier->max_units;
				// A declined tier still takes its range, so no lower group fills it, as in the cart. It is dropped below.
				$price = $this->tier_price( $tier, $product_price ) ?? -1.0;
				// The label sits next to a strikethrough of the regular price, so it has to use that same
				// reference: deriving it from the active price understates a stacked native sale, and reads
				// negative once the tier lands between the sale and regular prices.
				$percentage = ! $regular_price ? 100 : min( 100, ceil( $price / $regular_price * 100 ) );
				$count      = \count( $dtos );
				if ( ! $count ) {
					$new_dto = new SimpleTierDto( $min_units, $max_units, $price, $regular_price, $percentage );
					$dtos[]  = $new_dto;
					continue;
				}

				$i = 0;
				while ( $i < $count ) {
					$current = $dtos[ $i ];
					$prev    = 0 < $i ? $dtos[ $i - 1 ] : null;
					$next    = $i < $count - 1 ? $dtos[ $i + 1 ] : null;

					// left side.
					if ( $min_units < $current->min_units ) {
						$min = $prev ? max( $min_units, $prev->max_units + 1 ) : $min_units;
						$max = min( $current->min_units - 1, $max_units );
						if ( $min <= $max && $min < $current->min_units ) {
							$new_dto = new SimpleTierDto( $min, $max, $price, $regular_price, $percentage );
							array_splice( $dtos, $i, 1, [ $new_dto, $current ] );
							$count = \count( $dtos );
							++$i;
						}
					}

					// right side.
					if ( $max_units > $current->max_units ) {
						$min = max( $current->max_units + 1, $min_units );
						$max = $next ? min( $max_units, $next->min_units - 1 ) : $max_units;

						if ( $min <= $max && $max > $current->max_units ) {
							$new_dto = new SimpleTierDto( $min, $max, $price, $regular_price, $percentage );
							array_splice( $dtos, $i, 1, [ $current, $new_dto ] );
							$count = \count( $dtos );
							++$i;
						}
					}

					++$i;
				}
			}
		}

		return array_values(
			array_filter(
				$dtos,
				function ( $dto ) {
					return 0 <= $dto->price;
				}
			)
		);
	}

	/**
	 * Get formatted price for product
	 *
	 * @param string      $price The current formatted price.
	 * @param \WC_Product $product The product.
	 * @param int         $user_id The user ID.
	 * @return string Formatted price or range.
	 */
	public function get_price( $price, $product, $user_id ) {
		if ( ! $this->get_preferences()->overwrite_price() ) {
			return $price;
		}

		$product_price = (float) $product->get_price();
		$tiers         = $this->get_tiers( (int) $product->get_id(), $user_id, $product_price );
		if ( empty( $tiers ) ) {
			return $price;
		}
		$this->set_min_max( $tiers, $product_price, $min_price, $max_price );

		return $this->format_price( $min_price, $max_price );
	}

	/**
	 * Get formatted price for product variation
	 *
	 * @param string               $price The current formatted price.
	 * @param \WC_Product_Variable $product The product.
	 * @param int                  $user_id The user id.
	 * @return string Formatted price or range.
	 */
	public function get_variable_price( $price, $product, $user_id ) {

		if ( ! $this->get_preferences()->overwrite_price() ) {
			return $price;
		}

		$variation_prices = $product->get_variation_prices( true );

		if ( empty( $variation_prices['price'] ) ) {
			return $price;
		}
		$min_price       = PHP_FLOAT_MAX;
		$max_price       = PHP_FLOAT_MIN;
		$variation_price = 0.0;

		foreach ( $variation_prices['price'] as $variation_id => $variation_price ) {
			$variation_price = (float) $variation_price;
			$tiers           = $this->get_tiers( (int) $variation_id, $user_id, $variation_price );
			$this->set_min_max( $tiers, $variation_price, $variation_min, $variation_max );

			$min_price = min( $min_price, $variation_min );
			$max_price = max( $max_price, $variation_max );
		}

		return $this->format_price( $min_price, $max_price );
	}

	/**
	 * Get a formatted price or range of prices for a product and its tiers.
	 *
	 * @param float $min_price The min price.
	 * @param float $max_price The max price.
	 * @return string Formatted price or range.
	 */
	protected function format_price( $min_price, $max_price ) {
		return $min_price !== $max_price ? wc_format_price_range( $min_price, $max_price ) : wc_price( $min_price );
	}

	/**
	 * Set min and max price.
	 *
	 * @param SimpleTierDto[] $tiers The tiers.
	 * @param float             $product_price The product price.
	 * @param float             $min_price The min price.
	 * @param float             $max_price The max price.
	 * @return void
	 */
	protected function set_min_max( $tiers, $product_price, &$min_price, &$max_price ) {

		if ( empty( $tiers ) ) {
			$min_price = $product_price;
			$max_price = $product_price;
			return;
		}

		$min_price = PHP_FLOAT_MAX;
		$max_price = PHP_FLOAT_MIN;

		$has_gaps = ! ( current( $tiers )->min_units === 1 && end( $tiers )->max_units === Tier::MAX_UNITS );
		$last_max = null;

		foreach ( $tiers as $tier ) {
			$min_price = min( $min_price, $tier->price );
			$max_price = max( $max_price, $tier->price );

			if ( ! $has_gaps && null !== $last_max && $last_max + 1 !== $tier->min_units ) {
				$has_gaps = true;
			} else {
				$last_max = $tier->max_units;
			}
		}

		if ( $has_gaps ) {
			$original_price = $product_price;
			$min_price      = min( $min_price, $original_price );
			$max_price      = max( $max_price, $original_price );
		}
	}
}
