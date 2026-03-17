import test from "node:test";
import assert from "node:assert/strict";

import { mapFeedItemsForPresentation } from "../shared/feed/feedItemMapper.js";

test("mapFeedItemsForPresentation rewrites playable media urls to the proxy endpoint", () => {
  const items = mapFeedItemsForPresentation([
    {
      tweetId: "1000",
      videoUrl: "https://video.twimg.com/ext_tw_video/1000.mp4"
    },
    {
      tweetId: "1001",
      videoUrl: null
    }
  ]);

  assert.equal(items[0].videoUrl, "/api/media/1000");
  assert.equal(items[1].videoUrl, null);
});
