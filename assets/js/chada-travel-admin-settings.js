/**
 * Progressive enhancement for the Chada Travel - Agency Manager Settings page: the General tab's Company Logo Media
 * Library control and Company Status/Maintenance Message toggle, and the Payment Method tab's Bank Account
 * repeater (add/remove/reorder Bank Accounts), Digital Wallet QR Media Library selection, and Copy
 * Webhook URL button. Every control here already works with JavaScript disabled - editing text fields
 * directly, leaving a Bank Account slot blank to drop it, or always seeing the Maintenance Message field -
 * this script only avoids extra page reloads and hides what is not currently relevant.
 */
(function () {
    'use strict';

    function wireBankRepeater() {
        var repeater = document.querySelector('[data-chada-travel-bank-repeater]');
        if (!repeater) {
            return;
        }
        var rows = Array.prototype.slice.call(repeater.querySelectorAll('[data-chada-travel-bank-row]'));
        var addButton = repeater.querySelector('[data-chada-travel-add-bank-account]');

        function rowHasData(row) {
            return Array.prototype.some.call(row.querySelectorAll('input[type="text"], textarea'), function (field) {
                return field.value.trim() !== '';
            });
        }

        function updateAddButtonVisibility() {
            if (addButton) {
                addButton.hidden = false;
                addButton.disabled = false;
                addButton.setAttribute('aria-disabled', 'false');
            }
        }

        function bindRow(row) {
            var removeButton = row.querySelector('[data-chada-travel-remove-bank-account]');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    if (rowHasData(row) && !window.confirm('Remove this Bank Account? Its fields will be cleared.')) {
                        return;
                    }
                    row.querySelectorAll('input[type="text"], textarea').forEach(function (field) {
                        field.value = '';
                    });
                    var activeCheckbox = row.querySelector('input[type="checkbox"]');
                    if (activeCheckbox) {
                        activeCheckbox.checked = false;
                    }
                    row.hidden = true;
                    updateAddButtonVisibility();
                });
            }

            row.querySelectorAll('[data-chada-travel-bank-move]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var direction = button.getAttribute('data-chada-travel-bank-move');
                    var index = rows.indexOf(row);
                    var targetIndex = direction === 'up' ? index - 1 : index + 1;
                    if (targetIndex < 0 || targetIndex >= rows.length) {
                        return;
                    }
                    var targetRow = rows[targetIndex];
                    swapSortOrder(row, targetRow);
                    if (direction === 'up') {
                        repeater.insertBefore(row, targetRow);
                    } else {
                        repeater.insertBefore(targetRow, row);
                    }
                    rows = Array.prototype.slice.call(repeater.querySelectorAll('[data-chada-travel-bank-row]'));
                });
            });
        }

        if (addButton) {
            addButton.addEventListener('click', function () {
                var nextHidden = rows.find(function (row) {
                    return row.hidden;
                });
                if (!nextHidden) {
                    var template = rows[rows.length - 1];
                    if (!template) return;
                    nextHidden = template.cloneNode(true);
                    var nextIndex = rows.length;
                    nextHidden.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(/\[\d+\]/, '[' + nextIndex + ']');
                    });
                    nextHidden.querySelectorAll('[id], label[for]').forEach(function (field) {
                        var attribute = field.hasAttribute('for') ? 'for' : 'id';
                        field.setAttribute(attribute, field.getAttribute(attribute).replace(/-\d+$/, '-' + nextIndex));
                    });
                    nextHidden.querySelectorAll('input[type="text"], textarea').forEach(function (field) {
                        field.value = '';
                    });
                    var idField = nextHidden.querySelector('input[type="hidden"][name$="[id]"]');
                    if (idField) idField.value = '';
                    var sortField = nextHidden.querySelector('.chada-travel-bank-sort-order');
                    if (sortField) sortField.value = String(nextIndex + 1);
                    nextHidden.querySelectorAll('input[type="checkbox"]').forEach(function (field) {
                        field.checked = false;
                    });
                    var heading = nextHidden.querySelector('h3');
                    if (heading) heading.textContent = 'Bank Account ' + (nextIndex + 1);
                    repeater.appendChild(nextHidden);
                    rows.push(nextHidden);
                    bindRow(nextHidden);
                }
                nextHidden.hidden = false;
                updateAddButtonVisibility();
                var firstField = nextHidden.querySelector('input[type="text"]');
                if (firstField) firstField.focus();
            });
        }

        rows.forEach(bindRow);

        updateAddButtonVisibility();
    }

    function swapSortOrder(rowA, rowB) {
        var fieldA = rowA.querySelector('.chada-travel-bank-sort-order');
        var fieldB = rowB.querySelector('.chada-travel-bank-sort-order');
        if (!fieldA || !fieldB) {
            return;
        }
        var valueA = fieldA.value;
        fieldA.value = fieldB.value;
        fieldB.value = valueA;
    }

    /**
     * Reusable wp.media() single-image picker wiring, shared by the Digital Wallet QR control and the Company
     * Logo control: opens the Media Library, writes the chosen attachment id into a hidden input, clears a
     * "remove" flag hidden input, and updates a preview <img>. `selectors` are queried relative to `wrapper` so
     * each caller can use its own existing data-attribute names. `options.toggleVisibility` opts into also
     * toggling the preview image's/empty-notice's `hidden` attribute (the Company Logo control has no bundled
     * placeholder image, unlike Digital Wallet QR, so only it needs this). `options.placeholderUrl` opts into
     * swapping the preview `src` back to the bundled placeholder on Remove instead (Digital Wallet QR always
     * shows an image - real or placeholder - so hiding it would be wrong; without this the preview silently kept
     * showing the just-removed real QR until the next full page load).
     */
    function wireMediaControl(wrapper, selectors, options) {
        if (!wrapper || typeof window.wp === 'undefined' || !window.wp.media) {
            return;
        }
        options = options || {};
        var selectButton = wrapper.querySelector(selectors.select);
        var removeButton = wrapper.querySelector(selectors.remove);
        var preview = wrapper.querySelector(selectors.preview);
        var input = wrapper.querySelector(selectors.input);
        var removeInput = wrapper.querySelector(selectors.removeInput);
        var emptyNotice = selectors.emptyNotice ? wrapper.querySelector(selectors.emptyNotice) : null;
        var frame = null;

        if (selectButton) {
            selectButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (!frame) {
                    frame = window.wp.media({
                        title: options.title || 'Select an image',
                        button: { text: options.button || 'Use this image' },
                        library: { type: 'image' },
                        multiple: false,
                    });
                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        if (input) {
                            input.value = attachment.id;
                        }
                        if (removeInput) {
                            removeInput.value = '0';
                        }
                        if (preview) {
                            preview.src = attachment.url;
                            if (options.toggleVisibility) {
                                preview.hidden = false;
                            }
                        }
                        if (options.toggleVisibility && emptyNotice) {
                            emptyNotice.hidden = true;
                        }
                        if (removeButton) {
                            removeButton.hidden = false;
                        }
                        if (options.onSelect) {
                            options.onSelect(selectButton);
                        }
                        frame.close();
                    });
                }
                frame.open();
            });
        }

        if (removeButton) {
            removeButton.addEventListener('click', function () {
                if (input) {
                    input.value = '0';
                }
                if (removeInput) {
                    removeInput.value = '1';
                }
                if (options.toggleVisibility && preview) {
                    preview.hidden = true;
                    preview.src = '';
                }
                if (options.toggleVisibility && emptyNotice) {
                    emptyNotice.hidden = false;
                }
                if (options.placeholderUrl && preview) {
                    preview.src = options.placeholderUrl;
                }
                removeButton.hidden = true;
                if (options.onRemove) {
                    options.onRemove(selectButton);
                }
            });
        }
    }

    function wireDigitalWalletMediaPicker() {
        var wrapper = document.querySelector('[data-chada-travel-digital-wallet-qr]');
        if (!wrapper) {
            return;
        }
        var settings = window.chadaTravelAdminSettings || {};
        wireMediaControl(wrapper, {
            select: '[data-chada-travel-select-digital-wallet-qr]',
            remove: '[data-chada-travel-remove-digital-wallet-qr]',
            preview: '[data-chada-travel-digital-wallet-qr-preview]',
            input: '[data-chada-travel-digital-wallet-qr-input]',
            removeInput: '[data-chada-travel-digital-wallet-qr-remove-input]',
        }, {
            title: settings.mediaTitle || 'Select Digital Wallet QR Code',
            button: settings.mediaButton || 'Use this image',
            placeholderUrl: settings.digitalWalletPlaceholderUrl || '',
        });
    }

    function wireCompanyLogoMediaPicker() {
        var wrapper = document.querySelector('[data-chada-travel-media-control]');
        if (!wrapper) {
            return;
        }
        var settings = window.chadaTravelAdminSettings || {};
        wireMediaControl(wrapper, {
            select: '[data-chada-travel-select-media]',
            remove: '[data-chada-travel-remove-media]',
            preview: '[data-chada-travel-media-preview]',
            input: '[data-chada-travel-media-input]',
            removeInput: '[data-chada-travel-media-remove-input]',
            emptyNotice: '[data-chada-travel-media-empty-notice]',
        }, {
            title: wrapper.getAttribute('data-chada-travel-media-title') || settings.logoMediaTitle || 'Select Company Logo',
            button: wrapper.getAttribute('data-chada-travel-media-button') || settings.logoMediaButton || 'Use this image',
            toggleVisibility: true,
            onSelect: function (selectButton) {
                var label = selectButton.getAttribute('data-chada-travel-replace-label') || settings.logoReplaceLabel;
                if (label) {
                    selectButton.textContent = label;
                }
            },
        });
    }

    /**
     * Toggles the always-rendered Maintenance Message row to visible only while Company Status is Under
     * Maintenance; the row is never server-side hidden (see CHADA_TRAVEL_Admin_Settings::render_availability_section())
     * so this is a pure visual enhancement, not something saving depends on.
     */
    function wireMaintenanceMessageToggle() {
        var options = Array.prototype.slice.call(document.querySelectorAll('[data-chada-travel-company-status-option]'));
        var row = document.querySelector('[data-chada-travel-maintenance-message-row]');
        if (!options.length || !row) {
            return;
        }
        function sync() {
            var selected = options.find(function (option) {
                return option.checked;
            });
            row.hidden = !selected || selected.value !== 'maintenance';
        }
        options.forEach(function (option) {
            option.addEventListener('change', sync);
        });
        sync();
    }

    function wireCopyWebhookUrl() {
        // One button per registered provider can be present on an extension page at once; each is wired
        // independently so copying one provider's URL never depends on which was inserted into the DOM first.
        var buttons = document.querySelectorAll('[data-chada-travel-copy-webhook-url]');
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var url = button.getAttribute('data-chada-travel-copy-webhook-url') || '';
                if (!url || !navigator.clipboard) {
                    return;
                }
                navigator.clipboard.writeText(url).then(function () {
                    var settings = window.chadaTravelAdminSettings || {};
                    var original = button.textContent;
                    button.textContent = settings.copiedMessage || 'Copied.';
                    window.setTimeout(function () {
                        button.textContent = original;
                    }, 2000);
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        wireBankRepeater();
        wireDigitalWalletMediaPicker();
        wireCompanyLogoMediaPicker();
        wireMaintenanceMessageToggle();
        wireCopyWebhookUrl();
    });
})();
