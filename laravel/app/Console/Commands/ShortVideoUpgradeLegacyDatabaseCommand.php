<?php

namespace App\Console\Commands;

use App\ShortVideo\Legacy\LegacyDatabaseUpgradeService;
use Illuminate\Console\Command;

final class ShortVideoUpgradeLegacyDatabaseCommand extends Command
{
    protected $signature = 'shortvideo:upgrade-legacy-db {--json : Output JSON only}';

    protected $description = 'Backfill legacy shortvideo records after migrate has prepared the Laravel-first schema.';

    public function handle(LegacyDatabaseUpgradeService $upgrade): int
    {
        $result = $upgrade->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy shortvideo upgrade finished.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
