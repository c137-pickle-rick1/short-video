import { renderFeedDetail, renderFeedItem } from "./render.js";
import { installDetailVideoPlayer, installHoverVideoPreview } from "./videoPreview.js";

const state = {
  cursor: null,
  isLoading: false,
  done: false,
  source: "",
  renderedCount: 0,
  loadToken: 0
};

const grid = document.querySelector("#feed-grid");
const sourceFilter = document.querySelector("#source-filter");
const feedStatus = document.querySelector("#feed-status");
const feedSummary = document.querySelector("#feed-summary");
const sentinel = document.querySelector("#feed-sentinel");
const emptyStateTemplate = document.querySelector("#empty-state-template");
const detailModal = document.querySelector("#feed-detail-modal");
const detailModalPanel = document.querySelector("#feed-detail-modal-panel");

const feedItemsById = new Map();
const videoPreviewControllers = new Map();
let colcade = null;
let lastActiveElement = null;
let lastDetailOpenInteraction = null;
let previousBodyOverflow = "";
let detailPlayerController = null;
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

function getSourceLabel(handle) {
  return handle ? `@${handle}` : "全部来源";
}

function setStatus(label) {
  if (feedStatus) {
    feedStatus.textContent = label;
  }
}

function createFragment(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content.firstElementChild;
}

function createContent(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content;
}

function getFeedItems() {
  return Array.from(grid?.querySelectorAll(".feed-grid-item") || []);
}

function createEmptyStateNode() {
  return emptyStateTemplate?.content?.firstElementChild?.cloneNode(true) || null;
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

function syncColcadeLayout() {
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

function stopAllFeedPreviews() {
  for (const controller of videoPreviewControllers.values()) {
    controller.handleVisibilityChange(false);
  }
}

function pauseVideoElements(root) {
  for (const video of root?.querySelectorAll?.("video") || []) {
    if (!(video instanceof HTMLVideoElement)) {
      continue;
    }

    video.pause();
  }
}

function clearDetailModalNodes() {
  if (!detailModalPanel) {
    return;
  }

  for (const node of detailModalPanel.querySelectorAll("[data-detail-layout-node='true']")) {
    node.remove();
  }
}

function shouldIgnoreFeedDetailTrigger(target, feedItem) {
  if (!(target instanceof Element) || !(feedItem instanceof HTMLElement)) {
    return false;
  }

  const interactiveNode = target.closest(FEED_ITEM_INTERACTIVE_SELECTOR);
  return !!interactiveNode && interactiveNode !== feedItem && feedItem.contains(interactiveNode);
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

function openDetailModal(tweetId, triggerElement, { interactionType = "pointer" } = {}) {
  const normalizedTweetId = String(tweetId || "");
  const tweet = feedItemsById.get(normalizedTweetId);
  if (!tweet || !detailModal || !detailModalPanel) {
    return;
  }

  stopAllFeedPreviews();
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

function closeDetailModal({ restoreFocus = true } = {}) {
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

function updateFeedSummary() {
  if (!feedSummary) {
    return;
  }

  const sourceLabel = getSourceLabel(state.source);

  if (state.renderedCount === 0 && state.done) {
    feedSummary.textContent = `${sourceLabel} 暂无内容`;
    return;
  }

  if (state.renderedCount === 0) {
    feedSummary.textContent = `${sourceLabel} 正在加载探索内容…`;
    return;
  }

  const tail = state.done ? "已加载完毕" : "向下滚动继续加载";
  feedSummary.textContent = `${sourceLabel} · 已展示 ${state.renderedCount} 条 · ${tail}`;
}

function renderEmptyState() {
  if (!grid || getFeedItems().length > 0) {
    return;
  }

  grid.dataset.empty = "true";
  syncColcadeLayout();
  const emptyStateNode = createEmptyStateNode();
  if (emptyStateNode) {
    appendFeedNodes([emptyStateNode]);
  }
  updateFeedSummary();
}

function clearFeed() {
  if (!grid) {
    return;
  }

  closeDetailModal({ restoreFocus: false });
  feedItemsById.clear();

  for (const video of grid.querySelectorAll("video")) {
    videoObserver.unobserve(video);
    videoPreviewControllers.get(video)?.destroy();
    videoPreviewControllers.delete(video);
  }

  const items = getFeedItems();
  destroyColcade();
  for (const item of items) {
    item.remove();
  }
  grid.dataset.empty = "false";
  ensureColcade();
}

async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }

  return response.json();
}

async function loadSources() {
  const payload = await fetchJson("/api/sources");
  const enabledSources = payload.items
    .filter((source) => source.enabled)
    .sort(
      (left, right) => right.publishedCount - left.publishedCount || left.handle.localeCompare(right.handle)
    );
  if (!sourceFilter) {
    return;
  }

  sourceFilter.replaceChildren(createFragment('<option value="">全部来源</option>'));

  for (const source of enabledSources) {
    const option = document.createElement("option");
    option.value = source.handle;
    option.textContent = `@${source.handle} · ${source.publishedCount}`;
    sourceFilter.append(option);
  }

  if (!enabledSources.length) {
    setStatus("等待配置来源");
  }
}

function resetFeed() {
  state.cursor = null;
  state.done = false;
  state.renderedCount = 0;
  state.loadToken += 1;
  state.isLoading = false;
  clearFeed();
  updateFeedSummary();
}

async function applySource(nextSource) {
  state.source = nextSource;
  if (sourceFilter) {
    sourceFilter.value = nextSource;
  }
  resetFeed();
  await loadFeed();
}

async function loadFeed() {
  if (state.isLoading || state.done) {
    return;
  }

  const loadToken = state.loadToken;
  const sourceAtRequest = state.source;
  state.isLoading = true;
  setStatus("加载内容中");
  updateFeedSummary();

  try {
    const params = new URLSearchParams();
    params.set("limit", "8");
    if (state.cursor) {
      params.set("cursor", state.cursor);
    }
    if (state.source) {
      params.set("source", state.source);
    }

    const payload = await fetchJson(`/api/feed?${params.toString()}`);

    if (loadToken !== state.loadToken || sourceAtRequest !== state.source) {
      return;
    }

    if (!payload.items.length && !state.cursor) {
      state.done = true;
      renderEmptyState();
      setStatus("当前没有内容");
      return;
    }

    grid.dataset.empty = "false";
    syncColcadeLayout();

    for (const tweet of payload.items) {
      feedItemsById.set(String(tweet.tweetId), tweet);
    }

    const nodes = payload.items.map((tweet) => createFragment(renderFeedItem(tweet)));
    appendFeedNodes(nodes);

    for (const node of nodes) {
      const video = node.querySelector("video");
      if (video) {
        videoPreviewControllers.set(video, installHoverVideoPreview(node, video));
        videoObserver.observe(video);
      }
    }

    state.renderedCount += payload.items.length;
    state.cursor = payload.nextCursor;
    state.done = !payload.nextCursor;
    updateFeedSummary();
    setStatus(state.done ? "已经到底了" : "继续下滑加载");
  } catch (error) {
    setStatus("内容加载失败");
    if (feedSummary) {
      feedSummary.textContent = "加载失败，请稍后重试";
    }
    console.error(error);
  } finally {
    if (loadToken === state.loadToken) {
      state.isLoading = false;
    }
  }
}

const feedObserver = new IntersectionObserver(
  (entries) => {
    const [entry] = entries;
    if (entry?.isIntersecting) {
      loadFeed();
    }
  },
  {
    rootMargin: "400px"
  }
);

if (sourceFilter) {
  sourceFilter.addEventListener("change", async (event) => {
    await applySource(event.target.value);
  });
}

if (grid) {
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

    openDetailModal(feedItem.dataset.tweetId, feedItem, { interactionType: "pointer" });
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
    openDetailModal(feedItem.dataset.tweetId, feedItem, { interactionType: "keyboard" });
  });
}

if (detailModal) {
  detailModal.addEventListener("click", (event) => {
    if (event.target !== detailModal) {
      return;
    }

    closeDetailModal();
  });
}

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && detailModal && !detailModal.hidden) {
    closeDetailModal();
  }
});

async function bootstrap() {
  ensureColcade();
  await loadSources();
  resetFeed();
  await loadFeed();
  if (sentinel) {
    feedObserver.observe(sentinel);
  }
}

bootstrap().catch((error) => {
  setStatus("启动失败");
  console.error(error);
});
