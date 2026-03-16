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
    return "Unknown date";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "Unknown date";
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short"
  }).format(date);
}

export function renderFeedItem(tweet) {
  const safeText = escapeHtml(tweet.text || "Untitled tweet");
  const safeAuthor = escapeHtml(tweet.authorName || `@${tweet.authorHandle || "unknown"}`);
  const safeHandle = escapeHtml(tweet.authorHandle || "unknown");
  const safeSource = escapeHtml(tweet.sourceHandle || "source");
  const safePoster = escapeHtml(tweet.posterUrl || "");
  const safeTweetUrl = escapeHtml(tweet.tweetUrl || "#");
  const safeVideoUrl = escapeHtml(tweet.videoUrl || "");
  const safeStatus = escapeHtml(tweet.status || "pending");
  const mediaMarkup =
    tweet.status === "resolved" && tweet.videoUrl
      ? `
          <video
            class="h-full w-full object-cover"
            src="${safeVideoUrl}"
            poster="${safePoster}"
            muted
            loop
            controls
            playsinline
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

  const actionMarkup =
    tweet.status === "resolved" && tweet.videoUrl
      ? `<span class="text-xs font-medium uppercase tracking-[0.12em] text-[#5e6b72]">Inline playback ready</span>`
      : `<a class="inline-flex min-h-[2.8rem] items-center justify-center rounded-full bg-[#122029] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#1c303b]" href="${safeTweetUrl}" target="_blank" rel="noreferrer">Open on X</a>`;

  return `
    <article
      class="group overflow-hidden rounded-[1.5rem] border border-[#122029]/10 bg-[rgba(255,250,244,0.86)] shadow-glass backdrop-blur-xl transition-transform duration-300 hover:-translate-y-1 animate-card-in"
      data-tweet-id="${escapeHtml(tweet.tweetId)}"
      data-status="${safeStatus}"
    >
      <div class="relative aspect-[9/16] overflow-hidden bg-[linear-gradient(180deg,rgba(18,32,41,0.1),rgba(18,32,41,0.3))]">
        ${mediaMarkup}
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-[#122029]/35 to-transparent"></div>
      </div>
      <div class="grid gap-3 px-4 py-4">
        <div class="flex items-start justify-between gap-3 text-[0.82rem] text-[#5e6b72]">
          <span class="min-w-0 truncate">${safeAuthor} · @${safeHandle}</span>
          <span class="shrink-0">${formatFeedDate(tweet.postedAt)}</span>
        </div>
        <p class="text-[0.98rem] leading-6 text-[#122029]">${safeText}</p>
        <div class="flex items-center justify-between gap-3">
          <span class="inline-flex w-fit items-center rounded-full bg-[#122029]/5 px-3 py-1 text-xs font-medium text-[#122029]">
            @${safeSource}
          </span>
          ${actionMarkup}
        </div>
      </div>
    </article>
  `;
}
