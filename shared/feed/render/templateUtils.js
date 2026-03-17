export function escapeHtml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

export function stripUrls(value) {
  return String(value || "")
    .replaceAll(/https?:\/\/\S+/g, " ")
    .replaceAll(/\s+/g, " ")
    .trim();
}

export function getDisplayText(tweet) {
  return stripUrls(tweet?.text) || "未填写内容文案";
}

export function formatMonthDay(value) {
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

export function getAuthorInitial(value) {
  const normalized = String(value || "").trim().replace(/^@/, "");
  return normalized ? normalized[0].toUpperCase() : "L";
}

export function renderAvatarMarkup({
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

export function getMediaFrameClass(tweet) {
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

export function formatVideoDurationText(value) {
  const normalized = String(value || "").trim();
  if (!normalized) {
    return "";
  }

  const parts = normalized.split(":").map((part) => part.trim());
  if (parts.length < 2 || parts.length > 3 || parts.some((part) => !/^\d+$/.test(part))) {
    return normalized;
  }

  return parts
    .map((part, index) => (index === 0 ? String(Number(part)) : part.padStart(2, "0")))
    .join(":");
}

export function getSeed(value) {
  return Array.from(String(value || "")).reduce(
    (sum, character, index) => sum + character.charCodeAt(0) * (index + 17),
    0
  );
}
