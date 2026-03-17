import { ensureAppDirectories, resolveAppConfig } from "../config.js";

import { createCrawlerRuntime } from "./crawlerRuntime.js";
import { createHttpRuntime } from "./httpRuntime.js";
import { createSchedulerRuntime } from "./schedulerRuntime.js";

export async function createApplicationRuntime({
  logger = console,
  config = resolveAppConfig(),
  enableHttpServer = true,
  enableScheduler = true
} = {}) {
  ensureAppDirectories(config);

  const crawlerRuntime = createCrawlerRuntime({
    config,
    logger
  });
  const httpRuntime = createHttpRuntime({
    config,
    db: crawlerRuntime.db,
    crawler: crawlerRuntime.crawler,
    logger,
    enabled: enableHttpServer
  });
  const schedulerRuntime = createSchedulerRuntime({
    config,
    crawler: crawlerRuntime.crawler,
    logger,
    enabled: enableScheduler
  });

  return {
    config,
    logger,
    ...crawlerRuntime,
    app: httpRuntime.app,
    httpServer: httpRuntime.httpServer,
    scheduler: schedulerRuntime.scheduler,
    async startHttpServer(port = config.port) {
      return httpRuntime.start(port);
    },
    runInitialCrawl() {
      return schedulerRuntime.runInitialCrawl();
    },
    async close() {
      schedulerRuntime.stop();
      await httpRuntime.close();
      await crawlerRuntime.resolverClient.close().catch(() => {});
      crawlerRuntime.db.close();
    }
  };
}

export { createApplicationRuntime as bootstrapApplication };
