export function startScheduler({ intervalMinutes = 10, task, logger = console }) {
  let running = false;

  async function run() {
    if (running) {
      logger.info("scheduler: previous crawl still running, skipping");
      return;
    }

    running = true;
    try {
      await task();
    } catch (error) {
      logger.error("scheduler run failed", error);
    } finally {
      running = false;
    }
  }

  const timer = setInterval(run, intervalMinutes * 60 * 1000);
  return {
    run,
    stop() {
      clearInterval(timer);
    }
  };
}
