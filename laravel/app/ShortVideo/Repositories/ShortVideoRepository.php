<?php

namespace App\ShortVideo\Repositories;

use App\ShortVideo\Support\FeedCursor;
use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Database\ConnectionInterface;

final class ShortVideoRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<int, array{handle: string, enabled: bool}>  $sources
     * @return array<int, array<string, mixed>>
     */
    public function syncSources(array $sources): array
    {
        return $this->db->transaction(function () use ($sources) {
            $this->db->table('sources')->update(['enabled' => 0]);

            foreach ($sources as $source) {
                $existing = $this->db->table('sources')->where('handle', $source['handle'])->first();

                if ($existing) {
                    $this->db->table('sources')
                        ->where('handle', $source['handle'])
                        ->update(['enabled' => $source['enabled'] ? 1 : 0]);
                } else {
                    $this->db->table('sources')->insert([
                        'handle' => $source['handle'],
                        'enabled' => $source['enabled'] ? 1 : 0,
                    ]);
                }
            }

            return $this->listSources();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSources(): array
    {
        return array_map(
            fn (object $row) => [
                'id' => (int) $row->id,
                'handle' => (string) $row->handle,
                'enabled' => (bool) $row->enabled,
                'lastDiscoveredAt' => $row->lastDiscoveredAt ? (string) $row->lastDiscoveredAt : null,
            ],
            $this->db->select(
                <<<'SQL'
                    SELECT id, handle, enabled, last_discovered_at AS lastDiscoveredAt
                    FROM sources
                    ORDER BY handle ASC
                SQL
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEnabledSources(): array
    {
        return array_values(array_filter(
            $this->listSources(),
            fn (array $source) => $source['enabled'] === true
        ));
    }

    public function touchSourceLastDiscovered(int $sourceId): void
    {
        $this->db->table('sources')
            ->where('id', $sourceId)
            ->update(['last_discovered_at' => ShortVideoData::nowIso()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertDiscoveredTweet(array $attributes): bool
    {
        $durationText = $attributes['durationText']
            ?? ShortVideoData::extractDurationTextFromDiscoveryPayload($attributes['rawDiscoveryPayload'] ?? null);
        $payload = ShortVideoData::compactJson($attributes['rawDiscoveryPayload'] ?? null);

        $changes = $this->db->affectingStatement(
            <<<'SQL'
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
            SQL,
            [
                (string) $attributes['tweetId'],
                (int) $attributes['sourceId'],
                (string) $attributes['tweetUrl'],
                $durationText,
                $payload,
                ShortVideoData::nowIso(),
            ]
        );

        if ($changes > 0) {
            return true;
        }

        if ($durationText !== null || $payload !== null) {
            $this->db->update(
                <<<'SQL'
                    UPDATE tweets
                    SET
                        duration_text = COALESCE(?, duration_text),
                        raw_discovery_payload = COALESCE(?, raw_discovery_payload)
                    WHERE tweet_id = ?
                SQL,
                [$durationText, $payload, (string) $attributes['tweetId']]
            );
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    public function applyResolution(string $tweetId, array $resolution): void
    {
        $status = (string) ($resolution['status'] ?? 'failed');
        if (! in_array($status, ShortVideoData::TWEET_STATUSES, true)) {
            throw new \InvalidArgumentException("Unsupported tweet status: {$status}");
        }

        $tweet = is_array($resolution['tweet'] ?? null) ? $resolution['tweet'] : [];
        $mediaAssets = is_array($resolution['mediaAssets'] ?? null) ? $resolution['mediaAssets'] : [];

        $this->db->transaction(function () use ($tweetId, $status, $tweet, $resolution, $mediaAssets) {
            $this->db->update(
                <<<'SQL'
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
                SQL,
                [
                    $tweet['authorHandle'] ?? null,
                    $tweet['authorName'] ?? null,
                    $tweet['authorAvatarUrl'] ?? null,
                    $tweet['text'] ?? null,
                    $tweet['postedAt'] ?? null,
                    $tweet['posterUrl'] ?? null,
                    $tweet['durationText'] ?? null,
                    $status,
                    ShortVideoData::compactJson($resolution['rawPayload'] ?? null),
                    ShortVideoData::nowIso(),
                    $tweetId,
                ]
            );

            $this->db->delete('DELETE FROM media_assets WHERE tweet_id = ?', [$tweetId]);

            foreach ($mediaAssets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $this->db->table('media_assets')->insert([
                    'tweet_id' => $tweetId,
                    'url' => $asset['url'] ?? null,
                    'bitrate' => $asset['bitrate'] ?? null,
                    'content_type' => $asset['contentType'] ?? null,
                    'width' => $asset['width'] ?? null,
                    'height' => $asset['height'] ?? null,
                    'sort_order' => $asset['sortOrder'] ?? 0,
                    'is_primary' => ! empty($asset['isPrimary']) ? 1 : 0,
                ]);
            }
        });
    }

    public function createCrawlRun(string $phase, ?int $sourceId = null): int
    {
        return (int) $this->db->table('crawl_runs')->insertGetId([
            'phase' => $phase,
            'source_id' => $sourceId,
            'started_at' => ShortVideoData::nowIso(),
            'status' => 'running',
            'items_seen' => 0,
            'items_inserted' => 0,
            'items_resolved' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    public function finishCrawlRun(int $runId, array $outcome = []): void
    {
        $this->db->table('crawl_runs')
            ->where('id', $runId)
            ->update([
                'finished_at' => ShortVideoData::nowIso(),
                'status' => $outcome['status'] ?? 'success',
                'items_seen' => $outcome['itemsSeen'] ?? 0,
                'items_inserted' => $outcome['itemsInserted'] ?? 0,
                'items_resolved' => $outcome['itemsResolved'] ?? 0,
                'error_message' => $outcome['errorMessage'] ?? null,
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPendingTweets(?int $limit = null): array
    {
        $sql = <<<'SQL'
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
        SQL;

        $bindings = [];
        if ($limit !== null && $limit > 0) {
            $sql .= "\nLIMIT ?";
            $bindings[] = $limit;
        }

        return array_map(
            fn (object $row) => (array) $row,
            $this->db->select($sql, $bindings)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPublishedTweetsMissingAvatar(?int $limit = null): array
    {
        $sql = <<<'SQL'
            SELECT
                t.tweet_id AS tweetId,
                t.source_id AS sourceId,
                s.handle AS sourceHandle,
                t.tweet_url AS tweetUrl,
                t.status AS status,
                t.ingested_at AS ingestedAt,
                t.resolved_at AS resolvedAt
            FROM tweets t
            JOIN sources s ON s.id = t.source_id
            WHERE t.status IN ('resolved', 'external_only')
              AND s.enabled = 1
              AND (t.author_avatar_url IS NULL OR TRIM(t.author_avatar_url) = '')
            ORDER BY COALESCE(t.resolved_at, t.ingested_at) DESC, t.tweet_id DESC
        SQL;

        $bindings = [];
        if ($limit !== null && $limit > 0) {
            $sql .= "\nLIMIT ?";
            $bindings[] = $limit;
        }

        return array_map(
            fn (object $row) => (array) $row,
            $this->db->select($sql, $bindings)
        );
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, nextCursor: ?string}
     */
    public function getFeed(?string $cursor = null, ?string $sourceHandle = null, int $limit = 12): array
    {
        ['cursorSort' => $cursorSort, 'cursorTweetId' => $cursorTweetId] = FeedCursor::decode($cursor);

        $rows = array_map(
            fn (object $row) => (array) $row,
            $this->db->select(
                <<<'SQL'
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
                        (
                            SELECT asset.url
                            FROM media_assets asset
                            WHERE asset.tweet_id = t.tweet_id
                              AND asset.is_primary = 1
                            LIMIT 1
                        ) AS videoUrl,
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
                        ) AS mediaHeight,
                        s.handle AS sourceHandle,
                        COALESCE(t.posted_at, t.ingested_at) AS sortValue
                    FROM tweets t
                    JOIN sources s ON s.id = t.source_id
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
                SQL,
                [
                    $sourceHandle,
                    $sourceHandle,
                    $cursorSort,
                    $cursorSort,
                    $cursorSort,
                    $cursorTweetId,
                    $limit + 1,
                ]
            )
        );

        $hasMore = count($rows) > $limit;
        $items = array_slice($rows, 0, $limit);

        return [
            'items' => array_map(function (array $item) {
                unset($item['sortValue']);

                return $item;
            }, $items),
            'nextCursor' => $hasMore ? FeedCursor::encode($items) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPrimaryMedia(string $tweetId): ?array
    {
        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT url, content_type AS contentType
                FROM media_assets
                WHERE tweet_id = ? AND is_primary = 1
                LIMIT 1
            SQL,
            [$tweetId]
        );

        return $row ? (array) $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSourcesOverview(): array
    {
        return array_map(function (object $row) {
            return [
                'id' => (int) $row->id,
                'handle' => (string) $row->handle,
                'enabled' => (bool) $row->enabled,
                'lastDiscoveredAt' => $row->lastDiscoveredAt ? (string) $row->lastDiscoveredAt : null,
                'lastRunStatus' => $row->lastRunStatus ? (string) $row->lastRunStatus : null,
                'publishedCount' => (int) $row->publishedCount,
                'pendingCount' => (int) $row->pendingCount,
            ];
        }, $this->db->select(
            <<<'SQL'
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
            SQL
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT
                    SUM(CASE WHEN status IN ('resolved', 'external_only') THEN 1 ELSE 0 END) AS totalItems,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolvedCount,
                    SUM(CASE WHEN status = 'external_only' THEN 1 ELSE 0 END) AS externalOnlyCount,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failedCount,
                    MAX(COALESCE(resolved_at, ingested_at)) AS lastUpdatedAt
                FROM tweets t
                JOIN sources s ON s.id = t.source_id
                WHERE s.enabled = 1
            SQL
        );

        return [
            'totalItems' => (int) ($row->totalItems ?? 0),
            'resolvedCount' => (int) ($row->resolvedCount ?? 0),
            'externalOnlyCount' => (int) ($row->externalOnlyCount ?? 0),
            'failedCount' => (int) ($row->failedCount ?? 0),
            'lastUpdatedAt' => $row->lastUpdatedAt ?? null,
        ];
    }

    public function countTweetsByStatus(string $status): int
    {
        $row = $this->db->selectOne('SELECT COUNT(*) AS count FROM tweets WHERE status = ?', [$status]);

        return (int) ($row->count ?? 0);
    }
}
