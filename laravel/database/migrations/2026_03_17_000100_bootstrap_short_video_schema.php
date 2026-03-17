<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schema_migrations')) {
            Schema::create('schema_migrations', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->text('applied_at');
            });
        }

        if (! Schema::hasTable('sources')) {
            Schema::create('sources', function (Blueprint $table): void {
                $table->id();
                $table->string('handle')->unique();
                $table->integer('enabled')->default(1);
                $table->text('last_discovered_at')->nullable();
            });
        }

        if (! Schema::hasTable('tweets')) {
            Schema::create('tweets', function (Blueprint $table): void {
                $table->string('tweet_id')->primary();
                $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
                $table->text('tweet_url');
                $table->text('author_handle')->nullable();
                $table->text('author_name')->nullable();
                $table->text('author_avatar_url')->nullable();
                $table->text('text')->nullable();
                $table->text('posted_at')->nullable();
                $table->text('duration_text')->nullable();
                $table->text('poster_url')->nullable();
                $table->text('status');
                $table->text('raw_discovery_payload')->nullable();
                $table->text('raw_resolve_payload')->nullable();
                $table->text('ingested_at');
                $table->text('resolved_at')->nullable();
            });
        }

        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table): void {
                $table->id();
                $table->string('tweet_id');
                $table->foreign('tweet_id')->references('tweet_id')->on('tweets')->cascadeOnDelete();
                $table->text('url');
                $table->integer('bitrate')->nullable();
                $table->text('content_type')->nullable();
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('sort_order')->default(0);
                $table->integer('is_primary')->default(0);
            });
        }

        if (! Schema::hasTable('crawl_runs')) {
            Schema::create('crawl_runs', function (Blueprint $table): void {
                $table->id();
                $table->text('phase');
                $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
                $table->text('started_at');
                $table->text('finished_at')->nullable();
                $table->text('status');
                $table->integer('items_seen')->default(0);
                $table->integer('items_inserted')->default(0);
                $table->integer('items_resolved')->default(0);
                $table->text('error_message')->nullable();
            });
        }

        if (! Schema::hasTable('runtime_states')) {
            Schema::create('runtime_states', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->text('updated_at');
            });
        }

        if (! Schema::hasColumn('tweets', 'author_avatar_url')) {
            Schema::table('tweets', function (Blueprint $table): void {
                $table->text('author_avatar_url')->nullable();
            });
        }

        if (! Schema::hasColumn('tweets', 'duration_text')) {
            Schema::table('tweets', function (Blueprint $table): void {
                $table->text('duration_text')->nullable();
            });
        }

        DB::statement("CREATE INDEX IF NOT EXISTS idx_tweets_status_sort ON tweets(status, posted_at DESC, ingested_at DESC, tweet_id DESC)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_tweets_source_status ON tweets(source_id, status)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_media_assets_primary ON media_assets(tweet_id, is_primary)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_crawl_runs_phase_source ON crawl_runs(phase, source_id, started_at DESC)");

        $rows = DB::select(<<<'SQL'
            SELECT tweet_id AS tweetId, raw_discovery_payload AS rawDiscoveryPayload
            FROM tweets
            WHERE (duration_text IS NULL OR TRIM(duration_text) = '')
              AND raw_discovery_payload IS NOT NULL
        SQL);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->rawDiscoveryPayload, true);
            $durationText = $payload['durationText'] ?? $payload['discoveredLink']['durationText'] ?? null;
            $durationText = is_string($durationText) ? trim($durationText) : null;
            if ($durationText === null || $durationText === '') {
                continue;
            }

            DB::update('UPDATE tweets SET duration_text = ? WHERE tweet_id = ?', [$durationText, $row->tweetId]);
        }

        DB::table('schema_migrations')->insertOrIgnore([
            ['id' => '001_initial_schema', 'applied_at' => now()->toISOString()],
            ['id' => '002_add_author_avatar_url_to_tweets', 'applied_at' => now()->toISOString()],
            ['id' => '003_add_duration_text_to_tweets', 'applied_at' => now()->toISOString()],
            ['id' => '004_backfill_tweet_durations', 'applied_at' => now()->toISOString()],
        ]);
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive drops against an existing Node-compatible database.
    }
};
