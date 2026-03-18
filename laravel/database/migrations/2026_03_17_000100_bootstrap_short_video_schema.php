<?php

use App\ShortVideo\Legacy\LegacySchemaPreparationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacySchemaPreparationService::class)->ensureBootstrapSchema();
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive drops against an existing Node-compatible database.
    }
};
