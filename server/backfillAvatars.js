import { bootstrapApplication } from "./bootstrap.js";

const runtime = await bootstrapApplication({
  logger: console,
  enableHttpServer: false,
  enableScheduler: false
});
const limit = Number(process.env.BACKFILL_LIMIT || "");
const normalizedLimit = Number.isInteger(limit) && limit > 0 ? limit : null;

try {
  const result = await runtime.crawler.backfillMissingAvatars(normalizedLimit);
  console.log(JSON.stringify(result, null, 2));
} finally {
  await runtime.close();
}
