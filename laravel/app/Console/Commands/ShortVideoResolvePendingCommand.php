<?php

namespace App\Console\Commands;

use App\ShortVideo\Services\CrawlService;
use Illuminate\Console\Command;

final class ShortVideoResolvePendingCommand extends Command
{
    protected $signature = 'shortvideo:resolve-pending {--limit= : Maximum number of pending tweets to resolve} {--json : Output JSON only}';

    protected $description = 'Resolve pending tweet records through the Node/Playwright sidecar.';

    public function handle(CrawlService $crawlService): int
    {
        $limit = $this->option('limit');
        $result = $crawlService->resolvePending(is_numeric((string) $limit) ? (int) $limit : null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Resolve pending finished.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
