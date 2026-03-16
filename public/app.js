import { renderFeedItem } from "./render.js";
import { installHoverVideoPreview } from "./videoPreview.js";

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
const sourceChips = document.querySelector("#source-chips");
const feedStatus = document.querySelector("#feed-status");
const feedSummary = document.querySelector("#feed-summary");
const sentinel = document.querySelector("#feed-sentinel");
const emptyStateTemplate = document.querySelector("#empty-state-template");

const ACTIVE_SOURCE_CHIP_CLASS =
  "rounded-full border border-[#d14d34] bg-[#211b17] px-4 py-2 text-sm font-medium text-white shadow-[0_12px_28px_rgba(33,27,23,0.14)] transition";
const INACTIVE_SOURCE_CHIP_CLASS =
  "rounded-full border border-[#eadfd4] bg-white/88 px-4 py-2 text-sm font-medium text-[#6d5d52] shadow-[0_10px_28px_rgba(58,27,12,0.04)] transition hover:border-[#d14d34]/35 hover:text-[#211b17]";
const videoPreviewControllers = new Map();

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
  feedStatus.textContent = label;
}

function createFragment(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content.firstElementChild;
}

function updateFeedSummary() {
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
  if (grid.children.length > 0) {
    return;
  }

  grid.dataset.empty = "true";
  const clone = emptyStateTemplate.content.cloneNode(true);
  grid.appendChild(clone);
  updateFeedSummary();
}

function clearFeed() {
  for (const video of grid.querySelectorAll("video")) {
    videoObserver.unobserve(video);
    videoPreviewControllers.get(video)?.destroy();
    videoPreviewControllers.delete(video);
  }

  grid.dataset.empty = "false";
  grid.replaceChildren();
}

function syncActiveSourceChip() {
  for (const button of sourceChips.querySelectorAll("button")) {
    const active = button.dataset.source === state.source;
    button.className = active ? ACTIVE_SOURCE_CHIP_CLASS : INACTIVE_SOURCE_CHIP_CLASS;
    button.setAttribute("aria-pressed", active ? "true" : "false");
  }
}

function renderSourceChips(sources) {
  sourceChips.replaceChildren();

  const makeChip = (handle, label) => {
    const button = document.createElement("button");
    button.type = "button";
    button.dataset.source = handle;
    button.className = INACTIVE_SOURCE_CHIP_CLASS;
    button.textContent = label;
    button.addEventListener("click", () => {
      applySource(handle).catch((error) => {
        setStatus("筛选失败");
        console.error(error);
      });
    });
    return button;
  };

  sourceChips.append(makeChip("", "全部"));

  for (const source of sources) {
    sourceChips.append(makeChip(source.handle, `@${source.handle}`));
  }

  syncActiveSourceChip();
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
  const activeSources = enabledSources.filter(
    (source) => source.publishedCount > 0 || source.pendingCount > 0
  );
  const spotlightSources = activeSources.length > 0 ? activeSources : enabledSources;

  sourceFilter.replaceChildren(createFragment('<option value="">全部来源</option>'));

  for (const source of enabledSources) {
    const option = document.createElement("option");
    option.value = source.handle;
    option.textContent = `@${source.handle} · ${source.publishedCount}`;
    sourceFilter.append(option);
  }

  renderSourceChips(spotlightSources);

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
  sourceFilter.value = nextSource;
  syncActiveSourceChip();
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

    for (const tweet of payload.items) {
      const node = createFragment(renderFeedItem(tweet));
      grid.append(node);
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
    feedSummary.textContent = "加载失败，请稍后重试";
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

sourceFilter.addEventListener("change", async (event) => {
  await applySource(event.target.value);
});

async function bootstrap() {
  await loadSources();
  resetFeed();
  await loadFeed();
  feedObserver.observe(sentinel);
}

bootstrap().catch((error) => {
  setStatus("启动失败");
  console.error(error);
});
