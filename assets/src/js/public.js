/**
 * Public invitation envelope and RSVP (compiled to assets/build/js/public.js).
 */
(function () {
  "use strict";

  document.documentElement.classList.add("pks-oi-public-js");

  var root = document.querySelector(".pks-oi-envelope");
  var openButton = document.getElementById("pks-oi-open-invitation");
  var content = document.getElementById("pks-oi-invitation-content");

  if (root && openButton && content) {
    var prefersReducedMotion =
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    function revealInvitation() {
      root.classList.add("is-open");
      content.hidden = false;
      openButton.setAttribute("aria-expanded", "true");
      if (content.focus) {
        content.focus({ preventScroll: true });
      }
    }

    if (prefersReducedMotion) {
      revealInvitation();
      openButton.hidden = true;
    } else {
      openButton.setAttribute("aria-controls", "pks-oi-invitation-content");
      openButton.setAttribute("aria-expanded", "false");
      openButton.addEventListener("click", revealInvitation);
      openButton.addEventListener("keydown", function (event) {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault();
          revealInvitation();
        }
      });
    }
  }

  var rsvpRoot = document.querySelector("[data-pks-oi-rsvp]");
  var rsvpForm = document.querySelector("[data-pks-oi-rsvp-form]");
  if (!rsvpRoot || !rsvpForm) {
    return;
  }

  var restUrl = rsvpRoot.getAttribute("data-rest-url") || "";
  var restNonce = rsvpRoot.getAttribute("data-rest-nonce") || "";
  var statusEl = rsvpForm.querySelector("[data-pks-oi-rsvp-status]");
  var personalLinkEl = rsvpForm.querySelector("[data-pks-oi-personal-link]");
  var attendeeWrap = rsvpForm.querySelector("[data-pks-oi-attendee-wrap]");
  var i18n = (window.pksOiPublic && window.pksOiPublic.i18n) || {};

  function setStatus(message, isError) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = message || "";
    statusEl.hidden = !message;
    statusEl.classList.toggle("is-error", !!isError);
    statusEl.setAttribute("aria-busy", message && !isError ? "true" : "false");
  }

  function updateAttendeeVisibility() {
    if (!attendeeWrap) {
      return;
    }
    var attending = rsvpForm.querySelector('input[name="attending"]:checked');
    var show = attending && attending.value === "yes";
    attendeeWrap.hidden = !show;
  }

  rsvpForm.querySelectorAll('input[name="attending"]').forEach(function (input) {
    input.addEventListener("change", updateAttendeeVisibility);
  });
  updateAttendeeVisibility();

  function buildPayload(formData) {
    var payload = {
      attending: formData.get("attending"),
      idempotency_key: "rsvp-" + Date.now() + "-" + Math.random().toString(36).slice(2),
    };

    if (formData.get("display_name")) {
      payload.display_name = formData.get("display_name");
    }
    if (formData.get("email")) {
      payload.email = formData.get("email");
    }
    if (formData.get("attendee_count")) {
      payload.attendee_count = formData.get("attendee_count");
    }
    if (formData.get("rsvp_comment")) {
      payload.rsvp_comment = formData.get("rsvp_comment");
    }
    if (formData.get("dietary_notes")) {
      payload.dietary_notes = formData.get("dietary_notes");
    }

    return payload;
  }

  rsvpForm.addEventListener("submit", function (event) {
    event.preventDefault();
    if (!restUrl) {
      setStatus(i18n.error || "Could not save response.", true);
      return;
    }

    var submitButton = rsvpForm.querySelector(".pks-oi-rsvp__submit");
    if (submitButton) {
      submitButton.disabled = true;
    }
    setStatus(i18n.submitting || "Saving…", false);

    var formData = new FormData(rsvpForm);
    var payload = buildPayload(formData);

    fetch(restUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": restNonce,
        "X-PKS-OI-Idempotency-Key": payload.idempotency_key,
      },
      credentials: "same-origin",
      body: JSON.stringify(payload),
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return { ok: response.ok, body: body };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          var message =
            (result.body && result.body.message) ||
            i18n.error ||
            "Could not save response.";
          setStatus(message, true);
          return;
        }

        setStatus(
          (result.body && result.body.message) ||
            i18n.saved ||
            "Response saved.",
          false
        );

        if (result.body && result.body.invitation_url && personalLinkEl) {
          personalLinkEl.hidden = false;
          personalLinkEl.textContent =
            "Personal link (save for later): " + result.body.invitation_url;
        }
      })
      .catch(function () {
        setStatus(i18n.error || "Could not save response.", true);
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
        }
      });
  });

  var wishlistRoot = document.querySelector("[data-pks-oi-wishlist]");
  if (wishlistRoot) {
    var wishlistStatus = wishlistRoot.querySelector("[data-pks-oi-wishlist-status]");
    var wishlistBase = wishlistRoot.getAttribute("data-rest-base") || "";
    var wishlistNonce = wishlistRoot.getAttribute("data-rest-nonce") || "";
    var requiresName = wishlistRoot.getAttribute("data-requires-name") === "1";
    var nameInput = wishlistRoot.querySelector("[data-pks-oi-wishlist-name]");

    function setWishlistStatus(message, isError) {
      if (!wishlistStatus) {
        return;
      }
      wishlistStatus.hidden = !message;
      wishlistStatus.textContent = message || "";
      wishlistStatus.classList.toggle("is-error", !!isError);
      wishlistStatus.setAttribute("aria-busy", message && !isError ? "true" : "false");
    }

    function wishlistPayload() {
      var payload = {
        idempotency_key: "wishlist-" + Date.now() + "-" + Math.random().toString(36).slice(2),
      };
      if (requiresName && nameInput && nameInput.value) {
        payload.display_name = nameInput.value;
      }
      return payload;
    }

    wishlistRoot.addEventListener("click", function (event) {
      var target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }

      var item = target.closest("[data-item-id]");
      if (!item || !wishlistBase) {
        return;
      }

      var itemId = item.getAttribute("data-item-id");
      var action = target.hasAttribute("data-pks-oi-wishlist-reserve")
        ? "reserve"
        : target.hasAttribute("data-pks-oi-wishlist-release")
          ? "release"
          : "";

      if (!itemId || !action) {
        return;
      }

      var url = wishlistBase.replace(/\/$/, "") + "/" + itemId + "/" + action;
      fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": wishlistNonce,
          "X-PKS-OI-Idempotency-Key": wishlistPayload().idempotency_key,
        },
        credentials: "same-origin",
        body: JSON.stringify(wishlistPayload()),
      })
        .then(function (response) {
          return response.json().then(function (body) {
            return { ok: response.ok, body: body };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            setWishlistStatus(
              (result.body && result.body.message) ||
                i18n.wishlist_error ||
                "Could not update wishlist.",
              true
            );
            return;
          }
          setWishlistStatus(i18n.wishlist_saved || "Wishlist updated.", false);
        })
        .catch(function () {
          setWishlistStatus(i18n.wishlist_error || "Could not update wishlist.", true);
        });
    });
  }

  var photosRoot = document.querySelector("[data-pks-oi-photos]");
  if (photosRoot) {
    var photosStatus = photosRoot.querySelector("[data-pks-oi-photos-status]");
    var intentUrl = photosRoot.getAttribute("data-intent-url") || "";
    var uploadUrl = photosRoot.getAttribute("data-upload-url") || "";
    var photosNonce = photosRoot.getAttribute("data-rest-nonce") || "";
    var photosRequireName = photosRoot.getAttribute("data-requires-name") === "1";
    var photosNameInput = photosRoot.querySelector("[data-pks-oi-photos-name]");
    var photosInput = photosRoot.querySelector("[data-pks-oi-photos-input]");
    var photosUploadBtn = photosRoot.querySelector("[data-pks-oi-photos-upload]");
    var maxFiles = parseInt(photosRoot.getAttribute("data-max-files") || "10", 10);

    function setPhotosStatus(message, isError) {
      if (!photosStatus) {
        return;
      }
      photosStatus.hidden = !message;
      photosStatus.textContent = message || "";
      photosStatus.classList.toggle("is-error", !!isError);
      photosStatus.setAttribute("aria-busy", message && !isError ? "true" : "false");
    }

    function intentPayload() {
      var payload = {};
      if (photosRequireName && photosNameInput && photosNameInput.value) {
        payload.display_name = photosNameInput.value;
      }
      return payload;
    }

    if (photosUploadBtn) {
      photosUploadBtn.addEventListener("click", function () {
        if (!intentUrl || !uploadUrl || !photosInput || !photosInput.files || !photosInput.files.length) {
          setPhotosStatus(i18n.photos_error || "Could not upload photos.", true);
          return;
        }

        if (photosInput.files.length > maxFiles) {
          setPhotosStatus(i18n.photos_error || "Could not upload photos.", true);
          return;
        }

        photosUploadBtn.disabled = true;
        setPhotosStatus(i18n.photos_uploading || "Uploading…", false);

        fetch(intentUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": photosNonce,
          },
          credentials: "same-origin",
          body: JSON.stringify(intentPayload()),
        })
          .then(function (response) {
            return response.json().then(function (body) {
              return { ok: response.ok, body: body };
            });
          })
          .then(function (intentResult) {
            if (!intentResult.ok || !intentResult.body || !intentResult.body.intent) {
              throw new Error("intent_failed");
            }

            var formData = new FormData();
            for (var i = 0; i < photosInput.files.length; i++) {
              formData.append("photos[]", photosInput.files[i]);
            }

            return fetch(uploadUrl, {
              method: "POST",
              headers: {
                "X-WP-Nonce": photosNonce,
                "X-PKS-OI-Upload-Intent": intentResult.body.intent,
              },
              credentials: "same-origin",
              body: formData,
            }).then(function (response) {
              return response.json().then(function (body) {
                return { ok: response.ok, body: body };
              });
            });
          })
          .then(function (uploadResult) {
            if (!uploadResult.ok) {
              setPhotosStatus(
                (uploadResult.body && uploadResult.body.message) ||
                  i18n.photos_error ||
                  "Could not upload photos.",
                true
              );
              return;
            }
            setPhotosStatus(i18n.photos_uploaded || "Photos uploaded.", false);
            if (photosInput) {
              photosInput.value = "";
            }
          })
          .catch(function () {
            setPhotosStatus(i18n.photos_error || "Could not upload photos.", true);
          })
          .finally(function () {
            if (photosUploadBtn) {
              photosUploadBtn.disabled = false;
            }
          });
      });
    }
  }
})();
