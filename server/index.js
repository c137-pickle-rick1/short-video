import { bootstrapApplication } from "./bootstrap.js";

const logger = console;
const runtime = await bootstrapApplication({ logger });

await runtime.startHttpServer();
logger.info(`short-video listening on http://localhost:${runtime.config.port}`);

runtime.runInitialCrawl().catch((error) => {
  logger.error("initial crawl failed", error);
});

async function shutdown() {
  await runtime.close();
  process.exit(0);
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);
