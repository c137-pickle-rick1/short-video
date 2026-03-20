<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use Tests\TestCase;

final class VideoDetailPageTest extends TestCase
{
    public function test_public_published_video_detail_page_renders_watch_page_and_seo_metadata(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'detailsource', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '7001',
            'tweet' => [
                'authorHandle' => 'detailsource',
                'authorName' => 'Detail Source',
                'text' => '原始推文文案',
                'postedAt' => now()->subHours(2)->toISOString(),
                'posterUrl' => 'https://example.com/detail-poster.jpg',
            ],
            'mediaAssets' => [
                [
                    'url' => 'https://example.com/detail-video.mp4',
                    'bitrate' => 1200,
                    'contentType' => 'video/mp4',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 0,
                    'isPrimary' => true,
                ],
                [
                    'url' => 'https://example.com/detail-video.m3u8',
                    'bitrate' => null,
                    'contentType' => 'application/x-mpegURL',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 1,
                    'isPrimary' => false,
                ],
            ],
        ]);

        $video = Video::query()->where('tweet_id', '7001')->firstOrFail();
        $video->forceFill([
            'title' => 'SEO Detail Title',
            'caption' => '视频详情摘要',
            'description' => '#seo #detail',
            'duration_text' => '2:13',
            'duration_seconds' => 133,
            'published_at' => now()->subHours(2),
        ])->save();
        $commentAuthor = User::factory()->create([
            'name' => 'Comment Author',
            'username' => 'comment_author',
        ]);
        $rootComment = VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $commentAuthor->id,
            'parent_id' => null,
            'body' => '详情页首条评论',
        ]);
        VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $commentAuthor->id,
            'parent_id' => $rootComment->id,
            'reply_to_comment_id' => $rootComment->id,
            'body' => '详情页回复内容',
        ]);

        $response = $this->get(route('videos.show', ['video' => $video]));

        $response->assertOk();
        $response->assertSee('data-video-detail-page="true"', false);
        $response->assertSee('data-video-detail-player="true"', false);
        $response->assertSee('data-video-detail-back="true"', false);
        $response->assertDontSee('xl:grid-cols-[minmax(0,1fr)_430px]', false);
        $response->assertSeeInOrder([
            'data-video-detail-section="player"',
            'data-video-detail-section="title"',
            'data-video-detail-section="author"',
            'data-video-detail-section="interactions"',
            'data-video-detail-section="comments"',
        ], false);
        $response->assertSee('SEO Detail Title', false);
        $response->assertSee('视频详情摘要', false);
        $response->assertSee('href="'.route('profile.show', ['username' => 'detailsource'], false).'"', false);
        $response->assertSee('评论区', false);
        $response->assertSee('详情页首条评论', false);
        $response->assertDontSee('详情页回复内容', false);
        $response->assertSee('1 条评论', false);
        $response->assertSee('发布日期 ·', false);
        $response->assertSee('次观看', false);
        $response->assertSee('<link rel="canonical" href="'.route('videos.show', ['video' => $video]).'" />', false);
        $response->assertSee('<meta name="description" content="视频详情摘要" />', false);
        $response->assertSee('<meta property="og:title" content="SEO Detail Title" />', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image" />', false);
        $response->assertSee('src="/api/media/7001"', false);
        $response->assertSee('id="video-structured-data"', false);
        $response->assertSee('"@type":"VideoObject"', false);
        $response->assertSee('"contentUrl":', false);
        $response->assertSee('/api/media/7001', false);
    }

    public function test_video_detail_page_paginates_root_comments_and_renders_pagination_links(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'pagingdetail', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '7002',
            'tweet' => [
                'authorHandle' => 'pagingdetail',
                'authorName' => 'Paging Detail',
                'text' => '详情页分页样本',
                'postedAt' => now()->subHours(3)->toISOString(),
                'posterUrl' => 'https://example.com/paging-poster.jpg',
            ],
            'mediaAssets' => [
                [
                    'url' => 'https://example.com/paging-video.mp4',
                    'bitrate' => 1000,
                    'contentType' => 'video/mp4',
                    'width' => 720,
                    'height' => 1280,
                    'sortOrder' => 0,
                    'isPrimary' => true,
                ],
            ],
        ]);

        $video = Video::query()->where('tweet_id', '7002')->firstOrFail();
        $commentAuthor = User::factory()->create([
            'name' => 'Paging Author',
            'username' => 'paging_author',
        ]);

        foreach (range(1, 12) as $index) {
            VideoComment::query()->create([
                'video_id' => $video->id,
                'user_id' => $commentAuthor->id,
                'parent_id' => null,
                'body' => '根评论 '.$index,
                'created_at' => now()->subMinutes(12 - $index),
                'updated_at' => now()->subMinutes(12 - $index),
            ]);
        }

        $firstPage = $this->get(route('videos.show', ['video' => $video]));

        $firstPage->assertOk();
        $firstPage->assertSee('根评论 12', false);
        $firstPage->assertSee('根评论 3', false);
        $firstPage->assertDontSee('根评论 2', false);
        $firstPage->assertSee('12 条评论', false);
        $firstPage->assertSee('第 1 / 2 页', false);
        $firstPage->assertSee('data-video-detail-pagination="true"', false);
        $firstPage->assertSee(route('videos.show', ['video' => $video], false).'?page=2', false);
        $firstPage->assertSeeInOrder([
            'data-video-detail-section="comments"',
            'data-video-detail-section="pagination"',
        ], false);

        $secondPage = $this->get(route('videos.show', ['video' => $video, 'page' => 2]));

        $secondPage->assertOk();
        $secondPage->assertSee('根评论 2', false);
        $secondPage->assertSee('根评论 1', false);
        $secondPage->assertDontSee('根评论 12', false);
        $secondPage->assertSee('第 2 / 2 页', false);
        $secondPage->assertSee(route('videos.show', ['video' => $video], false), false);
    }

    public function test_video_detail_page_returns_not_found_for_non_public_or_unpublished_videos(): void
    {
        $this->useShortVideoDatabase();
        $creator = User::factory()->create([
            'username' => 'hidden_creator',
        ]);

        foreach ([
            ['title' => 'Review Hidden', 'status' => 'reviewing', 'visibility' => 'public'],
            ['title' => 'Removed Hidden', 'status' => 'removed', 'visibility' => 'public'],
            ['title' => 'Private Hidden', 'status' => 'published', 'visibility' => 'private'],
        ] as $definition) {
            $video = Video::query()->create([
                'origin' => 'manual_upload',
                'uploader_user_id' => $creator->id,
                'title' => $definition['title'],
                'playback_url' => 'https://example.com/hidden.mp4',
                'visibility' => $definition['visibility'],
                'status' => $definition['status'],
                'published_at' => now()->subHour(),
            ]);

            $this->get(route('videos.show', ['video' => $video]))->assertNotFound();
        }
    }

    public function test_feed_cards_render_title_links_without_removing_modal_trigger(): void
    {
        $this->useShortVideoDatabase();
        $creator = User::factory()->create([
            'name' => 'Card Creator',
            'username' => 'card_creator',
        ]);

        $video = Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creator->id,
            'title' => 'Card Linked Video',
            'caption' => 'Card Linked Video',
            'poster_url' => 'https://example.com/card-poster.jpg',
            'playback_url' => 'https://example.com/card-video.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subMinutes(30),
        ]);

        $homeResponse = $this->get(route('home'));

        $homeResponse->assertOk();
        $homeResponse->assertSee('data-feed-detail-trigger="true"', false);
        $homeResponse->assertSee('href="'.route('videos.show', ['video' => $video], false).'"', false);
        $homeResponse->assertSee('Card Linked Video', false);

        $profileResponse = $this->get(route('profile.show', ['username' => $creator->username]));

        $profileResponse->assertOk();
        $profileResponse->assertSee('href="'.route('videos.show', ['video' => $video], false).'"', false);
        $profileResponse->assertSee('id="feed-grid"', false);
        $profileResponse->assertSee('data-feed-detail-trigger="true"', false);
    }

    public function test_feed_api_items_include_detail_url(): void
    {
        $this->useShortVideoDatabase();
        $creator = User::factory()->create([
            'name' => 'API Creator',
            'username' => 'api_creator',
        ]);

        $video = Video::query()->create([
            'origin' => 'manual_upload',
            'uploader_user_id' => $creator->id,
            'title' => 'API Detail Link',
            'caption' => 'API Detail Link',
            'poster_url' => 'https://example.com/api-poster.jpg',
            'playback_url' => 'https://example.com/api-video.mp4',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now()->subMinutes(10),
        ]);

        $this->getJson('/api/feed?limit=5')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.detailUrl', route('videos.show', ['video' => $video], false));
    }
}
