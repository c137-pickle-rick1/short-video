<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ViewerCollectionsPageTest extends TestCase
{
    public function test_guest_is_redirected_to_login_when_opening_viewer_collection_pages(): void
    {
        $this->useShortVideoDatabase();

        $this->get('/me/history')->assertRedirect('/login');
        $this->get('/me/bookmarks')->assertRedirect('/login');
        $this->get('/me/interactions')->assertRedirect('/login');
    }

    public function test_authenticated_viewer_does_not_see_collection_entries_in_shell_navigation(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'name' => 'Library Tester',
            'username' => 'library_tester',
        ]);

        $response = $this->actingAs($viewer)->get('/');

        $response->assertOk();
        $response->assertDontSee('/me/history', false);
        $response->assertDontSee('/me/bookmarks', false);
        $response->assertDontSee('/me/interactions', false);
        $response->assertDontSee('mx-6 my-2 h-px bg-gray-200', false);

        $content = $response->getContent();
        self::assertIsString($content);
        preg_match('/<nav\s+aria-label="移动主导航".*?<\/nav>/s', $content, $matches);

        self::assertIsString($matches[0] ?? '');
        self::assertStringNotContainsString('观看记录', $matches[0] ?? '');
        self::assertStringNotContainsString('我的收藏', $matches[0] ?? '');
        self::assertStringNotContainsString('我的互动', $matches[0] ?? '');
    }

    public function test_viewer_history_page_renders_empty_state_when_no_records(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'history_viewer',
            'email' => 'history_viewer@example.com',
        ]);

        $response = $this->actingAs($viewer)->get('/me/history');

        $response->assertOk();
        $response->assertSee('观看记录', false);
        $response->assertSee('还没有观看记录', false);
        $response->assertSee('去探索', false);
        $response->assertSee('data-history-record-grid="true"', false);
        $response->assertDontSee('data-history-record-delete="true"', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-detail-modal"', false);
    }

    public function test_viewer_bookmarks_page_renders_empty_state_when_no_records(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'history_viewer',
            'email' => 'history_viewer@example.com',
        ]);

        $response = $this->actingAs($viewer)->get('/me/bookmarks');

        $response->assertOk();
        $response->assertSee('我的收藏', false);
        $response->assertSee('还没有收藏内容', false);
        $response->assertSee('ph ph-bookmark-simple', false);
        $response->assertSee('data-bookmark-back="true"', false);
        $response->assertSee('data-bookmark-record-grid="true"', false);
        $response->assertSee('去探索', false);
        $response->assertDontSee('data-bookmark-record-remove="true"', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-detail-modal"', false);
    }

    public function test_interactions_page_renders_empty_state(): void
    {
        $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'history_viewer',
            'email' => 'history_viewer@example.com',
        ]);

        $response = $this->actingAs($viewer)->get('/me/interactions');

        $response->assertOk();
        $response->assertSee('我的互动', false);
        $response->assertSee('还没有互动内容', false);
        $response->assertSee('ph ph-chat-circle-dots', false);
        $response->assertSee('data-interaction-back="true"', false);
        $response->assertSee('data-interaction-list="true"', false);
        $response->assertSee('去探索', false);
        $response->assertDontSee('data-interaction-item="true"', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-detail-modal"', false);
    }

    public function test_viewer_history_page_renders_real_records_in_grid_and_exposes_delete_action(): void
    {
        $repository = $this->useShortVideoDatabase();
        $postedAt = now()->subHour()->subMinute();
        $viewer = User::factory()->create([
            'username' => 'collector_viewer',
            'email' => 'collector_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '4501',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo Creator',
                'text' => '集合页测试视频',
                'postedAt' => $postedAt->toISOString(),
            ],
        ]);

        $video = Video::query()->firstOrFail();

        DB::table('video_views')->insert([
            'video_id' => $video->id,
            'user_id' => $viewer->id,
            'session_id' => 'viewer-history-session',
            'view_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get('/me/history');

        $response->assertOk();
        $response->assertSee('集合页测试视频', false);
        $response->assertSee('Demo Creator', false);
        $response->assertSee('data-history-back="true"', false);
        $response->assertSee('ph ph-arrow-left', false);
        $response->assertSee('data-history-clear-all="true"', false);
        $response->assertSee('清空记录', false);
        $response->assertSee('data-history-record-grid="true"', false);
        $response->assertSee('data-history-record-item="true"', false);
        $response->assertSee('/api/videos/'.$video->id.'/history', false);
        $response->assertSee('aspect-video', false);
        $response->assertSee('line-clamp-1 overflow-hidden text-base font-semibold leading-6 text-gray-900', false);
        $response->assertSee('1小时前', false);
        $response->assertDontSee('刚刚', false);
        $response->assertSee('group relative w-full overflow-hidden rounded-3xl', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-detail-modal"', false);
    }

    public function test_viewer_history_page_limits_each_page_to_12_records_and_renders_pagination(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'paginated_history_viewer',
            'email' => 'paginated_history_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        for ($index = 1; $index <= 25; $index++) {
            $tweetId = (string) (5000 + $index);
            $timestamp = now()->subMinutes(26 - $index);
            $recordLabel = sprintf('分页观看记录 #%02d', $index);

            $this->insertResolvedTweet($repository, $source['id'], [
                'tweetId' => $tweetId,
                'tweet' => [
                    'authorHandle' => 'demo',
                    'authorName' => 'Demo Creator',
                    'text' => $recordLabel,
                    'postedAt' => $timestamp->toISOString(),
                ],
            ]);

            $video = Video::query()->where('tweet_id', $tweetId)->firstOrFail();

            DB::table('video_views')->insert([
                'video_id' => $video->id,
                'user_id' => $viewer->id,
                'session_id' => 'viewer-history-page-'.$index,
                'view_date' => $timestamp->toDateString(),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $firstPage = $this->actingAs($viewer)->get('/me/history');

        $firstPage->assertOk();
        $firstPage->assertSee('观看记录分页', false);
        $firstPage->assertSee('/me/history?page=2', false);
        $firstPage->assertSee('ph ph-caret-left', false);
        $firstPage->assertSee('ph ph-caret-right', false);
        $firstPage->assertSee('inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200', false);
        $firstPage->assertSee('分页观看记录 #25', false);
        $firstPage->assertDontSee('分页观看记录 #13', false);
        $firstPageContent = $firstPage->getContent();
        self::assertIsString($firstPageContent);
        self::assertSame(12, substr_count($firstPageContent, 'data-history-record-item="true"'));

        $secondPage = $this->actingAs($viewer)->get('/me/history?page=2');

        $secondPage->assertOk();
        $secondPage->assertSee('观看记录分页', false);
        $secondPage->assertSee('/me/history', false);
        $secondPage->assertSee('分页观看记录 #13', false);
        $secondPage->assertSee('分页观看记录 #02', false);
        $secondPage->assertDontSee('分页观看记录 #25', false);
        $secondPage->assertDontSee('分页观看记录 #01', false);
        $secondPageContent = $secondPage->getContent();
        self::assertIsString($secondPageContent);
        self::assertSame(12, substr_count($secondPageContent, 'data-history-record-item="true"'));

        $thirdPage = $this->actingAs($viewer)->get('/me/history?page=3');

        $thirdPage->assertOk();
        $thirdPage->assertSee('观看记录分页', false);
        $thirdPage->assertSee('分页观看记录 #01', false);
        $thirdPage->assertDontSee('分页观看记录 #02', false);
        $thirdPageContent = $thirdPage->getContent();
        self::assertIsString($thirdPageContent);
        self::assertSame(1, substr_count($thirdPageContent, 'data-history-record-item="true"'));
    }

    public function test_viewer_bookmarks_page_renders_real_records_in_grid_and_exposes_remove_action(): void
    {
        $repository = $this->useShortVideoDatabase();
        $postedAt = now()->subHour()->subMinute();
        $viewer = User::factory()->create([
            'username' => 'collector_viewer',
            'email' => 'collector_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '4501',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo Creator',
                'text' => '集合页测试视频',
                'postedAt' => $postedAt->toISOString(),
            ],
        ]);

        $video = Video::query()->firstOrFail();

        $viewer->bookmarkedVideos()->attach($video->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get('/me/bookmarks');

        $response->assertOk();
        $response->assertSee('我的收藏', false);
        $response->assertSee('集合页测试视频', false);
        $response->assertSee('Demo Creator', false);
        $response->assertSee('data-bookmark-back="true"', false);
        $response->assertSee('data-bookmark-record-grid="true"', false);
        $response->assertSee('data-bookmark-record-item="true"', false);
        $response->assertSee('data-bookmark-record-remove="true"', false);
        $response->assertSee('/api/videos/'.$video->id.'/bookmarks', false);
        $response->assertSee('取消收藏', false);
        $response->assertSee('aspect-video', false);
        $response->assertSee('line-clamp-1 overflow-hidden text-base font-semibold leading-6 text-gray-900', false);
        $response->assertSee('1小时前', false);
        $response->assertDontSee('刚刚', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-detail-modal"', false);
    }

    public function test_viewer_bookmarks_page_limits_each_page_to_12_records_and_renders_pagination(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'paginated_bookmark_viewer',
            'email' => 'paginated_bookmark_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        for ($index = 1; $index <= 25; $index++) {
            $tweetId = (string) (6000 + $index);
            $timestamp = now()->subMinutes(26 - $index);
            $bookmarkLabel = sprintf('分页收藏 #%02d', $index);

            $this->insertResolvedTweet($repository, $source['id'], [
                'tweetId' => $tweetId,
                'tweet' => [
                    'authorHandle' => 'demo',
                    'authorName' => 'Demo Creator',
                    'text' => $bookmarkLabel,
                    'postedAt' => $timestamp->toISOString(),
                ],
            ]);

            $video = Video::query()->where('tweet_id', $tweetId)->firstOrFail();
            $viewer->bookmarkedVideos()->attach($video->id, [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $firstPage = $this->actingAs($viewer)->get('/me/bookmarks');

        $firstPage->assertOk();
        $firstPage->assertSee('我的收藏分页', false);
        $firstPage->assertSee('/me/bookmarks?page=2', false);
        $firstPage->assertSee('ph ph-caret-left', false);
        $firstPage->assertSee('ph ph-caret-right', false);
        $firstPage->assertSee('inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200', false);
        $firstPage->assertSee('分页收藏 #25', false);
        $firstPage->assertDontSee('分页收藏 #13', false);
        $firstPageContent = $firstPage->getContent();
        self::assertIsString($firstPageContent);
        self::assertSame(12, substr_count($firstPageContent, 'data-bookmark-record-item="true"'));

        $secondPage = $this->actingAs($viewer)->get('/me/bookmarks?page=2');

        $secondPage->assertOk();
        $secondPage->assertSee('我的收藏分页', false);
        $secondPage->assertSee('/me/bookmarks', false);
        $secondPage->assertSee('分页收藏 #13', false);
        $secondPage->assertSee('分页收藏 #02', false);
        $secondPage->assertDontSee('分页收藏 #25', false);
        $secondPage->assertDontSee('分页收藏 #01', false);
        $secondPageContent = $secondPage->getContent();
        self::assertIsString($secondPageContent);
        self::assertSame(12, substr_count($secondPageContent, 'data-bookmark-record-item="true"'));

        $thirdPage = $this->actingAs($viewer)->get('/me/bookmarks?page=3');

        $thirdPage->assertOk();
        $thirdPage->assertSee('我的收藏分页', false);
        $thirdPage->assertSee('分页收藏 #01', false);
        $thirdPage->assertDontSee('分页收藏 #02', false);
        $thirdPageContent = $thirdPage->getContent();
        self::assertIsString($thirdPageContent);
        self::assertSame(1, substr_count($thirdPageContent, 'data-bookmark-record-item="true"'));
    }

    public function test_interactions_page_renders_mixed_records_in_list_and_exposes_actions(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'collector_viewer',
            'email' => 'collector_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '4501',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo Creator',
                'text' => '互动点赞测试视频',
                'postedAt' => now()->subHour()->toISOString(),
            ],
        ]);
        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '4502',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo Creator',
                'text' => '互动评论测试视频',
                'postedAt' => now()->subHours(2)->toISOString(),
            ],
        ]);

        $likedVideo = Video::query()->where('tweet_id', '4501')->firstOrFail();
        $commentedVideo = Video::query()->where('tweet_id', '4502')->firstOrFail();

        $viewer->likedVideos()->attach($likedVideo->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $comment = VideoComment::query()->create([
            'video_id' => $commentedVideo->id,
            'user_id' => $viewer->id,
            'body' => '很适合集合页断言',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($viewer)->get('/me/interactions');

        $response->assertOk();
        $response->assertSee('我的互动', false);
        $response->assertSee('data-interaction-back="true"', false);
        $response->assertSee('data-interaction-list="true"', false);
        $response->assertSee('data-interaction-item="true"', false);
        $response->assertSee('互动点赞测试视频', false);
        $response->assertSee('互动评论测试视频', false);
        $response->assertSee('点赞了这个视频', false);
        $response->assertSee('评论了这个视频', false);
        $response->assertSee('很适合集合页断言', false);
        $response->assertSee('/api/videos/'.$likedVideo->id.'/likes', false);
        $response->assertSee('/api/videos/'.$commentedVideo->id.'/comments/'.$comment->id, false);
        $response->assertSee('取消点赞', false);
        $response->assertSee('删除评论', false);
        $response->assertDontSee('id="feed-grid"', false);
        $response->assertDontSee('id="feed-bootstrap"', false);
        $response->assertDontSee('id="feed-detail-modal"', false);
    }

    public function test_interactions_page_limits_each_page_to_12_records_and_renders_pagination(): void
    {
        $repository = $this->useShortVideoDatabase();
        $viewer = User::factory()->create([
            'username' => 'paginated_interaction_viewer',
            'email' => 'paginated_interaction_viewer@example.com',
        ]);
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        for ($index = 1; $index <= 13; $index++) {
            $tweetId = (string) (7000 + $index);
            $timestamp = now()->subMinutes(14 - $index);
            $interactionLabel = sprintf('分页互动点赞 #%02d', $index);

            $this->insertResolvedTweet($repository, $source['id'], [
                'tweetId' => $tweetId,
                'tweet' => [
                    'authorHandle' => 'demo',
                    'authorName' => 'Demo Creator',
                    'text' => $interactionLabel,
                    'postedAt' => $timestamp->toISOString(),
                ],
            ]);

            $video = Video::query()->where('tweet_id', $tweetId)->firstOrFail();
            $viewer->likedVideos()->attach($video->id, [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $firstPage = $this->actingAs($viewer)->get('/me/interactions');

        $firstPage->assertOk();
        $firstPage->assertSee('我的互动分页', false);
        $firstPage->assertSee('/me/interactions?page=2', false);
        $firstPage->assertSee('分页互动点赞 #13', false);
        $firstPage->assertDontSee('分页互动点赞 #01', false);
        $firstPageContent = $firstPage->getContent();
        self::assertIsString($firstPageContent);
        self::assertSame(12, substr_count($firstPageContent, 'data-interaction-item="true"'));

        $secondPage = $this->actingAs($viewer)->get('/me/interactions?page=2');

        $secondPage->assertOk();
        $secondPage->assertSee('我的互动分页', false);
        $secondPage->assertSee('分页互动点赞 #01', false);
        $secondPage->assertDontSee('分页互动点赞 #02', false);
        $secondPageContent = $secondPage->getContent();
        self::assertIsString($secondPageContent);
        self::assertSame(1, substr_count($secondPageContent, 'data-interaction-item="true"'));
    }
}
