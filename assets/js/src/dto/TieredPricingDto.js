/**
 * TieredPricing Data Transfer Object
 *
 * @since    1.0.0
 *
 * @abstract
 */
export class TieredPricingDto {

    constructor(id, title, status, tiers, rules) {
        this.id = id ? parseInt(id) : 0;
        this.title = title;
        this.status = status;
        this.tiers = tiers;
        this.rules = rules;
        this.validationMessage = null;
    }

    /**
     * Check if the DTO is valid
     * @returns {boolean}
     */
    isValid() {

        if (!this.title) {
            this.validationMessage = alondra_tiered_pricing.text.error_title;
            return false;
        }
        if (this.tiers.length === 0) {
            this.validationMessage = alondra_tiered_pricing.text.error_no_tier;
            return false;
        }
        // Filter for wrong tiers. Criteria is that minQuantity must be greater than 0 and maxQuantity must be greater than or equal to minQuantity.
        // And value must be a number equal or greater than 0.
        const wrongTiers = this.tiers.filter((tier) => {
            return tier.minQuantity <= 0 || (tier.maxQuantity && tier.maxQuantity < tier.minQuantity) || isNaN(tier.value) || tier.value < 0;
        });

        if (wrongTiers.length > 0) {
            this.validationMessage = alondra_tiered_pricing.text.error_wrong_tier;
            return false;
        }


        // Check if exists overlapping tiers, that is two or more tiers which intersect min and max quantities. Use forof instead of filter
        const overlappingTiers = this.tiers.filter((tier) => {
            return this.tiers.filter((t) => {

                const tMax = null !== t.maxQuantity ? t.maxQuantity : Number.MAX_SAFE_INTEGER;
                const tierMax = null !== tier.maxQuantity ? tier.maxQuantity : Number.MAX_SAFE_INTEGER;

                return (t.minQuantity >= tier.minQuantity && t.minQuantity <= tierMax)
                    || (tMax >= tier.minQuantity && tMax <= tierMax)
                    || (t.minQuantity <= tier.minQuantity && tMax >= tierMax);
            }).length > 1;
        });

        if (overlappingTiers.length > 0) {
            this.validationMessage = alondra_tiered_pricing.text.error_overlapping_tier;
            return false;
        }

        if (this.rules.length === 0) {
            this.validationMessage = alondra_tiered_pricing.text.error_no_rule;
            return false;
        }

        // Filter for wrong rules. Criteria is that categories must be an array of integers greater than 0. And products must be an array of integers greater than 0.
        // And tags must be an array of integers greater than 0. And users must be an array of integers greater than 0. 
        // And roles must be an array of strings not empty.
        // And categories, products, tags, users, roles can't be empty at the same time.

        const wrongRules = this.rules.filter((rule) => {
            return rule.categories.filter((x) => isNaN(x) || x <= 0).length > 0
                || rule.products.filter((x) => isNaN(x) || x <= 0).length > 0
                || rule.tags.filter((x) => isNaN(x) || x <= 0).length > 0
                || rule.users.filter((x) => isNaN(x) || x <= 0).length > 0
                || rule.roles.filter((x) => !x || x.trim() === '').length > 0
                || (rule.categories.length === 0 && rule.products.length === 0 && rule.tags.length === 0 && rule.users.length === 0 && rule.roles.length === 0);
        });

        if (wrongRules.length > 0) {
            this.validationMessage = alondra_tiered_pricing.text.error_wrong_rule;
            return false;
        }

        return true;
    }

    /**
     * Create DTO in status from current context
     * @param string status Default: publish
     * @returns TieredPricingDto
     */
    static makeFromContext(status = 'publish') {
        const tiers = [];
        document.querySelectorAll('#alondra-table-tiers .alondra-table__body >.alondra-table__row').forEach((row) => {
            const tier = {
                id: row.dataset.id ? parseInt(row.dataset.id) : 0,
                minQuantity: row.querySelector('input[name="min_quantity"]').value.trim(),
                maxQuantity: row.querySelector('input[name="max_quantity"]').value.trim(),
                value: parseFloat(row.querySelector('input[name="value"]').value)
            };
            tier.minQuantity = tier.minQuantity ? parseInt(tier.minQuantity) : 1;
            tier.maxQuantity = tier.maxQuantity ? parseInt(tier.maxQuantity) : null;
            tiers.push(tier);
        });
        const rules = [];
        document.querySelectorAll('#alondra-table-rules .alondra-table__body > .alondra-table__row > .alondra-table__rule').forEach((rule) => {
            rules.push({
                // Names the stored rule this one updates, so the server merges onto it instead of
                // rebuilding the row from a payload this UI does not render every column of.
                id: rule.closest('.alondra-table__row').dataset.id ? parseInt(rule.closest('.alondra-table__row').dataset.id) : 0,
                products: rule.querySelector('input[name="products"]').value.split(',').filter((x) => x ? x.trim() : null).map((x) => parseInt(x)),
                categories: rule.querySelector('input[name="categories"]').value.split(',').filter((x) => x ? x.trim() : null).map((x) => parseInt(x)),
                tags: rule.querySelector('input[name="tags"]').value.split(',').filter((x) => x ? x.trim() : null).map((x) => parseInt(x)),
                users: rule.querySelector('input[name="users"]').value.split(',').filter((x) => x ? x.trim() : null).map((x) => parseInt(x)),
                roles: rule.querySelector('input[name="profiles"]').value.split(',').filter((x) => x ? x.trim() : null),
            });
        });
        return new TieredPricingDto(
            alondra_tiered_pricing.entity.id,
            document.querySelector('.alondra-input[name="title"]').value.trim(),
            status,
            tiers,
            rules
        );
    }
}