import test from "node:test";
import assert from "node:assert/strict";

import { renderFeedDetail, renderFeedItem } from "../laravel/public/render.js";

test("renderFeedItem renders inline video cards with dialog trigger semantics", () => {
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
    hlsUrl: "https://example.com/video.m3u8",
    videoUrl: "https://example.com/video.mp4"
  });

  assert.match(markup, /<video/);
  assert.match(markup, /avatar\.jpg/);
  assert.match(markup, /data-feed-detail-trigger="true"/);
  assert.match(markup, /aria-haspopup="dialog"/);
  assert.match(markup, /poster="https:\/\/example\.com\/poster\.jpg"/);
  assert.match(markup, /data-hls-url="https:\/\/example\.com\/video\.m3u8"/);
  assert.match(markup, /data-fallback-url="https:\/\/example\.com\/video\.mp4"/);
  assert.match(markup, /preload="metadata"/);
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

  assert.match(markup, /data-status="external_only"/);
  assert.match(markup, /Poster for @demo/);
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

test("renderFeedDetail renders modal layout content", () => {
  const markup = renderFeedDetail({
    tweetId: "2001",
    authorHandle: "demo",
    authorName: "Demo Creator",
    authorAvatarUrl: "https://example.com/avatar.jpg",
    text: "这是一个用于详情弹窗的视频标题",
    postedAt: "2025-02-14T06:37:00.000Z",
    posterUrl: "https://example.com/poster.jpg",
    status: "resolved",
    videoUrl: "https://example.com/video.mp4"
  });

  assert.match(markup, /detail-modal-title/);
  assert.match(markup, /关注/);
  assert.match(markup, /发布日期/);
  assert.match(markup, /评论区/);
  assert.match(markup, /示意评论/);
  assert.match(markup, /<video/);
  assert.match(markup, /data-detail-player/);
});
