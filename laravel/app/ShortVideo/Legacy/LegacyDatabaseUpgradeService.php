<?php

namespace App\ShortVideo\Legacy;

use App\ShortVideo\Repositories\CreatorIdentityRepository;
use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Database\ConnectionInterface;

final class LegacyDatabaseUpgradeService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CreatorIdentityRepository $creatorIdentities
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'tweetDurations' => $this->backfillTweetDurations(),
            'importedVideos' => $this->backfillImportedVideos(),
            'externalCreators' => $this->backfillExternalCreators(),
        ];
    }

    /**
     * @return array{updated:int}
     */
    public function backfillTweetDurations(): array
    {
        $rows = $this->db->select(<<<'SQL'
            SELECT tweet_id AS tweetId, raw_discovery_payload AS rawDiscoveryPayload
            FROM tweets
            WHERE (duration_text IS NULL OR TRIM(duration_text) = '')
              AND raw_discovery_payload IS NOT NULL
        SQL);

        $updated = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->rawDiscoveryPayload, true);
            $durationText = $payload['durationText'] ?? $payload['discoveredLink']['durationText'] ?? null;
            $durationText = is_string($durationText) ? trim($durationText) : null;
            if ($durationText === null || $durationText === '') {
                continue;
            }

            $affected = $this->db->update('UPDATE tweets SET duration_text = ? WHERE tweet_id = ?', [$durationText, $row->tweetId]);
            if ($affected > 0) {
                $updated++;
            }
        }

        return ['updated' => $updated];
    }

    /**
     * @return array{inserted:int,updated:int}
     */
    public function backfillImportedVideos(): array
    {
        $sourceUserIdSelect = $this->db->getSchemaBuilder()->hasColumn('sources', 'user_id')
            ? 's.user_id'
            : 'NULL';

        $rows = $this->db->select(
            <<<SQL
                SELECT
                    t.tweet_id AS tweetId,
                    t.source_id AS sourceId,
                    t.text AS caption,
                    t.poster_url AS posterUrl,
                    t.duration_text AS durationText,
                    {$sourceUserIdSelect} AS sourceUserId,
                    COALESCE(t.posted_at, t.ingested_at) AS publishedAt,
                    (
                        SELECT asset.url
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.is_primary = 1
                        LIMIT 1
                    ) AS playbackUrl,
                    (
                        SELECT asset.url
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.content_type IN ('application/x-mpegURL', 'application/vnd.apple.mpegurl')
                        ORDER BY asset.sort_order ASC, asset.id ASC
                        LIMIT 1
                    ) AS hlsUrl,
                    (
                        SELECT asset.width
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.is_primary = 1
                        LIMIT 1
                    ) AS mediaWidth,
                    (
                        SELECT asset.height
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.is_primary = 1
                        LIMIT 1
                    ) AS mediaHeight
                FROM tweets t
                JOIN sources s ON s.id = t.source_id
                WHERE t.status IN ('resolved', 'external_only')
            SQL
        );

        $inserted = 0;
        $updated = 0;
        $timestamp = now();

        foreach ($rows as $row) {
            $payload = [
                'origin' => 'x_tweet',
                'source_id' => (int) $row->sourceId,
                'uploader_user_id' => $row->sourceUserId !== null ? (int) $row->sourceUserId : null,
                'title' => null,
                'caption' => $row->caption,
                'description' => null,
                'storage_disk' => null,
                'storage_path' => null,
                'poster_url' => $row->posterUrl,
                'playback_url' => $row->playbackUrl,
                'hls_url' => $row->hlsUrl,
                'duration_text' => $row->durationText,
                'duration_seconds' => ShortVideoData::parseDurationTextToSeconds($row->durationText),
                'width' => $row->mediaWidth !== null ? (int) $row->mediaWidth : null,
                'height' => $row->mediaHeight !== null ? (int) $row->mediaHeight : null,
                'visibility' => 'public',
                'status' => 'published',
                'published_at' => $row->publishedAt,
                'updated_at' => $timestamp,
            ];

            $existingId = $this->db->table('videos')->where('tweet_id', $row->tweetId)->value('id');

            if ($existingId !== null) {
                $affected = $this->db->table('videos')->where('id', $existingId)->update($payload);
                if ($affected > 0) {
                    $updated++;
                }

                continue;
            }

            $this->db->table('videos')->insert($payload + [
                'tweet_id' => $row->tweetId,
                'created_at' => $timestamp,
            ]);

            $inserted++;
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
        ];
    }

    /**
     * @return array{creatorUsersEnsured:int,sourceLinksUpdated:int,videoLinksUpdated:int}
     */
    public function backfillExternalCreators(): array
    {
        $ensuredUserIds = [];
        $sourceLinksUpdated = 0;
        $videoLinksUpdated = 0;

        $sources = $this->db->select('SELECT id, handle, user_id AS userId FROM sources ORDER BY id ASC');
        foreach ($sources as $source) {
            $handle = ShortVideoData::normalizeHandle((string) $source->handle);
            if ($handle === '') {
                continue;
            }

            $userId = $this->creatorIdentities->ensureExternalCreatorIdentity($handle, '@'.$handle, null);
            if ($userId === null) {
                continue;
            }

            $ensuredUserIds[$userId] = true;

            if ($this->updateSourceUserIdIfNeeded((int) $source->id, $source->userId, $userId)) {
                $sourceLinksUpdated++;
            }
        }

        $tweets = $this->db->select(
            <<<'SQL'
                SELECT
                    t.tweet_id AS tweetId,
                    t.author_handle AS authorHandle,
                    t.author_name AS authorName,
                    t.author_avatar_url AS authorAvatarUrl,
                    s.id AS sourceId,
                    s.handle AS sourceHandle,
                    s.user_id AS sourceUserId
                FROM tweets t
                JOIN sources s ON s.id = t.source_id
                ORDER BY t.tweet_id ASC
            SQL
        );

        foreach ($tweets as $tweet) {
            $handle = ShortVideoData::normalizeHandle($tweet->authorHandle ?: $tweet->sourceHandle);
            if ($handle === '') {
                continue;
            }

            $userId = $this->creatorIdentities->ensureExternalCreatorIdentity(
                $handle,
                is_string($tweet->authorName) && trim($tweet->authorName) !== '' ? trim($tweet->authorName) : '@'.$handle,
                is_string($tweet->authorAvatarUrl) && trim($tweet->authorAvatarUrl) !== '' ? trim($tweet->authorAvatarUrl) : null
            );

            if ($userId === null) {
                continue;
            }

            $ensuredUserIds[$userId] = true;

            if (ShortVideoData::normalizeHandle((string) $tweet->sourceHandle) === $handle
                && $this->updateSourceUserIdIfNeeded((int) $tweet->sourceId, $tweet->sourceUserId, $userId)) {
                $sourceLinksUpdated++;
            }

            $videoLinksUpdated += $this->db->table('videos')
                ->where('tweet_id', (string) $tweet->tweetId)
                ->where(static function ($query) use ($userId): void {
                    $query->whereNull('uploader_user_id')
                        ->orWhere('uploader_user_id', '!=', $userId);
                })
                ->update(['uploader_user_id' => $userId]);
        }

        return [
            'creatorUsersEnsured' => count($ensuredUserIds),
            'sourceLinksUpdated' => $sourceLinksUpdated,
            'videoLinksUpdated' => $videoLinksUpdated,
        ];
    }

    private function updateSourceUserIdIfNeeded(int $sourceId, mixed $currentUserId, int $nextUserId): bool
    {
        $normalizedCurrent = is_numeric((string) $currentUserId) ? (int) $currentUserId : null;
        if ($normalizedCurrent === $nextUserId) {
            return false;
        }

        return $this->db->table('sources')->where('id', $sourceId)->update(['user_id' => $nextUserId]) > 0;
    }
}
