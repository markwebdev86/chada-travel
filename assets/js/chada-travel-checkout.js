(() => {
  'use strict';

  const settings = window.chadaTravelCheckoutSettings || {};

  const DEFAULT_COUNTRY_CODE = 'US';

  // Sorts the customer-facing country list alphabetically by name, once, here, so every downstream consumer - the
  // Stage 1 accordion, Stage 2 applicant groups, the Stage 2-4 order summaries, and the Stage 3 review table -
  // reads the same alphabetical order without each needing to re-sort individually. chada_travel_country_fees' own stored
  // order is otherwise accidental creation/seed order (no admin UI exists to deliberately reorder it), so nothing
  // meaningful is lost by reordering it here for display; PHP stays untouched and remains the authority for
  // validation, fees, and totals.
  if (Array.isArray(settings.countries)) {
    settings.countries.sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));
  }

  // Resolves Stage 1's default-open/fallback country: United States specifically - matched by code, never by the
  // display name text (an administrator can edit it, and the checkout-preview fixture already stores it as "USA"
  // rather than "United States") - whenever it is configured and active, otherwise the first country in display
  // order, the same array-position-0 fallback this behavior has always used.
  const defaultCountryCode = () => {
    const countries = settings.countries || [];
    if (countries.some((country) => country.code === DEFAULT_COUNTRY_CODE)) return DEFAULT_COUNTRY_CODE;
    return countries[0]?.code || '';
  };

  const strings = settings.strings || {};
  // Assumes a single `.chada-travel-app` instance per page: this always binds to the first one in document order, and
  // every DOM read/write below is scoped through this `app` reference via app.querySelector(...). A second
  // instance (e.g. a cloned page-builder block) is left completely unbound rather than duplicating listeners,
  // REST requests, or state - its controls simply will not respond until only one instance remains on the page.
  const app = document.querySelector('.chada-travel-app');
  if (!app) return;

  const STORAGE_KEY = 'chadaTravelDraftToken';
  const STATE_STORAGE_KEY = 'chadaTravelVisaApplicationState';
  const STATE_VERSION = 1;
  // Stages 1-4 each carry their own visually-mirrored copy of the live-status element at the top of that stage's
  // own content (data-chada-travel-country-browser-status, data-chada-travel-booker-applicants-status, data-chada-travel-review-status,
  // data-chada-travel-payment-status - never a second data-chada-travel-live-status: several Playwright specs select that
  // attribute as a bare, non-.first()/.last() locator, which would break in Playwright's strict mode if it ever
  // matched more than one node). Stage 5 has no local copy and keeps using this element directly. Every stage
  // section toggles `hidden` independently, so only one local node (plus this one, on Stage 5) is ever un-hidden at
  // a time; announce() below mirrors the same text to every matched node regardless.
  const statusEls = app.querySelectorAll(
    '[data-chada-travel-live-status], [data-chada-travel-country-browser-status], [data-chada-travel-booker-applicants-status], '
      + '[data-chada-travel-review-status], [data-chada-travel-payment-status]'
  );
  const progressNav = app.querySelector('.chada-travel-progress');
  const progressShell = app.querySelector('.chada-travel-progress-shell');

  let draftToken = '';
  try {
    draftToken = window.sessionStorage.getItem(STORAGE_KEY) || '';
  } catch (error) {
    draftToken = '';
  }

  let accessibleStages = [1];
  let currentStage = 1;
  let bookerDetails = {
    first_name: '', last_name: '', email: '', mobile: '', address: '', privacy_terms_consent: false,
  };
  let applications = [];
  let selectedCountryCode = defaultCountryCode();
  let countryQuantities = {};
  let browserStateExpiredWithToken = false;
  let paymentMethod = (settings.payment || {}).defaultMethod || 'chada_travel_bank';
  let paymentSelection = null;
  let confirmationData = null;

  const announce = (message, isError = false) => {
    statusEls.forEach((node) => {
      node.textContent = message || '';
      node.classList.toggle('is-error', isError);
    });
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
  }[character]));

  const CURRENCY_SYMBOLS = {
    PHP: '₱', USD: '$', EUR: '€', GBP: '£', JPY: '¥',
    AUD: '$', CAD: '$', SGD: '$', HKD: '$', CNY: '¥',
  };

  // Mirrors CHADA_TRAVEL_Config::format_money()'s display modes so customer-facing amounts match server-rendered ones.
  const formatMoney = (amount, currencyCode) => {
    const code = (currencyCode || settings.currency || 'PHP').toUpperCase();
    const display = settings.currencyDisplay || 'symbol_code';
    const numeric = Number(amount || 0).toLocaleString('en-PH', {
      minimumFractionDigits: 2, maximumFractionDigits: 2,
    });
    const symbol = CURRENCY_SYMBOLS[code];
    if (!symbol || display === 'code') return `${code} ${numeric}`;
    return display === 'symbol_code' ? `${symbol}${numeric} ${code}` : `${symbol}${numeric}`;
  };

  const formatDate = (value) => {
    if (!value) return strings.notProvided || 'Not provided';
    try {
      return new Intl.DateTimeFormat('en-US', { dateStyle: 'long', timeZone: 'UTC' })
        .format(new Date(`${value}T00:00:00Z`));
    } catch (error) {
      return value;
    }
  };

  const countryLookup = () => {
    const map = {};
    (settings.countries || []).forEach((country) => { map[country.code] = country; });
    return map;
  };

  // Refreshes the Stage 1/3 policy links and version/effective-date captions from the order's own resolved
  // policy (its immutable snapshot once one exists), so they never silently drift to a newer Settings policy
  // bundle after a draft/snapshot has actually been resolved server-side (see
  // CHADA_TRAVEL_Rest_Controller::browser_safe_policy()).
  const applyPolicy = (policy) => {
    if (!policy) return;
    const urlByKey = {
      privacy: policy.privacyUrl, terms: policy.termsUrl, cancellation_refund: policy.cancellationRefundUrl,
    };
    app.querySelectorAll('[data-chada-travel-policy-link]').forEach((link) => {
      const url = urlByKey[link.dataset.chadaTravelPolicyLink];
      if (url) link.setAttribute('href', url);
    });
    app.querySelectorAll('[data-chada-travel-policy-caption]').forEach((caption) => {
      caption.textContent = `Policy Version ${policy.version || ''} · Effective ${policy.effectiveDate || ''}`;
    });
  };

  const setDraftToken = (token) => {
    if (!token) return;
    draftToken = token;
    try {
      window.sessionStorage.setItem(STORAGE_KEY, draftToken);
    } catch (error) {
      // Storage unavailable; the token still lives in memory for this page view.
    }
  };

  const makeClientId = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    return `chada-travel-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  };

  const blankApplication = (countryCode) => ({
    client_id: makeClientId(), country_code: countryCode, target_travel_date: settings.defaultTravelDate || '',
    first_name: '', last_name: '', is_booker: false,
  });

  const clearStoredState = (clearToken = false) => {
    try {
      window.sessionStorage.removeItem(STATE_STORAGE_KEY);
      if (clearToken) window.sessionStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      // The in-memory state is still reset below when requested.
    }
  };

  const resetClientState = (clearToken = false) => {
    if (clearToken) draftToken = '';
    bookerDetails = {
      first_name: '', last_name: '', email: '', mobile: '', address: '', privacy_terms_consent: false,
    };
    applications = [];
    paymentSelection = null;
    confirmationData = null;
    countryQuantities = {};
    selectedCountryCode = defaultCountryCode();
    accessibleStages = [1];
    browserStateExpiredWithToken = false;
    clearStoredState(clearToken);
  };

  const persistClientState = () => {
    const state = {
      version: STATE_VERSION, updated_at: Date.now(), selected_country: selectedCountryCode,
      country_quantities: countryQuantities, booker: bookerDetails, applications,
      payment_selection: paymentSelection && paymentSelection.method === 'chada_travel_bank'
        && paymentSelection.proofUrl ? {
          method: paymentSelection.method, status: paymentSelection.status, bookingId: paymentSelection.bookingId,
          proofUrl: paymentSelection.proofUrl,
        } : null,
    };
    try {
      window.sessionStorage.setItem(STATE_STORAGE_KEY, JSON.stringify(state));
    } catch (error) {
      // Storage unavailable; the state still lives in memory for this page view.
    }
  };

  const restoreClientState = () => {
    let raw = '';
    try {
      raw = window.sessionStorage.getItem(STATE_STORAGE_KEY) || '';
    } catch (error) {
      return;
    }
    if (!raw) return;
    try {
      const state = JSON.parse(raw);
      const expiryHours = Math.max(1, Math.min(720, Number(settings.draftExpiryHours) || 72));
      const isExpired = !Number.isFinite(state.updated_at)
        || (Date.now() - Number(state.updated_at)) > expiryHours * 60 * 60 * 1000;
      if (state.version !== STATE_VERSION || !Array.isArray(state.applications)
          || typeof state.booker !== 'object' || state.booker === null || isExpired) {
        clearStoredState(false);
        browserStateExpiredWithToken = Boolean(draftToken);
        announce(isExpired ? (strings.browserDraftExpiredNotice || strings.draftExpiredNotice || '')
          : (strings.genericError || ''), true);
        return;
      }
      const validCodes = new Set((settings.countries || []).map((country) => country.code));
      applications = state.applications.filter((row) => row && validCodes.has(row.country_code)).map((row) => ({
        client_id: String(row.client_id || makeClientId()), country_code: String(row.country_code),
        target_travel_date: String(row.target_travel_date || settings.defaultTravelDate || ''),
        first_name: String(row.first_name || ''),
        last_name: String(row.last_name || ''), is_booker: Boolean(row.is_booker),
      }));
      const selectedBooker = applications.find((row) => row.is_booker);
      applications.forEach((row) => { row.is_booker = row === selectedBooker; });
      bookerDetails = Object.assign(bookerDetails, {
        first_name: String(state.booker.first_name || ''), last_name: String(state.booker.last_name || ''),
        email: String(state.booker.email || ''), mobile: String(state.booker.mobile || ''),
        address: String(state.booker.address || ''),
        privacy_terms_consent: Boolean(state.booker.privacy_terms_consent),
      });
      selectedCountryCode = validCodes.has(state.selected_country)
        ? state.selected_country : defaultCountryCode();
      countryQuantities = typeof state.country_quantities === 'object' && state.country_quantities !== null
        ? state.country_quantities : {};
      const storedPayment = state.payment_selection;
      paymentSelection = storedPayment && storedPayment.method === 'chada_travel_bank'
        && typeof storedPayment.proofUrl === 'string' && storedPayment.proofUrl !== '' ? {
          method: storedPayment.method, status: String(storedPayment.status || ''),
          bookingId: String(storedPayment.bookingId || ''), proofUrl: storedPayment.proofUrl,
        } : null;
      applications.forEach((row) => {
        countryQuantities[row.country_code] = applications.filter((item) => item.country_code === row.country_code).length;
      });
      accessibleStages = applications.length ? [1, 2] : [1];
    } catch (error) {
      clearStoredState(false);
      browserStateExpiredWithToken = Boolean(draftToken);
      announce(strings.genericError || '', true);
    }
  };

  // Lets an active country's code in the URL hash (for example #US, case-insensitive) seed the initial Stage 1
  // selection, taking precedence over both the default first country and a restored sessionStorage selection - an
  // unknown/absent hash is ignored rather than treated as an error.
  const applyHashCountrySelection = () => {
    const code = window.location.hash.slice(1).trim().toUpperCase();
    if (!code) return;
    if (!(settings.countries || []).some((country) => country.code === code)) return;
    selectedCountryCode = code;
    if (!countryQuantities[code]) countryQuantities[code] = 1;
  };

  // Clears the expired session locally and returns the customer to Stage 1 with a clear, safe notice - the one
  // handler for CHADA_TRAVEL_Rest_Controller's `chada_travel_draft_expired` (410) response, wherever it is returned from.
  const handleDraftExpired = (message) => {
    draftToken = '';
    try {
      window.sessionStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      // Storage unavailable; the in-memory token above is already cleared.
    }
    resetClientState(true);
    goToStage(1);
    announce(message || strings.draftExpiredNotice || '', true);
  };

  const apiPost = async (path, payload, options = {}) => {
    let response;
    try {
      response = await fetch(`${settings.restUrl || ''}checkout/${path}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CHADA-TRAVEL-Nonce': settings.checkoutNonce || '',
          'X-CHADA-TRAVEL-Guest': settings.guestToken || '',
        },
        body: JSON.stringify(Object.assign({ draft_token: draftToken }, payload)),
      });
    } catch (error) {
      return { ok: false, data: { message: strings.genericError || '' } };
    }
    let data = {};
    try {
      data = await response.json();
    } catch (error) {
      data = {};
    }
    if (!response.ok && data.code === 'chada_travel_draft_expired' && !options.preserveOnDraftError) {
      handleDraftExpired(data.message);
    }
    return { ok: response.ok, data };
  };

  const clearFieldErrors = (root) => {
    root.querySelectorAll('.chada-travel-field-error').forEach((node) => node.remove());
    root.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
  };

  const showFieldError = (root, fieldName, message) => {
    const field = root.querySelector(`[name="${fieldName}"], [data-chada-travel-field="${fieldName}"]`);
    if (!field) {
      announce(message, true);
      return;
    }
    field.setAttribute('aria-invalid', 'true');
    const error = document.createElement('p');
    error.className = 'chada-travel-field-error';
    error.textContent = message;
    (field.closest('label') || field).insertAdjacentElement('afterend', error);
  };

  const confirmAction = (message, confirmLabel = 'Confirm') => new Promise((resolve) => {
    const dialog = app.querySelector('[data-chada-travel-confirm-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function') {
      resolve(window.confirm(message));
      return;
    }
    const initiatingControl = document.activeElement;
    const messageEl = dialog.querySelector('[data-chada-travel-confirm-message]');
    const confirmButton = dialog.querySelector('[data-chada-travel-confirm-action]');
    if (messageEl) messageEl.textContent = message;
    if (confirmButton) confirmButton.textContent = confirmLabel;
    const closeHandler = () => {
      dialog.removeEventListener('close', closeHandler);
      initiatingControl?.focus();
      resolve(dialog.returnValue === 'confirm');
    };
    dialog.addEventListener('close', closeHandler);
    dialog.showModal();
  });

  const applicantCountFor = (countryCode) => applications
    .filter((application) => application.country_code === countryCode).length;

  const fullName = (firstName, lastName) => [firstName, lastName].filter(Boolean).join(' ');

  const bindStageTargets = (root) => {
    root.querySelectorAll('[data-chada-travel-stage-target]').forEach((control) => {
      control.addEventListener('click', () => goToStage(Number(control.dataset.chadaTravelStageTarget || 0)));
    });
  };

  const renderProgress = () => {
    if (!progressNav) return;
    const stages = settings.stages || [];
    progressNav.innerHTML = stages.map((stage) => {
      const isAllowed = accessibleStages.includes(stage.stage);
      const isCurrent = stage.stage === currentStage;
      const tag = isAllowed ? 'button' : 'span';
      const attributes = [
        isAllowed ? `type="button" data-chada-travel-stage-target="${stage.stage}"` : '',
        isCurrent ? 'aria-current="step"' : (isAllowed ? '' : 'aria-disabled="true"'),
      ].filter(Boolean).join(' ');
      const activeClass = isCurrent ? ' is-active' : '';
      return `<${tag} class="chada-travel-progress-step${activeClass}" ${attributes} `
        + `data-mobile-label="Stage ${stage.stage} of 4">`
        + `<span class="chada-travel-progress-step__number">${stage.stage}</span>`
        + `<span class="chada-travel-progress-step__label">${escapeHtml(stage.label)}</span></${tag}>`;
    }).join('');
    bindStageTargets(progressNav);
  };

  const goToStage = (stage) => {
    if (!accessibleStages.includes(stage)) {
      announce(strings.stageBlocked || '', true);
      return;
    }
    currentStage = stage;
    app.querySelectorAll('[data-chada-travel-stage]').forEach((section) => {
      section.hidden = Number(section.dataset.chadaTravelStage) !== stage;
    });
    if (progressShell) progressShell.hidden = stage === 5;
    renderProgress();
    renderCartStatus();
    if (stage === 1) renderCountryBrowser();
    if (stage === 2) renderApplicants();
    if (stage === 3) renderReview();
    if (stage === 4) renderPaymentStage();
    if (stage === 5) renderConfirmation();
    const target = app.querySelector(`#chada-travel-stage-${stage}`);
    const heading = target ? target.querySelector('h2') : null;
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus({ preventScroll: true });
    }
    target?.scrollIntoView({ block: 'start' });
  };

  const buildSummaryLines = (apps) => {
    const lookup = countryLookup();
    const totals = {};
    apps.forEach((application) => {
      if (!application.country_code) return;
      const fee = Number((lookup[application.country_code] || {}).processing_fee || 0);
      if (!totals[application.country_code]) totals[application.country_code] = { quantity: 0, amount: 0 };
      totals[application.country_code].quantity += 1;
      totals[application.country_code].amount += fee;
    });
    const lines = (settings.countries || []).filter((country) => totals[country.code]).map((country) => ({
      code: country.code, label: `${country.name || country.code} Visa Processing`,
      quantity: totals[country.code].quantity, amount: formatMoney(totals[country.code].amount),
      numericAmount: totals[country.code].amount,
    }));
    const grandTotal = lines.reduce((sum, line) => sum + line.numericAmount, 0);
    return { lines, total: formatMoney(grandTotal) };
  };

  const renderSummary = (container, apps, totalLabel) => {
    if (!container) return;
    const { lines, total } = buildSummaryLines(apps);
    const isStageTwo = container.id === 'chada-travel-stage-2-summary';
    const rows = isStageTwo
      ? ['<div class="chada-travel-summary-table__head"><span>Country</span><span>Quantity</span><span>Amount</span></div>',
        ...lines.map((line) => '<div class="chada-travel-summary-table__row">'
          + `<span>${escapeHtml(line.label)}</span><span>${line.quantity}x</span>`
          + `<span>${escapeHtml(line.amount)}</span></div>`)]
      : lines.map((line) => `<div class="chada-travel-summary-line"><span>${escapeHtml(line.label)}</span>`
        + `<span>${escapeHtml(line.amount)}</span></div>`);
    rows.push(`<div class="chada-travel-summary-line chada-travel-summary-line--total"><span>${escapeHtml(totalLabel)}</span>`
      + `<span>${escapeHtml(total)}</span></div>`);
    container.innerHTML = rows.join('');
  };

  const renderCartStatus = () => {
    const count = applications.length;
    app.querySelectorAll('[data-chada-travel-cart-count]').forEach((node) => { node.textContent = String(count); });
    app.querySelectorAll('[data-chada-travel-cart-count-label]').forEach((node) => {
      node.textContent = `${count} visa applicant${count === 1 ? '' : 's'} selected`;
    });
    app.querySelectorAll('[data-chada-travel-cancel-application]').forEach((button) => {
      button.hidden = count < 1 || currentStage === 5;
    });
  };

  const updateCountryQuantity = (countryCode, quantity) => {
    const currentRows = applications.filter((row) => row.country_code === countryCode);
    const otherRows = applications.filter((row) => row.country_code !== countryCode);
    const retainedRows = currentRows.slice(0, quantity);
    while (retainedRows.length < quantity) retainedRows.push(blankApplication(countryCode));
    const order = (settings.countries || []).map((country) => country.code);
    applications = [...otherRows, ...retainedRows].sort((left, right) => (
      order.indexOf(left.country_code) - order.indexOf(right.country_code)
    ));
    countryQuantities[countryCode] = quantity;
    accessibleStages = applications.length ? Array.from(new Set([...accessibleStages, 1, 2])) : [1];
    persistClientState();
    renderCartStatus();
    renderCountryBrowser();
    renderApplicants();
  };

  const applyCountry = async (countryCode) => {
    const current = applicantCountFor(countryCode);
    const requested = Number(countryQuantities[countryCode]) || 1;
    const maxApplicants = Number(settings.maxApplicants) || 0;
    const proposedTotal = applications.length - current + requested;
    if (maxApplicants > 0 && proposedTotal > maxApplicants) {
      announce((strings.maxApplicantsReachedNotice || '').replace('%d', String(maxApplicants)), true);
      return;
    }
    if (requested < current) {
      const removed = current - requested;
      const confirmed = await confirmAction(
        `Reducing this country will discard ${removed} applicant record${removed === 1 ? '' : 's'} from the end. Continue?`,
        'Remove Applicant Records',
      );
      if (!confirmed) {
        countryQuantities[countryCode] = current;
        renderCountryBrowser();
        return;
      }
    }
    updateCountryQuantity(countryCode, requested);
    announce(`${requested} applicant${requested === 1 ? '' : 's'} applied for this country.`);
  };

  const cancelCountry = async (countryCode) => {
    const count = applicantCountFor(countryCode);
    if (!count) return;
    const country = countryLookup()[countryCode] || {};
    const confirmed = await confirmAction(
      `Cancel ${country.name || countryCode} and delete ${count} applicant record${count === 1 ? '' : 's'}?`,
      'Cancel Country',
    );
    if (!confirmed) return;
    applications = applications.filter((row) => row.country_code !== countryCode);
    countryQuantities[countryCode] = 1;
    accessibleStages = applications.length ? Array.from(new Set([...accessibleStages, 1, 2])) : [1];
    persistClientState();
    renderCartStatus();
    renderCountryBrowser();
    renderApplicants();
    if (!applications.length && currentStage !== 1) goToStage(1);
    announce(`${country.name || countryCode} was removed from the application.`);
  };

  const countryAccordionItem = (country) => {
    const isExpanded = country.code === selectedCountryCode;
    const count = applicantCountFor(country.code);
    const badge = count ? `<span class="chada-travel-country-badge">${count}<span class="chada-travel-sr-only"> applicants</span></span>` : '';
    // A unique per-country data-gallery value keeps each guide as its own independent lightbox: GLightbox groups
    // every matched element sharing no/the-same data-gallery value into one navigable gallery, and every country's
    // link stays present in the DOM (merely hidden) even when its accordion item is collapsed.
    const guide = country.guide_url
      ? `<a class="chada-travel-country-guide-link" href="${escapeHtml(country.guide_url)}" `
        + `data-gallery="chada-travel-guide-${escapeHtml(country.code)}" target="_blank" rel="noopener">`
        + `<img class="chada-travel-country-guide" src="${escapeHtml(country.guide_url)}" `
        + `alt="${escapeHtml(country.name)} Visa Step-by-Step Guide"></a>`
      : `<p class="chada-travel-country-missing">${escapeHtml(strings.missingGuideNotice || '')}</p>`;
    return `<section class="chada-travel-country-item"><h3><button type="button" class="chada-travel-country-toggle" `
      + `data-chada-travel-country-toggle="${escapeHtml(country.code)}" aria-expanded="${isExpanded}" `
      + `aria-controls="chada-travel-country-guide-${escapeHtml(country.code)}"><span>${escapeHtml(country.name)}</span>`
      + `${badge}<span class="chada-travel-country-toggle__mark" aria-hidden="true">${isExpanded ? '−' : '+'}</span></button></h3>`
      + `<div id="chada-travel-country-guide-${escapeHtml(country.code)}" class="chada-travel-country-guide-panel" `
      + `${isExpanded ? '' : 'hidden'}>${guide}</div></section>`;
  };

  let guideLightbox = null;
  // GLightbox only scans the DOM once at construction, so a re-render (toggle, quantity change, Apply, Cancel,
  // hash/session selection) needs an explicit reload() rather than a fresh instance, which would leak listeners.
  // Guarded so a blocked/failed library load degrades to the plain-link fallback instead of throwing.
  const refreshGuideLightbox = () => {
    if (typeof window.GLightbox !== 'function') return;
    if (guideLightbox) {
      guideLightbox.reload();
      return;
    }
    guideLightbox = window.GLightbox({
      selector: '.chada-travel-country-guide-link', touchNavigation: true, keyboardNavigation: true,
      closeButton: true, zoomable: true, loop: false,
    });
  };

  const renderCountryBrowser = () => {
    const countries = settings.countries || [];
    const accordion = app.querySelector('[data-chada-travel-country-accordion]');
    const details = app.querySelector('[data-chada-travel-country-details]');
    const empty = app.querySelector('[data-chada-travel-country-empty]');
    const nextButton = app.querySelector('[data-chada-travel-stage-one-next]');
    if (!accordion || !details) return;
    if (!countries.length) {
      if (nextButton) nextButton.hidden = true;
      accordion.innerHTML = '';
      details.innerHTML = '';
      if (empty) {
        empty.hidden = false;
        empty.textContent = strings.noCountriesNotice || '';
      }
      return;
    }
    if (nextButton) nextButton.hidden = false;
    if (empty) empty.hidden = true;
    if (!countries.some((country) => country.code === selectedCountryCode)) selectedCountryCode = defaultCountryCode();
    accordion.innerHTML = countries.map(countryAccordionItem).join('');
    refreshGuideLightbox();
    const country = countryLookup()[selectedCountryCode] || countryLookup()[defaultCountryCode()];
    // Keeps the URL hash mirroring whichever country is currently open (replaceState, not pushState/location.hash,
    // since this render also runs on quantity/Apply/Cancel actions that must not spam browser history).
    const targetHash = `#${country.code}`;
    if (window.location.hash !== targetHash) window.history.replaceState(null, '', targetHash);
    const current = applicantCountFor(country.code);
    const quantity = Number(countryQuantities[country.code]) || current || 1;
    countryQuantities[country.code] = quantity;
    const maxApplicants = Number(settings.maxApplicants) || 0;
    const plusDisabled = maxApplicants > 0 && applications.length - current + quantity >= maxApplicants;
    // Full Details is sanitized with WordPress's post-content allowlist before it reaches this browser config.
    const detailsText = country.full_details
      ? `<div class="chada-travel-country-details__text">${country.full_details}</div>`
      : `<p class="chada-travel-country-missing">${escapeHtml(strings.missingDetailsNotice || '')}</p>`;
    const guideDownload = country.guide_url
      ? `<a class="chada-travel-country-guide-download" href="${escapeHtml(country.guide_url)}" download `
        + `target="_blank" rel="noopener">Download the Step-by-step Guide</a>`
      : '';
    details.innerHTML = `<h3>${escapeHtml(country.name)}</h3>`
      + `<p class="chada-travel-country-fee">${escapeHtml(formatMoney(country.processing_fee || 0))} per applicant</p>`
      + detailsText + guideDownload + '<div class="chada-travel-country-controls">'
      + (current ? `<button class="chada-travel-button" type="button" data-chada-travel-country-cancel="${escapeHtml(country.code)}">`
        + 'CANCEL</button>' : '')
      + `<div class="chada-travel-quantity-control" role="group" aria-label="Applicants for ${escapeHtml(country.name)}">`
      + `<button type="button" data-chada-travel-quantity-minus aria-label="Decrease applicants" ${quantity <= 1 ? 'disabled' : ''}>−</button>`
      + `<output data-chada-travel-quantity aria-live="polite">${quantity}</output>`
      + `<button type="button" data-chada-travel-quantity-plus aria-label="Increase applicants" ${plusDisabled ? 'disabled' : ''}>+</button></div>`
      + `<button class="chada-travel-button chada-travel-button--primary" type="button" data-chada-travel-country-apply="${escapeHtml(country.code)}">`
      + 'APPLY</button></div>';
    accordion.querySelectorAll('[data-chada-travel-country-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        selectedCountryCode = button.dataset.chadaTravelCountryToggle;
        if (!countryQuantities[selectedCountryCode]) countryQuantities[selectedCountryCode] = 1;
        persistClientState();
        renderCountryBrowser();
      });
    });
    details.querySelector('[data-chada-travel-quantity-minus]')?.addEventListener('click', () => {
      countryQuantities[country.code] = Math.max(1, quantity - 1);
      persistClientState();
      renderCountryBrowser();
    });
    details.querySelector('[data-chada-travel-quantity-plus]')?.addEventListener('click', () => {
      if (plusDisabled) {
        announce((strings.maxApplicantsReachedNotice || '').replace('%d', String(maxApplicants)), true);
        return;
      }
      countryQuantities[country.code] = quantity + 1;
      persistClientState();
      renderCountryBrowser();
    });
    details.querySelector('[data-chada-travel-country-apply]')?.addEventListener('click', () => applyCountry(country.code));
    details.querySelector('[data-chada-travel-country-cancel]')?.addEventListener('click', () => cancelCountry(country.code));
  };

  const computeDuplicateWarnings = (apps) => {
    const groups = {};
    apps.forEach((application, index) => {
      if (!application.country_code || !application.first_name) return;
      const key = `${application.first_name.trim().toLowerCase()}|${application.last_name.trim().toLowerCase()}`
        + `|${application.country_code}`;
      (groups[key] = groups[key] || []).push(index);
    });
    return Object.values(groups).filter((indexes) => indexes.length > 1).flat();
  };

  const renderDuplicateNotice = () => {
    const summary = app.querySelector('#chada-travel-stage-2-summary');
    if (!summary) return;
    let notice = app.querySelector('#chada-travel-duplicate-notice');
    const hasDuplicates = computeDuplicateWarnings(applications).length > 0;
    if (!hasDuplicates) {
      notice?.remove();
      return;
    }
    if (!notice) {
      notice = document.createElement('div');
      notice.id = 'chada-travel-duplicate-notice';
      notice.className = 'chada-travel-notice';
      notice.dataset.type = 'info';
      notice.setAttribute('role', 'status');
      summary.insertAdjacentElement('afterend', notice);
    }
    notice.textContent = strings.duplicateWarning || '';
  };

  const applicantTemplate = (application, groupIndex) => {
    const selectedBooker = applications.find((row) => row.is_booker);
    const checkboxDisabled = selectedBooker && selectedBooker.client_id !== application.client_id;
    return `<article class="chada-travel-applicant-row" data-chada-travel-applicant-id="${escapeHtml(application.client_id)}" `
      + `data-chada-travel-applicant-index="${applications.indexOf(application)}"><div class="chada-travel-applicant-row__header">`
      + `<h4>Applicant ${groupIndex + 1}:</h4><button class="chada-travel-button chada-travel-button--small" type="button" `
      + `data-chada-travel-remove-applicant="${escapeHtml(application.client_id)}">DELETE</button></div>`
      + '<div class="chada-travel-applicant-fields">'
      + `<label class="chada-travel-field">Applicant First Name *<input class="chada-travel-control" data-chada-travel-field="first_name" `
      + `required value="${escapeHtml(application.first_name)}"></label>`
      + `<label class="chada-travel-field">Applicant Last Name (Optional)<input class="chada-travel-control" `
      + `data-chada-travel-field="last_name" value="${escapeHtml(application.last_name)}"></label>`
      + `<label class="chada-travel-field">Target Travel Date (Required)<input class="chada-travel-control" type="date" `
      + `data-chada-travel-field="target_travel_date" required min="${escapeHtml(settings.minTravelDate || '')}" `
      + `value="${escapeHtml(application.target_travel_date)}"></label></div>`
      + '<label class="chada-travel-check-row"><input type="checkbox" data-chada-travel-field="is_booker" '
      + `${application.is_booker ? 'checked' : ''} ${checkboxDisabled ? 'disabled' : ''}>`
      + `<span>${escapeHtml(strings.isBookerLabel || 'I am the visa applicant')}</span></label></article>`;
  };

  const applicantGroupTemplate = (country) => {
    const rows = applications.filter((application) => application.country_code === country.code);
    if (!rows.length) return '';
    const maxApplicants = Number(settings.maxApplicants) || 0;
    const atCap = maxApplicants > 0 && applications.length >= maxApplicants;
    return `<section class="chada-travel-applicant-group" data-chada-travel-country-group="${escapeHtml(country.code)}">`
      + `<h3>${escapeHtml(country.name)} Applicant${rows.length === 1 ? '' : 's'}</h3>`
      + rows.map(applicantTemplate).join('')
      + '<div class="chada-travel-applicant-actions">'
      + `<button class="chada-travel-button" type="button" data-chada-travel-add-applicant="${escapeHtml(country.code)}" `
      + `${atCap ? 'disabled aria-disabled="true"' : ''}>+ Add Another Applicant</button></div></section>`;
  };

  const updateApplicantCapUi = () => {
    const addButtons = app.querySelectorAll('[data-chada-travel-add-applicant]');
    const capNote = app.querySelector('[data-chada-travel-applicant-cap-note]');
    const maxApplicants = Number(settings.maxApplicants) || 0;
    const atCap = maxApplicants > 0 && applications.length >= maxApplicants;
    addButtons.forEach((addButton) => {
      addButton.disabled = atCap;
      addButton.setAttribute('aria-disabled', atCap ? 'true' : 'false');
    });
    if (capNote) {
      capNote.textContent = atCap
        ? (strings.maxApplicantsReachedNotice || '').replace('%d', String(maxApplicants)) : '';
    }
  };

  const renderApplicants = () => {
    const container = app.querySelector('#chada-travel-applicant-list');
    if (!container) return;
    container.innerHTML = (settings.countries || []).map(applicantGroupTemplate).join('');
    container.querySelectorAll('[data-chada-travel-applicant-id]').forEach((panel) => {
      const clientId = panel.dataset.chadaTravelApplicantId;
      const application = applications.find((row) => row.client_id === clientId);
      if (!application) return;
      panel.querySelectorAll('[data-chada-travel-field]').forEach((field) => {
        field.addEventListener('change', () => {
          const name = field.dataset.chadaTravelField;
          application[name] = field.type === 'checkbox' ? field.checked : field.value;
          if (name === 'is_booker' && field.checked) {
            applications.forEach((row) => { row.is_booker = row.client_id === clientId; });
            application.first_name = bookerDetails.first_name || application.first_name;
            application.last_name = bookerDetails.last_name || application.last_name;
            persistClientState();
            renderApplicants();
            return;
          }
          if (name === 'is_booker') {
            persistClientState();
            renderApplicants();
            return;
          }
          persistClientState();
          renderSummary(app.querySelector('#chada-travel-stage-2-summary'), applications, strings.estimatedTotal
            || 'Estimated Total');
          renderDuplicateNotice();
        });
        field.addEventListener('input', () => {
          if (field.type === 'checkbox') return;
          application[field.dataset.chadaTravelField] = field.value;
          persistClientState();
          renderSummary(app.querySelector('#chada-travel-stage-2-summary'), applications, strings.estimatedTotal
            || 'Estimated Total');
          renderDuplicateNotice();
        });
      });
    });
    container.querySelectorAll('[data-chada-travel-remove-applicant]').forEach((button) => {
      button.addEventListener('click', async () => {
        const application = applications.find((row) => row.client_id === button.dataset.chadaTravelRemoveApplicant);
        if (!application) return;
        const groupCount = applicantCountFor(application.country_code);
        const message = groupCount === 1
          ? 'Deleting this applicant will also remove the country from your application. Continue?'
          : 'Delete this applicant record?';
        if (!await confirmAction(message, 'Delete Applicant')) return;
        applications = applications.filter((row) => row.client_id !== application.client_id);
        countryQuantities[application.country_code] = applicantCountFor(application.country_code) || 1;
        accessibleStages = applications.length ? Array.from(new Set([...accessibleStages, 1, 2])) : [1];
        persistClientState();
        renderCountryBrowser();
        renderCartStatus();
        renderApplicants();
        if (!applications.length) goToStage(1);
      });
    });
    container.querySelectorAll('[data-chada-travel-add-applicant]').forEach((button) => {
      button.addEventListener('click', () => {
        const maxApplicants = Number(settings.maxApplicants) || 0;
        if (maxApplicants > 0 && applications.length >= maxApplicants) {
          updateApplicantCapUi();
          announce((strings.maxApplicantsReachedNotice || '').replace('%d', String(maxApplicants)), true);
          return;
        }
        applications.push(blankApplication(button.dataset.chadaTravelAddApplicant));
        countryQuantities[button.dataset.chadaTravelAddApplicant] = applicantCountFor(button.dataset.chadaTravelAddApplicant);
        persistClientState();
        renderCountryBrowser();
        renderCartStatus();
        renderApplicants();
      });
    });
    renderSummary(app.querySelector('#chada-travel-stage-2-summary'), applications, strings.estimatedTotal
      || 'Estimated Total');
    renderDuplicateNotice();
    updateApplicantCapUi();
  };

  const renderReview = () => {
    const bookerBox = app.querySelector('#chada-travel-review-booker');
    if (bookerBox) {
      bookerBox.innerHTML = `
        <div class="chada-travel-review-line"><span aria-hidden="true">&#9675;</span>`
          + `<span>${escapeHtml(fullName(bookerDetails.first_name, bookerDetails.last_name))}</span></div>
        <div class="chada-travel-review-line"><span aria-hidden="true">@</span>`
          + `<span>${escapeHtml(bookerDetails.email)}</span></div>
        <div class="chada-travel-review-line"><span aria-hidden="true">&#9742;</span>`
          + `<span>${escapeHtml(bookerDetails.mobile)}</span></div>
        <div class="chada-travel-review-line"><span aria-hidden="true">&#8962;</span>`
          + `<span>${escapeHtml(bookerDetails.address || strings.notProvided || '')}</span></div>`;
    }
    const lookup = countryLookup();
    const tbody = app.querySelector('#chada-travel-review-applications');
    if (tbody) {
      tbody.innerHTML = applications.map((application, index) => {
        const country = lookup[application.country_code] || {};
        return `<tr>
          <td>${escapeHtml(country.name || application.country_code)}</td>
          <td>${escapeHtml(fullName(application.first_name, application.last_name))}</td>
          <td>${escapeHtml(formatDate(application.target_travel_date))}</td>
          <td>${formatMoney(country.processing_fee || 0)}</td>
          <td><div class="chada-travel-table-actions">
            <button class="chada-travel-button chada-travel-button--small" type="button" data-chada-travel-edit-application="${index}">`
              + `${escapeHtml(strings.editApplication || 'Edit')}</button>
            <button class="chada-travel-button chada-travel-button--small" type="button" data-chada-travel-remove-review="${index}">`
              + `${escapeHtml(strings.removeApplicant || 'Remove')}</button>
          </div></td>
        </tr>`;
      }).join('');
      tbody.querySelectorAll('[data-chada-travel-edit-application]').forEach((button) => {
        button.addEventListener('click', () => goToStage(2));
      });
      tbody.querySelectorAll('[data-chada-travel-remove-review]').forEach((button) => {
        button.addEventListener('click', async () => {
          if (applications.length <= 1) {
            announce(strings.atLeastOneApplication || '', true);
            return;
          }
          if (!await confirmAction('Delete this applicant record?', 'Delete Applicant')) return;
          const removed = applications.splice(Number(button.dataset.chadaTravelRemoveReview), 1)[0];
          if (removed) countryQuantities[removed.country_code] = applicantCountFor(removed.country_code) || 1;
          persistClientState();
          renderCountryBrowser();
          renderCartStatus();
          renderReview();
        });
      });
    }
    renderSummary(app.querySelector('#chada-travel-stage-3-summary'), applications, strings.orderTotal || 'Total');
  };

  const PAYMENT_MARKS = { chada_travel_bank: 'BANK', chada_travel_digital_wallet: 'QR' };

  const renderPaymentOptions = () => {
    const container = app.querySelector('[data-chada-travel-payment-options]');
    if (!container) return;
    const payment = settings.payment || {};
    const methods = payment.methods || [];
    const labels = payment.methodLabels || {};
    if (!methods.length) {
      container.innerHTML = `<p class="chada-travel-status-note" role="alert">`
        + `${escapeHtml(strings.paymentUnavailableNotice
          || 'Payment is temporarily unavailable. Please try again later or contact support.')}</p>`;
      return;
    }
    container.innerHTML = methods.map((method) => {
      const isSelected = method === paymentMethod;
      const isDefault = method === payment.defaultMethod;
      const badge = isDefault ? `<span class="chada-travel-badge">${escapeHtml(strings.defaultBadge || 'Default')}</span>` : '';
      return `<button class="chada-travel-payment-option${isSelected ? ' is-selected' : ''}" type="button" role="radio" `
        + `aria-checked="${isSelected}" data-chada-travel-payment-method="${method}">`
        + `<span class="chada-travel-payment-option__mark" aria-hidden="true">${escapeHtml((payment.marks || {})[method] || PAYMENT_MARKS[method] || '?')}</span>`
        + `<span class="chada-travel-payment-option__label">${escapeHtml(labels[method] || method)}</span>${badge}`
        + `<span class="chada-travel-payment-option__radio" aria-hidden="true"></span></button>`;
    }).join('');
    container.querySelectorAll('[data-chada-travel-payment-method]').forEach((button) => {
      button.addEventListener('click', () => {
        paymentMethod = button.dataset.chadaTravelPaymentMethod;
        renderPaymentOptions();
        renderPaymentPanels();
      });
    });
  };

  const bankAccountCardTemplate = (account) => {
    const rows = [
      ['Bank Name', account.bank_name || ''],
      ['Account Name', account.account_name || ''],
      ['Account Number', account.account_number || ''],
    ];
    if (account.branch) rows.push(['Branch', account.branch]);
    if (account.account_type) rows.push(['Account Type', account.account_type]);
    const fields = rows.map(([label, value]) => `<dt>${escapeHtml(label)}:</dt><dd>${escapeHtml(value)}</dd>`).join('');
    const instructions = account.instructions ? `<p>${escapeHtml(account.instructions)}</p>` : '';
    return `<div class="chada-travel-bank-account-card">`
      + `<h4>${escapeHtml(account.label || 'Company Bank Account')}</h4>`
      + `<dl class="chada-travel-key-value">${fields}</dl>${instructions}</div>`;
  };

  const bankInitialTemplate = () => {
    const accounts = (settings.payment || {}).bankAccounts || [];
    const cards = accounts.map(bankAccountCardTemplate).join('');
    return `<h3>${escapeHtml(strings.bankTitle || 'Bank Payment')}</h3>`
      + `<p>${escapeHtml(strings.copyBankNotice || '')}</p><div class="chada-travel-bank-account-list">${cards}</div>`
      + `<button class="chada-travel-button chada-travel-button--primary chada-travel-button--block" type="button" data-chada-travel-select-bank>`
      + `${escapeHtml(strings.selectBankPayment || 'Select Bank Payment')}</button>`
      + `<p class="chada-travel-status-note">${escapeHtml(strings.bankBookingNotice || '')}</p>`;
  };

  const bankAwaitingTemplate = () => `<h3>${escapeHtml(strings.bankTitle || 'Bank Payment')}</h3>`
    + `<p><strong>${escapeHtml(strings.bookingIdLabel || 'Booking ID')}:</strong> `
    + `<span data-chada-travel-booking-id>${escapeHtml((paymentSelection || {}).bookingId || '')}</span></p>`
    + `<span class="chada-travel-badge" data-status="awaiting">${escapeHtml(strings.awaitingProofBadge || 'Awaiting Proof')}</span>`
    + `<p>${escapeHtml(strings.proofUseBookingId || '')}</p>`;

  const digitalWalletInitialTemplate = () => {
    const wallet = (settings.payment || {}).digitalWallet || {};
    const providerName = wallet.name || strings.digitalWalletFallbackTitle || 'Digital Wallet';
    const detailRows = [
      [strings.digitalWalletNameLabel || 'Digital Wallet Name', providerName],
      [strings.digitalWalletAccountNameLabel || 'Account Name', wallet.accountName || ''],
      [strings.digitalWalletAccountNumberLabel || 'Account/Mobile Number', wallet.accountNumber || ''],
    ].map(([label, value]) => `<dt>${escapeHtml(label)}:</dt><dd>${escapeHtml(value)}</dd>`).join('');
    return `<h3>${escapeHtml(providerName)}</h3><div class="chada-travel-digital-wallet-layout">`
      + `<img class="chada-travel-qr-image" src="${escapeHtml(wallet.qrUrl || '')}" `
      + `alt="${escapeHtml(providerName)} payment QR Code">`
      + `<div><dl class="chada-travel-key-value">${detailRows}</dl>`
      + `<label class="chada-travel-field">${escapeHtml(strings.digitalWalletReferenceLabel || 'Reference No.')} *`
      + `<input class="chada-travel-control" data-chada-travel-digital-wallet-reference autocomplete="off" inputmode="text" `
      + `maxlength="190" required></label></div></div>`
      + `<button class="chada-travel-button chada-travel-button--primary chada-travel-button--block" type="button" `
      + `data-chada-travel-submit-digital-wallet>`
      + `${escapeHtml(strings.submitDigitalWalletPayment || 'Submit Digital Wallet Payment Details')}</button>`
      + `<p class="chada-travel-status-note">${escapeHtml(strings.digitalWalletBookingNotice || '')}</p>`;
  };

  const digitalWalletAwaitingTemplate = () => {
    const wallet = (settings.payment || {}).digitalWallet || {};
    const providerName = wallet.name || strings.digitalWalletFallbackTitle || 'Digital Wallet';
    return `<h3>${escapeHtml(providerName)}</h3>`
      + `<p><strong>${escapeHtml(strings.bookingIdLabel || 'Booking ID')}:</strong> `
      + `<span data-chada-travel-booking-id>${escapeHtml((paymentSelection || {}).bookingId || '')}</span></p>`
      + `<span class="chada-travel-badge" data-status="awaiting">`
      + `${escapeHtml(strings.awaitingVerificationBadge || 'Awaiting Verification')}</span>`
      + `<p>${escapeHtml(strings.digitalWalletReferenceLabel || 'Reference No.')}: `
      + `${escapeHtml((paymentSelection || {}).digitalWalletReferenceNo || '')}</p>`;
  };

  const bindBankActions = (body) => {
    body.querySelector('[data-chada-travel-select-bank]')?.addEventListener('click', async () => {
      announce(strings.savingPayment || '');
      const { ok, data } = await apiPost('payments/bank/select', {});
      if (!ok) {
        announce(data.message || strings.genericError || '', true);
        return;
      }
      paymentSelection = {
        method: 'chada_travel_bank', status: data.paymentStatus, bookingId: data.bookingId,
        proofUrl: data.proofUrl || '',
      };
      persistClientState();
      accessibleStages = data.accessibleStages || accessibleStages;
      renderProgress();
      renderPaymentPanels();
      announce(strings.ready || '');
    });
  };

  const bindDigitalWalletActions = (body) => {
    body.querySelector('[data-chada-travel-submit-digital-wallet]')?.addEventListener('click', async () => {
      const input = body.querySelector('[data-chada-travel-digital-wallet-reference]');
      const referenceNo = input ? input.value.trim() : '';
      if (!referenceNo) {
        announce(strings.digitalWalletReferenceRequired || '', true);
        return;
      }
      announce(strings.savingPayment || '');
      const { ok, data } = await apiPost('payments/digital-wallet/submit', {
        digital_wallet_reference_no: referenceNo,
      });
      if (!ok) {
        announce(data.message || strings.genericError || '', true);
        return;
      }
      paymentSelection = {
        method: 'chada_travel_digital_wallet', status: data.paymentStatus, bookingId: data.bookingId,
        digitalWalletReferenceNo: data.digitalWalletReferenceNo,
      };
      accessibleStages = data.accessibleStages || accessibleStages;
      renderProgress();
      renderPaymentPanels();
      announce(strings.ready || '');
    });
  };

  const renderPaymentPanels = () => {
    const availableMethods = (settings.payment || {}).methods || [];
    app.querySelectorAll('[data-chada-travel-payment-panel]').forEach((panel) => {
      const method = panel.dataset.chadaTravelPaymentPanel;
      if (!availableMethods.length) {
        panel.hidden = true;
        return;
      }
      panel.hidden = method !== paymentMethod;
      const body = panel.querySelector('[data-chada-travel-payment-body]');
      if (!body) return;
      if (method === 'chada_travel_bank') {
        const isAwaiting = paymentSelection && paymentSelection.method === 'chada_travel_bank';
        body.innerHTML = isAwaiting ? bankAwaitingTemplate() : bankInitialTemplate();
        if (!isAwaiting) bindBankActions(body);
      } else if (method === 'chada_travel_digital_wallet') {
        const isAwaiting = paymentSelection && paymentSelection.method === 'chada_travel_digital_wallet';
        body.innerHTML = isAwaiting ? digitalWalletAwaitingTemplate() : digitalWalletInitialTemplate();
        if (!isAwaiting) bindDigitalWalletActions(body);
      } else if (window.dispatchEvent) {
        window.dispatchEvent(new CustomEvent('chada-travel:payment-provider-panel', {
          detail: { method, body, settings, selection: paymentSelection, render: renderPaymentPanels, apiPost },
        }));
      }
    });
  };

  const renderPaymentStage = () => {
    renderPaymentOptions();
    renderPaymentPanels();
    renderSummary(app.querySelector('#chada-travel-stage-4-summary'), applications, strings.orderTotal || 'Total');
  };

  const fetchConfirmation = () => apiPost('confirmation', {});

  const methodLabelFor = (method) => (((settings.payment || {}).methodLabels || {})[method]) || method || '';

  const buildServicesTable = (services, total) => {
    const rows = (services || []).map((line) => `<tr><td>${escapeHtml(line.label)}</td>`
      + `<td>${escapeHtml(formatMoney(line.amount))}</td></tr>`).join('');
    return '<div class="chada-travel-table-wrap"><table class="chada-travel-table"><thead><tr>'
      + `<th scope="col">${escapeHtml(strings.serviceColumnLabel || 'Service')}</th>`
      + `<th scope="col">${escapeHtml(strings.amountColumnLabel || 'Amount')}</th></tr></thead>`
      + `<tbody>${rows}</tbody><tfoot><tr><th>${escapeHtml(strings.orderTotal || 'Total')}</th>`
      + `<th>${escapeHtml(formatMoney(total))}</th></tr></tfoot></table></div>`;
  };

  const transactionSummary = (data, extraRowsHtml = '') => '<dl class="chada-travel-key-value">'
    + `<dt>${escapeHtml(strings.bookerLabel || 'Booker')}:</dt>`
    + `<dd>${escapeHtml(fullName(data.booker.firstName, data.booker.lastName))}</dd>`
    + `<dt>${escapeHtml(strings.paymentMethodLabel || 'Payment Method')}:</dt>`
    + `<dd>${escapeHtml(methodLabelFor(data.paymentMethod))}</dd>`
    + extraRowsHtml
    + `<dt>${escapeHtml(strings.dateTimeLabel || 'Date/Time')}:</dt>`
    + `<dd data-chada-travel-datetime>${escapeHtml(data.createdAt || '')}</dd>`
    + '</dl>';

  const paidConfirmationTemplate = (data) => '<article class="chada-travel-panel"><div class="chada-travel-panel__header">'
    + `<h3>${escapeHtml(strings.paymentConfirmationHeading || 'Payment Confirmation')}</h3>`
    + `<span class="chada-travel-badge" data-status="paid">${escapeHtml(strings.paidBadge || 'PAID')}</span></div>`
    + '<div class="chada-travel-panel__body">'
    + `<dl class="chada-travel-key-value">`
    + `<dt>${escapeHtml(strings.bookerLabel || 'Booker')}:</dt>`
    + `<dd>${escapeHtml(fullName(data.booker.firstName, data.booker.lastName))}</dd>`
    + `<dt>${escapeHtml(strings.paymentMethodLabel || 'Payment Method')}:</dt>`
    + `<dd>${escapeHtml(methodLabelFor(data.paymentMethod))}</dd>`
    + `<dt>${escapeHtml(strings.transactionIdLabel || 'Unique Transaction ID')}:</dt>`
    + `<dd data-chada-travel-transaction-id>${escapeHtml(data.payment.transactionId || '')}</dd>`
    + `<dt>${escapeHtml(strings.paymentDateLabel || 'Payment Date')}:</dt>`
    + `<dd data-chada-travel-payment-date>${escapeHtml(data.payment.paidAt || '')}</dd></dl>`
    + `<h3 class="chada-travel-panel-subheading">${escapeHtml(strings.servicesPaidHeading || 'Services Paid')}</h3>`
    + buildServicesTable(data.services, data.total)
    + '<div class="chada-travel-confirmation-resources">'
    + `<button class="chada-travel-button" type="button" data-chada-travel-upload-initial-files>`
    + `${escapeHtml(strings.uploadInitialFiles || 'Upload Initial Files')}</button>`
    + `<button class="chada-travel-button" type="button" data-chada-travel-view-guide>`
    + `${escapeHtml(strings.viewGuideChecklists || 'View Guide & Checklists')}</button></div>`
    + `<p class="chada-travel-status-note">${escapeHtml(strings.emailDeliveryNotice || '')}</p></div></article>`;

  const pendingConfirmationTemplate = (data) => {
    const isAwaitingProof = data.paymentStatus === 'chada_travel_awaiting_proof';
    const badgeLabel = isAwaitingProof ? (strings.awaitingProofBadge || 'Awaiting Proof')
      : (strings.awaitingVerificationBadge || 'Awaiting Verification');
    const instruction = isAwaitingProof ? (strings.awaitingProofInstruction || '')
      : (strings.awaitingVerificationInstruction || '');
    const extraRow = data.payment.digitalWalletReferenceNo
      ? `<dt>${escapeHtml(strings.digitalWalletReferenceLabel || 'Reference No.')}:</dt>`
        + `<dd>${escapeHtml(data.payment.digitalWalletReferenceNo)}</dd>` : '';
    const actionHtml = isAwaitingProof
      ? `<button class="chada-travel-button chada-travel-button--primary chada-travel-button--block" type="button" `
        + `data-chada-travel-upload-bank-proof>${escapeHtml(strings.uploadBankPaymentProofAction || 'Upload Bank Payment Proof')}`
        + '</button>'
      : `<button class="chada-travel-button chada-travel-button--block" type="button" disabled>`
        + `${escapeHtml(strings.waitingForAdminVerification || 'Waiting for Admin Verification')}</button>`;
    return '<article class="chada-travel-panel"><div class="chada-travel-panel__header">'
      + `<h3>${escapeHtml(methodLabelFor(data.paymentMethod))}</h3>`
      + `<span class="chada-travel-badge" data-status="awaiting">${escapeHtml(badgeLabel)}</span></div>`
      + '<div class="chada-travel-panel__body">'
      + transactionSummary(data, extraRow)
      + buildServicesTable(data.services, data.total)
      + `<p class="chada-travel-status-note">${escapeHtml(instruction)}</p>`
      + `<p class="chada-travel-status-note">${escapeHtml(strings.noPaidResourcesNotice || '')}</p>`
      + actionHtml + '</div></article>';
  };

  const rejectedConfirmationTemplate = (data) => '<article class="chada-travel-panel"><div class="chada-travel-panel__header">'
    + `<h3>${escapeHtml(methodLabelFor(data.paymentMethod))}</h3>`
    + `<span class="chada-travel-badge" data-status="rejected">${escapeHtml(strings.rejectedBadge || 'Rejected')}</span></div>`
    + '<div class="chada-travel-panel__body">'
    + transactionSummary(data)
    + buildServicesTable(data.services, data.total)
    + `<p class="chada-travel-status-note">${escapeHtml(data.payment.rejectionReason || strings.rejectedInstruction || '')}</p>`
    + '</div></article>';

  const goToUploadPortal = async () => {
    const { ok, data } = await apiPost('uploads/link', {});
    if (!ok) {
      announce(data.message || strings.genericError || '', true);
      return;
    }
    window.location.href = data.uploadUrl;
  };

  const goToBankProofPage = () => {
    const paymentProofUrl = (paymentSelection && paymentSelection.proofUrl) || settings.paymentProofUrl || '';
    const separator = paymentProofUrl.includes('?') ? '&' : '?';
    const bookingId = confirmationData ? confirmationData.bookingId : '';
    window.location.href = `${paymentProofUrl}${separator}booking_id=${encodeURIComponent(bookingId || '')}`;
  };

  const renderConfirmationActions = (data) => {
    const container = app.querySelector('#chada-travel-confirmation-actions');
    if (!container) return;
    const buttons = [];
    if (data.canReturnToPayment) {
      buttons.push(`<button class="chada-travel-button" type="button" data-chada-travel-stage-target="4">`
        + `${escapeHtml(strings.returnToPayment || 'Return to Payment')}</button>`);
    }
    buttons.push('<button class="chada-travel-button" type="button" data-chada-travel-return-home>'
      + `${escapeHtml(strings.returnToHome || 'Return to Home')}</button>`);
    container.innerHTML = buttons.join('');
    bindStageTargets(container);
    container.querySelector('[data-chada-travel-return-home]')?.addEventListener('click', () => {
      window.location.href = settings.homeUrl || '/';
    });
  };

  const renderConfirmation = async () => {
    const bannerContainer = app.querySelector('#chada-travel-confirmation-banner');
    const contentContainer = app.querySelector('#chada-travel-confirmation-content');
    if (!bannerContainer || !contentContainer) return;
    const { ok, data } = await fetchConfirmation();
    if (!ok) {
      announce(data.message || strings.genericError || '', true);
      return;
    }
    confirmationData = data;

    bannerContainer.innerHTML = '<div class="chada-travel-confirmation-banner">'
      + '<span class="chada-travel-confirmation-banner__check" aria-hidden="true">&#10003;</span>'
      + `<div><h3>${escapeHtml(strings.bookingIdLabel || 'Booking ID')}: `
      + `<span data-chada-travel-booking-id>${escapeHtml(data.bookingId || '')}</span></h3>`
      + `<p>${escapeHtml(strings.savedIdNotice || '')}</p></div></div>`;

    if (data.paymentStatus === 'chada_travel_paid') {
      contentContainer.innerHTML = paidConfirmationTemplate(data);
    } else if (data.paymentStatus === 'chada_travel_rejected') {
      contentContainer.innerHTML = rejectedConfirmationTemplate(data);
    } else {
      contentContainer.innerHTML = pendingConfirmationTemplate(data);
    }
    contentContainer.querySelector('[data-chada-travel-upload-initial-files]')?.addEventListener('click', goToUploadPortal);
    contentContainer.querySelector('[data-chada-travel-view-guide]')?.addEventListener('click', goToUploadPortal);
    contentContainer.querySelector('[data-chada-travel-upload-bank-proof]')?.addEventListener('click', goToBankProofPage);

    renderConfirmationActions(data);
  };

  const checkoutForm = app.querySelector('#chada-travel-booker-applicants-form');
  const syncBookerFromForm = () => {
    if (!checkoutForm) return;
    bookerDetails = {
      first_name: checkoutForm.elements.first_name?.value || '',
      last_name: checkoutForm.elements.last_name?.value || '',
      email: checkoutForm.elements.email?.value || '',
      mobile: checkoutForm.elements.mobile?.value || '',
      address: checkoutForm.elements.address?.value || '',
      privacy_terms_consent: checkoutForm.elements.privacy_terms_consent?.checked || false,
    };
    const bookerApplicant = applications.find((row) => row.is_booker);
    if (bookerApplicant) {
      bookerApplicant.first_name = bookerDetails.first_name;
      bookerApplicant.last_name = bookerDetails.last_name;
    }
    persistClientState();
  };

  const fillBookerForm = () => {
    if (!checkoutForm) return;
    ['first_name', 'last_name', 'email', 'mobile', 'address'].forEach((name) => {
      if (checkoutForm.elements[name]) checkoutForm.elements[name].value = bookerDetails[name] || '';
    });
    if (checkoutForm.elements.privacy_terms_consent) {
      checkoutForm.elements.privacy_terms_consent.checked = Boolean(bookerDetails.privacy_terms_consent);
    }
  };

  checkoutForm?.querySelectorAll('input, textarea').forEach((field) => {
    field.addEventListener(field.type === 'checkbox' ? 'change' : 'input', () => {
      syncBookerFromForm();
      if (applications.some((row) => row.is_booker)) renderApplicants();
    });
  });

  checkoutForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(checkoutForm);
    syncBookerFromForm();
    if (!applications.length) {
      announce(strings.atLeastOneApplication || '', true);
      goToStage(1);
      return;
    }
    if (browserStateExpiredWithToken) {
      announce(strings.draftExpiredNotice || '', true);
      return;
    }
    const payload = {
      first_name: bookerDetails.first_name, last_name: bookerDetails.last_name, email: bookerDetails.email,
      mobile: bookerDetails.mobile, address: bookerDetails.address,
      privacy_consent: bookerDetails.privacy_terms_consent, terms_consent: bookerDetails.privacy_terms_consent,
    };
    announce(strings.savingBooker || '');
    const submitButton = checkoutForm.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    const bookerResult = await apiPost('booker', payload);
    if (!bookerResult.ok) {
      if (submitButton) submitButton.disabled = false;
      Object.entries(bookerResult.data.errors || {}).forEach(([field, message]) => {
        showFieldError(checkoutForm, field === 'consent' ? 'privacy_terms_consent' : field, message);
      });
      announce(bookerResult.data.message || strings.genericError || '', true);
      return;
    }
    setDraftToken(bookerResult.data.draftToken);
    bookerDetails = Object.assign(bookerDetails, bookerResult.data.booker || {});
    accessibleStages = bookerResult.data.accessibleStages || [1, 2];
    applyPolicy(bookerResult.data.policy);
    persistClientState();
    const container = app.querySelector('#chada-travel-applicant-list');
    if (container) clearFieldErrors(container);
    announce(strings.savingApplications || '');
    const { ok, data } = await apiPost('applications', { applications });
    if (submitButton) submitButton.disabled = false;
    if (!ok) {
      (data.errors || []).forEach((rowErrors, index) => {
        if (!rowErrors) return;
        const panel = container?.querySelector(`[data-chada-travel-applicant-index="${index}"]`);
        Object.entries(rowErrors).forEach(([field, message]) => {
          if (field === '_all' || !panel) {
            announce(message, true);
            return;
          }
          showFieldError(panel, field, message);
        });
      });
      announce(data.message || strings.genericError || '', true);
      return;
    }
    accessibleStages = data.accessibleStages || accessibleStages;
    persistClientState();
    announce(strings.ready || '');
    goToStage(3);
  });

  const reviewForm = app.querySelector('#chada-travel-review-form');
  reviewForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(reviewForm);
    if (!applications.length) {
      announce(strings.atLeastOneApplication || '', true);
      return;
    }
    const consentField = reviewForm.elements.cancellation_refund_consent;
    announce(strings.savingReview || '');
    const submitButton = reviewForm.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    const { ok, data } = await apiPost('review', { cancellation_refund_consent: consentField?.checked || false });
    if (submitButton) submitButton.disabled = false;
    if (!ok) {
      if (data.errors?.cancellation_refund_consent) {
        showFieldError(reviewForm, 'cancellation_refund_consent', data.errors.cancellation_refund_consent);
      }
      announce(data.message || strings.genericError || '', true);
      return;
    }
    accessibleStages = data.accessibleStages || accessibleStages;
    announce(strings.ready || '');
    goToStage(4);
  });

  const cancelApplication = async () => {
    if (!applications.length) return;
    const confirmed = await confirmAction(
      'Cancel this entire Visa Application and discard all entered Booker and applicant information?',
      strings.cancelApplication || 'Cancel Application',
    );
    if (!confirmed) return;
    if (draftToken) {
      announce('Requesting application cancellation...');
      const { ok, data } = await apiPost('cancel', {}, { preserveOnDraftError: true });
      if (!ok) {
        if (data.code === 'chada_travel_order_paid') {
          const confirmation = await fetchConfirmation();
          if (confirmation.ok) {
            accessibleStages = confirmation.data.accessibleStages || accessibleStages;
            goToStage(5);
            return;
          }
        }
        const failure = data.message || 'The application was not cancelled.';
        announce(`${failure} Your information has been retained.`, true);
        return;
      }
      if (data.cancelled !== true) {
        announce(data.message || 'The application was not cancelled. Your information has been retained.', true);
        return;
      }
    }
    resetClientState(true);
    fillBookerForm();
    renderCountryBrowser();
    renderApplicants();
    renderCartStatus();
    goToStage(1);
    announce('The Visa Application was cancelled.');
  };

  app.querySelector('[data-chada-travel-stage-one-next]')?.addEventListener('click', () => {
    if (!applications.length) {
      announce(strings.emptyCartNotice || strings.atLeastOneApplication || '', true);
      return;
    }
    accessibleStages = Array.from(new Set([...accessibleStages, 1, 2]));
    renderProgress();
    goToStage(2);
  });
  app.querySelector('[data-chada-travel-add-country]')?.addEventListener('click', () => goToStage(1));
  app.querySelectorAll('[data-chada-travel-cancel-application]').forEach((button) => {
    button.addEventListener('click', cancelApplication);
  });

  restoreClientState();
  applyHashCountrySelection();
  fillBookerForm();
  renderCountryBrowser();
  renderApplicants();
  renderCartStatus();
  bindStageTargets(app);
  renderProgress();
  if (!statusEls[0]?.textContent) announce(strings.ready || '');

  // A returning Booker (page reload, or a fresh visit with the draft token still in sessionStorage - e.g. after
  // submitting Bank proof on the separate proof page) lands directly on Stage 5 when it is actually ready,
  // instead of restarting at Stage 1. Manual payment states remain on their normal confirmation path:
  // checkout/confirmation gate (CHADA_TRAVEL_Workflow::is_stage_five_ready()) only succeeds for the payment statuses
  // that have a real Stage 5 view.
  if (draftToken) {
    fetchConfirmation().then(({ ok, data }) => {
      if (ok) {
        accessibleStages = data.accessibleStages || accessibleStages;
        clearStoredState(false);
        goToStage(5);
        return;
      }
      if (data.code === 'chada_travel_draft_expired') return; // handleDraftExpired() already reset the page.
    });
  }
})();
