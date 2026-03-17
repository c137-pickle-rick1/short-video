import { formatFeedSummary } from "./render.js";
import { createDetailModalController } from "./app/detailModal.js";
import { createFeedGridController } from "./app/feedGrid.js";

const state = {
  cursor: null,
  isLoading: false,
  done: false,
  pageSize: 8,
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
const gridController = createFeedGridController({
  grid,
  emptyStateTemplate
});
const detailModalController = createDetailModalController({
  detailModal,
  detailModalPanel
});

function setStatus(label) {
  if (feedStatus) {
    feedStatus.textContent = label;
  }
}

function updateFeedSummary() {
  if (!feedSummary) {
    return;
  }

  feedSummary.textContent = formatFeedSummary({
    sourceHandle: state.source,
    renderedCount: state.renderedCount,
    done: state.done
  });
}

async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }

  return response.json();
}

async function loadSources() {
  if (!sourceFilter) {
    return;
  }

  if (sourceFilter.dataset.bootstrap === "true") {
    sourceFilter.value = state.source;
    if (sourceFilter.disabled) {
      setStatus("等待配置来源");
    }
    return;
  }

  const payload = await fetchJson("/api/sources");
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
  state.cursor = null;
  state.done = false;
  state.renderedCount = 0;
  state.loadToken += 1;
  state.isLoading = false;
  gridController.clearFeed({
    onBeforeClear: () => detailModalController.close({ restoreFocus: false })
  });
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
    params.set("limit", String(state.pageSize));
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
      gridController.renderEmptyState();
      updateFeedSummary();
      setStatus("当前没有内容");
      return;
    }

    gridController.markEmpty(false);
    gridController.syncLayout();

    for (const tweet of payload.items) {
      feedItemsById.set(String(tweet.tweetId), tweet);
    }

    gridController.appendFeedItems(payload.items);

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
  const sourceHandle = typeof payload.source === "string" ? payload.source : "";
  const nextPageSize = Number(payload.limit);

  state.cursor = nextCursor;
  state.done = !nextCursor;
  state.source = sourceHandle;
  state.renderedCount = items.length;

  if (Number.isFinite(nextPageSize) && nextPageSize > 0) {
    state.pageSize = Math.floor(nextPageSize);
  }

  for (const tweet of items) {
    feedItemsById.set(String(tweet.tweetId), tweet);
  }

  if (!items.length) {
    gridController.renderEmptyState();
  } else {
    gridController.markEmpty(false);
    gridController.seedInitialFeedLayout();
    gridController.ensureColcade();
    gridController.hydrateExistingFeedItems();
  }

  updateFeedSummary();
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

detailModalController.bindDismissInteractions();

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
