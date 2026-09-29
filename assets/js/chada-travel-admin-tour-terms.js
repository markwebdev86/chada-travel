(function () {
    'use strict';

    var data = window.chadaTravelTourTerms || {};
    var records = data.records || [];
    var form = document.getElementById('chada-travel-term-form');
    var fields = document.getElementById('chada-travel-term-form-fields');
    var addButton = document.getElementById('chada-travel-add-tour-term');
    var cancelButton = document.getElementById('chada-travel-cancel-tour-term');
    var editButton = document.getElementById('chada-travel-edit-tour-term');
    var saveButton = document.getElementById('chada-travel-save-tour-term');
    var title = document.getElementById('chada-travel-term-form-title');
    var idField = document.getElementById('chada-travel-term-id');
    var nameField = document.getElementById('chada-travel-term-name');
    var deleteModal = document.getElementById('chada-travel-tour-term-delete-modal');
    var deleteConfirm = deleteModal && deleteModal.querySelector('[data-chada-travel-delete-term-confirm]');
    var deleteCancelButtons = deleteModal ? deleteModal.querySelectorAll('[data-chada-travel-delete-term-cancel]') : [];
    var deleteReturnFocus = null;
    var deleteTargetForm = null;

    function findRecord(id) {
        return records.find(function (record) { return String(record.id) === String(id); });
    }

    function clearSelectedTourTermRow() {
        document.querySelectorAll('[data-chada-travel-tour-term-row]').forEach(function (row) {
            row.classList.remove('chada-travel-tour-row--selected');
            row.removeAttribute('aria-selected');
        });
    }

    function selectTourTermRow(id) {
        clearSelectedTourTermRow();
        if (!id) return;
        document.querySelectorAll('[data-chada-travel-tour-term-row]').forEach(function (row) {
            if (String(row.dataset.chadaTravelTourTermRow) !== String(id)) return;
            row.classList.add('chada-travel-tour-row--selected');
            row.setAttribute('aria-selected', 'true');
        });
    }

    function setVisible(visible) {
        if (form) form.hidden = !visible;
        if (visible && nameField) nameField.focus();
    }

    function setMode(mode) {
        if (!form) return;
        var viewMode = mode === 'view';
        form.dataset.chadaTravelTermFormMode = mode;
        if (title) title.textContent = mode === 'add' ? 'Add Record' : (viewMode ? 'View Record' : 'Edit Record');
        if (saveButton) saveButton.hidden = viewMode;
        if (editButton) editButton.hidden = !viewMode;
        if (nameField) {
            nameField.disabled = viewMode;
            nameField.readOnly = viewMode;
        }
    }

    function resetForm() {
        if (!fields) return;
        clearSelectedTourTermRow();
        fields.reset();
        if (idField) idField.value = '0';
        if (nameField) nameField.value = '';
        setMode('add');
        setVisible(true);
    }

    function populateForm(record, mode) {
        if (!record || !fields) return;
        if (idField) idField.value = String(record.id || 0);
        if (nameField) nameField.value = record.name || '';
        setMode(mode);
        setVisible(true);
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
        document.querySelectorAll('[data-delete-tour-term]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearSelectedTourTermRow();
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

    document.addEventListener('DOMContentLoaded', function () {
        if (form) setMode(form.dataset.chadaTravelTermFormMode || 'add');
        bindDelete();
        if (addButton) addButton.addEventListener('click', resetForm);
        if (cancelButton) cancelButton.addEventListener('click', function () {
            clearSelectedTourTermRow();
            setVisible(false);
        });
        if (editButton) editButton.addEventListener('click', function () { setMode('edit'); });
        document.querySelectorAll('[data-chada-travel-tour-term-row] form').forEach(function (actionForm) {
            actionForm.addEventListener('submit', clearSelectedTourTermRow);
        });
        document.querySelectorAll('[data-edit-tour-term]').forEach(function (button) {
            button.addEventListener('click', function () {
                var record = findRecord(button.dataset.editTourTerm);
                if (!record) return;
                selectTourTermRow(button.dataset.editTourTerm);
                populateForm(record, 'edit');
            });
        });
        document.querySelectorAll('[data-view-tour-term]').forEach(function (button) {
            button.addEventListener('click', function () {
                var record = findRecord(button.dataset.viewTourTerm);
                if (!record) return;
                selectTourTermRow(button.dataset.viewTourTerm);
                populateForm(record, 'view');
            });
        });
        var initialSelectedTermId = data.selectedTermId || (idField && idField.value && idField.value !== '0' ? idField.value : 0);
        if (initialSelectedTermId) selectTourTermRow(initialSelectedTermId);
    });
}());
