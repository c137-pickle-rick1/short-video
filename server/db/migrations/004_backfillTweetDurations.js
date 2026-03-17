import { extractDurationTextFromDiscoveryPayload } from "../shared.js";

export const migration004BackfillTweetDurations = {
  id: "004_backfill_tweet_durations",
  run(db) {
    const rows = db
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

    const update = db.prepare("UPDATE tweets SET duration_text = ? WHERE tweet_id = ?");

    for (const row of rows) {
      const durationText = extractDurationTextFromDiscoveryPayload(row.rawDiscoveryPayload);
      if (!durationText) {
        continue;
      }

      update.run(durationText, row.tweetId);
    }
  }
};
