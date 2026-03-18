import { formatFeedSummary } from "../../features/feed/render/index.js";
import { createDetailModalController } from "../../features/feed/detail-modal.js";
import { createFeedGridController } from "../../features/feed/feed-grid.js";
import { requestJson } from "../../shared/http.js";

const state = {
  cursor: null,
  isLoading: false,
  done: false,
  pageSize: 8,
  query: "",
  source: "",
  mode: "featured",
  renderedCount: 0,
  loadToken: 0
};
const DETAIL_HISTORY_FLAG = "__shortvideoMobileDetail";
const VIEW_RECORDED_EVENT = "shortvideo:video-view-recorded";
const VIEWER_SESSION_STORAGE_KEY = "shortvideo_viewer_session_id";
const READ_VISIBILITY_THRESHOLD = 0.6;
const READ_DWELL_MS = 600;

const grid = document.querySelector("#feed-grid");
const sourceFilter = document.querySelector("#source-filter");
const feedStatus = document.querySelector("#feed-status");
const feedSummary = document.querySelector("#feed-summary");
const sentinel = document.querySelector("#feed-sentinel");
const feedLoadingIndicator = document.querySelector("#feed-loading-indicator");
const emptyStateTemplate = document.querySelector("#empty-state-template");
const detailModal = document.querySelector("#feed-detail-modal");
const detailModalPanel = document.querySelector("#feed-detail-modal-panel");
const subscriptionsFollowList = document.querySelector("[data-subscriptions-follow-list='true']");

const feedItemsById = new Map();
const orderedTweetIds = [];
let mobileDetailHistoryActive = false;
let closingDetailFromPopstate = false;
const readTrackingState = {
  observedTweetIds: new Set(),
  pendingVideoIds: new Set(),
  visibilityTimers: new Map()
};

const gridController = createFeedGridController({
  grid,
  emptyStateTemplate
});
const detailModalController = createDetailModalController({
  detailModal,
  detailModalPanel,
  getOrderedTweets: () => orderedTweetIds.map((tweetId) => feedItemsById.get(tweetId)).filter(Boolean),
  onNeedMore: () => loadFeed(),
  onOpen: ({ mode }) => {
    if (mode !== "mobile" || mobileDetailHistoryActive) {
      return;
    }

    window.history.pushState(
      {
        ...(window.history.state || {}),
        [DETAIL_HISTORY_FLAG]: true
      },
      ""
    );
    mobileDetailHistoryActive = true;
  },
  onClose: ({ mode }) => {
    if (mode !== "mobile") {
      return;
    }

    if (closingDetailFromPopstate) {
      closingDetailFromPopstate = false;
      mobileDetailHistoryActive = false;
      return;
    }

    if (!mobileDetailHistoryActive) {
      return;
    }

    mobileDetailHistoryActive = false;
    window.history.back();
  }
});

function getViewerSessionId() {
  const storageKey = VIEWER_SESSION_STORAGE_KEY;

  try {
    const existingValue = window.localStorage.getItem(storageKey);
    if (existingValue && existingValue.length >= 8) {
      return existingValue;
    }
  } catch (error) {
    console.warn("Unable to access localStorage", error);
  }

  const nextValue =
    typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

  try {
    window.localStorage.setItem(storageKey, nextValue);
  } catch (error) {
    console.warn("Unable to persist viewer session id", error);
  }

  return nextValue;
}

function clearFeedReadVisibilityTimer(tweetId) {
  const timerId = readTrackingState.visibilityTimers.get(tweetId);
  if (typeof timerId !== "number") {
    return;
  }

  window.clearTimeout(timerId);
  readTrackingState.visibilityTimers.delete(tweetId);
}

function markFeedVideoAsRead(videoId) {
  const normalizedVideoId = String(videoId || "");
  if (!normalizedVideoId) {
    return;
  }

  readTrackingState.pendingVideoIds.delete(normalizedVideoId);

  for (const [tweetId, tweet] of feedItemsById.entries()) {
    if (String(tweet?.videoId || "") !== normalizedVideoId) {
      continue;
    }

    tweet.viewedByViewer = true;
    tweet.isNewForViewer = false;
    clearFeedReadVisibilityTimer(tweetId);
  }

  for (const node of grid?.querySelectorAll(".feed-grid-item[data-tweet-id]") || []) {
    if (!(node instanceof HTMLElement)) {
      continue;
    }

    const tweet = feedItemsById.get(String(node.dataset.tweetId || ""));
    if (!tweet || String(tweet.videoId || "") !== normalizedVideoId) {
      continue;
    }

    node.dataset.videoId = normalizedVideoId;
    node.dataset.viewedByViewer = "true";
    node.dataset.isNewForViewer = "false";
    feedReadObserver.unobserve(node);
  }
}

async function recordFeedCardView(card) {
  if (!(card instanceof HTMLElement)) {
    return;
  }

  const tweetId = String(card.dataset.tweetId || "");
  const tweet = feedItemsById.get(tweetId);
  const videoId = String(tweet?.videoId || "");
  if (!tweetId || !tweet || !videoId || tweet.isNewForViewer !== true || readTrackingState.pendingVideoIds.has(videoId)) {
    return;
  }

  readTrackingState.pendingVideoIds.add(videoId);

  try {
    await requestJson(`/api/videos/${videoId}/views`, {
      method: "POST",
      keepalive: true,
      body: {
        sessionId: getViewerSessionId()
      }
    });

    markFeedVideoAsRead(videoId);
    window.dispatchEvent(
      new CustomEvent(VIEW_RECORDED_EVENT, {
        detail: {
          source: "feed-grid",
          videoId,
          tweetId
        }
      })
    );
  } catch (error) {
    readTrackingState.pendingVideoIds.delete(videoId);
    console.error(error);
  }
}

const feedReadObserver = new IntersectionObserver(
  (entries) => {
    for (const entry of entries) {
      if (!(entry.target instanceof HTMLElement)) {
        continue;
      }

      const card = entry.target;
      const tweetId = String(card.dataset.tweetId || "");
      const tweet = feedItemsById.get(tweetId);
      if (!tweetId || !tweet || tweet.isNewForViewer !== true || !tweet.videoId) {
        clearFeedReadVisibilityTimer(tweetId);
        feedReadObserver.unobserve(card);
        continue;
      }

      if (!entry.isIntersecting || entry.intersectionRatio < READ_VISIBILITY_THRESHOLD) {
        clearFeedReadVisibilityTimer(tweetId);
        continue;
      }

      if (readTrackingState.visibilityTimers.has(tweetId)) {
        continue;
      }

      const timerId = window.setTimeout(() => {
        readTrackingState.visibilityTimers.delete(tweetId);
        void recordFeedCardView(card);
      }, READ_DWELL_MS);

      readTrackingState.visibilityTimers.set(tweetId, timerId);
    }
  },
  {
    threshold: [0, READ_VISIBILITY_THRESHOLD, 1]
  }
);

function observeUnreadFeedCards() {
  if (!(grid instanceof HTMLElement) || subscriptionsFollowList instanceof HTMLElement) {
    return;
  }

  for (const node of grid.querySelectorAll(".feed-grid-item[data-tweet-id]")) {
    if (!(node instanceof HTMLElement)) {
      continue;
    }

    const tweetId = String(node.dataset.tweetId || "");
    const tweet = feedItemsById.get(tweetId);
    if (
      !tweetId ||
      readTrackingState.observedTweetIds.has(tweetId) ||
      !tweet ||
      !tweet.videoId ||
      tweet.isNewForViewer !== true
    ) {
      continue;
    }

    node.dataset.videoId = String(tweet.videoId);
    node.dataset.viewedByViewer = tweet.viewedByViewer === true ? "true" : "false";
    node.dataset.isNewForViewer = tweet.isNewForViewer === true ? "true" : "false";
    readTrackingState.observedTweetIds.add(tweetId);
    feedReadObserver.observe(node);
  }
}

function resetFeedReadTracking() {
  for (const timerId of readTrackingState.visibilityTimers.values()) {
    window.clearTimeout(timerId);
  }

  readTrackingState.visibilityTimers.clear();
  readTrackingState.pendingVideoIds.clear();
  readTrackingState.observedTweetIds.clear();
  feedReadObserver.disconnect();
}

function syncAuthorFollowState(authorUserId, following) {
  for (const tweet of feedItemsById.values()) {
    if (Number(tweet.authorUserId) === Number(authorUserId)) {
      tweet.authorFollowedByViewer = following;
    }
  }
}

function appendFeedItemsToState(items) {
  for (const tweet of items) {
    const tweetId = String(tweet?.tweetId || "");
    if (!tweetId) {
      continue;
    }

    if (!feedItemsById.has(tweetId)) {
      orderedTweetIds.push(tweetId);
    }

    feedItemsById.set(tweetId, tweet);
  }

  detailModalController.syncFeedItems();
}

function setStatus(label) {
  if (feedStatus) {
    feedStatus.textContent = label;
  }
}

function setLoadingIndicator(visible) {
  if (!(feedLoadingIndicator instanceof HTMLElement)) {
    return;
  }

  const shouldShow = visible && !state.done;
  feedLoadingIndicator.hidden = !shouldShow;
  feedLoadingIndicator.classList.toggle("hidden", !shouldShow);
  feedLoadingIndicator.setAttribute("aria-hidden", shouldShow ? "false" : "true");
}

function updateFeedSummary() {
  if (!feedSummary) {
    return;
  }

  feedSummary.textContent = formatFeedSummary({
    mode: state.mode,
    query: state.query,
    sourceHandle: state.source,
    renderedCount: state.renderedCount,
    done: state.done
  });
}

async function loadSources() {
  if (!sourceFilter || state.mode !== "explore") {
    return;
  }

  if (sourceFilter.dataset.bootstrap === "true") {
    sourceFilter.value = state.source;
    if (sourceFilter.disabled) {
      setStatus("等待配置来源");
    }
    return;
  }

  const payload = await requestJson("/api/sources");
  const enabledSources = payload.items
    .filter((source) => source.enabled)
    .sort(
      (left, right) => right.publishedCount - left.publishedCount || left.handle.localeCompare(right.handle)
    );

  const defaultOption = document.createElement("option");
  defaultOption.value = "";
  defaultOption.textContent = "全部来源";
  sourceFilter.replaceChildren(defaultOption);

  for (const source of enabledSources) {
    const option = document.createElement("option");
    option.value = source.handle;
    option.textContent = `@${source.handle} · ${source.publishedCount}`;
    sourceFilter.append(option);
  }

  sourceFilter.value = state.source;

  if (!enabledSources.length) {
    setStatus("等待配置来源");
  }
}

function resetFeed() {
  resetFeedReadTracking();
  state.cursor = null;
  state.done = false;
  state.renderedCount = 0;
  state.loadToken += 1;
  state.isLoading = false;
  feedItemsById.clear();
  orderedTweetIds.length = 0;
  gridController.clearFeed({
    onBeforeClear: () => detailModalController.close({ restoreFocus: false })
  });
  setLoadingIndicator(false);
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
  setLoadingIndicator(true);
  setStatus("加载内容中");
  updateFeedSummary();

  try {
    const params = new URLSearchParams();
    params.set("limit", String(state.pageSize));
    if (state.cursor) {
      params.set("cursor", state.cursor);
    }
    if (state.source) {
      params.set("source", state.source);
    }
    if (state.query) {
      params.set("q", state.query);
    }
    params.set("mode", state.mode);

    const payload = await requestJson(`/api/feed?${params.toString()}`);

    if (loadToken !== state.loadToken || sourceAtRequest !== state.source) {
      return;
    }

    if (!payload.items.length && !state.cursor) {
      state.done = true;
      gridController.renderEmptyState();
      setLoadingIndicator(false);
      updateFeedSummary();
      setStatus("当前没有内容");
      return;
    }

    gridController.markEmpty(false);
    gridController.syncLayout();
    appendFeedItemsToState(payload.items);
    gridController.appendFeedItems(payload.items);
    observeUnreadFeedCards();

    state.renderedCount += payload.items.length;
    state.cursor = payload.nextCursor;
    state.done = !payload.nextCursor;
    updateFeedSummary();
    setStatus(state.done ? "已经到底了" : "继续下滑加载");
  } catch (error) {
    setLoadingIndicator(false);
    setStatus("内容加载失败");
    if (feedSummary) {
      feedSummary.textContent = "加载失败，请稍后重试";
    }
    console.error(error);
  } finally {
    if (loadToken === state.loadToken) {
      state.isLoading = false;
      setLoadingIndicator(false);
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

function readBootstrapData() {
  const bootstrapNode = document.querySelector("#feed-bootstrap");
  if (!(bootstrapNode instanceof HTMLScriptElement)) {
    return null;
  }

  try {
    return JSON.parse(bootstrapNode.textContent || "null");
  } catch (error) {
    console.error("Failed to parse feed bootstrap payload", error);
    return null;
  }
}

function hydrateBootstrappedFeed() {
  const payload = readBootstrapData();
  if (!payload) {
    return false;
  }

  const items = Array.isArray(payload.items) ? payload.items : [];
  const nextCursor =
    typeof payload.nextCursor === "string" && payload.nextCursor ? payload.nextCursor : null;
  const query = typeof payload.query === "string" ? payload.query : "";
  const sourceHandle = typeof payload.source === "string" ? payload.source : "";
  const mode = typeof payload.mode === "string" ? payload.mode : "featured";
  const nextPageSize = Number(payload.limit);

  state.cursor = nextCursor;
  state.done = !nextCursor;
  state.query = query;
  state.source = sourceHandle;
  state.mode = ["featured", "following", "explore", "history", "bookmarks", "interactions"].includes(mode)
    ? mode
    : "featured";
  state.renderedCount = items.length;

  if (Number.isFinite(nextPageSize) && nextPageSize > 0) {
    state.pageSize = Math.floor(nextPageSize);
  }

  appendFeedItemsToState(items);

  if (!items.length) {
    gridController.renderEmptyState();
  } else {
    gridController.markEmpty(false);
    gridController.seedInitialFeedLayout();
    gridController.ensureColcade();
    gridController.hydrateExistingFeedItems();
    observeUnreadFeedCards();
  }

  updateFeedSummary();
  setLoadingIndicator(false);
  setStatus(!items.length ? "当前没有内容" : state.done ? "已经到底了" : "继续下滑加载");
  return true;
}

if (sourceFilter) {
  sourceFilter.addEventListener("change", async (event) => {
    await applySource(event.target.value);
  });
}

gridController.bindDetailTriggers((tweetId, triggerElement, options) => {
  const tweet = feedItemsById.get(String(tweetId || ""));
  if (!tweet) {
    return;
  }

  gridController.stopAllFeedPreviews();
  detailModalController.open(tweet, triggerElement, options);
});

window.addEventListener("shortvideo:author-follow-change", (event) => {
  const authorUserId = event?.detail?.authorUserId;
  if (!Number.isInteger(Number(authorUserId))) {
    return;
  }

  syncAuthorFollowState(Number(authorUserId), event?.detail?.following === true);
});

window.addEventListener(VIEW_RECORDED_EVENT, (event) => {
  const videoId = String(event?.detail?.videoId || "");
  if (!videoId) {
    return;
  }

  markFeedVideoAsRead(videoId);
});

detailModalController.bindDismissInteractions();

window.addEventListener("popstate", () => {
  if (!mobileDetailHistoryActive || !detailModalController.isOpen() || !detailModalController.isMobileMode()) {
    return;
  }

  closingDetailFromPopstate = true;
  detailModalController.close({ restoreFocus: false });
});

async function bootstrap() {
  const hydratedFromServer = hydrateBootstrappedFeed();
  await loadSources();
  if (!hydratedFromServer) {
    gridController.ensureColcade();
    resetFeed();
    await loadFeed();
  }
  if (sentinel) {
    feedObserver.observe(sentinel);
  }
}

bootstrap().catch((error) => {
  setStatus("启动失败");
  console.error(error);
});
