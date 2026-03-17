import test from "node:test";
import assert from "node:assert/strict";
import path from "node:path";

import request from "supertest";

import { createApiApp } from "../server/api.js";
import { getHomePageViewModel, renderHomePage } from "../server/homePage.js";
import { createTempDb } from "./helpers.js";

function insertResolvedTweet(db, sourceId, overrides = {}) {
  db.insertDiscoveredTweet({
    tweetId: overrides.tweetId || "3000",
    sourceId,
    tweetUrl: overrides.tweetUrl || "https://x.com/demo/status/3000",
    durationText: overrides.durationText || "0:21",
    rawDiscoveryPayload: { link: "x" }
  });

  db.applyResolution(overrides.tweetId || "3000", {
    status: "resolved",
    tweet: {
      authorHandle: "demo",
      authorName: "Demo",
      authorAvatarUrl: "https://example.com/avatar.jpg",
      text: "SSR 首页视频",
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

test("getHomePageViewModel maps first-page items to media proxy urls", () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    insertResolvedTweet(temp.db, source.id);

    const viewModel = getHomePageViewModel({
      db: temp.db,
      sourceHandle: "demo"
    });

    assert.equal(viewModel.activeSourceHandle, "demo");
    assert.equal(viewModel.feed.items.length, 1);
    assert.equal(viewModel.feed.items[0].videoUrl, "/api/media/3000");
  } finally {
    temp.cleanup();
  }
});

test("renderHomePage includes empty state markup for JS-disabled visits", () => {
  const temp = createTempDb();
  try {
    temp.db.syncSources([{ handle: "demo", enabled: true }]);
    const markup = renderHomePage(
      getHomePageViewModel({
        db: temp.db
      })
    );

    assert.match(markup, /id="feed-grid" aria-live="polite" data-empty="true"/);
    assert.match(markup, /还没有可展示的视频/);
    assert.match(markup, /id="feed-bootstrap" type="application\/json"/);
    assert.match(markup, /id="source-filter"/);
    assert.match(markup, /id="feed-summary"/);
    assert.match(markup, /id="feed-status"/);
    assert.match(markup, /\/vendor\/fonts\/fonts\.css/);
    assert.match(markup, /\/vendor\/phosphor\/regular\/style\.css/);
    assert.match(markup, /\/vendor\/phosphor\/fill\/style\.css/);
    assert.match(markup, /\/vendor\/plyr\/plyr\.css/);
    assert.match(markup, /\/vendor\/plyr\/plyr\.min\.js/);
    assert.match(markup, /\/vendor\/colcade\/colcade\.js/);
    assert.doesNotMatch(markup, /fonts\.googleapis\.com/);
    assert.doesNotMatch(markup, /fonts\.gstatic\.com/);
    assert.doesNotMatch(markup, /cdn\.plyr\.io/);
    assert.doesNotMatch(markup, /unpkg\.com/);
  } finally {
    temp.cleanup();
  }
});

test("GET \\/ renders SSR home page with initial feed and bootstrap payload", async () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    insertResolvedTweet(temp.db, source.id, {
      tweetId: "3001",
      tweet: {
        text: "服务端首屏卡片"
      }
    });

    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public")
    });

    const response = await request(app).get("/?source=demo");
    assert.equal(response.status, 200);
    assert.match(response.headers["content-type"], /text\/html/);
    assert.match(response.text, /服务端首屏卡片/);
    assert.match(response.text, /\/api\/media\/3001/);
    assert.match(response.text, /"source":"demo"/);
  } finally {
    temp.cleanup();
  }
});
