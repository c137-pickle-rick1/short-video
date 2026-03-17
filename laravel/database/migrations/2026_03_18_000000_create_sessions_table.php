<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (Schema::hasTable('schema_migrations')) {
            DB::table('schema_migrations')->insertOrIgnore([
                ['id' => '007_create_sessions_table', 'applied_at' => now()->toISOString()],
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive drops against an existing database.
    }
};
