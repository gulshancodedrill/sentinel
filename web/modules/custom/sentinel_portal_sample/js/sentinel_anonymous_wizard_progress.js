/**
 * @file
 * Keeps "Step 1 of N" and the progress fill in sync with Company / Individual.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  function applyStep1(wrap) {
    const cfg = drupalSettings.sentinelAnonWizardProgressStep1;
    if (!cfg) {
      return;
    }
    const form = wrap.closest('form');
    if (!form) {
      return;
    }
    const selected = form.querySelector('input[name="user_type"]:checked');
    const isIndividual = selected && selected.value === 'individual';
    const line = isIndividual ? cfg.individualLine : cfg.companyLine;
    const lineEl = wrap.querySelector('.sentinel-anon-progress-line');
    if (lineEl) {
      lineEl.textContent = line;
    }
    wrap.setAttribute('aria-label', line);

    const fill = wrap.querySelector('.sentinel-anon-progress-fill');
    if (fill) {
      const pct = isIndividual ? cfg.individualPercent : cfg.companyPercent;
      fill.style.width = `${Number(pct)}%`;
    }
  }

  Drupal.behaviors.sentinelAnonymousWizardProgressStep1 = {
    attach(context) {
      once('sentinelAnonWizardStep1', '.sentinel-anon-progress-wrap[data-anon-progress-step1]', context).forEach((wrap) => {
        const form = wrap.closest('form');
        if (!form) {
          return;
        }
        applyStep1(wrap);
        form.querySelectorAll('input[name="user_type"]').forEach((input) => {
          input.addEventListener('change', () => applyStep1(wrap));
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
