import { renderFeedItem } from "./render.js";

const state = {
  cursor: null,
  isLoading: false,
  done: false,
  source: ""
};

const grid = document.querySelector("#feed-grid");
const sourceFilter = document.querySelector("#source-filter");
const feedStatus = document.querySelector("#feed-status");
const sentinel = document.querySelector("#feed-sentinel");
const emptyStateTemplate = document.querySelector("#empty-state-template");
const totalItemsEl = document.querySelector("#total-items");
const resolvedCountEl = document.querySelector("#resolved-count");
const lastUpdatedEl = document.querySelector("#last-updated");

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

function setStatus(label) {
  feedStatus.textContent = label;
}

function createFragment(markup) {
  const template = document.createElement("template");
  template.innerHTML = markup.trim();
  return template.content.firstElementChild;
}

function renderEmptyState() {
  if (grid.children.length > 0) {
    return;
  }

  const clone = emptyStateTemplate.content.cloneNode(true);
  grid.appendChild(clone);
}

function clearFeed() {
  for (const video of grid.querySelectorAll("video")) {
    videoObserver.unobserve(video);
  }

  grid.replaceChildren();
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
  totalItemsEl.textContent = String(stats.totalItems);
  resolvedCountEl.textContent = String(stats.resolvedCount);
  lastUpdatedEl.textContent = stats.lastUpdatedAt
    ? new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" }).format(
        new Date(stats.lastUpdatedAt)
      )
    : "Never";
}

async function loadSources() {
  const payload = await fetchJson("/api/sources");
  for (const source of payload.items) {
    if (!source.enabled) {
      continue;
    }
    const option = document.createElement("option");
    option.value = source.handle;
    option.textContent = source.handle;
    sourceFilter.append(option);
  }
}

async function loadFeed({ reset = false } = {}) {
  if (reset) {
    state.cursor = null;
    state.done = false;
    clearFeed();
  }

  if (state.isLoading || state.done) {
    return;
  }

  state.isLoading = true;
  setStatus("Loading feed…");

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
    if (!payload.items.length && !state.cursor) {
      renderEmptyState();
      setStatus("No resolved videos yet");
      state.done = true;
      return;
    }

    for (const tweet of payload.items) {
      const node = createFragment(renderFeedItem(tweet));
      grid.append(node);
      const video = node.querySelector("video");
      if (video) {
        videoObserver.observe(video);
      }
    }

    state.cursor = payload.nextCursor;
    state.done = !payload.nextCursor;
    setStatus(state.done ? "All caught up" : "Scroll for more");
  } catch (error) {
    setStatus("Feed load failed");
    console.error(error);
  } finally {
    state.isLoading = false;
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
  state.source = event.target.value;
  state.done = false;
  await loadFeed({ reset: true });
});

async function bootstrap() {
  await Promise.all([loadStats(), loadSources()]);
  await loadFeed({ reset: true });
  feedObserver.observe(sentinel);
}

bootstrap().catch((error) => {
  setStatus("Startup failed");
  console.error(error);
});
