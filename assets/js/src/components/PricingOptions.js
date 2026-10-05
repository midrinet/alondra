/**
 * Pricing options component.
 *
 * @since    1.0.0
 */
export class PricingOptions {

    constructor(args) {
        this.elem = args.elem;
        if (!this.elem) return;

        this.input = document.querySelector('input[name="quantity"]');
        if (this.input) {
            this.lastValue = 0;
            this.#onQuantityChange();
            this.input.addEventListener('change', this.#onQuantityChange);
            this.input.addEventListener('focusout', this.#onQuantityChange);
        }
        this.#options().forEach(option => option.addEventListener('click', this.#onOptionClick));
    }

    #onOptionClick = (e) => {
        const option = e.currentTarget;
        if (
            'undefined' === typeof alondra.is_clickable_class ||
            'undefined' === typeof option.dataset.minUnits ||
            !option.classList.contains(alondra.is_clickable_class) ||
            !this.input) {
            return;
        }
        this.input.value = parseInt(option.dataset.minUnits);
        this.#onQuantityChange();
    }

    #options = () => {
        return this.elem.querySelectorAll(`.${alondra.option_class}`);
    }

    #onQuantityChange = () => {
        const currentValue = parseInt(this.input.value);

        if (isNaN(currentValue)) {
            this.lastValue = null;
            return;
        }

        if (currentValue === this.lastValue) {
            return
        }

        this.lastValue = currentValue;
        this.#updateActiveOption();
    }

    #updateActiveOption = () => {
        this.#options().forEach(option => {
            if (option.dataset.minUnits <= this.lastValue && option.dataset.maxUnits >= this.lastValue) {
                option.classList.add(alondra.option_active_class);
            } else {
                option.classList.remove(alondra.option_active_class);
            }
        });
    }
}
