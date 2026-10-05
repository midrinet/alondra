/**
 * Snackbar component.
 * TODO: Move to toolkit
 */
export class Snackbar {

    constructor() {
        this.class = 'alondra-snackbar';
        this.classHide = 'alondra-snackbar--hide';
        this.classIcon = 'alondra-snackbar__icon';
        this.classMessage = 'alondra-snackbar__message';
        this.classDismiss = 'alondra-snackbar__dismiss';
    }

    get() {
        // create snackbar div if not exists
        let elem = document.querySelector(`.${this.class}`);
        if (!elem) {
            elem = document.createElement('div');
            elem.classList.add(this.class, this.classHide);
            document.body.appendChild(elem);
        }
        return elem;
    }

    show(
        message,
        utf8Icon = null,
        dismissible = true,
        onDismiss = null,
        timeout = 3000,
        explicitDismissible = false,
        onExplicitDismiss = null,
    ) {
        let snackbar = this.get();
        // The element is shared, so a pending auto-hide from an earlier call would hide this one.
        clearTimeout(snackbar.hideTimer);

        // prepare html content.
        let html = '';
        if (utf8Icon) {
            html += `<span class="${this.classIcon}" aria-label="Icon" role="img" style="fontSize: 21">${utf8Icon}</span>`;
        }
        html += `<span class="${this.classMessage}">${message}</span>`;
        if (explicitDismissible) {
            html += `<span class="${this.classDismiss}" aria-label="Dismiss" role="button">✕</span>`;
        }
        snackbar.innerHTML = html;

        // dismiss snackbar
        if (dismissible) {
            snackbar.hideTimer = setTimeout(() => {
                snackbar.classList.add(this.classHide);
                // snackbar.classList.remove(this.classShow);
                onDismiss && onDismiss();
            }, timeout);
        }
        if (explicitDismissible) {
            snackbar.querySelector(`.${this.classDismiss}`).addEventListener('click', (ev) => {
                ev.stopPropagation();
                // Drop the pending show, or a dismiss landing before it fires pops the snackbar back up.
                clearTimeout(snackbar.showTimer);
                clearTimeout(snackbar.hideTimer);
                snackbar.classList.add(this.classHide);
                // snackbar.classList.remove(this.classShow);

                onExplicitDismiss && onExplicitDismiss();
            });
        }

        let wait = 1; // wait at least for 1ms to allow animation correctly show.
        // check if snackbar is already shown
        if (!snackbar.classList.contains(this.classHide)) {
            // if snackbar is already shown, then hide it first
            snackbar.classList.add(this.classHide);
            wait = 300;
        }
        // then show it
        snackbar.showTimer = setTimeout(() => snackbar.classList.remove(this.classHide), wait);
    }
}