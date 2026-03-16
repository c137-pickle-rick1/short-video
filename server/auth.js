import { ensureAppDirectories, resolveAppConfig } from "./config.js";
import { openAuthBrowser } from "./xResolver.js";

const config = resolveAppConfig();
ensureAppDirectories(config);

const context = await openAuthBrowser({
  browserProfileDir: config.browserProfileDir
});

console.log("Browser profile opened. Log into X, then press Ctrl+C to close.");

async function shutdown() {
  await context.close().catch(() => {});
  process.exit(0);
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);

await new Promise(() => {});
