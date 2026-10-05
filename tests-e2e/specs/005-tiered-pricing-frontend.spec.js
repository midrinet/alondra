import { test, expect } from '../fixtures/test';
import { ON_SALE, CHILD_A, CATEGORIES, OTHER_USER } from '../fixtures/catalog';

/**
 * Frontend price-application tests for Tiered Pricing. Complements
 * 001-tiered-pricing-admin.spec.js: these tests assert the actual price shown
 * at the product/cart page, not just that a form field round-trips.
 */

/**
 * Publish a tiered pricing (fixed value 15) with the given title and rules,
 * buy the on-sale product, and return the resulting cart unit price. Shared by
 * every test below - only the rule configuration under test differs between them.
 */
async function publishRuleAndGetPrice({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }, title, rules) {
    await tieredPricingPage.helper.clearDataAndEmptyCart();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();
    await tieredPricingPage.fill({
        ...tieredPricingPage.formData,
        title,
        pricing: [{ minUnits: 1, maxUnits: null, value: 15 }],
        rules,
    });
    await tieredPricingPage.publish();

    await productPage.addToCart({ slug: ON_SALE.slug, quantity: 1 });
    await cartPage.goto();
    return cartPage.getItemPrice();
}

test.describe('Tiered Pricing - Frontend Price Application', () => {

    test('A fixed tier discounts the product and cart price', async ({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }) => {
        await tieredPricingPage.wpAdmin.login({ force: false });

        const price = await publishRuleAndGetPrice(
            { tieredPricingListingPage, tieredPricingPage, productPage, cartPage },
            'Fixed Tier',
            tieredPricingPage.formData.rules
        );
        expect(price).toBeCloseTo(15, 2);
        console.log('✅ Fixed tier applied its price in the cart');
    });

    /**
     * Two rules on the same tiered pricing are OR'd together (see
     * TieredPricing::is_fulfilled()): a product matching only the second rule
     * still gets the discount. Every prior multi-rule test only checked form
     * persistence, never that a real product falls through to the second rule.
     */
    test('A product matching only the second rule still gets the discount', async ({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }) => {
        await tieredPricingPage.wpAdmin.login({ force: false });

        const price = await publishRuleAndGetPrice(
            { tieredPricingListingPage, tieredPricingPage, productPage, cartPage },
            'Multi-rule OR',
            [
                { ...tieredPricingPage.formData.rules[0], products: [{ text: CHILD_A.name, id: CHILD_A.id }] },
                { ...tieredPricingPage.formData.rules[0], products: [{ text: ON_SALE.search, id: ON_SALE.id }] },
            ]
        );
        expect(price).toBeCloseTo(15, 2);
        console.log('✅ Product matched only the second rule and still got the discount');
    });

    /**
     * A rule matches only when the logged-in user is in the rule's users list.
     */
    test('A rule scoped to a specific user only matches that user', async ({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }) => {
        await tieredPricingPage.helper.clearData();
        await tieredPricingPage.wpAdmin.login({ force: false });

        await productPage.goto({ slug: ON_SALE.slug });
        // getBasePrice() reads the ACTIVE price, so on this product it is the sale
        // price, not the regular one - which is also what the cart charges when no
        // tier matches, so the two sides of the comparison agree.
        const basePrice = await productPage.getBasePrice();

        const pages = { tieredPricingListingPage, tieredPricingPage, productPage, cartPage };
        const publishWithUsers = users => publishRuleAndGetPrice(
            pages,
            `Users ${users.join('-')}`,
            [{ ...tieredPricingPage.formData.rules[0], products: [], users }]
        );

        // Rule includes admin -> matches.
        expect(await publishWithUsers(['admin'])).toBeCloseTo(15, 2);

        // Rule excludes admin (targets a different user) -> does not match.
        expect(await publishWithUsers([OTHER_USER])).toBeCloseTo(basePrice, 2);

        console.log('✅ Rule scoped to a specific user matched only that user');
    });

    /**
     * Category matching end to end: a rule listing categories matches a product
     * that is in any one of them, and does not match otherwise. The on-sale
     * product is in CATEGORIES.onSale and in nothing else, so CATEGORIES.unmatched
     * is the miss. Neither test rewrites the product's terms: they vary the rule,
     * not the catalog, so nothing here can corrupt the store for a later run.
     */
    test('A rule scoped to categories matches a product in any of them', async ({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }) => {
        await tieredPricingPage.helper.clearData();
        await tieredPricingPage.wpAdmin.login({ force: false });

        await productPage.goto({ slug: ON_SALE.slug });
        const basePrice = await productPage.getBasePrice();

        const pages = { tieredPricingListingPage, tieredPricingPage, productPage, cartPage };
        const publishWithCategories = categories => publishRuleAndGetPrice(
            pages,
            `Categories ${categories.join('-')}`,
            [{ ...tieredPricingPage.formData.rules[0], products: [], categories }]
        );

        // The product is in one of the two listed categories -> matches.
        expect(await publishWithCategories([CATEGORIES.onSale, CATEGORIES.unmatched])).toBeCloseTo(15, 2);

        // The product is in neither listed category -> its own price stands.
        expect(await publishWithCategories([CATEGORIES.unmatched])).toBeCloseTo(basePrice, 2);

        console.log('✅ Rule scoped to categories matched a product in any of them');
    });

    /**
     * The tier row's percent label sits beside a strikethrough of the REGULAR price, so
     * it has to be measured against that same reference. The on-sale product seeds at
     * regular 65 / sale 55 and a fixed tier of 60 lands between the two, which is where
     * measuring against the active price turns the label negative.
     */
    test('The discount label is measured against the regular price, not a native sale price', async ({ tieredPricingListingPage, tieredPricingPage, productPage }) => {
        await tieredPricingPage.helper.clearData();
        await tieredPricingPage.wpAdmin.login({ force: false });

        await tieredPricingListingPage.goto();
        await tieredPricingListingPage.addNew();
        await tieredPricingPage.fill({
            ...tieredPricingPage.formData,
            title: 'Sale Stacked Tier',
            pricing: [{ minUnits: 1, maxUnits: null, value: 60 }],
        });
        await tieredPricingPage.publish();

        await productPage.goto({ slug: ON_SALE.slug });

        expect(
            await productPage.getTierStrikethroughPrice(),
            `The tier is struck through against the regular price, not the ${ON_SALE.salePrice} sale price`
        ).toBeCloseTo(ON_SALE.regularPrice, 2);

        expect(
            await productPage.getTierDiscountPercent(),
            `A negative percentage means the label was measured against the ${ON_SALE.salePrice} sale price instead of the ${ON_SALE.regularPrice} strikethrough it sits next to`
        ).toBe(7);

        console.log('✅ Discount label measured against the regular price');
    });

});
