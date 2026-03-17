<?php

namespace Tests\Feature;

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

        $response->assertOk();
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.videoUrl', '/api/media/2000');
        $response->assertJsonPath('items.0.hlsUrl', 'https://example.com/video.m3u8');
        $response->assertJsonPath('items.0.authorAvatarUrl', 'https://example.com/avatar.jpg');
        $response->assertJsonPath('items.0.durationText', '0:21');
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
}
