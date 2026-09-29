(() => {
  'use strict';

  const settings = window.chadaTravelPaymentProofSettings || {};
  const strings = settings.strings || {};
  const app = document.querySelector('.chada-travel-proof-app');
  if (!app) return;

  const statusEl = app.querySelector('[data-chada-travel-proof-live-status]');
  const form = app.querySelector('#chada-travel-proof-form');

  const announce = (message, isError = false) => {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('is-error', isError);
  };

  const clearFieldErrors = () => {
    form.querySelectorAll('.chada-travel-field-error').forEach((node) => node.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
  };

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors();

    const bookingIdField = form.elements.booking_id;
    const fileField = form.querySelector('input[type="file"]');
    if (!form.elements.token?.value) {
      announce('Open the secure payment-proof link from your email before submitting proof.', true);
      return;
    }
    if (!bookingIdField?.value.trim()) {
      announce(strings.proofBookingIdLabel ? `${strings.proofBookingIdLabel} is required.` : 'Booking ID is required.', true);
      return;
    }
    if (!fileField?.files?.length) {
      announce(strings.proofFileLabel ? `${strings.proofFileLabel} is required.` : 'Choose a file to upload.', true);
      return;
    }

    const formData = new FormData();
    formData.append('booking_id', bookingIdField.value.trim());
    formData.append('token', form.elements.token?.value || '');
    formData.append('proof_file', fileField.files[0]);

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    announce(strings.savingPayment || '');

    let response;
    let data = {};
    try {
      response = await fetch(`${settings.restUrl || ''}payments/proof/submit`, {
        method: 'POST',
        headers: {
          'X-CHADA-TRAVEL-Nonce': settings.proofNonce || '',
          'X-CHADA-TRAVEL-Guest': settings.guestToken || '',
        },
        body: formData,
      });
      data = await response.json();
    } catch (error) {
      if (submitButton) submitButton.disabled = false;
      announce(strings.genericError || '', true);
      return;
    }

    if (!response.ok || !data.success) {
      if (submitButton) submitButton.disabled = false;
      announce(data.message || strings.genericError || '', true);
      return;
    }

    announce(strings.proofSubmittedNotice || '');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = strings.proofSubmittedNotice || submitButton.textContent;
    }
  });

  app.querySelector('[data-chada-travel-proof-back]')?.addEventListener('click', () => {
    window.location.href = settings.homeUrl || '/';
  });
})();
