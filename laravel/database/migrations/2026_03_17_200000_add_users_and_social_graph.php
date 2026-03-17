<?php

use App\ShortVideo\Support\ShortVideoData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('username')->unique();
                $table->string('email')->nullable()->unique();
                $table->string('phone')->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->text('avatar_url')->nullable();
                $table->text('bio')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_source_links')) {
            Schema::create('user_source_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
                $table->string('relationship')->default('owner');
                $table->boolean('is_primary')->default(true);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'source_id']);
                $table->unique('source_id');
            });
        }

        if (! Schema::hasTable('source_follows')) {
            Schema::create('source_follows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'source_id']);
            });
        }

        if (! Schema::hasTable('videos')) {
            Schema::create('videos', function (Blueprint $table): void {
                $table->id();
                $table->string('origin')->default('x_tweet');
                $table->string('tweet_id')->nullable()->unique();
                $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
                $table->foreignId('uploader_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title')->nullable();
                $table->text('caption')->nullable();
                $table->text('description')->nullable();
                $table->string('storage_disk')->nullable();
                $table->text('storage_path')->nullable();
                $table->text('poster_url')->nullable();
                $table->text('playback_url')->nullable();
                $table->text('hls_url')->nullable();
                $table->string('duration_text')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('visibility')->default('public');
                $table->string('status')->default('published');
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->foreign('tweet_id')->references('tweet_id')->on('tweets')->cascadeOnDelete();
                $table->index(['origin', 'status', 'published_at']);
                $table->index(['source_id', 'published_at']);
                $table->index(['uploader_user_id', 'published_at']);
            });
        }

        if (! Schema::hasTable('user_follows')) {
            Schema::create('user_follows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('followed_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['follower_user_id', 'followed_user_id']);
            });
        }

        if (! Schema::hasTable('video_likes')) {
            Schema::create('video_likes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'video_id']);
            });
        }

        if (! Schema::hasTable('video_bookmarks')) {
            Schema::create('video_bookmarks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'video_id']);
            });
        }

        if (! Schema::hasTable('video_comments')) {
            Schema::create('video_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('video_comments')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('edited_at')->nullable();
                $table->timestamps();
                $table->index(['video_id', 'created_at']);
                $table->index(['parent_id', 'created_at']);
            });
        }

        $this->backfillImportedVideos();

        if (Schema::hasTable('schema_migrations')) {
            DB::table('schema_migrations')->insertOrIgnore([
                ['id' => '005_create_users_and_social_graph', 'applied_at' => now()->toISOString()],
                ['id' => '006_backfill_imported_videos', 'applied_at' => now()->toISOString()],
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive drops against an existing database.
    }

    private function backfillImportedVideos(): void
    {
        if (! Schema::hasTable('videos') || ! Schema::hasTable('tweets')) {
            return;
        }

        $rows = DB::select(
            <<<'SQL'
                SELECT
                    t.tweet_id AS tweetId,
                    t.source_id AS sourceId,
                    t.text AS caption,
                    t.poster_url AS posterUrl,
                    t.duration_text AS durationText,
                    COALESCE(t.posted_at, t.ingested_at) AS publishedAt,
                    (
                        SELECT asset.url
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.is_primary = 1
                        LIMIT 1
                    ) AS playbackUrl,
                    (
                        SELECT asset.url
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.content_type IN ('application/x-mpegURL', 'application/vnd.apple.mpegurl')
                        ORDER BY asset.sort_order ASC, asset.id ASC
                        LIMIT 1
                    ) AS hlsUrl,
                    (
                        SELECT asset.width
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.is_primary = 1
                        LIMIT 1
                    ) AS mediaWidth,
                    (
                        SELECT asset.height
                        FROM media_assets asset
                        WHERE asset.tweet_id = t.tweet_id
                          AND asset.is_primary = 1
                        LIMIT 1
                    ) AS mediaHeight
                FROM tweets t
                WHERE t.status IN ('resolved', 'external_only')
            SQL
        );

        $timestamp = now();

        foreach ($rows as $row) {
            $payload = [
                'origin' => 'x_tweet',
                'source_id' => (int) $row->sourceId,
                'uploader_user_id' => null,
                'title' => null,
                'caption' => $row->caption,
                'description' => null,
                'storage_disk' => null,
                'storage_path' => null,
                'poster_url' => $row->posterUrl,
                'playback_url' => $row->playbackUrl,
                'hls_url' => $row->hlsUrl,
                'duration_text' => $row->durationText,
                'duration_seconds' => ShortVideoData::parseDurationTextToSeconds($row->durationText),
                'width' => $row->mediaWidth !== null ? (int) $row->mediaWidth : null,
                'height' => $row->mediaHeight !== null ? (int) $row->mediaHeight : null,
                'visibility' => 'public',
                'status' => 'published',
                'published_at' => $row->publishedAt,
                'updated_at' => $timestamp,
            ];

            $existingId = DB::table('videos')->where('tweet_id', $row->tweetId)->value('id');

            if ($existingId !== null) {
                DB::table('videos')->where('id', $existingId)->update($payload);

                continue;
            }

            DB::table('videos')->insert($payload + [
                'tweet_id' => $row->tweetId,
                'created_at' => $timestamp,
            ]);
        }
    }
};
