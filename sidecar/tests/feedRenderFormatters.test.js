import test from "node:test";
import assert from "node:assert/strict";

import { formatFeedSummary } from "../../laravel/resources/js/features/feed/render/formatters.js";
import { renderFeedItem } from "../../laravel/resources/js/features/feed/render/templates.js";

test("formatFeedSummary reflects search context and loading state for explore feeds", () => {
  assert.equal(
    formatFeedSummary({
      mode: "explore",
      query: "lagos",
      sourceHandle: "demo",
      renderedCount: 12,
      done: false
    }),
    "@demo · 搜索 “lagos” · 已展示 12 条 · 向下滚动继续加载"
  );
});

test("renderFeedItem keeps interactive card markup after the feed module move", () => {
  const html = renderFeedItem({
    tweetId: "42",
    status: "resolved",
    text: "Rooftop walkthrough https://text.example/link",
    postedAt: new Date().toISOString(),
    authorName: "Demo Creator",
    authorHandle: "demo",
    authorUsername: "demo-creator",
    authorAvatarUrl: "https://example.com/avatar.jpg",
    posterUrl: "https://example.com/poster.jpg",
    hlsUrl: "https://example.com/video.m3u8",
    videoUrl: "https://example.com/video.mp4",
    mediaWidth: 720,
    mediaHeight: 1280,
    durationText: "0:21"
  });

  assert.ok(html.includes('data-feed-detail-trigger="true"'));
  assert.ok(html.includes('data-hls-url="https://example.com/video.m3u8"'));
  assert.ok(html.includes('data-fallback-url="https://example.com/video.mp4"'));
  assert.ok(html.includes('href="/demo-creator"'));
  assert.ok(html.includes("Rooftop walkthrough"));
  assert.ok(!html.includes("https://text.example/link"));
});
