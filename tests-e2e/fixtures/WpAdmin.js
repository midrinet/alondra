export default class WpAdmin {

    /**
     * @param {import('@playwright/test').Page} page 
     */
    constructor(page, baseURL, expect) {
        this.page = page;
        this.baseURL = baseURL;
        this.expect = expect;
        this.locators = {
            // Freemius intercepts a manual deactivation with a "Quick Feedback" modal.
            skipAndDeactivate: () => this.page.getByRole('link', { name: 'Skip & Deactivate' }),
        };
        this.urls = {
            plugins: () => `${this.baseURL}/wp-admin/plugins.php`,
        };
    }

    /**
     * Login to WordPress admin
     * 
     * @param {Object} options
     * @param {boolean} options.force Whether to force the login even if already logged in
     * @returns {Promise<void>}
     */
    async login({ force = false }) {
        if (!force) {
            const cookies = await this.page.context().cookies();
            if (cookies.find(c => -1 !== c.name.search('wordpress_logged_in'))) {
                return;
            }
        }
        await this.page.goto(`${this.baseURL}/wp-login.php?redirect_to=${encodeURIComponent(`${this.baseURL}/wp-admin/`)}&reauth=1`);
        await this.expect(this.page.locator('#loginform')).toBeVisible();
        await this.page.fill('#user_login', process.env.WP_ADMIN_USER);

        // wp-login.php focuses #user_login 200 ms after its inline script runs, which
        // steals the focus fill() needs for insertText and silently drops the password.
        const pass = this.page.locator('#user_pass');
        await this.expect.poll(async () => {
            await pass.fill(process.env.WP_ADMIN_PASSWORD);
            return pass.inputValue();
        }).toBe(process.env.WP_ADMIN_PASSWORD);

        await this.page.click('#wp-submit');
        await this.page.waitForURL('./wp-admin/', { timeout: 15000 });
    }

    async logout() {
        // await this.page.goto(`${this.baseURL}/wp-login.php?action=logout`);
        // logout by clearing cookies
        await this.page.context().clearCookies({ name: /wordpress_logged_in.*/ });
    }

    async goToPlugins() {
        await this.login({ force: false });
        await this.page.goto(this.urls.plugins(), { waitUntil: 'domcontentloaded' });
    }

    /**
     * A plugin's row on plugins.php. Both Alondra copies show the identical
     * display name, so rows are targeted by their `data-plugin` path.
     *
     * @param {string} pluginPath e.g. `alondra/alondra.php` or `alondra-uninstall-copy/alondra.php`
     */
    pluginRow(pluginPath) {
        return this.page.locator(`tr[data-plugin="${pluginPath}"]`);
    }

    /**
     * Whether a plugin is active (its row exposes a Deactivate link).
     * @param {string} pluginPath
     */
    async isPluginActive(pluginPath) {
        await this.goToPlugins();
        return 0 < await this.pluginRow(pluginPath).locator('a[href*="action=deactivate"]').count();
    }

    /**
     * Activate a plugin from plugins.php. Freemius may bounce through an
     * opt-in/sync redirect before landing back on the list.
     * @param {string} pluginPath
     */
    async activatePlugin(pluginPath) {
        await this.goToPlugins();
        // Await the click's navigation so the activate request commits before the
        // poll re-navigates, which would otherwise abort it in flight. The poll
        // also rides out Freemius' opt-in redirect.
        await this.pluginRow(pluginPath).locator('a[href*="action=activate"]').click();
        await this.expect.poll(() => this.isPluginActive(pluginPath), { timeout: 30000 }).toBe(true);
    }

    /**
     * Deactivate a plugin from plugins.php, dismissing the Freemius "Quick
     * Feedback" modal via "Skip & Deactivate" when it appears.
     * @param {string} pluginPath
     */
    async deactivatePlugin(pluginPath) {
        await this.goToPlugins();
        await this.pluginRow(pluginPath).locator('a[href*="action=deactivate"]').click({ noWaitAfter: true });
        // Only the modal-absent case (deactivation went straight through) is
        // ignored: if the modal does appear, a failure to dismiss it surfaces.
        const skip = this.locators.skipAndDeactivate();
        const modalAppeared = await skip.waitFor({ state: 'visible', timeout: 10000 }).then(() => true).catch(() => false);
        if (modalAppeared) {
            await skip.click();
        }
        // Poll until the row reports inactive (isPluginActive re-navigates).
        await this.expect.poll(() => this.isPluginActive(pluginPath), { timeout: 30000 }).toBe(false);
    }

    /**
     * Delete a plugin from plugins.php, accepting the "Are you sure…" confirm
     * dialog. The delete completes asynchronously — resolves once the row is gone.
     * @param {string} pluginPath
     */
    async deletePlugin(pluginPath) {
        await this.goToPlugins();
        this.page.once('dialog', dialog => dialog.accept());
        await this.pluginRow(pluginPath).locator('a[href*="action=delete-selected"]').click({ noWaitAfter: true });
        // WP deletes via AJAX and rewrites the row in place to a `.deleted` state.
        // Wait for that positive signal — not merely the absence of a live row,
        // which a full-page fallback (interstitial confirm screen) would satisfy
        // without an actual delete having run.
        await this.expect.poll(
            () => this.page.locator(`tr[data-plugin="${pluginPath}"].deleted`).count(),
            { timeout: 30000 }
        ).toBeGreaterThan(0);
    }
}
