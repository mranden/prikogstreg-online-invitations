/**
 * Photo wall public page entry.
 */
import { initPhotoWall } from "./modules/photo-share.js";

(function () {
  "use strict";
  document.documentElement.classList.add("pks-oi-public-js");
  initPhotoWall();
})();
