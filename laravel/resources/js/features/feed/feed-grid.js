import { renderFeedEmptyState, renderFeedItem } from "./render/index.js";
import { installHoverVideoPreview } from "./video-preview.js";

const FEED_ITEM_INTERACTIVE_SELECTOR = [
  ".plyr__controls",
  ".plyr__control",
  ".plyr__menu",
  ".plyr__progress",
  ".plyr__volume",
  "button",
  "a[href]",
  "input",
  "select",
  "textarea",
  "[role='slider']",
  "[role='menuitem']"
].join(", ");
const scheduleTask =
  typeof requestAnimationFrame === "function" ? requestAnimationFrame : (callback) => setTimeout(callback, 0);

function createFragment(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content.firstElementChild;
}

export function createFeedGridController({ grid, emptyStateTemplate }) {
  const videoPreviewControllers = new Map();
  const preloadCandidates = new Set();
  let colcade = null;
  let currentPreloadVideo = null;
  let preloadEvaluationScheduled = false;

  const videoObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        const video = entry.target;
        if (!(video instanceof HTMLVideoElement)) {
          continue;
        }

        const controller = videoPreviewControllers.get(video);
        if (!controller) {
          continue;
        }

        if (!entry.isIntersecting) {
          controller.handleVisibilityChange(false);
        }
      }
    },
    {
      threshold: 0.55
    }
  );
  const preloadObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        const video = entry.target;
        if (!(video instanceof HTMLVideoElement)) {
          continue;
        }

        if (entry.isIntersecting) {
          preloadCandidates.add(video);
        } else {
          preloadCandidates.delete(video);
          if (currentPreloadVideo === video) {
            videoPreviewControllers.get(video)?.deactivate?.();
            currentPreloadVideo = null;
          }
        }
      }

      schedulePreloadEvaluation();
    },
    {
      rootMargin: "320px 0px",
      threshold: 0.01
    }
  );

  function schedulePreloadEvaluation() {
    if (preloadEvaluationScheduled) {
      return;
    }

    preloadEvaluationScheduled = true;
    scheduleTask(() => {
      preloadEvaluationScheduled = false;
      evaluatePreloadCandidate();
    });
  }

  function evaluatePreloadCandidate() {
    const viewportCenter = window.innerHeight / 2;
    let nextVideo = null;
    let nextDistance = Number.POSITIVE_INFINITY;

    for (const video of preloadCandidates) {
      if (!(video instanceof HTMLVideoElement) || !video.isConnected) {
        preloadCandidates.delete(video);
        continue;
      }

      const rect = video.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) {
        continue;
      }

      const distance = Math.abs(rect.top + rect.height / 2 - viewportCenter);
      if (distance < nextDistance) {
        nextDistance = distance;
        nextVideo = video;
      }
    }

    if (!(nextVideo instanceof HTMLVideoElement)) {
      if (currentPreloadVideo instanceof HTMLVideoElement) {
        videoPreviewControllers.get(currentPreloadVideo)?.deactivate?.();
      }
      currentPreloadVideo = null;
      return;
    }

    if (currentPreloadVideo === nextVideo) {
      return;
    }

    currentPreloadVideo = nextVideo;
    videoPreviewControllers.get(nextVideo)?.preload?.();
  }

  function getFeedItems() {
    return Array.from(grid?.querySelectorAll(".feed-grid-item") || []);
  }

  function getFeedColumns() {
    return Array.from(grid?.querySelectorAll(".feed-grid-col") || []);
  }

  function getVisibleFeedColumns() {
    return getFeedColumns().filter(
      (column) => column instanceof HTMLElement && window.getComputedStyle(column).display !== "none"
    );
  }

  function createEmptyStateNode() {
    if (emptyStateTemplate?.content?.firstElementChild) {
      return emptyStateTemplate.content.firstElementChild.cloneNode(true);
    }

    return createFragment(renderFeedEmptyState());
  }

  function seedInitialFeedLayout() {
    if (!grid || grid.dataset.empty === "true") {
      return;
    }

    const items = getFeedItems();
    const allColumns = getFeedColumns();
    const visibleColumns = getVisibleFeedColumns();
    if (items.length < 2 || visibleColumns.length < 2 || !allColumns.length) {
      return;
    }

    for (const column of allColumns) {
      column.replaceChildren();
    }

    items.forEach((item, index) => {
      visibleColumns[index % visibleColumns.length]?.append(item);
    });
  }

  function ensureColcade() {
    if (!grid || colcade || typeof window.Colcade !== "function") {
      return colcade;
    }

    colcade = new window.Colcade(grid, {
      columns: ".feed-grid-col",
      items: ".feed-grid-item"
    });

    return colcade;
  }

  function destroyColcade() {
    if (!colcade) {
      return;
    }

    colcade.destroy();
    colcade = null;
  }

  function syncLayout() {
    ensureColcade()?.layout();
  }

  function appendFeedNodes(nodes) {
    if (!nodes.length || !grid) {
      return;
    }

    const instance = ensureColcade();
    if (instance) {
      instance.append(nodes);
      return;
    }

    grid.querySelector(".feed-grid-col")?.append(...nodes);
  }

  function registerFeedNode(node) {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    const video = node.querySelector("video");
    if (!(video instanceof HTMLVideoElement) || videoPreviewControllers.has(video)) {
      return;
    }

    videoPreviewControllers.set(video, installHoverVideoPreview(node, video));
    videoObserver.observe(video);
    if ((video.dataset.hlsUrl || "").trim() !== "") {
      preloadObserver.observe(video);
      schedulePreloadEvaluation();
    }
  }

  function registerFeedNodes(nodes) {
    for (const node of nodes) {
      registerFeedNode(node);
    }
  }

  function shouldIgnoreFeedDetailTrigger(target, feedItem) {
    if (!(target instanceof Element) || !(feedItem instanceof HTMLElement)) {
      return false;
    }

    const interactiveNode = target.closest(FEED_ITEM_INTERACTIVE_SELECTOR);
    return !!interactiveNode && interactiveNode !== feedItem && feedItem.contains(interactiveNode);
  }

  function markEmpty(isEmpty) {
    if (!grid) {
      return;
    }

    grid.dataset.empty = isEmpty ? "true" : "false";
  }

  function stopAllFeedPreviews() {
    for (const controller of videoPreviewControllers.values()) {
      controller.handleVisibilityChange(false);
    }

    currentPreloadVideo = null;
  }

  function renderEmptyState() {
    if (!grid || getFeedItems().length > 0) {
      return false;
    }

    markEmpty(true);
    syncLayout();
    const emptyStateNode = createEmptyStateNode();
    if (emptyStateNode) {
      appendFeedNodes([emptyStateNode]);
    }

    return true;
  }

  function clearFeed({ onBeforeClear } = {}) {
    if (!grid) {
      return;
    }

    onBeforeClear?.();

    for (const video of grid.querySelectorAll("video")) {
      videoObserver.unobserve(video);
      preloadObserver.unobserve(video);
      preloadCandidates.delete(video);
      if (currentPreloadVideo === video) {
        currentPreloadVideo = null;
      }
      videoPreviewControllers.get(video)?.destroy();
      videoPreviewControllers.delete(video);
    }

    const items = getFeedItems();
    destroyColcade();
    for (const item of items) {
      item.remove();
    }
    markEmpty(false);
    ensureColcade();
  }

  function appendFeedItems(items) {
    const nodes = items.map((tweet) => createFragment(renderFeedItem(tweet))).filter(Boolean);
    appendFeedNodes(nodes);
    registerFeedNodes(nodes);
  }

  function hydrateExistingFeedItems() {
    registerFeedNodes(getFeedItems());
  }

  if (grid) {
    grid.addEventListener("pointerover", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const video = target.closest(".feed-grid-item")?.querySelector("video");
      if (!(video instanceof HTMLVideoElement) || (video.dataset.hlsUrl || "").trim() === "") {
        return;
      }

      preloadCandidates.add(video);
      currentPreloadVideo = video;
      videoPreviewControllers.get(video)?.preload?.();
    });

    grid.addEventListener("pointerout", () => {
      schedulePreloadEvaluation();
    });

    grid.addEventListener("focusin", (event) => {
      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const video = target.closest(".feed-grid-item")?.querySelector("video");
      if (!(video instanceof HTMLVideoElement) || (video.dataset.hlsUrl || "").trim() === "") {
        return;
      }

      preloadCandidates.add(video);
      currentPreloadVideo = video;
      videoPreviewControllers.get(video)?.preload?.();
    });

    grid.addEventListener("focusout", () => {
      schedulePreloadEvaluation();
    });

    window.addEventListener("scroll", schedulePreloadEvaluation, { passive: true });
    window.addEventListener("resize", schedulePreloadEvaluation);
  }

  function bindDetailTriggers(onOpen) {
    if (!grid) {
      return;
    }

    grid.addEventListener("click", (event) => {
      if (event.defaultPrevented) {
        return;
      }

      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const feedItem = target.closest("[data-feed-detail-trigger='true']");
      if (!(feedItem instanceof HTMLElement) || !grid.contains(feedItem)) {
        return;
      }

      if (shouldIgnoreFeedDetailTrigger(target, feedItem)) {
        return;
      }

      onOpen?.(feedItem.dataset.tweetId, feedItem, { interactionType: "pointer" });
    });

    grid.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") {
        return;
      }

      const target = event.target;
      if (!(target instanceof Element)) {
        return;
      }

      const feedItem = target.closest("[data-feed-detail-trigger='true']");
      if (!(feedItem instanceof HTMLElement) || !grid.contains(feedItem)) {
        return;
      }

      if (shouldIgnoreFeedDetailTrigger(target, feedItem)) {
        return;
      }

      event.preventDefault();
      onOpen?.(feedItem.dataset.tweetId, feedItem, { interactionType: "keyboard" });
    });
  }

  return {
    appendFeedItems,
    bindDetailTriggers,
    clearFeed,
    ensureColcade,
    getFeedItems,
    hydrateExistingFeedItems,
    markEmpty,
    renderEmptyState,
    seedInitialFeedLayout,
    stopAllFeedPreviews,
    syncLayout
  };
}
