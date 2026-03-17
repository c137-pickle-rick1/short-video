<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Repositories\ShortVideoRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

final class MediaProxyService
{
    private const PROXY_RESPONSE_HEADERS = [
        'accept-ranges',
        'cache-control',
        'content-length',
        'content-range',
        'content-type',
        'etag',
        'last-modified',
    ];

    public function __construct(
        private readonly ShortVideoRepository $repository,
        private readonly Client $client = new Client(['http_errors' => false, 'stream' => true])
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getMediaStream(string $tweetId, ?string $rangeHeader = ''): array
    {
        $media = $this->repository->getPrimaryMedia($tweetId);
        if (! is_array($media) || empty($media['url'])) {
            return [
                'kind' => 'json',
                'status' => 404,
                'body' => ['error' => 'Video not found'],
            ];
        }

        $timeoutSeconds = max(1, (int) config('shortvideo.media_proxy_timeout_ms')) / 1000;

        try {
            $response = $this->client->request('GET', (string) $media['url'], [
                'headers' => [
                    'Range' => (string) ($rangeHeader ?? ''),
                    'Referer' => 'https://x.com/',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                ],
                'timeout' => $timeoutSeconds,
                'connect_timeout' => $timeoutSeconds,
                'read_timeout' => $timeoutSeconds,
                'stream' => true,
                'allow_redirects' => false,
            ]);
        } catch (GuzzleException $exception) {
            return [
                'kind' => 'json',
                'status' => 504,
                'body' => [
                    'error' => 'Upstream video request timed out after '.config('shortvideo.media_proxy_timeout_ms').'ms',
                ],
            ];
        }

        $status = $response->getStatusCode();
        if ($status !== 200 && $status !== 206) {
            return [
                'kind' => 'json',
                'status' => $status,
                'body' => [
                    'error' => "Upstream video request failed with HTTP {$status}",
                ],
            ];
        }

        $headers = [];
        foreach (self::PROXY_RESPONSE_HEADERS as $header) {
            if ($response->hasHeader($header)) {
                $headers[$header] = implode(', ', $response->getHeader($header));
            }
        }

        return [
            'kind' => 'stream',
            'status' => $status,
            'headers' => $headers,
            'body' => $response->getBody(),
        ];
    }

    public function stream(StreamInterface $stream): \Generator
    {
        while (! $stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                break;
            }

            yield $chunk;
        }
    }
}
