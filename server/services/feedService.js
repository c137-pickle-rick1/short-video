import { mapFeedItemsForPresentation } from "../../shared/feed/feedItemMapper.js";
import { getSourceLabel } from "../../shared/feed/sourceLabel.js";
import { normalizeSourceHandle } from "../config.js";

export const DEFAULT_FEED_LIMIT = 12;
export const MAX_FEED_LIMIT = 24;
export const HOME_PAGE_FEED_LIMIT = 8;

function normalizeFeedLimit(limit, fallback = DEFAULT_FEED_LIMIT) {
  const numericLimit = Number(limit || fallback);
  if (!Number.isFinite(numericLimit)) {
    return fallback;
  }

  return Math.min(Math.max(Math.floor(numericLimit), 1), MAX_FEED_LIMIT);
}

export function getFeedPage({ db, cursor = null, sourceHandle = "", limit = DEFAULT_FEED_LIMIT }) {
  const normalizedSourceHandle = normalizeSourceHandle(sourceHandle);
  const normalizedLimit = normalizeFeedLimit(limit);
  const feed = db.getFeed({
    cursor,
    sourceHandle: normalizedSourceHandle || null,
    limit: normalizedLimit
  });

  return {
    items: mapFeedItemsForPresentation(feed.items),
    nextCursor: feed.nextCursor,
    sourceHandle: normalizedSourceHandle,
    limit: normalizedLimit
  };
}

export function getHomePageViewModel({ db, sourceHandle = "", limit = HOME_PAGE_FEED_LIMIT }) {
  const feed = getFeedPage({
    db,
    cursor: null,
    sourceHandle,
    limit
  });
  const renderedCount = feed.items.length;
  const done = !feed.nextCursor;

  return {
    pageTitle: feed.sourceHandle
      ? `${getSourceLabel(feed.sourceHandle)} · Lagos Explore Feed`
      : "Lagos Explore Feed",
    activeSourceHandle: feed.sourceHandle,
    feed: {
      items: feed.items,
      nextCursor: feed.nextCursor,
      limit: feed.limit,
      renderedCount,
      done,
      isEmpty: renderedCount === 0
    }
  };
}
