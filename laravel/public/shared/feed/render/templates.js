import { formatDetailDate, formatFeedDate } from "./formatters.js";
import {
  getAuthorMeta,
  renderAuthorIdentity,
  renderDetailComposer,
  renderDetailMedia,
  renderEmptyStateCard,
  renderFeedMedia
} from "./components.js";
import { escapeHtml, getDisplayText, getMediaFrameClass } from "./templateUtils.js";

function renderAuthorFollowButton(tweet) {
  const authorUserId = Number.parseInt(String(tweet.authorUserId || ""), 10);
  const viewerUserId = Number.parseInt(String(tweet.viewerUserId || ""), 10);
  const hasViewer = Number.isInteger(viewerUserId) && viewerUserId > 0;
  const canFollowAuthor = Boolean(tweet.canFollowAuthor) && Number.isInteger(authorUserId) && authorUserId > 0;
  const isFollowing = tweet.authorFollowedByViewer === true;

  let label = "关注";
  if (!hasViewer) {
    label = "登录后可关注";
  } else if (Number.isInteger(authorUserId) && authorUserId === viewerUserId) {
    label = "你自己";
  } else if (!canFollowAuthor) {
    label = "暂不可关注";
  } else if (isFollowing) {
    label = "已关注";
  }

  const buttonClass = canFollowAuthor
    ? isFollowing
      ? "border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200"
      : "bg-rose-500 text-white hover:bg-rose-600"
    : "bg-gray-100 text-gray-400";

  const disabledAttr = canFollowAuthor ? "" : " disabled";
  const authorUserIdAttr = Number.isInteger(authorUserId) && authorUserId > 0 ? String(authorUserId) : "";

  return `
          <button
            type="button"
            class="inline-flex h-11 shrink-0 items-center rounded-full px-5 text-sm font-semibold shadow-sm transition ${buttonClass}"
            data-detail-author-follow-button="true"
            data-author-user-id="${escapeHtml(authorUserIdAttr)}"
            data-following="${isFollowing ? "true" : "false"}"
            data-enabled="${canFollowAuthor ? "true" : "false"}"
            data-label-follow="关注"
            data-label-following="已关注"
            data-label-disabled="${escapeHtml(label)}"
            aria-pressed="${isFollowing ? "true" : "false"}"${disabledAttr}
          >
            ${escapeHtml(label)}
          </button>
  `;
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
            nameClass: "truncate text-sm font-semibold text-gray-900"
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
  const engagement = tweet.engagement || {
    likeCount: 0,
    bookmarkCount: 0,
    commentCount: 0,
    viewCount: 0,
    likedByViewer: false,
    bookmarkedByViewer: false
  };
  const viewerUserId = Number.parseInt(String(tweet.viewerUserId || ""), 10);
  const canComment = Number.isInteger(viewerUserId) && viewerUserId > 0;

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

        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-gray-500">
          <span class="rounded-full bg-gray-100 px-3 py-1">${escapeHtml(String(engagement.viewCount || 0))} 次观看</span>
          <span class="rounded-full bg-gray-100 px-3 py-1" data-detail-comment-count-badge="true">
            ${escapeHtml(String(engagement.commentCount || 0))} 条评论
          </span>
        </div>

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
