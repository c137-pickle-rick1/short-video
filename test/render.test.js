import test from "node:test";
import assert from "node:assert/strict";

import { renderFeedItem } from "../public/render.js";

test("renderFeedItem renders inline video cards", () => {
  const markup = renderFeedItem({
    tweetId: "999",
    authorHandle: "demo",
    authorName: "Demo",
    text: "Inline video",
    postedAt: "2025-02-14T06:37:00.000Z",
    posterUrl: "https://example.com/poster.jpg",
    status: "resolved",
    sourceHandle: "demo",
    tweetUrl: "https://x.com/demo/status/999",
    videoUrl: "https://example.com/video.mp4"
  });

  assert.match(markup, /<video/);
  assert.match(markup, /本地直放/);
  assert.match(markup, /查看原帖/);
});

test("renderFeedItem renders external fallback cards", () => {
  const markup = renderFeedItem({
    tweetId: "1001",
    authorHandle: "demo",
    authorName: "Demo",
    text: "External fallback",
    postedAt: "2025-02-14T06:37:00.000Z",
    posterUrl: "https://example.com/poster.jpg",
    status: "external_only",
    sourceHandle: "demo",
    tweetUrl: "https://x.com/demo/status/1001",
    videoUrl: null
  });

  assert.match(markup, /外链回退/);
  assert.doesNotMatch(markup, /<video/);
});
