#!/usr/bin/env node

import { createDiscoveryClient } from "./lib/discovery/compositeDiscoveryClient.js";
import { JinaDiscoveryClient } from "./lib/discovery/jinaDiscoveryClient.js";
import {
  PlaywrightResolver,
  openAuthBrowser
} from "./lib/resolve/playwrightResolver.js";
import { XApiClient } from "./lib/xApiClient.js";

function parseArgs(argv) {
  const [command = "", ...rest] = argv;
  const options = {};

  for (let index = 0; index < rest.length; index += 1) {
    const token = rest[index];
    if (!token.startsWith("--")) {
      continue;
    }

    const key = token.slice(2);
    const next = rest[index + 1];
    if (!next || next.startsWith("--")) {
      options[key] = true;
      continue;
    }

    options[key] = next;
    index += 1;
  }

  return {
    command,
    options
  };
}

async function readStdin() {
  const chunks = [];

  for await (const chunk of process.stdin) {
    chunks.push(chunk);
  }

  return Buffer.concat(chunks).toString("utf8");
}

function printJson(payload) {
  process.stdout.write(`${JSON.stringify(payload)}\n`);
}

function printError(error) {
  printJson({
    ok: false,
    error: {
      code: error?.code || "sidecar_failed",
      message: error?.message || "Unknown sidecar error"
    }
  });
}

function parseMode(rawMode) {
  return String(rawMode || "hybrid").trim().toLowerCase();
}

function parsePositiveInt(value, fallback, { min = 1, max = Number.MAX_SAFE_INTEGER } = {}) {
  const parsed = Number.parseInt(String(value || fallback), 10);
  if (!Number.isFinite(parsed)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, parsed));
}

function shouldFallbackToBrowser(result) {
  return result?.status !== "resolved";
}

function createXApiClient(options) {
  const bearerToken = String(
    options["x-api-bearer-token"] || process.env.X_API_BEARER_TOKEN || ""
  ).trim();

  if (!bearerToken) {
    return null;
  }

  return new XApiClient({
    bearerToken,
    maxPages: parsePositiveInt(options["x-api-max-pages"], "4", { min: 1, max: 20 }),
    pageSize: parsePositiveInt(options["x-api-page-size"], "100", { min: 5, max: 100 }),
    includeReplies: Boolean(options["x-api-include-replies"]),
    includeRetweets: Boolean(options["x-api-include-retweets"])
  });
}

async function resolveWithBrowser(resolver, tweets, concurrency) {
  const results = [];

  for (let index = 0; index < tweets.length; index += concurrency) {
    const tweetBatch = tweets.slice(index, index + concurrency);
    const batchResults = await Promise.all(
      tweetBatch.map(async (tweet) => ({
        tweetId: String(tweet?.tweetId || ""),
        ...(await resolver.resolveTweet(tweet))
      }))
    );

    results.push(...batchResults);

    if (
      batchResults.some(
        (resolution) =>
          resolution?.errorCode === "rate_limited" || resolution?.errorCode === "auth_required"
      )
    ) {
      break;
    }
  }

  return results;
}

async function runDiscoverSource(options) {
  const mode = parseMode(options.mode);
  const handle = String(options.handle || "").trim().replace(/^@/, "").toLowerCase();
  const sourceUserId = String(options["source-user-id"] || "").trim();
  const sinceId = String(options["since-id"] || "").trim();
  const browserProfileDir = String(options["browser-profile-dir"] || "");
  const storageStatePath = String(options["storage-state-path"] || "");
  const cdpUrl = String(options["cdp-url"] || "");
  const maxScrollRounds = parsePositiveInt(options["max-scroll-rounds"], "6", {
    min: 1,
    max: 100
  });

  if (!handle) {
    throw Object.assign(new Error("Missing required --handle option"), { code: "invalid_arguments" });
  }

  const resolver = new PlaywrightResolver({
    browserProfileDir,
    storageStatePath,
    cdpUrl,
    maxScrollRounds
  });
  const apiClient = createXApiClient(options);
  const discoveryClient = createDiscoveryClient({
    mode,
    primaryClient: new JinaDiscoveryClient(),
    fallbackClient: resolver,
    apiClient
  });

  try {
    const result = await discoveryClient.discoverSource(handle, {
      sourceUserId: sourceUserId || null,
      sinceId: sinceId || null
    });
    printJson({
      ok: true,
      result
    });
  } finally {
    await resolver.close().catch(() => {});
  }
}

async function runResolveTweets(options) {
  const mode = parseMode(options.mode);
  const browserProfileDir = String(options["browser-profile-dir"] || "");
  const storageStatePath = String(options["storage-state-path"] || "");
  const cdpUrl = String(options["cdp-url"] || "");
  const concurrency = parsePositiveInt(options.concurrency, "4", {
    min: 1,
    max: 32
  });
  const resolver = new PlaywrightResolver({
    browserProfileDir,
    storageStatePath,
    cdpUrl
  });
  const apiClient = createXApiClient(options);

  try {
    const rawInput = await readStdin();
    const tweets = rawInput.trim() ? JSON.parse(rawInput) : [];
    if (!Array.isArray(tweets)) {
      throw Object.assign(new Error("resolve-tweets expects a JSON array on stdin"), {
        code: "invalid_arguments"
      });
    }

    if (mode === "api") {
      if (!apiClient) {
        throw Object.assign(
          new Error("Official API mode requires X_API_BEARER_TOKEN to be configured"),
          { code: "api_auth_failed" }
        );
      }

      const results = await apiClient.resolveTweets(tweets);
      printJson({
        ok: true,
        result: {
          results
        }
      });

      return;
    }

    if (mode === "api_hybrid") {
      if (!apiClient && !resolver.canUseBrowserFallback()) {
        throw Object.assign(
          new Error("API hybrid mode requires X_API_BEARER_TOKEN or an available browser fallback"),
          { code: "api_auth_failed" }
        );
      }

      const apiResults = new Map();

      if (apiClient) {
        try {
          for (const result of await apiClient.resolveTweets(tweets)) {
            apiResults.set(String(result?.tweetId || ""), result);
          }
        } catch (error) {
          if (!resolver.canUseBrowserFallback()) {
            throw error;
          }

          console.warn("resolve-tweets: X API lookup failed, falling back to browser", error);
        }
      }

      const browserFallbackTweets = apiClient
        ? tweets.filter((tweet) => shouldFallbackToBrowser(apiResults.get(String(tweet?.tweetId || ""))))
        : tweets;
      const browserFallbackResults =
        browserFallbackTweets.length > 0 && resolver.canUseBrowserFallback()
          ? await resolveWithBrowser(resolver, browserFallbackTweets, concurrency)
          : [];
      const browserResultsById = new Map(
        browserFallbackResults.map((result) => [String(result?.tweetId || ""), result])
      );

      const results = tweets.map((tweet) => {
        const tweetId = String(tweet?.tweetId || "");
        return browserResultsById.get(tweetId) || apiResults.get(tweetId) || {
          tweetId,
          status: "failed",
          errorCode: "resolve_failed",
          errorMessage: `Unable to resolve tweet ${tweetId}`,
          rawPayload: {
            source: apiClient ? "x_api_hybrid" : "browser",
            tweetId
          }
        };
      });

      printJson({
        ok: true,
        result: {
          results
        }
      });

      return;
    }

    const results = await resolveWithBrowser(resolver, tweets, concurrency);
    printJson({
      ok: true,
      result: {
        results
      }
    });
  } finally {
    await resolver.close().catch(() => {});
  }
}

async function runOpenAuthBrowser(options) {
  const browserProfileDir = String(options["browser-profile-dir"] || "");
  const cdpUrl = String(options["cdp-url"] || "");

  if (!browserProfileDir && !cdpUrl) {
    throw Object.assign(new Error("Missing required --browser-profile-dir or --cdp-url option"), {
      code: "invalid_arguments"
    });
  }

  const browserSession = await openAuthBrowser({
    browserProfileDir,
    cdpUrl
  });

  process.stdout.write("Browser profile opened. Log into X, then press Ctrl+C to close.\n");

  const shutdown = async () => {
    await browserSession.close().catch(() => {});
    process.exit(0);
  };

  process.on("SIGINT", shutdown);
  process.on("SIGTERM", shutdown);

  await new Promise(() => {});
}

async function main() {
  const { command, options } = parseArgs(process.argv.slice(2));

  if (command === "discover-source") {
    await runDiscoverSource(options);
    return;
  }

  if (command === "resolve-tweets") {
    await runResolveTweets(options);
    return;
  }

  if (command === "open-auth-browser") {
    await runOpenAuthBrowser(options);
    return;
  }

  throw Object.assign(new Error(`Unsupported sidecar command: ${command || "<none>"}`), {
    code: "invalid_command"
  });
}

main().catch((error) => {
  printError(error);
  process.exit(1);
});
