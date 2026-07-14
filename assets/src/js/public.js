/**
 * Public invitation entry (compiled to assets/build/js/public.js).
 */
import { initEnvelopeController } from "./modules/envelope-controller.js";
import { initAllPosterViewports, initPosterPages } from "./modules/poster-viewport.js";
import { initRsvp } from "./modules/rsvp.js";
import { initWishlist } from "./modules/wishlist.js";

(function () {
  "use strict";

  document.documentElement.classList.add("pks-oi-public-js");

  const i18n = (window.pksOiPublic && window.pksOiPublic.i18n) || {};
  const recalculatePosters = initAllPosterViewports();

  initPosterPages(i18n);
  initEnvelopeController({ recalculatePosters: recalculatePosters });
  initRsvp(i18n);
  initWishlist(i18n);
})();
