<?php

namespace App\ShortVideo\Services;

use App\ShortVideo\Repositories\SourceRepository;

final class SourceSyncService
{
    public function __construct(
        private readonly SourceConfigService $sourceConfigService,
        private readonly SourceRepository $sources
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncConfiguredSources(): array
    {
        return $this->sources->syncSources($this->sourceConfigService->loadSources());
    }
}
