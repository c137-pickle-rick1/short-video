<?php

namespace Tests\Feature;

use App\ShortVideo\Services\SidecarClient;
use Tests\TestCase;

final class ShortVideoCommandTest extends TestCase
{
    public function test_sync_sources_command_reads_json_config(): void
    {
        $repository = $this->useShortVideoDatabase([
            ['handle' => 'demo', 'enabled' => true],
            ['handle' => 'disabled', 'enabled' => false],
        ]);

        $this->artisan('shortvideo:sync-sources --json')->assertExitCode(0);

        $sources = $repository->listSources();
        $this->assertCount(2, $sources);
        $this->assertSame('demo', $sources[0]['handle']);
        $this->assertTrue($sources[0]['enabled']);
        $this->assertFalse($sources[1]['enabled']);
    }

    public function test_crawl_once_command_uses_sidecar_results_and_updates_feed(): void
    {
        $repository = $this->useShortVideoDatabase([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->app->instance(SidecarClient::class, new class extends SidecarClient
        {
            public function discoverSource(string $handle, ?string $sourceUserId = null, ?string $sinceId = null): array
            {
                return [
                    'items' => [[
                        'tweetId' => '9001',
                        'tweetUrl' => "https://x.com/{$handle}/status/9001",
                        'durationText' => '0:37',
                        'rawDiscoveryPayload' => ['link' => 'demo'],
                    ]],
                ];
            }

            public function resolveTweets(array $tweets): array
            {
                return [
                    'results' => [[
                        'tweetId' => '9001',
                        'status' => 'resolved',
                        'tweet' => [
                            'authorHandle' => 'demo',
                            'authorName' => 'Demo',
                            'authorAvatarUrl' => 'https://example.com/avatar.jpg',
                            'text' => '命令行抓取结果',
                            'postedAt' => '2025-02-14T06:37:00.000Z',
                            'posterUrl' => 'https://example.com/poster.jpg',
                        ],
                        'mediaAssets' => [[
                            'url' => 'https://example.com/video.mp4',
                            'bitrate' => 1000,
                            'contentType' => 'video/mp4',
                            'width' => 720,
                            'height' => 1280,
                            'sortOrder' => 0,
                            'isPrimary' => true,
                        ]],
                    ]],
                ];
            }
        });

        $this->artisan('shortvideo:crawl-once --json')->assertExitCode(0);

        $this->assertSame(1, $repository->countTweetsByStatus('resolved'));
        $feed = $repository->getFeed(null, 'demo', 8);
        $this->assertCount(1, $feed['items']);
        $this->assertSame('命令行抓取结果', $feed['items'][0]['text']);
    }

    public function test_crawl_once_persists_source_checkpoint_and_reuses_it_for_incremental_discovery(): void
    {
        $repository = $this->useShortVideoDatabase([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $sidecar = new class extends SidecarClient
        {
            /**
             * @var array<int, array<string, string|null>>
             */
            public array $discoverCalls = [];

            private int $discoverCount = 0;

            public function discoverSource(string $handle, ?string $sourceUserId = null, ?string $sinceId = null): array
            {
                $this->discoverCalls[] = [
                    'handle' => $handle,
                    'sourceUserId' => $sourceUserId,
                    'sinceId' => $sinceId,
                ];

                if ($this->discoverCount === 0) {
                    $this->discoverCount++;

                    return [
                        'items' => [[
                            'tweetId' => '9002',
                            'tweetUrl' => "https://x.com/{$handle}/status/9002",
                            'durationText' => '0:12',
                            'rawDiscoveryPayload' => ['method' => 'x-api-timeline'],
                        ]],
                        'rawPayload' => [
                            'userId' => 'u-demo',
                            'latestTweetId' => '9002',
                        ],
                    ];
                }

                return [
                    'items' => [],
                    'rawPayload' => [
                        'userId' => 'u-demo',
                        'latestTweetId' => '9002',
                    ],
                ];
            }

            public function resolveTweets(array $tweets): array
            {
                return [
                    'results' => array_map(static fn (array $tweet) => [
                        'tweetId' => (string) $tweet['tweetId'],
                        'status' => 'resolved',
                        'tweet' => [
                            'authorHandle' => 'demo',
                            'authorName' => 'Demo',
                            'authorAvatarUrl' => 'https://example.com/avatar.jpg',
                            'text' => '增量抓取结果',
                            'postedAt' => '2025-02-14T06:37:00.000Z',
                            'posterUrl' => 'https://example.com/poster.jpg',
                        ],
                        'mediaAssets' => [[
                            'url' => 'https://example.com/video.mp4',
                            'bitrate' => 1000,
                            'contentType' => 'video/mp4',
                            'width' => 720,
                            'height' => 1280,
                            'sortOrder' => 0,
                            'isPrimary' => true,
                        ]],
                    ], $tweets),
                ];
            }
        };

        $this->app->instance(SidecarClient::class, $sidecar);

        $this->artisan('shortvideo:crawl-once --json')->assertExitCode(0);

        $source = $repository->listSources()[0];
        $this->assertSame('u-demo', $source['providerUserId']);
        $this->assertSame('9002', $source['lastSeenTweetId']);
        $this->assertNull($sidecar->discoverCalls[0]['sourceUserId']);
        $this->assertNull($sidecar->discoverCalls[0]['sinceId']);

        $this->artisan('shortvideo:crawl-once --json')->assertExitCode(0);

        $this->assertSame('u-demo', $sidecar->discoverCalls[1]['sourceUserId']);
        $this->assertSame('9002', $sidecar->discoverCalls[1]['sinceId']);
    }
}
