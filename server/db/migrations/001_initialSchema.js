import { TWEET_STATUSES } from "../shared.js";

const tweetStatusCheck = TWEET_STATUSES.map((status) => `'${status}'`).join(", ");

export const migration001InitialSchema = {
  id: "001_initial_schema",
  run(db) {
    db.exec(`
      CREATE TABLE IF NOT EXISTS sources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        handle TEXT NOT NULL UNIQUE,
        enabled INTEGER NOT NULL DEFAULT 1,
        last_discovered_at TEXT
      );

      CREATE TABLE IF NOT EXISTS tweets (
        tweet_id TEXT PRIMARY KEY,
        source_id INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE,
        tweet_url TEXT NOT NULL,
        author_handle TEXT,
        author_name TEXT,
        text TEXT,
        posted_at TEXT,
        poster_url TEXT,
        status TEXT NOT NULL CHECK (status IN (${tweetStatusCheck})),
        raw_discovery_payload TEXT,
        raw_resolve_payload TEXT,
        ingested_at TEXT NOT NULL,
        resolved_at TEXT
      );

      CREATE TABLE IF NOT EXISTS media_assets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tweet_id TEXT NOT NULL REFERENCES tweets(tweet_id) ON DELETE CASCADE,
        url TEXT NOT NULL,
        bitrate INTEGER,
        content_type TEXT,
        width INTEGER,
        height INTEGER,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_primary INTEGER NOT NULL DEFAULT 0
      );

      CREATE TABLE IF NOT EXISTS crawl_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phase TEXT NOT NULL,
        source_id INTEGER REFERENCES sources(id) ON DELETE SET NULL,
        started_at TEXT NOT NULL,
        finished_at TEXT,
        status TEXT NOT NULL,
        items_seen INTEGER NOT NULL DEFAULT 0,
        items_inserted INTEGER NOT NULL DEFAULT 0,
        items_resolved INTEGER NOT NULL DEFAULT 0,
        error_message TEXT
      );

      CREATE INDEX IF NOT EXISTS idx_tweets_status_sort
        ON tweets(status, posted_at DESC, ingested_at DESC, tweet_id DESC);

      CREATE INDEX IF NOT EXISTS idx_tweets_source_status
        ON tweets(source_id, status);

      CREATE INDEX IF NOT EXISTS idx_media_assets_primary
        ON media_assets(tweet_id, is_primary);

      CREATE INDEX IF NOT EXISTS idx_crawl_runs_phase_source
        ON crawl_runs(phase, source_id, started_at DESC);
    `);
  }
};
