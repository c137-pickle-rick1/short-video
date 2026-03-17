import { nowIso } from "../shared.js";

import { migration001InitialSchema } from "./001_initialSchema.js";
import { migration002AddAuthorAvatarUrlToTweets } from "./002_addAuthorAvatarUrlToTweets.js";
import { migration003AddDurationTextToTweets } from "./003_addDurationTextToTweets.js";
import { migration004BackfillTweetDurations } from "./004_backfillTweetDurations.js";

export const DATABASE_MIGRATIONS = [
  migration001InitialSchema,
  migration002AddAuthorAvatarUrlToTweets,
  migration003AddDurationTextToTweets,
  migration004BackfillTweetDurations
];

export function runDatabaseMigrations(db) {
  db.exec(`
    CREATE TABLE IF NOT EXISTS schema_migrations (
      id TEXT PRIMARY KEY,
      applied_at TEXT NOT NULL
    )
  `);

  const appliedMigrationIds = new Set(
    db
      .prepare(
        `
          SELECT id
          FROM schema_migrations
          ORDER BY id ASC
        `
      )
      .all()
      .map((row) => row.id)
  );
  const recordMigration = db.prepare(`
    INSERT INTO schema_migrations (id, applied_at)
    VALUES (?, ?)
  `);
  const newlyAppliedMigrationIds = [];

  for (const migration of DATABASE_MIGRATIONS) {
    if (appliedMigrationIds.has(migration.id)) {
      continue;
    }

    db.transaction(() => {
      migration.run(db);
      recordMigration.run(migration.id, nowIso());
    })();
    newlyAppliedMigrationIds.push(migration.id);
  }

  return newlyAppliedMigrationIds;
}
