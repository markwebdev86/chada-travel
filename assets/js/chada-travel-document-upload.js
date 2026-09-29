(() => {
  'use strict';

  const settings = window.chadaTravelDocumentUploadSettings || {};
  const strings = settings.strings || {};
  const app = document.querySelector('.chada-travel-documents-app');
  if (!app) return;

  const statusEl = app.querySelector('[data-chada-travel-documents-live-status]');
  const listContainer = app.querySelector('[data-chada-travel-documents-list]');
  const tokenField = app.querySelector('#chada-travel-documents-token');
  const token = tokenField ? tokenField.value : '';

  const announce = (message, isError = false) => {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('is-error', isError);
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
  }[character]));

  const formatBytes = (bytes) => (bytes >= 1048576
    ? `${(bytes / 1048576).toFixed(1).replace(/\.0$/, '')} MB`
    : `${Math.round(bytes / 1024)} KB`);

  const STATUS_LABELS = {
    chada_travel_missing: 'Missing', chada_travel_uploaded: 'Uploaded', chada_travel_under_review: 'Under Review',
    chada_travel_accepted: 'Accepted', chada_travel_rejected: 'Rejected', chada_travel_replacement_required: 'Replacement Required',
  };

  const requirementTemplate = (application, requirement) => {
    const hasFile = requirement.documentStatus !== 'chada_travel_missing';
    const actionLabel = hasFile
      ? (strings.replaceDocumentAction || 'Replace File') : (strings.uploadDocumentAction || 'Upload File');
    const requiredBadge = requirement.isRequired
      ? (strings.requirementRequiredLabel || 'Required') : (strings.requirementOptionalLabel || 'Optional');
    const currentFile = hasFile
      ? `${escapeHtml(requirement.filename || '')} (${escapeHtml(STATUS_LABELS[requirement.documentStatus] || requirement.documentStatus)})`
      : escapeHtml(strings.noFileYetLabel || 'No file uploaded yet');
    const reviewNote = requirement.reviewNote
      ? `<p class="chada-travel-status-note">${escapeHtml(requirement.reviewNote)}</p>` : '';

    return `<div class="chada-travel-requirement-row" data-chada-travel-requirement="${requirement.requirementId}" `
      + `data-chada-travel-application="${application.applicationId}">`
      + `<div class="chada-travel-requirement-row__info"><strong>${escapeHtml(requirement.label)}</strong> `
      + `<span class="chada-travel-badge">${escapeHtml(requiredBadge)}</span>`
      + `<p>${escapeHtml(requirement.description || '')}</p>`
      + `<p class="chada-travel-requirement-row__file">${currentFile}</p>${reviewNote}</div>`
      + '<div class="chada-travel-requirement-row__action">'
      + `<input type="file" accept="${escapeHtml((requirement.mimeTypes || []).join(','))}" `
      + 'data-chada-travel-requirement-file>'
      + `<button class="chada-travel-button" type="button" data-chada-travel-requirement-upload>${escapeHtml(actionLabel)}</button>`
      + '<p class="chada-travel-status-note" data-chada-travel-requirement-note></p>'
      + '</div></div>';
  };

  const applicationTemplate = (application) => '<article class="chada-travel-panel chada-travel-panel--spaced">'
    + `<div class="chada-travel-panel__header"><h3>${escapeHtml(application.countryName)} - `
    + `${escapeHtml(application.applicantName)}</h3></div>`
    + `<div class="chada-travel-panel__body">${application.requirements.map(
      (requirement) => requirementTemplate(application, requirement)
    ).join('')}</div></article>`;

  const bindUploadButtons = () => {
    listContainer.querySelectorAll('[data-chada-travel-requirement-upload]').forEach((button) => {
      button.addEventListener('click', async () => {
        const row = button.closest('[data-chada-travel-requirement]');
        const fileInput = row.querySelector('[data-chada-travel-requirement-file]');
        const note = row.querySelector('[data-chada-travel-requirement-note]');
        if (!fileInput.files || !fileInput.files.length) {
          note.textContent = strings.documentUploadFailedNotice || 'Choose a file to upload.';
          note.classList.add('is-error');
          return;
        }
        const formData = new FormData();
        formData.append('token', token);
        formData.append('application_id', row.dataset.chadaTravelApplication);
        formData.append('requirement_id', row.dataset.chadaTravelRequirement);
        formData.append('document_file', fileInput.files[0]);

        button.disabled = true;
        note.classList.remove('is-error');
        note.textContent = '';
        let response;
        let data = {};
        try {
          response = await fetch(`${settings.restUrl || ''}documents/upload`, {
            method: 'POST', headers: {
              'X-CHADA-TRAVEL-Nonce': settings.uploadNonce || '',
              'X-CHADA-TRAVEL-Guest': settings.guestToken || '',
            }, body: formData,
          });
          data = await response.json();
        } catch (error) {
          button.disabled = false;
          note.textContent = strings.documentUploadFailedNotice || 'The file could not be uploaded.';
          note.classList.add('is-error');
          return;
        }
        button.disabled = false;
        if (!response.ok || !data.success) {
          note.textContent = data.message || strings.documentUploadFailedNotice || 'The file could not be uploaded.';
          note.classList.add('is-error');
          return;
        }
        note.textContent = strings.documentUploadedNotice || 'File uploaded.';
        await loadChecklist();
      });
    });
  };

  const loadChecklist = async () => {
    let response;
    let data = {};
    try {
      response = await fetch(`${settings.restUrl || ''}documents/list`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CHADA-TRAVEL-Nonce': settings.uploadNonce || '',
          'X-CHADA-TRAVEL-Guest': settings.guestToken || '',
        },
        body: JSON.stringify({ token }),
      });
      data = await response.json();
    } catch (error) {
      announce(strings.genericError || '', true);
      return;
    }
    if (!response.ok || !data.success) {
      announce(data.message || strings.uploadLinkInvalidNotice || '', true);
      listContainer.innerHTML = '';
      return;
    }
    listContainer.innerHTML = (data.applications || []).map(applicationTemplate).join('');
    bindUploadButtons();
    announce('');
  };

  app.querySelector('[data-chada-travel-documents-back]')?.addEventListener('click', () => {
    window.location.href = settings.homeUrl || '/';
  });

  if (!token) {
    announce(strings.uploadLinkInvalidNotice || 'This upload link is invalid, expired, or has been revoked.', true);
  } else {
    loadChecklist();
  }
})();
