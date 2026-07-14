/**
 * WooCommerce product admin — online_invitation panel visibility and envelope media picker.
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

  function bindEnvelopeImagePicker() {
    var $field = $(".pks-oi-envelope-image-field");
    if (!$field.length || typeof wp === "undefined" || !wp.media) {
      return;
    }

    var frame = null;
    var $input = $("#_pks_oi_envelope_image_id");
    var $preview = $field.find(".pks-oi-envelope-image-preview");
    var $remove = $field.find(".pks-oi-envelope-image-remove");

    $field.on("click", ".pks-oi-envelope-image-upload", function (event) {
      event.preventDefault();

      if (!frame) {
        var i18n = window.pksOiAdmin && window.pksOiAdmin.i18n ? window.pksOiAdmin.i18n : {};
        frame = wp.media({
          title: i18n.selectEnvelopeImage || "Select envelope image",
          button: { text: i18n.useImage || "Use image" },
          library: { type: "image" },
          multiple: false,
        });

        frame.on("select", function () {
          var attachment = frame.state().get("selection").first().toJSON();
          if (!attachment || !attachment.id) {
            return;
          }

          $input.val(String(attachment.id));
          $preview
            .show()
            .find("img")
            .attr("src", attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
          $remove.show();
        });
      }

      frame.open();
    });

    $field.on("click", ".pks-oi-envelope-image-remove", function (event) {
      event.preventDefault();
      $input.val("0");
      $preview.hide().find("img").attr("src", "");
      $remove.hide();
    });
  }

  $(function () {
    if (!$("#product-type").length) {
      return;
    }

    extendInvitationPanelClasses();
    bindEnvelopeImagePicker();
    $("#product-type").on("change", refreshPanels);
    refreshPanels();
    $("#product-type").trigger("change");
  });
})(jQuery);
