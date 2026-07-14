/**
 * Photo share landing page entry.
 */
import { initPhotoShare } from "./modules/photo-share.js";

(function () {
  "use strict";
  document.documentElement.classList.add("pks-oi-public-js");
  const i18n = (window.pksOiPhotoShare && window.pksOiPhotoShare.i18n) || {};
  initPhotoShare(i18n);
})();
