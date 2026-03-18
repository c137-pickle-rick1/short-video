<?php

namespace App\ShortVideo\Repositories;

use Illuminate\Database\ConnectionInterface;

final class SocialGraphRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  array<int, int|null>  $candidateUserIds
     * @return list<int>
     */
    public function getFollowedUserIds(int $viewerUserId, array $candidateUserIds): array
    {
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
        $this->db->table('user_follows')
            ->where('follower_user_id', $viewerUserId)
            ->where('followed_user_id', $followedUserId)
            ->delete();
    }

    public function countFollowingUsers(int $viewerUserId): int
    {
        return (int) $this->db->table('user_follows')
            ->where('follower_user_id', $viewerUserId)
            ->count();
    }

    public function countFollowerUsers(int $viewerUserId): int
    {
        return (int) $this->db->table('user_follows')
            ->where('followed_user_id', $viewerUserId)
            ->count();
    }
}
