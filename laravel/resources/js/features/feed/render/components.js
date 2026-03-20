import { escapeHtml, formatVideoDurationText, getAuthorInitial, renderAvatarMarkup } from "./template-utils.js";

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

function getReactionTone(type, active, enabled = true, tone = "light") {
  if (!enabled) {
    return tone === "dark"
      ? "border-white/10 bg-white/10 text-white/35"
      : "border-gray-200 bg-gray-100 text-gray-400";
  }

  if (tone === "dark") {
    if (type === "like") {
      return active
        ? "border-rose-300/60 bg-rose-500/20 text-rose-100"
        : "border-white/15 bg-black/25 text-white/85 hover:border-white/30 hover:bg-black/40 hover:text-white";
    }

    return active
      ? "border-amber-300/60 bg-amber-500/20 text-amber-100"
      : "border-white/15 bg-black/25 text-white/85 hover:border-white/30 hover:bg-black/40 hover:text-white";
  }

  if (type === "like") {
    return active
      ? "border-rose-200 bg-rose-50 text-rose-600"
      : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700";
  }

  return active
    ? "border-amber-200 bg-amber-50 text-amber-700"
    : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700";
}

function getMobileStatIconClass(type, active, enabled = true) {
  if (!enabled) {
    return "text-white/35";
  }

  if (!active) {
    return "text-white";
  }

  return type === "like" ? "text-rose-300" : "text-amber-200";
}

function renderHtmlAttributes(attributes = {}) {
  return Object.entries(attributes)
    .flatMap(([name, value]) => {
      if (value === false || value == null || value === "") {
        return [];
      }

      if (value === true) {
        return [escapeHtml(name)];
      }

      return [`${escapeHtml(name)}="${escapeHtml(String(value))}"`];
    })
    .join(" ");
}

export function renderReactionButton({
  type,
  label,
  count,
  active,
  enabled = true,
  tone = "light",
  layout = "pill"
}) {
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

  if (layout === "mobile-stat") {
    return `
      <button
        type="button"
        class="inline-flex min-w-[3.75rem] items-center justify-center gap-2 text-white transition ${enabled ? "opacity-100" : "opacity-40"}"
        data-detail-reaction-button="${type}"
        data-detail-reaction-layout="mobile-stat"
        data-active="${activeAttr}"
        data-enabled="${enabledAttr}"
        data-count="${escapeHtml(String(count))}"
        data-tone="${escapeHtml(tone)}"
        aria-pressed="${activeAttr}"
        aria-label="${safeLabel}"
      >
        <i
          class="${iconClass} ${getMobileStatIconClass(type, active, enabled)} text-[1.85rem] leading-none"
          aria-hidden="true"
          data-detail-reaction-icon
        ></i>
        <span class="text-[0.95rem] font-semibold leading-none tabular-nums text-white/95" data-detail-reaction-count>${safeCount}</span>
      </button>
    `;
  }

  return `
    <button
      type="button"
      class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold transition ${getReactionTone(
        type,
        active,
        enabled,
        tone
      )}"
      data-detail-reaction-button="${type}"
      data-active="${activeAttr}"
      data-enabled="${enabledAttr}"
      data-count="${escapeHtml(String(count))}"
      data-tone="${escapeHtml(tone)}"
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

export function renderCommentButton({ count, tone = "light", layout = "pill" }) {
  if (layout === "mobile-stat") {
    return `
      <button
        type="button"
        class="inline-flex min-w-[3.75rem] items-center justify-center gap-2 text-white transition"
        data-detail-comments-open="true"
        data-tone="${escapeHtml(tone)}"
        aria-label="打开评论"
      >
        <i class="ph ph-chat-circle text-[1.85rem] leading-none text-white" aria-hidden="true"></i>
        <span class="text-[0.95rem] font-semibold leading-none tabular-nums text-white/95" data-detail-comment-button-count>${escapeHtml(formatCompactCount(count))}</span>
      </button>
    `;
  }

  const buttonClass =
    tone === "dark"
      ? "border-white/15 bg-black/25 text-white/85 hover:border-white/30 hover:bg-black/40 hover:text-white"
      : "border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700";

  return `
    <button
      type="button"
      class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold transition ${buttonClass}"
      data-detail-comments-open="true"
      data-tone="${escapeHtml(tone)}"
      aria-label="打开评论"
    >
      <i class="ph ph-chat-circle text-[1.05rem] leading-none" aria-hidden="true"></i>
      <span class="text-xs font-semibold tabular-nums opacity-80" data-detail-comment-button-count>${escapeHtml(formatCompactCount(count))}</span>
    </button>
  `;
}

export function renderEmptyStateCard({
  title = "还没有可展示的视频",
  description = "先在 <code>config/sources.json</code> 启用来源并运行抓取。首页布局已经准备好，一旦有数据就会按瀑布流方式展示出来。",
  body,
  iconClass = "ph ph-magnifying-glass",
  buttonLabel = null,
  buttonHref = null,
  buttonAttributes = {}
} = {}) {
  const descriptionMarkup = description ?? body;
  const normalizedButtonLabel = String(buttonLabel || "").trim();
  const normalizedButtonHref = String(buttonHref || "").trim();
  const buttonTag = normalizedButtonHref ? "a" : "button";
  const buttonMarkup =
    normalizedButtonLabel === ""
      ? ""
      : `
      <div class="mt-6">
        <${buttonTag}
          ${renderHtmlAttributes({
            ...(normalizedButtonHref ? { href: normalizedButtonHref } : { type: "button" }),
            ...buttonAttributes
          })}
          class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-rose-600"
        >
          <span>${escapeHtml(normalizedButtonLabel)}</span>
          <i class="ph ph-arrow-right text-base leading-none" aria-hidden="true"></i>
        </${buttonTag}>
      </div>
    `;

  return `
    <article
      class="feed-grid-item mb-3 inline-flex min-h-[clamp(26rem,58vh,42rem)] w-full flex-col items-center justify-center px-6 py-12 text-center sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-empty-state="true"
    >
      <div class="mx-auto flex items-center justify-center text-[4rem] text-gray-400">
        <i class="${escapeHtml(iconClass)}" aria-hidden="true"></i>
      </div>
      <h2 class="mt-4 text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl">
        ${escapeHtml(title)}
      </h2>
      <p class="mt-4 mx-auto w-full max-w-xl text-sm leading-7 text-gray-500 sm:text-base">
        ${descriptionMarkup}
      </p>
      ${buttonMarkup}
    </article>
  `;
}

export function getAuthorMeta(tweet) {
  const authorHandle = String(tweet.authorHandle || "unknown").trim() || "unknown";
  const rawAuthorName = String(tweet.authorName || "").trim();
  const rawAuthorUsername = String(tweet.authorUsername || "").trim();
  const normalizedHandle = authorHandle.replace(/^@/u, "").toLowerCase();
  const normalizedAuthorName = rawAuthorName.replace(/^@/u, "").toLowerCase();
  const normalizedAuthorUsername = rawAuthorUsername.replace(/^@/u, "").toLowerCase();
  const authorNameLooksLikeHandle =
    normalizedAuthorName !== "" &&
    (normalizedAuthorName === normalizedHandle || normalizedAuthorName === normalizedAuthorUsername);
  const authorName = authorNameLooksLikeHandle
    ? rawAuthorName.replace(/^@/u, "") || rawAuthorUsername.replace(/^@/u, "") || authorHandle
    : rawAuthorName || `@${authorHandle}`;

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
  imageClass = "",
  profileUrl = null
}) {
  const safeName = escapeHtml(authorName);
  const safeHandle = escapeHtml(authorHandle);
  const safeProfileUrl = profileUrl ? escapeHtml(profileUrl) : "";
  const handleMarkup = handleClass
    ? `
        <p class="${handleClass}">@${safeHandle}</p>
      `
    : "";
  const wrapperTag = safeProfileUrl ? "a" : "div";
  const wrapperAttributes = safeProfileUrl
    ? `href="${safeProfileUrl}" class="${wrapperClass} rounded-2xl transition hover:opacity-80 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300"`
    : `class="${wrapperClass}"`;

  return `
    <${wrapperTag} ${wrapperAttributes}>
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
    </${wrapperTag}>
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

export function renderDetailMedia({
  tweet,
  authorName,
  videoClass = "detail-modal-video h-full w-full bg-black object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]",
  imageClass = "max-h-full w-auto max-w-full object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]",
  wrapperClass = "relative flex h-full w-full items-center justify-center",
  showPreviewBadge = true
}) {
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeHlsUrl = escapeHtml(tweet.hlsUrl || "");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeAuthor = escapeHtml(authorName);

  if ((tweet.hlsUrl || tweet.videoUrl) && tweet.status === "resolved") {
    return `
      <video
        class="${videoClass}"
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
    <div class="${wrapperClass}">
      <img
        class="${imageClass}"
        src="${safePoster}"
        alt="${safeAuthor} 的视频封面"
        loading="lazy"
      />
      ${
        showPreviewBadge
          ? `
            <span class="absolute bottom-6 left-6 rounded-full bg-white/10 px-4 py-2 text-xs font-semibold tracking-[0.18em] text-white backdrop-blur-md">
              VIDEO PREVIEW
            </span>
          `
          : ""
      }
    </div>
  `;
}

export function renderDetailComposer({
  engagement,
  canComment = false,
  includeReactions = true,
  containerClass = "border-t border-gray-200 px-5 py-4 sm:px-6",
  formClass = "flex items-center gap-3",
  reactionsClass = "flex shrink-0 items-center gap-2",
  inputClass = "h-12 min-w-0 flex-1 rounded-full bg-gray-100 px-4 text-sm text-gray-900 outline-none placeholder:text-gray-400",
  submitClass = "inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
}) {
  const placeholder = canComment ? "说点什么..." : "登录后参与评论";

  return `
    <div class="${containerClass}">
      <div class="flex flex-col gap-3">
        <div
          class="hidden items-center justify-between gap-3 rounded-[1.25rem] bg-gray-100 px-4 py-3"
          data-detail-comment-reply-state="true"
          hidden
        >
          <div class="min-w-0">
            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-gray-400">正在回复</p>
            <p class="mt-1 truncate text-sm font-medium text-gray-700" data-detail-comment-reply-target="true"></p>
          </div>
          <button
            type="button"
            class="inline-flex h-8 shrink-0 items-center justify-center rounded-full bg-white px-3 text-xs font-semibold text-gray-500 transition hover:bg-gray-200 hover:text-gray-700"
            data-detail-comment-reply-cancel="true"
          >
            取消
          </button>
        </div>
        <form class="${formClass}" data-detail-comment-form="true">
          <input
            type="text"
            name="body"
            maxlength="500"
            class="${inputClass}"
            placeholder="${placeholder}"
            data-default-placeholder="${placeholder}"
            ${canComment ? "" : "disabled"}
            data-detail-comment-input="true"
          />
          <button
            type="submit"
            class="${submitClass}"
            data-detail-comment-submit="true"
          >
            ${canComment ? "发布" : "去登录"}
          </button>
        </form>
        ${
          includeReactions
            ? `
              <div class="${reactionsClass}">
                ${renderReactionButton({
                  type: "like",
                  label: "点赞",
                  count: engagement.likeCount,
                  active: engagement.likedByViewer,
                  enabled: true
                })}
                ${renderReactionButton({
                  type: "bookmark",
                  label: "收藏",
                  count: engagement.bookmarkCount,
                  active: engagement.bookmarkedByViewer,
                  enabled: true
                })}
              </div>
            `
            : ""
        }
      </div>
    </div>
  `;
}
