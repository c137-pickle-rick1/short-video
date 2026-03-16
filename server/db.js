import Database from "better-sqlite3";
import fs from "node:fs";
import path from "node:path";

const TWEET_STATUSES = ["pending", "resolved", "external_only", "skipped", "failed"];

function nowIso() {
  return new Date().toISOString();
}

function compactJson(value) {
  if (!value) {
    return null;
  }

  const text = typeof value === "string" ? value : JSON.stringify(value);
  if (text.length <= 50000) {
    return text;
  }

  return `${text.slice(0, 49950)}...[truncated]`;
}

function extractDurationTextFromDiscoveryPayload(rawDiscoveryPayload) {
  if (!rawDiscoveryPayload) {
    return null;
  }

  const payload =
    typeof rawDiscoveryPayload === "string"
      ? (() => {
          try {
            return JSON.parse(rawDiscoveryPayload);
          } catch {
            return null;
          }
        })()
      : rawDiscoveryPayload;

  const durationText = payload?.durationText || payload?.discoveredLink?.durationText || null;
  return typeof durationText === "string" && durationText.trim() ? durationText.trim() : null;
}

export class AppDatabase {
  constructor(dbPath) {
    fs.mkdirSync(path.dirname(dbPath), { recursive: true });
    this.db = new Database(dbPath);
    this.db.pragma("journal_mode = WAL");
    this.db.pragma("foreign_keys = ON");
    this.#init();
  }

  #init() {
    this.db.exec(`
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
        author_avatar_url TEXT,
        text TEXT,
        posted_at TEXT,
        poster_url TEXT,
        status TEXT NOT NULL CHECK (status IN ('pending', 'resolved', 'external_only', 'skipped', 'failed')),
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

    this.#ensureColumn("tweets", "author_avatar_url", "TEXT");
    this.#ensureColumn("tweets", "duration_text", "TEXT");
    this.#backfillTweetDurationsFromDiscoveryPayload();
  }

  #ensureColumn(tableName, columnName, definition) {
    const columns = this.db.prepare(`PRAGMA table_info(${tableName})`).all();
    if (columns.some((column) => column.name === columnName)) {
      return;
    }

    this.db.exec(`ALTER TABLE ${tableName} ADD COLUMN ${columnName} ${definition}`);
  }

  #backfillTweetDurationsFromDiscoveryPayload() {
    const rows = this.db
      .prepare(
        `
          SELECT tweet_id AS tweetId, raw_discovery_payload AS rawDiscoveryPayload
          FROM tweets
          WHERE (duration_text IS NULL OR TRIM(duration_text) = '')
            AND raw_discovery_payload IS NOT NULL
        `
      )
      .all();

    if (!rows.length) {
      return;
    }

    const update = this.db.prepare("UPDATE tweets SET duration_text = ? WHERE tweet_id = ?");
    const tx = this.db.transaction((items) => {
      for (const row of items) {
        const durationText = extractDurationTextFromDiscoveryPayload(row.rawDiscoveryPayload);
        if (!durationText) {
          continue;
        }

        update.run(durationText, row.tweetId);
      }
    });

    tx(rows);
  }

  close() {
    this.db.close();
  }

  syncSources(sources) {
    const tx = this.db.transaction((sourceItems) => {
      this.db.prepare("UPDATE sources SET enabled = 0").run();

      const upsert = this.db.prepare(`
        INSERT INTO sources (handle, enabled)
        VALUES (?, ?)
        ON CONFLICT(handle) DO UPDATE SET enabled = excluded.enabled
      `);

      for (const source of sourceItems) {
        upsert.run(source.handle, source.enabled ? 1 : 0);
      }
    });

    tx(sources);
    return this.listSources();
  }

  listSources() {
    return this.db
      .prepare(
        `
          SELECT id, handle, enabled, last_discovered_at AS lastDiscoveredAt
          FROM sources
          ORDER BY handle ASC
        `
      )
      .all()
      .map((source) => ({
        ...source,
        enabled: Boolean(source.enabled)
      }));
  }

  listEnabledSources() {
    return this.listSources().filter((source) => source.enabled);
  }

  touchSourceLastDiscovered(sourceId) {
    this.db
      .prepare("UPDATE sources SET last_discovered_at = ? WHERE id = ?")
      .run(nowIso(), sourceId);
  }

  insertDiscoveredTweet({ tweetId, sourceId, tweetUrl, rawDiscoveryPayload, durationText = null }) {
    const normalizedDurationText = durationText || extractDurationTextFromDiscoveryPayload(rawDiscoveryPayload);
    const compactDiscoveryPayload = compactJson(rawDiscoveryPayload);
    const insertResult = this.db
      .prepare(
        `
          INSERT INTO tweets (
            tweet_id,
            source_id,
            tweet_url,
            duration_text,
            status,
            raw_discovery_payload,
            ingested_at
          )
          VALUES (?, ?, ?, ?, 'pending', ?, ?)
          ON CONFLICT(tweet_id) DO NOTHING
        `
      )
      .run(tweetId, sourceId, tweetUrl, normalizedDurationText, compactDiscoveryPayload, nowIso());

    if (insertResult.changes > 0) {
      return true;
    }

    if (normalizedDurationText || compactDiscoveryPayload) {
      this.db
        .prepare(
          `
            UPDATE tweets
            SET
              duration_text = COALESCE(?, duration_text),
              raw_discovery_payload = COALESCE(?, raw_discovery_payload)
            WHERE tweet_id = ?
          `
        )
        .run(normalizedDurationText, compactDiscoveryPayload, tweetId);
    }

    return false;
  }

  listPendingTweets(limit = null) {
    const baseQuery = `
      SELECT
        t.tweet_id AS tweetId,
        t.source_id AS sourceId,
        s.handle AS sourceHandle,
        t.tweet_url AS tweetUrl,
        t.duration_text AS durationText,
        t.ingested_at AS ingestedAt
      FROM tweets t
      JOIN sources s ON s.id = t.source_id
      WHERE t.status = 'pending'
        AND s.enabled = 1
      ORDER BY t.ingested_at DESC, t.tweet_id DESC
    `;

    if (Number.isInteger(limit) && limit > 0) {
      return this.db.prepare(`${baseQuery}\nLIMIT ?`).all(limit);
    }

    return this.db.prepare(baseQuery).all();
  }

  listPublishedTweetsMissingAvatar(limit = null) {
    const baseQuery = `
      SELECT
        t.tweet_id AS tweetId,
        t.source_id AS sourceId,
        s.handle AS sourceHandle,
        t.tweet_url AS tweetUrl,
        t.status,
        t.ingested_at AS ingestedAt,
        t.resolved_at AS resolvedAt
      FROM tweets t
      JOIN sources s ON s.id = t.source_id
      WHERE t.status IN ('resolved', 'external_only')
        AND s.enabled = 1
        AND (t.author_avatar_url IS NULL OR TRIM(t.author_avatar_url) = '')
      ORDER BY COALESCE(t.resolved_at, t.ingested_at) DESC, t.tweet_id DESC
    `;

    if (Number.isInteger(limit) && limit > 0) {
      return this.db.prepare(`${baseQuery}\nLIMIT ?`).all(limit);
    }

    return this.db.prepare(baseQuery).all();
  }

  applyResolution(tweetId, resolution) {
    if (!TWEET_STATUSES.includes(resolution.status)) {
      throw new Error(`Unsupported tweet status: ${resolution.status}`);
    }

    const tx = this.db.transaction(() => {
      this.db
        .prepare(
          `
            UPDATE tweets
            SET
              author_handle = ?,
              author_name = ?,
              author_avatar_url = ?,
              text = ?,
              posted_at = ?,
              poster_url = ?,
              duration_text = COALESCE(?, duration_text),
              status = ?,
              raw_resolve_payload = ?,
              resolved_at = ?
            WHERE tweet_id = ?
          `
        )
        .run(
          resolution.tweet?.authorHandle || null,
          resolution.tweet?.authorName || null,
          resolution.tweet?.authorAvatarUrl || null,
          resolution.tweet?.text || null,
          resolution.tweet?.postedAt || null,
          resolution.tweet?.posterUrl || null,
          resolution.tweet?.durationText || null,
          resolution.status,
          compactJson(resolution.rawPayload),
          nowIso(),
          tweetId
        );

      this.db.prepare("DELETE FROM media_assets WHERE tweet_id = ?").run(tweetId);

      if (Array.isArray(resolution.mediaAssets) && resolution.mediaAssets.length > 0) {
        const insertMedia = this.db.prepare(`
          INSERT INTO media_assets (
            tweet_id,
            url,
            bitrate,
            content_type,
            width,
            height,
            sort_order,
            is_primary
          )
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        `);

        for (const asset of resolution.mediaAssets) {
          insertMedia.run(
            tweetId,
            asset.url,
            asset.bitrate ?? null,
            asset.contentType ?? null,
            asset.width ?? null,
            asset.height ?? null,
            asset.sortOrder ?? 0,
            asset.isPrimary ? 1 : 0
          );
        }
      }
    });

    tx();
  }

  createCrawlRun({ phase, sourceId = null }) {
    const result = this.db
      .prepare(
        `
          INSERT INTO crawl_runs (phase, source_id, started_at, status)
          VALUES (?, ?, ?, 'running')
        `
      )
      .run(phase, sourceId, nowIso());

    return result.lastInsertRowid;
  }

  finishCrawlRun(runId, outcome = {}) {
    this.db
      .prepare(
        `
          UPDATE crawl_runs
          SET
            finished_at = ?,
            status = ?,
            items_seen = ?,
            items_inserted = ?,
            items_resolved = ?,
            error_message = ?
          WHERE id = ?
        `
      )
      .run(
        nowIso(),
        outcome.status || "success",
        outcome.itemsSeen || 0,
        outcome.itemsInserted || 0,
        outcome.itemsResolved || 0,
        outcome.errorMessage || null,
        runId
      );
  }

  getFeed({ cursor = null, sourceHandle = null, limit = 12 }) {
    let cursorSort = null;
    let cursorTweetId = null;

    if (cursor) {
      try {
        const decoded = JSON.parse(Buffer.from(cursor, "base64url").toString("utf8"));
        cursorSort = decoded.sortValue || null;
        cursorTweetId = decoded.tweetId || null;
      } catch {
        cursorSort = null;
        cursorTweetId = null;
      }
    }

    const rows = this.db
      .prepare(
        `
          SELECT
            t.tweet_id AS tweetId,
            t.tweet_url AS tweetUrl,
            t.author_handle AS authorHandle,
            t.author_name AS authorName,
            t.author_avatar_url AS authorAvatarUrl,
            t.text,
            t.posted_at AS postedAt,
            t.duration_text AS durationText,
            t.poster_url AS posterUrl,
            t.status,
            ma.url AS videoUrl,
            ma.width AS mediaWidth,
            ma.height AS mediaHeight,
            s.handle AS sourceHandle,
            COALESCE(t.posted_at, t.ingested_at) AS sortValue
          FROM tweets t
          JOIN sources s ON s.id = t.source_id
          LEFT JOIN media_assets ma
            ON ma.tweet_id = t.tweet_id
           AND ma.is_primary = 1
          WHERE t.status IN ('resolved', 'external_only')
            AND s.enabled = 1
            AND (? IS NULL OR s.handle = ?)
            AND (
              ? IS NULL
              OR COALESCE(t.posted_at, t.ingested_at) < ?
              OR (
                COALESCE(t.posted_at, t.ingested_at) = ?
                AND t.tweet_id < ?
              )
            )
          ORDER BY COALESCE(t.posted_at, t.ingested_at) DESC, t.tweet_id DESC
          LIMIT ?
        `
      )
      .all(
        sourceHandle,
        sourceHandle,
        cursorSort,
        cursorSort,
        cursorSort,
        cursorTweetId,
        limit + 1
      );

    const hasMore = rows.length > limit;
    const items = rows.slice(0, limit);
    const nextCursor = hasMore
      ? Buffer.from(
          JSON.stringify({
            sortValue: items[items.length - 1].sortValue,
            tweetId: items[items.length - 1].tweetId
          })
        ).toString("base64url")
      : null;

    return {
      items: items.map(({ sortValue, ...row }) => row),
      nextCursor
    };
  }

  getPrimaryMedia(tweetId) {
    return (
      this.db
        .prepare(
          `
            SELECT url, content_type AS contentType
            FROM media_assets
            WHERE tweet_id = ? AND is_primary = 1
            LIMIT 1
          `
        )
        .get(tweetId) || null
    );
  }

  getSourcesOverview() {
    return this.db
      .prepare(
        `
          SELECT
            s.id,
            s.handle,
            s.enabled,
            s.last_discovered_at AS lastDiscoveredAt,
            (
              SELECT cr.status
              FROM crawl_runs cr
              WHERE cr.phase = 'discovery' AND cr.source_id = s.id
              ORDER BY cr.started_at DESC
              LIMIT 1
            ) AS lastRunStatus,
            (
              SELECT COUNT(*)
              FROM tweets t
              WHERE t.source_id = s.id AND t.status IN ('resolved', 'external_only')
            ) AS publishedCount,
            (
              SELECT COUNT(*)
              FROM tweets t
              WHERE t.source_id = s.id AND t.status = 'pending'
            ) AS pendingCount
          FROM sources s
          ORDER BY s.enabled DESC, s.handle ASC
        `
      )
      .all()
      .map((source) => ({
        ...source,
        enabled: Boolean(source.enabled)
      }));
  }

  getStats() {
    const row = this.db
      .prepare(
        `
          SELECT
            SUM(CASE WHEN status IN ('resolved', 'external_only') THEN 1 ELSE 0 END) AS totalItems,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolvedCount,
            SUM(CASE WHEN status = 'external_only' THEN 1 ELSE 0 END) AS externalOnlyCount,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failedCount,
            MAX(COALESCE(resolved_at, ingested_at)) AS lastUpdatedAt
          FROM tweets t
          JOIN sources s ON s.id = t.source_id
          WHERE s.enabled = 1
        `
      )
      .get();

    return {
      totalItems: row?.totalItems || 0,
      resolvedCount: row?.resolvedCount || 0,
      externalOnlyCount: row?.externalOnlyCount || 0,
      failedCount: row?.failedCount || 0,
      lastUpdatedAt: row?.lastUpdatedAt || null
    };
  }

  countTweetsByStatus(status) {
    return this.db
      .prepare("SELECT COUNT(*) AS count FROM tweets WHERE status = ?")
      .get(status).count;
  }
}
