import { error } from 'console';
import CartPage from '../fixtures/CartPage';
import { test, expect } from '../fixtures/test';
import { ON_SALE, CHILD_A, VARIATION, TAG, CATEGORIES, PROFILES } from '../fixtures/catalog';

test.describe('Tiered Pricing', () => {

  test('Validate form and publish', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    // validate form with required data empty
    const invalidFormData = tieredPricingPage.invalidFormData;
    for (const f of invalidFormData) {
      await tieredPricingPage.fill(f);
      await tieredPricingPage.expectFormInvalid(f.error, 'publish', false);
    }

    // fill with correct data and publish form
    const validFormData = tieredPricingPage.validFormData;
    await tieredPricingPage.fill(validFormData);

    // Publish correct form data
    await tieredPricingPage.publish();
    await page.reload();
    await tieredPricingPage.expectFormDataFilled(validFormData);
    console.log('✅ Form data successfully Published');
  });


  test('Validate form and save as draft/unpublished', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    // validate form with required data empty
    const invalidFormData = [
      // Title is empty. The other fields are filled.
      {
        ...tieredPricingPage.formData,
        title: '',
        error: 'Title is required'
      },
      // Pricing is empty. The other fields are filled.
      {
        ...tieredPricingPage.formData,
        pricing: [],
        error: 'At least one tier is required'
      },
      // Rules is empty. The other fields are filled.
      {
        ...tieredPricingPage.formData,
        rules: [],
        error: 'At least one rule is required'
      },
    ];

    for (const f of invalidFormData) {
      await tieredPricingPage.fill(f);
      await tieredPricingPage.expectFormInvalid(f.error, 'saveAsDraft', false);
    }

    // fill with correct data and Save as draft
    const formData = {
      ...tieredPricingPage.formData,
      title: 'Tiered Price Title',
      pricing: [
        ...tieredPricingPage.formData.pricing,
        {
          ...tieredPricingPage.formData.pricing[0],
          minUnits: 11,
          maxUnits: null,
          value: 60
        }
      ],
      rules: [
        {
          ...tieredPricingPage.formData.rules[0],
          tags: [TAG],
          users: ['admin'],
          profiles: ['Administrator']
        },
        {
          ...tieredPricingPage.formData.rules[0],
          products: [
            { text: `${CHILD_A.id}`, id: CHILD_A.id },
            { text: VARIATION.sku, id: VARIATION.id }
          ],
          categories: [CATEGORIES.parent, CATEGORIES.sub],
          profiles: [...PROFILES]
        }
      ]
    }

    await tieredPricingPage.fill(formData);

    // Save as unpublished the correct form data
    await tieredPricingPage.saveAsDraft(false);
    await page.reload();
    await tieredPricingPage.expectFormDataFilled(formData);
    console.log('✅ Created a tiered price in draft status');
  });

  /**
   * Test: Delete a selected subset of rows (not all) from the Pricing tiers
   * and Rules tables, verifying only the checked rows are removed and the
   * rest survive both immediately and after a publish + reload.
   */
  test('Delete selected rows keeps the rest (pricing and rules tables)', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    const formData = {
      ...tieredPricingPage.formData,
      pricing: [
        { minUnits: 1, maxUnits: 5, value: 10 },
        { minUnits: 6, maxUnits: 10, value: 20 },
        { minUnits: 11, maxUnits: null, value: 30 },
      ],
      rules: [
        { ...tieredPricingPage.formData.rules[0], products: [{ text: ON_SALE.search, id: ON_SALE.id }] },
        { ...tieredPricingPage.formData.rules[0], products: [{ text: `${CHILD_A.id}`, id: CHILD_A.id }] },
        { ...tieredPricingPage.formData.rules[0], products: [{ text: VARIATION.sku, id: VARIATION.id }] },
      ],
    };

    await tieredPricingPage.fill(formData);

    // Delete only the middle pricing row (6-10, value 20).
    await tieredPricingPage.locator.pricing.checkRow(1).check();
    await tieredPricingPage.locator.pricing.delSelectedRowsButton().click(tieredPricingPage.options);
    await expect(tieredPricingPage.locator.pricing.rows()).toHaveCount(2);

    // Delete only the middle rule row (the one searched by product id).
    await tieredPricingPage.locator.rules.checkRow(1).check();
    await tieredPricingPage.locator.rules.delSelectedRowsButton().click(tieredPricingPage.options);
    await expect(tieredPricingPage.locator.rules.rows()).toHaveCount(2);

    const remainingFormData = {
      ...formData,
      pricing: [formData.pricing[0], formData.pricing[2]],
      rules: [formData.rules[0], formData.rules[2]],
    };
    await tieredPricingPage.expectFormDataFilled(remainingFormData);
    console.log('✅ Deleted only the selected pricing and rule rows, the rest survived in place');

    await tieredPricingPage.publish();
    await page.reload();
    await expect(tieredPricingPage.locator.pricing.rows()).toHaveCount(2);
    await expect(tieredPricingPage.locator.rules.rows()).toHaveCount(2);
    await tieredPricingPage.expectFormDataFilled(remainingFormData);
    console.log('✅ Row deletion survived publish and reload');
  });

  /**
   * Test: Edit an already-published tiered pricing and re-save it via the
   * "Save" button (not "Publish"), verifying the changes persist after reload.
   * Every other test only ever creates-then-checks; none edit an existing one.
   */
  test('Edit an existing published tiered pricing', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {
    const helper = tieredPricingListingPage.helper;
    await helper.clearData();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    const initialData = tieredPricingPage.formData;
    await tieredPricingPage.fill(initialData);
    await tieredPricingPage.publish();

    // Reload before editing: right after publish(), the SPA can still be mid
    // post-create refetch, which races with and reverts in-place field edits.
    await page.reload();

    // Change the title, a pricing value and add a category to the existing rule.
    await tieredPricingPage.locator.title().fill('Updated Tiered Price Title');
    await tieredPricingPage.locator.pricing.value(0).fill('75');
    await tieredPricingPage.locator.rules.categories(0).fill(CATEGORIES.parent);
    await tieredPricingPage.locator.rules.categoriesDropdownOpt(0, CATEGORIES.parent).click(tieredPricingPage.options);

    await tieredPricingPage.save();
    await page.reload();

    const updatedData = {
      ...initialData,
      title: 'Updated Tiered Price Title',
      pricing: [{ ...initialData.pricing[0], value: 75 }],
      rules: [{ ...initialData.rules[0], categories: [CATEGORIES.parent] }],
    };
    await tieredPricingPage.expectFormDataFilled(updatedData);
    console.log('✅ Edited an existing published tiered pricing and the changes persisted');
  });

  /**
   * Test: Rule row autocomplete searches append a cache-busting query param
   * so a proxy/CDN keyed on URL alone can't serve a stale response. Covers the
   * plugin's own product search and one WooCommerce core search (categories),
   * confirming the extra param doesn't break it, and asserts that the same
   * search from a second rule row produces a different URL — the property that
   * makes a URL-keyed cache miss. Without the param both URLs are identical.
   */
  test('Rule autocomplete searches include a cache-busting param', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    await tieredPricingPage.locator.rules.addRowButton().click(tieredPricingPage.options);

    // Distinct search strings: TomSelect memoizes searches per control, so a repeated query never reaches the network.
    const productSearch = page.waitForRequest(req => req.url().includes('tiered-pricing/product'));
    await tieredPricingPage.locator.rules.products(0).fill(ON_SALE.search);
    const firstProductUrl = (await productSearch).url();
    expect(firstProductUrl).toMatch(/[?&]_=\d+/);

    const categorySearch = page.waitForRequest(req => req.url().includes('wc/v2/products/categories'));
    await tieredPricingPage.locator.rules.categories(0).fill(CATEGORIES.parent);
    const categoryRequest = await categorySearch;
    expect(categoryRequest.url()).toMatch(/[?&]_=\d+/);
    expect((await categoryRequest.response()).status()).toBe(200);

    // A second rule row is its own TomSelect control, so the same term reaches the network again.
    await tieredPricingPage.locator.rules.addRowButton().click(tieredPricingPage.options);
    const repeatedSearch = page.waitForRequest(req => req.url().includes('tiered-pricing/product'));
    await tieredPricingPage.locator.rules.products(1).fill(ON_SALE.search);
    expect((await repeatedSearch).url()).not.toBe(firstProductUrl);

    console.log('✅ Autocomplete searches carried a cache-busting param and never repeated a URL');
  });

  /**
   * Test: Navigate to the edit screen by clicking the listing row's title
   * link, rather than going there directly by URL as every other test does.
   */
  test('Navigate to edit screen from the listing', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {
    const helper = tieredPricingListingPage.helper;
    await helper.clearData();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    const formData = tieredPricingPage.formData;
    await tieredPricingPage.fill(formData);
    await tieredPricingPage.publish();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.openEdit(1);

    await page.waitForURL(/action=edit&id=1/);
    await tieredPricingPage.expectFormDataFilled(formData);
    console.log('✅ Navigated to the edit screen via the listing title link and saw the correct entity');
  });

  test('Set as unpublished', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {
    const helper = tieredPricingListingPage.helper;

    // Reset the database, create a sample tiered pricing and set it as unpublished from the edit screen
    await helper.clearData();
    await helper.createSampleTieredPricing();

    await tieredPricingPage.goto(1);
    await tieredPricingPage.saveAsDraft();
    console.log('✅ Set as unpublished from the edit screen');

    // Reset the database, create a sample tiered pricing and set it as unpublished from the listing screen
    await helper.clearData();
    await helper.createSampleTieredPricing();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.changeStatus(1, 'unpublish');
    console.log('✅ Set as unpublished from the listing screen');
  });

  test('Manage trash', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {
    const helper = tieredPricingListingPage.helper;
      
    // Reset the database, create a sample tiered pricing and trash it from the edit screen
    await helper.clearData();
    await helper.createSampleTieredPricing();

    await tieredPricingPage.goto(1);
    await tieredPricingPage.trash();
    console.log('✅ Trashed from the edit screen');

    // Restore the previously trashed tiered pricing clicking the restore button
    await tieredPricingListingPage.gotoTrash();
    await tieredPricingListingPage.changeStatus(1, 'untrash');
    console.log('✅ Restored from Trash');

    // Reset the database, create a sample tiered pricing and trash it from the listing screen
    await helper.clearData();
    await helper.createSampleTieredPricing();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.changeStatus(1, 'trash');
    console.log('✅ Trashed from the listing screen');

    // Restore the previously trashed tiered pricing using the bulk actions selector
    await tieredPricingListingPage.gotoTrash();
    await tieredPricingListingPage.bulkActions([1], 'Restore');
    console.log('✅ Restored from the bulk actions selector');

    // Reset the database, create a sample tiered pricing and trash it from the bulk actions selector
    await helper.clearData();
    await helper.createSampleTieredPricing();
    await helper.createSampleTieredPricing();

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.bulkActions([1,2], 'Move to Trash');
    console.log('✅ Trashed from the bulk actions selector');
  });

  /**
   * Test: Tier with equal min and max units can be published.
   */
  test('Publish tier with equal min and max units', async ({ tieredPricingListingPage, tieredPricingPage, page }) => {

    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();

    const formData = {
      ...tieredPricingPage.formData,
      title: 'Equal Min Max Tier',
      pricing: [
        {
          minUnits: 2,
          maxUnits: 2,
          value: 25
        },
        {
          minUnits: 3,
          maxUnits: null,
          value: 20
        }
      ],
    };

    await tieredPricingPage.fill(formData);
    await tieredPricingPage.publish();
    await page.reload();
    await tieredPricingPage.expectFormDataFilled(formData);
    console.log('✅ Tier with equal min and max units successfully published');
  });

  test('Delete permanently', async ({ tieredPricingListingPage, page }) => {
    const helper = tieredPricingListingPage.helper;
    
    // Reset the database, create a sample tiered pricing and trash it from the listing screen. Then delete it permanently from the single button.
    await helper.clearData();
    await helper.createSampleTieredPricing();
    await helper.changeStatusToTieredPricing(1, 'trash');

    await tieredPricingListingPage.gotoTrash();
    await tieredPricingListingPage.bulkActions([1], 'Delete Permanently');
    console.log('✅ Deleted Permanently from the single button');

    // Reset the database, create two sample tiered pricing and trash them from the listing screen. Then delete them permanently from the bulk actions selector.
    await helper.clearData();
    await helper.createSampleTieredPricing();
    await helper.createSampleTieredPricing();
    await helper.changeStatusToTieredPricing(1, 'trash');
    await helper.changeStatusToTieredPricing(2, 'trash');

    await tieredPricingListingPage.gotoTrash();
    await tieredPricingListingPage.bulkActions([1,2], 'Delete Permanently');
    console.log('✅ Deleted Permanently from the bulk actions selector');
  });


  /**
   * When two tiered pricings match the same product with conflicting prices,
   * the first one created wins. Publishes the two groups in one order, asserts
   * the winner, then repeats with the creation order flipped, so a flipped
   * winner proves creation order (not the price value) decides.
   */
  test('The first created tiered pricing wins when two of them conflict', async ({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }) => {

    const assertWinner = async (...values) => {
      await tieredPricingPage.helper.clearDataAndEmptyCart();

      for (const value of values) {
        await tieredPricingListingPage.goto();
        await tieredPricingListingPage.addNew();
        await tieredPricingPage.fill({
          ...tieredPricingPage.formData,
          title: `Tier ${value}`,
          pricing: [{ minUnits: 1, maxUnits: null, value }],
        });
        await tieredPricingPage.publish();
      }

      const expectedWinner = values[0];

      // The product page shows the winning tiered pricing's tier table.
      await productPage.goto({ slug: ON_SALE.slug });
      expect(await productPage.getTierPrice(), 'product page shows the winning price').toBeCloseTo(expectedWinner, 2);

      // The cart applies the winning price.
      await productPage.addToCart({ slug: ON_SALE.slug, quantity: 3 });
      await cartPage.goto();
      expect(await cartPage.getItemPrice(), 'cart applies the winning price').toBeCloseTo(expectedWinner, 2);
    };

    await assertWinner(10, 20); // value-10 tier created first -> wins
    await assertWinner(20, 10); // value-20 tier created first -> wins
    console.log('✅ The first created tiered pricing wins (product + cart)');
  });

  /**
   * The quantity in the cart selects the tier whose range contains it.
   */
  test('Quantity selects the matching tier range in the cart', async ({ tieredPricingListingPage, tieredPricingPage, productPage, cartPage }) => {
    await tieredPricingPage.helper.clearData();

    // One tiered pricing on the on-sale product with two quantity ranges (both below its
    // 55 active price, so they read as discounts).
    await tieredPricingListingPage.goto();
    await tieredPricingListingPage.addNew();
    await tieredPricingPage.fill({
      ...tieredPricingPage.formData,
      title: 'Range',
      pricing: [
        { minUnits: 1, maxUnits: 5, value: 10 },
        { minUnits: 6, maxUnits: null, value: 8 },
      ],
    });
    await tieredPricingPage.publish();

    // Quantity 3 falls in the 1–5 range -> unit price 10.
    await tieredPricingPage.helper.emptyCart();
    await productPage.addToCart({ slug: ON_SALE.slug, quantity: 3 });
    await cartPage.goto();
    expect(await cartPage.getItemPrice()).toBeCloseTo(10, 2);

    // Quantity 8 falls in the 6+ range -> unit price 8.
    await tieredPricingPage.helper.emptyCart();
    await productPage.addToCart({ slug: ON_SALE.slug, quantity: 8 });
    await cartPage.goto();
    expect(await cartPage.getItemPrice()).toBeCloseTo(8, 2);

    console.log('✅ Quantity ranges applied the correct tier price');
  });

});