<?php

namespace App\ShortVideo\Repositories;

use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Database\ConnectionInterface;

final class ShortVideoRepository
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly SourceRepository $sources,
        private readonly FeedRepository $feeds,
        private readonly EngagementRepository $engagement,
        private readonly SocialGraphRepository $socialGraph,
        private readonly CreatorIdentityRepository $creatorIdentities
    ) {}

    /**
     * @param  array<int, array{handle: string, enabled: bool}>  $sources
     * @return array<int, array<string, mixed>>
     */
    public function syncSources(array $sources): array
    {
        return $this->sources->syncSources($sources);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSources(): array
    {
        return $this->sources->listSources();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEnabledSources(): array
    {
        return $this->sources->listEnabledSources();
    }

    public function touchSourceLastDiscovered(int $sourceId): void
    {
        $this->sources->touchSourceLastDiscovered($sourceId);
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

            $authorHandle = ShortVideoData::normalizeHandle((string) ($tweet['authorHandle'] ?? ''));
            $sourceRow = $this->db->selectOne(
                <<<'SQL'
                    SELECT t.source_id AS sourceId, s.handle AS sourceHandle
                    FROM tweets t
                    JOIN sources s ON s.id = t.source_id
                    WHERE t.tweet_id = ?
                    LIMIT 1
                SQL,
                [$tweetId]
            );

            $sourceId = $sourceRow?->sourceId !== null ? (int) $sourceRow->sourceId : null;
            $sourceHandle = $sourceRow?->sourceHandle !== null
                ? ShortVideoData::normalizeHandle((string) $sourceRow->sourceHandle)
                : '';

            if ($authorHandle === '' && $sourceId !== null) {
                $this->creatorIdentities->syncSourceCreatorIdentity($sourceId, $sourceHandle);
                $authorHandle = $sourceHandle;
            }

            $authorUserId = $this->creatorIdentities->ensureExternalCreatorIdentity(
                $authorHandle,
                $tweet['authorName'] ?? null,
                $tweet['authorAvatarUrl'] ?? null
            );

            if ($sourceId !== null && $sourceHandle !== '' && $sourceHandle === $authorHandle && $authorUserId !== null) {
                $this->db->table('sources')->where('id', $sourceId)->update(['user_id' => $authorUserId]);
            }

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

            $this->syncImportedVideo($tweetId, $status, $authorUserId);
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
    public function getFeed(
        ?string $cursor = null,
        ?string $sourceHandle = null,
        int $limit = 12,
        string $mode = 'explore',
        ?int $viewerUserId = null
    ): array {
        return $this->feeds->getFeed($cursor, $sourceHandle, $limit, $mode, $viewerUserId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatestPublicFeedCandidates(int $limit = 1000, ?int $viewerUserId = null): array
    {
        return $this->feeds->getLatestPublicFeedCandidates($limit, $viewerUserId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getVideoEngagementSnapshot(int $videoId, ?int $viewerUserId = null): array
    {
        return $this->engagement->getVideoEngagementSnapshot($videoId, $viewerUserId);
    }

    /**
     * @return array<string, mixed>
     */
    public function setVideoLikeState(int $videoId, int $userId, bool $liked): array
    {
        return $this->engagement->setVideoLikeState($videoId, $userId, $liked);
    }

    /**
     * @return array<string, mixed>
     */
    public function setVideoBookmarkState(int $videoId, int $userId, bool $bookmarked): array
    {
        return $this->engagement->setVideoBookmarkState($videoId, $userId, $bookmarked);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listVideoComments(int $videoId, int $limit = 50): array
    {
        return $this->engagement->listVideoComments($videoId, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function createVideoComment(int $videoId, int $userId, string $body): array
    {
        return $this->engagement->createVideoComment($videoId, $userId, $body);
    }

    public function recordVideoView(int $videoId, ?int $userId, string $sessionId, ?string $viewDate = null): bool
    {
        return $this->engagement->recordVideoView($videoId, $userId, $sessionId, $viewDate);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPrimaryMedia(string $tweetId): ?array
    {
        return $this->feeds->getPrimaryMedia($tweetId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSourcesOverview(): array
    {
        return $this->sources->getSourcesOverview();
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->sources->getStats();
    }

    public function countTweetsByStatus(string $status): int
    {
        return $this->sources->countTweetsByStatus($status);
    }

    /**
     * @param  array<int, int|null>  $candidateUserIds
     * @return list<int>
     */
    public function getFollowedUserIds(int $viewerUserId, array $candidateUserIds): array
    {
        return $this->socialGraph->getFollowedUserIds($viewerUserId, $candidateUserIds);
    }

    public function followUser(int $viewerUserId, int $followedUserId): void
    {
        $this->socialGraph->followUser($viewerUserId, $followedUserId);
    }

    public function unfollowUser(int $viewerUserId, int $followedUserId): void
    {
        $this->socialGraph->unfollowUser($viewerUserId, $followedUserId);
    }

    public function countFollowingUsers(int $viewerUserId): int
    {
        return $this->socialGraph->countFollowingUsers($viewerUserId);
    }

    public function countFollowerUsers(int $viewerUserId): int
    {
        return $this->socialGraph->countFollowerUsers($viewerUserId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCreatorRankings(int $windowDays = 7, int $limit = 50): array
    {
        return $this->feeds->getCreatorRankings($windowDays, $limit);
    }

    private function syncImportedVideo(string $tweetId, string $status, ?int $authorUserId = null): void
    {
        if (! in_array($status, ['resolved', 'external_only'], true)) {
            $this->db->table('videos')->where('tweet_id', $tweetId)->delete();

            return;
        }

        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT
                    t.tweet_id AS tweetId,
                    t.source_id AS sourceId,
                    t.text AS caption,
                    t.poster_url AS posterUrl,
                    t.duration_text AS durationText,
                    s.user_id AS sourceUserId,
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
                WHERE t.tweet_id = ?
                LIMIT 1
            SQL,
            [$tweetId]
        );

        if (! $row) {
            return;
        }

        $payload = [
            'origin' => 'x_tweet',
            'source_id' => (int) $row->sourceId,
            'uploader_user_id' => $authorUserId ?? ($row->sourceUserId !== null ? (int) $row->sourceUserId : null),
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
            'updated_at' => now(),
        ];

        $existingId = $this->db->table('videos')->where('tweet_id', $tweetId)->value('id');

        if ($existingId !== null) {
            $this->db->table('videos')->where('id', $existingId)->update($payload);

            return;
        }

        $this->db->table('videos')->insert($payload + [
            'tweet_id' => $tweetId,
            'created_at' => now(),
        ]);
    }
}
