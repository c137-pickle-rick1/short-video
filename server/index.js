import { bootstrapApplication } from "./bootstrap.js";

const logger = console;
const runtime = await bootstrapApplication({ logger });

runtime.httpServer.listen(runtime.config.port, () => {
  logger.info(`short-video listening on http://localhost:${runtime.config.port}`);
});

runtime.scheduler.run().catch((error) => {
  logger.error("initial crawl failed", error);
});

async function shutdown() {
  runtime.scheduler.stop();
  runtime.httpServer.close();
  await runtime.resolverClient.close().catch(() => {});
  runtime.db.close();
  process.exit(0);
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);
