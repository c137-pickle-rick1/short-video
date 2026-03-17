<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_home_page_renders_ssr_feed_and_bootstrap_payload(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '3001',
            'tweet' => [
                'text' => '服务端首屏卡片',
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

        $response = $this->get('/?source=demo');

        $response->assertOk();
        $response->assertSee('服务端首屏卡片', false);
        $response->assertSee('/api/media/3001', false);
        $response->assertSee('data-hls-url="https://example.com/video.m3u8"', false);
        $response->assertSee('"source":"demo"', false);
        $response->assertSee('id="feed-bootstrap"', false);
        $response->assertSee('/vendor/plyr/plyr.min.js', false);
        $response->assertDontSee('id="source-filter"', false);
        $response->assertDontSee('id="feed-summary"', false);
        $response->assertDontSee('id="feed-status"', false);
    }
}
