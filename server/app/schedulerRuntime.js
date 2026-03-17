import { startScheduler } from "../scheduler.js";

function createNoopScheduler() {
  return {
    async run() {
      return null;
    },
    stop() {}
  };
}

export function createSchedulerRuntime({
  config,
  crawler,
  logger = console,
  enabled = true,
  startSchedulerImpl = startScheduler
} = {}) {
  if (!enabled) {
    const scheduler = createNoopScheduler();

    return {
      scheduler,
      runInitialCrawl() {
        return scheduler.run();
      },
      stop() {
        scheduler.stop();
      }
    };
  }

  const scheduler = startScheduler({
    intervalMinutes: config.scrapeIntervalMinutes,
    task: () => crawler.crawlOnce(),
    logger
  });

  return {
    scheduler,
    runInitialCrawl() {
      return scheduler.run();
    },
    stop() {
      scheduler.stop();
    }
  };
}
