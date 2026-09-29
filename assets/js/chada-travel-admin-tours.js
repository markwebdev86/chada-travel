(function () {
    'use strict';

    var data = window.chadaTravelTours || {};
    var records = data.records || [];
    var form = document.getElementById('chada-travel-tour-form');
    var formFields = document.getElementById('chada-travel-tour-form-fields');
    var addButton = document.getElementById('chada-travel-add-tour');
    var cancelButton = document.getElementById('chada-travel-cancel-tour');
    var editButton = document.getElementById('chada-travel-edit-tour');
    var duplicateButton = document.getElementById('chada-travel-duplicate-tour');
    var title = document.getElementById('chada-travel-tour-form-title');
    var tourId = document.getElementById('chada-travel-tour-id');
    var saveButton = document.getElementById('chada-travel-save-tour');
    var nameField = document.getElementById('chada-travel-tour-tour_name');
    var slugField = document.getElementById('chada-travel-tour-tour_slug');
    var urlControl = document.querySelector('.chada-travel-tour-url-control');
    var urlPreview = document.getElementById('chada-travel-tour-url-preview');
    var urlOpen = document.getElementById('chada-travel-tour-url-open');
    var durationOutput = document.getElementById('chada-travel-tour-duration');
    var duplicateModal = document.getElementById('chada-travel-tour-duplicate-modal');
    var duplicateForm = document.getElementById('chada-travel-tour-duplicate-form');
    var duplicateId = document.getElementById('chada-travel-tour-duplicate-id');
    var duplicateConfirm = duplicateModal && duplicateModal.querySelector('[data-chada-travel-duplicate-confirm]');
    var duplicateCancelButtons = duplicateModal ? duplicateModal.querySelectorAll('[data-chada-travel-duplicate-cancel]') : [];
    var duplicateReturnFocus = null;
    var deleteModal = document.getElementById('chada-travel-tour-delete-modal');
    var deleteConfirm = deleteModal && deleteModal.querySelector('[data-chada-travel-delete-confirm]');
    var deleteCancelButtons = deleteModal ? deleteModal.querySelectorAll('[data-chada-travel-delete-cancel]') : [];
    var deleteReturnFocus = null;
    var deleteTargetForm = null;
    var editorNames = ['description', 'trip_includes', 'trip_excludes', 'basic_visa_requirements', 'itinerary', 'booking_conditions'];
    var slugAutoMode = !(slugField && slugField.value);

    function pad(value) {
        return String(value).length === 1 ? '0' + value : String(value);
    }

    function parseDate(value) {
        var parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some(function (part) { return !part; })) return null;
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function formatDate(value) {
        var date = value instanceof Date ? value : parseDate(value);
        if (!date || Number.isNaN(date.getTime())) return '';
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function addDays(value, days) {
        var date = parseDate(value);
        if (!date) return '';
        date.setDate(date.getDate() + days);
        return formatDate(date);
    }

    function calculateDuration(startValue, endValue) {
        var start = parseDate(startValue);
        var end = parseDate(endValue);
        if (!start || !end) return '';
        var startUtc = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
        var endUtc = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());
        var nights = Math.abs(Math.round((endUtc - startUtc) / 86400000));
        var days = nights + 1;
        return days + ' ' + (days === 1 ? 'Day' : 'Days') + ' ' + nights + ' ' + (nights === 1 ? 'Night' : 'Nights');
    }

    function updateDuration() {
        if (!durationOutput) return;
        var rows = getDateRows();
        var inputs = rows.length ? getDateInputs(rows[0]) : {};
        var duration = inputs.start && inputs.end ? calculateDuration(inputs.start.value, inputs.end.value) : '';
        durationOutput.textContent = duration || durationOutput.dataset.emptyText || '—';
    }

    function findRecord(id) {
        return records.find(function (record) { return String(record.id) === String(id); });
    }

    function clearSelectedTourRow() {
        document.querySelectorAll('[data-chada-travel-tour-row]').forEach(function (row) {
            row.classList.remove('chada-travel-tour-row--selected');
            row.removeAttribute('aria-selected');
        });
    }

    function selectTourRow(id) {
        clearSelectedTourRow();
        if (!id) return;
        document.querySelectorAll('[data-chada-travel-tour-row]').forEach(function (row) {
            if (String(row.dataset.chadaTravelTourRow) !== String(id)) return;
            row.classList.add('chada-travel-tour-row--selected');
            row.setAttribute('aria-selected', 'true');
        });
    }

    function slugify(value) {
        var slug = String(value || '').trim().toLowerCase();
        if (typeof slug.normalize === 'function') slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return slug.replace(/[^a-z0-9 _-]/g, '').replace(/[ _-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 200);
    }

    function updateTourUrl() {
        if (!slugField || !urlControl) return;
        var prefix = urlControl.dataset.chadaTravelTourUrlPrefix || data.permalinkPrefix || '';
        var suffix = urlControl.dataset.chadaTravelTourUrlSuffix || data.permalinkSuffix || '';
        var slug = slugify(slugField.value);
        var url = slug ? prefix + encodeURIComponent(slug) + suffix : '';
        if (urlPreview) {
            urlPreview.textContent = url;
            urlPreview.hidden = !url;
            if (url) urlPreview.href = url;
            else urlPreview.removeAttribute('href');
        }
        if (urlOpen) {
            urlOpen.hidden = !url;
            urlOpen.setAttribute('aria-hidden', url ? 'false' : 'true');
            if (url) urlOpen.href = url;
            else urlOpen.removeAttribute('href');
        }
    }

    function updateGeneratedSlug() {
        if (!nameField || !slugField || !slugAutoMode || !form || form.dataset.chadaTravelTourFormMode === 'view') return;
        slugField.value = slugify(nameField.value);
        updateTourUrl();
    }

    function normalizeSlugField() {
        if (!slugField) return;
        slugField.value = slugify(slugField.value);
        updateTourUrl();
    }

    function setVisible(visible) {
        if (!form) return;
        form.hidden = !visible;
        if (visible) {
            var nameField = document.getElementById('chada-travel-tour-tour_name');
            if (nameField) nameField.focus();
        }
    }

    function closeDuplicateModal() {
        if (!duplicateModal) return;
        duplicateModal.hidden = true;
        if (duplicateReturnFocus && typeof duplicateReturnFocus.focus === 'function') duplicateReturnFocus.focus();
        duplicateReturnFocus = null;
    }

    function openDuplicateModal(id, trigger) {
        if (!duplicateModal || !duplicateForm || !duplicateId || !id) return;
        duplicateId.value = String(id);
        duplicateReturnFocus = trigger || null;
        duplicateModal.hidden = false;
        var dialog = duplicateModal.querySelector('[role="dialog"]');
        if (dialog) dialog.focus();
    }

    function bindDuplicate() {
        if (!duplicateModal) return;
        document.querySelectorAll('[data-duplicate-tour]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearSelectedTourRow();
                openDuplicateModal(button.dataset.duplicateTour, button);
            });
        });
        if (duplicateButton) duplicateButton.addEventListener('click', function () {
            openDuplicateModal(tourId && tourId.value, duplicateButton);
        });
        Array.prototype.forEach.call(duplicateCancelButtons, function (button) {
            button.addEventListener('click', closeDuplicateModal);
        });
        if (duplicateConfirm) duplicateConfirm.addEventListener('click', function () {
            if (duplicateForm) duplicateForm.submit();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !duplicateModal.hidden) closeDuplicateModal();
        });
    }

    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.hidden = true;
        if (deleteReturnFocus && typeof deleteReturnFocus.focus === 'function') deleteReturnFocus.focus();
        deleteReturnFocus = null;
        deleteTargetForm = null;
    }

    function openDeleteModal(trigger) {
        if (!deleteModal || !trigger) return;
        var formId = trigger.dataset.deleteForm;
        deleteTargetForm = formId ? document.getElementById(formId) : trigger.closest('form');
        if (!deleteTargetForm) return;
        deleteReturnFocus = trigger;
        deleteModal.hidden = false;
        var dialog = deleteModal.querySelector('[role="dialog"]');
        if (dialog) dialog.focus();
    }

    function bindDelete() {
        if (!deleteModal) return;
        document.querySelectorAll('[data-delete-tour]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearSelectedTourRow();
                openDeleteModal(button);
            });
        });
        Array.prototype.forEach.call(deleteCancelButtons, function (button) {
            button.addEventListener('click', closeDeleteModal);
        });
        if (deleteConfirm) deleteConfirm.addEventListener('click', function () {
            if (deleteTargetForm) deleteTargetForm.submit();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && deleteModal && !deleteModal.hidden) closeDeleteModal();
        });
    }

    function getEditor(name) {
        return window.tinymce && window.tinymce.get('chada-travel-tour-editor-' + name);
    }

    function cleanPastedHtml(html) {
        var content = String(html || '').replace(/<!--[\s\S]*?-->/g, '')
            .replace(/<\/?(?:o:p|w:[^>\s]+)[^>]*>/gi, '');
        var container = document.createElement('div');
        container.innerHTML = content;
        Array.prototype.forEach.call(container.querySelectorAll('style, link[rel~="stylesheet"], meta'), function (node) {
            node.remove();
        });
        Array.prototype.forEach.call(container.querySelectorAll('*'), function (node) {
            node.removeAttribute('style');
            node.removeAttribute('data-mce-style');
            node.removeAttribute('xmlns');
            node.removeAttribute('xml:lang');
            var classes = String(node.getAttribute('class') || '').split(/\s+/).filter(function (className) {
                return className && !/^(?:Mso[\w-]*|mso-[\w-]*)$/i.test(className);
            });
            if (classes.length) node.setAttribute('class', classes.join(' '));
            else node.removeAttribute('class');
        });
        return container.innerHTML;
    }

    function bindEditorPasteCleanup(editor) {
        if (!editor || editor.chadaTravelTourPasteCleanupBound) return;
        editor.chadaTravelTourPasteCleanupBound = true;
        editor.on('PastePreProcess', function (event) {
            event.content = cleanPastedHtml(event.content);
        });
    }

    function bindEditorPasteCleanups() {
        if (!window.tinymce) return;
        editorNames.forEach(function (name) { bindEditorPasteCleanup(getEditor(name)); });
        if (typeof window.tinymce.on === 'function') {
            window.tinymce.on('AddEditor', function (event) {
                var editor = event && event.editor;
                if (editor && editor.id.indexOf('chada-travel-tour-editor-') === 0) bindEditorPasteCleanup(editor);
            });
        }
    }

    function setEditorValue(name, value, previewValue) {
        var textarea = formFields && formFields.querySelector('[name="' + name + '"]');
        var editor = getEditor(name);
        var content = String(value || '');
        if (textarea) textarea.value = content;
        if (editor) {
            editor.setContent(content);
            editor.save();
        }
        var preview = form && form.querySelector('[data-chada-travel-editor-preview="' + name + '"]');
        if (preview) preview.innerHTML = previewValue || content;
    }

    function getDateInputs(row) {
        var start = row.querySelector('.chada-travel-tour-start-date, input[name*="[start_date]"]');
        var end = row.querySelector('.chada-travel-tour-end-date, input[name*="[end_date]"]');
        if (start) start.classList.add('chada-travel-tour-start-date');
        if (end) end.classList.add('chada-travel-tour-end-date');
        return { start: start, end: end };
    }

    function getDateRows() {
        var container = document.getElementById('chada-travel-tour-dates');
        return container ? Array.prototype.slice.call(container.querySelectorAll('.chada-travel-tour-date-row')) : [];
    }

    function updateAddDateButton() {
        var container = document.getElementById('chada-travel-tour-dates');
        var button = document.getElementById('chada-travel-add-tour-date');
        if (!container || !button) return;
        var viewMode = form && form.dataset.chadaTravelTourFormMode === 'view';
        button.hidden = viewMode;
        button.disabled = viewMode;
        button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');
    }

    function updateDateRow(row, initial) {
        var inputs = getDateInputs(row);
        if (!inputs.start || !inputs.end) return;
        var viewMode = form && form.dataset.chadaTravelTourFormMode === 'view';
        var defaultStart = row.dataset.chadaTravelDefaultStart === '1';
        if (!inputs.start.value) {
            inputs.end.disabled = true;
            updateDuration();
            return;
        }
        if (viewMode || defaultStart) {
            inputs.end.disabled = true;
            return;
        }
        inputs.end.disabled = false;
        if (!inputs.end.value || inputs.end.dataset.chadaTravelAuto === '1') {
            inputs.end.value = addDays(inputs.start.value, 1);
            inputs.end.dataset.chadaTravelAuto = '1';
        }
        updateDuration();
    }

    function renumberDateRows() {
        getDateRows().forEach(function (row, index) {
            getDateInputs(row).start.name = 'travel_dates[' + index + '][start_date]';
            getDateInputs(row).end.name = 'travel_dates[' + index + '][end_date]';
            var dateId = row.querySelector('.chada-travel-tour-date-id');
            if (dateId) dateId.name = 'travel_dates[' + index + '][date_id]';
        });
    }

    function createDateRow(index, startValue, endValue, defaultStart, dateId) {
        var row = document.createElement('div');
        row.className = 'chada-travel-tour-date-row';
        if (defaultStart) row.dataset.chadaTravelDefaultStart = '1';
        var dateIdField = Number(dateId || 0) > 0
            ? '<input type="hidden" class="chada-travel-tour-date-id" name="travel_dates[' + index + '][date_id]" value="' + Number(dateId) + '">'
            : '';
        row.innerHTML = dateIdField + '<input type="date" class="chada-travel-tour-start-date" name="travel_dates[' + index + '][start_date]" value="' + (startValue || '') + '" required> <span>&ndash;</span> <input type="date" class="chada-travel-tour-end-date" name="travel_dates[' + index + '][end_date]" value="' + (endValue || '') + '" required> <button type="button" class="button-link-delete chada-travel-remove-tour-date">Remove</button>';
        return row;
    }

    function resetDateRows() {
        var container = document.getElementById('chada-travel-tour-dates');
        if (!container) return;
        container.innerHTML = '';
        var start = data.newStartDate || '';
        container.appendChild(createDateRow(0, start, '', true));
        updateDateRow(container.firstElementChild, true);
    }

    function populateDateRows(dateRanges) {
        var container = document.getElementById('chada-travel-tour-dates');
        if (!container) return;
        var ranges = Array.isArray(dateRanges) && dateRanges.length ? dateRanges : [{ start_date: data.newStartDate || '', end_date: '' }];
        container.innerHTML = '';
        ranges.forEach(function (range, index) {
            container.appendChild(createDateRow(index, range.start_date, range.end_date, false, range.date_id));
        });
        getDateRows().forEach(function (row) { updateDateRow(row, false); });
        updateAddDateButton();
    }

    function bindDateRows() {
        var container = document.getElementById('chada-travel-tour-dates');
        var addDateButton = document.getElementById('chada-travel-add-tour-date');
        if (!container || !addDateButton) return;

        container.addEventListener('click', function (event) {
            if (!event.target.classList.contains('chada-travel-remove-tour-date')) return;
            var rows = getDateRows();
            if (rows.length > 1) event.target.closest('.chada-travel-tour-date-row').remove();
            renumberDateRows();
            updateDuration();
            updateAddDateButton();
        });

        container.addEventListener('change', function (event) {
            var row = event.target.closest('.chada-travel-tour-date-row');
            if (!row) return;
            if (event.target.classList.contains('chada-travel-tour-start-date')) {
                row.dataset.chadaTravelDefaultStart = '0';
                updateDateRow(row, false);
            } else if (event.target.classList.contains('chada-travel-tour-end-date')) {
                event.target.dataset.chadaTravelAuto = '0';
                updateDuration();
            }
        });

        container.addEventListener('input', function (event) {
            if (event.target.classList.contains('chada-travel-tour-end-date')) {
                updateDuration();
                return;
            }
            if (!event.target.classList.contains('chada-travel-tour-start-date')) return;
            var row = event.target.closest('.chada-travel-tour-date-row');
            row.dataset.chadaTravelDefaultStart = '0';
            updateDateRow(row, false);
        });

        addDateButton.addEventListener('click', function () {
            var rows = getDateRows();
            var previous = rows[rows.length - 1];
            var previousInputs = previous ? getDateInputs(previous) : {};
            var previousDate = previousInputs.end && previousInputs.end.value;
            if (!previousDate && previousInputs.start) previousDate = previousInputs.start.value;
            var start = previousDate ? addDays(previousDate, 1) : (data.newStartDate || '');
            var row = createDateRow(rows.length, start, '', true);
            container.appendChild(row);
            updateDateRow(row, true);
            updateAddDateButton();
        });

        getDateRows().forEach(function (row) {
            var inputs = getDateInputs(row);
            if (form && form.dataset.chadaTravelTourFormMode === 'add' && inputs.start.value === data.newStartDate && !inputs.end.value) {
                row.dataset.chadaTravelDefaultStart = '1';
            }
            updateDateRow(row, true);
        });
        updateAddDateButton();
        updateDuration();
    }

    function setMediaState(attachmentId, previewUrl) {
        var idField = document.getElementById('chada-travel-tour-image-id');
        var preview = document.getElementById('chada-travel-tour-image-preview');
        var remove = document.getElementById('chada-travel-tour-remove-image');
        if (idField) idField.value = String(attachmentId || 0);
        if (previewUrl && !preview) {
            preview = document.createElement('img');
            preview.id = 'chada-travel-tour-image-preview';
            preview.width = 120;
            preview.height = 120;
            preview.alt = '';
            var media = document.querySelector('.chada-travel-tour-media');
            if (media) media.insertBefore(preview, media.firstChild);
        }
        if (preview) {
            if (previewUrl) {
                preview.src = previewUrl;
                preview.hidden = false;
            } else {
                preview.remove();
            }
        }
        if (remove) remove.hidden = !attachmentId || (form && form.dataset.chadaTravelTourFormMode === 'view');
    }

    function bindMedia() {
        var select = document.getElementById('chada-travel-tour-select-image');
        var remove = document.getElementById('chada-travel-tour-remove-image');
        if (!select || typeof wp === 'undefined' || !wp.media) return;
        var frame;
        select.addEventListener('click', function () {
            if (!frame) {
                frame = wp.media({ title: 'Select Tour Featured Image', button: { text: 'Use Image' }, multiple: false, library: { type: ['image/jpeg', 'image/png'] } });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    setMediaState(attachment.id, attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                });
            }
            frame.open();
        });
        if (remove) remove.addEventListener('click', function () { setMediaState(0, ''); });
    }

    function getFileRows() {
        var container = document.getElementById('chada-travel-tour-files');
        return container ? Array.prototype.slice.call(container.querySelectorAll('.chada-travel-tour-file-row')) : [];
    }

    function updateFileCount() {
        var container = document.getElementById('chada-travel-tour-files');
        var count = document.getElementById('chada-travel-tour-files-count');
        if (count) {
            count.textContent = getFileRows().length + ' files selected';
        }
        var select = document.getElementById('chada-travel-tour-select-files');
        if (select) {
            var viewMode = form && form.dataset.chadaTravelTourFormMode === 'view';
            select.disabled = viewMode;
            select.setAttribute('aria-disabled', select.disabled ? 'true' : 'false');
        }
    }

    function createFileRow(file) {
        var row = document.createElement('li');
        row.className = 'chada-travel-tour-file-row';
        row.dataset.attachmentId = String(file.id || 0);
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'downloadable_file_ids[]';
        input.value = String(file.id || 0);
        row.appendChild(input);
        var link = document.createElement(file.url ? 'a' : 'span');
        if (file.url) {
            link.href = file.url;
            link.download = file.name || ((data.strings && data.strings.unnamedFile) || 'download');
        }
        link.textContent = file.name || (data.strings && data.strings.unnamedFile) || 'Unnamed file';
        row.appendChild(link);
        var meta = document.createElement('span');
        meta.className = 'description';
        meta.textContent = file.mime || '';
        row.appendChild(meta);
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button-link-delete chada-travel-remove-tour-file';
        remove.textContent = (data.strings && data.strings.remove) || 'Remove';
        row.appendChild(remove);
        var up = document.createElement('button');
        up.type = 'button';
        up.className = 'button-link chada-travel-move-tour-file chada-travel-move-tour-file-up';
        up.setAttribute('aria-label', (data.strings && data.strings.moveUp) || 'Move file up');
        up.textContent = '↑';
        row.appendChild(up);
        var down = document.createElement('button');
        down.type = 'button';
        down.className = 'button-link chada-travel-move-tour-file chada-travel-move-tour-file-down';
        down.setAttribute('aria-label', (data.strings && data.strings.moveDown) || 'Move file down');
        down.textContent = '↓';
        row.appendChild(down);
        return row;
    }

    function populateFiles(files) {
        var container = document.getElementById('chada-travel-tour-files');
        if (!container) return;
        container.querySelectorAll('.chada-travel-tour-file-row').forEach(function (row) { row.remove(); });
        (Array.isArray(files) ? files : []).forEach(function (file) {
            if (file && file.id) container.appendChild(createFileRow(file));
        });
        updateFileCount();
    }

    function bindFiles() {
        var container = document.getElementById('chada-travel-tour-files');
        var select = document.getElementById('chada-travel-tour-select-files');
        if (!container || !select) return;
        container.addEventListener('click', function (event) {
            var row = event.target.closest('.chada-travel-tour-file-row');
            if (!row) return;
            if (event.target.classList.contains('chada-travel-remove-tour-file')) row.remove();
            if (event.target.classList.contains('chada-travel-move-tour-file-up') && row.previousElementSibling) {
                container.insertBefore(row, row.previousElementSibling);
            }
            if (event.target.classList.contains('chada-travel-move-tour-file-down') && row.nextElementSibling) {
                container.insertBefore(row.nextElementSibling, row);
            }
            updateFileCount();
        });
        if (typeof wp === 'undefined' || !wp.media) return;
        var frame;
        select.addEventListener('click', function () {
            if (select.disabled) {
                updateFileCount();
                return;
            }
            if (!frame) {
                frame = wp.media({
                    title: 'Select Tour Downloadable Files',
                    button: { text: (data.strings && data.strings.useFiles) || 'Use Files' },
                    multiple: true,
                    library: { type: data.downloadableMimeTypes || ['application/pdf', 'image/jpeg', 'image/png'] }
                });
                frame.on('select', function () {
                    var existing = getFileRows().map(function (row) { return row.dataset.attachmentId; });
                    var selected = frame.state().get('selection').toJSON();
                    selected.forEach(function (attachment) {
                        if (existing.indexOf(String(attachment.id)) !== -1) return;
                        var file = {
                            id: attachment.id,
                            name: attachment.filename || attachment.title || ((data.strings && data.strings.unnamedFile) || 'Unnamed file'),
                            url: attachment.url || '',
                            mime: attachment.mime || ''
                        };
                        container.appendChild(createFileRow(file));
                        existing.push(String(attachment.id));
                    });
                    updateFileCount();
                });
            }
            frame.open();
        });
        updateFileCount();
    }

    function setMode(mode) {
        if (!form) return;
        form.dataset.chadaTravelTourFormMode = mode;
        var viewMode = mode === 'view';
        if (title) title.textContent = mode === 'add' ? 'Add Tour' : (viewMode ? 'View Tour' : 'Edit Tour');
        if (saveButton) saveButton.hidden = viewMode;
        if (duplicateButton) duplicateButton.hidden = viewMode || mode !== 'edit' || !tourId || !tourId.value || tourId.value === '0';
        if (editButton) editButton.hidden = !viewMode;
        form.querySelectorAll('input, textarea, select').forEach(function (field) {
            if (field.type === 'hidden') return;
            field.disabled = viewMode;
            if (field.type !== 'checkbox' && field.type !== 'date') field.readOnly = viewMode;
        });
        form.querySelectorAll('.chada-travel-tour-image-control').forEach(function (control) { control.hidden = viewMode; });
        form.querySelectorAll('.chada-travel-tour-file-control, .chada-travel-remove-tour-file, .chada-travel-move-tour-file').forEach(function (control) { control.hidden = viewMode; });
        form.querySelectorAll('.chada-travel-remove-tour-date, #chada-travel-add-tour-date').forEach(function (control) { control.hidden = viewMode; });
        form.querySelectorAll('.chada-travel-tour-editor-field').forEach(function (field) {
            var control = field.querySelector('.chada-travel-tour-editor-control');
            var preview = field.querySelector('.chada-travel-tour-editor-preview');
            if (control) control.hidden = viewMode;
            if (preview) preview.hidden = !viewMode;
        });
        var recordMetadata = form.querySelector('[data-chada-travel-tour-record-metadata]');
        if (recordMetadata) recordMetadata.hidden = !tourId || !tourId.value || tourId.value === '0';
        getDateRows().forEach(function (row) { updateDateRow(row, false); });
        updateAddDateButton();
        var imageId = document.getElementById('chada-travel-tour-image-id');
        var imagePreview = document.getElementById('chada-travel-tour-image-preview');
        var removeImage = document.getElementById('chada-travel-tour-remove-image');
        if (removeImage) removeImage.hidden = !imageId || !imageId.value || viewMode;
        if (imagePreview) imagePreview.hidden = !(imageId && imageId.value && imagePreview.src);
        updateTourUrl();
        updateFileCount();
    }

    function resetForm() {
        if (!formFields) return;
        clearSelectedTourRow();
        formFields.reset();
        if (tourId) tourId.value = '0';
        ['tour_name', 'tour_code', 'price', 'currency'].forEach(function (name) {
            var field = document.getElementById('chada-travel-tour-' + name);
            if (field) field.value = name === 'currency' ? (data.defaultTourCurrency || 'USD') : '';
        });
        slugAutoMode = true;
        if (slugField) slugField.value = '';
        form.querySelectorAll('input[type="checkbox"]').forEach(function (field) { field.checked = false; });
        editorNames.forEach(function (name) { setEditorValue(name, '', ''); });
        setMediaState(0, '');
        populateFiles([]);
        resetDateRows();
        setMode('add');
        setVisible(true);
    }

    function populateForm(record, mode) {
        if (!record || !formFields) return;
        formFields.reset();
        if (tourId) tourId.value = String(record.id || 0);
        ['tour_name', 'tour_code', 'price', 'currency'].forEach(function (name) {
            var field = document.getElementById('chada-travel-tour-' + name);
            if (field) field.value = record[name] || '';
        });
        if (slugField) slugField.value = record.tour_slug || record.slug || '';
        slugAutoMode = !slugField || slugField.value === '';
        editorNames.forEach(function (name) { setEditorValue(name, record[name] || '', record.editor_html && record.editor_html[name]); });
        setMediaState(record.feature_image_attachment_id || 0, record.feature_image_url || '');
        var featuredField = document.getElementById('chada-travel-tour-featured');
        if (featuredField) featuredField.checked = Boolean(record.featured);
        populateFiles(record.downloadable_files || []);
        form.querySelectorAll('input[name="destination_ids[]"], input[name="type_ids[]"]').forEach(function (field) {
            var selected = field.name.indexOf('destination') !== -1 ? record.destination_ids : record.type_ids;
            field.checked = selected.map(String).indexOf(String(field.value)) !== -1;
        });
        populateDateRows(record.travel_dates || []);
        setMode(mode);
        setVisible(true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindEditorPasteCleanups();
        bindDateRows();
        bindMedia();
        bindFiles();
        bindDuplicate();
        bindDelete();
        if (form) setMode(form.dataset.chadaTravelTourFormMode || 'add');
        if (addButton) addButton.addEventListener('click', resetForm);
        if (cancelButton) cancelButton.addEventListener('click', function () {
            clearSelectedTourRow();
            setVisible(false);
        });
        if (editButton) editButton.addEventListener('click', function () { setMode('edit'); });
        if (nameField) {
            nameField.addEventListener('input', updateGeneratedSlug);
            nameField.addEventListener('keyup', updateGeneratedSlug);
        }
        if (slugField) {
            slugField.addEventListener('input', function () {
                slugAutoMode = String(slugField.value || '').trim() === '';
                updateTourUrl();
            });
            slugField.addEventListener('change', normalizeSlugField);
            slugField.addEventListener('blur', normalizeSlugField);
        }
        if (formFields) formFields.addEventListener('submit', normalizeSlugField);
        document.querySelectorAll('[data-chada-travel-tour-row] form').forEach(function (actionForm) {
            actionForm.addEventListener('submit', clearSelectedTourRow);
        });
        document.querySelectorAll('[data-edit-tour]').forEach(function (button) {
            button.addEventListener('click', function () {
                var record = findRecord(button.dataset.editTour);
                if (!record) return;
                selectTourRow(button.dataset.editTour);
                populateForm(record, 'edit');
            });
        });
        document.querySelectorAll('[data-view-tour]').forEach(function (button) {
            button.addEventListener('click', function () {
                var record = findRecord(button.dataset.viewTour);
                if (!record) return;
                selectTourRow(button.dataset.viewTour);
                populateForm(record, 'view');
            });
        });
        var initialSelectedTourId = data.selectedTourId || (tourId && tourId.value && tourId.value !== '0' ? tourId.value : 0);
        if (initialSelectedTourId) selectTourRow(initialSelectedTourId);
    });
}());
