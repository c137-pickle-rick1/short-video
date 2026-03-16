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

function getAuthorInitial(value) {
  const normalized = String(value || "").trim().replace(/^@/, "");
  return normalized ? normalized[0].toUpperCase() : "L";
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

export function renderFeedItem(tweet) {
  const displayText = stripUrls(tweet.text) || "未填写内容文案";
  const safeText = escapeHtml(displayText);
  const safeAuthor = escapeHtml(tweet.authorName || `@${tweet.authorHandle || "unknown"}`);
  const safeHandle = escapeHtml(tweet.authorHandle || "unknown");
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeStatus = escapeHtml(tweet.status || "pending");
  const safeAuthorAvatarUrl = escapeHtml(tweet.authorAvatarUrl || "");
  const authorInitial = escapeHtml(getAuthorInitial(tweet.authorName || tweet.authorHandle));
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
      class="feed-grid-item group mb-3 inline-block w-full overflow-hidden rounded-3xl border border-gray-200 bg-white/95 shadow-sm backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:shadow-md sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
      data-tweet-id="${escapeHtml(tweet.tweetId)}"
      data-status="${safeStatus}"
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
            ${
              safeAuthorAvatarUrl
                ? `
                    <img
                      class="h-7 w-7 rounded-full object-cover ring-1 ring-gray-200"
                      src="${safeAuthorAvatarUrl}"
                      alt="${safeAuthor} 的头像"
                      loading="lazy"
                      referrerpolicy="no-referrer"
                    />
                  `
                : `
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">
                      ${authorInitial}
                    </span>
                  `
            }
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
