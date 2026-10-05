import AlondraHelper from './AlondraHelper';
import WpAdmin from './WpAdmin';

/**
 * Page class. Calls webhooks to interact with the plugin and prepare data for tests.
 */
export default class Page {

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
        this.helper = new AlondraHelper(request, expect);
    }
}