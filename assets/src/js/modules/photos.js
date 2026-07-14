/**
 * Public guest photo upload flow.
 */
export function initPhotos(i18n) {
  const photosRoot = document.querySelector("[data-pks-oi-photos]");
  if (!photosRoot) {
    return;
  }

  const photosStatus = photosRoot.querySelector("[data-pks-oi-photos-status]");
  const intentUrl = photosRoot.getAttribute("data-intent-url") || "";
  const uploadUrl = photosRoot.getAttribute("data-upload-url") || "";
  const photosNonce = photosRoot.getAttribute("data-rest-nonce") || "";
  const photosRequireName = photosRoot.getAttribute("data-requires-name") === "1";
  const photosNameInput = photosRoot.querySelector("[data-pks-oi-photos-name]");
  const photosInput = photosRoot.querySelector("[data-pks-oi-photos-input]");
  const photosUploadBtn = photosRoot.querySelector("[data-pks-oi-photos-upload]");
  const maxFiles = parseInt(photosRoot.getAttribute("data-max-files") || "10", 10);

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
    const payload = {};
    if (photosRequireName && photosNameInput && photosNameInput.value) {
      payload.display_name = photosNameInput.value;
    }
    return payload;
  }

  if (!photosUploadBtn) {
    return;
  }

  photosUploadBtn.addEventListener("click", function () {
    if (!intentUrl || !uploadUrl || !photosInput || !photosInput.files || !photosInput.files.length) {
      setPhotosStatus((i18n && i18n.photos_error) || "Could not upload photos.", true);
      return;
    }

    if (photosInput.files.length > maxFiles) {
      setPhotosStatus((i18n && i18n.photos_error) || "Could not upload photos.", true);
      return;
    }

    photosUploadBtn.disabled = true;
    setPhotosStatus((i18n && i18n.photos_uploading) || "Uploading…", false);

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

        const formData = new FormData();
        for (let i = 0; i < photosInput.files.length; i++) {
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
              (i18n && i18n.photos_error) ||
              "Could not upload photos.",
            true
          );
          return;
        }
        setPhotosStatus((i18n && i18n.photos_uploaded) || "Photos uploaded.", false);
        photosInput.value = "";
      })
      .catch(function () {
        setPhotosStatus((i18n && i18n.photos_error) || "Could not upload photos.", true);
      })
      .finally(function () {
        photosUploadBtn.disabled = false;
      });
  });
}
