import { loadSourceConfig } from "../config.js";
import { CrawlOrchestrator } from "../crawler.js";
import { AppDatabase } from "../db.js";
import { createDiscoveryClient } from "../infra/discovery/compositeDiscoveryClient.js";
import { JinaDiscoveryClient } from "../infra/discovery/jinaDiscoveryClient.js";
import { PlaywrightResolver } from "../infra/resolve/playwrightResolver.js";

export function createCrawlerRuntime({ config, logger = console } = {}) {
  const db = new AppDatabase(config.dbPath, {
    runMigrations: config.runMigrationsOnBoot
  });
  const resolverClient = new PlaywrightResolver({
    browserProfileDir: config.browserProfileDir,
    storageStatePath: config.storageStatePath,
    logger
  });
  const discoveryClient = createDiscoveryClient({
    mode: config.discoveryMode,
    primaryClient: new JinaDiscoveryClient({ logger }),
    fallbackClient: resolverClient,
    logger
  });
  const crawler = new CrawlOrchestrator({
    db,
    discoveryClient,
    resolverClient,
    logger
  });

  crawler.syncSources(loadSourceConfig(config));

  return {
    db,
    resolverClient,
    discoveryClient,
    crawler
  };
}
