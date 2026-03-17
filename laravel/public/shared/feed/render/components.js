import { escapeHtml, formatVideoDurationText, getAuthorInitial, renderAvatarMarkup } from "./templateUtils.js";

const FEED_DURATION_BADGE_CLASS =
  "pointer-events-none absolute right-3 top-3 z-10 rounded-full bg-black/15 px-2.5 py-1.5 text-sm font-semibold leading-none text-white backdrop-blur-sm";

function formatCompactCount(value) {
  const numericValue = Math.max(0, Number(value) || 0);
  if (numericValue < 1000) {
    return String(numericValue);
  }

  const compactValue = Math.round((numericValue / 1000) * 10) / 10;
  return `${compactValue.toFixed(compactValue >= 10 ? 0 : 1)}k`;
}

function getReactionTone(type, active) {
  if (type === "like") {
    return active
      ? "border-rose-200 bg-rose-50 text-rose-600"
      : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700";
  }

  return active
    ? "border-amber-200 bg-amber-50 text-amber-700"
    : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700";
}

function renderReactionButton({ type, label, count, active, enabled = true }) {
  const safeLabel = escapeHtml(label);
  const safeCount = escapeHtml(formatCompactCount(count));
  const activeAttr = active ? "true" : "false";
  const enabledAttr = enabled ? "true" : "false";
  const iconClass =
    type === "like"
      ? active
        ? "ph-fill ph-heart"
        : "ph ph-heart"
      : active
        ? "ph-fill ph-bookmark-simple"
        : "ph ph-bookmark-simple";

  return `
    <button
      type="button"
      class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold transition ${getReactionTone(
        type,
        active
      )}"
      data-detail-reaction-button="${type}"
      data-active="${activeAttr}"
      data-enabled="${enabledAttr}"
      data-count="${escapeHtml(String(count))}"
      aria-pressed="${activeAttr}"
      aria-label="${safeLabel}"
    >
      <i
        class="${iconClass} text-[1.05rem] leading-none"
        aria-hidden="true"
        data-detail-reaction-icon
      ></i>
      <span class="text-xs font-semibold tabular-nums opacity-80" data-detail-reaction-count>${safeCount}</span>
    </button>
  `;
}

export function renderEmptyStateCard({
  title = "还没有可展示的视频",
  body = "先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。"
} = {}) {
  return `
    <article
      class="feed-grid-item mb-3 inline-block w-full overflow-hidden rounded-3xl border border-gray-200 bg-white/95 px-6 py-8 text-center shadow-xl backdrop-blur-2xl sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-empty-state="true"
    >
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-xl text-gray-700">
        ⌕
      </div>
      <h2 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">
        ${escapeHtml(title)}
      </h2>
      <p class="mt-3 text-sm leading-6 text-gray-500 sm:text-base">
        ${body}
      </p>
    </article>
  `;
}

export function getAuthorMeta(tweet) {
  const authorName = tweet.authorName || `@${tweet.authorHandle || "unknown"}`;
  const authorHandle = tweet.authorHandle || "unknown";

  return {
    authorName,
    authorHandle,
    authorInitial: getAuthorInitial(authorName)
  };
}

export function renderDurationBadge(durationText, className = FEED_DURATION_BADGE_CLASS) {
  const safeDurationText = escapeHtml(formatVideoDurationText(durationText));
  const hiddenClass = safeDurationText ? "" : "hidden ";

  return `
    <span
      class="${hiddenClass}${className}"
      data-video-duration
    >${safeDurationText}</span>
  `;
}

export function renderAuthorIdentity({
  imageUrl,
  authorName,
  authorHandle,
  authorInitial,
  avatarSizeClass,
  nameClass,
  handleClass = "",
  wrapperClass = "flex min-w-0 items-center gap-3",
  fallbackClass = "bg-gray-100 text-gray-700",
  imageClass = ""
}) {
  const safeName = escapeHtml(authorName);
  const safeHandle = escapeHtml(authorHandle);
  const handleMarkup = handleClass
    ? `
        <p class="${handleClass}">@${safeHandle}</p>
      `
    : "";

  return `
    <div class="${wrapperClass}">
      ${renderAvatarMarkup({
        imageUrl,
        label: authorName,
        initial: authorInitial,
        sizeClass: avatarSizeClass,
        fallbackClass,
        imageClass
      })}
      <div class="min-w-0">
        <p class="${nameClass}">${safeName}</p>
        ${handleMarkup}
      </div>
    </div>
  `;
}

export function renderFeedMedia({ tweet, frameClass, authorHandle }) {
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeHlsUrl = escapeHtml(tweet.hlsUrl || "");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeHandle = escapeHtml(authorHandle || "unknown");
  const videoPreload = tweet.hlsUrl ? "metadata" : "none";
  const mediaMarkup =
    tweet.status === "resolved" && (tweet.hlsUrl || tweet.videoUrl)
      ? `
          <video
            class="js-feed-player h-full w-full object-cover"
            ${safePoster ? `poster="${safePoster}"` : ""}
            data-poster="${safePoster}"
            ${safeHlsUrl ? `data-hls-url="${safeHlsUrl}"` : ""}
            ${safeVideoUrl ? `data-fallback-url="${safeVideoUrl}"` : ""}
            muted
            loop
            playsinline
            disablepictureinpicture
            preload="${videoPreload}"
            referrerpolicy="no-referrer"
          ></video>
        `
      : `
          <img
            class="h-full w-full object-cover"
            src="${safePoster}"
            alt="Poster for @${safeHandle}"
            loading="lazy"
          />
        `;

  return `
    <div class="relative ${frameClass} overflow-hidden bg-gray-100">
      ${mediaMarkup}
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-black/5 to-black/10"></div>
      ${renderDurationBadge(tweet.durationText)}
    </div>
  `;
}

export function renderDetailMedia({ tweet, authorName }) {
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeHlsUrl = escapeHtml(tweet.hlsUrl || "");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeAuthor = escapeHtml(authorName);

  if ((tweet.hlsUrl || tweet.videoUrl) && tweet.status === "resolved") {
    return `
      <video
        class="detail-modal-video h-full w-full bg-black object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]"
        ${tweet.hlsUrl ? "" : `src="${safeVideoUrl}"`}
        ${safePoster ? `poster="${safePoster}"` : ""}
        autoplay
        muted
        loop
        playsinline
        preload="metadata"
        data-detail-layout-node="true"
        data-detail-player
        ${safeHlsUrl ? `data-hls-url="${safeHlsUrl}"` : ""}
        ${safeVideoUrl ? `data-fallback-url="${safeVideoUrl}"` : ""}
        referrerpolicy="no-referrer"
      ></video>
    `;
  }

  return `
    <div class="relative flex h-full w-full items-center justify-center">
      <img
        class="max-h-full w-auto max-w-full object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]"
        src="${safePoster}"
        alt="${safeAuthor} 的视频封面"
        loading="lazy"
      />
      <span class="absolute bottom-6 left-6 rounded-full bg-white/10 px-4 py-2 text-xs font-semibold tracking-[0.18em] text-white backdrop-blur-md">
        VIDEO PREVIEW
      </span>
    </div>
  `;
}

export function renderDetailComposer({ engagement, canComment = false }) {
  return `
    <div class="border-t border-gray-200 px-5 py-4 sm:px-6">
      <div class="flex flex-col gap-3">
        <form class="flex items-center gap-3" data-detail-comment-form="true">
          <input
            type="text"
            name="body"
            maxlength="500"
            class="h-12 min-w-0 flex-1 rounded-full bg-gray-100 px-4 text-sm text-gray-900 outline-none placeholder:text-gray-400"
            placeholder="${canComment ? "说点什么..." : "登录后参与评论"}"
            ${canComment ? "" : "disabled"}
            data-detail-comment-input="true"
          />
          <button
            type="submit"
            class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
            data-detail-comment-submit="true"
          >
            ${canComment ? "发布" : "去登录"}
          </button>
        </form>
        <div class="flex shrink-0 items-center gap-2">
          ${renderReactionButton({
            type: "like",
            label: "点赞",
            count: engagement.likeCount,
            active: engagement.likedByViewer,
            enabled: canComment
          })}
          ${renderReactionButton({
            type: "bookmark",
            label: "收藏",
            count: engagement.bookmarkCount,
            active: engagement.bookmarkedByViewer,
            enabled: canComment
          })}
        </div>
      </div>
    </div>
  `;
}
