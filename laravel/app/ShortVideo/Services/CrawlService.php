<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Support\ShortVideoData;
use App\ShortVideo\Support\ShortVideoException;

final class CrawlService
{
    public function __construct(
        private readonly ShortVideoRepository $repository,
        private readonly SourceSyncService $sourceSyncService,
        private readonly RuntimeStateStore $runtimeStateStore,
        private readonly SidecarClient $sidecarClient
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncConfiguredSources(): array
    {
        return $this->sourceSyncService->syncConfiguredSources();
    }

    /**
     * @return array{backoffUntil: ?string, backoffReason: ?string}
     */
    public function getBackoffState(): array
    {
        return $this->runtimeStateStore->getBackoffState();
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function discoverSource(array $source): array
    {
        $runId = $this->repository->createCrawlRun('discovery', (int) $source['id']);

        try {
            $result = $this->sidecarClient->discoverSource((string) $source['handle']);
            $items = is_array($result['items'] ?? null) ? $result['items'] : [];
            $inserted = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $wasInserted = $this->repository->insertDiscoveredTweet([
                    'tweetId' => $item['tweetId'],
                    'sourceId' => $source['id'],
                    'tweetUrl' => $item['tweetUrl'],
                    'durationText' => $item['durationText'] ?? null,
                    'rawDiscoveryPayload' => $item['rawDiscoveryPayload'] ?? null,
                ]);

                if ($wasInserted) {
                    $inserted++;
                }
            }

            $this->repository->touchSourceLastDiscovered((int) $source['id']);
            $this->repository->finishCrawlRun($runId, [
                'status' => 'success',
                'itemsSeen' => count($items),
                'itemsInserted' => $inserted,
            ]);

            return [
                'itemsSeen' => count($items),
                'itemsInserted' => $inserted,
            ];
        } catch (ShortVideoException $exception) {
            if (ShortVideoData::isBackoffErrorCode($exception->errorCode())) {
                $this->runtimeStateStore->setBackoff($exception->errorCode());
            }

            $this->repository->finishCrawlRun($runId, [
                'status' => 'failed',
                'errorMessage' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function discoverAllSources(): array
    {
        if ($this->isBackoffActive()) {
            return $this->skippedSummary();
        }

        $summary = [
            'itemsSeen' => 0,
            'itemsInserted' => 0,
        ];

        foreach ($this->repository->listEnabledSources() as $source) {
            $result = $this->discoverSource($source);
            $summary['itemsSeen'] += $result['itemsSeen'];
            $summary['itemsInserted'] += $result['itemsInserted'];
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePending(?int $limit = null): array
    {
        if ($this->isBackoffActive()) {
            return $this->skippedSummary();
        }

        return $this->runResolutionBatch($this->repository->listPendingTweets($limit), 'resolve');
    }

    /**
     * @return array<string, mixed>
     */
    public function backfillMissingAvatars(?int $limit = null): array
    {
        if ($this->isBackoffActive()) {
            return $this->skippedSummary();
        }

        return $this->runResolutionBatch(
            $this->repository->listPublishedTweetsMissingAvatar($limit),
            'resolve'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function crawlOnce(): array
    {
        $this->syncConfiguredSources();
        $discovery = $this->discoverAllSources();
        $resolution = $this->resolvePending();
        $backoff = $this->getBackoffState();

        return [
            'discovery' => $discovery,
            'resolution' => $resolution,
            'backoffUntil' => $backoff['backoffUntil'],
            'backoffReason' => $backoff['backoffReason'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tweets
     * @return array<string, mixed>
     */
    private function runResolutionBatch(array $tweets, string $phase): array
    {
        $runId = $this->repository->createCrawlRun($phase);
        $resolvedCount = 0;
        $failureMessage = null;
        $finalStatus = 'success';

        try {
            foreach (array_chunk($tweets, 10) as $tweetChunk) {
                $payload = $this->sidecarClient->resolveTweets($tweetChunk);
                $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];

                foreach ($results as $resolution) {
                    if (! is_array($resolution) || ! isset($resolution['tweetId'])) {
                        continue;
                    }

                    $this->repository->applyResolution((string) $resolution['tweetId'], $resolution);
                    $resolvedCount++;

                    if (ShortVideoData::isBackoffErrorCode($resolution['errorCode'] ?? null)) {
                        $this->runtimeStateStore->setBackoff((string) $resolution['errorCode']);
                        $failureMessage = (string) ($resolution['errorMessage'] ?? $resolution['errorCode']);
                        $finalStatus = 'failed';
                        break 2;
                    }
                }
            }
        } catch (ShortVideoException $exception) {
            $failureMessage = $exception->getMessage();
            $finalStatus = 'failed';

            if (ShortVideoData::isBackoffErrorCode($exception->errorCode())) {
                $this->runtimeStateStore->setBackoff($exception->errorCode());
            }
        }

        $this->repository->finishCrawlRun($runId, [
            'status' => $finalStatus,
            'itemsSeen' => count($tweets),
            'itemsResolved' => $resolvedCount,
            'errorMessage' => $failureMessage,
        ]);

        return [
            'itemsSeen' => count($tweets),
            'itemsResolved' => $resolvedCount,
            'status' => $finalStatus,
            'errorMessage' => $failureMessage,
        ];
    }

    private function isBackoffActive(): bool
    {
        $state = $this->runtimeStateStore->getBackoffState();

        return ! empty($state['backoffUntil']);
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedSummary(): array
    {
        $state = $this->runtimeStateStore->getBackoffState();

        return [
            'skipped' => true,
            'reason' => $state['backoffReason'],
            'until' => $state['backoffUntil'],
        ];
    }
}
