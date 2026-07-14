/**
 * Photo share tools for My Account.
 */
export function initPhotoShareTools(i18n) {
  const root = document.querySelector("[data-pks-oi-photo-share-tools]");
  if (!root) {
    return;
  }

  const shareUrl = root.getAttribute("data-share-url") || "";
  const wallUrl = root.getAttribute("data-wall-url") || "";
  const statusEl = root.querySelector("[data-pks-oi-share-tools-status]");
  const copyBtn = root.querySelector("[data-pks-oi-copy-share-url]");
  const copyWallBtn = root.querySelector("[data-pks-oi-copy-wall-url]");
  const nativeBtn = root.querySelector("[data-pks-oi-native-share]");

  function setStatus(message) {
    if (!statusEl) {
      return;
    }
    statusEl.hidden = !message;
    statusEl.textContent = message || "";
  }

  function copyText(text) {
    if (!text) {
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        setStatus((i18n && i18n.copied) || "Link copied.");
      }).catch(function () {
        setStatus("");
      });
      return;
    }
    const input = root.querySelector("[data-pks-oi-share-url-input]");
    if (input && input.select) {
      input.value = text;
      input.select();
      try {
        document.execCommand("copy");
        setStatus((i18n && i18n.copied) || "Link copied.");
      } catch (e) {
        setStatus("");
      }
    }
  }

  if (nativeBtn && navigator.share) {
    nativeBtn.hidden = false;
    nativeBtn.addEventListener("click", function () {
      navigator.share({
        title: document.title,
        url: shareUrl,
      }).catch(function () {});
    });
  }

  if (copyBtn) {
    copyBtn.addEventListener("click", function () {
      copyText(shareUrl);
    });
  }

  if (copyWallBtn) {
    copyWallBtn.addEventListener("click", function () {
      copyText(wallUrl);
    });
  }
}
