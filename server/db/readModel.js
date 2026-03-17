function mapSourceRow(source) {
  return {
    ...source,
    enabled: Boolean(source.enabled)
  };
}

function decodeFeedCursor(cursor) {
  if (!cursor) {
    return {
      cursorSort: null,
      cursorTweetId: null
    };
  }

  try {
    const decoded = JSON.parse(Buffer.from(cursor, "base64url").toString("utf8"));
    return {
      cursorSort: decoded.sortValue || null,
      cursorTweetId: decoded.tweetId || null
    };
  } catch {
    return {
      cursorSort: null,
      cursorTweetId: null
    };
  }
}

function encodeFeedCursor(items) {
  return Buffer.from(
    JSON.stringify({
      sortValue: items[items.length - 1].sortValue,
      tweetId: items[items.length - 1].tweetId
    })
  ).toString("base64url");
}

export function createDatabaseReadModel(db) {
  return {
    listSources() {
      return db
        .prepare(
          `
            SELECT id, handle, enabled, last_discovered_at AS lastDiscoveredAt
            FROM sources
            ORDER BY handle ASC
          `
        )
        .all()
        .map(mapSourceRow);
    },

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
        return db.prepare(`${baseQuery}\nLIMIT ?`).all(limit);
      }

      return db.prepare(baseQuery).all();
    },

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
        return db.prepare(`${baseQuery}\nLIMIT ?`).all(limit);
      }

      return db.prepare(baseQuery).all();
    },

    getFeed({ cursor = null, sourceHandle = null, limit = 12 }) {
      const { cursorSort, cursorTweetId } = decodeFeedCursor(cursor);

      const rows = db
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

      return {
        items: items.map(({ sortValue, ...row }) => row),
        nextCursor: hasMore ? encodeFeedCursor(items) : null
      };
    },

    getPrimaryMedia(tweetId) {
      return (
        db
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
    },

    getSourcesOverview() {
      return db
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
        .map(mapSourceRow);
    },

    getStats() {
      const row = db
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
    },

    countTweetsByStatus(status) {
      return db.prepare("SELECT COUNT(*) AS count FROM tweets WHERE status = ?").get(status).count;
    }
  };
}
