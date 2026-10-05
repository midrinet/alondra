import { test as baseTest, expect } from "@playwright/test";
import ProductPage from "./ProductPage";
import CheckoutPage from "./CheckoutPage";
import CartPage from "./CartPage";
import ShopPage from "./ShopPage";
import TieredPricingListingPage from "./TieredPricingListingPage";
import TieredPricingPage from "./TieredPricingPage";
import SettingsPage from "./SettingsPage";

export const test = baseTest.extend({

    tieredPricingListingPage: async ({ page, baseURL, request }, use) => {
        const fixture = new TieredPricingListingPage(page, expect, baseURL, request);
        await use(fixture);
    },
    tieredPricingPage: async ({ page, baseURL, request }, use) => {
        const fixture = new TieredPricingPage(page, expect, baseURL, request);
        await use(fixture);
    },

    settingsPage: async ({ page, baseURL, request }, use) => {
        const fixture = new SettingsPage(page, expect, baseURL, request);
        await use(fixture);
    },

    productPage: async ({ page }, use) => await use(new ProductPage(page)),
    cartPage: async ({ page }, use) => await use(new CartPage(page, expect)),
    shopPage: async ({ page }, use) => await use(new ShopPage(page, expect)),
    checkoutPage: async ({ page, baseURL, request }, use) => await use(new CheckoutPage(page, baseURL, expect, request))
});

export { expect };