import { bootstrapApplication } from "./bootstrap.js";

const runtime = await bootstrapApplication({ logger: console });
const limit = Number(process.env.BACKFILL_LIMIT || "");
const normalizedLimit = Number.isInteger(limit) && limit > 0 ? limit : null;

try {
  const result = await runtime.crawler.backfillMissingAvatars(normalizedLimit);
  console.log(JSON.stringify(result, null, 2));
} finally {
  runtime.scheduler.stop();
  await runtime.resolverClient.close().catch(() => {});
  runtime.db.close();
  runtime.httpServer.close();
}
