<?php

namespace App\Console\Commands;

use App\ShortVideo\Services\CrawlService;
use Illuminate\Console\Command;

final class ShortVideoSyncSourcesCommand extends Command
{
    protected $signature = 'shortvideo:sync-sources {--json : Output JSON only}';

    protected $description = 'Sync config/sources.json into the SQLite sources table.';

    public function handle(CrawlService $crawlService): int
    {
        $sources = $crawlService->syncConfiguredSources();

        if ($this->option('json')) {
            $this->line(json_encode(['items' => $sources], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Synced '.count($sources).' sources.');

        return self::SUCCESS;
    }
}
