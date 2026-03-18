import test from "node:test";
import assert from "node:assert/strict";

import {
  extractMediaGridVideoLinks,
  normalizeSyndicationTweet,
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

test("normalizeSyndicationTweet maps public tweet-result payload into resolved media assets", () => {
  const payload = {
    id_str: "2003489517244940493",
    text: "Santa’s calling, imma change real quick https://t.co/nfty70r0Hv",
    created_at: "2025-12-23T15:34:58.000Z",
    user: {
      name: "Alexis Mucci",
      screen_name: "alexmucci_",
      profile_image_url_https:
        "https://pbs.twimg.com/profile_images/1960477159538978816/XKNCPjo__normal.jpg"
    },
    mediaDetails: [
      {
        type: "video",
        media_url_https:
          "https://pbs.twimg.com/amplify_video_thumb/2003489046308233216/img/9Gpgbp1kXxyymAMB.jpg",
        original_info: {
          width: 2160,
          height: 3840
        },
        video_info: {
          variants: [
            {
              content_type: "application/x-mpegURL",
              url: "https://video.twimg.com/amplify_video/2003489046308233216/pl/HT5vXhKvpCOv2kQZ.m3u8"
            },
            {
              bitrate: 25128000,
              content_type: "video/mp4",
              url: "https://video.twimg.com/amplify_video/2003489046308233216/vid/avc1/2160x3840/zWz1Tj_8hN4tgUgQ.mp4"
            }
          ]
        }
      }
    ]
  };

  const tweet = normalizeSyndicationTweet(payload);

  assert.ok(tweet);
  assert.equal(tweet.tweetId, "2003489517244940493");
  assert.equal(tweet.authorHandle, "alexmucci_");
  assert.equal(tweet.authorAvatarUrl, "https://pbs.twimg.com/profile_images/1960477159538978816/XKNCPjo__400x400.jpg");
  assert.equal(tweet.text, "Santa’s calling, imma change real quick");
  assert.equal(tweet.mediaAssets.length, 2);
  assert.equal(tweet.mediaAssets[0].isPrimary, true);
});

test("normalizeSyndicationTweet returns null for tombstones", () => {
  const tweet = normalizeSyndicationTweet({
    __typename: "TweetTombstone",
    tombstone: {}
  });

  assert.equal(tweet, null);
});
