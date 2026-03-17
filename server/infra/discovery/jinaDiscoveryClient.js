import { createAppError } from "../../errors.js";

const STATUS_URL_RE =
  /^https?:\/\/(?:www\.)?(?:x|twitter)\.com\/([A-Za-z0-9_]+)\/status\/([0-9]+)\/?$/;

function normalizeUrl(url) {
  try {
    const parsed = new URL(url);
    parsed.hash = "";
    parsed.search = "";
    return parsed.toString().replace(/\/$/, "");
  } catch {
    return null;
  }
}

export function extractStatusLinks(links = [], expectedHandle = null) {
  const seenIds = new Set();
  const normalizedExpected = expectedHandle ? expectedHandle.toLowerCase() : null;
  const items = [];

  for (const entry of links) {
    const [label, href] = Array.isArray(entry) ? entry : [null, entry?.url];
    const normalized = normalizeUrl(href);
    if (!normalized) {
      continue;
    }

    const match = normalized.match(STATUS_URL_RE);
    if (!match) {
      continue;
    }

    const handle = match[1].toLowerCase();
    const tweetId = match[2];
    if (normalizedExpected && handle !== normalizedExpected) {
      continue;
    }

    if (seenIds.has(tweetId)) {
      continue;
    }

    seenIds.add(tweetId);
    items.push({
      tweetId,
      tweetUrl: normalized.replace(/^http:\/\//, "https://"),
      label: label || null,
      handle
    });
  }

  return items;
}

export function extractStatusLinksFromContent(content = "", expectedHandle = null) {
  const links = [];
  const matches = content.matchAll(
    /https?:\/\/(?:www\.)?(?:x|twitter)\.com\/([A-Za-z0-9_]+)\/status\/([0-9]+)/g
  );

  for (const match of matches) {
    links.push([null, match[0]]);
  }

  return extractStatusLinks(links, expectedHandle);
}

export class JinaDiscoveryClient {
  constructor({ fetchImpl = fetch, logger = console } = {}) {
    this.fetchImpl = fetchImpl;
    this.logger = logger;
  }

  async discoverSource(handle) {
    const url = `https://r.jina.ai/http://x.com/${handle}/media`;
    const response = await this.fetchImpl(url, {
      headers: {
        Accept: "application/json",
        "X-With-Links-Summary": "all",
        "X-No-Cache": "true"
      }
    });

    if (response.status === 429) {
      throw createAppError("rate_limited", `Jina rate limited discovery for @${handle}`);
    }

    if (!response.ok) {
      throw createAppError(
        "discovery_failed",
        `Jina discovery failed for @${handle} with HTTP ${response.status}`
      );
    }

    const payload = await response.json();
    const links = payload?.data?.links || [];
    const items =
      extractStatusLinks(links, handle).length > 0
        ? extractStatusLinks(links, handle)
        : extractStatusLinksFromContent(payload?.data?.content || "", handle);

    return {
      items: items.map((item) => ({
        tweetId: item.tweetId,
        tweetUrl: item.tweetUrl,
        rawDiscoveryPayload: {
          sourceHandle: handle,
          discoveredLink: item,
          title: payload?.data?.title || null,
          publishedTime: payload?.data?.publishedTime || null
        }
      })),
      rawPayload: payload
    };
  }
}
