<?php

namespace App\Console\Commands;

use App\ShortVideo\Legacy\LegacySchemaPreparationService;
use Illuminate\Console\Command;

final class ShortVideoPrepareLegacyDatabaseCommand extends Command
{
    protected $signature = 'shortvideo:prepare-legacy-db {--json : Output JSON only}';

    protected $description = 'Prepare the legacy-compatible shortvideo schema before running migrations.';

    public function handle(LegacySchemaPreparationService $schemaPreparation): int
    {
        $result = $schemaPreparation->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy shortvideo schema preparation finished.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
