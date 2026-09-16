(() => {
  'use strict';

  const data = window.chadaTravelVisaCountries || {};
  const countries = data.countries || [];
  const strings = data.strings || {};
  const pdfIconUrl = data.pdfIconUrl || '';

  const form = document.getElementById('chada-travel-country-form');
  const addButton = document.getElementById('chada-travel-add-country');
  const cancelButton = document.getElementById('chada-travel-cancel-country');
  const formTitle = document.getElementById('chada-travel-country-form-title');
  const originalCodeField = document.getElementById('chada-travel-country-original-code');
  const nameField = document.getElementById('chada-travel-country-name');
  const codeField = document.getElementById('chada-travel-country-code');
  const feeField = document.getElementById('chada-travel-country-fee');
  const checklistField = document.getElementById('chada-travel-country-checklist');
  const fullDetailsField = document.getElementById('chada-travel-country-full-details');

  const searchInput = document.getElementById('chada-travel-country-search');
  const statusFilter = document.getElementById('chada-travel-country-status');
  const sortSelect = document.getElementById('chada-travel-country-sort');
  const rowsBody = document.getElementById('chada-travel-country-rows');
  const countLabel = document.getElementById('chada-travel-country-count');

  const noFileSelected = strings.noFileSelected || 'No file selected';
  const pdfLabel = strings.pdfLabel || 'PDF document';
  const mediaNameMaxLength = 40;
  const fullDetailsMaxLength = 50000;
  const fullDetailsEditorId = 'chada-travel-country-full-details';
  const metadata = form?.querySelector('[data-chada-travel-country-record-metadata]');
  const createdAt = form?.querySelector('[data-chada-travel-country-created-at]');
  const updatedAt = form?.querySelector('[data-chada-travel-country-updated-at]');
  const metadataFallback = strings.notAvailable || 'Not available';

  const truncateMediaName = (value) => {
    const characters = Array.from(String(value || ''));
    return characters.length > mediaNameMaxLength
      ? `${characters.slice(0, mediaNameMaxLength - 1).join('')}…`
      : characters.join('');
  };

  const getFullDetailsEditor = () => window.tinymce?.get(fullDetailsEditorId) || null;

  const cleanPastedHtml = (value) => {
    const content = String(value || '')
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/<\/?(?:o|w|v):[^>]*>/gi, '');
    const container = document.createElement('div');
    container.innerHTML = content;
    container.querySelectorAll('style, link, meta, xml').forEach((node) => node.remove());
    container.querySelectorAll('*').forEach((node) => {
      Array.from(node.attributes).forEach((attribute) => {
        const attributeName = attribute.name.toLowerCase();
        if (attributeName === 'style' || attributeName === 'data-mce-style'
          || /^xmlns(?::|$)/i.test(attributeName) || /^xml:/i.test(attributeName)) {
          node.removeAttribute(attribute.name);
        }
      });
      const classes = (node.getAttribute('class') || '').split(/\s+/).filter((className) => (
        className && !/^(?:Mso[\w-]*|mso-[\w-]*)$/i.test(className)
      ));
      if (classes.length) node.setAttribute('class', classes.join(' '));
      else node.removeAttribute('class');
    });
    return container.innerHTML;
  };

  const bindFullDetailsPasteCleanup = () => {
    const tinymce = window.tinymce;
    if (!tinymce) return;
    const bindEditor = (editor) => {
      if (!editor || editor.id !== fullDetailsEditorId || editor.chadaTravelVisaPasteCleanupBound) return;
      editor.chadaTravelVisaPasteCleanupBound = true;
      editor.on('PastePreProcess', (event) => {
        event.content = cleanPastedHtml(event.content);
      });
    };
    bindEditor(getFullDetailsEditor());
    if (typeof tinymce.on === 'function' && !tinymce.chadaTravelVisaPasteCleanupListenerBound) {
      tinymce.chadaTravelVisaPasteCleanupListenerBound = true;
      tinymce.on('AddEditor', (event) => bindEditor(event?.editor));
    }
  };

  bindFullDetailsPasteCleanup();
  document.addEventListener('DOMContentLoaded', bindFullDetailsPasteCleanup, { once: true });

  const syncFullDetails = () => {
    if (fullDetailsField) fullDetailsField.setAttribute('maxlength', String(fullDetailsMaxLength));
    getFullDetailsEditor()?.save();
  };

  const setFullDetailsValue = (value) => {
    const content = String(value || '');
    if (!fullDetailsField) return;
    fullDetailsField.setAttribute('maxlength', String(fullDetailsMaxLength));
    const editor = getFullDetailsEditor();
    if (editor) {
      editor.setContent(content);
      editor.save();
      return;
    }
    fullDetailsField.value = content;
  };

  const setRecordMetadata = (country = null) => {
    const isExisting = Boolean(country?.code);
    if (createdAt) createdAt.textContent = isExisting ? (country.created_at || metadataFallback) : metadataFallback;
    if (updatedAt) updatedAt.textContent = isExisting ? (country.updated_at || metadataFallback) : metadataFallback;
    if (metadata) metadata.hidden = !isExisting;
  };

  if (fullDetailsField) fullDetailsField.setAttribute('maxlength', String(fullDetailsMaxLength));
  form?.addEventListener('submit', syncFullDetails);

  const setMediaPreview = (field, previewData = {}) => {
    const container = field.querySelector('[data-chada-travel-media-preview-container]');
    const preview = field.querySelector('[data-chada-travel-media-preview]');
    if (!container || !preview) return;
    const isPdf = Boolean(previewData.isPdf);
    const previewUrl = isPdf ? (previewData.pdfIconUrl || pdfIconUrl) : (previewData.thumbnailUrl || '');
    preview.src = previewUrl;
    preview.alt = isPdf ? pdfLabel : (previewData.alt || 'Media thumbnail');
    container.hidden = !previewUrl;
  };

  const setMediaState = (field, attachmentId, previewData = {}, mediaName = '') => {
    const input = field.querySelector('input[type="hidden"]');
    const name = field.querySelector('[data-chada-travel-media-name]');
    const remove = field.querySelector('[data-chada-travel-remove-media]');
    const select = field.querySelector('[data-chada-travel-select-media]');
    if (input) input.value = String(attachmentId || 0);
    if (name) {
      const displayName = mediaName || (attachmentId ? `Media attachment #${attachmentId}` : noFileSelected);
      name.textContent = truncateMediaName(displayName);
    }
    if (remove) remove.hidden = !attachmentId;
    if (select) select.textContent = attachmentId ? 'Replace' : 'Select file';
    setMediaPreview(field, previewData);
  };

  const countryMedia = (country, kind) => {
    const prefix = kind === 'image' ? 'guide' : 'checklist';
    return {
      thumbnailUrl: country[`${prefix}_thumbnail_url`] || '',
      pdfIconUrl: country[`${prefix}_pdf_icon_url`] || pdfIconUrl,
      isPdf: Boolean(country[`${prefix}_is_pdf`]),
      alt: `${country.name || 'Country'} ${kind === 'image' ? 'Step-by-Step Guide' : 'Documents Checklist'} thumbnail`,
    };
  };

  const openForm = (title) => {
    if (!form) return;
    form.hidden = false;
    if (formTitle) formTitle.textContent = title;
    nameField?.focus();
  };

  const resetForm = () => {
    if (originalCodeField) originalCodeField.value = '';
    [nameField, codeField, feeField, checklistField].forEach((field) => {
      if (field) field.value = '';
    });
    setFullDetailsValue('');
    setRecordMetadata();
    document.querySelectorAll('[data-chada-travel-country-media]').forEach((field) => {
      const input = field.querySelector('input[type="hidden"]');
      if (input) input.value = '0';
      setMediaState(field, 0);
    });
  };

  addButton?.addEventListener('click', () => {
    resetForm();
    openForm('Add Country');
  });

  cancelButton?.addEventListener('click', () => {
    if (window.confirm(strings.confirmCancel || 'Discard the unsaved country changes?') && form) form.hidden = true;
  });

  document.querySelectorAll('[data-edit-country]').forEach((button) => {
    button.addEventListener('click', () => {
      const code = button.dataset.editCountry;
      const country = countries.find((item) => item.code === code);
      if (!country) return;
      if (originalCodeField) originalCodeField.value = country.code;
      if (nameField) nameField.value = country.name || '';
      if (codeField) codeField.value = country.code || '';
      if (feeField) feeField.value = country.processing_fee || '';
      if (checklistField) checklistField.value = country.checklist_version || '';
      setFullDetailsValue(country.full_details || '');
      setRecordMetadata(country);
      const mediaIds = {
        image: Number(country.guide_attachment_id || 0),
        checklist: Number(country.checklist_attachment_id || 0),
      };
      document.querySelectorAll('[data-chada-travel-country-media]').forEach((field) => {
        const kind = field.dataset.chadaTravelCountryMedia;
        const attachmentId = mediaIds[kind] || 0;
        const media = countryMedia(country, kind);
        const title = kind === 'image' ? country.guide_title : country.checklist_title;
        setMediaState(field, attachmentId, media, title || '');
      });
      openForm(`Edit Country: ${country.name || code}`);
    });
  });

  document.querySelectorAll('[data-chada-travel-country-media]').forEach((field) => {
    const kind = field.dataset.chadaTravelCountryMedia;
    const select = field.querySelector('[data-chada-travel-select-media]');
    const remove = field.querySelector('[data-chada-travel-remove-media]');
    select?.addEventListener('click', () => {
      if (!window.wp?.media) return;
      const frame = window.wp.media({
        title: kind === 'image' ? 'Select Step-by-Step Guide' : 'Select Documents Checklist',
        button: { text: 'Use this file' },
        library: { type: kind === 'image' ? ['image/jpeg', 'image/png'] : ['application/pdf', 'image/jpeg', 'image/png'] },
        multiple: false,
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first()?.toJSON();
        if (!attachment) return;
        const isPdf = attachment.mime === 'application/pdf';
        const thumbnailUrl = attachment.sizes?.thumbnail?.url || '';
        setMediaState(field, attachment.id, {
          thumbnailUrl,
          pdfIconUrl,
          isPdf,
          alt: `${kind === 'image' ? 'Step-by-Step Guide' : 'Documents Checklist'} thumbnail`,
        }, attachment.filename || attachment.title || '');
      });
      frame.open();
    });
    remove?.addEventListener('click', () => {
      if (!window.confirm(strings.confirmRemove || 'Remove this file association from the country?')) return;
      setMediaState(field, 0);
    });
  });

  const applyView = () => {
    if (!rowsBody) return;
    const query = (searchInput?.value || '').trim().toLowerCase();
    const status = statusFilter?.value || '';
    const rows = Array.from(rowsBody.querySelectorAll('[data-country]'));
    rows.sort((a, b) => {
      if (sortSelect?.value === 'name') return a.dataset.name.localeCompare(b.dataset.name);
      if (sortSelect?.value === 'fee') return Number(a.dataset.fee) - Number(b.dataset.fee);
      return Number(a.dataset.order) - Number(b.dataset.order);
    });
    rows.forEach((row) => rowsBody.appendChild(row));
    let visible = 0;
    rows.forEach((row) => {
      const matchesQuery = !query || row.dataset.search.includes(query);
      const matchesStatus = !status || row.dataset.status === status;
      const show = matchesQuery && matchesStatus;
      row.hidden = !show;
      if (show) visible += 1;
    });
    if (countLabel) countLabel.textContent = `Showing ${visible} of ${rows.length} records`;
  };

  [searchInput, statusFilter, sortSelect].forEach((control) => control?.addEventListener('input', applyView));
  applyView();
})();
