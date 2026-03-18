import { createAppError } from "./errors.js";

const DEFAULT_BASE_URL = "https://api.x.com/2";
const DEFAULT_TIMEOUT_MS = 15000;
const VIDEO_MEDIA_TYPES = new Set(["video", "animated_gif"]);
const HLS_CONTENT_TYPES = new Set([
  "application/x-mpegURL",
  "application/vnd.apple.mpegurl"
]);

function stripUrls(value) {
  return String(value || "")
    .replaceAll(/https?:\/\/\S+/g, " ")
    .replaceAll(/\s+/g, " ")
    .trim();
}

function normalizeAvatarUrl(value) {
  if (!value || typeof value !== "string") {
    return null;
  }

  return value
    .replace(/^http:\/\//i, "https://")
    .replace(/_normal(\.(?:jpg|jpeg|png|webp))(?=$|\?)/i, "_400x400$1");
}

function normalizeDate(value) {
  if (!value) {
    return null;
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return date.toISOString();
}

export function formatDurationText(durationMs) {
  const totalSeconds = Math.floor(Number(durationMs || 0) / 1000);
  if (!Number.isFinite(totalSeconds) || totalSeconds <= 0) {
    return null;
  }

  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
  }

  return `${minutes}:${String(seconds).padStart(2, "0")}`;
}

function buildApiUrl(baseUrl, path, params = {}) {
  const normalizedBaseUrl = baseUrl.endsWith("/") ? baseUrl : `${baseUrl}/`;
  const url = new URL(path.replace(/^\//, ""), normalizedBaseUrl);

  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === "") {
      continue;
    }

    url.searchParams.set(key, String(value));
  }

  return url;
}

function compareTweetIds(left, right) {
  const normalizedLeft = String(left || "").trim();
  const normalizedRight = String(right || "").trim();

  if (!normalizedLeft && !normalizedRight) {
    return 0;
  }

  if (!normalizedLeft) {
    return -1;
  }

  if (!normalizedRight) {
    return 1;
  }

  if (/^\d+$/.test(normalizedLeft) && /^\d+$/.test(normalizedRight)) {
    const leftValue = BigInt(normalizedLeft);
    const rightValue = BigInt(normalizedRight);

    if (leftValue === rightValue) {
      return 0;
    }

    return leftValue > rightValue ? 1 : -1;
  }

  return normalizedLeft.localeCompare(normalizedRight);
}

function maxTweetId(currentTweetId, candidateTweetId) {
  return compareTweetIds(candidateTweetId, currentTweetId) > 0 ? candidateTweetId : currentTweetId;
}

function buildMediaMaps(includes = {}) {
  return {
    mediaByKey: new Map(
      (Array.isArray(includes.media) ? includes.media : [])
        .filter((media) => media?.media_key)
        .map((media) => [String(media.media_key), media])
    ),
    usersById: new Map(
      (Array.isArray(includes.users) ? includes.users : [])
        .filter((user) => user?.id)
        .map((user) => [String(user.id), user])
    )
  };
}

function collectAttachedMedia(tweet, includes = {}) {
  const { mediaByKey } = buildMediaMaps(includes);
  const mediaKeys = Array.isArray(tweet?.attachments?.media_keys) ? tweet.attachments.media_keys : [];

  return mediaKeys
    .map((mediaKey) => mediaByKey.get(String(mediaKey)))
    .filter((media) => media && typeof media === "object");
}

function normalizeMediaVariants(mediaNodes = []) {
  const mp4Variants = [];
  const hlsVariants = [];
  let hasVideoLikeMedia = false;
  let posterUrl = null;
  let durationMs = null;

  for (const media of mediaNodes) {
    if (!VIDEO_MEDIA_TYPES.has(String(media?.type || ""))) {
      continue;
    }

    hasVideoLikeMedia = true;
    posterUrl = posterUrl || media?.preview_image_url || media?.url || null;

    const rawDurationMs = Number(media?.duration_ms);
    if (Number.isFinite(rawDurationMs) && rawDurationMs > 0) {
      durationMs = durationMs === null ? rawDurationMs : Math.max(durationMs, rawDurationMs);
    }

    const width = Number.isFinite(Number(media?.width)) ? Number(media.width) : null;
    const height = Number.isFinite(Number(media?.height)) ? Number(media.height) : null;
    const variants = Array.isArray(media?.variants) ? media.variants : [];

    for (const variant of variants) {
      if (!variant?.url) {
        continue;
      }

      const bitrateValue = Number(variant?.bit_rate ?? variant?.bitrate);
      const normalizedVariant = {
        url: String(variant.url),
        bitrate: Number.isFinite(bitrateValue) ? bitrateValue : null,
        contentType: variant?.content_type || null,
        width,
        height
      };

      if (normalizedVariant.contentType === "video/mp4") {
        mp4Variants.push(normalizedVariant);
        continue;
      }

      if (HLS_CONTENT_TYPES.has(String(normalizedVariant.contentType || ""))) {
        hlsVariants.push(normalizedVariant);
      }
    }
  }

  mp4Variants.sort((left, right) => {
    const leftBitrate = left.bitrate ?? -1;
    const rightBitrate = right.bitrate ?? -1;
    return rightBitrate - leftBitrate;
  });

  const orderedVariants = [...mp4Variants, ...hlsVariants];
  const mediaAssets = orderedVariants.map((variant, index) => ({
    ...variant,
    sortOrder: index,
    isPrimary: index === 0 && variant.contentType === "video/mp4"
  }));

  return {
    hasVideoLikeMedia,
    mediaAssets,
    posterUrl,
    durationMs
  };
}

function fallbackAuthorRecord(fallback = {}) {
  if (!fallback || typeof fallback !== "object") {
    return null;
  }

  const username = String(
    fallback.username || fallback.handle || fallback.authorHandle || fallback.sourceHandle || ""
  ).trim();

  if (!username) {
    return null;
  }

  return {
    id: fallback.id ? String(fallback.id) : null,
    username,
    name: fallback.name || fallback.authorName || null,
    profile_image_url: fallback.profile_image_url || fallback.authorAvatarUrl || null
  };
}

export function normalizeApiTweet(tweet, includes = {}, fallbackAuthor = null) {
  if (!tweet || typeof tweet !== "object") {
    return null;
  }

  const tweetId = String(tweet.id || "").trim();
  if (!tweetId) {
    return null;
  }

  const { usersById } = buildMediaMaps(includes);
  const attachedMedia = collectAttachedMedia(tweet, includes);
  const media = normalizeMediaVariants(attachedMedia);
  const author =
    usersById.get(String(tweet.author_id || "")) || fallbackAuthorRecord(fallbackAuthor) || null;
  const authorHandle = author?.username || null;
  const authorName = author?.name || null;
  const authorAvatarUrl = normalizeAvatarUrl(author?.profile_image_url || null);
  const tweetUrl = authorHandle ? `https://x.com/${authorHandle}/status/${tweetId}` : null;

  return {
    tweetId,
    tweetUrl,
    authorHandle,
    authorName,
    authorAvatarUrl,
    text: stripUrls(tweet?.text || ""),
    postedAt: normalizeDate(tweet?.created_at || null),
    posterUrl: media.posterUrl,
    durationText: formatDurationText(media.durationMs),
    mediaAssets: media.mediaAssets,
    hasVideoLikeMedia: media.hasVideoLikeMedia
  };
}

function buildResolveResult(tweetId, normalizedTweet, source = "x_api") {
  if (!normalizedTweet) {
    return {
      tweetId: String(tweetId),
      status: "failed",
      errorCode: "resolve_failed",
      errorMessage: `X API did not return tweet ${tweetId}`,
      rawPayload: {
        source,
        tweetId: String(tweetId)
      }
    };
  }

  if (normalizedTweet.mediaAssets.length > 0) {
    return {
      tweetId: normalizedTweet.tweetId,
      status: "resolved",
      tweet: normalizedTweet,
      mediaAssets: normalizedTweet.mediaAssets,
      rawPayload: {
        source,
        tweetId: normalizedTweet.tweetId
      }
    };
  }

  if (normalizedTweet.hasVideoLikeMedia) {
    return {
      tweetId: normalizedTweet.tweetId,
      status: "external_only",
      tweet: normalizedTweet,
      mediaAssets: [],
      rawPayload: {
        source,
        tweetId: normalizedTweet.tweetId
      }
    };
  }

  return {
    tweetId: normalizedTweet.tweetId,
    status: "skipped",
    tweet: normalizedTweet,
    mediaAssets: [],
    rawPayload: {
      source,
      tweetId: normalizedTweet.tweetId
    }
  };
}

function extractErrorTweetId(error = {}) {
  const candidate = error?.resource_id ?? error?.value ?? error?.id ?? null;
  if (candidate === null || candidate === undefined) {
    return null;
  }

  return String(candidate);
}

function errorMessageFromPayload(payload, fallbackMessage) {
  if (Array.isArray(payload?.errors) && payload.errors.length > 0) {
    const [firstError] = payload.errors;
    return firstError?.detail || firstError?.message || firstError?.title || fallbackMessage;
  }

  return payload?.detail || payload?.message || payload?.title || fallbackMessage;
}

export class XApiClient {
  constructor({
    bearerToken = "",
    fetchImpl = fetch,
    logger = console,
    baseUrl = DEFAULT_BASE_URL,
    maxPages = 4,
    pageSize = 100,
    includeReplies = false,
    includeRetweets = false,
    timeoutMs = DEFAULT_TIMEOUT_MS
  } = {}) {
    this.bearerToken = String(bearerToken || "").trim();
    this.fetchImpl = fetchImpl;
    this.logger = logger;
    this.baseUrl = String(baseUrl || DEFAULT_BASE_URL).trim().replace(/\/$/, "");
    this.maxPages = Number.isFinite(Number(maxPages)) ? Math.max(1, Number(maxPages)) : 4;
    this.pageSize = Number.isFinite(Number(pageSize))
      ? Math.min(100, Math.max(5, Number(pageSize)))
      : 100;
    this.includeReplies = Boolean(includeReplies);
    this.includeRetweets = Boolean(includeRetweets);
    this.timeoutMs = Number.isFinite(Number(timeoutMs))
      ? Math.max(1000, Number(timeoutMs))
      : DEFAULT_TIMEOUT_MS;
  }

  requireToken() {
    if (!this.bearerToken) {
      throw createAppError(
        "api_auth_failed",
        "Missing X API bearer token. Set X_API_BEARER_TOKEN before using official API mode."
      );
    }
  }

  async request(path, params = {}, defaultErrorCode = "x_api_failed") {
    this.requireToken();

    const url = buildApiUrl(this.baseUrl, path, params);
    const response = await this.fetchImpl(url, {
      headers: {
        accept: "application/json",
        authorization: `Bearer ${this.bearerToken}`
      },
      signal: AbortSignal.timeout(this.timeoutMs)
    });

    const payload = await response.json().catch(() => null);

    if (response.status === 429) {
      throw createAppError(
        "rate_limited",
        errorMessageFromPayload(payload, `X API rate limited request to ${url.pathname}`)
      );
    }

    if (response.status === 401) {
      throw createAppError(
        "api_auth_failed",
        errorMessageFromPayload(payload, "X API authentication failed")
      );
    }

    if (response.status === 403) {
      throw createAppError(
        "api_access_denied",
        errorMessageFromPayload(payload, "X API access was denied")
      );
    }

    if (!response.ok) {
      throw createAppError(
        defaultErrorCode,
        errorMessageFromPayload(payload, `X API request failed with HTTP ${response.status}`),
        {
          status: response.status,
          payload
        }
      );
    }

    if (!payload || typeof payload !== "object") {
      throw createAppError(defaultErrorCode, "X API returned invalid JSON");
    }

    return payload;
  }

  async fetchUserByHandle(handle) {
    const payload = await this.request(
      `/users/by/username/${encodeURIComponent(handle)}`,
      {
        "user.fields": "id,name,profile_image_url,username"
      },
      "discovery_failed"
    );

    if (!payload?.data?.id) {
      throw createAppError(
        "discovery_failed",
        errorMessageFromPayload(payload, `X API did not return a user for @${handle}`)
      );
    }

    return payload.data;
  }

  timelineRequestParams({ paginationToken = null, sinceId = null } = {}) {
    const excludedTypes = [];

    if (!this.includeReplies) {
      excludedTypes.push("replies");
    }

    if (!this.includeRetweets) {
      excludedTypes.push("retweets");
    }

    return {
      max_results: this.pageSize,
      pagination_token: paginationToken,
      since_id: sinceId,
      expansions: "attachments.media_keys,author_id",
      "tweet.fields": "attachments,author_id,created_at,text",
      "media.fields": "duration_ms,height,media_key,preview_image_url,type,url,variants,width",
      "user.fields": "id,name,profile_image_url,username",
      exclude: excludedTypes.length > 0 ? excludedTypes.join(",") : null
    };
  }

  async discoverSource(handle, { sourceUserId = null, sinceId = null } = {}) {
    const normalizedHandle = String(handle || "").trim().replace(/^@/, "").toLowerCase();
    if (!normalizedHandle) {
      throw createAppError("invalid_arguments", "Missing source handle for X API discovery");
    }

    const normalizedSourceUserId = String(sourceUserId || "").trim();
    const normalizedSinceId = String(sinceId || "").trim();
    const user = normalizedSourceUserId
      ? {
          id: normalizedSourceUserId,
          username: normalizedHandle,
          name: null,
          profile_image_url: null
        }
      : await this.fetchUserByHandle(normalizedHandle);
    const discovered = [];
    let paginationToken = null;
    let pagesFetched = 0;
    let latestTweetId = null;

    while (pagesFetched < this.maxPages) {
      const payload = await this.request(
        `/users/${encodeURIComponent(String(user.id))}/tweets`,
        this.timelineRequestParams({
          paginationToken,
          sinceId: normalizedSinceId || null
        }),
        "discovery_failed"
      );

      const includes = payload?.includes || {};
      const tweets = Array.isArray(payload?.data) ? payload.data : [];

      for (const tweet of tweets) {
        latestTweetId = maxTweetId(latestTweetId, String(tweet?.id || "").trim() || null);
      }

      for (const tweet of tweets) {
        const normalizedTweet = normalizeApiTweet(tweet, includes, user);
        if (!normalizedTweet?.hasVideoLikeMedia) {
          continue;
        }

        discovered.push({
          tweetId: normalizedTweet.tweetId,
          tweetUrl: normalizedTweet.tweetUrl || `https://x.com/${normalizedHandle}/status/${tweet.id}`,
          durationText: normalizedTweet.durationText,
          rawDiscoveryPayload: {
            sourceHandle: normalizedHandle,
            method: "x-api-timeline",
            userId: String(user.id),
            page: pagesFetched + 1,
            tweetId: normalizedTweet.tweetId,
            mediaKeys: Array.isArray(tweet?.attachments?.media_keys) ? tweet.attachments.media_keys : [],
            posterUrl: normalizedTweet.posterUrl,
            durationText: normalizedTweet.durationText
          }
        });
      }

      pagesFetched += 1;
      paginationToken = payload?.meta?.next_token || null;
      if (!paginationToken) {
        break;
      }
    }

    return {
      items: discovered,
      rawPayload: {
        sourceHandle: normalizedHandle,
        method: "x-api-timeline",
        userId: String(user.id),
        pagesFetched,
        sinceId: normalizedSinceId || null,
        latestTweetId,
        itemsFound: discovered.length
      }
    };
  }

  async resolveTweets(tweetRecords = []) {
    if (!Array.isArray(tweetRecords) || tweetRecords.length === 0) {
      return [];
    }

    const resultsById = new Map();

    for (let index = 0; index < tweetRecords.length; index += 100) {
      const batch = tweetRecords.slice(index, index + 100);
      const ids = batch
        .map((tweetRecord) => String(tweetRecord?.tweetId || "").trim())
        .filter(Boolean);

      if (ids.length === 0) {
        continue;
      }

      const payload = await this.request(
        "/tweets",
        {
          ids: ids.join(","),
          expansions: "attachments.media_keys,author_id",
          "tweet.fields": "attachments,author_id,created_at,text",
          "media.fields": "duration_ms,height,media_key,preview_image_url,type,url,variants,width",
          "user.fields": "id,name,profile_image_url,username"
        },
        "resolve_failed"
      );

      const includes = payload?.includes || {};
      const tweetsById = new Map(
        (Array.isArray(payload?.data) ? payload.data : [])
          .filter((tweet) => tweet?.id)
          .map((tweet) => [String(tweet.id), tweet])
      );
      const errorsById = new Map(
        (Array.isArray(payload?.errors) ? payload.errors : [])
          .map((error) => [extractErrorTweetId(error), error])
          .filter(([tweetId]) => tweetId)
      );

      for (const tweetRecord of batch) {
        const tweetId = String(tweetRecord?.tweetId || "").trim();
        if (!tweetId) {
          continue;
        }

        const fallbackAuthor = fallbackAuthorRecord({
          sourceHandle: tweetRecord?.sourceHandle || null
        });
        const tweet = tweetsById.get(tweetId);
        const normalizedTweet = normalizeApiTweet(tweet, includes, fallbackAuthor);

        if (normalizedTweet) {
          resultsById.set(tweetId, buildResolveResult(tweetId, normalizedTweet));
          continue;
        }

        const apiError = errorsById.get(tweetId);
        if (apiError) {
          resultsById.set(tweetId, {
            tweetId,
            status: "failed",
            errorCode: "resolve_failed",
            errorMessage:
              apiError.detail || apiError.message || apiError.title || `X API did not return tweet ${tweetId}`,
            rawPayload: {
              source: "x_api",
              tweetId
            }
          });
          continue;
        }

        resultsById.set(tweetId, buildResolveResult(tweetId, null));
      }
    }

    return tweetRecords.map((tweetRecord) => {
      const tweetId = String(tweetRecord?.tweetId || "").trim();
      return (
        resultsById.get(tweetId) || {
          tweetId,
          status: "failed",
          errorCode: "resolve_failed",
          errorMessage: `X API did not return tweet ${tweetId}`,
          rawPayload: {
            source: "x_api",
            tweetId
          }
        }
      );
    });
  }
}
