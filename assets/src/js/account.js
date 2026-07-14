/**
 * My Account scripts (compiled to assets/build/js/account.js).
 */
import { initPhotoShareTools } from "./modules/photo-share-tools.js";
import { initAllPosterViewports } from "./modules/poster-viewport.js";

(function () {
  "use strict";

  document.documentElement.classList.add("pks-oi-js");

  function initBulkSelection(root) {
    var scope = root || document;
    var selectAll = scope.querySelector("[data-pks-oi-select-all]");
    var checkboxes = scope.querySelectorAll("[data-pks-oi-row-checkbox]");
    var bulkBar = scope.querySelector("[data-pks-oi-bulk-bar]");
    var bulkCount = scope.querySelector("[data-pks-oi-bulk-count]");

    if (!checkboxes.length) {
      return;
    }

    function updateBulkBar() {
      var selected = 0;
      checkboxes.forEach(function (box) {
        if (box.checked) {
          selected += 1;
        }
      });
      if (bulkBar) {
        bulkBar.classList.toggle("is-visible", selected > 0);
      }
      if (bulkCount) {
        bulkCount.textContent = String(selected);
      }
    }

    if (selectAll) {
      selectAll.addEventListener("change", function () {
        checkboxes.forEach(function (box) {
          box.checked = selectAll.checked;
        });
        updateBulkBar();
      });
    }

    checkboxes.forEach(function (box) {
      box.addEventListener("change", updateBulkBar);
    });
  }

  initBulkSelection(document);
  initAllPosterViewports();
  initPhotoShareTools((window.pksOiAccount && window.pksOiAccount.i18n) || {});
})();
