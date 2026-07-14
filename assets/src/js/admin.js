/**
 * WooCommerce product admin — online_invitation panel visibility.
 */
(function ($) {
  "use strict";

  var invitationType =
    window.pksOiAdmin && window.pksOiAdmin.productType
      ? window.pksOiAdmin.productType
      : "online_invitation";

  function extendInvitationPanelClasses() {
    $(".options_group, .form-field, .stock_fields, .pricing").each(function () {
      var $el = $(this);
      if (
        $el.hasClass("show_if_simple") ||
        $el.hasClass("show_if_virtual") ||
        $el.hasClass("pricing")
      ) {
        $el.addClass("show_if_" + invitationType);
      }
    });
  }

  function restoreProductDataTabs() {
    $(".product_data_tabs > li").show();
  }

  function syncVirtualCheckbox() {
    if ($("#product-type").val() === invitationType) {
      $("#_virtual").prop("checked", true);
    }
  }

  function refreshPanels() {
    restoreProductDataTabs();
    extendInvitationPanelClasses();
    syncVirtualCheckbox();
    $("#_virtual, #_downloadable").trigger("change");
  }

  $(function () {
    if (!$("#product-type").length) {
      return;
    }

    extendInvitationPanelClasses();
    $("#product-type").on("change", refreshPanels);
    refreshPanels();
    $("#product-type").trigger("change");
  });
})(jQuery);
