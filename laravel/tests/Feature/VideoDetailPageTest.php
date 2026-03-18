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
        VideoComment::query()->create([
            'video_id' => $video->id,
            'user_id' => $commentAuthor->id,
            'parent_id' => null,
            'body' => '详情页首条评论',
        ]);

        $response = $this->get(route('videos.show', ['video' => $video]));

        $response->assertOk();
        $response->assertSee('data-video-detail-page="true"', false);
        $response->assertSee('data-video-detail-player="true"', false);
        $response->assertSee('data-video-detail-back="true"', false);
        $response->assertSee('SEO Detail Title', false);
        $response->assertSee('视频详情摘要', false);
        $response->assertSee('href="'.route('profile.show', ['username' => 'detailsource'], false).'"', false);
        $response->assertSee('评论区', false);
        $response->assertSee('详情页首条评论', false);
        $response->assertSee('1 条评论', false);
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
