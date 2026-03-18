import test from "node:test";
import assert from "node:assert/strict";

import { openAuthBrowser, PlaywrightResolver } from "../lib/resolve/playwrightResolver.js";

test("PlaywrightResolver reuses an existing CDP browser context without closing the user's browser", async () => {
  let connectUrl = null;
  let contextClosed = false;
  let browserClosed = false;

  const context = {
    close: async () => {
      contextClosed = true;
    }
  };

  const browser = {
    contexts: () => [context],
    close: async () => {
      browserClosed = true;
    }
  };

  const resolver = new PlaywrightResolver({
    cdpUrl: "http://127.0.0.1:9222",
    chromiumImpl: {
      connectOverCDP: async (url) => {
        connectUrl = url;
        return browser;
      }
    }
  });

  const connectedContext = await resolver.ensureContext();

  assert.equal(connectUrl, "http://127.0.0.1:9222");
  assert.equal(connectedContext, context);

  await resolver.close();

  assert.equal(contextClosed, false);
  assert.equal(browserClosed, false);
});

test("openAuthBrowser opens X in the existing CDP browser and only closes the page it created", async () => {
  let gotoArgs = null;
  let pageClosed = false;
  let contextClosed = false;

  const page = {
    goto: async (...args) => {
      gotoArgs = args;
    },
    close: async () => {
      pageClosed = true;
    }
  };

  const context = {
    newPage: async () => page,
    close: async () => {
      contextClosed = true;
    }
  };

  const session = await openAuthBrowser({
    cdpUrl: "http://127.0.0.1:9222",
    chromiumImpl: {
      connectOverCDP: async () => ({
        contexts: () => [context]
      })
    }
  });

  assert.deepEqual(gotoArgs, ["https://x.com/home", { waitUntil: "domcontentloaded" }]);

  await session.close();

  assert.equal(pageClosed, true);
  assert.equal(contextClosed, false);
});

test("PlaywrightResolver falls back to the browser when syndication returns a tombstone", async () => {
  const originalFetch = global.fetch;
  let lastResponseHandler = null;

  global.fetch = async () => ({
    ok: true,
    json: async () => ({
      __typename: "TweetTombstone",
      id_str: "2021865942016569359"
    })
  });

  const page = {
    on: (event, handler) => {
      if (event === "response") {
        lastResponseHandler = handler;
      }
    },
    off: () => {},
    goto: async () => {
      await lastResponseHandler?.({
        status: () => 200,
        url: () => "https://x.com/i/api/graphql/test/TweetResultByRestId",
        headers: () => ({ "content-type": "application/json" }),
        text: async () =>
          JSON.stringify({
            data: {
              tweetResult: {
                result: {
                  rest_id: "2021865942016569359",
                  legacy: {
                    id_str: "2021865942016569359",
                    full_text: "你好香 https://t.co/demo",
                    created_at: "Wed Feb 12 08:36:00 +0000 2026",
                    extended_entities: {
                      media: [
                        {
                          type: "video",
                          media_url_https: "https://pbs.twimg.com/media/demo.jpg",
                          original_info: { width: 720, height: 1280 },
                          video_info: {
                            variants: [
                              {
                                url: "https://video.twimg.com/ext_tw_video/demo/pu/vid/720x1280/demo.mp4",
                                content_type: "video/mp4",
                                bitrate: 832000
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
                          name: "sunny",
                          screen_name: "77sunnyx",
                          profile_image_url_https: "https://pbs.twimg.com/profile_images/demo_normal.jpg"
                        }
                      }
                    }
                  }
                }
              }
            }
          })
      });
    },
    waitForLoadState: async () => {},
    waitForTimeout: async () => {},
    locator: () => ({
      innerText: async () => "tweet page"
    }),
    url: () => "https://x.com/77sunnyx/status/2021865942016569359",
    close: async () => {}
  };

  const context = {
    newPage: async () => page
  };

  const resolver = new PlaywrightResolver({
    cdpUrl: "http://127.0.0.1:9222",
    chromiumImpl: {
      connectOverCDP: async () => ({
        contexts: () => [context]
      })
    }
  });

  try {
    const result = await resolver.resolveTweet({
      tweetId: "2021865942016569359",
      tweetUrl: "https://x.com/77sunnyx/status/2021865942016569359"
    });

    assert.equal(result.status, "resolved");
    assert.equal(result.tweet.authorHandle, "77sunnyx");
    assert.equal(result.mediaAssets[0].url, "https://video.twimg.com/ext_tw_video/demo/pu/vid/720x1280/demo.mp4");
  } finally {
    global.fetch = originalFetch;
    await resolver.close();
  }
});
