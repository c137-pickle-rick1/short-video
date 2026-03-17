<?php

namespace App\Console\Commands;

use App\ShortVideo\Services\CrawlService;
use App\ShortVideo\Services\RuntimeStateStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ShortVideoCrawlOnceCommand extends Command
{
    protected $signature = 'shortvideo:crawl-once {--json : Output JSON only}';

    protected $description = 'Run one discover + resolve cycle without starting the HTTP server or scheduler.';

    public function handle(CrawlService $crawlService, RuntimeStateStore $runtimeStateStore): int
    {
        $owner = (string) Str::uuid();
        if (! $runtimeStateStore->acquireCrawlLock($owner, 3600)) {
            $this->warn('Another crawl is already running.');

            return self::FAILURE;
        }

        try {
            $result = $crawlService->crawlOnce();
        } finally {
            $runtimeStateStore->releaseCrawlLock($owner);
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Crawl once finished.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
