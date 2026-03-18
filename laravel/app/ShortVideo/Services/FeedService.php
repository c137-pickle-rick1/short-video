<?php

namespace App\ShortVideo\Services;

use App\Models\User;
use App\ShortVideo\Support\FeedConfig;

final class FeedService
{
    public const MODE_FEATURED = FeedConfig::MODE_FEATURED;

    public const MODE_EXPLORE = FeedConfig::MODE_EXPLORE;

    public const MODE_FOLLOWING = FeedConfig::MODE_FOLLOWING;

    public const DEFAULT_FEED_LIMIT = FeedConfig::DEFAULT_FEED_LIMIT;

    public const MAX_FEED_LIMIT = FeedConfig::MAX_FEED_LIMIT;

    public const FEATURED_CANDIDATE_LIMIT = FeedConfig::FEATURED_CANDIDATE_LIMIT;

    public const HOME_PAGE_FEED_LIMIT = FeedConfig::HOME_PAGE_FEED_LIMIT;

    public const RANKINGS_LIMIT = FeedConfig::RANKINGS_LIMIT;

    public const RECOMMENDED_CREATORS_LIMIT = FeedConfig::RECOMMENDED_CREATORS_LIMIT;

    public function __construct(
        private readonly FeedQueryService $feedQueries,
        private readonly FeedPageService $feedPages,
        private readonly CreatorRankingService $creatorRankings
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFeedPage(
        ?string $cursor = null,
        ?string $sourceHandle = '',
        int|string|null $limit = null,
        string $mode = self::MODE_EXPLORE
    ): array {
        return $this->feedQueries->getFeedPage($cursor, $sourceHandle, $limit, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFeaturedPageViewModel(int|string|null $limit = null): array
    {
        return $this->feedPages->getFeaturedPageViewModel($limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getExplorePageViewModel(?string $sourceHandle = '', int|string|null $limit = null): array
    {
        return $this->feedPages->getExplorePageViewModel($sourceHandle, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscriptionsPageViewModel(int|string|null $limit = null): array
    {
        return $this->feedPages->getSubscriptionsPageViewModel($limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreatorRankingsPageViewModel(int|string|null $limit = null, ?string $window = '7d'): array
    {
        return $this->creatorRankings->getCreatorRankingsPageViewModel($limit, $window);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreatorRankingsApiPayload(int|string|null $limit = null, ?string $window = '7d'): array
    {
        return $this->creatorRankings->getCreatorRankingsApiPayload($limit, $window);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfilePageViewModel(User $viewer): array
    {
        return $this->feedPages->getProfilePageViewModel($viewer);
    }

    public function formatFeedSummary(string $mode, string $sourceHandle, int $renderedCount, bool $done): string
    {
        return $this->feedQueries->formatFeedSummary($mode, $sourceHandle, $renderedCount, $done);
    }
}
