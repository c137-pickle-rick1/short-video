import test from "node:test";
import assert from "node:assert/strict";

import { CrawlOrchestrator } from "../server/crawler.js";
import { createAppError } from "../server/errors.js";
import { createTempDb } from "./helpers.js";

test("crawlOnce discovers tweets and resolves them into published feed items", async () => {
  const temp = createTempDb();
  const discoveryClient = {
    async discoverSource(handle) {
      return {
        items: [
          {
            tweetId: "1000",
            tweetUrl: `https://x.com/${handle}/status/1000`,
            durationText: "0:18",
            rawDiscoveryPayload: { link: "test" }
          }
        ]
      };
    }
  };
  const resolverClient = {
    async resolveTweet(tweet) {
      return {
        status: "resolved",
        tweet: {
          tweetId: tweet.tweetId,
          tweetUrl: tweet.tweetUrl,
          authorHandle: "demo",
          authorName: "Demo",
          text: "Resolved tweet",
          postedAt: "2025-02-14T06:37:00.000Z",
          posterUrl: "https://example.com/poster.jpg"
        },
        mediaAssets: [
          {
            url: "https://example.com/video.mp4",
            bitrate: 1000,
            contentType: "video/mp4",
            width: 720,
            height: 1280,
            sortOrder: 0,
            isPrimary: true
          }
        ]
      };
    }
  };

  try {
    const crawler = new CrawlOrchestrator({
      db: temp.db,
      discoveryClient,
      resolverClient
    });

    crawler.syncSources([{ handle: "demo", enabled: true }]);
    const result = await crawler.crawlOnce();

    assert.equal(result.discovery.itemsInserted, 1);
    assert.equal(result.resolution.itemsResolved, 1);
    assert.equal(temp.db.countTweetsByStatus("resolved"), 1);
    assert.equal(temp.db.countTweetsByStatus("pending"), 0);
    assert.equal(temp.db.getFeed({ limit: 1 }).items[0].durationText, "0:18");
  } finally {
    temp.cleanup();
  }
});

test("backfillMissingAvatars re-resolves published tweets without avatars", async () => {
  const temp = createTempDb();
  const resolverCalls = [];
  const resolverClient = {
    async resolveTweet(tweet) {
      resolverCalls.push(tweet.tweetId);
      return {
        status: "resolved",
        tweet: {
          tweetId: tweet.tweetId,
          tweetUrl: tweet.tweetUrl,
          authorHandle: "demo",
          authorName: "Demo",
          authorAvatarUrl: "https://example.com/avatar.jpg",
          text: "Resolved tweet",
          postedAt: "2025-02-14T06:37:00.000Z",
          posterUrl: "https://example.com/poster.jpg"
        },
        mediaAssets: [
          {
            url: "https://example.com/video.mp4",
            bitrate: 1000,
            contentType: "video/mp4",
            width: 720,
            height: 1280,
            sortOrder: 0,
            isPrimary: true
          }
        ]
      };
    }
  };

  try {
    const crawler = new CrawlOrchestrator({
      db: temp.db,
      discoveryClient: { async discoverSource() { return { items: [] }; } },
      resolverClient
    });

    const [source] = crawler.syncSources([{ handle: "demo", enabled: true }]);
    temp.db.insertDiscoveredTweet({
      tweetId: "2001",
      sourceId: source.id,
      tweetUrl: "https://x.com/demo/status/2001",
      durationText: "1:24",
      rawDiscoveryPayload: { link: "x" }
    });
    temp.db.applyResolution("2001", {
      status: "resolved",
      tweet: {
        authorHandle: "demo",
        authorName: "Demo",
        authorAvatarUrl: null,
        text: "Old tweet",
        postedAt: "2025-02-14T06:37:00.000Z",
        posterUrl: "https://example.com/poster.jpg"
      },
      mediaAssets: [
        {
          url: "https://example.com/video.mp4",
          bitrate: 1000,
          contentType: "video/mp4",
          width: 720,
          height: 1280,
          sortOrder: 0,
          isPrimary: true
        }
      ]
    });

    const result = await crawler.backfillMissingAvatars();
    const [item] = temp.db.getFeed({ limit: 1 }).items;

    assert.equal(result.itemsSeen, 1);
    assert.equal(result.itemsResolved, 1);
    assert.deepEqual(resolverCalls, ["2001"]);
    assert.equal(item.authorAvatarUrl, "https://example.com/avatar.jpg");
    assert.equal(item.durationText, "1:24");
  } finally {
    temp.cleanup();
  }
});

test("discoverAllSources skips work while a backoff window is active", async () => {
  const temp = createTempDb();

  try {
    const crawler = new CrawlOrchestrator({
      db: temp.db,
      discoveryClient: {
        async discoverSource() {
          throw new Error("discovery should not run during backoff");
        }
      },
      resolverClient: {
        async resolveTweet() {
          throw new Error("resolution should not run during backoff");
        }
      }
    });

    crawler.setBackoff("rate_limited", 15);

    const result = await crawler.discoverAllSources();

    assert.equal(result.skipped, true);
    assert.equal(result.reason, "rate_limited");
    assert.match(result.until, /^\d{4}-\d{2}-\d{2}T/);
  } finally {
    temp.cleanup();
  }
});

test("resolvePending enters backoff when resolver throws a backoff error", async () => {
  const temp = createTempDb();

  try {
    const crawler = new CrawlOrchestrator({
      db: temp.db,
      discoveryClient: {
        async discoverSource() {
          return { items: [] };
        }
      },
      resolverClient: {
        async resolveTweet() {
          throw createAppError("rate_limited", "Resolver is rate limited");
        }
      }
    });

    const [source] = crawler.syncSources([{ handle: "demo", enabled: true }]);
    temp.db.insertDiscoveredTweet({
      tweetId: "3001",
      sourceId: source.id,
      tweetUrl: "https://x.com/demo/status/3001",
      durationText: "0:33",
      rawDiscoveryPayload: { link: "x" }
    });

    const result = await crawler.resolvePending();

    assert.equal(result.status, "failed");
    assert.equal(result.itemsSeen, 1);
    assert.equal(result.itemsResolved, 0);
    assert.equal(result.errorMessage, "Resolver is rate limited");
    assert.equal(crawler.inBackoff(), true);
    assert.equal(crawler.getBackoffState().backoffReason, "rate_limited");
  } finally {
    temp.cleanup();
  }
});
