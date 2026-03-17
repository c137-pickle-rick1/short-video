import {
  TWEET_STATUSES,
  compactJson,
  extractDurationTextFromDiscoveryPayload,
  nowIso
} from "./shared.js";

export function createDatabaseWriteRepository(db) {
  return {
    syncSources(sources) {
      const tx = db.transaction((sourceItems) => {
        db.prepare("UPDATE sources SET enabled = 0").run();

        const upsert = db.prepare(`
          INSERT INTO sources (handle, enabled)
          VALUES (?, ?)
          ON CONFLICT(handle) DO UPDATE SET enabled = excluded.enabled
        `);

        for (const source of sourceItems) {
          upsert.run(source.handle, source.enabled ? 1 : 0);
        }
      });

      tx(sources);
    },

    touchSourceLastDiscovered(sourceId) {
      db.prepare("UPDATE sources SET last_discovered_at = ? WHERE id = ?").run(nowIso(), sourceId);
    },

    insertDiscoveredTweet({ tweetId, sourceId, tweetUrl, rawDiscoveryPayload, durationText = null }) {
      const normalizedDurationText =
        durationText || extractDurationTextFromDiscoveryPayload(rawDiscoveryPayload);
      const compactDiscoveryPayload = compactJson(rawDiscoveryPayload);
      const insertResult = db
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
        db.prepare(
          `
            UPDATE tweets
            SET
              duration_text = COALESCE(?, duration_text),
              raw_discovery_payload = COALESCE(?, raw_discovery_payload)
            WHERE tweet_id = ?
          `
        ).run(normalizedDurationText, compactDiscoveryPayload, tweetId);
      }

      return false;
    },

    applyResolution(tweetId, resolution) {
      if (!TWEET_STATUSES.includes(resolution.status)) {
        throw new Error(`Unsupported tweet status: ${resolution.status}`);
      }

      const tx = db.transaction(() => {
        db.prepare(
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
        ).run(
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

        db.prepare("DELETE FROM media_assets WHERE tweet_id = ?").run(tweetId);

        if (Array.isArray(resolution.mediaAssets) && resolution.mediaAssets.length > 0) {
          const insertMedia = db.prepare(`
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
    },

    createCrawlRun({ phase, sourceId = null }) {
      const result = db
        .prepare(
          `
            INSERT INTO crawl_runs (phase, source_id, started_at, status)
            VALUES (?, ?, ?, 'running')
          `
        )
        .run(phase, sourceId, nowIso());

      return result.lastInsertRowid;
    },

    finishCrawlRun(runId, outcome = {}) {
      db.prepare(
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
      ).run(
        nowIso(),
        outcome.status || "success",
        outcome.itemsSeen || 0,
        outcome.itemsInserted || 0,
        outcome.itemsResolved || 0,
        outcome.errorMessage || null,
        runId
      );
    }
  };
}
