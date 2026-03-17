<?php

namespace App\Console\Commands;

use App\ShortVideo\Services\CrawlService;
use Illuminate\Console\Command;

final class ShortVideoBackfillAvatarsCommand extends Command
{
    protected $signature = 'shortvideo:backfill-avatars {--limit= : Maximum number of published tweets to re-resolve} {--json : Output JSON only}';

    protected $description = 'Backfill missing author avatars for already published tweets.';

    public function handle(CrawlService $crawlService): int
    {
        $limit = $this->option('limit');
        $result = $crawlService->backfillMissingAvatars(is_numeric((string) $limit) ? (int) $limit : null);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Avatar backfill finished.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
