import Page from './Page';
import { ON_SALE, CHILD_A, VARIATION, TAG, CATEGORIES, PROFILES } from './catalog';

export default class TieredPricingPage extends Page {
    /**
     * @typedef FormData
     * @property {string} title
     * @property {{minUnits:int, maxUnits?:int, value:float}[]} pricing
     * @property {{
     * products:{text:string, id:string}[], 
     * categories:string[],
     * tags:string[],
     * users:string[],
     * profiles:string[]
     *  }[]} rules
     */

    /**
     * @param {import('@playwright/test').Page} page
     * @param {import('@playwright/test').Expect} expect
     */
    constructor(page, expect, baseURL, request) {
        super(page, baseURL, expect, request);
        this.options = { timeout: 10000 };
        this.locator = {
            publishButton: () => this.page.locator('.alondra-publish-tiered-pricing'),
            optionsButton: () => this.page.locator('.alondra-options-menu__toggle'),
            trashButton: () => this.page.locator('.alondra-trash-tiered-pricing'),
            draftButton: () => this.page.locator('.alondra-preferences__titlebar-options .alondra-draft-tiered-pricing').first(),
            modal: {
                acceptButton: () => this.page.locator('.alondra-modal__accept'),
            },
            message: {
                successfullyPublished: () => this.page.locator('.alondra-snackbar__message', { hasText: 'Successfully published' }),
                successfullyTrashed: () => this.page.locator('.notice-success', { hasText: 'Successfully moved item to the trash' }),
                successfullySavedAsUnpublished: () => this.page.locator('.alondra-snackbar__message', { hasText: 'Saved as unpublished' }),
                error: error => this.page.locator('.alondra-snackbar__message', { hasText: error }),
                dismissButton: () => this.page.locator('.alondra-snackbar__dismiss'),
            },
            title: () => this.page.locator('.alondra-input[name="title"]'),
            pricing: {
                addRowButton: () => this.page.locator('#alondra-table-tiers .alondra-add'),
                delSelectedRowsButton: () => this.page.locator('#alondra-table-tiers .alondra-remove'),
                checkAllRows: () => this.page.locator('#alondra-table-tiers .alondra-check-all'),
                checkRow: row => this.page.locator('#alondra-table-tiers .alondra-table__body>.alondra-table__row [type="checkbox"]').nth(row),
                rows: () => this.page.locator('#alondra-table-tiers .alondra-table__body>.alondra-table__row'),
                minUnits: row => this.page.locator('#alondra-table-tiers .alondra-table__body>.alondra-table__row [name="min_quantity"]').nth(row),
                maxUnits: row => this.page.locator('#alondra-table-tiers .alondra-table__body>.alondra-table__row [name="max_quantity"]').nth(row),
                value: row => this.page.locator('#alondra-table-tiers .alondra-table__body>.alondra-table__row [name="value"]').nth(row),
            },
            rules: {
                addRowButton: () => this.page.locator('#alondra-table-rules .alondra-add'),
                delSelectedRowsButton: () => this.page.locator('#alondra-table-rules .alondra-remove'),
                checkAllRows: () => this.page.locator('#alondra-table-rules .alondra-check-all'),
                checkRow: row => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [type="checkbox"]').nth(row),
                rows: () => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row'),

                products: row => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="products"] + .alondra-input input').nth(row),
                productsDropdownOpt: (row, dataValue) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="products"] + .alondra-input').nth(row).locator(`.ts-dropdown [data-value="${dataValue}"]`),
                productsSelectedOpt: (row, dataValue) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="products"] + .alondra-input').nth(row).locator(`.ts-control [data-value="${dataValue}"]`),

                categories: row => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="categories"] + .alondra-input input').nth(row),
                categoriesDropdownOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="categories"] + .alondra-input').nth(row).locator('.ts-dropdown [data-value]', { hasText: text }),
                categoriesSelectedOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="categories"] + .alondra-input').nth(row).locator('.ts-control [data-value]', { hasText: text }),

                tags: row => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="tags"] + .alondra-input input').nth(row),
                tagsDropdownOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="tags"] + .alondra-input').nth(row).locator('.ts-dropdown [data-value]', { hasText: text }),
                tagsSelectedOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="tags"] + .alondra-input').nth(row).locator('.ts-control [data-value]', { hasText: text }),

                users: row => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="users"] + .alondra-input input').nth(row),
                usersDropdownOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="users"] + .alondra-input').nth(row).locator('.ts-dropdown [data-value]', { hasText: text }),
                usersSelectedOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="users"] + .alondra-input').nth(row).locator('.ts-control [data-value]', { hasText: text }),

                profiles: row => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="profiles"] + .alondra-input input').nth(row),
                profilesDropdownOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="profiles"] + .alondra-input').nth(row).locator('.ts-dropdown [data-value]', { hasText: text }),
                profilesSelectedOpt: (row, text) => this.page.locator('#alondra-table-rules .alondra-table__body>.alondra-table__row [name="profiles"] + .alondra-input').nth(row).locator('.ts-control [data-value]', { hasText: text }),
            }
        }
        // Default form data to fill
        this.formData = {
            title: 'Tiered Price Title',
            pricing: [
                {
                    minUnits: 1,
                    maxUnits: 10,
                    value: 50
                }
            ],
            rules: [
                {
                    products: [{
                        text: ON_SALE.search,
                        id: ON_SALE.id
                    }],
                    categories: [],
                    tags: [],
                    users: [],
                    profiles: []
                },
            ]
        }

        this.validFormData = {
            ...this.formData,
            title: 'Tiered Price Title',
            pricing: [
                ...this.formData.pricing,
                {
                    ...this.formData.pricing[0],
                    minUnits: 11,
                    maxUnits: null,
                    value: 60
                }
            ],
            rules: [
                {
                    ...this.formData.rules[0],
                    tags: [TAG],
                    users: ['admin'],
                    profiles: ['Administrator']
                },
                {
                    ...this.formData.rules[0],
                    // By id and by SKU, so both non-title branches of
                    // ProductRepo::get_products() are exercised - the
                    // SKU one over a product_variation.
                    products: [
                        { text: `${CHILD_A.id}`, id: CHILD_A.id },
                        { text: VARIATION.sku, id: VARIATION.id }
                    ],
                    categories: [CATEGORIES.parent, CATEGORIES.sub],
                    profiles: [...PROFILES]
                }
            ]
        };

        this.invalidFormData = [
            // Title is empty. The other fields are filled.
            {
                ...this.formData,
                title: '',
                error: 'Title is required'
            },
            // Pricing is empty. The other fields are filled.
            {
                ...this.formData,
                pricing: [],
                error: 'At least one tier is required'
            },
            // Pricing options are colliding.
            {
                ...this.formData,
                pricing: [
                    ...this.formData.pricing,
                    {
                        ...this.formData.pricing[0],
                        minUnits: 8,
                        maxUnits: null,
                        value: 50
                    }
                ],
                error: 'At least two price rows affect the same range of units'
            },
            // Rules is empty. The other fields are filled.
            {
                ...this.formData,
                rules: [],
                error: 'At least one rule is required'
            },
        ];
    }

    /**
     * Fill the form with the given data
     * @param {FormData} data
     */
    async fill(data) {
            const { title, pricing, rules } = data;

            // Clear form.
            await this.locator.pricing.checkAllRows().check();
            await this.locator.pricing.delSelectedRowsButton().click(this.options);
            await this.locator.rules.checkAllRows().check();
            await this.locator.rules.delSelectedRowsButton().click(this.options);

            // Fill form
            await this.locator.title().fill(title);
            for (let i = 0; i < pricing.length; i++) {
                await this.locator.pricing.addRowButton().click(this.options);
                await this.locator.pricing.minUnits(i).fill(`${pricing[i].minUnits}`);
                if (null !== pricing[i].maxUnits) {
                    await this.locator.pricing.maxUnits(i).fill(`${pricing[i].maxUnits}`);
                }
                await this.locator.pricing.value(i).fill(`${pricing[i].value}`);
            }

            for (let i = 0; i < rules.length; i++) {
                await this.locator.rules.addRowButton().click(this.options);

                for (let j = 0; j < rules[i].products.length; j++) {
                    const { id, text } = rules[i].products[j];
                    await this.locator.rules.products(i).fill(text);
                    await this.locator.rules.productsDropdownOpt(i, id).click(this.options);
                }

                for (let j = 0; j < rules[i].categories.length; j++) {
                    const text = rules[i].categories[j];
                    await this.locator.rules.categories(i).fill(text);
                    await this.locator.rules.categoriesDropdownOpt(i, text).click(this.options);
                }

                for (let j = 0; j < rules[i].tags.length; j++) {
                    const text = rules[i].tags[j];
                    await this.locator.rules.tags(i).fill(text);
                    await this.locator.rules.tagsDropdownOpt(i, text).click(this.options);
                }

                for (let j = 0; j < rules[i].users.length; j++) {
                    const text = rules[i].users[j];
                    await this.locator.rules.users(i).fill(text);
                    await this.locator.rules.usersDropdownOpt(i, text).click(this.options);
                }

                for (let j = 0; j < rules[i].profiles.length; j++) {
                    const text = rules[i].profiles[j];
                    await this.locator.rules.profiles(i).fill(text);
                    await this.locator.rules.profilesDropdownOpt(i, text).click(this.options);
                }
            }
        }

    /**
     * Expect the form to be filled with the given data
     * @param {FormData} data
     */
    async expectFormDataFilled(data) {
            const { title, pricing, rules } = data;
            await this.expect(this.locator.title()).toHaveValue(title, this.options);

            for (let i = 0; i < pricing.length; i++) {
                await this.expect(this.locator.pricing.minUnits(i)).toHaveValue(`${pricing[i].minUnits}`, this.options);
                const maxUnits = pricing[i].maxUnits ?? '';
                await this.expect(this.locator.pricing.maxUnits(i)).toHaveValue(`${maxUnits}`, this.options);
                await this.expect(this.locator.pricing.value(i)).toHaveValue(`${pricing[i].value}`, this.options);
            }

            for (let i = 0; i < rules.length; i++) {

                for (let j = 0; j < rules[i].products.length; j++) {
                    const { id, text } = rules[i].products[j];
                    await this.expect(this.locator.rules.productsSelectedOpt(i, id)).toBeVisible(this.options);
                }


                for (let j = 0; j < rules[i].categories.length; j++) {
                    const text = rules[i].categories[j];
                    await this.expect(this.locator.rules.categoriesSelectedOpt(i, text)).toBeVisible(this.options);
                }


                for (let j = 0; j < rules[i].tags.length; j++) {
                    const text = rules[i].tags[j];
                    await this.expect(this.locator.rules.tagsSelectedOpt(i, text), `Tag should be visible with text: ${text} on row: ${i}`).toBeVisible(this.options);
                }


                for (let j = 0; j < rules[i].users.length; j++) {
                    const text = rules[i].users[j];
                    await this.expect(this.locator.rules.usersSelectedOpt(i, text)).toBeVisible(this.options);
                }


                for (let j = 0; j < rules[i].profiles.length; j++) {
                    const text = rules[i].profiles[j];
                    await this.expect(this.locator.rules.profilesSelectedOpt(i, text)).toBeVisible(this.options);
                }
            }
        }

    /**
     * Expect the form to be invalid with the given error message after trying to publish or save as draft.
     * @param {string} error The error message to expect
     * @param {string} action The action to perform (publish or saveAsDraft)
     * @param {boolean} confirmDraft Whether is necessary to confirm or not the draft
     */
    async expectFormInvalid(error, action, confirmDraft = true) {
            console.log(`Testing that error is shown: ${error}`);
            // console.log(`Must confirm save as draft: ${confirmDraft ? 'Yes' : 'No'}`);

            if ('publish' === action) {
                // Validate Publish button.
                await this.locator.publishButton().click(this.options);
                // await this.page.pause();
                await this.expect(this.locator.message.error(error), `Error message should be visible with text: ${error}`).toBeVisible(this.options);
                await this.locator.message.dismissButton().click(this.options);
            } else if ('saveAsDraft' === action) {
                // Validate Save as unpublished button.
                await this.locator.draftButton().click(this.options);
                if (confirmDraft) {
                    await this.locator.modal.acceptButton().click(this.options);
                }
                await this.expect(this.locator.message.error(error), `Error message should be visible with text: ${error}`).toBeVisible(this.options);
                await this.locator.message.dismissButton().click(this.options);
            }
        }

    /**
     * Successfully Save as unpublished.
     * @param {boolean} confirmDraft Whether is necessary to confirm or not the draft
     * @returns {Promise<void>}
     */
    async saveAsDraft(confirmDraft = true) {
            await this.locator.draftButton().click(this.options);
            if (confirmDraft) {
                await this.locator.modal.acceptButton().click(this.options);
            }

            await this.expect(this.locator.message.successfullySavedAsUnpublished()).toBeVisible(this.options);
        }

    /**
     * Successfully publish a new tiered pricing.
     */
    async publish() {
            await this.save();
            await this.expect(this.locator.publishButton()).toHaveText('Save', this.options);
        }

    /**
     * Successfully save an existing published tiered pricing after editing.
     */
    async save() {
            await this.locator.publishButton().click(this.options);
            await this.expect(this.locator.message.successfullyPublished()).toBeVisible(this.options);
        }

    /**
     * Go to the tiered pricing listing page.
     */
    async goto(id){
            await this.wpAdmin.login({ force: false });
            await this.page.goto('./wp-admin/admin.php?page=alondra-tiered-pricing&action=edit&id=' + id);
        }

    /**
     * Trash the tiered pricing.
     */
    async trash(){
            await this.locator.optionsButton().click(this.options);
            await this.locator.trashButton().click(this.options);
            await this.expect(this.locator.message.successfullyTrashed()).toBeVisible(this.options);
        }
}
