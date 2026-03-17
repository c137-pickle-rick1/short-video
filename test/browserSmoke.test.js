import test from "node:test";
import assert from "node:assert/strict";
import { createServer } from "node:http";
import { spawn } from "node:child_process";
import { promisify } from "node:util";
import { execFile } from "node:child_process";
import { mkdtemp, rm, writeFile } from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import net from "node:net";

import Database from "better-sqlite3";
import { chromium } from "playwright";

const execFileAsync = promisify(execFile);
const TEST_APP_KEY = "base64:4xxewHMiYHhwrfHSpyO2bRqZj+yx7G8DhNxYI7JUns0=";

function createRangeAwareVideoResponse(request, response, videoBuffer) {
  const range = request.headers.range;
  if (!range) {
    response.writeHead(200, {
      "content-type": "video/mp4",
      "content-length": videoBuffer.length,
      "cache-control": "public, max-age=60",
      "accept-ranges": "bytes"
    });
    response.end(videoBuffer);
    return;
  }

  const match = /bytes=(\d+)-(\d*)/.exec(range);
  if (!match) {
    response.writeHead(416);
    response.end();
    return;
  }

  const start = Number(match[1]);
  const end = match[2] ? Number(match[2]) : videoBuffer.length - 1;
  const chunk = videoBuffer.subarray(start, Math.min(end + 1, videoBuffer.length));

  response.writeHead(206, {
    "content-type": "video/mp4",
    "content-length": chunk.length,
    "cache-control": "public, max-age=60",
    "accept-ranges": "bytes",
    "content-range": `bytes ${start}-${start + chunk.length - 1}/${videoBuffer.length}`
  });
  response.end(chunk);
}

async function startUpstreamServer() {
  const videoBuffer = Buffer.alloc(4096, 7);
  const httpServer = createServer((request, response) => {
    if ((request.url || "").endsWith(".mp4")) {
      createRangeAwareVideoResponse(request, response, videoBuffer);
      return;
    }

    response.writeHead(200, {
      "content-type": "image/svg+xml",
      "cache-control": "public, max-age=60"
    });
    response.end('<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8"></svg>');
  });

  await new Promise((resolve, reject) => {
    httpServer.once("error", reject);
    httpServer.listen(0, "127.0.0.1", () => {
      httpServer.off("error", reject);
      resolve();
    });
  });

  const address = httpServer.address();
  if (!address || typeof address === "string") {
    throw new Error("Failed to resolve upstream server address");
  }

  return {
    httpServer,
    baseUrl: `http://127.0.0.1:${address.port}`
  };
}

async function getFreePort() {
  return await new Promise((resolve, reject) => {
    const server = net.createServer();
    server.once("error", reject);
    server.listen(0, "127.0.0.1", () => {
      const address = server.address();
      server.close(() => {
        if (!address || typeof address === "string") {
          reject(new Error("Failed to allocate test port"));
          return;
        }

        resolve(address.port);
      });
    });
  });
}

async function migrateDatabase({ dbPath, sourcesPath }) {
  await writeFile(dbPath, "");

  await execFileAsync(
    "php",
    ["artisan", "migrate", "--force"],
    {
      cwd: path.resolve(process.cwd(), "laravel"),
      env: {
        ...process.env,
        APP_KEY: TEST_APP_KEY,
        DB_PATH: dbPath,
        SOURCE_CONFIG_PATH: sourcesPath,
        RUN_MIGRATIONS_ON_BOOT: "false",
        APP_ENV: "testing"
      }
    }
  );
}

function seedDatabase({ dbPath, mediaBaseUrl }) {
  const db = new Database(dbPath);
  const insertSource = db.prepare(
    "INSERT INTO sources (id, handle, enabled, last_discovered_at) VALUES (?, ?, ?, ?)"
  );
  const insertTweet = db.prepare(`
    INSERT INTO tweets (
      tweet_id, source_id, tweet_url, author_handle, author_name, author_avatar_url, text,
      posted_at, duration_text, poster_url, status, raw_discovery_payload, raw_resolve_payload,
      ingested_at, resolved_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);
  const insertAsset = db.prepare(`
    INSERT INTO media_assets (
      tweet_id, url, bitrate, content_type, width, height, sort_order, is_primary
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  `);

  insertSource.run(1, "demo", 1, "2025-02-14T06:37:00.000Z");

  for (let index = 0; index < 12; index += 1) {
    const tweetId = String(4000 + index);
    const postedAt = new Date(Date.UTC(2025, 1, 14, 6, 37, index)).toISOString();
    const discoveryPayload = JSON.stringify({
      link: `x-${tweetId}`,
      durationText: `0:${String((index % 50) + 10).padStart(2, "0")}`
    });
    const resolvePayload = JSON.stringify({
      status: "resolved",
      tweetId
    });

    insertTweet.run(
      tweetId,
      1,
      `https://x.com/demo/status/${tweetId}`,
      "demo",
      "Demo",
      `${mediaBaseUrl}/avatar.svg`,
      `浏览器测试卡片 ${index + 1}`,
      postedAt,
      `0:${String((index % 50) + 10).padStart(2, "0")}`,
      `${mediaBaseUrl}/poster.svg`,
      "resolved",
      discoveryPayload,
      resolvePayload,
      postedAt,
      postedAt
    );

    insertAsset.run(
      tweetId,
      `${mediaBaseUrl}/video.mp4`,
      1000,
      "video/mp4",
      720,
      1280,
      0,
      1
    );
  }

  db.close();
}

async function waitForHealthy(baseUrl, serverProcess) {
  const deadline = Date.now() + 20000;
  let lastError = "";

  while (Date.now() < deadline) {
    if (serverProcess.exitCode !== null) {
      throw new Error(`Laravel server exited early.\n${lastError}`);
    }

    try {
      const response = await fetch(`${baseUrl}/api/health`);
      if (response.ok) {
        return;
      }
      lastError = `HTTP ${response.status}`;
    } catch (error) {
      lastError = String(error);
    }

    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  throw new Error(`Timed out waiting for Laravel server.\n${lastError}`);
}

test("browser smoke keeps the Laravel SSR feed interactive after hydration", async () => {
  const tempDir = await mkdtemp(path.join(os.tmpdir(), "short-video-browser-smoke-"));
  const dbPath = path.join(tempDir, "app.db");
  const sourcesPath = path.join(tempDir, "sources.json");
  let upstreamServer;
  let browser;
  let page;
  let serverProcess;

  try {
    await writeFile(
      sourcesPath,
      JSON.stringify([{ handle: "demo", enabled: true }], null, 2)
    );

    upstreamServer = await startUpstreamServer();
    await migrateDatabase({ dbPath, sourcesPath });
    seedDatabase({ dbPath, mediaBaseUrl: upstreamServer.baseUrl });

    const port = await getFreePort();
    const serverLogs = [];
    serverProcess = spawn(
      "php",
      [
        "-S",
        `127.0.0.1:${port}`,
        path.resolve(
          process.cwd(),
          "laravel",
          "vendor",
          "laravel",
          "framework",
          "src",
          "Illuminate",
          "Foundation",
          "resources",
          "server.php"
        )
      ],
      {
        cwd: path.resolve(process.cwd(), "laravel", "public"),
        env: {
          ...process.env,
          APP_KEY: TEST_APP_KEY,
          DB_PATH: dbPath,
          SOURCE_CONFIG_PATH: sourcesPath,
          RUN_MIGRATIONS_ON_BOOT: "false",
          APP_ENV: "testing"
        },
        stdio: ["ignore", "pipe", "pipe"]
      }
    );

    serverProcess.stdout.on("data", (chunk) => {
      serverLogs.push(String(chunk));
    });
    serverProcess.stderr.on("data", (chunk) => {
      serverLogs.push(String(chunk));
    });

    const baseUrl = `http://127.0.0.1:${port}`;
    await waitForHealthy(baseUrl, serverProcess);

    browser = await chromium.launch({ headless: true });
    page = await browser.newPage({
      viewport: {
        width: 1440,
        height: 900
      }
    });

    const pageErrors = [];
    const disallowedRequests = [];
    const mediaRequests = [];
    page.on("pageerror", (error) => {
      pageErrors.push(error);
    });
    page.on("request", (request) => {
      const url = request.url();
      if (url.includes("/api/media/")) {
        mediaRequests.push(url);
      }
      if (
        url.startsWith("https://cdn.plyr.io/") ||
        url.startsWith("https://unpkg.com/") ||
        url.startsWith("https://fonts.googleapis.com/") ||
        url.startsWith("https://fonts.gstatic.com/")
      ) {
        disallowedRequests.push(url);
      }
    });

    await page.goto(`${baseUrl}/?source=demo`, {
      waitUntil: "domcontentloaded"
    });

    await page.waitForFunction(() => {
      return document.querySelectorAll(".feed-grid-item[data-tweet-id]").length >= 8;
    });
    assert.equal(await page.locator("#source-filter").count(), 0);
    assert.equal(await page.locator("#feed-summary").count(), 0);
    assert.equal(await page.locator("#feed-status").count(), 0);

    const initialCardLayout = await page.evaluate(() => {
      const card = document.querySelector(".feed-grid-item[data-tweet-id]");
      const mediaFrame = card?.querySelector(":scope > div");
      const title = card?.querySelector("p");

      return {
        aspectRatio: mediaFrame ? getComputedStyle(mediaFrame).aspectRatio : "",
        lineClamp: title ? getComputedStyle(title).webkitLineClamp : ""
      };
    });
    assert.notEqual(initialCardLayout.aspectRatio, "auto");
    assert.equal(initialCardLayout.lineClamp, "2");
    assert.equal(mediaRequests.length, 0);

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

    await page.locator("[data-feed-detail-trigger='true']").first().click();
    await page.locator("#feed-detail-modal").waitFor({ state: "visible" });
    await page.locator("#detail-modal-title").waitFor({ state: "visible" });
    await page.waitForFunction(() =>
      performance.getEntriesByType("resource").some((entry) => entry.name.includes("/api/media/"))
    );

    const modalTitle = await page.locator("#detail-modal-title").textContent();
    assert.match(modalTitle || "", /浏览器测试卡片/);

    const modalLayout = await page.evaluate(() => {
      const panel = document.querySelector("#feed-detail-modal-panel");
      const mediaShell = panel?.querySelector(".detail-modal-media-shell");
      const aside = panel?.querySelector("aside");

      return {
        panelWidth: panel?.getBoundingClientRect().width ?? 0,
        mediaWidth: mediaShell?.getBoundingClientRect().width ?? 0,
        asideWidth: aside?.getBoundingClientRect().width ?? 0
      };
    });
    assert.ok(modalLayout.panelWidth > 0);
    assert.ok(modalLayout.mediaWidth > modalLayout.asideWidth);
    assert.ok(modalLayout.asideWidth >= 380 && modalLayout.asideWidth <= 480);

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
    if (serverProcess) {
      const exitPromise = new Promise((resolve) => {
        serverProcess.once("exit", () => resolve());
        setTimeout(resolve, 1500);
      });
      serverProcess.kill("SIGTERM");
      await exitPromise;
      if (serverProcess.exitCode === null) {
        serverProcess.kill("SIGKILL");
        await new Promise((resolve) => {
          serverProcess.once("exit", () => resolve());
          setTimeout(resolve, 1500);
        });
      }
    }
    if (upstreamServer?.httpServer) {
      await new Promise((resolve) => upstreamServer.httpServer.close(() => resolve()));
    }
    await rm(tempDir, { recursive: true, force: true }).catch(() => {});
  }
});
