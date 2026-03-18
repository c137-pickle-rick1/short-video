<?php

namespace App\ShortVideo\Support;

final class FeedConfig
{
    public const MODE_FEATURED = 'featured';

    public const MODE_EXPLORE = 'explore';

    public const MODE_FOLLOWING = 'following';

    public const DEFAULT_FEED_LIMIT = 12;

    public const MAX_FEED_LIMIT = 24;

    public const FEATURED_CANDIDATE_LIMIT = 1000;

    public const HOME_PAGE_FEED_LIMIT = 8;

    public const RANKINGS_LIMIT = 50;

    public const RECOMMENDED_CREATORS_LIMIT = 6;

    private function __construct() {}
}
