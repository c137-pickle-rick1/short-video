import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";

import Database from "better-sqlite3";

import { AppDatabase } from "../server/db.js";
import { DATABASE_MIGRATIONS } from "../server/db/migrations/index.js";
import { createTempDb } from "./helpers.js";

function getAppliedMigrationIds(db) {
  return db
    .prepare(
      `
        SELECT id
        FROM schema_migrations
        ORDER BY id ASC
      `
    )
    .all()
    .map((row) => row.id);
}

test("AppDatabase records explicit migrations for a fresh database", () => {
  const temp = createTempDb();

  try {
    assert.deepEqual(
      getAppliedMigrationIds(temp.db.db),
      DATABASE_MIGRATIONS.map((migration) => migration.id)
    );
  } finally {
    temp.cleanup();
  }
});

test("AppDatabase upgrades a legacy tweets schema through explicit migrations", () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), "short-video-legacy-"));
  const dbPath = path.join(dir, "legacy.db");
  const legacyDb = new Database(dbPath);

  try {
    legacyDb.exec(`
      PRAGMA foreign_keys = ON;

      CREATE TABLE sources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        handle TEXT NOT NULL UNIQUE,
        enabled INTEGER NOT NULL DEFAULT 1,
        last_discovered_at TEXT
      );

      CREATE TABLE tweets (
        tweet_id TEXT PRIMARY KEY,
        source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE,
        tweet_url TEXT NOT NULL,
        author_handle TEXT,
        author_name TEXT,
        text TEXT,
        posted_at TEXT,
        poster_url TEXT,
        status TEXT NOT NULL CHECK (status IN ('pending', 'resolved', 'external_only', 'skipped', 'failed')),
        raw_discovery_payload TEXT,
        raw_resolve_payload TEXT,
        ingested_at TEXT NOT NULL,
        resolved_at TEXT
      );
    `);

    legacyDb.prepare("INSERT INTO sources (id, handle, enabled) VALUES (1, 'demo', 1)").run();
    legacyDb
      .prepare(
        `
          INSERT INTO tweets (
            tweet_id,
            source_id,
            tweet_url,
            author_handle,
            author_name,
            text,
            posted_at,
            poster_url,
            status,
            raw_discovery_payload,
            raw_resolve_payload,
            ingested_at,
            resolved_at
          )
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `
      )
      .run(
        "legacy-1",
        1,
        "https://x.com/demo/status/legacy-1",
        "demo",
        "Demo",
        "Legacy tweet",
        "2025-02-14T06:37:00.000Z",
        "https://example.com/poster.jpg",
        "resolved",
        JSON.stringify({
          durationText: "0:45"
        }),
        null,
        "2025-02-14T06:37:00.000Z",
        "2025-02-14T06:38:00.000Z"
      );
  } finally {
    legacyDb.close();
  }

  const upgradedDb = new AppDatabase(dbPath);

  try {
    const tweetColumns = upgradedDb.db.prepare("PRAGMA table_info(tweets)").all();
    assert(tweetColumns.some((column) => column.name === "author_avatar_url"));
    assert(tweetColumns.some((column) => column.name === "duration_text"));

    const row = upgradedDb.db
      .prepare(
        `
          SELECT duration_text AS durationText
          FROM tweets
          WHERE tweet_id = 'legacy-1'
        `
      )
      .get();
    assert.equal(row.durationText, "0:45");
    assert.deepEqual(
      getAppliedMigrationIds(upgradedDb.db),
      DATABASE_MIGRATIONS.map((migration) => migration.id)
    );
  } finally {
    upgradedDb.close();
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
