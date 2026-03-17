import test from "node:test";
import assert from "node:assert/strict";

import { createMediaService } from "../server/services/mediaService.js";

test("createMediaService returns a 404 payload when media is missing", async () => {
  const mediaService = createMediaService({
    db: {
      getPrimaryMedia() {
        return null;
      }
    }
  });

  const result = await mediaService.getMediaStream({ tweetId: "missing" });

  assert.equal(result.kind, "json");
  assert.equal(result.status, 404);
  assert.equal(result.body.error, "Video not found");
});

test("createMediaService returns a 504 payload when the upstream fetch times out", async () => {
  const mediaService = createMediaService({
    db: {
      getPrimaryMedia() {
        return {
          url: "https://example.com/video.mp4"
        };
      }
    },
    timeoutMs: 5,
    fetchImpl() {
      return new Promise(() => {});
    }
  });

  const result = await mediaService.getMediaStream({ tweetId: "slow" });

  assert.equal(result.kind, "json");
  assert.equal(result.status, 504);
  assert.match(result.body.error, /timed out/i);
});
