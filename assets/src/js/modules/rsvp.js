/**
 * Public RSVP form submission.
 */
export function initRsvp(i18n) {
  const rsvpRoot = document.querySelector("[data-pks-oi-rsvp]");
  const rsvpForm = document.querySelector("[data-pks-oi-rsvp-form]");
  if (!rsvpRoot || !rsvpForm) {
    return;
  }

  const restUrl = rsvpRoot.getAttribute("data-rest-url") || "";
  const restNonce = rsvpRoot.getAttribute("data-rest-nonce") || "";
  const statusEl = rsvpForm.querySelector("[data-pks-oi-rsvp-status]");
  const personalLinkEl = rsvpForm.querySelector("[data-pks-oi-personal-link]");
  const attendeeWrap = rsvpForm.querySelector("[data-pks-oi-attendee-wrap]");

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
    const attending = rsvpForm.querySelector('input[name="attending"]:checked');
    attendeeWrap.hidden = !(attending && attending.value === "yes");
  }

  rsvpForm.querySelectorAll('input[name="attending"]').forEach(function (input) {
    input.addEventListener("change", updateAttendeeVisibility);
  });
  updateAttendeeVisibility();

  function buildPayload(formData) {
    const payload = {
      attending: formData.get("attending"),
      idempotency_key: "rsvp-" + Date.now() + "-" + Math.random().toString(36).slice(2),
    };

    ["display_name", "email", "attendee_count", "rsvp_comment", "dietary_notes"].forEach(function (key) {
      if (formData.get(key)) {
        payload[key] = formData.get(key);
      }
    });

    return payload;
  }

  rsvpForm.addEventListener("submit", function (event) {
    event.preventDefault();
    if (!restUrl) {
      setStatus((i18n && i18n.error) || "Could not save response.", true);
      return;
    }

    const submitButton = rsvpForm.querySelector(".pks-oi-rsvp__submit");
    if (submitButton) {
      submitButton.disabled = true;
    }
    setStatus((i18n && i18n.submitting) || "Saving…", false);

    const formData = new FormData(rsvpForm);
    const payload = buildPayload(formData);

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
          setStatus(
            (result.body && result.body.message) ||
              (i18n && i18n.error) ||
              "Could not save response.",
            true
          );
          return;
        }

        setStatus(
          (result.body && result.body.message) ||
            (i18n && i18n.saved) ||
            "Response saved.",
          false
        );

        if (result.body && result.body.invitation_url && personalLinkEl) {
          personalLinkEl.hidden = false;
          personalLinkEl.textContent =
            ((i18n && i18n.personal_link) || "Personal link (save for later):") +
            " " +
            result.body.invitation_url;
        }
      })
      .catch(function () {
        setStatus((i18n && i18n.error) || "Could not save response.", true);
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
        }
      });
  });
}
