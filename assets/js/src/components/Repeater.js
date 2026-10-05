/**
 * Repeater component.
 *
 * @since    1.0.0
 */
export class Repeater {

    constructor(selector, data = [], onAddRow = null, onDeleteRow = null, readonly = false) {
        this.elem = document.querySelector(selector);
        if (!this.elem) return;
        this.readonly = readonly;
        this.checkAllCheckbox = this.elem.querySelector('.alondra-check-all');

        this.body = this.elem.querySelector('.alondra-table__body');
        this.template = this.body.querySelector('template');


        if (!readonly) {
            this.checkAllCheckbox.addEventListener('change', this.#changeAllChecks);
            this.elem.querySelector('.alondra-add').addEventListener('click', this.#addRow);
            this.elem.querySelector('.alondra-remove').addEventListener('click', this.#deleteRows);
        } else {
            this.checkAllCheckbox.disabled = true;
            this.elem.querySelector('.alondra-add').remove();
            this.elem.querySelector('.alondra-remove').remove();
        }

        this.onAddRow = onAddRow;
        this.onDeleteRow = onDeleteRow;

        data.forEach((row) => this.#addRow(null, row));

        this.#checkboxes().forEach((checkbox) => {
            if (readonly) {
                checkbox.disabled = true;
                return;
            }
            checkbox.addEventListener('change', this.#uncheckCheckAll)
        });
    }

    #checkboxes = () => this.elem.querySelectorAll('.alondra-table__body >.alondra-table__row input[type="checkbox"]:first-child');

    #changeAllChecks = (e) => {
        if ('undefined' !== typeof e?.preventDefault) {
            e?.preventDefault();
        }
        this.#checkboxes().forEach((checkbox) => {
            checkbox.checked = e.target.checked

            if (e.target.checked) {
                checkbox.closest('.alondra-table__row').classList.add('alondra-table__row--selected');
            } else {
                checkbox.closest('.alondra-table__row').classList.remove('alondra-table__row--selected');
            }
        });
    };

    #uncheckCheckAll = (e) => {
        if ('undefined' !== typeof e?.preventDefault) {
            e?.preventDefault();
        }
        // get parent with class .alondra-table__row
        const ch = this.#checkboxes();
        if (!ch) return;
        // check if all checkboxes are checked
        let allChecked = true;
        for (const checkbox of ch) {
            if (!checkbox.checked) {
                if (allChecked) {
                    allChecked = false;
                }
                checkbox.closest('.alondra-table__row').classList.remove('alondra-table__row--selected');
                // break;
            } else {
                checkbox.closest('.alondra-table__row').classList.add('alondra-table__row--selected');
            }
        }
        this.checkAllCheckbox.checked = allChecked;
    };

    #addRow = (e, data = null) => {
        if ('undefined' !== typeof e?.preventDefault) {
            e?.preventDefault();
        }
        // clone template
        const clone = this.template.cloneNode(true);
        const row = clone.content.querySelector('.alondra-table__row');
        // get last row
        const lastRow = this.body.querySelector('.alondra-table__row:last-child');
        // set checkbox change event
        row.querySelector('input[type="checkbox"]:first-child').addEventListener('change', this.#uncheckCheckAll);
        this.checkAllCheckbox.checked = false;

        // The id rides on the row so the save payload can name the stored child it updates. Without it
        // every save looks like a fresh set of children, and the server has nothing to merge a partial
        // update onto -- it would blank whatever column this UI does not render.
        if (data && data.id) {
            row.dataset.id = data.id;
        }

        if (this.onAddRow) {
            this.onAddRow(row, lastRow, data);
        }

        // append to .alondra-table__body
        this.body.appendChild(row);

        document.dispatchEvent(new CustomEvent('alondra_repeater_row_added', { detail: { row: row } }));
    };

    #deleteRows = (e) => {
        if ('undefined' !== typeof e?.preventDefault) {
            e?.preventDefault();
        }
        this.#checkboxes().forEach((checkbox) => {
            if (checkbox.checked) {
                checkbox.closest('.alondra-table__row').remove();
            }
        });
        this.checkAllCheckbox.checked = false;
    }


}