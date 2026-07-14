/**
 * Envelope open state machine: closed → opening → revealed → settled.
 */
export function initEnvelopeController(options) {
  const root = document.querySelector(".pks-oi-envelope");
  const openButton = document.getElementById("pks-oi-open-invitation");
  const content = document.getElementById("pks-oi-invitation-content");
  if (!root || !openButton || !content) {
    return;
  }

  const recalculatePosters =
    options && typeof options.recalculatePosters === "function"
      ? options.recalculatePosters
      : function () {};

  const sessionKey = root.getAttribute("data-pks-oi-session-key") || "";
  const prefersReducedMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const states = ["closed", "opening", "revealed", "settled"];
  let busy = false;

  function setState(next) {
    if (!states.includes(next)) {
      return;
    }
    root.setAttribute("data-envelope-state", next);
  }

  function persistOpened() {
    if (!sessionKey) {
      return;
    }
    try {
      sessionStorage.setItem(sessionKey, "1");
    } catch (error) {
      /* ignore storage failures */
    }
  }

  function wasOpenedBefore() {
    if (!sessionKey) {
      return false;
    }
    try {
      return sessionStorage.getItem(sessionKey) === "1";
    } catch (error) {
      return false;
    }
  }

  function finishReveal(skipAnimation) {
    content.hidden = false;
    content.removeAttribute("inert");
    openButton.setAttribute("aria-expanded", "true");
    openButton.hidden = true;

    setState(skipAnimation ? "settled" : "revealed");
    recalculatePosters();

    window.setTimeout(
      function () {
        setState("settled");
        recalculatePosters();

        const heading = content.querySelector("#pks-oi-invitation-heading, .pks-oi-envelope__revealed-poster h2");
        if (heading && heading.focus) {
          heading.focus({ preventScroll: true });
        } else if (content.focus) {
          content.focus({ preventScroll: true });
        }

        const revealedPoster = content.querySelector(".pks-oi-poster-viewport");
        if (revealedPoster && revealedPoster.scrollIntoView && window.innerWidth < 768) {
          revealedPoster.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      },
      skipAnimation ? 50 : 900
    );

    persistOpened();
  }

  function openInvitation() {
    if (busy || root.getAttribute("data-envelope-state") !== "closed") {
      return;
    }

    busy = true;
    openButton.setAttribute("aria-busy", "true");
    openButton.disabled = true;
    setState("opening");

    window.setTimeout(
      function () {
        finishReveal(false);
        busy = false;
        openButton.removeAttribute("aria-busy");
      },
      prefersReducedMotion ? 0 : 750
    );
  }

  openButton.setAttribute("aria-controls", "pks-oi-invitation-content");
  openButton.setAttribute("aria-expanded", "false");

  if (prefersReducedMotion || wasOpenedBefore()) {
    setState("opening");
    finishReveal(true);
  } else {
    openButton.addEventListener("click", openInvitation);
    openButton.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openInvitation();
      }
    });
  }
}
