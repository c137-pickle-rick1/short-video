import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import { createApplicationRuntime } from "../server/app/runtime.js";

function createRuntimeConfig(tempDir) {
  return {
    cwd: process.cwd(),
    port: 3000,
    dbPath: path.join(tempDir, "app.db"),
    browserProfileDir: path.join(tempDir, "browser-profile"),
    storageStatePath: path.join(tempDir, "x-storage-state.json"),
    scrapeIntervalMinutes: 10,
    discoveryMode: "hybrid",
    mediaProxyTimeoutMs: 15000,
    runMigrationsOnBoot: true,
    sourceConfigPath: path.join(tempDir, "sources.json"),
    publicDir: path.resolve(process.cwd(), "public")
  };
}

test("createApplicationRuntime can build a CLI-friendly runtime without http or scheduler", async () => {
  const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), "short-video-runtime-"));
  const runtime = await createApplicationRuntime({
    config: createRuntimeConfig(tempDir),
    logger: {
      info() {},
      error() {}
    },
    enableHttpServer: false,
    enableScheduler: false
  });

  try {
    assert.equal(runtime.httpServer, null);
    assert.equal(typeof runtime.crawler.crawlOnce, "function");
    assert.equal(typeof runtime.scheduler.run, "function");
  } finally {
    await runtime.close();
    fs.rmSync(tempDir, { recursive: true, force: true });
  }
});
