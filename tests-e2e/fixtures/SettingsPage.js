import Page from './Page';

export default class SettingsPage extends Page {

    /**
     * @param {import('@playwright/test').Page} page
     * @param {import('@playwright/test').Expect} expect
     */
    constructor(page, expect, baseURL, request) {
        super(page, baseURL, expect, request);
        this.locator = {
            field: key => this.page.locator(`[name="alondra[${key}]"]`),
            anyField: () => this.page.locator('form.alondra-settings [name^="alondra["]'),
            save: () => this.page.locator('form.alondra-settings button[type="submit"]'),
            saved: () => this.page.locator('.notice-success').getByText('Settings saved.'),
            banner: () => this.page.locator('.notice.alondra-upsell'),
            upgradeLink: () => this.banner().getByRole('link', { name: 'Get Alondra Plus' }),
            titlebar: () => this.page.locator('.alondra-preferences__titlebar'),
            logo: () => this.page.locator('.alondra-preferences__titlebar .alondra-preferences__logo'),
            title: () => this.page.locator('.alondra-preferences__title'),
            tabs: () => this.page.locator('.alondra-tab__container'),
            footer: () => this.page.locator('.alondra-preferences__footer'),
            edition: () => this.page.locator('.alondra-preferences__footer-edition'),
            changelogLink: () => this.page.locator('.alondra-preferences__footer').getByRole('link', { name: 'Changelog' }),
            supportLink: () => this.page.locator('.alondra-preferences__footer').getByRole('link', { name: 'Get help' }),
            reviewsLink: () => this.page.locator('.alondra-rate'),
            donateLink: () => this.page.locator('.alondra-kofi'),
        };
        this.banner = this.locator.banner;
    }

    async goto() {
        await this.wpAdmin.login({ force: false });
        await this.page.goto('./wp-admin/options-general.php?page=alondra-settings');
    }

    /**
     * Names of every settings field the form renders.
     * @returns {Promise<string[]>}
     */
    async fieldNames() {
        return this.locator.anyField().evaluateAll(els => [...new Set(els.map(el => el.getAttribute('name')))]);
    }

    async save() {
        await this.locator.save().click();
        await this.expect(this.locator.saved()).toBeVisible();
    }
}
