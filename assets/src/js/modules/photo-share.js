/**
 * Dedicated photo share landing page.
 */
export function initPhotoShare(i18n) {
  const root = document.querySelector("[data-pks-oi-photo-share]");
  if (!root) {
    return;
  }

  const statusEl = root.querySelector("[data-pks-oi-photo-share-status]");
  const codeForm = root.querySelector("[data-pks-oi-photo-code-form]");
  const uploader = root.querySelector("[data-pks-oi-photo-uploader]");
  const gallery = root.querySelector("[data-pks-oi-photo-gallery]");

  function setStatus(message, isError) {
        if (!statusEl) {
      return;
    }
    statusEl.hidden = !message;
    statusEl.textContent = message || "";
    statusEl.classList.toggle("is-error", !!isError);
    statusEl.classList.toggle("is-success", !!message && !isError);
  }

  if (codeForm) {
    const codeInput = codeForm.querySelector("[data-pks-oi-photo-code]");
    const base = root.getAttribute("data-rest-base") || "";
    const restNonce = root.getAttribute("data-rest-nonce") || "";
    codeForm.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!base || !codeInput) {
        setStatus((i18n && i18n.code_error) || "Error", true);
        return;
      }

      setStatus((i18n && i18n.code_submitting) || "Checking…", false);
      fetch(base + "/verify", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": restNonce,
        },
        credentials: "same-origin",
        body: JSON.stringify({ code: codeInput.value }),
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
                (i18n && i18n.code_error) ||
                "Error",
              true
            );
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          setStatus((i18n && i18n.code_error) || "Error", true);
        });
    });
  }

  if (uploader) {
    initUploader(uploader, i18n, setStatus);
  }

  if (gallery) {
    initGallery(gallery);
  }
}

export function initPhotoWall() {
  const root = document.querySelector("[data-pks-oi-photo-wall]");
  if (!root) {
    return;
  }

  const gallery = root.querySelector("[data-pks-oi-photo-gallery]");
  const emptyEl = root.querySelector("[data-pks-oi-photo-wall-empty]");
  if (gallery) {
    initGallery(gallery, {
      onFirstPageLoaded: function (count) {
        if (emptyEl) {
          emptyEl.hidden = count > 0;
        }
      },
    });
  }
}

function initUploader(uploader, i18n, setStatus) {
  const restBase = uploader.getAttribute("data-rest-base") || "";
  const nonce = uploader.getAttribute("data-rest-nonce") || "";
  const maxFiles = parseInt(uploader.getAttribute("data-max-files") || "10", 10);
  const nameInput = uploader.querySelector("[data-pks-oi-photo-share-name]");
  const fileInput = uploader.querySelector("[data-pks-oi-photo-share-input]");
  const uploadBtn = uploader.querySelector("[data-pks-oi-photo-share-upload]");
  const consent = uploader.querySelector("[data-pks-oi-photo-consent]");
  const previews = uploader.querySelector("[data-pks-oi-photo-previews]");

  if (!uploadBtn || !fileInput) {
    return;
  }

  fileInput.addEventListener("change", function () {
    if (!previews || !fileInput.files) {
      return;
    }
    previews.innerHTML = "";
    previews.hidden = !fileInput.files.length;
    for (let i = 0; i < fileInput.files.length; i++) {
      const item = document.createElement("li");
      item.textContent = fileInput.files[i].name;
      previews.appendChild(item);
    }
  });

  uploadBtn.addEventListener("click", function () {
    if (!restBase || !fileInput.files || !fileInput.files.length) {
      setStatus((i18n && i18n.upload_error) || "Error", true);
      return;
    }
    if (consent && !consent.checked) {
      setStatus((i18n && i18n.upload_error) || "Error", true);
      return;
    }
    if (fileInput.files.length > maxFiles) {
      setStatus((i18n && i18n.upload_error) || "Error", true);
      return;
    }

    uploadBtn.disabled = true;
    setStatus((i18n && i18n.uploading) || "Uploading…", false);

    const payload = {};
    if (nameInput && nameInput.value) {
      payload.display_name = nameInput.value;
    }

    fetch(restBase + "/photos/intent", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": nonce,
      },
      credentials: "same-origin",
      body: JSON.stringify(payload),
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
        for (let i = 0; i < fileInput.files.length; i++) {
          formData.append("photos[]", fileInput.files[i]);
        }

        return fetch(restBase + "/photos/upload", {
          method: "POST",
          headers: {
            "X-WP-Nonce": nonce,
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
          setStatus(
            (uploadResult.body && uploadResult.body.message) ||
              (i18n && i18n.upload_error) ||
              "Error",
            true
          );
          return;
        }
        setStatus(
          (uploadResult.body && uploadResult.body.message) ||
            (i18n && i18n.uploaded) ||
            "Uploaded.",
          false
        );
        fileInput.value = "";
        if (previews) {
          previews.innerHTML = "";
          previews.hidden = true;
        }
      })
      .catch(function () {
        setStatus((i18n && i18n.upload_error) || "Error", true);
      })
      .finally(function () {
        uploadBtn.disabled = false;
      });
  });
}

function initGallery(gallery, options) {
  const scope = gallery.closest("[data-pks-oi-photo-share], [data-pks-oi-photo-wall]") || gallery;
  const restBase = scope.getAttribute("data-rest-base") || gallery.getAttribute("data-rest-base") || "";
  const nonce = scope.getAttribute("data-rest-nonce") || gallery.getAttribute("data-rest-nonce") || "";
  const galleryPath = gallery.getAttribute("data-gallery-path") || "/gallery";
  const moreBtn = gallery.parentElement
    ? gallery.parentElement.querySelector("[data-pks-oi-photo-gallery-more]")
    : null;
  let page = 1;
  let loading = false;
  let hasMore = true;

  function loadPage() {
    if (!restBase || loading || !hasMore) {
      return;
    }
    loading = true;
    fetch(restBase + galleryPath + "?page=" + page + "&per_page=20", {
      headers: { "X-WP-Nonce": nonce },
      credentials: "same-origin",
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (body) {
        if (!body || !body.items) {
          return;
        }
        if (page === 1 && options && typeof options.onFirstPageLoaded === "function") {
          options.onFirstPageLoaded(body.items.length);
        }
        body.items.forEach(function (item) {
          if (!item.stream_url) {
            return;
          }
          const img = document.createElement("img");
          img.src = item.stream_url;
          img.loading = "lazy";
          img.alt = "";
          img.width = item.width || undefined;
          img.height = item.height || undefined;
          gallery.appendChild(img);
        });
        const total = body.total || 0;
        const loaded = page * (body.per_page || 20);
        hasMore = loaded < total;
        page += 1;
        if (moreBtn) {
          moreBtn.hidden = !hasMore;
        }
      })
      .finally(function () {
        loading = false;
      });
  }

  loadPage();
  if (moreBtn) {
    moreBtn.addEventListener("click", loadPage);
  }
}
