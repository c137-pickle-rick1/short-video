export const TWEET_STATUSES = ["pending", "resolved", "external_only", "skipped", "failed"];

export function nowIso() {
  return new Date().toISOString();
}

export function compactJson(value) {
  if (!value) {
    return null;
  }

  const text = typeof value === "string" ? value : JSON.stringify(value);
  if (text.length <= 50000) {
    return text;
  }

  return `${text.slice(0, 49950)}...[truncated]`;
}

export function extractDurationTextFromDiscoveryPayload(rawDiscoveryPayload) {
  if (!rawDiscoveryPayload) {
    return null;
  }

  const payload =
    typeof rawDiscoveryPayload === "string"
      ? (() => {
          try {
            return JSON.parse(rawDiscoveryPayload);
          } catch {
            return null;
          }
        })()
      : rawDiscoveryPayload;

  const durationText = payload?.durationText || payload?.discoveredLink?.durationText || null;
  return typeof durationText === "string" && durationText.trim() ? durationText.trim() : null;
}
