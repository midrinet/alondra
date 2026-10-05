import { PricingOptions } from "./components/PricingOptions";

// Initialize the stuff that requires DOM and resources are loaded.
(function ($) {
    document.addEventListener('DOMContentLoaded', () => {

        let pricingOpts = null;
        const initPricingOpts = (elem) => pricingOpts = elem ? new PricingOptions({ elem: elem }) : null;
        initPricingOpts(document.querySelector('.alondra-pricing__options'));

        $('.variations_form').on('woocommerce_variation_select_change', () => {
            const wrapper = document.querySelector(`.${alondra.wrapper_class}`)
            if (wrapper) {
                wrapper.innerHTML = '';
            }
        });

        $('.single_variation_wrap').on('show_variation', (event, variation) => {
            const template = document.querySelector(`.${alondra.variation_tiers_class}`);
            const wrapper = document.querySelector(`.${alondra.wrapper_class}`);
            if (!template || !wrapper) {
                return;
            }
            const tiersHtml = template.content.cloneNode(true).children[0];
            wrapper.appendChild(tiersHtml);
            template.remove();
            initPricingOpts(tiersHtml);
        });
    });
})(jQuery);