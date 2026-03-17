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
                $normalizedHandle = ShortVideoData::normalizeHandle((string) ($source['handle'] ?? ''));
                if ($normalizedHandle === '') {
                    continue;
                }

                $existing = $this->db->table('sources')->where('handle', $normalizedHandle)->first();

                if ($existing) {
                    $this->db->table('sources')
                        ->where('handle', $normalizedHandle)
                        ->update(['enabled' => $source['enabled'] ? 1 : 0]);
                    $sourceId = (int) $existing->id;
                } else {
                    $sourceId = (int) $this->db->table('sources')->insertGetId([
                        'handle' => $normalizedHandle,
                        'enabled' => $source['enabled'] ? 1 : 0,
                    ]);
                }

                $this->syncSourceCreatorIdentity($sourceId, $normalizedHandle);
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
                'userId' => $row->userId !== null ? (int) $row->userId : null,
                'enabled' => (bool) $row->enabled,
                'lastDiscoveredAt' => $row->lastDiscoveredAt ? (string) $row->lastDiscoveredAt : null,
            ],
            $this->db->select(
                <<<'SQL'
                    SELECT id, handle, user_id AS userId, enabled, last_discovered_at AS lastDiscoveredAt
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
                $this->syncSourceCreatorIdentity($sourceId, $sourceHandle);
                $authorHandle = $sourceHandle;
            }

            $authorUserId = $this->ensureExternalCreatorIdentity(
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
    ): array
    {
        ['cursorSort' => $cursorSort, 'cursorTweetId' => $cursorTweetId] = FeedCursor::decode($cursor);
        $normalizedMode = $mode === 'following' ? 'following' : 'explore';
        $rows = $this->selectFeedRows(
            $sourceHandle,
            $limit + 1,
            $normalizedMode,
            $viewerUserId,
            $cursorSort,
            $cursorTweetId
        );

        $hasMore = count($rows) > $limit;
        $items = array_slice($rows, 0, $limit);

        return [
            'items' => $items,
            'nextCursor' => $hasMore ? FeedCursor::encode($items) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLatestPublicFeedCandidates(int $limit = 1000, ?int $viewerUserId = null): array
    {
        return $this->selectFeedRows(null, max(1, $limit), 'explore', $viewerUserId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getVideoEngagementSnapshot(int $videoId, ?int $viewerUserId = null): array
    {
        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT
                    COALESCE((SELECT COUNT(*) FROM video_likes WHERE video_id = ?), 0) AS likeCount,
                    COALESCE((SELECT COUNT(*) FROM video_bookmarks WHERE video_id = ?), 0) AS bookmarkCount,
                    COALESCE((SELECT COUNT(*) FROM video_comments WHERE video_id = ?), 0) AS commentCount,
                    COALESCE((SELECT COUNT(*) FROM video_views WHERE video_id = ?), 0) AS viewCount,
                    CASE
                        WHEN ? IS NOT NULL AND EXISTS (
                            SELECT 1
                            FROM video_likes
                            WHERE video_id = ?
                              AND user_id = ?
                        ) THEN 1
                        ELSE 0
                    END AS likedByViewer,
                    CASE
                        WHEN ? IS NOT NULL AND EXISTS (
                            SELECT 1
                            FROM video_bookmarks
                            WHERE video_id = ?
                              AND user_id = ?
                        ) THEN 1
                        ELSE 0
                    END AS bookmarkedByViewer
            SQL,
            [
                $videoId,
                $videoId,
                $videoId,
                $videoId,
                $viewerUserId,
                $videoId,
                $viewerUserId,
                $viewerUserId,
                $videoId,
                $viewerUserId,
            ]
        );

        return [
            'likeCount' => (int) ($row->likeCount ?? 0),
            'bookmarkCount' => (int) ($row->bookmarkCount ?? 0),
            'commentCount' => (int) ($row->commentCount ?? 0),
            'viewCount' => (int) ($row->viewCount ?? 0),
            'likedByViewer' => (bool) ($row->likedByViewer ?? false),
            'bookmarkedByViewer' => (bool) ($row->bookmarkedByViewer ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setVideoLikeState(int $videoId, int $userId, bool $liked): array
    {
        $timestamp = now();
        if ($liked) {
            $this->db->table('video_likes')->insertOrIgnore([
                'video_id' => $videoId,
                'user_id' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } else {
            $this->db->table('video_likes')
                ->where('video_id', $videoId)
                ->where('user_id', $userId)
                ->delete();
        }

        return $this->getVideoEngagementSnapshot($videoId, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function setVideoBookmarkState(int $videoId, int $userId, bool $bookmarked): array
    {
        $timestamp = now();
        if ($bookmarked) {
            $this->db->table('video_bookmarks')->insertOrIgnore([
                'video_id' => $videoId,
                'user_id' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } else {
            $this->db->table('video_bookmarks')
                ->where('video_id', $videoId)
                ->where('user_id', $userId)
                ->delete();
        }

        return $this->getVideoEngagementSnapshot($videoId, $userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listVideoComments(int $videoId, int $limit = 50): array
    {
        return array_map(
            fn (object $row): array => [
                'id' => (int) $row->id,
                'body' => (string) $row->body,
                'createdAt' => $row->createdAt ? (string) $row->createdAt : null,
                'author' => [
                    'id' => (int) $row->authorId,
                    'name' => (string) $row->authorName,
                    'username' => (string) $row->authorUsername,
                    'avatarUrl' => $row->authorAvatarUrl ? (string) $row->authorAvatarUrl : null,
                ],
            ],
            $this->db->select(
                <<<'SQL'
                    SELECT
                        vc.id,
                        vc.body,
                        vc.created_at AS createdAt,
                        u.id AS authorId,
                        COALESCE(u.name, u.username) AS authorName,
                        u.username AS authorUsername,
                        u.avatar_url AS authorAvatarUrl
                    FROM video_comments vc
                    JOIN users u ON u.id = vc.user_id
                    WHERE vc.video_id = ?
                      AND vc.parent_id IS NULL
                    ORDER BY vc.created_at DESC, vc.id DESC
                    LIMIT ?
                SQL,
                [$videoId, max(1, $limit)]
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createVideoComment(int $videoId, int $userId, string $body): array
    {
        $timestamp = now();
        $commentId = (int) $this->db->table('video_comments')->insertGetId([
            'video_id' => $videoId,
            'user_id' => $userId,
            'parent_id' => null,
            'body' => $body,
            'edited_at' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT
                    vc.id,
                    vc.body,
                    vc.created_at AS createdAt,
                    u.id AS authorId,
                    COALESCE(u.name, u.username) AS authorName,
                    u.username AS authorUsername,
                    u.avatar_url AS authorAvatarUrl
                FROM video_comments vc
                JOIN users u ON u.id = vc.user_id
                WHERE vc.id = ?
                LIMIT 1
            SQL,
            [$commentId]
        );

        return [
            'id' => (int) ($row->id ?? $commentId),
            'body' => (string) ($row->body ?? $body),
            'createdAt' => $row?->createdAt ? (string) $row->createdAt : $timestamp->toISOString(),
            'author' => [
                'id' => (int) ($row->authorId ?? $userId),
                'name' => (string) ($row->authorName ?? ''),
                'username' => (string) ($row->authorUsername ?? ''),
                'avatarUrl' => $row?->authorAvatarUrl ? (string) $row->authorAvatarUrl : null,
            ],
        ];
    }

    public function recordVideoView(int $videoId, ?int $userId, string $sessionId, ?string $viewDate = null): bool
    {
        $timestamp = now();
        $changes = $this->db->table('video_views')->insertOrIgnore([
            'video_id' => $videoId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'view_date' => $viewDate ?: $timestamp->toDateString(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $changes > 0;
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
    private function selectFeedRows(
        ?string $sourceHandle,
        int $limit,
        string $mode,
        ?int $viewerUserId,
        ?string $cursorSort = null,
        ?string $cursorTweetId = null
    ): array {
        $sortExpression = 'COALESCE(v.published_at, t.posted_at, t.ingested_at, v.created_at)';
        $cursorTweetExpression = 'COALESCE(t.tweet_id, CAST(v.id AS TEXT))';

        return array_map(
            fn (object $row): array => $this->mapFeedRow($row),
            $this->db->select(
                <<<SQL
                    SELECT
                        v.id AS videoId,
                        t.tweet_id AS tweetId,
                        t.tweet_url AS tweetUrl,
                        COALESCE(t.author_handle, s.handle, u.username) AS authorHandle,
                        COALESCE(u.name, t.author_name, '@' || COALESCE(t.author_handle, s.handle, u.username, CAST(v.id AS TEXT))) AS authorName,
                        COALESCE(u.avatar_url, t.author_avatar_url) AS authorAvatarUrl,
                        COALESCE(v.uploader_user_id, s.user_id) AS authorUserId,
                        u.username AS authorUsername,
                        u.account_type AS authorAccountType,
                        COALESCE(v.caption, t.text, v.title) AS text,
                        {$sortExpression} AS postedAt,
                        COALESCE(v.duration_text, t.duration_text) AS durationText,
                        COALESCE(v.poster_url, t.poster_url) AS posterUrl,
                        CASE
                            WHEN t.status IN ('resolved', 'external_only') THEN t.status
                            WHEN v.status = 'published' THEN 'resolved'
                            ELSE COALESCE(t.status, v.status, 'pending')
                        END AS status,
                        COALESCE(
                            v.playback_url,
                            (
                                SELECT asset.url
                                FROM media_assets asset
                                WHERE asset.tweet_id = t.tweet_id
                                  AND asset.is_primary = 1
                                LIMIT 1
                            )
                        ) AS videoUrl,
                        COALESCE(
                            v.hls_url,
                            (
                                SELECT asset.url
                                FROM media_assets asset
                                WHERE asset.tweet_id = t.tweet_id
                                  AND asset.content_type IN ('application/x-mpegURL', 'application/vnd.apple.mpegurl')
                                ORDER BY asset.sort_order ASC, asset.id ASC
                                LIMIT 1
                            )
                        ) AS hlsUrl,
                        COALESCE(
                            v.width,
                            (
                                SELECT asset.width
                                FROM media_assets asset
                                WHERE asset.tweet_id = t.tweet_id
                                  AND asset.is_primary = 1
                                LIMIT 1
                            )
                        ) AS mediaWidth,
                        COALESCE(
                            v.height,
                            (
                                SELECT asset.height
                                FROM media_assets asset
                                WHERE asset.tweet_id = t.tweet_id
                                  AND asset.is_primary = 1
                                LIMIT 1
                            )
                        ) AS mediaHeight,
                        s.handle AS sourceHandle,
                        COALESCE((SELECT COUNT(*) FROM video_likes vl WHERE vl.video_id = v.id), 0) AS likeCount,
                        COALESCE((SELECT COUNT(*) FROM video_bookmarks vb WHERE vb.video_id = v.id), 0) AS bookmarkCount,
                        COALESCE((SELECT COUNT(*) FROM video_comments vc WHERE vc.video_id = v.id), 0) AS commentCount,
                        COALESCE((SELECT COUNT(*) FROM video_views vv WHERE vv.video_id = v.id), 0) AS viewCount,
                        CASE
                            WHEN ? IS NOT NULL AND EXISTS (
                                SELECT 1
                                FROM video_likes vl
                                WHERE vl.video_id = v.id
                                  AND vl.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS likedByViewer,
                        CASE
                            WHEN ? IS NOT NULL AND EXISTS (
                                SELECT 1
                                FROM video_bookmarks vb
                                WHERE vb.video_id = v.id
                                  AND vb.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS bookmarkedByViewer,
                        {$sortExpression} AS sortValue,
                        NULL AS secondarySortValue,
                        {$cursorTweetExpression} AS cursorTweetId
                    FROM videos v
                    LEFT JOIN tweets t ON t.tweet_id = v.tweet_id
                    LEFT JOIN sources s ON s.id = v.source_id
                    LEFT JOIN users u ON u.id = COALESCE(v.uploader_user_id, s.user_id)
                    WHERE v.status = 'published'
                      AND v.visibility = 'public'
                      AND (s.enabled = 1 OR s.id IS NULL)
                      AND (? IS NULL OR s.handle = ?)
                      AND (
                        ? <> 'following'
                        OR (
                          ? IS NOT NULL
                          AND EXISTS (
                            SELECT 1
                            FROM user_follows uf
                            WHERE uf.follower_user_id = ?
                              AND uf.followed_user_id = COALESCE(v.uploader_user_id, s.user_id)
                          )
                        )
                      )
                      AND (
                        ? IS NULL
                        OR {$sortExpression} < ?
                        OR (
                          {$sortExpression} = ?
                          AND {$cursorTweetExpression} < ?
                        )
                      )
                    ORDER BY {$sortExpression} DESC, {$cursorTweetExpression} DESC
                    LIMIT ?
                SQL,
                [
                    $viewerUserId,
                    $viewerUserId,
                    $viewerUserId,
                    $viewerUserId,
                    $sourceHandle,
                    $sourceHandle,
                    $mode,
                    $viewerUserId,
                    $viewerUserId,
                    $cursorSort,
                    $cursorSort,
                    $cursorSort,
                    $cursorTweetId,
                    $limit,
                ]
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFeedRow(object $row): array
    {
        return [
            'videoId' => (int) $row->videoId,
            'tweetId' => $row->tweetId ? (string) $row->tweetId : null,
            'tweetUrl' => $row->tweetUrl ? (string) $row->tweetUrl : null,
            'authorHandle' => $row->authorHandle ? (string) $row->authorHandle : '',
            'authorName' => $row->authorName ? (string) $row->authorName : '',
            'authorAvatarUrl' => $row->authorAvatarUrl ? (string) $row->authorAvatarUrl : null,
            'authorUserId' => $row->authorUserId !== null ? (int) $row->authorUserId : null,
            'authorUsername' => $row->authorUsername ? (string) $row->authorUsername : null,
            'authorAccountType' => $row->authorAccountType ? (string) $row->authorAccountType : null,
            'text' => $row->text ? (string) $row->text : '',
            'postedAt' => $row->postedAt ? (string) $row->postedAt : null,
            'durationText' => $row->durationText ? (string) $row->durationText : null,
            'posterUrl' => $row->posterUrl ? (string) $row->posterUrl : null,
            'status' => $row->status ? (string) $row->status : 'pending',
            'videoUrl' => $row->videoUrl ? (string) $row->videoUrl : null,
            'hlsUrl' => $row->hlsUrl ? (string) $row->hlsUrl : null,
            'mediaWidth' => $row->mediaWidth !== null ? (int) $row->mediaWidth : null,
            'mediaHeight' => $row->mediaHeight !== null ? (int) $row->mediaHeight : null,
            'sourceHandle' => $row->sourceHandle ? (string) $row->sourceHandle : '',
            'engagement' => [
                'likeCount' => (int) ($row->likeCount ?? 0),
                'bookmarkCount' => (int) ($row->bookmarkCount ?? 0),
                'commentCount' => (int) ($row->commentCount ?? 0),
                'viewCount' => (int) ($row->viewCount ?? 0),
                'likedByViewer' => (bool) ($row->likedByViewer ?? false),
                'bookmarkedByViewer' => (bool) ($row->bookmarkedByViewer ?? false),
            ],
            'sortValue' => $row->sortValue ? (string) $row->sortValue : null,
            'secondarySortValue' => $row->secondarySortValue ? (string) $row->secondarySortValue : null,
            'cursorTweetId' => $row->cursorTweetId ? (string) $row->cursorTweetId : ($row->tweetId ? (string) $row->tweetId : null),
        ];
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
                'userId' => $row->userId !== null ? (int) $row->userId : null,
                'creatorName' => $row->creatorName ? (string) $row->creatorName : null,
                'creatorUsername' => $row->creatorUsername ? (string) $row->creatorUsername : null,
                'creatorAccountType' => $row->creatorAccountType ? (string) $row->creatorAccountType : null,
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
                    s.user_id AS userId,
                    u.name AS creatorName,
                    u.username AS creatorUsername,
                    u.account_type AS creatorAccountType,
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
                LEFT JOIN users u ON u.id = s.user_id
                ORDER BY s.enabled DESC, s.handle ASC
            SQL
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $sevenDaysAgo = now()->subDays(7)->toISOString();
        $row = $this->db->selectOne(
            <<<'SQL'
                SELECT
                    SUM(CASE WHEN status IN ('resolved', 'external_only') THEN 1 ELSE 0 END) AS totalItems,
                    SUM(CASE WHEN status IN ('resolved', 'external_only') AND COALESCE(posted_at, ingested_at) >= ? THEN 1 ELSE 0 END) AS recentPublishedCount7d,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolvedCount,
                    SUM(CASE WHEN status = 'external_only' THEN 1 ELSE 0 END) AS externalOnlyCount,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failedCount,
                    MAX(COALESCE(resolved_at, ingested_at)) AS lastUpdatedAt
                FROM tweets t
                JOIN sources s ON s.id = t.source_id
                WHERE s.enabled = 1
            SQL,
            [$sevenDaysAgo]
        );

        return [
            'totalItems' => (int) ($row->totalItems ?? 0),
            'recentPublishedCount7d' => (int) ($row->recentPublishedCount7d ?? 0),
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

    /**
     * @param  array<int, int|null>  $candidateUserIds
     * @return list<int>
     */
    public function getFollowedUserIds(int $viewerUserId, array $candidateUserIds): array
    {
        if (! $this->db->getSchemaBuilder()->hasTable('user_follows')) {
            return [];
        }

        $normalizedIds = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $id): ?int => is_numeric((string) $id) ? (int) $id : null,
                $candidateUserIds
            ),
            static fn (?int $id): bool => $id !== null
        )));

        if ($normalizedIds === []) {
            return [];
        }

        return array_map(
            static fn (mixed $id): int => (int) $id,
            $this->db->table('user_follows')
                ->where('follower_user_id', $viewerUserId)
                ->whereIn('followed_user_id', $normalizedIds)
                ->pluck('followed_user_id')
                ->all()
        );
    }

    public function followUser(int $viewerUserId, int $followedUserId): void
    {
        if (! $this->db->getSchemaBuilder()->hasTable('user_follows')) {
            return;
        }

        $timestamp = now();
        $this->db->table('user_follows')->insertOrIgnore([
            'follower_user_id' => $viewerUserId,
            'followed_user_id' => $followedUserId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function unfollowUser(int $viewerUserId, int $followedUserId): void
    {
        if (! $this->db->getSchemaBuilder()->hasTable('user_follows')) {
            return;
        }

        $this->db->table('user_follows')
            ->where('follower_user_id', $viewerUserId)
            ->where('followed_user_id', $followedUserId)
            ->delete();
    }

    public function countFollowingUsers(int $viewerUserId): int
    {
        if (! $this->db->getSchemaBuilder()->hasTable('user_follows')) {
            return 0;
        }

        return (int) $this->db->table('user_follows')
            ->where('follower_user_id', $viewerUserId)
            ->count();
    }

    public function countFollowerUsers(int $viewerUserId): int
    {
        if (! $this->db->getSchemaBuilder()->hasTable('user_follows')) {
            return 0;
        }

        return (int) $this->db->table('user_follows')
            ->where('followed_user_id', $viewerUserId)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCreatorRankings(int $windowDays = 7, int $limit = 50): array
    {
        $normalizedWindowDays = max(1, $windowDays);
        $normalizedLimit = max(1, $limit);
        $windowStart = now()->subDays($normalizedWindowDays)->toISOString();

        return array_map(
            static fn (object $row): array => [
                'userId' => (int) $row->userId,
                'username' => (string) $row->username,
                'name' => (string) $row->name,
                'avatarUrl' => $row->avatarUrl ? (string) $row->avatarUrl : null,
                'publishedCount7d' => (int) $row->publishedCount7d,
                'totalVideos' => (int) $row->totalVideos,
                'lastPublishedAt' => $row->lastPublishedAt ? (string) $row->lastPublishedAt : null,
            ],
            $this->db->select(
                <<<'SQL'
                    WITH creator_activity AS (
                        SELECT
                            u.id AS userId,
                            u.username AS username,
                            u.name AS name,
                            u.avatar_url AS avatarUrl,
                            SUM(
                                CASE
                                    WHEN COALESCE(v.published_at, v.created_at) >= ? THEN 1
                                    ELSE 0
                                END
                            ) AS publishedCount7d,
                            COUNT(v.id) AS totalVideos,
                            MAX(COALESCE(v.published_at, v.created_at)) AS lastPublishedAt
                        FROM videos v
                        JOIN users u ON u.id = v.uploader_user_id
                        WHERE v.status = 'published'
                          AND v.visibility = 'public'
                        GROUP BY u.id, u.username, u.name, u.avatar_url
                    )
                    SELECT
                        userId,
                        username,
                        name,
                        avatarUrl,
                        publishedCount7d,
                        totalVideos,
                        lastPublishedAt
                    FROM creator_activity
                    WHERE publishedCount7d > 0
                    ORDER BY publishedCount7d DESC, lastPublishedAt DESC, totalVideos DESC, userId ASC
                    LIMIT ?
                SQL,
                [$windowStart, $normalizedLimit]
            )
        );
    }

    private function syncImportedVideo(string $tweetId, string $status, ?int $authorUserId = null): void
    {
        if (! $this->db->getSchemaBuilder()->hasTable('videos')) {
            return;
        }

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

    private function syncSourceCreatorIdentity(int $sourceId, string $handle): ?int
    {
        if (! $this->db->getSchemaBuilder()->hasColumn('sources', 'user_id')) {
            return null;
        }

        $userId = $this->ensureExternalCreatorIdentity($handle, '@'.ShortVideoData::normalizeHandle($handle), null);
        if ($userId === null) {
            return null;
        }

        $this->db->table('sources')->where('id', $sourceId)->update(['user_id' => $userId]);

        return $userId;
    }

    private function ensureExternalCreatorIdentity(?string $handle, ?string $name, ?string $avatarUrl): ?int
    {
        if (! $this->db->getSchemaBuilder()->hasTable('users') || ! $this->db->getSchemaBuilder()->hasTable('user_external_accounts')) {
            return null;
        }

        $normalizedHandle = ShortVideoData::normalizeHandle($handle);
        if ($normalizedHandle === '') {
            return null;
        }

        $existingAccount = $this->db->table('user_external_accounts')
            ->where('provider', 'x')
            ->where('handle', $normalizedHandle)
            ->first();

        if ($existingAccount) {
            $userId = (int) $existingAccount->user_id;
            $this->refreshExternalCreatorUser($userId, $normalizedHandle, $name, $avatarUrl);

            return $userId;
        }

        $existingExternalUser = $this->db->table('users')
            ->where('username', $normalizedHandle)
            ->where('account_type', 'external_creator')
            ->first();

        if ($existingExternalUser) {
            $userId = (int) $existingExternalUser->id;
            $this->refreshExternalCreatorUser($userId, $normalizedHandle, $name, $avatarUrl);
        } else {
            $timestamp = now();
            $userId = (int) $this->db->table('users')->insertGetId([
                'name' => is_string($name) && trim($name) !== '' ? trim($name) : '@'.$normalizedHandle,
                'username' => $this->generateUniqueExternalUsername($normalizedHandle),
                'account_type' => 'external_creator',
                'email' => null,
                'phone' => null,
                'email_verified_at' => null,
                'password' => null,
                'avatar_url' => is_string($avatarUrl) && trim($avatarUrl) !== '' ? trim($avatarUrl) : null,
                'bio' => null,
                'last_login_at' => null,
                'remember_token' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $timestamp = now();
        $this->db->table('user_external_accounts')->updateOrInsert(
            [
                'provider' => 'x',
                'handle' => $normalizedHandle,
            ],
            [
                'user_id' => $userId,
                'provider_user_id' => null,
                'profile_url' => 'https://x.com/'.$normalizedHandle,
                'raw_payload' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]
        );

        return $userId;
    }

    private function refreshExternalCreatorUser(int $userId, string $handle, ?string $name, ?string $avatarUrl): void
    {
        $user = $this->db->table('users')
            ->select('account_type', 'name', 'avatar_url')
            ->where('id', $userId)
            ->first();

        if (! $user || ($user->account_type ?? 'local') !== 'external_creator') {
            return;
        }

        $updates = [
            'name' => is_string($name) && trim($name) !== '' ? trim($name) : (($user->name ?? null) ?: '@'.$handle),
            'updated_at' => now(),
        ];

        if (is_string($avatarUrl) && trim($avatarUrl) !== '') {
            $updates['avatar_url'] = trim($avatarUrl);
        }

        $this->db->table('users')->where('id', $userId)->update($updates);
    }

    private function generateUniqueExternalUsername(string $handle): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '_', $handle) ?? '';
        $base = trim($base, '_');
        $base = $base !== '' ? substr($base, 0, 24) : 'x_user';

        $candidate = $base;
        $counter = 1;
        while ($this->db->table('users')->where('username', $candidate)->exists()) {
            $suffix = $counter === 1 ? '_x' : '_x'.$counter;
            $candidate = substr($base, 0, max(1, 32 - strlen($suffix))).$suffix;
            $counter++;
        }

        return $candidate;
    }
}
