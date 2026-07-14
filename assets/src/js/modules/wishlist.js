/**
 * Public wishlist reserve/release interactions.
 */
export function initWishlist(i18n) {
  const wishlistRoot = document.querySelector("[data-pks-oi-wishlist]");
  if (!wishlistRoot) {
    return;
  }

  const wishlistStatus = wishlistRoot.querySelector("[data-pks-oi-wishlist-status]");
  const wishlistBase = wishlistRoot.getAttribute("data-rest-base") || "";
  const wishlistNonce = wishlistRoot.getAttribute("data-rest-nonce") || "";
  const requiresName = wishlistRoot.getAttribute("data-requires-name") === "1";
  const nameInput = wishlistRoot.querySelector("[data-pks-oi-wishlist-name]");

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
    const payload = {
      idempotency_key: "wishlist-" + Date.now() + "-" + Math.random().toString(36).slice(2),
    };
    if (requiresName && nameInput && nameInput.value) {
      payload.display_name = nameInput.value;
    }
    return payload;
  }

  wishlistRoot.addEventListener("click", function (event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const item = target.closest("[data-item-id]");
    if (!item || !wishlistBase) {
      return;
    }

    const itemId = item.getAttribute("data-item-id");
    const action = target.hasAttribute("data-pks-oi-wishlist-reserve")
      ? "reserve"
      : target.hasAttribute("data-pks-oi-wishlist-release")
        ? "release"
        : "";

    if (!itemId || !action) {
      return;
    }

    const url = wishlistBase.replace(/\/$/, "") + "/" + itemId + "/" + action;
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
              (i18n && i18n.wishlist_error) ||
              "Could not update wishlist.",
            true
          );
          return;
        }
        setWishlistStatus((i18n && i18n.wishlist_saved) || "Wishlist updated.", false);
      })
      .catch(function () {
        setWishlistStatus((i18n && i18n.wishlist_error) || "Could not update wishlist.", true);
      });
  });
}
