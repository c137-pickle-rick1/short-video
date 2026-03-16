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
