/**
 * Modal component.
 * TODO: Move to toolkit
 */
export class Modal {

    constructor(
        message,
        acceptText = 'OK',
        onAccept = null,
        cancelText = null,
        onCancel = null
    ) {
        this.class = 'alondra-modal';
        this.classOverlay = 'alondra-modal__overlay';
        this.classAccept = 'alondra-modal__accept';
        this.classCancel = 'alondra-modal__cancel';

        this.message = message;
        this.acceptText = acceptText;
        this.onAccept = onAccept;
        this.cancelText = cancelText;
        this.onCancel = onCancel;
    }

    show() {
        let modal = document.createElement('div');
        modal.classList.add(this.classOverlay);

        // prepare html content.
        let html = '';
        html += `<div class="${this.class}">`;
        html += `<span>${this.message}</span>`;
        html += `<footer>`;
        // add cancel button if exists
        if (this.cancelText) {
            html += `<button class="button ${this.classCancel}" type="button">${this.cancelText}</button>`;
        }
        html += `<button class="button button-primary ${this.classAccept}" type="button">${this.acceptText}</button>`;
        html += `</footer>`;
        html += `</div>`;

        modal.innerHTML = html;

        const onAcceptClicked = (ev) => {
            ev.stopPropagation();
            this.onAccept && this.onAccept();
            hide();
        };

        const onCancelClicked = (ev) => {
            ev.stopPropagation();
            this.onCancel && this.onCancel();
            hide();
        };

        const hide = () => {
            // remove event listeners
            modal.querySelector(`.${this.classAccept}`).removeEventListener('click', onAcceptClicked);
            if (this.cancelText) {
                modal.querySelector(`.${this.classCancel}`).removeEventListener('click', onCancelClicked);
            }
            // remove modal from dom
            modal.remove();
        };

        // add event listeners
        modal.querySelector(`.${this.classAccept}`).addEventListener('click', onAcceptClicked);
        if (this.cancelText) {
            modal.querySelector(`.${this.classCancel}`).addEventListener('click', onCancelClicked);
        }

        // show modal
        document.body.appendChild(modal);
    }
}