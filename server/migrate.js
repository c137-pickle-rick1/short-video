import { ensureAppDirectories, resolveAppConfig } from "./config.js";
import { AppDatabase } from "./db.js";

const logger = console;

const config = resolveAppConfig();
ensureAppDirectories(config);

const db = new AppDatabase(config.dbPath, {
  runMigrations: true
});

try {
  const appliedCount = db.appliedMigrationIds.length;

  if (appliedCount > 0) {
    logger.info(`Applied ${appliedCount} migration(s): ${db.appliedMigrationIds.join(", ")}`);
  } else {
    logger.info("No pending migrations");
  }
} finally {
  db.close();
}
