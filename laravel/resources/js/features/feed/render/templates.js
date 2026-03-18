import { formatDetailDate, formatFeedDate } from "./formatters.js";
import {
  getAuthorMeta,
  renderAuthorIdentity,
  renderCommentButton,
  renderDetailComposer,
  renderDetailMedia,
  renderEmptyStateCard,
  renderFeedMedia,
  renderReactionButton
} from "./components.js";
import { escapeHtml, getDisplayText, getMediaFrameClass } from "./template-utils.js";

function getEngagementState(tweet) {
  return tweet.engagement || {
    likeCount: 0,
    bookmarkCount: 0,
    commentCount: 0,
    viewCount: 0,
    likedByViewer: false,
    bookmarkedByViewer: false
  };
}

function getViewerPermissions(tweet) {
  const authorUserId = Number.parseInt(String(tweet.authorUserId || ""), 10);
  const viewerUserId = Number.parseInt(String(tweet.viewerUserId || ""), 10);
  const hasViewer = Number.isInteger(viewerUserId) && viewerUserId > 0;
  const canFollowAuthor = Boolean(tweet.canFollowAuthor) && Number.isInteger(authorUserId) && authorUserId > 0;

  return {
    authorUserId,
    viewerUserId,
    hasViewer,
    canComment: hasViewer,
    canFollowAuthor
  };
}

function renderAuthorFollowButton(tweet, { tone = "light", size = "default" } = {}) {
  const authorUserId = Number.parseInt(String(tweet.authorUserId || ""), 10);
  const { canFollowAuthor, hasViewer, viewerUserId } = getViewerPermissions(tweet);
  const isFollowing = tweet.authorFollowedByViewer === true;
  const authRequired = !hasViewer && Number.isInteger(authorUserId) && authorUserId > 0;
  const canInteract = canFollowAuthor || authRequired;

  let label = "关注";
  if (!hasViewer) {
    label = "关注";
  } else if (Number.isInteger(authorUserId) && authorUserId === viewerUserId) {
    label = "你自己";
  } else if (!canFollowAuthor) {
    label = "暂不可关注";
  } else if (isFollowing) {
    label = "已关注";
  }

  const buttonClass =
    tone === "dark"
      ? canInteract
        ? isFollowing
          ? "border border-white/20 bg-white/10 text-white hover:bg-white/20"
          : "bg-rose-500 text-white hover:bg-rose-600"
        : "border border-white/10 bg-white/10 text-white/40"
      : canInteract
        ? isFollowing
          ? "border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200"
          : "bg-rose-500 text-white hover:bg-rose-600"
        : "bg-gray-100 text-gray-400";

  const disabledAttr = canInteract ? "" : " disabled";
  const authorUserIdAttr = Number.isInteger(authorUserId) && authorUserId > 0 ? String(authorUserId) : "";
  const sizeClass = size === "compact" ? "h-10 px-4 text-[0.8125rem]" : "h-11 px-5 text-sm";

  return `
          <button
            type="button"
            class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold shadow-sm transition ${sizeClass} ${buttonClass}"
            data-detail-author-follow-button="true"
            data-author-user-id="${escapeHtml(authorUserIdAttr)}"
            data-following="${isFollowing ? "true" : "false"}"
            data-enabled="${canInteract ? "true" : "false"}"
            data-auth-required="${authRequired ? "true" : "false"}"
            data-label-follow="关注"
            data-label-following="已关注"
            data-label-disabled="${escapeHtml(label)}"
            data-size="${escapeHtml(size)}"
            data-tone="${escapeHtml(tone)}"
            aria-pressed="${isFollowing ? "true" : "false"}"${disabledAttr}
          >
            ${escapeHtml(label)}
          </button>
  `;
}

function renderDetailMetaBadges(engagement, { tone = "light" } = {}) {
  const badgeClass =
    tone === "dark"
      ? "rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs text-white/80 backdrop-blur-sm"
      : "rounded-full bg-gray-100 px-3 py-1";

  return `
    <div class="flex flex-wrap items-center gap-2 text-xs ${tone === "dark" ? "text-white/80" : "text-gray-500"}">
      <span class="${badgeClass}">${escapeHtml(String(engagement.viewCount || 0))} 次观看</span>
      <span class="${badgeClass}" data-detail-comment-count-badge="true">
        ${escapeHtml(String(engagement.commentCount || 0))} 条评论
      </span>
    </div>
  `;
}

function getProfileUrl(username) {
  const normalizedUsername = String(username || "").trim();

  return normalizedUsername ? `/${encodeURIComponent(normalizedUsername)}` : null;
}

export function renderFeedEmptyState() {
  return renderEmptyStateCard();
}

export function renderFeedItem(tweet) {
  const displayText = getDisplayText(tweet);
  const safeText = escapeHtml(displayText);
  const { authorName, authorHandle, authorInitial } = getAuthorMeta(tweet);
  const safeAuthor = escapeHtml(authorName);
  const safeStatus = escapeHtml(tweet.status || "pending");
  const frameClass = getMediaFrameClass(tweet);
  const profileUrl = getProfileUrl(tweet.authorUsername);

  return `
    <article
      class="feed-grid-item group mb-3 inline-block w-full cursor-pointer overflow-hidden rounded-3xl border border-gray-200 bg-white/95 shadow-sm backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-rose-300 sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-tweet-id="${escapeHtml(tweet.tweetId)}"
      data-status="${safeStatus}"
      data-feed-detail-trigger="true"
      role="button"
      tabindex="0"
      aria-haspopup="dialog"
      aria-label="打开 ${safeAuthor} 的视频详情"
    >
      ${renderFeedMedia({ tweet, frameClass, authorHandle })}
      <div class="grid gap-3 px-4 pb-4 pt-3">
        <p class="line-clamp-2 overflow-hidden text-base font-semibold leading-6 text-gray-900">
          ${safeText}
        </p>
        <div class="flex items-center justify-between gap-3">
          ${renderAuthorIdentity({
            imageUrl: tweet.authorAvatarUrl,
            authorName,
            authorHandle,
            authorInitial,
            avatarSizeClass: "h-7 w-7",
            nameClass: "truncate text-sm font-semibold text-gray-900",
            profileUrl
          })}
          <div class="shrink-0 text-xs text-gray-500">
            <span>${formatFeedDate(tweet.postedAt)}</span>
          </div>
        </div>
      </div>
    </article>
  `;
}

export function renderFeedDetail(tweet) {
  const displayText = getDisplayText(tweet);
  const safeText = escapeHtml(displayText);
  const { authorName, authorHandle, authorInitial } = getAuthorMeta(tweet);
  const safeDate = escapeHtml(formatDetailDate(tweet.postedAt));
  const engagement = getEngagementState(tweet);
  const { canComment } = getViewerPermissions(tweet);

  return `
    ${renderDetailMedia({ tweet, authorName })}

    <aside
      class="flex w-full max-w-full flex-col border-t border-gray-200 bg-white xl:w-[430px] xl:max-w-[430px] xl:border-l xl:border-t-0"
      data-detail-layout-node="true"
    >
      <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-4">
          ${renderAuthorIdentity({
            imageUrl: tweet.authorAvatarUrl,
            authorName,
            authorHandle,
            authorInitial,
            avatarSizeClass: "h-12 w-12",
            nameClass: "truncate text-base font-semibold text-gray-950",
            handleClass: "mt-1 truncate text-sm text-gray-500"
          })}

          ${renderAuthorFollowButton(tweet)}
        </div>
      </div>

      <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-6 sm:py-6">
        <h2 id="detail-modal-title" class="text-[1.2rem] font-semibold leading-[1.42] text-gray-950 sm:text-[1.35rem]">
          ${safeText}
        </h2>

        <p class="mt-3 text-sm font-normal tracking-[0.02em] text-gray-500">
          发布日期 · ${safeDate}
        </p>

        <div class="mt-4">${renderDetailMetaBadges(engagement)}</div>

        <div class="my-5 h-px bg-gray-200"></div>

        <section aria-labelledby="detail-comments-title">
          <div class="flex items-end justify-between gap-3">
            <div>
              <h3 id="detail-comments-title" class="text-base font-semibold text-gray-950">
                评论区
              </h3>
            </div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500" data-detail-comments-status="true">
              加载中
            </span>
          </div>

          <div class="mt-5 grid gap-5" data-detail-comments-list="true"></div>
        </section>
      </div>

      ${renderDetailComposer({ engagement, canComment })}
    </aside>
  `;
}

export function renderMobileFeedSlide(tweet) {
  const displayText = getDisplayText(tweet);
  const safeText = escapeHtml(displayText);
  const { authorName, authorHandle, authorInitial } = getAuthorMeta(tweet);
  const engagement = getEngagementState(tweet);
  const safeTweetId = escapeHtml(String(tweet.tweetId || ""));

  return `
    <article
      class="mobile-detail-slide relative flex h-[100dvh] w-full snap-start snap-always items-stretch justify-center overflow-hidden bg-black"
      data-detail-layout-node="true"
      data-mobile-detail-slide="true"
      data-tweet-id="${safeTweetId}"
      data-active="false"
      aria-label="${escapeHtml(authorName)} 的视频"
    >
      <div class="detail-mobile-media-shell relative flex-1 overflow-hidden bg-black">
        ${renderDetailMedia({
          tweet,
          authorName,
          videoClass: "detail-modal-video h-full w-full bg-black object-cover",
          imageClass: "h-full w-full object-cover",
          wrapperClass: "relative flex h-full w-full items-center justify-center",
          showPreviewBadge: false
        })}
      </div>

      <div class="pointer-events-none absolute inset-x-0 top-0 z-10 h-40 bg-gradient-to-b from-black/75 via-black/20 to-transparent"></div>
      <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-[44dvh] bg-gradient-to-t from-black/75 via-black/40 to-transparent"></div>

      <div class="absolute inset-x-0 top-0 z-30 flex items-center justify-between px-4 pt-[calc(env(safe-area-inset-top)+0.8rem)] sm:px-6">
        <button
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/35 text-white backdrop-blur-xl transition hover:bg-black/55"
          data-mobile-detail-close="true"
          aria-label="返回视频流"
        >
          <i class="ph ph-arrow-left text-lg leading-none" aria-hidden="true"></i>
        </button>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/35 text-white backdrop-blur-xl transition hover:bg-black/55"
            data-detail-search-action="true"
            aria-label="搜索相关内容"
          >
            <i class="ph ph-magnifying-glass text-lg leading-none" aria-hidden="true"></i>
          </button>
          <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-black/35 text-white backdrop-blur-xl transition hover:bg-black/55"
            data-detail-share-action="true"
            aria-label="分享视频"
          >
            <i class="ph ph-share text-lg leading-none" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <div class="absolute inset-x-0 bottom-0 z-20 px-4 pb-[calc(env(safe-area-inset-bottom)+6.1rem)] pt-24 sm:px-6">
        <div class="mx-auto flex w-full max-w-md flex-col gap-3.5">
          <div class="flex min-w-0 items-center gap-3">
            ${renderAuthorIdentity({
              imageUrl: tweet.authorAvatarUrl,
              authorName,
              authorHandle,
              authorInitial,
              avatarSizeClass: "h-10 w-10",
              nameClass: "truncate text-sm font-semibold text-white",
              wrapperClass: "flex min-w-0 items-center gap-2.5",
              fallbackClass: "bg-white/15 text-white",
              imageClass: "ring-white/15"
            })}
            ${renderAuthorFollowButton(tweet, { tone: "dark", size: "compact" })}
          </div>

          <div class="grid gap-3">
            <h2 class="line-clamp-2 text-[1.05rem] font-semibold leading-7 text-white sm:text-lg">
              ${safeText}
            </h2>
          </div>

        </div>
      </div>

      <div class="absolute inset-x-0 bottom-[calc(env(safe-area-inset-bottom)+4.7rem)] z-30 px-4 sm:px-6">
        <div class="mx-auto grid w-full max-w-md gap-2">
          <div
            class="detail-mobile-progress-time hidden text-center text-[0.95rem] font-medium tracking-[0.04em] text-white/95"
            data-detail-progress-time="true"
            aria-hidden="true"
          >
            00:00 / 00:00
          </div>
          <div class="pointer-events-auto" data-detail-progress-shell="true">
            <input
              type="range"
              min="0"
              max="1000"
              step="1"
              value="0"
              class="detail-mobile-progress-range w-full"
              data-detail-progress-range="true"
              style="--detail-progress-value: 0%;"
              aria-label="视频播放进度"
            />
          </div>
        </div>
      </div>

      <div class="absolute inset-x-0 bottom-0 z-30 border-t border-white/10 bg-black/90 px-4 pb-[calc(env(safe-area-inset-bottom)+0.9rem)] pt-3 backdrop-blur-xl sm:px-6">
        <div class="mx-auto flex w-full max-w-md items-center gap-3">
          <button
            type="button"
            class="inline-flex h-12 min-w-0 flex-1 items-center gap-3 rounded-full bg-white/10 px-4 text-sm text-white/60 transition hover:bg-white/15"
            data-detail-comments-open="true"
            aria-label="打开评论输入"
          >
            <i class="ph ph-pencil-simple-line text-lg leading-none text-white/70" aria-hidden="true"></i>
            <span class="truncate">说点什么...</span>
          </button>

          <div class="flex shrink-0 items-center gap-4">
            ${renderReactionButton({
              type: "like",
              label: "点赞",
              count: engagement.likeCount,
              active: engagement.likedByViewer,
              enabled: true,
              tone: "dark",
              layout: "mobile-stat"
            })}
            ${renderReactionButton({
              type: "bookmark",
              label: "收藏",
              count: engagement.bookmarkCount,
              active: engagement.bookmarkedByViewer,
              enabled: true,
              tone: "dark",
              layout: "mobile-stat"
            })}
            ${renderCommentButton({
              count: engagement.commentCount,
              tone: "dark",
              layout: "mobile-stat"
            })}
          </div>
        </div>
      </div>
    </article>
  `;
}

export function renderMobileCommentsDrawer(tweet) {
  const engagement = getEngagementState(tweet);
  const { canComment } = getViewerPermissions(tweet);

  return `
    <div
      class="pointer-events-auto absolute inset-0 z-40 hidden"
      data-detail-layout-node="true"
      data-mobile-comments-layer="true"
      hidden
      aria-hidden="true"
    >
      <button
        type="button"
        class="absolute inset-0 bg-black/55 opacity-0 transition duration-200"
        data-mobile-comments-backdrop="true"
        aria-label="关闭评论"
      ></button>

      <section
        class="absolute inset-x-0 bottom-0 flex max-h-[82dvh] translate-y-full flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl transition duration-300 ease-out"
        data-mobile-comments-drawer="true"
        aria-labelledby="mobile-detail-comments-title"
      >
        <header class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-5">
          <div class="flex items-center gap-3">
            <h2 id="mobile-detail-comments-title" class="text-base font-semibold text-gray-950">
              评论
            </h2>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500" data-detail-comments-status="true">
              ${escapeHtml(String(engagement.commentCount || 0))} 条评论
            </span>
          </div>

          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200 hover:text-gray-900"
            data-mobile-comments-close="true"
            aria-label="关闭评论"
          >
            <i class="ph ph-x text-lg leading-none" aria-hidden="true"></i>
          </button>
        </header>

        <div class="flex-1 overflow-y-auto px-4 py-5 sm:px-5">
          <div class="grid gap-5" data-detail-comments-list="true"></div>
        </div>

        ${renderDetailComposer({
          engagement,
          canComment,
          includeReactions: false,
          containerClass:
            "border-t border-gray-200 bg-white px-4 py-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] sm:px-5",
          formClass: "flex items-center gap-3"
        })}
      </section>
    </div>
  `;
}
