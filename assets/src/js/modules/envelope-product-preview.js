/**
 * Mirror BPP page thumbnails inside the product envelope preview.
 */
export function initEnvelopeProductPreview(root) {
  var host = root.querySelector('[data-pks-oi-envelope-thumbnail-host]');
  if (!host) {
    return;
  }

  var img = host.querySelector('.pks-oi-product-envelope-preview__invitation-image');
  if (!img) {
    img = document.createElement('img');
    img.className = 'pks-oi-product-envelope-preview__invitation-image';
    img.alt = '';
    img.loading = 'lazy';
    img.decoding = 'async';
    host.appendChild(img);
  }

  var fallbackThumbnails = {};
  var rawThumbnails = host.getAttribute('data-pks-oi-page-thumbnails');

  if (rawThumbnails) {
    try {
      fallbackThumbnails = JSON.parse(rawThumbnails);
    } catch (error) {
      fallbackThumbnails = {};
    }
  }

  function parseBackgroundUrl(element) {
    if (!element) {
      return '';
    }

    var inlineStyle = element.style.backgroundImage;
    var computedStyle = inlineStyle || window.getComputedStyle(element).backgroundImage;

    if (!computedStyle || computedStyle === 'none') {
      return '';
    }

    var match = computedStyle.match(/url\(["']?([^"')]+)["']?\)/);
    return match ? match[1] : '';
  }

  function getActivePageIndex() {
    if (typeof window.current_page !== 'undefined' && window.current_page !== null && window.current_page !== '') {
      return String(window.current_page);
    }

    var customizer = document.getElementById('customizer-area');
    if (customizer) {
      return String(customizer.getAttribute('data-active-index') || host.getAttribute('data-pks-oi-active-page') || '0');
    }

    return String(host.getAttribute('data-pks-oi-active-page') || '0');
  }

  function getThumbnailForPage(page) {
    var thumbnail = document.querySelector('.bpp-page-thumbnail[data-page="' + page + '"]');
    if (thumbnail) {
      var thumbnailUrl = parseBackgroundUrl(thumbnail);
      if (thumbnailUrl) {
        return thumbnailUrl;
      }
    }

    var hiddenInput = document.getElementById('page-thumbnail-' + page);
    if (hiddenInput && hiddenInput.value) {
      return hiddenInput.value;
    }

    if (fallbackThumbnails[page]) {
      return fallbackThumbnails[page];
    }

    return '';
  }

  function updateThumbnail() {
    var page = getActivePageIndex();
    var url = getThumbnailForPage(page);

    if (!url) {
      var activeThumbnail = document.querySelector('.bpp-page-thumbnail.active');
      url = parseBackgroundUrl(activeThumbnail);
    }

    if (url) {
      img.src = url;
      host.classList.add('has-thumbnail');
      host.setAttribute('data-pks-oi-active-page', page);

      var label = host.querySelector('.pks-oi-product-envelope-preview__inner-label');
      if (label) {
        label.hidden = true;
      }
      return;
    }

    host.classList.remove('has-thumbnail');
  }

  updateThumbnail();

  if (window.jQuery) {
    window.jQuery(document).on('page-change', updateThumbnail);
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('.bpp-page-thumbnail')) {
      window.setTimeout(updateThumbnail, 0);
    }
  });

  var thumbnailContainer = document.querySelector('.bpp-page-thumbnail-container');
  if (thumbnailContainer && typeof MutationObserver !== 'undefined') {
    var thumbnailObserver = new MutationObserver(updateThumbnail);
    thumbnailObserver.observe(thumbnailContainer, {
      attributes: true,
      attributeFilter: ['style', 'class'],
      subtree: true,
    });
  }
}
