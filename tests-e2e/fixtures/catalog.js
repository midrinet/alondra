/**
 * The seeded demo store, addressed by the ROLE each entry plays in the suite
 * rather than by what the product happens to be called.
 *
 * Only values the specs actually read live here. Tier values, preferences and
 * discount labels the plugin computes are plugin behavior, not catalog data,
 * and stay in the spec that asserts them.
 *
 * Every id, title, SKU and term below is created by
 * dev/helper/demo-store/seed.php, which pins the post ids: that seeder is
 * the source of truth, so a value changed there has to be changed here too.
 *
 * The seeder also describes five bundle packs at ids 1011-1015. They need
 * WooCommerce Product Bundles, which this plugin neither supports nor
 * activates, so they are never created and nothing here refers to them.
 */

/**
 * The suite's anchor: a product carrying a native WooCommerce sale, so a tier
 * stacked on it exercises the regular-vs-sale reference. Belongs to
 * CATEGORIES.onSale and to no other category, and carries no tag.
 */
export const ON_SALE = Object.freeze({
    id: 1001,
    slug: 'cordillera-reserve-kilo',
    name: 'Cordillera Reserve Kilo',
    // One lowercase word, interpolated raw into a REST ?search= by 001 and by
    // the rule form. It has to match this product and NOTHING else:
    // ProductRepo::get_products() matches title, id and SKU, caps the
    // result at five rows and carries no ORDER BY, so a term matching more
    // than one row can silently drop the target rather than rank it second.
    // No other seeded title or SKU contains "cordillera".
    search: 'cordillera',
    regularPrice: 65,
    salePrice: 55
});

/**
 * A plainly priced product with no native sale, so a figure read back off the
 * screen is exact arithmetic. Used as a rule target, and searched by its id
 * so the repository's `ID = %d` branch is exercised: no seeded title or SKU
 * contains "1002" either.
 */
export const CHILD_A = Object.freeze({
    id: 1002,
    name: 'Yirgacheffe Filter Roast',
    price: 25
});

/** A second one, so a rule can carry two product targets. */
export const CHILD_B = Object.freeze({
    id: 1003,
    name: 'Sumatra Nightfall Roast',
    price: 20
});

/**
 * A product variation, found in the rule form by its SKU - which is also the
 * only seeded SKU containing "CSC-KG" (CSC-000 and CSC-250 do not), and the
 * reason the search reaches `post_type = product_variation` at all.
 */
export const VARIATION = Object.freeze({
    id: 1006,
    sku: 'CSC-KG'
});

/**
 * The only seeded product_tag, by display name - the autocomplete matches the
 * rendered label, not the slug. ON_SALE does not carry it.
 */
export const TAG = 'Single Origin';

/**
 * A product seeded only so a test has somewhere to rewrite categories and tags
 * without touching a product the store sells. It is seeded hidden from the
 * catalog and from search, so the shop, the home collection, the site search,
 * the related-products block and the category counts all pass it over; hidden
 * still leaves it reachable at its permalink and purchasable.
 *
 * Nothing in this suite reads it yet. It is kept so a test that rewrites
 * terms has a safe target instead of reaching for a sold product.
 */
export const TERM_FIXTURE = Object.freeze({
    id: 1016,
    slug: 'term-matching-fixture'
});

export const CATEGORIES = Object.freeze({
    /**
     * The only category ON_SALE belongs to, and the one TERM_FIXTURE is seeded
     * into. TERM_FIXTURE is hidden, so ON_SALE stays its only visible member.
     */
    onSale: 'Wholesale',
    /** A real category ON_SALE does not belong to, so a match and a miss differ. */
    unmatched: 'Equipment',
    /**
     * A parent category and one of its children, used only to fill the rule
     * form. Deliberately the same term as `unmatched`: nothing needs them
     * distinct, and one category fewer is one substring collision fewer.
     *
     * The autocomplete requests `_fields=id,name`, so an option and a selected
     * chip are both labelled with the bare term name - "Grinders", never
     * "Equipment > Grinders". Without that, matching a chip by text would
     * resolve two elements and fail strict mode.
     */
    parent: 'Equipment',
    sub: 'Grinders'
});

/**
 * A WordPress user other than the admin the suite logs in as, by display name.
 * The store has exactly two users: the sample-data import that used to create
 * more is gone with the sample catalog.
 */
export const OTHER_USER = 'Marta Ruiz';

/** Role labels the rule form can pick, used only to fill the profiles field. */
export const PROFILES = Object.freeze(['Author', 'Shop Manager']);

/**
 * The two tiers the helper's sample tiered pricing seeds, and where the second
 * range opens. No spec here asserts them yet - the specs that call
 * `createSampleTieredPricing()` only ever change its status - but they are the
 * figures to assert against if one starts to.
 */
export const SAMPLE_TIERS = Object.freeze({
    first: 50,
    second: 60,
    secondFrom: 11
});
