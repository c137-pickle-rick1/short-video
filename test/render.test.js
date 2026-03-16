import test from "node:test";
import assert from "node:assert/strict";

import { renderFeedItem } from "../public/render.js";

test("renderFeedItem renders inline video cards", () => {
  const markup = renderFeedItem({
    tweetId: "999",
    authorHandle: "demo",
    authorName: "Demo",
    authorAvatarUrl: "https://example.com/avatar.jpg",
    text: "Inline video",
    postedAt: "2025-02-14T06:37:00.000Z",
    posterUrl: "https://example.com/poster.jpg",
    status: "resolved",
    sourceHandle: "demo",
    tweetUrl: "https://x.com/demo/status/999",
    videoUrl: "https://example.com/video.mp4"
  });

  assert.match(markup, /<video/);
  assert.match(markup, /avatar\.jpg/);
  assert.match(markup, /本地直放/);
  assert.match(markup, /查看原帖/);
  assert.doesNotMatch(markup, /\bcontrols\b/);
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

test("renderFeedItem strips links from card title text", () => {
  const markup = renderFeedItem({
    tweetId: "1002",
    authorHandle: "demo",
    authorName: "Demo",
    text: "这是一条带链接的标题 https://example.com/watch?v=1",
    postedAt: "2025-02-14T06:37:00.000Z",
    posterUrl: "https://example.com/poster.jpg",
    status: "external_only",
    sourceHandle: "demo",
    tweetUrl: "https://x.com/demo/status/1002",
    videoUrl: null
  });

  assert.match(markup, /这是一条带链接的标题/);
  assert.doesNotMatch(markup, /https:\/\/example\.com\/watch\?v=1/);
});

test("renderFeedItem falls back to author initial when avatar is missing", () => {
  const markup = renderFeedItem({
    tweetId: "1003",
    authorHandle: "demo",
    authorName: "Demo",
    authorAvatarUrl: null,
    text: "No avatar",
    postedAt: "2025-02-14T06:37:00.000Z",
    posterUrl: "https://example.com/poster.jpg",
    status: "external_only",
    sourceHandle: "demo",
    tweetUrl: "https://x.com/demo/status/1003",
    videoUrl: null
  });

  assert.match(markup, />\s*D\s*<\/span>/);
  assert.doesNotMatch(markup, /<img[\s\S]*头像/);
});
