export default class AlondraHelper {

    /**
     * @param {import('@playwright/test').Request} request
     * @param {import('@playwright/test').Expect} expect
     */
    constructor(request, expect) {
        this.request = request;
        this.expect = expect;
        this.webhooks = {
            CLEAR_DATA: 'clear_data',
            CREATE_SAMPLE_TIERED_PRICING: 'create_sample_tiered_pricing',
            CHANGE_STATUS_TO_TIERED_PRICING: 'change_status_to_tiered_pricing',
            EMPTY_CART: 'empty_cart',
            SET_PRODUCT_TAGS: 'set_product_tags',
            SET_PRODUCT_CATEGORIES: 'set_product_categories',
            SET_USER_ROLES: 'set_user_roles',
            PROVISION_PLUGIN_COPY: 'provision_plugin_copy',
            REMOVE_PLUGIN_COPY: 'remove_plugin_copy',
            PLUGIN_STATUS: 'plugin_status',
            ACTIVATE_PLUGIN: 'activate_plugin',
            SEED_DEMO_STORE: 'seed_demo_store'
        };
    }

    /**
     * Login to WordPress admin
     * 
     * @param {string} webhook The webhook to execute
     * @param {Array<Object>} args The arguments to pass to the webhook. Each argument is an object with `name` and `value` properties
     * @returns {Promise<void>}
     */
    async executeWebhook(webhook, args = []) {
        let url = `./?alondra-webhook=${webhook}`;
        for (const { name, value } of args) {
            url += `&${name}=${encodeURIComponent(value)}`;
        }
        const response = await this.request.post(url);
        this.expect(response.status(), 'Webhook response has HTTP 200 code').toBe(200);
        const json = await response.json();
        this.expect(json.success, 'Webhook was processed successfully').toBe(true);
        return json;
    }

    async clearData() {
        await this.executeWebhook(this.webhooks.CLEAR_DATA);
    }

    async createSampleTieredPricing() {
        await this.executeWebhook(this.webhooks.CREATE_SAMPLE_TIERED_PRICING);
    }

    async changeStatusToTieredPricing(id, status) {
        await this.executeWebhook(this.webhooks.CHANGE_STATUS_TO_TIERED_PRICING, [
            { name: 'id', value: id },
            { name: 'status', value: status }
        ]);
    }

    async emptyCart() {
        await this.executeWebhook(this.webhooks.EMPTY_CART);
    }

    /**
     * clearData() and emptyCart() hit disjoint tables (tiered pricing vs. cart/
     * session), so they're safe to run concurrently instead of one after the other.
     */
    async clearDataAndEmptyCart() {
        await Promise.all([this.clearData(), this.emptyCart()]);
    }

    /**
     * Assign product_tag terms to a product, replacing its existing tags.
     * Used to anchor tag matching tests against a product that does not already
     * carry the term under test.
     *
     * @param {number} productId
     * @param {string[]} tags
     */
    async setProductTags(productId, tags) {
        await this.executeWebhook(this.webhooks.SET_PRODUCT_TAGS, [
            { name: 'product_id', value: productId },
            { name: 'tags', value: JSON.stringify(tags) }
        ]);
    }

    /**
     * Assign product_cat terms to a product, replacing its existing categories.
     * Used to anchor category matching tests against a product/category combination
     * the seeder does not already create.
     *
     * @param {number} productId
     * @param {string[]} categories
     */
    async setProductCategories(productId, categories) {
        await this.executeWebhook(this.webhooks.SET_PRODUCT_CATEGORIES, [
            { name: 'product_id', value: productId },
            { name: 'categories', value: JSON.stringify(categories) }
        ]);
    }

    /**
     * Set a WordPress user's roles, replacing their existing ones. Used to
     * anchor role matching tests against a user with more than one role.
     *
     * @param {number} userId
     * @param {string[]} roles
     */
    async setUserRoles(userId, roles) {
        await this.executeWebhook(this.webhooks.SET_USER_ROLES, [
            { name: 'user_id', value: userId },
            { name: 'roles', value: JSON.stringify(roles) }
        ]);
    }

    /**
     * Provision a disposable copy of the plugin at wp-content/plugins/alondra so
     * the real wp-admin Delete flow can be exercised without touching the
     * bind-mounted source. Idempotent.
     */
    async provisionPluginCopy() {
        await this.executeWebhook(this.webhooks.PROVISION_PLUGIN_COPY);
    }

    /**
     * Remove the disposable plugin copy. Teardown safety net when the wp-admin
     * Delete flow did not run.
     */
    async removePluginCopy() {
        await this.executeWebhook(this.webhooks.REMOVE_PLUGIN_COPY);
    }

    /**
     * Read-only probe of the plugin's DB footprint.
     *
     * @returns {Promise<{tables: string[], tables_exist: boolean, option_exists: boolean, version_option_exists: boolean, rows: number}>}
     *   `tables` lists every existing `wp_alondra_%` table; `tables_exist` is
     *   true when all three are present; `rows` is the tiered-pricing row count.
     */
    async getPluginStatus() {
        const json = await this.executeWebhook(this.webhooks.PLUGIN_STATUS);
        return json.data;
    }

    /**
     * Programmatically (re)activate the live Alondra plugin, page-free. Used in
     * teardown to guarantee the plugin is active for later specs. No-op if
     * already active.
     */
    async ensureAlondraActive() {
        await this.executeWebhook(this.webhooks.ACTIVATE_PLUGIN);
    }

    /**
     * Re-run the demo store seeder. clearData() truncates the plugin's tables,
     * which takes the seeded pricing groups with it and leaves the store with
     * no tier table to photograph; this puts them back. Idempotent - everything
     * else in the catalog is resolved before it is written and reports
     * "unchanged".
     *
     * @returns {Promise<string[]>} The seeder's report, one line per entity.
     */
    async seedDemoStore() {
        const json = await this.executeWebhook(this.webhooks.SEED_DEMO_STORE);
        return json.data.report;
    }
}