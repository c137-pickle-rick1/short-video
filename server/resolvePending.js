import { bootstrapApplication } from "./bootstrap.js";

const runtime = await bootstrapApplication({
  logger: console,
  enableHttpServer: false,
  enableScheduler: false
});

try {
  const result = await runtime.crawler.resolvePending();
  console.log(JSON.stringify(result, null, 2));
} finally {
  await runtime.close();
}
