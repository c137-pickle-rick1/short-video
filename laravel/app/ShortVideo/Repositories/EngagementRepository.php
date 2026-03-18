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
}
