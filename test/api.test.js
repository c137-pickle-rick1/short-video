import test from "node:test";
import assert from "node:assert/strict";
import path from "node:path";

import request from "supertest";

import { createApiApp } from "../server/api.js";
import { createTempDb } from "./helpers.js";

test("GET /api/feed paginates and filters by source", async () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    temp.db.insertDiscoveredTweet({
      tweetId: "2000",
      sourceId: source.id,
      tweetUrl: "https://x.com/demo/status/2000",
      durationText: "0:37",
      rawDiscoveryPayload: { link: "x" }
    });
    temp.db.applyResolution("2000", {
      status: "resolved",
      tweet: {
        authorHandle: "demo",
        authorName: "Demo",
        authorAvatarUrl: "https://example.com/avatar.jpg",
        text: "API test tweet",
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

    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public")
    });

    const response = await request(app).get("/api/feed?source=demo&limit=5");
    assert.equal(response.status, 200);
    assert.equal(response.body.items.length, 1);
    assert.equal(response.body.items[0].videoUrl, "/api/media/2000");
    assert.equal(response.body.items[0].authorAvatarUrl, "https://example.com/avatar.jpg");
    assert.equal(response.body.items[0].durationText, "0:37");
  } finally {
    temp.cleanup();
  }
});

test("GET /api/media proxies upstream headers and body", async () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    temp.db.insertDiscoveredTweet({
      tweetId: "2002",
      sourceId: source.id,
      tweetUrl: "https://x.com/demo/status/2002",
      rawDiscoveryPayload: { link: "x" }
    });
    temp.db.applyResolution("2002", {
      status: "resolved",
      tweet: {
        authorHandle: "demo",
        authorName: "Demo",
        text: "Media proxy tweet",
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

    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public"),
      fetchImpl: async (_url, options) =>
        new Response("video-data", {
          status: 206,
          headers: {
            "content-type": "video/mp4",
            "content-range": "bytes 0-9/10",
            "accept-ranges": "bytes",
            "cache-control": "public, max-age=60",
            "content-length": "10",
            etag: "\"demo\""
          }
        })
    });

    const response = await request(app)
      .get("/api/media/2002")
      .set("Range", "bytes=0-9");

    assert.equal(response.status, 206);
    assert.equal(response.headers["content-type"], "video/mp4");
    assert.equal(response.headers["content-range"], "bytes 0-9/10");
    assert.equal(
      Buffer.isBuffer(response.body) ? response.body.toString("utf8") : response.text,
      "video-data"
    );
  } finally {
    temp.cleanup();
  }
});

test("GET /api/media returns 504 when the upstream request times out", async () => {
  const temp = createTempDb();
  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    temp.db.insertDiscoveredTweet({
      tweetId: "2003",
      sourceId: source.id,
      tweetUrl: "https://x.com/demo/status/2003",
      rawDiscoveryPayload: { link: "x" }
    });
    temp.db.applyResolution("2003", {
      status: "resolved",
      tweet: {
        authorHandle: "demo",
        authorName: "Demo",
        text: "Slow upstream tweet",
        postedAt: "2025-02-14T06:37:00.000Z",
        posterUrl: "https://example.com/poster.jpg"
      },
      mediaAssets: [
        {
          url: "https://example.com/slow.mp4",
          bitrate: 1000,
          contentType: "video/mp4",
          width: 720,
          height: 1280,
          sortOrder: 0,
          isPrimary: true
        }
      ]
    });

    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public"),
      mediaProxyTimeoutMs: 5,
      fetchImpl: () => new Promise(() => {})
    });

    const response = await request(app).get("/api/media/2003");

    assert.equal(response.status, 504);
    assert.match(response.body.error, /timed out/i);
  } finally {
    temp.cleanup();
  }
});

test("GET /shared serves browser-visible shared modules", async () => {
  const temp = createTempDb();
  try {
    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public")
    });

    const response = await request(app).get("/shared/feed/render/templates.js");

    assert.equal(response.status, 200);
    assert.match(response.headers["content-type"], /javascript|ecmascript|text\/plain/);
    assert.match(response.text, /renderFeedItem/);
  } finally {
    temp.cleanup();
  }
});

test("GET /vendor serves local runtime assets", async () => {
  const temp = createTempDb();
  try {
    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public")
    });

    const response = await request(app).get("/vendor/plyr/plyr.min.js");

    assert.equal(response.status, 200);
    assert.match(response.headers["content-type"], /javascript|ecmascript|text\/plain/);
    assert.match(response.text, /Plyr/);
  } finally {
    temp.cleanup();
  }
});
