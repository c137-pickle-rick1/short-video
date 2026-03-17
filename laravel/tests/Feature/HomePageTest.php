<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_home_page_renders_featured_feed_with_bootstrap_payload(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3001',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '首页轻推荐样本',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('精选', false);
        $response->assertSee('首页轻推荐样本', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('"mode":"featured"', false);
        $response->assertSee('/vendor/plyr/plyr.min.js', false);
        $response->assertSee('id="feed-grid"', false);
        $response->assertSee('id="feed-loading-indicator"', false);
        $response->assertSee('正在加载更多', false);
        $response->assertDontSee('轻推荐首页', false);
        $response->assertDontSee('id="source-filter"', false);
    }

    public function test_explore_page_renders_ssr_feed_toolbar_and_bootstrap_payload(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3002',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '探索页首屏卡片',
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

        $response = $this->get('/explore?source=demo');

        $response->assertOk();
        $response->assertSee('探索', false);
        $response->assertSee('探索页首屏卡片', false);
        $response->assertSee('/api/media/3002', false);
        $response->assertSee('data-hls-url="https://example.com/video.m3u8"', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('"source":"demo"', false);
        $response->assertSee('"mode":"explore"', false);
        $response->assertSee('id="source-filter"', false);
        $response->assertSee('id="feed-summary"', false);
        $response->assertSee('id="feed-status"', false);
        $response->assertSee('id="feed-loading-indicator"', false);
        $response->assertSee('/vendor/plyr/plyr.min.js', false);
    }

    public function test_subscriptions_page_prompts_login_and_hides_feed_when_viewer_missing(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3003',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '推荐创作者样本',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);

        $response = $this->get('/subscriptions');

        $response->assertOk();
        $response->assertSee('登录后查看订阅更新', false);
        $response->assertSee('登录后可关注', false);
        $response->assertSee('&#64;demo', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-grid"', false);
    }

    public function test_subscriptions_page_shows_recommendations_when_logged_in_without_follows(): void
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
            'tweetId' => '3004',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '无关注时的推荐样本',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);

        $response = $this->actingAs($viewer)->get('/subscriptions');

        $response->assertOk();
        $response->assertSee('先关注几个创作者', false);
        $response->assertSee('data-author-follow-button="true"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-grid"', false);
    }

    public function test_authenticated_home_page_hides_viewer_name_and_username(): void
    {
        $this->useShortVideoDatabase();
        $user = User::factory()->create([
            'name' => 'Follow Tester',
            'username' => 'follow_tester',
            'avatar_url' => 'https://example.com/viewer.jpg',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('Follow Tester', false);
        $response->assertDontSee('@follow_tester', false);
        $response->assertSee('>我的<', false);
        $response->assertSee(route('profile'), false);
        $response->assertSee('https://example.com/viewer.jpg', false);
        $response->assertDontSee('aria-label="退出登录"', false);
    }
}
