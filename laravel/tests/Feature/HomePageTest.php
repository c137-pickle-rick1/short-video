<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
            'mediaAssets' => [
                [
                    'url' => 'https://example.com/home-video.mp4',
                    'bitrate' => 1000,
                    'contentType' => 'video/mp4',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 0,
                    'isPrimary' => true,
                ],
                [
                    'url' => 'https://example.com/home-video.m3u8',
                    'bitrate' => null,
                    'contentType' => 'application/x-mpegURL',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 1,
                    'isPrimary' => false,
                ],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('精选', false);
        $response->assertSee('首页轻推荐样本', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('"mode":"featured"', false);
        $response->assertSee('/vendor/plyr/plyr.css', false);
        $response->assertSee('/vendor/plyr/plyr.min.js', false);
        $response->assertSee('class="js-feed-player h-full w-full object-cover"', false);
        $response->assertSee('data-hls-url="https://example.com/home-video.m3u8"', false);
        $response->assertSee('data-fallback-url="/api/media/3001"', false);
        $response->assertSee('id="feed-grid"', false);
        $response->assertSee('id="feed-detail-modal"', false);
        $response->assertSee('id="feed-detail-modal-panel"', false);
        $response->assertSee('h-[100dvh]', false);
        $response->assertSee('lg:h-[92vh]', false);
        $response->assertSee('id="feed-loading-indicator"', false);
        $response->assertSee('正在加载更多', false);
        $response->assertDontSee('轻推荐首页', false);
        $response->assertDontSee('id="source-filter"', false);
        $response->assertDontSee('这里按真实互动和发布时间做混排，强互动内容会优先浮出，新内容也会持续进入候选池。', false);
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
        $response->assertSee('line-clamp-2 overflow-hidden text-base font-semibold leading-6 text-gray-900', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('"source":"demo"', false);
        $response->assertSee('"mode":"explore"', false);
        $response->assertSee('id="feed-detail-modal"', false);
        $response->assertSee('h-[100dvh]', false);
        $response->assertSee('id="feed-loading-indicator"', false);
        $response->assertSee('/vendor/plyr/plyr.min.js', false);
        $response->assertDontSee('id="source-filter"', false);
        $response->assertDontSee('id="feed-summary"', false);
        $response->assertDontSee('id="feed-status"', false);
        $response->assertDontSee('最新公开内容会按发布时间持续流入这里，你可以按来源切换视角，继续向下滚动扩展样本。', false);
    }

    public function test_explore_page_supports_fixed_16_9_media_frame_mode(): void
    {
        $repository = $this->useShortVideoDatabase();
        Config::set('shortvideo.feed_media_frame_mode', '16:9');
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3002',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '探索页 16:9 卡片',
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
            ],
        ]);

        $response = $this->get('/explore?source=demo');

        $response->assertOk();
        $response->assertSee('探索页 16:9 卡片', false);
        $response->assertSee('aspect-video', false);
        $response->assertDontSee('aspect-[3/4]', false);
        $response->assertDontSee('aspect-[4/5]', false);
    }

    public function test_explore_page_filters_results_and_prefills_search_query(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3101',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => 'Lagos rooftop walkthrough',
            ],
        ]);
        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3102',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => 'Completely unrelated clip',
            ],
        ]);

        $response = $this->get('/explore?q=lagos');

        $response->assertOk();
        $response->assertSee('action="'.route('explore').'"', false);
        $response->assertSee('name="q"', false);
        $response->assertSee('value="lagos"', false);
        $response->assertSee('搜索 “lagos” · 探索 · Lagos Explore Feed', false);
        $response->assertSee('Lagos rooftop walkthrough', false);
        $response->assertDontSee('Completely unrelated clip', false);
        $response->assertSee('"query":"lagos"', false);
    }

    public function test_explore_page_uses_search_specific_empty_state_when_no_result_matches(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3103',
            'tweet' => [
                'text' => 'Only unrelated content',
            ],
        ]);

        $response = $this->get('/explore?q=missing');

        $response->assertOk();
        $response->assertSee('没有找到相关内容', false);
        $response->assertSee('没有找到与 “missing” 相关的内容。试试更短的关键词，或切换来源后重试。', false);
        $response->assertSee('ph ph-magnifying-glass', false);
        $response->assertSee('清除搜索', false);
        $response->assertSee('href="'.route('explore').'"', false);
        $response->assertSee('"query":"missing"', false);
    }

    public function test_featured_page_empty_state_renders_icon_and_button(): void
    {
        $this->useShortVideoDatabase();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('还没有可展示的精选内容', false);
        $response->assertSee('等第一批公开视频与互动信号进入后，这里会按精选排序持续展示。', false);
        $response->assertSee('ph ph-shooting-star', false);
        $response->assertSee('去探索', false);
        $response->assertSee('href="'.route('explore').'"', false);
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
        $response->assertSee('去登录', false);
        $response->assertDontSee('&#64;demo', false);
        $response->assertDontSee('data-author-follow-button="true"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-grid"', false);
    }

    public function test_subscriptions_page_renders_empty_following_state_when_logged_in_without_follows(): void
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
        $response->assertSee('你还没有任何订阅关系。下面先按近 7 天活跃度推荐一批创作者，关注后页面会立即刷新为订阅流。', false);
        $response->assertDontSee('无关注时的推荐样本', false);
        $response->assertDontSee('data-author-follow-button="true"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-grid"', false);
    }

    public function test_subscriptions_root_page_renders_followed_account_list_without_default_selection(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'quiet_follow_viewer',
            'email' => 'quiet_follow_viewer@example.com',
        ]);
        $followedCreator = User::factory()->create([
            'username' => 'silent_creator',
            'email' => 'silent_creator@example.com',
        ]);

        $repository->followUser($viewer->id, $followedCreator->id);

        $response = $this->actingAs($viewer)->get('/subscriptions');

        $response->assertOk();
        $response->assertSee('data-subscriptions-follow-list="true"', false);
        $response->assertDontSee('data-subscriptions-page-nav="true"', false);
        $response->assertSee('data-subscriptions-selected-account=""', false);
        $response->assertSee('data-subscriptions-account-item="true"', false);
        $response->assertSee('data-subscriptions-account-username="silent_creator"', false);
        $response->assertSee(route('subscriptions.show', ['account' => 'silent_creator']), false);
        $response->assertSee('data-active="false"', false);
        $response->assertSee('lg:h-[calc(100dvh-100px)] xl:h-[calc(100dvh-104px)] 2xl:h-[calc(100dvh-108px)]', false);
        $response->assertSee('grid min-h-0 gap-0 lg:gap-6 xl:gap-7 h-full', false);
        $response->assertDontSee('row-start-2', false);
        $response->assertSee('lg:sticky lg:top-[100px] lg:self-start xl:top-[104px] 2xl:top-[108px]', false);
        $response->assertSee('lg:flex lg:h-[calc(100dvh-100px)] lg:flex-col', false);
        $response->assertSee('xl:h-[calc(100dvh-104px)] 2xl:h-[calc(100dvh-108px)]', false);
        $response->assertSee('lg:h-full lg:overscroll-contain lg:pb-0', false);
        $response->assertDontSee('lg:max-h-[calc(100vh-11rem)]', false);
        $response->assertDontSee('2xl:max-h-[calc(100vh-12rem)]', false);
        $response->assertDontSee('&#64;silent_creator', false);
        $response->assertSee('暂无更新', false);
        $response->assertSee('选择一个已关注账号', false);
        $response->assertSee('从左侧列表进入某个订阅者，右侧只会展示这个账号的公开视频。', false);
        $response->assertDontSee('data-author-follow-button="true"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('这个账号还没有公开发布的视频', false);
    }

    public function test_subscriptions_account_page_renders_empty_state_when_selected_creator_has_no_published_videos(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'quiet_follow_selected_viewer',
            'email' => 'quiet_follow_selected_viewer@example.com',
        ]);
        $followedCreator = User::factory()->create([
            'username' => 'silent_creator_selected',
            'email' => 'silent_creator_selected@example.com',
        ]);

        $repository->followUser($viewer->id, $followedCreator->id);

        $response = $this->actingAs($viewer)->get(route('subscriptions.show', ['account' => 'silent_creator_selected']));

        $response->assertOk();
        $response->assertSee('data-subscriptions-follow-list="true"', false);
        $response->assertSee('data-subscriptions-selected-account="silent_creator_selected"', false);
        $response->assertSee('lg:sticky lg:top-[100px] lg:self-start xl:top-[104px] 2xl:top-[108px]', false);
        $response->assertSee('lg:flex lg:h-[calc(100dvh-100px)] lg:flex-col', false);
        $response->assertSee('xl:h-[calc(100dvh-104px)] 2xl:h-[calc(100dvh-108px)]', false);
        $response->assertSee('lg:h-full lg:overscroll-contain lg:pb-0', false);
        $response->assertDontSee('lg:max-h-[calc(100vh-11rem)]', false);
        $response->assertDontSee('2xl:max-h-[calc(100vh-12rem)]', false);
        $response->assertSee('这个账号还没有公开发布的视频', false);
        $response->assertSee('切换到其他已关注账号，或者先去探索页继续补充订阅内容。', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('id="feed-grid"', false);
        $response->assertSee('data-feed-grid-max-columns="3"', false);
    }

    public function test_subscriptions_account_page_switches_between_followed_accounts_and_renders_selected_accounts_videos(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'subscriptions_viewer',
            'email' => 'subscriptions_viewer@example.com',
        ]);
        $creatorAlpha = User::factory()->create([
            'name' => 'Creator Alpha',
            'username' => 'creator_alpha',
            'avatar_url' => 'https://example.com/creator-alpha.jpg',
        ]);
        $creatorBeta = User::factory()->create([
            'name' => 'Creator Beta',
            'username' => 'creator_beta',
            'avatar_url' => 'https://example.com/creator-beta.jpg',
        ]);

        $repository->followUser($viewer->id, $creatorAlpha->id);
        $repository->followUser($viewer->id, $creatorBeta->id);

        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creatorAlpha->id,
            'title' => 'Alpha clip one',
            'poster_url' => 'https://example.com/alpha-one.jpg',
            'playback_url' => 'https://example.com/alpha-one.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);
        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creatorBeta->id,
            'title' => 'Beta clip one',
            'poster_url' => 'https://example.com/beta-one.jpg',
            'playback_url' => 'https://example.com/beta-one.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creatorBeta->id,
            'title' => 'Beta clip two',
            'poster_url' => 'https://example.com/beta-two.jpg',
            'playback_url' => 'https://example.com/beta-two.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subHours(8),
        ]);

        $response = $this->actingAs($viewer)->get(route('subscriptions.show', ['account' => 'creator_beta']));

        $response->assertOk();
        $response->assertSee('data-subscriptions-follow-list="true"', false);
        $response->assertSee('data-subscriptions-selected-account="creator_beta"', false);
        $response->assertSee('lg:sticky lg:top-[100px] lg:self-start xl:top-[104px] 2xl:top-[108px]', false);
        $response->assertSee('lg:flex lg:h-[calc(100dvh-100px)] lg:flex-col', false);
        $response->assertSee('xl:h-[calc(100dvh-104px)] 2xl:h-[calc(100dvh-108px)]', false);
        $response->assertSee('lg:h-full lg:overscroll-contain lg:pb-0', false);
        $response->assertDontSee('lg:max-h-[calc(100vh-11rem)]', false);
        $response->assertDontSee('2xl:max-h-[calc(100vh-12rem)]', false);
        $response->assertSee(route('subscriptions.show', ['account' => 'creator_alpha']), false);
        $response->assertSee(route('subscriptions.show', ['account' => 'creator_beta']), false);
        $response->assertSee('Creator Alpha', false);
        $response->assertSee('Creator Beta', false);
        $response->assertSee('Beta clip one', false);
        $response->assertSee('Beta clip two', false);
        $response->assertDontSee('Alpha clip one', false);
        $response->assertSee('data-subscriptions-page-nav="true"', false);
        $response->assertSee('id="feed-grid"', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('data-feed-grid-max-columns="3"', false);
    }

    public function test_subscriptions_page_renders_unread_counts_and_new_badges_based_on_view_history(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'subscriptions_badge_viewer',
            'email' => 'subscriptions_badge_viewer@example.com',
        ]);
        $creatorAlpha = User::factory()->create([
            'name' => 'Creator Alpha',
            'username' => 'creator_alpha_badges',
            'avatar_url' => 'https://example.com/creator-alpha-badges.jpg',
        ]);
        $creatorBeta = User::factory()->create([
            'name' => 'Creator Beta',
            'username' => 'creator_beta_badges',
            'avatar_url' => 'https://example.com/creator-beta-badges.jpg',
        ]);

        $repository->followUser($viewer->id, $creatorAlpha->id);
        $repository->followUser($viewer->id, $creatorBeta->id);

        $alphaViewedVideo = Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creatorAlpha->id,
            'title' => 'Alpha viewed clip',
            'poster_url' => 'https://example.com/alpha-viewed.jpg',
            'playback_url' => 'https://example.com/alpha-viewed.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subDays(3),
        ]);
        $betaViewedVideo = Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creatorBeta->id,
            'title' => 'Beta viewed clip',
            'poster_url' => 'https://example.com/beta-viewed.jpg',
            'playback_url' => 'https://example.com/beta-viewed.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);
        Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creatorBeta->id,
            'title' => 'Beta unread clip',
            'poster_url' => 'https://example.com/beta-unread.jpg',
            'playback_url' => 'https://example.com/beta-unread.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subHours(6),
        ]);

        DB::table('video_views')->insert([
            [
                'video_id' => $alphaViewedVideo->id,
                'user_id' => $viewer->id,
                'session_id' => 'subscriptions_badges_alpha',
                'view_date' => now()->subDays(2)->toDateString(),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'video_id' => $betaViewedVideo->id,
                'user_id' => $viewer->id,
                'session_id' => 'subscriptions_badges_beta',
                'view_date' => now()->subDay()->toDateString(),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ]);

        $response = $this->actingAs($viewer)->get(route('subscriptions.show', ['account' => 'creator_beta_badges']));

        $response->assertOk();
        $response->assertSee('Creator Alpha', false);
        $response->assertSee('Creator Beta', false);
        $response->assertSee('更新', false);
        $response->assertDontSee('最近更新', false);
        $response->assertSee('data-subscriptions-unread-badge="true"', false);
        $response->assertSee('data-unread-count="1"', false);
        $response->assertDontSee('已全部读完', false);
        $response->assertSee('Beta viewed clip', false);
        $response->assertSee('Beta unread clip', false);
        $response->assertSee('data-subscriptions-feed-card="true"', false);
        $response->assertSee('data-feed-new-badge="true"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-feed-new-badge="true"'));
    }

    public function test_subscriptions_page_prefers_latest_published_author_name_for_followed_account_label(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'subscriptions_name_viewer',
            'email' => 'subscriptions_name_viewer@example.com',
        ]);
        $creator = User::factory()->create([
            'name' => '@tangyuan_269',
            'username' => 'tangyuan_269',
            'avatar_url' => 'https://example.com/tangyuan.jpg',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'tangyuan_269', 'enabled' => true],
        ]);

        $repository->followUser($viewer->id, $creator->id);

        DB::table('tweets')->insert([
            'tweet_id' => 'subscriptions-name-1',
            'source_id' => $source['id'],
            'tweet_url' => 'https://x.com/tangyuan_269/status/subscriptions-name-1',
            'author_handle' => 'tangyuan_269',
            'author_name' => '芝麻湯圓🍡',
            'author_avatar_url' => 'https://example.com/tangyuan.jpg',
            'text' => '昵称应优先展示 author_name',
            'posted_at' => now()->subDay()->toISOString(),
            'duration_text' => '0:21',
            'poster_url' => 'https://example.com/tangyuan-poster.jpg',
            'status' => 'resolved',
            'raw_discovery_payload' => '{}',
            'raw_resolve_payload' => '{}',
            'ingested_at' => now()->subDay()->toISOString(),
            'resolved_at' => now()->subDay()->toISOString(),
        ]);

        Video::query()->create([
            'origin' => 'x_tweet',
            'tweet_id' => 'subscriptions-name-1',
            'source_id' => $source['id'],
            'uploader_user_id' => $creator->id,
            'title' => 'Tangyuan clip',
            'poster_url' => 'https://example.com/tangyuan-poster.jpg',
            'playback_url' => 'https://example.com/tangyuan.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($viewer)->get(route('subscriptions.show', ['account' => 'tangyuan_269']));

        $response->assertOk();
        $response->assertSee('芝麻湯圓🍡', false);
        $response->assertDontSee('&#64;tangyuan_269', false);
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
        $response->assertSee('href="/'.$user->username.'"', false);
        $response->assertSee('https://example.com/viewer.jpg', false);
        $response->assertDontSee('aria-label="退出登录"', false);
    }

    public function test_home_page_feed_card_author_identity_links_to_canonical_profile_route(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3201',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '作者链接卡片',
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="/demo"', false);
    }
}
