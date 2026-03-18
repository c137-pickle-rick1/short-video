<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\User;
use App\Models\Video;
use App\ShortVideo\Services\RuntimeStateStore;
use Tests\TestCase;

final class FeedApiTest extends TestCase
{
    public function test_feed_endpoint_paginates_and_filters_by_source(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2000',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => 'API test tweet',
            ],
            'mediaAssets' => [
                [
                    'url' => 'https://example.com/video.mp4',
                    'bitrate' => 1000,
                    'contentType' => 'video/mp4',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 0,
                    'isPrimary' => true,
                ],
                [
                    'url' => 'https://example.com/video.m3u8',
                    'bitrate' => null,
                    'contentType' => 'application/x-mpegURL',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 1,
                    'isPrimary' => false,
                ],
            ],
        ]);

        $response = $this->getJson('/api/feed?source=demo&limit=5');
        $sourceModel = Source::query()->find($source['id']);

        $response->assertOk();
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.videoUrl', '/api/media/2000');
        $response->assertJsonPath('items.0.hlsUrl', 'https://example.com/video.m3u8');
        $response->assertJsonPath('items.0.authorAvatarUrl', 'https://example.com/avatar.jpg');
        $response->assertJsonPath('items.0.authorUserId', $sourceModel?->user_id);
        $response->assertJsonPath('items.0.authorAccountType', 'external_creator');
        $response->assertJsonPath('items.0.durationText', '0:21');
        $response->assertJsonPath('items.0.engagement.likeCount', 0);
        $response->assertJsonPath('items.0.engagement.bookmarkedByViewer', false);
    }

    public function test_featured_feed_prefers_stronger_engagement_over_recency(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2301',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => 'Older but stronger',
                'postedAt' => now()->subDays(2)->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2302',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => 'Newer but weaker',
                'postedAt' => now()->subHours(3)->toISOString(),
            ],
        ]);

        $olderVideo = Video::query()->where('tweet_id', '2301')->firstOrFail();
        $newerVideo = Video::query()->where('tweet_id', '2302')->firstOrFail();
        $viewer = User::factory()->count(3)->create();

        $viewer[0]->likedVideos()->attach($olderVideo->id);
        $viewer[1]->likedVideos()->attach($olderVideo->id);
        $viewer[2]->bookmarkedVideos()->attach($olderVideo->id);
        $viewer[0]->videoComments()->create([
            'video_id' => $olderVideo->id,
            'body' => '这条值得排前面',
        ]);
        $repository->recordVideoView($olderVideo->id, $viewer[0]->id, 'featured-seen-1');
        $repository->recordVideoView($olderVideo->id, $viewer[1]->id, 'featured-seen-2');
        $repository->recordVideoView($newerVideo->id, $viewer[2]->id, 'featured-seen-3');

        $response = $this->getJson('/api/feed?mode=featured&limit=5');

        $response->assertOk();
        $response->assertJsonPath('items.0.tweetId', '2301');
        $response->assertJsonPath('items.0.engagement.commentCount', 1);
        $response->assertJsonPath('items.1.tweetId', '2302');
    }

    public function test_featured_feed_falls_back_to_publish_time_when_interactions_are_empty(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2303',
            'tweet' => [
                'postedAt' => now()->subHours(5)->toISOString(),
                'text' => 'Older no engagement',
            ],
        ]);
        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2304',
            'tweet' => [
                'postedAt' => now()->subHour()->toISOString(),
                'text' => 'Newer no engagement',
            ],
        ]);

        $response = $this->getJson('/api/feed?mode=featured&limit=5');

        $response->assertOk();
        $response->assertJsonPath('items.0.tweetId', '2304');
        $response->assertJsonPath('items.1.tweetId', '2303');
    }

    public function test_following_feed_requires_active_viewer(): void
    {
        $this->useShortVideoDatabase();

        $this->getJson('/api/feed?mode=following')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Login required for following feed.');
    }

    public function test_following_feed_returns_only_followed_creators_in_desc_order(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'follow_tester',
            'email' => 'follow_tester@example.com',
        ]);
        [$alpha, $beta] = $repository->syncSources([
            ['handle' => 'alpha', 'enabled' => true],
            ['handle' => 'beta', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $alpha['id'], [
            'tweetId' => '2101',
            'tweet' => [
                'authorHandle' => 'alpha',
                'authorName' => 'Alpha',
                'text' => 'Alpha first',
                'postedAt' => now()->subHours(2)->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $beta['id'], [
            'tweetId' => '2102',
            'tweet' => [
                'authorHandle' => 'beta',
                'authorName' => 'Beta',
                'text' => 'Beta only',
                'postedAt' => now()->subHours(1)->toISOString(),
            ],
        ]);

        $alphaAuthorId = Source::query()->findOrFail($alpha['id'])->user_id;
        $this->assertNotNull($alphaAuthorId);
        $repository->followUser($viewer->id, (int) $alphaAuthorId);

        $response = $this->actingAs($viewer)->getJson('/api/feed?mode=following&limit=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.tweetId', '2101');
        $response->assertJsonPath('items.0.authorHandle', 'alpha');
        $response->assertJsonPath('items.0.authorFollowedByViewer', true);
    }

    public function test_explore_feed_search_matches_video_text_author_and_source_handle(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$demo, $maker, $sourceMatch] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
            ['handle' => 'makerroom', 'enabled' => true],
            ['handle' => 'source-match', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $demo['id'], [
            'tweetId' => '2401',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => 'Sunset alley walkthrough',
            ],
        ]);
        $this->insertResolvedTweet($repository, $maker['id'], [
            'tweetId' => '2402',
            'tweet' => [
                'authorHandle' => 'makerroom',
                'authorName' => 'Studio Bravo',
                'text' => 'Workbench session',
            ],
        ]);
        $this->insertResolvedTweet($repository, $sourceMatch['id'], [
            'tweetId' => '2403',
            'tweet' => [
                'authorHandle' => 'source-match',
                'authorName' => 'Gamma',
                'text' => 'Archive clip',
            ],
        ]);

        $this->getJson('/api/feed?mode=explore&q=sunset')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.tweetId', '2401');

        $this->getJson('/api/feed?mode=explore&q=studio%20bravo')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.tweetId', '2402');

        $this->getJson('/api/feed?mode=explore&q=source-match')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.tweetId', '2403');
    }

    public function test_explore_feed_search_supports_source_intersection_blank_query_and_cursor_pagination(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$alpha, $beta] = $repository->syncSources([
            ['handle' => 'alpha', 'enabled' => true],
            ['handle' => 'beta', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $alpha['id'], [
            'tweetId' => '2404',
            'tweet' => [
                'authorHandle' => 'alpha',
                'authorName' => 'Alpha',
                'text' => 'Search match newest',
                'postedAt' => now()->subHour()->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $alpha['id'], [
            'tweetId' => '2405',
            'tweet' => [
                'authorHandle' => 'alpha',
                'authorName' => 'Alpha',
                'text' => 'Search match older',
                'postedAt' => now()->subHours(2)->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $beta['id'], [
            'tweetId' => '2406',
            'tweet' => [
                'authorHandle' => 'beta',
                'authorName' => 'Beta',
                'text' => 'Search match other source',
                'postedAt' => now()->subMinutes(30)->toISOString(),
            ],
        ]);

        $paginatedResponse = $this->getJson('/api/feed?mode=explore&q=search%20match&source=alpha&limit=1');

        $paginatedResponse->assertOk();
        $paginatedResponse->assertJsonCount(1, 'items');
        $paginatedResponse->assertJsonPath('items.0.tweetId', '2404');

        $nextCursor = $paginatedResponse->json('nextCursor');
        $this->assertIsString($nextCursor);
        $this->assertNotSame('', $nextCursor);

        $this->getJson('/api/feed?mode=explore&q=search%20match&source=alpha&limit=1&cursor='.urlencode($nextCursor))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.tweetId', '2405')
            ->assertJsonPath('nextCursor', null);

        $this->getJson('/api/feed?mode=explore&q=%20%20%20')
            ->assertOk()
            ->assertJsonCount(3, 'items');
    }

    public function test_rankings_api_returns_creator_activity_order_and_follow_state(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'follow_tester',
            'email' => 'follow_tester@example.com',
        ]);
        [$alpha, $beta] = $repository->syncSources([
            ['handle' => 'alpha', 'enabled' => true],
            ['handle' => 'beta', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $alpha['id'], [
            'tweetId' => '2201',
            'tweet' => [
                'authorHandle' => 'alpha',
                'authorName' => 'Alpha',
                'text' => 'Alpha one',
                'postedAt' => now()->subDays(2)->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $alpha['id'], [
            'tweetId' => '2202',
            'tweet' => [
                'authorHandle' => 'alpha',
                'authorName' => 'Alpha',
                'text' => 'Alpha two',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $beta['id'], [
            'tweetId' => '2203',
            'tweet' => [
                'authorHandle' => 'beta',
                'authorName' => 'Beta',
                'text' => 'Beta one',
                'postedAt' => now()->subHours(10)->toISOString(),
            ],
        ]);

        $betaAuthorId = Source::query()->findOrFail($beta['id'])->user_id;
        $this->assertNotNull($betaAuthorId);
        $repository->followUser($viewer->id, (int) $betaAuthorId);

        $response = $this->actingAs($viewer)->getJson('/api/rankings/creators?window=7d&limit=5');

        $response->assertOk();
        $response->assertJsonPath('window', '7d');
        $response->assertJsonPath('items.0.rank', 1);
        $response->assertJsonPath('items.0.creator.username', 'alpha');
        $response->assertJsonPath('items.0.publishedCount7d', 2);
        $response->assertJsonPath('items.0.followedByViewer', false);
        $response->assertJsonPath('items.1.rank', 2);
        $response->assertJsonPath('items.1.creator.username', 'beta');
        $response->assertJsonPath('items.1.followedByViewer', true);
    }

    public function test_rankings_api_includes_inactive_creators_after_active_ones_when_needed(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$activeSource, $olderSource] = $repository->syncSources([
            ['handle' => 'active_creator', 'enabled' => true],
            ['handle' => 'older_creator', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $activeSource['id'], [
            'tweetId' => '2204',
            'tweet' => [
                'authorHandle' => 'active_creator',
                'authorName' => 'Active Creator',
                'text' => 'Recently active',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $olderSource['id'], [
            'tweetId' => '2205',
            'tweet' => [
                'authorHandle' => 'older_creator',
                'authorName' => 'Older Creator',
                'text' => 'Older content',
                'postedAt' => now()->subDays(20)->toISOString(),
            ],
        ]);

        $response = $this->getJson('/api/rankings/creators?window=7d&limit=5');

        $response->assertOk();
        $response->assertJsonCount(2, 'items');
        $response->assertJsonPath('items.0.creator.username', 'active_creator');
        $response->assertJsonPath('items.0.publishedCount7d', 1);
        $response->assertJsonPath('items.1.rank', 2);
        $response->assertJsonPath('items.1.creator.username', 'older_creator');
        $response->assertJsonPath('items.1.publishedCount7d', 0);
    }

    public function test_health_endpoint_reports_backoff_state(): void
    {
        $this->useShortVideoDatabase();
        $runtimeStateStore = $this->app->make(RuntimeStateStore::class);
        $runtimeStateStore->setBackoff('rate_limited', 15);

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('backoffReason', 'rate_limited');
        $this->assertNotNull($response->json('backoffUntil'));
    }

    public function test_follow_endpoint_updates_author_follow_state_in_feed(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'follow_tester',
            'email' => 'follow_tester@example.com',
        ]);

        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2010',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '可关注作者视频',
            ],
        ]);

        $sourceModel = Source::query()->findOrFail($source['id']);
        $authorUserId = $sourceModel->user_id;

        $this->assertNotNull($authorUserId);

        $this->actingAs($viewer)->getJson('/api/feed?source=demo&limit=5')
            ->assertOk()
            ->assertJsonPath('items.0.authorUserId', $authorUserId)
            ->assertJsonPath('items.0.viewerUserId', $viewer->id)
            ->assertJsonPath('items.0.canFollowAuthor', true)
            ->assertJsonPath('items.0.authorFollowedByViewer', false);

        $this->actingAs($viewer)->postJson("/api/users/{$authorUserId}/follow")
            ->assertOk()
            ->assertJsonPath('viewerUserId', $viewer->id)
            ->assertJsonPath('authorUserId', $authorUserId)
            ->assertJsonPath('following', true);

        $this->assertDatabaseHas('user_follows', [
            'follower_user_id' => $viewer->id,
            'followed_user_id' => $authorUserId,
        ]);

        $this->actingAs($viewer)->getJson('/api/feed?source=demo&limit=5')
            ->assertOk()
            ->assertJsonPath('items.0.authorFollowedByViewer', true);

        $this->actingAs($viewer)->deleteJson("/api/users/{$authorUserId}/follow")
            ->assertOk()
            ->assertJsonPath('following', false);

        $this->assertDatabaseMissing('user_follows', [
            'follower_user_id' => $viewer->id,
            'followed_user_id' => $authorUserId,
        ]);
    }
}
