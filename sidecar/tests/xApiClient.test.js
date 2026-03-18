import test from "node:test";
import assert from "node:assert/strict";

import { formatDurationText, normalizeApiTweet, XApiClient } from "../lib/xApiClient.js";

test("formatDurationText renders minute and hour durations", () => {
  assert.equal(formatDurationText(37_000), "0:37");
  assert.equal(formatDurationText(3_723_000), "1:02:03");
  assert.equal(formatDurationText(null), null);
});

test("normalizeApiTweet extracts video variants and author metadata", () => {
  const normalized = normalizeApiTweet(
    {
      id: "2025001",
      author_id: "u-1",
      text: "视频正文 https://t.co/demo",
      created_at: "2026-03-18T10:20:30.000Z",
      attachments: {
        media_keys: ["3_1"]
      }
    },
    {
      users: [
        {
          id: "u-1",
          username: "demo_creator",
          name: "Demo Creator",
          profile_image_url: "https://pbs.twimg.com/profile_images/demo_normal.jpg"
        }
      ],
      media: [
        {
          media_key: "3_1",
          type: "video",
          width: 720,
          height: 1280,
          duration_ms: 37_000,
          preview_image_url: "https://pbs.twimg.com/media/demo.jpg",
          variants: [
            {
              content_type: "application/x-mpegURL",
              url: "https://video.twimg.com/demo/master.m3u8"
            },
            {
              bit_rate: 832000,
              content_type: "video/mp4",
              url: "https://video.twimg.com/demo/720.mp4"
            }
          ]
        }
      ]
    }
  );

  assert.equal(normalized.authorHandle, "demo_creator");
  assert.equal(normalized.authorAvatarUrl, "https://pbs.twimg.com/profile_images/demo_400x400.jpg");
  assert.equal(normalized.text, "视频正文");
  assert.equal(normalized.durationText, "0:37");
  assert.equal(normalized.posterUrl, "https://pbs.twimg.com/media/demo.jpg");
  assert.equal(normalized.mediaAssets[0].url, "https://video.twimg.com/demo/720.mp4");
  assert.equal(normalized.mediaAssets[1].url, "https://video.twimg.com/demo/master.m3u8");
});

test("XApiClient discoverSource paginates and keeps only video tweets", async () => {
  const requests = [];
  const fetchImpl = async (url) => {
    requests.push(url.toString());

    if (url.pathname.endsWith("/users/by/username/demo")) {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            id: "u-demo",
            username: "demo",
            name: "Demo"
          }
        })
      };
    }

    if (url.pathname.endsWith("/users/u-demo/tweets") && url.searchParams.get("pagination_token") === null) {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: [
            {
              id: "3001",
              author_id: "u-demo",
              text: "first video",
              created_at: "2026-03-18T10:20:30.000Z",
              attachments: {
                media_keys: ["m-video", "m-photo"]
              }
            }
          ],
          includes: {
            users: [
              {
                id: "u-demo",
                username: "demo",
                name: "Demo"
              }
            ],
            media: [
              {
                media_key: "m-video",
                type: "video",
                duration_ms: 12_000,
                preview_image_url: "https://pbs.twimg.com/media/first.jpg",
                variants: [
                  {
                    bit_rate: 256000,
                    content_type: "video/mp4",
                    url: "https://video.twimg.com/demo/first.mp4"
                  }
                ]
              },
              {
                media_key: "m-photo",
                type: "photo",
                url: "https://pbs.twimg.com/media/photo.jpg"
              }
            ]
          },
          meta: {
            next_token: "page-2"
          }
        })
      };
    }

    if (
      url.pathname.endsWith("/users/u-demo/tweets") &&
      url.searchParams.get("pagination_token") === "page-2"
    ) {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: [
            {
              id: "3002",
              author_id: "u-demo",
              text: "second video",
              created_at: "2026-03-18T11:20:30.000Z",
              attachments: {
                media_keys: ["m-video-2"]
              }
            }
          ],
          includes: {
            users: [
              {
                id: "u-demo",
                username: "demo",
                name: "Demo"
              }
            ],
            media: [
              {
                media_key: "m-video-2",
                type: "animated_gif",
                preview_image_url: "https://pbs.twimg.com/media/second.jpg",
                variants: [
                  {
                    bit_rate: 128000,
                    content_type: "video/mp4",
                    url: "https://video.twimg.com/demo/second.mp4"
                  }
                ]
              }
            ]
          },
          meta: {}
        })
      };
    }

    throw new Error(`Unexpected request: ${url.toString()}`);
  };

  const client = new XApiClient({
    bearerToken: "token",
    fetchImpl,
    maxPages: 2,
    pageSize: 100
  });

  const result = await client.discoverSource("demo");

  assert.equal(result.items.length, 2);
  assert.equal(result.items[0].tweetId, "3001");
  assert.equal(result.items[0].durationText, "0:12");
  assert.equal(result.items[1].tweetUrl, "https://x.com/demo/status/3002");
  assert.equal(result.rawPayload.pagesFetched, 2);
  assert.equal(
    requests.filter((request) => request.includes("/users/u-demo/tweets")).length,
    2
  );
});

test("XApiClient discoverSource reuses cached user id and forwards since_id", async () => {
  const requests = [];
  const fetchImpl = async (url) => {
    requests.push(url.toString());

    if (url.pathname.endsWith("/users/u-demo/tweets")) {
      assert.equal(url.searchParams.get("since_id"), "5000");

      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: [
            {
              id: "5002",
              author_id: "u-demo",
              text: "latest non-video",
              created_at: "2026-03-18T12:20:30.000Z"
            },
            {
              id: "5001",
              author_id: "u-demo",
              text: "latest video",
              created_at: "2026-03-18T11:20:30.000Z",
              attachments: {
                media_keys: ["m-video"]
              }
            }
          ],
          includes: {
            media: [
              {
                media_key: "m-video",
                type: "video",
                duration_ms: 15_000,
                preview_image_url: "https://pbs.twimg.com/media/latest.jpg",
                variants: [
                  {
                    bit_rate: 512000,
                    content_type: "video/mp4",
                    url: "https://video.twimg.com/demo/latest.mp4"
                  }
                ]
              }
            ]
          },
          meta: {}
        })
      };
    }

    throw new Error(`Unexpected request: ${url.toString()}`);
  };

  const client = new XApiClient({
    bearerToken: "token",
    fetchImpl,
    maxPages: 1,
    pageSize: 20
  });

  const result = await client.discoverSource("demo", {
    sourceUserId: "u-demo",
    sinceId: "5000"
  });

  assert.equal(result.items.length, 1);
  assert.equal(result.items[0].tweetId, "5001");
  assert.equal(result.rawPayload.userId, "u-demo");
  assert.equal(result.rawPayload.latestTweetId, "5002");
  assert.equal(
    requests.some((request) => request.includes("/users/by/username/")),
    false
  );
});

test("XApiClient resolveTweets normalizes results in input order", async () => {
  const fetchImpl = async (url) => {
    if (url.pathname.endsWith("/tweets")) {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: [
            {
              id: "4002",
              author_id: "u-demo",
              text: "two",
              created_at: "2026-03-18T11:20:30.000Z",
              attachments: {
                media_keys: ["m-two"]
              }
            },
            {
              id: "4001",
              author_id: "u-demo",
              text: "one https://t.co/demo",
              created_at: "2026-03-18T10:20:30.000Z",
              attachments: {
                media_keys: ["m-one"]
              }
            }
          ],
          includes: {
            users: [
              {
                id: "u-demo",
                username: "demo",
                name: "Demo",
                profile_image_url: "https://pbs.twimg.com/profile_images/demo_normal.jpg"
              }
            ],
            media: [
              {
                media_key: "m-one",
                type: "video",
                duration_ms: 21_000,
                preview_image_url: "https://pbs.twimg.com/media/one.jpg",
                variants: [
                  {
                    bit_rate: 832000,
                    content_type: "video/mp4",
                    url: "https://video.twimg.com/demo/one.mp4"
                  }
                ]
              },
              {
                media_key: "m-two",
                type: "video",
                duration_ms: 12_000,
                preview_image_url: "https://pbs.twimg.com/media/two.jpg",
                variants: []
              }
            ]
          }
        })
      };
    }

    throw new Error(`Unexpected request: ${url.toString()}`);
  };

  const client = new XApiClient({
    bearerToken: "token",
    fetchImpl
  });

  const results = await client.resolveTweets([
    { tweetId: "4001", sourceHandle: "demo" },
    { tweetId: "4002", sourceHandle: "demo" },
    { tweetId: "4003", sourceHandle: "demo" }
  ]);

  assert.equal(results[0].tweetId, "4001");
  assert.equal(results[0].status, "resolved");
  assert.equal(results[0].tweet.text, "one");
  assert.equal(results[0].mediaAssets[0].url, "https://video.twimg.com/demo/one.mp4");

  assert.equal(results[1].tweetId, "4002");
  assert.equal(results[1].status, "external_only");
  assert.equal(results[1].tweet.authorHandle, "demo");

  assert.equal(results[2].tweetId, "4003");
  assert.equal(results[2].status, "failed");
});
