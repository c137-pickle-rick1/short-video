function escapeHtml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

export function formatFeedDate(value) {
  if (!value) {
    return "未知时间";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "未知时间";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short"
  }).format(date);
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

export function renderFeedItem(tweet) {
  const safeText = escapeHtml(tweet.text || "未填写内容文案");
  const safeAuthor = escapeHtml(tweet.authorName || `@${tweet.authorHandle || "unknown"}`);
  const safeHandle = escapeHtml(tweet.authorHandle || "unknown");
  const safeSource = escapeHtml(tweet.sourceHandle || "source");
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeTweetUrl = escapeHtml(tweet.tweetUrl || "#");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeStatus = escapeHtml(tweet.status || "pending");
  const authorInitial = escapeHtml(getAuthorInitial(tweet.authorName || tweet.authorHandle));
  const frameClass = getMediaFrameClass(tweet);
  const statusLabel = tweet.status === "resolved" && tweet.videoUrl ? "本地直放" : "外链回退";
  const behaviorLabel = tweet.status === "resolved" && tweet.videoUrl ? "悬停播放预览" : "点击跳转查看";
  const mediaMarkup =
    tweet.status === "resolved" && tweet.videoUrl
      ? `
          <video
            class="h-full w-full object-cover"
            src="${safeVideoUrl}"
            poster="${safePoster}"
            muted
            loop
            playsinline
            disablepictureinpicture
            preload="metadata"
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
    <article
      class="group mb-5 inline-block w-full overflow-hidden rounded-[1.55rem] border border-white/70 bg-[rgba(255,250,246,0.95)] shadow-[0_24px_48px_rgba(58,27,12,0.10)] backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:shadow-[0_26px_58px_rgba(58,27,12,0.15)] animate-card-in"
      data-tweet-id="${escapeHtml(tweet.tweetId)}"
      data-status="${safeStatus}"
    >
      <div class="relative ${frameClass} overflow-hidden bg-[#efe6de]">
        ${mediaMarkup}
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(33,27,23,0.02)_0%,rgba(33,27,23,0.12)_100%)]"></div>
        <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-[#fff4ef] px-2.5 py-1 text-[0.68rem] font-semibold tracking-[0.08em] text-[#d14d34]">
              ${statusLabel}
            </span>
            <span class="rounded-full bg-[#211b17]/72 px-2.5 py-1 text-[0.68rem] font-medium text-white/92">
              @${safeSource}
            </span>
          </div>
          <a
            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/88 text-base text-[#211b17] shadow-[0_10px_24px_rgba(33,27,23,0.12)] transition hover:bg-white"
            href="${safeTweetUrl}"
            target="_blank"
            rel="noreferrer"
            aria-label="查看原帖"
          >
            ↗
          </a>
        </div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-[#211b17]/42 to-transparent"></div>
      </div>
      <div class="grid gap-3 px-4 pb-4 pt-3">
        <p class="overflow-hidden text-[1rem] font-semibold leading-6 text-[#211b17] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]">
          ${safeText}
        </p>
        <div class="flex items-center gap-2 text-[0.78rem] text-[#8b7768]">
          <span>${formatFeedDate(tweet.postedAt)}</span>
          <span class="h-1 w-1 rounded-full bg-current/70"></span>
          <span>${behaviorLabel}</span>
        </div>
        <div class="flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#fde9e0] text-sm font-semibold text-[#d14d34]">
              ${authorInitial}
            </span>
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-[#211b17]">${safeAuthor}</p>
              <p class="truncate text-xs text-[#8b7768]">@${safeHandle}</p>
            </div>
          </div>
          <a
            class="inline-flex min-h-[2.7rem] items-center justify-center rounded-full bg-[#211b17] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#342a24]"
            href="${safeTweetUrl}"
            target="_blank"
            rel="noreferrer"
          >
            查看原帖
          </a>
        </div>
      </div>
    </article>
  `;
}
