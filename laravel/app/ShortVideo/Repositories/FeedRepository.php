<?php

namespace App\ShortVideo\Repositories;

use App\ShortVideo\Support\FeedCursor;
use Illuminate\Database\ConnectionInterface;

final class FeedRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, nextCursor: ?string}
     */
    public function getFeed(
        ?string $cursor = null,
        ?string $sourceHandle = null,
        int $limit = 12,
        string $mode = 'explore',
        ?int $viewerUserId = null,
        ?string $query = null
    ): array {
        ['cursorSort' => $cursorSort, 'cursorTweetId' => $cursorTweetId] = FeedCursor::decode($cursor);
        $normalizedMode = $mode === 'following' ? 'following' : 'explore';
        $rows = $this->selectFeedRows(
            $sourceHandle,
            $limit + 1,
            $normalizedMode,
            $viewerUserId,
            $query,
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
    public function getLatestPublicFeedCandidates(
        int $limit = 1000,
        ?int $viewerUserId = null,
        ?string $query = null
    ): array {
        return $this->selectFeedRows(null, max(1, $limit), 'explore', $viewerUserId, $query);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPublishedFeedForAuthor(int $authorUserId, int $limit = 24, ?int $viewerUserId = null): array
    {
        return $this->selectFeedRows(
            null,
            max(1, $limit),
            'explore',
            $viewerUserId,
            null,
            null,
            null,
            $authorUserId
        );
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
    public function getCreatorRankings(
        int $windowDays = 7,
        int $limit = 50,
        bool $includeInactiveFallback = false
    ): array {
        $normalizedWindowDays = max(1, $windowDays);
        $normalizedLimit = max(1, $limit);
        $windowStart = now()->subDays($normalizedWindowDays)->toISOString();
        $activityFilter = $includeInactiveFallback ? '' : 'WHERE publishedCount7d > 0';

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
                <<<SQL
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
                    {$activityFilter}
                    ORDER BY publishedCount7d DESC, lastPublishedAt DESC, totalVideos DESC, userId ASC
                    LIMIT ?
                SQL,
                [$windowStart, $normalizedLimit]
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getViewerCollectionFeed(int $viewerUserId, string $collection, int $limit = 12): array
    {
        [$collectionSql, $collectionBindings] = $this->viewerCollectionDefinition($viewerUserId, $collection);

        return $this->selectViewerCollectionRows($collectionSql, $collectionBindings, $viewerUserId, max(1, $limit), 0);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginateViewerCollectionFeed(
        int $viewerUserId,
        string $collection,
        int $page = 1,
        int $perPage = 24
    ): array {
        [$collectionSql, $collectionBindings] = $this->viewerCollectionDefinition($viewerUserId, $collection);
        $normalizedPerPage = max(1, $perPage);
        $normalizedPage = max(1, $page);
        $total = $this->countViewerCollectionRows($collectionSql, $collectionBindings);
        $lastPage = max(1, (int) ceil($total / $normalizedPerPage));
        $resolvedPage = $total === 0 ? 1 : min($normalizedPage, $lastPage);

        return [
            'items' => $total === 0
                ? []
                : $this->selectViewerCollectionRows(
                    $collectionSql,
                    $collectionBindings,
                    $viewerUserId,
                    $normalizedPerPage,
                    ($resolvedPage - 1) * $normalizedPerPage
                ),
            'total' => $total,
            'page' => $resolvedPage,
            'perPage' => $normalizedPerPage,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function paginateViewerInteractions(
        int $viewerUserId,
        int $page = 1,
        int $perPage = 24
    ): array {
        $normalizedPerPage = max(1, $perPage);
        $normalizedPage = max(1, $page);
        $total = $this->countViewerInteractionRows($viewerUserId);
        $lastPage = max(1, (int) ceil($total / $normalizedPerPage));
        $resolvedPage = $total === 0 ? 1 : min($normalizedPage, $lastPage);

        return [
            'items' => $total === 0
                ? []
                : $this->selectViewerInteractionRows(
                    $viewerUserId,
                    $normalizedPerPage,
                    ($resolvedPage - 1) * $normalizedPerPage
                ),
            'total' => $total,
            'page' => $resolvedPage,
            'perPage' => $normalizedPerPage,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selectFeedRows(
        ?string $sourceHandle,
        int $limit,
        string $mode,
        ?int $viewerUserId,
        ?string $query = null,
        ?string $cursorSort = null,
        ?string $cursorTweetId = null,
        ?int $authorUserId = null
    ): array {
        $sortExpression = 'COALESCE(v.published_at, t.posted_at, t.ingested_at, v.created_at)';
        $cursorTweetExpression = 'COALESCE(t.tweet_id, CAST(v.id AS TEXT))';
        $searchLikePattern = $this->buildSearchLikePattern($query);

        return array_map(
            fn (object $row): array => $this->mapFeedRow($row),
            $this->db->select(
                <<<SQL
                    SELECT
                        v.id AS videoId,
                        t.tweet_id AS tweetId,
                        t.tweet_url AS tweetUrl,
                        COALESCE(t.author_handle, s.handle, u.username) AS authorHandle,
                        COALESCE(t.author_name, u.name, '@' || COALESCE(t.author_handle, s.handle, u.username, CAST(v.id AS TEXT))) AS authorName,
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
                        COALESCE((SELECT COUNT(*) FROM video_comments vc WHERE vc.video_id = v.id AND vc.deleted_at IS NULL), 0) AS commentCount,
                        COALESCE((SELECT COUNT(*) FROM video_views vv WHERE vv.video_id = v.id), 0) AS viewCount,
                        CASE
                            WHEN ? IS NOT NULL AND EXISTS (
                                SELECT 1
                                FROM video_views vv
                                WHERE vv.video_id = v.id
                                  AND vv.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS viewedByViewer,
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
                      AND (? IS NULL OR COALESCE(v.uploader_user_id, s.user_id) = ?)
                      AND (
                        ? IS NULL
                        OR LOWER(COALESCE(v.title, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(v.caption, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(v.description, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(t.text, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(u.name, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(u.username, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(t.author_name, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(t.author_handle, '')) LIKE ? ESCAPE '\'
                        OR LOWER(COALESCE(s.handle, '')) LIKE ? ESCAPE '\'
                      )
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
                    $viewerUserId,
                    $viewerUserId,
                    $sourceHandle,
                    $sourceHandle,
                    $authorUserId,
                    $authorUserId,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
                    $searchLikePattern,
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
     * @param  list<mixed>  $collectionBindings
     * @return array<int, array<string, mixed>>
     */
    private function selectViewerCollectionRows(
        string $collectionSql,
        array $collectionBindings,
        int $viewerUserId,
        int $limit,
        int $offset
    ): array {
        $publishedAtExpression = 'COALESCE(v.published_at, t.posted_at, t.ingested_at, v.created_at)';
        $cursorTweetExpression = 'COALESCE(t.tweet_id, CAST(v.id AS TEXT))';

        return array_map(
            fn (object $row): array => $this->mapFeedRow($row),
            $this->db->select(
                <<<SQL
                    WITH collection AS (
                        {$collectionSql}
                    )
                    SELECT
                        v.id AS videoId,
                        t.tweet_id AS tweetId,
                        t.tweet_url AS tweetUrl,
                        COALESCE(t.author_handle, s.handle, u.username) AS authorHandle,
                        COALESCE(t.author_name, u.name, '@' || COALESCE(t.author_handle, s.handle, u.username, CAST(v.id AS TEXT))) AS authorName,
                        COALESCE(u.avatar_url, t.author_avatar_url) AS authorAvatarUrl,
                        COALESCE(v.uploader_user_id, s.user_id) AS authorUserId,
                        u.username AS authorUsername,
                        u.account_type AS authorAccountType,
                        COALESCE(v.caption, t.text, v.title) AS text,
                        {$publishedAtExpression} AS postedAt,
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
                        COALESCE((SELECT COUNT(*) FROM video_comments vc WHERE vc.video_id = v.id AND vc.deleted_at IS NULL), 0) AS commentCount,
                        COALESCE((SELECT COUNT(*) FROM video_views vv WHERE vv.video_id = v.id), 0) AS viewCount,
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM video_likes vl
                                WHERE vl.video_id = v.id
                                  AND vl.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS likedByViewer,
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM video_bookmarks vb
                                WHERE vb.video_id = v.id
                                  AND vb.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS bookmarkedByViewer,
                        collection.collectedAt AS sortValue,
                        {$publishedAtExpression} AS secondarySortValue,
                        {$cursorTweetExpression} AS cursorTweetId
                    FROM collection
                    JOIN videos v ON v.id = collection.videoId
                    LEFT JOIN tweets t ON t.tweet_id = v.tweet_id
                    LEFT JOIN sources s ON s.id = v.source_id
                    LEFT JOIN users u ON u.id = COALESCE(v.uploader_user_id, s.user_id)
                    WHERE v.status = 'published'
                      AND v.visibility = 'public'
                      AND (s.enabled = 1 OR s.id IS NULL)
                    ORDER BY collection.collectedAt DESC, {$publishedAtExpression} DESC, {$cursorTweetExpression} DESC
                    LIMIT ?
                    OFFSET ?
                SQL,
                [
                    ...$collectionBindings,
                    $viewerUserId,
                    $viewerUserId,
                    $limit,
                    max(0, $offset),
                ]
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selectViewerInteractionRows(int $viewerUserId, int $limit, int $offset): array
    {
        $publishedAtExpression = 'COALESCE(v.published_at, t.posted_at, t.ingested_at, v.created_at)';

        return array_map(
            fn (object $row): array => $this->mapInteractionRow($row),
            $this->db->select(
                <<<SQL
                    WITH interaction_events AS (
                        SELECT
                            'like' AS interactionType,
                            NULL AS commentId,
                            NULL AS commentBody,
                            vl.video_id AS videoId,
                            vl.created_at AS interactionAt,
                            NULL AS parentCommentId
                        FROM video_likes vl
                        WHERE vl.user_id = ?

                        UNION ALL

                        SELECT
                            'comment' AS interactionType,
                            vc.id AS commentId,
                            vc.body AS commentBody,
                            vc.video_id AS videoId,
                            vc.created_at AS interactionAt,
                            vc.parent_id AS parentCommentId
                        FROM video_comments vc
                        WHERE vc.user_id = ?
                          AND vc.deleted_at IS NULL
                    )
                    SELECT
                        interaction_events.interactionType,
                        interaction_events.commentId,
                        interaction_events.commentBody,
                        interaction_events.interactionAt,
                        interaction_events.parentCommentId,
                        parent_author.username AS parentAuthorUsername,
                        COALESCE(parent_author.name, parent_author.username) AS parentAuthorName,
                        v.id AS videoId,
                        t.tweet_id AS tweetId,
                        t.tweet_url AS tweetUrl,
                        COALESCE(t.author_handle, s.handle, u.username) AS authorHandle,
                        COALESCE(t.author_name, u.name, '@' || COALESCE(t.author_handle, s.handle, u.username, CAST(v.id AS TEXT))) AS authorName,
                        COALESCE(u.avatar_url, t.author_avatar_url) AS authorAvatarUrl,
                        COALESCE(v.uploader_user_id, s.user_id) AS authorUserId,
                        u.username AS authorUsername,
                        u.account_type AS authorAccountType,
                        COALESCE(v.caption, t.text, v.title) AS text,
                        {$publishedAtExpression} AS postedAt,
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
                        COALESCE((SELECT COUNT(*) FROM video_comments vc WHERE vc.video_id = v.id AND vc.deleted_at IS NULL), 0) AS commentCount,
                        COALESCE((SELECT COUNT(*) FROM video_views vv WHERE vv.video_id = v.id), 0) AS viewCount,
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM video_likes vl
                                WHERE vl.video_id = v.id
                                  AND vl.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS likedByViewer,
                        CASE
                            WHEN EXISTS (
                                SELECT 1
                                FROM video_bookmarks vb
                                WHERE vb.video_id = v.id
                                  AND vb.user_id = ?
                            ) THEN 1
                            ELSE 0
                        END AS bookmarkedByViewer,
                        interaction_events.interactionAt AS sortValue,
                        {$publishedAtExpression} AS secondarySortValue,
                        COALESCE(t.tweet_id, CAST(v.id AS TEXT)) AS cursorTweetId
                    FROM interaction_events
                    JOIN videos v ON v.id = interaction_events.videoId
                    LEFT JOIN tweets t ON t.tweet_id = v.tweet_id
                    LEFT JOIN sources s ON s.id = v.source_id
                    LEFT JOIN users u ON u.id = COALESCE(v.uploader_user_id, s.user_id)
                    LEFT JOIN video_comments parent_comment ON parent_comment.id = interaction_events.parentCommentId
                    LEFT JOIN users parent_author ON parent_author.id = parent_comment.user_id
                    WHERE v.status = 'published'
                      AND v.visibility = 'public'
                      AND (s.enabled = 1 OR s.id IS NULL)
                    ORDER BY interaction_events.interactionAt DESC, interaction_events.videoId DESC, COALESCE(interaction_events.commentId, 0) DESC
                    LIMIT ?
                    OFFSET ?
                SQL,
                [
                    $viewerUserId,
                    $viewerUserId,
                    $viewerUserId,
                    $viewerUserId,
                    $limit,
                    max(0, $offset),
                ]
            )
        );
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private function viewerCollectionDefinition(int $viewerUserId, string $collection): array
    {
        return match ($collection) {
            'history' => [
                <<<'SQL'
                    SELECT
                        vv.video_id AS videoId,
                        MAX(vv.created_at) AS collectedAt
                    FROM video_views vv
                    WHERE vv.user_id = ?
                    GROUP BY vv.video_id
                SQL,
                [$viewerUserId],
            ],
            'bookmarks' => [
                <<<'SQL'
                    SELECT
                        vb.video_id AS videoId,
                        MAX(vb.created_at) AS collectedAt
                    FROM video_bookmarks vb
                    WHERE vb.user_id = ?
                    GROUP BY vb.video_id
                SQL,
                [$viewerUserId],
            ],
            'interactions' => [
                <<<'SQL'
                    SELECT
                        interaction_events.video_id AS videoId,
                        MAX(interaction_events.collectedAt) AS collectedAt
                    FROM (
                        SELECT
                            vl.video_id,
                            vl.created_at AS collectedAt
                        FROM video_likes vl
                        WHERE vl.user_id = ?
                        UNION ALL
                        SELECT
                            vc.video_id,
                            vc.created_at AS collectedAt
                        FROM video_comments vc
                        WHERE vc.user_id = ?
                          AND vc.deleted_at IS NULL
                    ) interaction_events
                    GROUP BY interaction_events.video_id
                SQL,
                [$viewerUserId, $viewerUserId],
            ],
            default => throw new \InvalidArgumentException("Unsupported viewer collection [{$collection}]."),
        };
    }

    /**
     * @param  list<mixed>  $collectionBindings
     */
    private function countViewerCollectionRows(string $collectionSql, array $collectionBindings): int
    {
        $row = $this->db->selectOne(
            <<<SQL
                WITH collection AS (
                    {$collectionSql}
                )
                SELECT COUNT(*) AS aggregateCount
                FROM collection
            SQL,
            $collectionBindings
        );

        return max(0, (int) ($row->aggregateCount ?? 0));
    }

    private function countViewerInteractionRows(int $viewerUserId): int
    {
        $row = $this->db->selectOne(
            <<<'SQL'
                WITH interaction_events AS (
                    SELECT vl.video_id AS videoId
                    FROM video_likes vl
                    WHERE vl.user_id = ?

                    UNION ALL

                    SELECT vc.video_id AS videoId
                    FROM video_comments vc
                    WHERE vc.user_id = ?
                      AND vc.deleted_at IS NULL
                )
                SELECT COUNT(*) AS aggregateCount
                FROM interaction_events
                JOIN videos v ON v.id = interaction_events.videoId
                LEFT JOIN sources s ON s.id = v.source_id
                WHERE v.status = 'published'
                  AND v.visibility = 'public'
                  AND (s.enabled = 1 OR s.id IS NULL)
            SQL,
            [$viewerUserId, $viewerUserId]
        );

        return max(0, (int) ($row->aggregateCount ?? 0));
    }

    private function buildSearchLikePattern(?string $query): ?string
    {
        if (! is_string($query) || trim($query) === '') {
            return null;
        }

        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            mb_strtolower($query)
        );

        return '%'.$escaped.'%';
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
            'viewedByViewer' => (bool) ($row->viewedByViewer ?? false),
            'sortValue' => $row->sortValue ? (string) $row->sortValue : null,
            'secondarySortValue' => $row->secondarySortValue ? (string) $row->secondarySortValue : null,
            'cursorTweetId' => $row->cursorTweetId ? (string) $row->cursorTweetId : ($row->tweetId ? (string) $row->tweetId : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInteractionRow(object $row): array
    {
        return array_merge($this->mapFeedRow($row), [
            'interactionType' => (string) ($row->interactionType ?? ''),
            'interactionAt' => $row->interactionAt ? (string) $row->interactionAt : null,
            'commentId' => $row->commentId !== null ? (int) $row->commentId : null,
            'commentBody' => $row->commentBody ? (string) $row->commentBody : null,
            'parentCommentId' => $row->parentCommentId !== null ? (int) $row->parentCommentId : null,
            'parentAuthorName' => $row->parentAuthorName ? (string) $row->parentAuthorName : null,
            'parentAuthorUsername' => $row->parentAuthorUsername ? (string) $row->parentAuthorUsername : null,
        ]);
    }
}
