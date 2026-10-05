import Page from './Page';

export default class TieredPricingListingPage extends Page {

    /**
     * @param {import('@playwright/test').Page} page
     * @param {import('@playwright/test').Expect} expect
     */
    constructor(page, expect, baseURL, request) {
        super(page, baseURL, expect, request);
        this.locator = {
            addNewButton: () => this.page.locator('#alondra-add-tiered-pricing'),
            // The primary column is a td up to WordPress 7.0 and a th from 7.1, so
            // match the column class instead of the cell element.
            titleCell: id => this.page.locator(`tr:has([name="element[]"][value="${id}"]) :is(td, th).column-title`),
            titleLink: id => this.page.locator(`tr:has([name="element[]"][value="${id}"]) :is(td, th).column-title a.row-title`),
            trashTab: () => this.page.locator('li.trash > a'),
            changeStatusAction: (id, status) => {
                let action = status;
                if (status === 'unpublish') {
                    action = 'draft';
                }
                // The href carries a nonce after the id, so match on its stable part.
                return this.page.locator(`.row-actions a[href*="action=${action}&id=${id}&"]`);
            },
            successfullyChangeStatusTo: status => {
                const notice = this.page.locator('.notice-success');
                switch (status) {
                    case 'unpublish':
                        return notice.getByText('Successfully changed item to unpublished');
                    case 'trash':
                        return notice.getByText('Successfully moved item to the trash');
                    case 'untrash':
                        return notice.getByText('Successfully restored item from trash');
                    case 'delete':
                        return notice.getByText('Successfully deleted item');
                }
            }

        }
    }

    async goto() {
        await this.wpAdmin.login({ force: false });
        await this.page.goto('./wp-admin/admin.php?page=alondra-tiered-pricing');
    }

    async addNew() {
        await this.locator.addNewButton().click();
    }

    /**
    * Successfully Save as unpublished.
    * @param {boolean} id The id of the tiered pricing to change status
    * @param {boolean} status The status to change to, either 'unpublish', 'trash', 'untrash' or 'delete'.
    * @returns {Promise<void>}
    */
    async changeStatus(id, status) {
        const actionLocator = this.locator.changeStatusAction(id, status);
        await this.locator.titleCell(id).hover();
        await actionLocator.click();
        await this.expect(this.locator.successfullyChangeStatusTo(status)).toBeVisible();
    }

    /**
     * Open the edit screen by clicking the row's title link, as a user would
     * from the listing, rather than navigating there directly by URL.
     * @param {number} id
     */
    async openEdit(id) {
        await this.locator.titleLink(id).click();
    }

    async gotoTrash() {
        await this.goto();
        await this.locator.trashTab().click();
    }

    /**
     * One bulk action for given ids
     * @param {Array<number>} ids
     * @param {string} action The action to perform. Options: 'Move to Trash', 'Restore', 'Delete Permanently'
     */
    async bulkActions(ids, action) {
        // await this.page.pause();
        for (let i = 0; i < ids.length; i++) {
            await this.page.check(`[name="element[]"][value="${ids[i]}"]`);
        }

        await this.page.selectOption('select[name="action"]', { label: action });
    }
}