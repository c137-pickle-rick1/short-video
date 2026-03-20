<?php

namespace App\ShortVideo\Repositories;

use Illuminate\Database\ConnectionInterface;

final class EngagementRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

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
                    COALESCE((SELECT COUNT(*) FROM video_comments WHERE video_id = ? AND deleted_at IS NULL), 0) AS commentCount,
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
            fn (object $row): array => $this->mapCommentRow($row),
            $this->db->select(
                <<<'SQL'
                    WITH reply_counts AS (
                        SELECT
                            vc.parent_id AS rootCommentId,
                            COUNT(*) AS replyCount
                        FROM video_comments vc
                        WHERE vc.video_id = ?
                          AND vc.parent_id IS NOT NULL
                        GROUP BY vc.parent_id
                    )
                    SELECT
                        vc.id,
                        vc.parent_id AS parentId,
                        vc.reply_to_comment_id AS replyToCommentId,
                        vc.body,
                        vc.created_at AS createdAt,
                        vc.deleted_at AS deletedAt,
                        u.id AS authorId,
                        COALESCE(u.name, u.username) AS authorName,
                        u.username AS authorUsername,
                        u.avatar_url AS authorAvatarUrl,
                        COALESCE(reply_counts.replyCount, 0) AS replyCount,
                        NULL AS replyToAuthorName,
                        NULL AS replyToAuthorUsername
                    FROM video_comments vc
                    JOIN users u ON u.id = vc.user_id
                    LEFT JOIN reply_counts ON reply_counts.rootCommentId = vc.id
                    WHERE vc.video_id = ?
                      AND vc.parent_id IS NULL
                      AND (vc.deleted_at IS NULL OR COALESCE(reply_counts.replyCount, 0) > 0)
                    ORDER BY vc.created_at DESC, vc.id DESC
                    LIMIT ?
                SQL,
                [$videoId, $videoId, max(1, $limit)]
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function listVideoCommentReplies(int $videoId, int $rootCommentId, int $limit = 100): ?array
    {
        if (! $this->rootCommentExists($videoId, $rootCommentId)) {
            return null;
        }

        return array_map(
            fn (object $row): array => $this->mapCommentRow($row),
            $this->db->select(
                <<<'SQL'
                    SELECT
                        vc.id,
                        vc.parent_id AS parentId,
                        vc.reply_to_comment_id AS replyToCommentId,
                        vc.body,
                        vc.created_at AS createdAt,
                        vc.deleted_at AS deletedAt,
                        u.id AS authorId,
                        COALESCE(u.name, u.username) AS authorName,
                        u.username AS authorUsername,
                        u.avatar_url AS authorAvatarUrl,
                        0 AS replyCount,
                        COALESCE(reply_to_author.name, reply_to_author.username) AS replyToAuthorName,
                        reply_to_author.username AS replyToAuthorUsername
                    FROM video_comments vc
                    JOIN users u ON u.id = vc.user_id
                    LEFT JOIN video_comments reply_to_comment ON reply_to_comment.id = vc.reply_to_comment_id
                    LEFT JOIN users reply_to_author ON reply_to_author.id = reply_to_comment.user_id
                    WHERE vc.video_id = ?
                      AND vc.parent_id = ?
                    ORDER BY vc.created_at ASC, vc.id ASC
                    LIMIT ?
                SQL,
                [$videoId, $rootCommentId, max(1, $limit)]
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createVideoComment(int $videoId, int $userId, string $body, ?int $replyToCommentId = null): ?array
    {
        return $this->db->transaction(function () use ($videoId, $userId, $body, $replyToCommentId): ?array {
            $timestamp = now();
            $parentId = null;
            $resolvedReplyToCommentId = null;

            if ($replyToCommentId !== null) {
                $replyTarget = $this->db->selectOne(
                    <<<'SQL'
                        SELECT
                            vc.id,
                            vc.parent_id AS parentId
                        FROM video_comments vc
                        WHERE vc.id = ?
                          AND vc.video_id = ?
                          AND vc.deleted_at IS NULL
                        LIMIT 1
                    SQL,
                    [$replyToCommentId, $videoId]
                );

                if (! $replyTarget) {
                    return null;
                }

                $resolvedReplyToCommentId = (int) $replyTarget->id;
                $parentId = $replyTarget->parentId !== null ? (int) $replyTarget->parentId : (int) $replyTarget->id;
            }

            $commentId = (int) $this->db->table('video_comments')->insertGetId([
                'video_id' => $videoId,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'reply_to_comment_id' => $resolvedReplyToCommentId,
                'body' => $body,
                'edited_at' => null,
                'deleted_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $row = $this->selectCommentRow($commentId);

            if ($row) {
                return $this->mapCommentRow($row);
            }

            return [
                'id' => $commentId,
                'body' => $body,
                'createdAt' => $timestamp->toISOString(),
                'author' => [
                    'id' => $userId,
                    'name' => '',
                    'username' => '',
                    'avatarUrl' => null,
                ],
                'replyCount' => 0,
                'isDeleted' => false,
                'replyToCommentId' => $resolvedReplyToCommentId,
                'replyToAuthor' => null,
            ];
        });
    }

    public function deleteViewerComment(int $commentId, int $videoId, int $userId): int
    {
        $timestamp = now();

        return $this->db->table('video_comments')
            ->where('id', $commentId)
            ->where('video_id', $videoId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
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

    public function deleteViewerHistory(int $videoId, int $userId): int
    {
        return $this->db->table('video_views')
            ->where('video_id', $videoId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function clearViewerHistory(int $userId): int
    {
        return $this->db->table('video_views')
            ->where('user_id', $userId)
            ->delete();
    }

    private function rootCommentExists(int $videoId, int $rootCommentId): bool
    {
        return $this->db->table('video_comments')
            ->where('id', $rootCommentId)
            ->where('video_id', $videoId)
            ->whereNull('parent_id')
            ->exists();
    }

    private function selectCommentRow(int $commentId): ?object
    {
        return $this->db->selectOne(
            <<<'SQL'
                WITH reply_counts AS (
                    SELECT
                        vc.parent_id AS rootCommentId,
                        COUNT(*) AS replyCount
                    FROM video_comments vc
                    WHERE vc.parent_id IS NOT NULL
                    GROUP BY vc.parent_id
                )
                SELECT
                    vc.id,
                    vc.parent_id AS parentId,
                    vc.reply_to_comment_id AS replyToCommentId,
                    vc.body,
                    vc.created_at AS createdAt,
                    vc.deleted_at AS deletedAt,
                    u.id AS authorId,
                    COALESCE(u.name, u.username) AS authorName,
                    u.username AS authorUsername,
                    u.avatar_url AS authorAvatarUrl,
                    COALESCE(reply_counts.replyCount, 0) AS replyCount,
                    COALESCE(reply_to_author.name, reply_to_author.username) AS replyToAuthorName,
                    reply_to_author.username AS replyToAuthorUsername
                FROM video_comments vc
                JOIN users u ON u.id = vc.user_id
                LEFT JOIN reply_counts ON reply_counts.rootCommentId = vc.id
                LEFT JOIN video_comments reply_to_comment ON reply_to_comment.id = vc.reply_to_comment_id
                LEFT JOIN users reply_to_author ON reply_to_author.id = reply_to_comment.user_id
                WHERE vc.id = ?
                LIMIT 1
            SQL,
            [$commentId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCommentRow(object $row): array
    {
        $isReply = $row->parentId !== null;
        $isDeleted = $row->deletedAt !== null;
        $replyToCommentId = $row->replyToCommentId !== null ? (int) $row->replyToCommentId : null;
        $replyToAuthorName = trim((string) ($row->replyToAuthorName ?? ''));
        $replyToAuthorUsername = trim((string) ($row->replyToAuthorUsername ?? ''));

        return [
            'id' => (int) $row->id,
            'body' => $isDeleted ? ($isReply ? '该回复已删除' : '该评论已删除') : (string) $row->body,
            'createdAt' => $row->createdAt ? (string) $row->createdAt : null,
            'author' => [
                'id' => (int) $row->authorId,
                'name' => (string) $row->authorName,
                'username' => (string) $row->authorUsername,
                'avatarUrl' => $row->authorAvatarUrl ? (string) $row->authorAvatarUrl : null,
            ],
            'replyCount' => $isReply ? 0 : (int) ($row->replyCount ?? 0),
            'isDeleted' => $isDeleted,
            'replyToCommentId' => $replyToCommentId,
            'replyToAuthor' => $replyToCommentId === null || ($replyToAuthorName === '' && $replyToAuthorUsername === '')
                ? null
                : [
                    'name' => $replyToAuthorName,
                    'username' => $replyToAuthorUsername,
                ],
        ];
    }
}
