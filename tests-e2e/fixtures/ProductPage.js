import { parsePrice } from './utils';

export default class ProductPage {

    /**
     * @param {import('@playwright/test').Page} page
     */
    constructor(page) {
        this.page = page;
    }

    /**
     * @param {Object} options
     * @param {string} options.slug
     * @returns {Promise<string>} The URL of the product
     */
    async goto({ slug }) {
        const url = this.getProductUrl(slug);
        await this.page.goto(url);
        return url;
    }

    getProductUrl(slug) {
        return `./product/${slug}/`;
    }

    /**
     * @param {Object} options
     * @param {string} options.slug The product slug
     * @param {number} options.quantity The quantity to add to the cart
     */
    async addToCart({ slug, quantity }) {
        const url = await this.goto({ slug });
        await this.page.fill('[name="quantity"]', `${quantity || 1}`);
        await this.page.click('[name="add-to-cart"]');
        await this.page.waitForURL(url, { timeout: 5000, waitUntil: 'commit' });
    }

    async selectVariation({ attributeName, value }) {
        await this.page.locator(`select[name="attribute_${attributeName}"]`, value).selectOption(value);
    }

    async clearVariations() {
        await this.page.click('.reset_variations');
    }

    /**
     * Read the displayed product price as a number. Assumes the product page is open.
     * @returns {Promise<number>}
     */
    async getBasePrice() {
        // Scope to the single-product price block; .last() yields the current price
        // (the sale price when on sale, otherwise the only amount).
        const text = await this.page
            .locator('.wp-block-woocommerce-product-price[data-is-descendent-of-single-product-template="true"] .woocommerce-Price-amount')
            .last().innerText();
        return parsePrice(text);
    }

    /**
     * Read the per-unit price of the first row of the Alondra tier table shown on the
     * product page (the table belongs to the winning tiered pricing). Assumes the
     * product page is open and a tiered pricing matches the product.
     * @returns {Promise<number>}
     */
    async getTierPrice() {
        const value = await this.page.locator('.alondra-pricing__option').first().getAttribute('data-price');
        return parseFloat(value);
    }

    /**
     * A row of the Alondra tier table, waited for. `count()` and `innerText()` do not
     * auto-wait, so readers built on them would report "absent" while they mean
     * "not rendered yet".
     * @param {number} index Row index.
     */
    async tierRow(index = 0) {
        const row = this.page.locator('.alondra-pricing__option').nth(index);
        await row.waitFor();
        return row;
    }

    /**
     * The price a tier row is struck through against. Alondra always strikes the tier
     * price through the product's REGULAR price, so on a product carrying a native
     * WooCommerce sale this is the regular price, never the sale one.
     * @param {number} index Row index.
     * @returns {Promise<number|null>} null when the row shows no strikethrough.
     */
    async getTierStrikethroughPrice(index = 0) {
        const del = (await this.tierRow(index)).locator('del');
        return (await del.count()) ? parsePrice(await del.innerText()) : null;
    }

    /**
     * The signed discount percentage a tier row labels itself with: 7 for "(7% off)",
     * -10 for "(-10% off)". Read out of the row text rather than by selector - the
     * percent <span> carries no class, while wc_price() emits its own spans.
     * @param {number} index Row index.
     * @returns {Promise<number|null>} null when the row shows no discount label.
     */
    async getTierDiscountPercent(index = 0) {
        const match = (await (await this.tierRow(index)).innerText()).match(/\((-?\d+)\s*%\s*off\)/);
        return match ? parseInt(match[1], 10) : null;
    }

    /* ---- Settings storefront verification ---- */

    /** The Alondra tier block wrapper. */
    tierWrapper() {
        return this.page.locator('.alondra-pricing__wrapper');
    }

    /** Whether the tier block is rendered at all (false for layout_pos = "hide"). */
    async isTierVisible() {
        return (await this.tierWrapper().count()) > 0;
    }

    /** Layout template in use: 'table' | 'pills' | 'list' | null. */
    async getLayoutType() {
        const inner = this.page.locator('.alondra-pricing__options').first();
        if (0 === await inner.count()) {
            return null;
        }
        const cls = await inner.getAttribute('class');
        if (cls.includes('alondra-pricing__table')) {
            return 'table';
        }
        if (cls.includes('alondra-pricing__pill-container')) {
            return 'pills';
        }
        if (cls.includes('alondra-pricing__list-container')) {
            return 'list';
        }
        return null;
    }

    /** The Alondra CSS custom properties applied on :root (empty object when styles are disabled). */
    async getColorVars() {
        return this.page.evaluate(() => {
            const s = getComputedStyle(document.documentElement);
            const read = name => s.getPropertyValue(name).trim();
            return {
                color: read('--alondra-color'),
                bg: read('--alondra-bg-color'),
                bd: read('--alondra-bd-color'),
                hColor: read('--alondra-h-color'),
                hBg: read('--alondra-h-bg-color'),
                hBd: read('--alondra-h-bd-color'),
            };
        });
    }

    /** Whether Alondra's front styles were enqueued (false when "Disable styles" is on). */
    async hasAlondraStyles() {
        return this.page.evaluate(() =>
            [...document.querySelectorAll('style')].some(s => s.textContent.includes('--alondra-color'))
            || !!document.querySelector('link[href*="front.css"], #front-css, link[id^="alondra"]')
        );
    }

    /** Per-unit price of the currently active tier option. */
    async getActiveTierPrice() {
        const value = await this.page.locator('.alondra-pricing__option--active').first().getAttribute('data-price');
        return parseFloat(value);
    }

    /** Whether the live subtotal element is present ("Show total after prices"). */
    async hasSubtotal() {
        return (await this.page.locator('.alondra-pricing__subtotal').count()) > 0;
    }

    /** The live subtotal (active tier price × quantity) as a number. */
    async getSubtotal() {
        const text = await this.page.locator('.alondra-pricing__subtotal').first().innerText();
        return parsePrice(text);
    }

    /** Whether the original product price is struck through ("Show as discount"). */
    async hasStrikethrough() {
        return (await this.page.locator('.wp-block-woocommerce-product-price del, .price del, .alondra-price del').count()) > 0;
    }

    /** Whether tier options are clickable ("Interactive prices"). */
    async tiersAreClickable() {
        return (await this.page.locator('.alondra-pricing__option--clickable').count()) > 0;
    }

    /** Click a tier option by index. */
    async clickTier(index = 0) {
        await this.page.locator('.alondra-pricing__option').nth(index).click();
    }

    /** Current value of the quantity input. */
    async getQuantity() {
        return parseInt(await this.page.locator('[name="quantity"]').inputValue(), 10);
    }

    /** Displayed single-product price as a number. */
    async getDisplayedPrice() {
        return this.getBasePrice();
    }

    /** All amounts shown in the single-product price block, as numbers. */
    async getPriceAmounts() {
        const texts = await this.page
            .locator('.wp-block-woocommerce-product-price[data-is-descendent-of-single-product-template="true"] .woocommerce-Price-amount')
            .allInnerTexts();
        return texts.map(parsePrice);
    }

    /**
     * Read a flag the front script localizes onto window.alondra. Used for
     * settings whose visible effect depends on theme markup the storefront
     * does not render (e.g. live_price needs an .alondra-price element).
     * @param {string} name
     */
    async getFrontFlag(name) {
        return this.page.evaluate(n => (window.alondra ? window.alondra[n] : undefined), name);
    }

    /** Set the quantity input and dispatch the change so live handlers react. */
    async setQuantity(qty) {
        const input = this.page.locator('[name="quantity"]');
        await input.fill(`${qty}`);
        await input.dispatchEvent('change');
    }
}
