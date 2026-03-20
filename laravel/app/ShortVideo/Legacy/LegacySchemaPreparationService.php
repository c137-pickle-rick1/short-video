<?php

namespace App\ShortVideo\Legacy;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

final class LegacySchemaPreparationService
{
    public function __construct(
        private readonly ConnectionInterface $db
    ) {}

    /**
     * @return array<string, array<string, int>>
     */
    public function run(): array
    {
        return [
            'bootstrap' => $this->ensureBootstrapSchema(),
            'socialGraph' => $this->ensureSocialGraphSchema(),
            'commentThreading' => $this->ensureVideoCommentThreadingSchema(),
            'externalCreators' => $this->ensureExternalCreatorSchema(),
        ];
    }

    /**
     * @return array{tablesCreated:int,columnsAdded:int,indexesEnsured:int}
     */
    public function ensureBootstrapSchema(): array
    {
        $tablesCreated = 0;
        $columnsAdded = 0;

        $tablesCreated += $this->ensureTable('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('handle')->unique();
            $table->integer('enabled')->default(1);
            $table->text('last_discovered_at')->nullable();
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('tweets', function (Blueprint $table): void {
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
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('media_assets', function (Blueprint $table): void {
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
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('crawl_runs', function (Blueprint $table): void {
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
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('runtime_states', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->text('updated_at');
        }) ? 1 : 0;

        $columnsAdded += $this->ensureColumn('tweets', 'author_avatar_url', function (Blueprint $table): void {
            $table->text('author_avatar_url')->nullable();
        }) ? 1 : 0;

        $columnsAdded += $this->ensureColumn('tweets', 'duration_text', function (Blueprint $table): void {
            $table->text('duration_text')->nullable();
        }) ? 1 : 0;

        $columnsAdded += $this->ensureSourceProviderUserIdColumn() ? 1 : 0;
        $columnsAdded += $this->ensureSourceLastSeenTweetIdColumn() ? 1 : 0;

        $this->db->statement('CREATE INDEX IF NOT EXISTS idx_tweets_status_sort ON tweets(status, posted_at DESC, ingested_at DESC, tweet_id DESC)');
        $this->db->statement('CREATE INDEX IF NOT EXISTS idx_tweets_source_status ON tweets(source_id, status)');
        $this->db->statement('CREATE INDEX IF NOT EXISTS idx_media_assets_primary ON media_assets(tweet_id, is_primary)');
        $this->db->statement('CREATE INDEX IF NOT EXISTS idx_crawl_runs_phase_source ON crawl_runs(phase, source_id, started_at DESC)');

        return [
            'tablesCreated' => $tablesCreated,
            'columnsAdded' => $columnsAdded,
            'indexesEnsured' => 4,
        ];
    }

    /**
     * @return array{columnsAdded:int}
     */
    public function ensureSourceApiCursorColumns(): array
    {
        $columnsAdded = 0;

        $columnsAdded += $this->ensureSourceProviderUserIdColumn() ? 1 : 0;
        $columnsAdded += $this->ensureSourceLastSeenTweetIdColumn() ? 1 : 0;

        return [
            'columnsAdded' => $columnsAdded,
        ];
    }

    /**
     * @return array{tablesCreated:int}
     */
    public function ensureSocialGraphSchema(): array
    {
        $tablesCreated = 0;

        $tablesCreated += $this->ensureTable('users', function (Blueprint $table): void {
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
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('user_source_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('relationship')->default('owner');
            $table->boolean('is_primary')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'source_id']);
            $table->unique('source_id');
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('source_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'source_id']);
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('videos', function (Blueprint $table): void {
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
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('user_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_user_id', 'followed_user_id']);
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('video_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('video_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('video_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('video_comments')->cascadeOnDelete();
            $table->foreignId('reply_to_comment_id')->nullable()->constrained('video_comments')->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['video_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
        }) ? 1 : 0;

        return [
            'tablesCreated' => $tablesCreated,
        ];
    }

    /**
     * @return array{columnsAdded:int,indexesEnsured:int}
     */
    public function ensureVideoCommentThreadingSchema(): array
    {
        $columnsAdded = 0;

        $columnsAdded += $this->ensureVideoCommentReplyToCommentIdColumn() ? 1 : 0;
        $columnsAdded += $this->ensureVideoCommentDeletedAtColumn() ? 1 : 0;

        $this->db->statement(
            'CREATE INDEX IF NOT EXISTS idx_video_comments_video_deleted_parent_created ON video_comments(video_id, deleted_at, parent_id, created_at, id)'
        );
        $this->db->statement(
            'CREATE INDEX IF NOT EXISTS idx_video_comments_reply_to_created ON video_comments(reply_to_comment_id, created_at, id)'
        );

        return [
            'columnsAdded' => $columnsAdded,
            'indexesEnsured' => 2,
        ];
    }

    /**
     * @return array{tablesCreated:int,columnsAdded:int,usersUpdated:int}
     */
    public function ensureExternalCreatorSchema(): array
    {
        $tablesCreated = 0;
        $columnsAdded = 0;

        $columnsAdded += $this->ensureColumn('users', 'account_type', function (Blueprint $table): void {
            $table->string('account_type')->default('local');
        }) ? 1 : 0;

        $tablesCreated += $this->ensureTable('user_external_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id')->nullable();
            $table->string('handle');
            $table->text('profile_url')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'handle']);
        }) ? 1 : 0;

        $columnsAdded += $this->ensureSourceUserIdColumn() ? 1 : 0;

        $usersUpdated = 0;
        if ($this->schema()->hasTable('users')) {
            $usersUpdated = $this->db->table('users')->whereNull('account_type')->update(['account_type' => 'local']);
        }

        return [
            'tablesCreated' => $tablesCreated,
            'columnsAdded' => $columnsAdded,
            'usersUpdated' => $usersUpdated,
        ];
    }

    private function ensureTable(string $table, Closure $definition): bool
    {
        if ($this->schema()->hasTable($table)) {
            return false;
        }

        $this->schema()->create($table, $definition);

        return true;
    }

    private function ensureColumn(string $table, string $column, Closure $definition): bool
    {
        if (! $this->schema()->hasTable($table) || $this->schema()->hasColumn($table, $column)) {
            return false;
        }

        $this->schema()->table($table, $definition);

        return true;
    }

    private function ensureSourceUserIdColumn(): bool
    {
        if (! $this->schema()->hasTable('sources') || $this->schema()->hasColumn('sources', 'user_id')) {
            return false;
        }

        if ($this->db->getDriverName() === 'sqlite') {
            $this->db->statement('ALTER TABLE sources ADD COLUMN user_id INTEGER NULL');

            return true;
        }

        $this->schema()->table('sources', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable();
        });

        return true;
    }

    private function ensureSourceProviderUserIdColumn(): bool
    {
        if (! $this->schema()->hasTable('sources') || $this->schema()->hasColumn('sources', 'provider_user_id')) {
            return false;
        }

        if ($this->db->getDriverName() === 'sqlite') {
            $this->db->statement('ALTER TABLE sources ADD COLUMN provider_user_id TEXT NULL');

            return true;
        }

        $this->schema()->table('sources', function (Blueprint $table): void {
            $table->string('provider_user_id')->nullable();
        });

        return true;
    }

    private function ensureSourceLastSeenTweetIdColumn(): bool
    {
        if (! $this->schema()->hasTable('sources') || $this->schema()->hasColumn('sources', 'last_seen_tweet_id')) {
            return false;
        }

        if ($this->db->getDriverName() === 'sqlite') {
            $this->db->statement('ALTER TABLE sources ADD COLUMN last_seen_tweet_id TEXT NULL');

            return true;
        }

        $this->schema()->table('sources', function (Blueprint $table): void {
            $table->string('last_seen_tweet_id')->nullable();
        });

        return true;
    }

    private function ensureVideoCommentReplyToCommentIdColumn(): bool
    {
        if (! $this->schema()->hasTable('video_comments') || $this->schema()->hasColumn('video_comments', 'reply_to_comment_id')) {
            return false;
        }

        if ($this->db->getDriverName() === 'sqlite') {
            $this->db->statement('ALTER TABLE video_comments ADD COLUMN reply_to_comment_id INTEGER NULL');

            return true;
        }

        $this->schema()->table('video_comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('reply_to_comment_id')->nullable();
        });

        return true;
    }

    private function ensureVideoCommentDeletedAtColumn(): bool
    {
        if (! $this->schema()->hasTable('video_comments') || $this->schema()->hasColumn('video_comments', 'deleted_at')) {
            return false;
        }

        if ($this->db->getDriverName() === 'sqlite') {
            $this->db->statement('ALTER TABLE video_comments ADD COLUMN deleted_at DATETIME NULL');

            return true;
        }

        $this->schema()->table('video_comments', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable();
        });

        return true;
    }

    private function schema(): Builder
    {
        return $this->db->getSchemaBuilder();
    }
}
