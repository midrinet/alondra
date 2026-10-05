import { Repeater } from "./Repeater";

/**
 * Repeater component.
 *
 * @since    1.0.0
 */
export class TierRepeater extends Repeater {

    constructor(selector, data = [], readonly = false) {
        // call parent constructor
        super(selector, data, (newRow, previousRow, data) => {
            if (data) {
                newRow.querySelector('input[name="min_quantity"]').value = data.min_units;
                newRow.querySelector('input[name="max_quantity"]').value = data.max_units;
                newRow.querySelector('input[name="value"]').value = data.value;
            }
            else if (previousRow) {
                const max = parseInt(previousRow.querySelector('input[name="max_quantity"]').value);
                if (!isNaN(max)) {
                    // set clone min_quantity the same as last row max_quantity + 1
                    newRow.querySelector('input[name="min_quantity"]').value = max + 1;
                }
            }

            if (readonly) {
                newRow.querySelectorAll('input,select').forEach(elem => elem.disabled = true);
            }
        }, null, readonly);
    }
}