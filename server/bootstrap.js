import { createServer } from "node:http";

import { createApiApp } from "./api.js";
import { resolveAppConfig, ensureAppDirectories, loadSourceConfig } from "./config.js";
import { CrawlOrchestrator } from "./crawler.js";
import { AppDatabase } from "./db.js";
import { startScheduler } from "./scheduler.js";
import { PlaywrightResolver } from "./xResolver.js";

export async function bootstrapApplication({ logger = console } = {}) {
  const config = resolveAppConfig();
  ensureAppDirectories(config);
  const db = new AppDatabase(config.dbPath);
  const resolverClient = new PlaywrightResolver({
    browserProfileDir: config.browserProfileDir,
    storageStatePath: config.storageStatePath,
    logger
  });
  const crawler = new CrawlOrchestrator({
    db,
    discoveryClient: resolverClient,
    resolverClient,
    logger
  });

  crawler.syncSources(loadSourceConfig(config));

  const app = createApiApp({
    db,
    publicDir: config.publicDir,
    crawler,
    logger
  });
  const httpServer = createServer(app);
  const scheduler = startScheduler({
    intervalMinutes: config.scrapeIntervalMinutes,
    task: () => crawler.crawlOnce(),
    logger
  });

  return {
    config,
    db,
    crawler,
    resolverClient,
    httpServer,
    scheduler
  };
}
