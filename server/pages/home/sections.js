import { renderFeedEmptyState, renderFeedItem } from "../../../shared/feed/render/templatesEntry.js";

import { escapeHtml } from "./helpers.js";

function renderFeedGridItems(items, isEmpty) {
  if (isEmpty) {
    return renderFeedEmptyState();
  }

  return items.map((item) => renderFeedItem(item)).join("");
}

function renderActiveSourceOption(activeSourceHandle) {
  if (!activeSourceHandle) {
    return "";
  }

  return `
        <option value="${escapeHtml(activeSourceHandle)}" selected>
          ${escapeHtml(`@${activeSourceHandle}`)}
        </option>
      `;
}

export function renderDocumentHead(pageTitle) {
  return `
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>${escapeHtml(pageTitle)}</title>
    <link rel="stylesheet" href="/vendor/fonts/fonts.css" />
    <link rel="stylesheet" href="/vendor/phosphor/regular/style.css" />
    <link rel="stylesheet" href="/vendor/phosphor/fill/style.css" />
    <link rel="stylesheet" href="/vendor/plyr/plyr.css" />
    <link rel="stylesheet" href="/styles.css" />
  `;
}

export function renderDesktopNavigation() {
  return `
          <aside class="hidden lg:block lg:sticky lg:top-[100px] lg:w-56 lg:flex-none xl:top-[104px] 2xl:top-[108px]">
            <nav aria-label="桌面主导航">
              <div class="grid gap-2">
                <button
                  type="button"
                  aria-current="page"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full bg-gray-100 px-6 text-left text-lg font-semibold text-gray-900 transition-colors hover:bg-gray-200"
                >
                  <i class="ph-fill ph-house text-2xl leading-none" aria-hidden="true"></i>
                  <span>首页</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  <i class="ph ph-bookmarks text-2xl leading-none" aria-hidden="true"></i>
                  <span>订阅</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  <i class="ph ph-compass text-2xl leading-none" aria-hidden="true"></i>
                  <span>探索</span>
                </button>
                <button
                  type="button"
                  class="inline-flex h-12 w-full items-center gap-4 rounded-full px-6 text-left text-lg font-medium text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900"
                >
                  <i class="ph ph-chart-bar text-2xl leading-none" aria-hidden="true"></i>
                  <span>榜单</span>
                </button>
              </div>
            </nav>
          </aside>
  `;
}

export function renderMobileNavigation() {
  return `
          <nav
            aria-label="移动主导航"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-3 py-2 backdrop-blur-2xl lg:hidden"
          >
            <div class="mx-auto grid max-w-md grid-cols-4">
              <button
                type="button"
                aria-current="page"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-900"
              >
                <i class="ph-fill ph-house text-[28px] leading-none" aria-hidden="true"></i>
                首页
              </button>
              <button
                type="button"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500"
              >
                <i class="ph ph-bookmarks text-[28px] leading-none" aria-hidden="true"></i>
                订阅
              </button>
              <button
                type="button"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500"
              >
                <i class="ph ph-compass text-[28px] leading-none" aria-hidden="true"></i>
                探索
              </button>
              <button
                type="button"
                class="flex flex-col items-center justify-center gap-1 py-2 text-center text-xs font-medium text-gray-500"
              >
                <i class="ph ph-chart-bar text-[28px] leading-none" aria-hidden="true"></i>
                榜单
              </button>
            </div>
          </nav>
  `;
}

export function renderFeedToolbar({
  activeSourceHandle,
  feedSummaryText,
  feedStatusText
}) {
  return `
            <div class="mb-4 flex flex-col gap-3 rounded-[28px] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm backdrop-blur-xl sm:px-5 lg:mb-5 lg:flex-row lg:items-center lg:justify-between lg:px-6">
              <div class="min-w-0">
                <p
                  id="feed-summary"
                  class="text-sm font-medium text-gray-700"
                >${escapeHtml(feedSummaryText)}</p>
                <p
                  id="feed-status"
                  class="mt-1 text-xs text-gray-500"
                  aria-live="polite"
                >${escapeHtml(feedStatusText)}</p>
              </div>
              <label class="inline-flex items-center gap-3 text-sm text-gray-600">
                <span class="shrink-0 font-medium text-gray-700">来源</span>
                <select
                  id="source-filter"
                  class="h-11 min-w-[180px] rounded-full border border-gray-200 bg-gray-50 px-4 text-sm text-gray-900 outline-none transition focus:border-gray-300"
                >
                  <option value="" ${activeSourceHandle ? "" : "selected"}>全部来源</option>
                  ${renderActiveSourceOption(activeSourceHandle)}
                </select>
              </label>
            </div>
  `;
}

export function renderFeedGrid(viewModel) {
  return `
            <section class="feed-grid" id="feed-grid" aria-live="polite" data-empty="${viewModel.feed.isEmpty}">
              <div class="feed-grid-col">
                ${renderFeedGridItems(viewModel.feed.items, viewModel.feed.isEmpty)}
              </div>
              <div class="feed-grid-col"></div>
              <div class="feed-grid-col hidden xl:block"></div>
              <div class="feed-grid-col hidden 2xl:block"></div>
            </section>
            <div id="feed-sentinel" class="h-px" aria-hidden="true"></div>
  `;
}

export function renderDetailModal() {
  return `
    <div
      id="feed-detail-modal"
      class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/20 p-3 sm:p-5 xl:p-7"
      hidden
    >
      <section
        id="feed-detail-modal-panel"
        class="relative z-10 flex h-[92vh] max-h-[920px] w-full max-w-[1520px] overflow-hidden rounded-[32px] bg-white shadow-glass animate-card-in"
        role="dialog"
        aria-modal="true"
        aria-labelledby="detail-modal-title"
        tabindex="-1"
      >
      </section>
    </div>
  `;
}
