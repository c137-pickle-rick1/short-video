import test from "node:test";
import assert from "node:assert/strict";

import { getFeedPage, getHomePageViewModel } from "../server/services/feedService.js";
import { createTempDb } from "./helpers.js";

function insertResolvedTweet(db, sourceId, overrides = {}) {
  db.insertDiscoveredTweet({
    tweetId: overrides.tweetId || "4000",
    sourceId,
    tweetUrl: overrides.tweetUrl || "https://x.com/demo/status/4000",
    durationText: overrides.durationText || "0:19",
    rawDiscoveryPayload: { link: "x" }
  });

  db.applyResolution(overrides.tweetId || "4000", {
    status: "resolved",
    tweet: {
      authorHandle: "demo",
      authorName: "Demo",
      authorAvatarUrl: "https://example.com/avatar.jpg",
      text: "Feed service tweet",
      postedAt: "2025-02-14T06:37:00.000Z",
      posterUrl: "https://example.com/poster.jpg",
      ...overrides.tweet
    },
    mediaAssets: [
      {
        url: "https://example.com/video.mp4",
        bitrate: 1000,
        contentType: "video/mp4",
        width: 720,
        height: 1280,
        sortOrder: 0,
        isPrimary: true,
        ...overrides.media
      }
    ]
  });
}

test("getFeedPage normalizes source handles and presentation urls", () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    insertResolvedTweet(temp.db, source.id);

    const page = getFeedPage({
      db: temp.db,
      sourceHandle: "@Demo",
      limit: 50
    });

    assert.equal(page.sourceHandle, "demo");
    assert.equal(page.limit, 24);
    assert.equal(page.items[0].videoUrl, "/api/media/4000");
  } finally {
    temp.cleanup();
  }
});

test("getHomePageViewModel derives page title from the active source", () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    insertResolvedTweet(temp.db, source.id);

    const viewModel = getHomePageViewModel({
      db: temp.db,
      sourceHandle: "demo"
    });

    assert.equal(viewModel.pageTitle, "@demo · Lagos Explore Feed");
    assert.equal(viewModel.feed.items.length, 1);
  } finally {
    temp.cleanup();
  }
});
