/**
 * Minimal product-page enhancements for online_invitation configurators.
 */
import { initEnvelopeProductPreview } from './modules/envelope-product-preview.js';

function boot() {
  'use strict';

  var root = document.querySelector('.pks-oi-product-configurator');
  if (!root) {
    return;
  }

  var envelopePreview = root.querySelector('.pks-oi-product-envelope-preview');
  if (envelopePreview) {
    initEnvelopeProductPreview(envelopePreview);
  }

  var form = root.closest('form.cart');
  var loading = root.querySelector('.pks-oi-product-configurator__loading');

  if (form && loading) {
    form.addEventListener('submit', function () {
      root.classList.add('is-loading');
      loading.hidden = false;
    });
  }

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reducedMotion) {
    root.classList.add('is-reduced-motion');
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
