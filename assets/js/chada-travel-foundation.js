(() => {
  'use strict';

  const settings = window.chadaTravelCheckoutSettings || {};
  const app = document.querySelector('.chada-travel-app');
  if (!app) return;

  const status = app.querySelector('[data-chada-travel-live-status]');
  const announce = (message, isError = false) => {
    if (!status) return;
    status.textContent = message;
    status.classList.toggle('is-error', isError);
  };

  app.querySelectorAll('[data-chada-travel-stage-target]').forEach((control) => {
    control.addEventListener('click', () => {
      const requestedStage = Number(control.dataset.chadaTravelStageTarget || 0);
      app.dispatchEvent(new CustomEvent('chada-travel:stage-requested', {
        bubbles: true,
        detail: { requestedStage, currentStage: Number(settings.currentStage || 1) }
      }));
    });
  });

  app.addEventListener('chada-travel:stage-blocked', () => announce(settings.strings?.stageBlocked || '', true));
  announce(settings.strings?.ready || '');
})();
