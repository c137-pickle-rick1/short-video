import test from "node:test";
import assert from "node:assert/strict";

import { CrawlOrchestrator } from "../server/crawler.js";
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
  } finally {
    temp.cleanup();
  }
});
