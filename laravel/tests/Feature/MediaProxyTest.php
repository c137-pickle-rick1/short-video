<?php

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Tests\TestCase;

final class MediaProxyTest extends TestCase
{
    public function test_media_proxy_streams_upstream_headers_and_body(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2002',
        ]);

        $mock = new MockHandler([
            new PsrResponse(206, [
                'content-type' => 'video/mp4',
                'content-range' => 'bytes 0-9/10',
                'accept-ranges' => 'bytes',
                'cache-control' => 'public, max-age=60',
                'content-length' => '10',
                'etag' => '"demo"',
            ], 'video-data'),
        ]);

        $this->app->instance(Client::class, new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
            'stream' => true,
        ]));

        $response = $this->call('GET', '/api/media/2002', [], [], [], [
            'HTTP_RANGE' => 'bytes=0-9',
        ]);

        $response->assertStatus(206);
        $response->assertHeader('content-type', 'video/mp4');
        $response->assertHeader('content-range', 'bytes 0-9/10');
        $this->assertSame('video-data', $response->streamedContent());
    }

    public function test_media_proxy_returns_504_when_upstream_times_out(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '2003',
            'media' => [
                'url' => 'https://example.com/slow.mp4',
            ],
        ]);

        $mock = new MockHandler([
            new ConnectException('timed out', new Request('GET', 'https://example.com/slow.mp4')),
        ]);

        $this->app->instance(Client::class, new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
            'stream' => true,
        ]));

        $response = $this->getJson('/api/media/2003');

        $response->assertStatus(504);
        $this->assertStringContainsString('timed out', (string) $response->json('error'));
    }
}
