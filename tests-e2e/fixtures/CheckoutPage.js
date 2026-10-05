import WpAdmin from './WpAdmin';
export default class CheckoutPage {

    /**
     * @param {import('@playwright/test').Page} page
     * @param {string} baseURL
     * @param {import('@playwright/test').Expect} expect
     * @param {import('@playwright/test').Request} request
     */
    constructor(page, baseURL, expect, request) {
        this.page = page;
        this.expect = expect;
        this.request = request;
        this.wpAdmin = new WpAdmin(page, baseURL, expect);
    }

    async goto() {
        await this.page.goto('./checkout/');
    }
}