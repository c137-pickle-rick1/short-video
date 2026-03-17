import fs from "node:fs";

import { chromium } from "playwright";
import { createAppError } from "../errors.js";

const STATUS_PATH_RE = /^\/([A-Za-z0-9_]+)\/status\/([0-9]+)(?:\/([^/?#]+)\/?([^?#/]+)?)?\/?$/;
const VIDEO_STATUS_PATH_RE =
  /^\/([A-Za-z0-9_]+)\/status\/([0-9]+)\/video\/([0-9]+)\/?$/;

function stripUrls(value) {
  return String(value || "")
    .replaceAll(/https?:\/\/\S+/g, " ")
    .replaceAll(/\s+/g, " ")
    .trim();
}

function normalizeStatusCandidate(candidate) {
  if (!candidate || typeof candidate !== "string") {
    return null;
  }

  try {
    const parsed = new URL(candidate, "https://x.com");
    if (!/(?:^|\.)?(?:x|twitter)\.com$/i.test(parsed.hostname)) {
      return null;
    }

    parsed.hash = "";
    parsed.search = "";

    const statusMatch = parsed.pathname.match(STATUS_PATH_RE);
    if (!statusMatch) {
      return null;
    }

    const handle = statusMatch[1];
    const tweetId = statusMatch[2];
    const segment = statusMatch[3] || null;
    const segmentValue = statusMatch[4] || null;
    const normalizedTweetUrl = `https://x.com/${handle}/status/${tweetId}`;

    return {
      handle,
      tweetId,
      tweetUrl: normalizedTweetUrl,
      normalizedUrl: parsed.toString().replace(/\/$/, ""),
      pathname: parsed.pathname.replace(/\/$/, ""),
      segment,
      segmentValue
    };
  } catch {
    return null;
  }
}

export function extractMediaGridVideoLinks(entries = [], expectedHandle = null) {
  const normalizedExpected = expectedHandle ? expectedHandle.toLowerCase() : null;
  const seenIds = new Set();
  const items = [];

  for (const entry of entries) {
    const href = typeof entry === "string" ? entry : entry?.href;
    const normalized = normalizeStatusCandidate(href);
    if (!normalized) {
      continue;
    }

    if (!VIDEO_STATUS_PATH_RE.test(normalized.pathname)) {
      continue;
    }

    if (normalizedExpected && normalized.handle.toLowerCase() !== normalizedExpected) {
      continue;
    }

    if (seenIds.has(normalized.tweetId)) {
      continue;
    }

    seenIds.add(normalized.tweetId);
    items.push({
      tweetId: normalized.tweetId,
      tweetUrl: normalized.tweetUrl,
      handle: normalized.handle.toLowerCase(),
      discoveredUrl: normalized.normalizedUrl,
      durationText: typeof entry === "object" ? entry.durationText || null : null,
      text: typeof entry === "object" ? entry.text || null : null
    });
  }

  return items;
}

function walkObjects(value, visitor, seen = new WeakSet()) {
  if (!value || typeof value !== "object") {
    return;
  }

  if (seen.has(value)) {
    return;
  }

  seen.add(value);
  visitor(value);

  if (Array.isArray(value)) {
    for (const item of value) {
      walkObjects(item, visitor, seen);
    }
    return;
  }

  for (const child of Object.values(value)) {
    walkObjects(child, visitor, seen);
  }
}

function unwrapTweetNode(node) {
  let current = node;

  if (current?.tweet_results?.result) {
    current = current.tweet_results.result;
  }

  if (current?.result && typeof current.result === "object") {
    current = current.result;
  }

  if (current?.tweet && typeof current.tweet === "object") {
    current = current.tweet;
  }

  if (current?.legacy || current?.rest_id || current?.id_str) {
    return current;
  }

  return null;
}

function findTweetNodes(payload) {
  const candidates = [];

  walkObjects(payload, (node) => {
    const tweetNode = unwrapTweetNode(node);
    if (tweetNode) {
      candidates.push(tweetNode);
    }
  });

  return candidates;
}

function getUserNode(tweetNode) {
  return (
    tweetNode?.core?.user_results?.result ||
    tweetNode?.core?.user ||
    tweetNode?.author ||
    null
  );
}

function getLegacy(tweetNode) {
  return tweetNode?.legacy || tweetNode?.tweet?.legacy || null;
}

function getFullText(tweetNode, legacy) {
  const noteText =
    tweetNode?.note_tweet?.note_tweet_results?.result?.text ||
    tweetNode?.note_tweet?.note_tweet_results?.result?.richtext ||
    null;

  return stripUrls(noteText || legacy?.full_text || legacy?.text || "");
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

function normalizeAvatarUrl(value) {
  if (!value || typeof value !== "string") {
    return null;
  }

  const normalized = value
    .replace(/^http:\/\//i, "https://")
    .replace(/_normal(\.(?:jpg|jpeg|png|webp))(?=$|\?)/i, "_400x400$1");

  return normalized || null;
}

export function extractVideoVariants(tweetNode) {
  const legacy = getLegacy(tweetNode);
  const mediaNodes = legacy?.extended_entities?.media || legacy?.entities?.media || [];
  const posterUrl =
    mediaNodes.find((media) => media?.media_url_https || media?.media_url)?.media_url_https ||
    mediaNodes.find((media) => media?.media_url)?.media_url ||
    null;

  const mp4Variants = [];
  const hlsVariants = [];
  let hasVideoLikeMedia = false;

  for (const media of mediaNodes) {
    const type = media?.type;
    if (type !== "video" && type !== "animated_gif") {
      continue;
    }

    hasVideoLikeMedia = true;
    const width = media?.original_info?.width || null;
    const height = media?.original_info?.height || null;
    const variants = Array.isArray(media?.video_info?.variants) ? media.video_info.variants : [];

    for (const variant of variants) {
      if (!variant?.url) {
        continue;
      }

      const normalizedVariant = {
        url: variant.url,
        bitrate: Number.isFinite(variant.bitrate) ? variant.bitrate : null,
        contentType: variant.content_type || null,
        width,
        height
      };

      if (variant.content_type === "video/mp4") {
        mp4Variants.push(normalizedVariant);
        continue;
      }

      if (
        variant.content_type === "application/x-mpegURL" ||
        variant.content_type === "application/vnd.apple.mpegurl"
      ) {
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
    posterUrl
  };
}

export function selectTweetFromPayload(payload, tweetId) {
  const nodes = findTweetNodes(payload);
  const exact = nodes.find((node) => {
    const restId = node?.rest_id || node?.id_str || getLegacy(node)?.id_str;
    return String(restId || "") === String(tweetId);
  });

  return exact || null;
}

export function normalizeResolvedTweet(tweetNode, fallbackTweetId = null) {
  const legacy = getLegacy(tweetNode);
  const restId = tweetNode?.rest_id || tweetNode?.id_str || legacy?.id_str || fallbackTweetId;
  const userNode = getUserNode(tweetNode);
  const authorHandle =
    userNode?.legacy?.screen_name || userNode?.core?.screen_name || userNode?.screen_name || null;
  const authorName =
    userNode?.legacy?.name || userNode?.core?.name || userNode?.name || null;
  const authorAvatarUrl = normalizeAvatarUrl(
    userNode?.legacy?.profile_image_url_https ||
      userNode?.legacy?.profile_image_url ||
      userNode?.avatar?.image_url ||
      null
  );
  const tweetUrl = authorHandle ? `https://x.com/${authorHandle}/status/${restId}` : null;
  const text = getFullText(tweetNode, legacy);
  const postedAt = normalizeDate(legacy?.created_at);
  const video = extractVideoVariants(tweetNode);

  return {
    tweetId: String(restId),
    tweetUrl,
    authorHandle,
    authorName,
    authorAvatarUrl,
    text,
    postedAt,
    posterUrl: video.posterUrl,
    mediaAssets: video.mediaAssets,
    hasVideoLikeMedia: video.hasVideoLikeMedia
  };
}

function summarizePayload(rawPayloads) {
  return rawPayloads.slice(0, 4).map((entry) => ({
    url: entry.url,
    match: entry.match
  }));
}

async function getPageSnapshot(page) {
  const [currentUrl, bodyText] = await Promise.all([
    Promise.resolve(page.url()),
    page.locator("body").innerText().catch(() => "")
  ]);

  return {
    currentUrl,
    bodyText
  };
}

function checkPageAccess({ currentUrl, bodyText, sawRateLimit, action, handleOrUrl }) {
  if (sawRateLimit) {
    throw createAppError("rate_limited", `X rate limited while ${action} ${handleOrUrl}`);
  }

  if (/\/login/.test(currentUrl) || /log in|sign up|join today/i.test(bodyText)) {
    throw createAppError(
      "auth_required",
      `X login is required before ${action} ${handleOrUrl}`
    );
  }
}

async function collectMediaGridEntries(page) {
  return page.evaluate(() => {
    const anchors = Array.from(document.querySelectorAll('a[href*="/status/"][href*="/video/"]'));
    const entries = anchors.map((anchor) => ({
      href: anchor.getAttribute("href"),
      text: (anchor.textContent || "").trim().slice(0, 280),
      durationText:
        Array.from(anchor.querySelectorAll("span"))
          .map((node) => (node.textContent || "").trim())
          .find((value) => /^[0-9]{1,2}:[0-9]{2}(?::[0-9]{2})?$/.test(value)) || null
    }));

    return {
      entries,
      scrollHeight: Math.max(
        document.body?.scrollHeight || 0,
        document.documentElement?.scrollHeight || 0
      )
    };
  });
}

function loadSanitizedStorageState(storageStatePath) {
  const raw = JSON.parse(fs.readFileSync(storageStatePath, "utf8"));
  const allowedDomain = /(^|\.)((x|twitter)\.com|twimg\.com)$/i;

  return {
    cookies: (raw.cookies || [])
      .filter((cookie) => allowedDomain.test(cookie.domain || ""))
      .map((cookie) => ({
        name: cookie.name,
        value: cookie.value,
        domain: cookie.domain,
        path: cookie.path,
        expires: cookie.expires,
        httpOnly: cookie.httpOnly,
        secure: cookie.secure,
        sameSite: cookie.sameSite
      })),
    origins: (raw.origins || []).filter((origin) =>
      /https:\/\/(?:www\.)?(?:x|twitter)\.com/i.test(origin.origin || "")
    )
  };
}

export class PlaywrightResolver {
  constructor({
    browserProfileDir,
    storageStatePath = null,
    chromiumImpl = chromium,
    logger = console
  } = {}) {
    this.browserProfileDir = browserProfileDir;
    this.storageStatePath = storageStatePath;
    this.chromiumImpl = chromiumImpl;
    this.logger = logger;
    this.contextPromise = null;
    this.browser = null;
  }

  async ensureContext({ headless = true } = {}) {
    if (!this.contextPromise) {
      this.contextPromise = (async () => {
        const commonOptions = {
          viewport: { width: 1280, height: 900 },
          userAgent:
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
        };

        if (this.storageStatePath && fs.existsSync(this.storageStatePath)) {
          this.browser = await this.chromiumImpl.launch({ headless });
          return this.browser.newContext({
            ...commonOptions,
            storageState: loadSanitizedStorageState(this.storageStatePath)
          });
        }

        return this.chromiumImpl.launchPersistentContext(this.browserProfileDir, {
          headless,
          ...commonOptions
        });
      })();
    }

    return this.contextPromise;
  }

  async discoverSource(handle) {
    const context = await this.ensureContext();
    const page = await context.newPage();
    let sawRateLimit = false;
    const discovered = new Map();
    let scrolls = 0;
    let stableRounds = 0;
    let lastScrollHeight = 0;

    const onResponse = (response) => {
      if (response.status() === 429) {
        sawRateLimit = true;
      }
    };

    page.on("response", onResponse);

    try {
      const mediaUrl = `https://x.com/${handle}/media`;
      await page.goto(mediaUrl, {
        waitUntil: "domcontentloaded",
        timeout: 45000
      });

      await page.waitForLoadState("networkidle", { timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(2500);

      const initialSnapshot = await getPageSnapshot(page);
      checkPageAccess({
        ...initialSnapshot,
        sawRateLimit,
        action: "discovering media for",
        handleOrUrl: `@${handle}`
      });

      for (let round = 0; round < 6; round += 1) {
        const { entries, scrollHeight } = await collectMediaGridEntries(page);
        const extracted = extractMediaGridVideoLinks(entries, handle);
        let insertedThisRound = 0;

        for (const item of extracted) {
          if (discovered.has(item.tweetId)) {
            continue;
          }

          discovered.set(item.tweetId, item);
          insertedThisRound += 1;
        }

        if (insertedThisRound === 0 && scrollHeight === lastScrollHeight) {
          stableRounds += 1;
        } else {
          stableRounds = 0;
        }

        lastScrollHeight = scrollHeight;
        if (stableRounds >= 2) {
          break;
        }

        scrolls += 1;
        await page.evaluate(() => {
          window.scrollBy(0, Math.round(window.innerHeight * 1.6));
        });
        await page.waitForTimeout(1400);

        const snapshot = await getPageSnapshot(page);
        checkPageAccess({
          ...snapshot,
          sawRateLimit,
          action: "discovering media for",
          handleOrUrl: `@${handle}`
        });
      }

      return {
        items: Array.from(discovered.values()).map((item) => ({
          tweetId: item.tweetId,
          tweetUrl: item.tweetUrl,
          durationText: item.durationText || null,
          rawDiscoveryPayload: {
            sourceHandle: handle,
            method: "playwright-media-grid",
            discoveredLink: item,
            scrolls
          }
        })),
        rawPayload: {
          sourceHandle: handle,
          method: "playwright-media-grid",
          itemsFound: discovered.size,
          scrolls
        }
      };
    } finally {
      page.off("response", onResponse);
      await page.close().catch(() => {});
    }
  }

  async resolveTweet(tweetRecord) {
    const context = await this.ensureContext();
    const page = await context.newPage();
    const captured = [];
    let sawRateLimit = false;

    const onResponse = async (response) => {
      try {
        const url = response.url();
        if (response.status() === 429) {
          sawRateLimit = true;
        }

        const contentType = response.headers()["content-type"] || "";
        if (!contentType.includes("application/json") && !/\/graphql|\/i\/api\//.test(url)) {
          return;
        }

        const text = await response.text();
        if (!text || text.length > 2_000_000) {
          return;
        }

        const payload = JSON.parse(text);
        const tweetNode = selectTweetFromPayload(payload, tweetRecord.tweetId);
        if (!tweetNode) {
          return;
        }

        captured.push({
          url,
          match: tweetNode
        });
      } catch (error) {
        this.logger.debug?.("resolver response parse skipped", error);
      }
    };

    page.on("response", onResponse);

    try {
      await page.goto(tweetRecord.tweetUrl, {
        waitUntil: "domcontentloaded",
        timeout: 45000
      });

      await page.waitForLoadState("networkidle", { timeout: 8000 }).catch(() => {});
      await page.waitForTimeout(2500);

      const snapshot = await getPageSnapshot(page);
      checkPageAccess({
        ...snapshot,
        sawRateLimit,
        action: "resolving",
        handleOrUrl: tweetRecord.tweetUrl
      });

      const latestMatch = captured[captured.length - 1]?.match || null;
      if (!latestMatch) {
        return {
          status: "failed",
          errorCode: "resolve_failed",
          errorMessage: `No matching tweet payload found for ${tweetRecord.tweetUrl}`,
          rawPayload: {
            url: snapshot.currentUrl,
            bodyText: snapshot.bodyText.slice(0, 1000)
          }
        };
      }

      const normalized = normalizeResolvedTweet(latestMatch, tweetRecord.tweetId);
      if (normalized.mediaAssets.length > 0) {
        return {
          status: "resolved",
          tweet: normalized,
          mediaAssets: normalized.mediaAssets,
          rawPayload: summarizePayload(captured)
        };
      }

      if (normalized.hasVideoLikeMedia) {
        return {
          status: "external_only",
          tweet: normalized,
          mediaAssets: [],
          rawPayload: summarizePayload(captured)
        };
      }

      return {
        status: "skipped",
        tweet: normalized,
        mediaAssets: [],
        rawPayload: summarizePayload(captured)
      };
    } catch (error) {
      if (error?.code === "rate_limited" || error?.code === "auth_required") {
        return {
          status: "failed",
          errorCode: error.code,
          errorMessage: error.message,
          rawPayload: summarizePayload(captured)
        };
      }

      throw error;
    } finally {
      page.off("response", onResponse);
      await page.close().catch(() => {});
    }
  }

  async close() {
    if (!this.contextPromise) {
      return;
    }

    const context = await this.contextPromise;
    await context.close();
    if (this.browser) {
      await this.browser.close();
      this.browser = null;
    }
    this.contextPromise = null;
  }
}

export async function openAuthBrowser({ browserProfileDir, chromiumImpl = chromium }) {
  const context = await chromiumImpl.launchPersistentContext(browserProfileDir, {
    headless: false,
    viewport: { width: 1280, height: 900 }
  });
  const page = await context.newPage();
  await page.goto("https://x.com/home", { waitUntil: "domcontentloaded" });
  return context;
}
