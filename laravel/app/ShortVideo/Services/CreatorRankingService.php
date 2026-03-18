<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\ShortVideo\Auth\CurrentViewerResolver;
use App\ShortVideo\Repositories\FeedRepository;
use App\ShortVideo\Repositories\SocialGraphRepository;
use App\ShortVideo\Support\FeedConfig;

final class CreatorRankingService
{
    public function __construct(
        private readonly FeedRepository $feeds,
        private readonly SocialGraphRepository $socialGraph,
        private readonly CurrentViewerResolver $currentViewerResolver
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getCreatorRankingsPageViewModel(int|string|null $limit = null, ?string $window = '7d'): array
    {
        $normalizedWindow = $this->normalizeWindow($window);
        $viewer = $this->currentViewerResolver->resolve();
        $rankingLimit = $this->normalizeRankingLimit($limit, FeedConfig::RANKINGS_LIMIT);

        return [
            'pageTitle' => '榜单 · Lagos Explore Feed',
            'headerViewer' => $this->mapViewerSummary($viewer),
            'page' => [
                'eyebrow' => 'Rankings',
                'title' => '榜单',
                'description' => 'v1 先只看创作者更新活跃度。排序口径固定为近 7 天更新数、最近更新时间、总视频数，避免伪热度噪音。',
            ],
            'window' => $normalizedWindow,
            'items' => $this->getCreatorRankingItems($rankingLimit, $viewer?->id, true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreatorRankingsApiPayload(int|string|null $limit = null, ?string $window = '7d'): array
    {
        $normalizedWindow = $this->normalizeWindow($window);
        $viewer = $this->currentViewerResolver->resolve();
        $items = $this->getCreatorRankingItems(
            $this->normalizeRankingLimit($limit, FeedConfig::RANKINGS_LIMIT),
            $viewer?->id,
            true
        );

        return [
            'window' => $normalizedWindow,
            'items' => array_map(
                static fn (array $item): array => [
                    'rank' => $item['rank'],
                    'creator' => $item['creator'],
                    'publishedCount7d' => $item['publishedCount7d'],
                    'totalVideos' => $item['totalVideos'],
                    'lastPublishedAt' => $item['lastPublishedAt'],
                    'followedByViewer' => $item['followedByViewer'],
                ],
                $items
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecommendedCreators(?int $viewerUserId, int $limit): array
    {
        $candidateLimit = max($limit * 5, $limit);
        $candidates = $this->getCreatorRankingItems($candidateLimit, $viewerUserId);

        $filtered = array_values(array_filter(
            $candidates,
            static function (array $item) use ($viewerUserId): bool {
                $creatorUserId = $item['creator']['userId'] ?? null;
                if (! is_int($creatorUserId)) {
                    return false;
                }

                if ($viewerUserId !== null && $creatorUserId === $viewerUserId) {
                    return false;
                }

                return $item['followedByViewer'] !== true;
            }
        ));

        return array_slice($filtered, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCreatorRankingItems(
        int $limit,
        ?int $viewerUserId,
        bool $includeInactiveFallback = false
    ): array {
        $items = $this->feeds->getCreatorRankings(7, $limit, $includeInactiveFallback);
        $followedUserIds = $viewerUserId !== null
            ? $this->socialGraph->getFollowedUserIds(
                $viewerUserId,
                array_map(
                    static fn (array $item): ?int => is_numeric((string) ($item['userId'] ?? null)) ? (int) $item['userId'] : null,
                    $items
                )
            )
            : [];

        return array_map(
            fn (array $item, int $index): array => $this->mapCreatorItemForPresentation(
                $item,
                $index + 1,
                $viewerUserId,
                $followedUserIds
            ),
            $items,
            array_keys($items)
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<int>  $followedUserIds
     * @return array<string, mixed>
     */
    private function mapCreatorItemForPresentation(
        array $item,
        int $rank,
        ?int $viewerUserId,
        array $followedUserIds
    ): array {
        $creatorUserId = is_numeric((string) ($item['userId'] ?? null)) ? (int) $item['userId'] : null;
        $canFollowCreator = $viewerUserId !== null && $creatorUserId !== null && $viewerUserId !== $creatorUserId;

        return [
            'rank' => $rank,
            'creator' => [
                'userId' => $creatorUserId,
                'username' => (string) ($item['username'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'avatarUrl' => ! empty($item['avatarUrl']) ? (string) $item['avatarUrl'] : null,
            ],
            'publishedCount7d' => (int) ($item['publishedCount7d'] ?? 0),
            'totalVideos' => (int) ($item['totalVideos'] ?? 0),
            'lastPublishedAt' => $item['lastPublishedAt'] ?? null,
            'viewerUserId' => $viewerUserId,
            'canFollowCreator' => $canFollowCreator,
            'followedByViewer' => $canFollowCreator && in_array($creatorUserId, $followedUserIds, true),
        ];
    }

    /**
     * @return array{id:int,name:string,username:string,avatarUrl:?string}|null
     */
    private function mapViewerSummary(?User $viewer): ?array
    {
        if (! $viewer) {
            return null;
        }

        return [
            'id' => $viewer->id,
            'name' => trim((string) ($viewer->name ?? '')) !== '' ? trim((string) $viewer->name) : $viewer->username,
            'username' => $viewer->username,
            'avatarUrl' => ! empty($viewer->avatar_url) ? (string) $viewer->avatar_url : null,
        ];
    }

    private function normalizeRankingLimit(int|string|null $limit, int $fallback): int
    {
        $numericLimit = is_numeric((string) $limit) ? (int) $limit : $fallback;

        return max(1, min(FeedConfig::RANKINGS_LIMIT, $numericLimit));
    }

    private function normalizeWindow(?string $window): string
    {
        return $window === '7d' ? '7d' : '7d';
    }
}
