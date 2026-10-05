/**
 * JavaScript to Initialize Admin
 *
 * @var alondra wp_localize_script.
 */
import { Snackbar } from './components/Snackbar';
import { Modal } from './components/Modal';
import { CollapsableSection } from './components/CollapsableSection';
import { TierRepeater } from './components/TierRepeater';
import { RuleRepeater } from './components/RuleRepeater';
import { TieredPricingDto } from './dto/TieredPricingDto';

(function ($) {

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.alondra-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                if (!tab.classList.contains('alondra-active')) {
                    const active = document.querySelector('.alondra-tab.alondra-active');
                    if (null !== active) {
                        active.classList.remove('alondra-active');
                        document.querySelector(active.dataset.target).classList.remove('alondra-active');
                    }
                    tab.classList.add('alondra-active');
                    document.querySelector(tab.dataset.target).classList.add('alondra-active');
                }
            });
        });

        if ('undefined' !== typeof alondra_tiered_pricing) {
            // Init options menu actions.
            const menu = document.querySelector('.alondra-preferences__titlebar-options .alondra-options-menu');

            const showOptionsMenu = (isOpen) => {
                if (isOpen) {
                    menu.classList.add('alondra-options-menu--open');
                }
                else {
                    menu.classList.remove('alondra-options-menu--open');
                }
            };
            const toggleOptionsMenu = () => {
                menu.classList.toggle('alondra-options-menu--open');
            };

            const toggler = document.querySelector('.alondra-preferences__titlebar-options .alondra-options-menu__toggle');

            if (toggler) {
                document.addEventListener('click', (e) => {
                    if (!toggler.contains(e.target)) {
                        showOptionsMenu(false);
                    }
                    else if (toggler.contains(e.target)) {
                        toggleOptionsMenu();
                    }
                });
            }

            const barTitle = document.querySelector('.alondra-preferences__title');
            const draftButtons = document.querySelectorAll('.alondra-preferences__titlebar-options .alondra-draft-tiered-pricing');
            const publishButton = document.querySelector('.alondra-preferences__titlebar-options .alondra-publish-tiered-pricing');
            const trashButton = document.querySelector('.alondra-preferences__titlebar-options .alondra-trash-tiered-pricing');

            const restoreButton = document.querySelector('.alondra-preferences__titlebar-options .alondra-restore-tiered-pricing');
            const deleteButton = document.querySelector('.alondra-preferences__titlebar-options .alondra-delete-tiered-pricing');

            const getPublishString = () => {
                const entity = alondra_tiered_pricing.entity;
                if (!entity.id) {
                    return alondra_tiered_pricing.text.publish_new;
                }
                if ('publish' === entity.status) {
                    return alondra_tiered_pricing.text.publish_published;
                }
                return alondra_tiered_pricing.text.publish_draft;
            }

            // BEGIN: Title Bar Actions. ---------------------

            let uiState = 'ready';
            const setUiState = (state) => {
                uiState = state;
                if (publishButton) {
                    publishButton.textContent = getPublishString();
                }
                switch (state) {
                    case 'busy':
                        if (publishButton) {
                            publishButton.classList.add('alondra-busy');
                        }
                        draftButtons.forEach(button => button.disabled = true);

                        if (trashButton) {
                            trashButton.disabled = true;
                        }

                        if (deleteButton) {
                            deleteButton.classList.add('alondra-busy');
                        }
                        if (restoreButton) {
                            restoreButton.disabled = true;
                        }
                        break;
                    case 'ready':
                        if (publishButton) {
                            publishButton.classList.remove('alondra-busy');
                        }

                        if (deleteButton) {
                            deleteButton.classList.remove('alondra-busy');
                        }

                        draftButtons.forEach(button => button.disabled = false);

                        const isNew = !(alondra_tiered_pricing.entity && alondra_tiered_pricing.entity.id);

                        if (barTitle) {
                            if (isNew) {
                                barTitle.textContent = alondra_tiered_pricing.text.title_add;
                            } else if ('trash' === alondra_tiered_pricing.entity.status) {
                                barTitle.textContent = isNew ? alondra_tiered_pricing.text.title_add : alondra_tiered_pricing.text.title_view;
                            } else {
                                barTitle.textContent = isNew ? alondra_tiered_pricing.text.title_add : alondra_tiered_pricing.text.title_edit;
                            }
                        }
                        if (trashButton) {
                            trashButton.disabled = isNew;
                        }
                        if (restoreButton) {
                            restoreButton.disabled = isNew;
                        }
                        break;
                }
            };


            const trash = async function (e = null) {
                e && e.preventDefault();
                if (alondra_tiered_pricing.entity.id) {
                    // redirect to trash page.
                    window.location.href = alondra_tiered_pricing.url.trash + alondra_tiered_pricing.entity.id;
                }
            };
            const save = async function (status = 'publish') {
                const payload = TieredPricingDto.makeFromContext(status);

                if (!payload.isValid()) {
                    throw new Error(payload.validationMessage);
                }

                const response = await fetch(`${alondra.api.root}${alondra.api.namespace}/tiered-pricing/`, {
                    method: alondra_tiered_pricing.entity.id ? 'PUT' : 'POST',
                    mode: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': alondra.api.nonce,
                    },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                const data = await response.json();
                alondra_tiered_pricing.entity = data;
                // Write the ids the save minted back onto the rows. Without this a second save in the
                // same page session looks like a fresh set of children, so the server has nothing to
                // merge onto and blanks whatever column this UI does not render. The match is
                // positional because both lists come back in the order the request sent them.
                const syncRowIds = (selector, children) => {
                    document.querySelectorAll(selector).forEach((row, i) => {
                        if (children[i] && children[i].id) {
                            row.dataset.id = children[i].id;
                        }
                    });
                };
                syncRowIds('#alondra-table-tiers .alondra-table__body > .alondra-table__row', data.tiers ?? []);
                syncRowIds('#alondra-table-rules .alondra-table__body > .alondra-table__row', data.rules ?? []);
                // Update ID in the form.
                document.querySelector('.alondra-input[name="id"]').value = data.id;
                // Update the url
                const url = new URL(window.location.href);
                url.searchParams.set('action', 'edit');
                url.searchParams.set('id', data.id);
                window.history.pushState({}, '', url);

                // reset the prevent leaving.
                window.onbeforeunload = null;
            };
            const draft = async function (e = null) {
                e && e.preventDefault();


                const handleSaveDraft = async () => {
                    setUiState('busy');
                    const snackbar = new Snackbar();
                    try {
                        await save('draft');
                        snackbar.show(alondra_tiered_pricing.text.success_saved_draft, '✅', true, null, 5000, true);
                        setUiState('ready');
                        setUiState('drafted');
                    } catch (e) {
                        snackbar.show(e.message ?? alondra_tiered_pricing.text.error, '❗', false, null, null, true);
                        setUiState('ready');
                    }
                };

                if ('draft' === alondra_tiered_pricing.entity.status) {
                    return handleSaveDraft();
                }

                // show modal.
                new Modal(
                    alondra_tiered_pricing.text.modal_subtitle_save_draft,
                    alondra_tiered_pricing.text.modal_title_save_draft,
                    handleSaveDraft,
                    alondra_tiered_pricing.text.cancel
                ).show();
            };

            const publish = async function (e = null) {
                e && e.preventDefault();
                if (uiState === 'busy') {
                    return;
                }
                setUiState('busy');

                const snackbar = new Snackbar();

                try {
                    await save();
                    snackbar.show(alondra_tiered_pricing.text.success_published, '✅', true, null, 5000, true);
                    setUiState('ready');
                    setUiState('published');
                } catch (e) {
                    snackbar.show(e.message ?? alondra_tiered_pricing.text.error, '❗', false, null, null, true);
                    setUiState('ready');
                }
            };

            const restore = async function (e = null) {
                e && e.preventDefault();

                setUiState('busy');
                const snackbar = new Snackbar();
                try {
                    await save('draft');
                    snackbar.show(alondra_tiered_pricing.text.success_restored, '✅', true, null, 5000, true);
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } catch (e) {
                    snackbar.show(e.message ?? alondra_tiered_pricing.text.error, '❗', false, null, null, true);
                    setUiState('ready');
                }
            };

            const onDelete = async function (e = null) {
                e && e.preventDefault();

                const handleDelete = async () => {
                    setUiState('busy');
                    window.location.href = alondra_tiered_pricing.url.delete + alondra_tiered_pricing.entity.id;
                };

                // show modal.
                new Modal(
                    alondra_tiered_pricing.text.modal_subtitle_delete,
                    alondra_tiered_pricing.text.modal_title_delete,
                    handleDelete,
                    alondra_tiered_pricing.text.cancel
                ).show();
            };


            draftButtons.forEach((button) => button.addEventListener('click', draft));
            if (trashButton) {
                trashButton.addEventListener('click', trash);
            }
            if (publishButton) {
                publishButton.addEventListener('click', publish);
            }
            if (deleteButton) {
                deleteButton.addEventListener('click', onDelete);
            }
            if (restoreButton) {
                restoreButton.addEventListener('click', restore);
            }
            // END: Title Bar Actions. ---------------------

            const readonly = alondra_tiered_pricing.entity && alondra_tiered_pricing.entity.status === 'trash';

            const editSections = document.querySelectorAll('.alondra-edit-section');
            if (editSections.length > 0) {
                editSections.forEach(section => new CollapsableSection(section));

                const preventLeavingOnChange = (e) => {
                    window.onbeforeunload = function (e) {
                        return alondra.text.prevent_leaving;
                    };
                }

                // setup data.
                let tiers = [];
                let rules = [];
                if ('undefined' !== typeof alondra_tiered_pricing.entity) {
                    document.querySelector('.alondra-input[name="id"]').value = alondra_tiered_pricing.entity.id;
                    const inputTitle = document.querySelector('.alondra-input[name="title"]');
                    inputTitle.value = alondra_tiered_pricing.entity.title;


                    inputTitle.addEventListener('keyup', preventLeavingOnChange);

                    if (readonly) {
                        inputTitle.disabled = true;
                    }

                    tiers = alondra_tiered_pricing.entity.tiers;
                    rules = alondra_tiered_pricing.entity.rules;
                }

                document.addEventListener('alondra_repeater_row_added', function (e) {
                    const inputs = e.detail.row.querySelectorAll('[name]');
                    inputs.forEach((input) => {
                        input.addEventListener('change', preventLeavingOnChange);
                        input.addEventListener('keyup', preventLeavingOnChange);
                    });
                });

                new TierRepeater('#alondra-table-tiers', tiers, readonly);
                new RuleRepeater('#alondra-table-rules', rules, readonly);

                setUiState('ready');
            }
        }
    });
})(jQuery);