import { renderFeedItem } from "./render.js";

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
const sourceBoard = document.querySelector("#source-board");
const feedStatus = document.querySelector("#feed-status");
const feedSummary = document.querySelector("#feed-summary");
const liveStatus = document.querySelector("#live-status");
const sourceTotal = document.querySelector("#source-total");
const sentinel = document.querySelector("#feed-sentinel");
const emptyStateTemplate = document.querySelector("#empty-state-template");
const publishedTotalEl = document.querySelector("#published-total");
const localPlayableEl = document.querySelector("#local-playable");
const externalCountEl = document.querySelector("#external-count");
const lastUpdatedEl = document.querySelector("#last-updated");

const ACTIVE_SOURCE_CHIP_CLASS =
  "rounded-full border border-[#d14d34] bg-[#211b17] px-4 py-2 text-sm font-medium text-white shadow-[0_12px_28px_rgba(33,27,23,0.14)] transition";
const INACTIVE_SOURCE_CHIP_CLASS =
  "rounded-full border border-[#eadfd4] bg-white/88 px-4 py-2 text-sm font-medium text-[#6d5d52] shadow-[0_10px_28px_rgba(58,27,12,0.04)] transition hover:border-[#d14d34]/35 hover:text-[#211b17]";

const videoObserver = new IntersectionObserver(
  (entries) => {
    for (const entry of entries) {
      const video = entry.target;
      if (!(video instanceof HTMLVideoElement)) {
        continue;
      }

      if (entry.isIntersecting) {
        video.play().catch(() => {});
      } else {
        video.pause();
      }
    }
  },
  {
    threshold: 0.55
  }
);

function escapeHtml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function formatDateTime(value) {
  if (!value) {
    return "暂无";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short"
  }).format(new Date(value));
}

function getSourceLabel(handle) {
  return handle ? `@${handle}` : "全部来源";
}

function setStatus(label) {
  feedStatus.textContent = label;
}

function setLiveStatus(label) {
  liveStatus.textContent = label;
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

function renderSourceBoard(sources) {
  sourceBoard.replaceChildren();

  if (sources.length === 0) {
    sourceBoard.append(
      createFragment(`
        <article class="rounded-[1.35rem] border border-[#eadfd4] bg-white/85 px-4 py-4 text-sm leading-6 text-[#78675d]">
          暂无启用来源，先在 <code>config/sources.json</code> 中添加账号。
        </article>
      `)
    );
    return;
  }

  for (const source of sources.slice(0, 6)) {
    const syncState =
      source.pendingCount > 0
        ? `${source.pendingCount} 条待解析`
        : source.publishedCount > 0
          ? `${source.publishedCount} 条已发布`
          : "等待首条内容";
    const syncToneClass =
      source.pendingCount > 0 ? "bg-[#fff1da] text-[#ae6b00]" : "bg-[#eef6ef] text-[#2b6b44]";
    const lastSeen = source.lastDiscoveredAt
      ? `最近发现 ${formatDateTime(source.lastDiscoveredAt)}`
      : "尚未记录发现时间";
    const runLabel =
      source.lastRunStatus === "success"
        ? "最近任务成功"
        : source.lastRunStatus
          ? `最近任务 ${escapeHtml(source.lastRunStatus)}`
          : "尚无运行记录";

    sourceBoard.append(
      createFragment(`
        <article class="rounded-[1.35rem] border border-[#eadfd4] bg-white/88 px-4 py-4 shadow-[0_12px_30px_rgba(58,27,12,0.05)]">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-[1rem] font-semibold text-[#211b17]">@${escapeHtml(source.handle)}</p>
              <p class="mt-1 text-xs text-[#8d7b6f]">${lastSeen}</p>
            </div>
            <span class="rounded-full ${syncToneClass} px-2.5 py-1 text-[0.72rem] font-semibold">
              ${syncState}
            </span>
          </div>
          <div class="mt-3 flex items-center justify-between gap-3 text-xs text-[#8d7b6f]">
            <span>已发布 ${source.publishedCount}</span>
            <span>${runLabel}</span>
          </div>
        </article>
      `)
    );
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

async function loadStats() {
  const stats = await fetchJson("/api/stats");
  publishedTotalEl.textContent = String(stats.totalItems);
  localPlayableEl.textContent = String(stats.resolvedCount);
  externalCountEl.textContent = String(stats.externalOnlyCount);
  lastUpdatedEl.textContent = formatDateTime(stats.lastUpdatedAt);
  setLiveStatus(stats.lastUpdatedAt ? `最近同步 ${formatDateTime(stats.lastUpdatedAt)}` : "等待首轮抓取");
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
  renderSourceBoard(spotlightSources);
  sourceTotal.textContent = `${spotlightSources.length} 个活跃来源`;

  if (!enabledSources.length) {
    setLiveStatus("等待配置来源");
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
  await Promise.all([loadStats(), loadSources()]);
  resetFeed();
  await loadFeed();
  feedObserver.observe(sentinel);
}

bootstrap().catch((error) => {
  setStatus("启动失败");
  console.error(error);
});
