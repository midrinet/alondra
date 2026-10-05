export default class ShopPage {

    /**
     * @param {import('@playwright/test').Page} page
     * @param {import('@playwright/test').Expect} expect
     */
    constructor(page, expect) {
        this.page = page;
        this.expect = expect;
    }

    async goto() {
        await this.page.goto('./shop/');
    }
}