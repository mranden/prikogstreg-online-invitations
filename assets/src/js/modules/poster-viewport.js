/**
 * Scales the published poster canvas to fit its frame.
 */
export function initPosterViewport(options = {}) {
  const viewport = document.querySelector(".pks-oi-poster-viewport");
  const canvas = viewport && viewport.querySelector("[data-pks-oi-poster-canvas]");
  if (!viewport || !canvas) {
    return { recalculate: function () {} };
  }

  const designWidth = parseInt(viewport.getAttribute("data-poster-width") || "510", 10);
  const designHeight = parseInt(viewport.getAttribute("data-poster-height") || "680", 10);
  if (!designWidth || !designHeight) {
    return { recalculate: function () {} };
  }

  let lastScale = 0;

  function applyScale() {
    const frame = viewport.querySelector(".pks-oi-poster-viewport__frame");
    if (!frame) {
      return;
    }

    const available = frame.clientWidth;
    if (!available) {
      return;
    }

    const scale = Math.min(1, available / designWidth);
    if (scale === lastScale) {
      return;
    }
    lastScale = scale;

    canvas.style.transform = "scale(" + scale + ")";
    canvas.style.width = designWidth + "px";
    canvas.style.height = designHeight + "px";
    frame.style.height = Math.ceil(designHeight * scale) + "px";
  }

  applyScale();

  const frame = viewport.querySelector(".pks-oi-poster-viewport__frame");
  if (window.ResizeObserver && frame) {
    const observer = new ResizeObserver(applyScale);
    observer.observe(frame);
  } else {
    window.addEventListener("resize", applyScale);
  }

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(applyScale).catch(function () {});
  }

  window.addEventListener("load", applyScale);

  canvas.querySelectorAll("img").forEach(function (img) {
    if (!img.complete) {
      img.addEventListener("load", applyScale, { once: true });
    }
  });

  if (typeof options.onReady === "function") {
    options.onReady(applyScale);
  }

  return { recalculate: applyScale };
}

export function initAllPosterViewports() {
  const recalculators = [];
  document.querySelectorAll(".pks-oi-poster-viewport").forEach(function () {
    recalculators.push(initPosterViewport());
  });

  return function recalculateAll() {
    recalculators.forEach(function (item) {
      if (item && typeof item.recalculate === "function") {
        item.recalculate();
      }
    });
  };
}

export function initPosterPages(i18n) {
  document.querySelectorAll(".pks-oi-poster-viewport").forEach(function (viewport) {
    const pages = Array.prototype.slice.call(
      viewport.querySelectorAll("[data-pks-oi-poster-page]")
    );
    if (pages.length < 2) {
      return;
    }

    let current = 0;
    const prevButton = viewport.querySelector("[data-pks-oi-poster-prev]");
    const nextButton = viewport.querySelector("[data-pks-oi-poster-next]");
    const status = viewport.querySelector("[data-pks-oi-poster-status]");

    function pageLabel(index) {
      const template = (i18n && i18n.poster_page) || "Page %1$d of %2$d";
      return template
        .replace("%1$d", String(index + 1))
        .replace("%2$d", String(pages.length))
        .replace("%s", String(index + 1));
    }

    function showPage(index) {
      if (index < 0 || index >= pages.length) {
        return;
      }

      current = index;
      pages.forEach(function (page, pageIndex) {
        const active = pageIndex === current;
        page.hidden = !active;
        page.setAttribute("aria-hidden", active ? "false" : "true");
      });

      if (status) {
        status.textContent = pageLabel(current);
      }
      if (prevButton) {
        prevButton.disabled = current === 0;
      }
      if (nextButton) {
        nextButton.disabled = current >= pages.length - 1;
      }
    }

    if (prevButton) {
      prevButton.addEventListener("click", function () {
        showPage(current - 1);
      });
    }
    if (nextButton) {
      nextButton.addEventListener("click", function () {
        showPage(current + 1);
      });
    }

    viewport.addEventListener("keydown", function (event) {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        showPage(current - 1);
      }
      if (event.key === "ArrowRight") {
        event.preventDefault();
        showPage(current + 1);
      }
    });

    showPage(0);
  });
}
