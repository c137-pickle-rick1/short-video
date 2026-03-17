import test from "node:test";
import assert from "node:assert/strict";

import {
  extractMediaGridVideoLinks,
  normalizeResolvedTweet,
  selectTweetFromPayload
} from "../lib/xResolver.js";

const samplePayload = {
  data: {
    threaded_conversation_with_injections_v2: {
      instructions: [
        {
          entries: [
            {
              content: {
                itemContent: {
                  tweet_results: {
                    result: {
                      rest_id: "777",
                      legacy: {
                        id_str: "777",
                        full_text: "Video post body",
                        created_at: "Fri Feb 14 06:37:00 +0000 2025",
                        extended_entities: {
                          media: [
                            {
                              type: "video",
                              media_url_https:
                                "https://pbs.twimg.com/ext_tw_video_thumb/777/pu/img/poster.jpg",
                              original_info: {
                                width: 720,
                                height: 1280
                              },
                              video_info: {
                                variants: [
                                  {
                                    content_type: "application/x-mpegURL",
                                    url: "https://video.twimg.com/ext_tw_video/777/pu/pl/master.m3u8"
                                  },
                                  {
                                    bitrate: 832000,
                                    content_type: "video/mp4",
                                    url: "https://video.twimg.com/ext_tw_video/777/pu/vid/360x640/a.mp4"
                                  },
                                  {
                                    bitrate: 2176000,
                                    content_type: "video/mp4",
                                    url: "https://video.twimg.com/ext_tw_video/777/pu/vid/720x1280/b.mp4"
                                  }
                                ]
                              }
                            }
                          ]
                        }
                      },
                      core: {
                        user_results: {
                          result: {
                            legacy: {
                              name: "Jina AI",
                              screen_name: "JinaAI_",
                              profile_image_url_https:
                                "https://pbs.twimg.com/profile_images/123/avatar_normal.jpg"
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          ]
        }
      ]
    }
  }
};

test("selectTweetFromPayload finds target tweet in nested GraphQL payload", () => {
  const tweetNode = selectTweetFromPayload(samplePayload, "777");
  assert.ok(tweetNode);
  assert.equal(tweetNode.rest_id, "777");
});

test("normalizeResolvedTweet keeps direct HLS manifests while preferring the highest bitrate mp4 as primary", () => {
  const tweetNode = selectTweetFromPayload(samplePayload, "777");
  const tweet = normalizeResolvedTweet(tweetNode, "777");

  assert.equal(tweet.authorHandle, "JinaAI_");
  assert.equal(
    tweet.authorAvatarUrl,
    "https://pbs.twimg.com/profile_images/123/avatar_400x400.jpg"
  );
  assert.equal(tweet.mediaAssets.length, 3);
  assert.equal(tweet.mediaAssets[0].isPrimary, true);
  assert.equal(
    tweet.mediaAssets[0].url,
    "https://video.twimg.com/ext_tw_video/777/pu/vid/720x1280/b.mp4"
  );
  assert.equal(
    tweet.mediaAssets[2].url,
    "https://video.twimg.com/ext_tw_video/777/pu/pl/master.m3u8"
  );
});

test("normalizeResolvedTweet strips trailing links from tweet text", () => {
  const payload = structuredClone(samplePayload);
  payload.data.threaded_conversation_with_injections_v2.instructions[0].entries[0].content.itemContent.tweet_results.result.legacy.full_text =
    "Video post body https://t.co/example";

  const tweetNode = selectTweetFromPayload(payload, "777");
  const tweet = normalizeResolvedTweet(tweetNode, "777");

  assert.equal(tweet.text, "Video post body");
});

test("extractMediaGridVideoLinks keeps only canonical video links for the target handle", () => {
  const links = [
    { href: "/alexmucci_/status/111/video/1", durationText: "0:10" },
    { href: "https://x.com/alexmucci_/status/111/video/2", durationText: "0:10" },
    { href: "https://twitter.com/alexmucci_/status/222/video/1" },
    { href: "/alexmucci_/status/333/photo/1" },
    { href: "/other/status/444/video/1" }
  ];

  const items = extractMediaGridVideoLinks(links, "AlexMucci_");

  assert.deepEqual(
    items.map((item) => item.tweetId),
    ["111", "222"]
  );
  assert.equal(items[0].tweetUrl, "https://x.com/alexmucci_/status/111");
  assert.equal(items[0].durationText, "0:10");
});
