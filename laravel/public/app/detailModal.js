import { renderFeedDetail } from "../render.js";
import { installDetailVideoPlayer } from "../videoPreview.js";

function createContent(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content;
}

function pauseVideoElements(root) {
  for (const video of root?.querySelectorAll?.("video") || []) {
    if (!(video instanceof HTMLVideoElement)) {
      continue;
    }

    video.pause();
  }
}

export function createDetailModalController({ detailModal, detailModalPanel }) {
  let lastActiveElement = null;
  let lastDetailOpenInteraction = null;
  let previousBodyOverflow = "";
  let detailPlayerController = null;

  function clearDetailModalNodes() {
    if (!detailModalPanel) {
      return;
    }

    for (const node of detailModalPanel.querySelectorAll("[data-detail-layout-node='true']")) {
      node.remove();
    }
  }

  function destroyDetailPlayer() {
    detailPlayerController?.destroy?.();
    detailPlayerController = null;
  }

  function setupDetailPlayer() {
    if (!detailModalPanel) {
      return;
    }

    destroyDetailPlayer();
    const video = detailModalPanel.querySelector("[data-detail-player]");
    if (!(video instanceof HTMLVideoElement)) {
      return;
    }

    detailPlayerController = installDetailVideoPlayer(detailModalPanel, video);
  }

  function open(tweet, triggerElement, { interactionType = "pointer" } = {}) {
    if (!tweet || !detailModal || !detailModalPanel) {
      return;
    }

    destroyDetailPlayer();
    pauseVideoElements(detailModalPanel);
    clearDetailModalNodes();
    detailModalPanel.append(createContent(renderFeedDetail(tweet)));
    setupDetailPlayer();

    if (detailModal.hidden) {
      previousBodyOverflow = document.body.style.overflow;
    }

    detailModal.hidden = false;
    detailModal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    lastActiveElement = triggerElement instanceof HTMLElement ? triggerElement : document.activeElement;
    lastDetailOpenInteraction = interactionType === "keyboard" ? "keyboard" : "pointer";
    detailModalPanel.focus({ preventScroll: true });
  }

  function close({ restoreFocus = true } = {}) {
    if (!detailModal || detailModal.hidden) {
      return;
    }

    const shouldRestoreTriggerFocus =
      restoreFocus &&
      lastDetailOpenInteraction === "keyboard" &&
      lastActiveElement instanceof HTMLElement;

    if (!shouldRestoreTriggerFocus) {
      const activeElement = document.activeElement;
      if (activeElement instanceof HTMLElement && detailModal.contains(activeElement)) {
        activeElement.blur();
      }
    }

    destroyDetailPlayer();
    pauseVideoElements(detailModal);
    detailModal.classList.add("hidden");
    detailModal.hidden = true;
    clearDetailModalNodes();
    document.body.style.overflow = previousBodyOverflow;

    if (shouldRestoreTriggerFocus) {
      lastActiveElement.focus({ preventScroll: true });
    }

    lastActiveElement = null;
    lastDetailOpenInteraction = null;
  }

  function bindDismissInteractions() {
    if (detailModal) {
      detailModal.addEventListener("click", (event) => {
        if (event.target !== detailModal) {
          return;
        }

        close();
      });
    }

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && detailModal && !detailModal.hidden) {
        close();
      }
    });
  }

  return {
    open,
    close,
    bindDismissInteractions
  };
}
