import test from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import path from "node:path";

import { chromium } from "playwright";

import { createApiApp } from "../server/api.js";
import { createTempDb } from "./helpers.js";

function insertResolvedTweet(db, sourceId, index) {
  const tweetId = String(4000 + index);

  db.insertDiscoveredTweet({
    tweetId,
    sourceId,
    tweetUrl: `https://x.com/demo/status/${tweetId}`,
    durationText: `0:${String((index % 50) + 10).padStart(2, "0")}`,
    rawDiscoveryPayload: { link: `x-${tweetId}` }
  });

  db.applyResolution(tweetId, {
    status: "resolved",
    tweet: {
      authorHandle: "demo",
      authorName: "Demo",
      authorAvatarUrl: "https://example.com/avatar.jpg",
      text: `浏览器测试卡片 ${index + 1}`,
      postedAt: new Date(Date.UTC(2025, 1, 14, 6, 37, index)).toISOString(),
      posterUrl: "https://example.com/poster.jpg"
    },
    mediaAssets: [
      {
        url: "https://example.com/video.mp4",
        bitrate: 1000,
        contentType: "video/mp4",
        width: 720,
        height: 1280,
        sortOrder: 0,
        isPrimary: true
      }
    ]
  });
}

async function startHttpServer(app) {
  const httpServer = createServer(app);

  await new Promise((resolve, reject) => {
    httpServer.once("error", reject);
    httpServer.listen(0, "127.0.0.1", () => {
      httpServer.off("error", reject);
      resolve();
    });
  });

  const address = httpServer.address();
  if (!address || typeof address === "string") {
    throw new Error("Failed to resolve test server address");
  }

  return {
    httpServer,
    baseUrl: `http://127.0.0.1:${address.port}`
  };
}

async function stubRemoteMedia(page) {
  await page.route("https://example.com/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "image/svg+xml",
      body: `<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"></svg>`
    })
  );
}

test("browser smoke keeps the SSR feed interactive after hydration", async () => {
  const temp = createTempDb();
  let browser;
  let page;
  let httpServer;

  try {
    const [source] = temp.db.syncSources([{ handle: "demo", enabled: true }]);
    for (let index = 0; index < 12; index += 1) {
      insertResolvedTweet(temp.db, source.id, index);
    }

    const app = createApiApp({
      db: temp.db,
      crawler: {},
      publicDir: path.resolve(process.cwd(), "public"),
      fetchImpl: async () =>
        new Response("", {
          status: 200,
          headers: {
            "content-type": "video/mp4",
            "cache-control": "public, max-age=60"
          }
        })
    });
    const server = await startHttpServer(app);
    httpServer = server.httpServer;

    browser = await chromium.launch({ headless: true });
    page = await browser.newPage({
      viewport: {
        width: 1024,
        height: 640
      }
    });

    const pageErrors = [];
    const disallowedRequests = [];
    page.on("pageerror", (error) => {
      pageErrors.push(error);
    });
    page.on("request", (request) => {
      const url = request.url();
      if (
        url.startsWith("https://cdn.plyr.io/") ||
        url.startsWith("https://unpkg.com/") ||
        url.startsWith("https://fonts.googleapis.com/") ||
        url.startsWith("https://fonts.gstatic.com/")
      ) {
        disallowedRequests.push(url);
      }
    });

    await stubRemoteMedia(page);
    await page.goto(`${server.baseUrl}/?source=demo`, {
      waitUntil: "domcontentloaded"
    });

    await page.waitForFunction(() => {
      return document.querySelectorAll(".feed-grid-item[data-tweet-id]").length >= 8;
    });
    await page.waitForFunction(() => {
      const summary = document.querySelector("#feed-summary");
      return summary?.textContent?.includes("已展示 8 条");
    });

    const sourceValue = await page.locator("#source-filter").inputValue();
    assert.equal(sourceValue, "demo");

    const initialCount = await page.locator(".feed-grid-item[data-tweet-id]").count();
    assert.equal(initialCount, 8);

    for (let attempt = 0; attempt < 4; attempt += 1) {
      if ((await page.locator(".feed-grid-item[data-tweet-id]").count()) >= 12) {
        break;
      }

      await page.locator("#feed-sentinel").scrollIntoViewIfNeeded();
      await page.waitForTimeout(250);
    }

    await page.waitForFunction(() => {
      return document.querySelectorAll(".feed-grid-item[data-tweet-id]").length >= 12;
    });
    await page.waitForFunction(() => {
      const summary = document.querySelector("#feed-summary");
      return summary?.textContent?.includes("已展示 12 条");
    });

    await page.locator("[data-feed-detail-trigger='true']").first().click();
    await page.locator("#feed-detail-modal").waitFor({ state: "visible" });
    await page.locator("#detail-modal-title").waitFor({ state: "visible" });

    const modalTitle = await page.locator("#detail-modal-title").textContent();
    assert.match(modalTitle || "", /浏览器测试卡片/);

    await page.keyboard.press("Escape");
    await page.waitForFunction(() => {
      const modal = document.querySelector("#feed-detail-modal");
      return modal instanceof HTMLElement && modal.hidden;
    });

    assert.deepEqual(disallowedRequests, []);
    assert.equal(pageErrors.length, 0);
  } finally {
    await page?.close().catch(() => {});
    await browser?.close().catch(() => {});
    if (httpServer) {
      await new Promise((resolve) => httpServer.close(() => resolve()));
    }
    temp.cleanup();
  }
});
