import { bootstrapApplication } from "./bootstrap.js";

const runtime = await bootstrapApplication({ logger: console });

try {
  const result = await runtime.crawler.resolvePending();
  console.log(JSON.stringify(result, null, 2));
} finally {
  runtime.scheduler.stop();
  await runtime.resolverClient.close().catch(() => {});
  runtime.db.close();
  runtime.httpServer.close();
}
