/**
 * My Account scripts (compiled to assets/build/js/account.js).
 */
(function () {
  "use strict";

  document.documentElement.classList.add("pks-oi-js");

  var editor = document.getElementById("pks-oi-editor");
  if (!editor) {
    return;
  }

  var restUrl = editor.getAttribute("data-pks-oi-rest-url") || "";
  var nonce = editor.getAttribute("data-pks-oi-rest-nonce") || "";
  var stateVersion = parseInt(
    editor.getAttribute("data-pks-oi-state-version") || "0",
    10
  );
  var statusEl = document.getElementById("pks-oi-save-status");
  var i18n = (window.pksOiAccount && window.pksOiAccount.i18n) || {};

  function setStatus(message, isError) {
    if (!statusEl) {
      return;
    }
    statusEl.hidden = !message;
    statusEl.textContent = message || "";
    statusEl.classList.toggle("is-error", !!isError);
    statusEl.setAttribute("aria-busy", message && !isError && message.indexOf("…") > -1 ? "true" : "false");
  }

  function saveState(state) {
    if (!restUrl) {
      setStatus(i18n.save_unavailable || "Save endpoint unavailable.", true);
      return;
    }

    setStatus(i18n.saving || "Saving…", false);

    fetch(restUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": nonce,
      },
      credentials: "same-origin",
      body: JSON.stringify({
        expected_state_version: stateVersion,
        state: state,
      }),
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return { ok: response.ok, status: response.status, body: body };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          var err = (result.body && result.body.error) || "save_failed";
          if (result.status === 409) {
            setStatus(
              i18n.save_conflict ||
                "Your design was changed elsewhere. Reload the page and try again.",
              true
            );
            return;
          }
          setStatus(i18n.save_failed || "Save failed.", true);
          return;
        }

        if (result.body && typeof result.body.state_version === "number") {
          stateVersion = result.body.state_version;
          editor.setAttribute("data-pks-oi-state-version", String(stateVersion));
        }

        setStatus(i18n.saved || "Saved.", false);
      })
      .catch(function () {
        setStatus(
          i18n.save_failed ||
            "Save failed. Check your connection and try again.",
          true
        );
      });
  }

  document.addEventListener("bpp:save-requested", function (event) {
    var detail = event && event.detail ? event.detail : {};
    if (!detail.state || typeof detail.state !== "object") {
      setStatus(i18n.invalid_payload || "Invalid save payload from editor.", true);
      return;
    }
    saveState(detail.state);
  });
})();
