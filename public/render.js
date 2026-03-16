function escapeHtml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function stripUrls(value) {
  return String(value || "")
    .replaceAll(/https?:\/\/\S+/g, " ")
    .replaceAll(/\s+/g, " ")
    .trim();
}

function getDisplayText(tweet) {
  return stripUrls(tweet?.text) || "未填写内容文案";
}

export function formatFeedDate(value) {
  if (!value) {
    return "未知时间";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "未知时间";
  }

  const now = Date.now();
  const diffMs = now - date.getTime();

  if (diffMs < 0) {
    return "刚刚";
  }

  const minuteMs = 60 * 1000;
  const hourMs = 60 * minuteMs;
  const dayMs = 24 * hourMs;
  const weekMs = 7 * dayMs;
  const monthMs = 30 * dayMs;
  const yearMs = 365 * dayMs;

  if (diffMs < minuteMs) {
    return "刚刚";
  }

  if (diffMs < hourMs) {
    return `${Math.floor(diffMs / minuteMs)}分钟前`;
  }

  if (diffMs < dayMs) {
    return `${Math.floor(diffMs / hourMs)}小时前`;
  }

  if (diffMs < weekMs) {
    return `${Math.floor(diffMs / dayMs)}天前`;
  }

  if (diffMs < monthMs) {
    return `${Math.floor(diffMs / weekMs)}周前`;
  }

  if (diffMs < yearMs) {
    return `${Math.floor(diffMs / monthMs)}个月前`;
  }

  return `${Math.floor(diffMs / yearMs)}年前`;
}

export function formatDetailDate(value) {
  if (!value) {
    return "发布日期待更新";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "发布日期待更新";
  }

  return new Intl.DateTimeFormat("zh-CN", {
    year: "numeric",
    month: "long",
    day: "numeric"
  }).format(date);
}

function formatMonthDay(value) {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "刚刚";
  }

  return new Intl.DateTimeFormat("zh-CN", {
    month: "2-digit",
    day: "2-digit"
  })
    .format(date)
    .replaceAll("/", "-");
}

function getAuthorInitial(value) {
  const normalized = String(value || "").trim().replace(/^@/, "");
  return normalized ? normalized[0].toUpperCase() : "L";
}

function renderAvatarMarkup({
  imageUrl,
  label,
  initial,
  sizeClass,
  fallbackClass = "bg-gray-100 text-gray-700",
  imageClass = ""
}) {
  const safeLabel = escapeHtml(label);
  const safeInitial = escapeHtml(initial);

  if (imageUrl) {
    const safeImageUrl = escapeHtml(imageUrl);
    const imageClasses = `${sizeClass} rounded-full object-cover ring-1 ring-gray-200 ${imageClass}`.trim();
    return `
      <img
        class="${imageClasses}"
        src="${safeImageUrl}"
        alt="${safeLabel} 的头像"
        loading="lazy"
        referrerpolicy="no-referrer"
      />
    `;
  }

  return `
    <span
      class="flex ${sizeClass} items-center justify-center rounded-full ${fallbackClass} text-xs font-semibold"
      aria-hidden="true"
    >
      ${safeInitial}
    </span>
  `;
}

function getMediaFrameClass(tweet) {
  const width = Number(tweet.mediaWidth || 0);
  const height = Number(tweet.mediaHeight || 0);

  if (width > 0 && height > 0) {
    const ratio = width / height;

    if (ratio >= 1.15) {
      return "aspect-[6/5]";
    }

    if (ratio >= 0.92) {
      return "aspect-square";
    }

    if (ratio >= 0.72) {
      return "aspect-[4/5]";
    }

    return "aspect-[3/4]";
  }

  return (tweet.text || "").length > 110 ? "aspect-[3/4]" : "aspect-[4/5]";
}

function formatVideoDurationText(value) {
  const normalized = String(value || "").trim();
  if (!normalized) {
    return "";
  }

  const parts = normalized.split(":").map((part) => part.trim());
  if (parts.length < 2 || parts.length > 3 || parts.some((part) => !/^\d+$/.test(part))) {
    return normalized;
  }

  return parts.map((part, index) => (index === 0 ? String(Number(part)) : part.padStart(2, "0"))).join(":");
}

function getSeed(value) {
  return Array.from(String(value || "")).reduce(
    (sum, character, index) => sum + character.charCodeAt(0) * (index + 17),
    0
  );
}

const COMMENT_NAMES = ["野柚", "一格慢镜", "阿南", "五月列车", "知更", "Kiko", "晚灯", "Luna"];
const COMMENT_BODIES = [
  "这个镜头的节奏很舒服，{topic} 这一段看了两遍。",
  "想知道这种 {topic} 的转场是怎么做的，氛围感很好。",
  "标题和封面是一个气质，点进来没失望。",
  "{topic} 这个主题很适合继续做系列，蹲下一条。",
  "发布时间卡得刚好，晚上刷到会让人想一直往下看。",
  "右边评论区如果真做出来，应该会很热闹。"
];
const COMMENT_AVATAR_TONES = [
  "bg-amber-100 text-amber-700",
  "bg-emerald-100 text-emerald-700",
  "bg-sky-100 text-sky-700",
  "bg-rose-100 text-rose-700",
  "bg-orange-100 text-orange-700"
];

function buildMockComments(tweet) {
  const seed = getSeed(tweet?.tweetId || tweet?.authorHandle || tweet?.text);
  const topic = getDisplayText(tweet).slice(0, 18) || "这个视频";
  const baseDate = new Date(tweet?.postedAt || Date.now());

  return Array.from({ length: 6 }, (_, index) => {
    const date = Number.isNaN(baseDate.getTime()) ? new Date() : new Date(baseDate.getTime());
    date.setDate(date.getDate() + index + 1);

    return {
      author: COMMENT_NAMES[(seed + index) % COMMENT_NAMES.length],
      body: COMMENT_BODIES[(seed * 3 + index) % COMMENT_BODIES.length].replace("{topic}", topic),
      dateLabel: formatMonthDay(date),
      likes: 8 + ((seed + index * 11) % 76),
      tone: COMMENT_AVATAR_TONES[(seed + index) % COMMENT_AVATAR_TONES.length]
    };
  });
}

function renderCommentMarkup(comment) {
  const safeAuthor = escapeHtml(comment.author);
  const safeBody = escapeHtml(comment.body);
  const safeDateLabel = escapeHtml(comment.dateLabel);
  const safeLikes = escapeHtml(String(comment.likes));
  const avatarInitial = escapeHtml(getAuthorInitial(comment.author));

  return `
    <article class="grid gap-3">
      <div class="flex items-start gap-3">
        <span
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${comment.tone} text-xs font-semibold"
          aria-hidden="true"
        >
          ${avatarInitial}
        </span>
        <div class="min-w-0 flex-1">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-gray-900">${safeAuthor}</p>
              <p class="mt-1 text-sm leading-6 text-gray-700">${safeBody}</p>
            </div>
            <button
              type="button"
              class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-300 transition hover:bg-gray-100 hover:text-gray-500"
              aria-label="更多评论操作"
            >
              <i class="ph ph-dots-three text-lg leading-none" aria-hidden="true"></i>
            </button>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-400">
            <span>${safeDateLabel}</span>
            <span>${safeLikes} 赞</span>
            <button type="button" class="font-medium text-gray-500 transition hover:text-gray-900">
              回复
            </button>
          </div>
        </div>
      </div>
    </article>
  `;
}

export function renderFeedItem(tweet) {
  const displayText = getDisplayText(tweet);
  const safeText = escapeHtml(displayText);
  const safeAuthor = escapeHtml(tweet.authorName || `@${tweet.authorHandle || "unknown"}`);
  const safeHandle = escapeHtml(tweet.authorHandle || "unknown");
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeStatus = escapeHtml(tweet.status || "pending");
  const authorInitial = getAuthorInitial(tweet.authorName || tweet.authorHandle);
  const frameClass = getMediaFrameClass(tweet);
  const durationText = formatVideoDurationText(tweet.durationText);
  const safeDurationText = escapeHtml(durationText);
  const mediaMarkup =
    tweet.status === "resolved" && tweet.videoUrl
      ? `
          <video
            class="js-feed-player h-full w-full object-cover"
            data-poster="${safePoster}"
            muted
            loop
            playsinline
            disablepictureinpicture
            preload="metadata"
          >
            <source src="${safeVideoUrl}" />
          </video>
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
      <div class="relative ${frameClass} overflow-hidden bg-gray-100">
        ${mediaMarkup}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-black/5 to-black/10"></div>
        <span
          class="pointer-events-none absolute right-3 top-3 z-10 ${safeDurationText ? "" : "hidden "}rounded-full bg-black/15 px-2.5 py-1.5 text-sm font-semibold leading-none text-white backdrop-blur-sm"
          data-video-duration
        >${safeDurationText}</span>
      </div>
      <div class="grid gap-3 px-4 pb-4 pt-3">
        <p class="line-clamp-2 overflow-hidden text-base font-semibold leading-6 text-gray-900">
          ${safeText}
        </p>
        <div class="flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            ${renderAvatarMarkup({
              imageUrl: tweet.authorAvatarUrl,
              label: tweet.authorName || tweet.authorHandle || "unknown",
              initial: authorInitial,
              sizeClass: "h-7 w-7"
            })}
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-gray-900">${safeAuthor}</p>
            </div>
          </div>
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
  const authorName = tweet.authorName || `@${tweet.authorHandle || "unknown"}`;
  const safeAuthor = escapeHtml(authorName);
  const safeHandle = escapeHtml(tweet.authorHandle || "unknown");
  const authorInitial = getAuthorInitial(authorName);
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeDate = escapeHtml(formatDetailDate(tweet.postedAt));
  const comments = buildMockComments(tweet);
  const commentsMarkup = comments.map(renderCommentMarkup).join("");

  const mediaMarkup =
    tweet.videoUrl && tweet.status === "resolved"
      ? `
          <video
            class="detail-modal-video h-full w-full bg-black object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)]"
            src="${safeVideoUrl}"
            ${safePoster ? `poster="${safePoster}"` : ""}
            autoplay
            muted
            loop
            playsinline
            preload="metadata"
            data-detail-layout-node="true"
            data-detail-player
          ></video>
        `
      : `
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

  return `
    ${mediaMarkup}

    <aside
      class="flex w-full max-w-full flex-col border-t border-gray-200 bg-white xl:w-[430px] xl:max-w-[430px] xl:border-l xl:border-t-0"
      data-detail-layout-node="true"
    >
      <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
        <div class="flex items-start justify-between gap-4">
          <div class="flex min-w-0 items-center gap-3">
            ${renderAvatarMarkup({
              imageUrl: tweet.authorAvatarUrl,
              label: authorName,
              initial: authorInitial,
              sizeClass: "h-12 w-12",
              fallbackClass: "bg-gray-100 text-gray-700"
            })}
            <div class="min-w-0">
              <p class="truncate text-base font-semibold text-gray-950">${safeAuthor}</p>
              <p class="mt-1 truncate text-sm text-gray-500">@${safeHandle}</p>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex h-11 shrink-0 items-center rounded-full bg-rose-500 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-600"
          >
            关注
          </button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto px-5 py-5 sm:px-6 sm:py-6">
        <h2 id="detail-modal-title" class="text-[1.2rem] font-semibold leading-[1.42] text-gray-950 sm:text-[1.35rem]">
          ${safeText}
        </h2>

        <p class="mt-3 text-sm font-normal tracking-[0.02em] text-gray-500">
          发布日期 · ${safeDate}
        </p>

        <div class="my-5 h-px bg-gray-200"></div>

        <section aria-labelledby="detail-comments-title">
          <div class="flex items-end justify-between gap-3">
            <div>
              <h3 id="detail-comments-title" class="text-base font-semibold text-gray-950">
                评论区
              </h3>
            </div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
              ${comments.length} 条示意评论
            </span>
          </div>

          <div class="mt-5 grid gap-5">
            ${commentsMarkup}
          </div>
        </section>
      </div>

      <div class="border-t border-gray-200 px-5 py-4 sm:px-6">
        <div class="flex items-center gap-3">
          <div class="flex h-12 min-w-0 flex-1 items-center rounded-full bg-gray-100 px-4 text-sm text-gray-400">
            说点什么...
          </div>
          <button
            type="button"
            class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gray-100 text-lg text-gray-500 transition hover:bg-gray-200 hover:text-gray-700"
            aria-label="更多操作"
          >
            <i class="ph ph-smiley text-xl leading-none" aria-hidden="true"></i>
          </button>
        </div>
      </div>
    </aside>
  `;
}
