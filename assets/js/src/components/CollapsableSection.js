/**
 * Collapsable Section component.
 *
 * @since    1.0.0
 */
export class CollapsableSection {

    constructor(elem) {
        this.elem = elem;
        if (!this.elem) return;
        const toggle = this.elem.querySelector('.alondra-edit-section__toggle');
        toggle && toggle.addEventListener('click', this.#toggleSection);
    }

    #toggleSection = (e = null) => {
        e && e.preventDefault();
        this.elem.classList.toggle('alondra-edit-section--open');
    };
}