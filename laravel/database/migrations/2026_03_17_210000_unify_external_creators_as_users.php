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
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'account_type')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('account_type')->default('local');
            });
        }

        if (! Schema::hasTable('user_external_accounts')) {
            Schema::create('user_external_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('provider');
                $table->string('provider_user_id')->nullable();
                $table->string('handle');
                $table->text('profile_url')->nullable();
                $table->text('raw_payload')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'handle']);
            });
        }

        if (Schema::hasTable('sources') && ! Schema::hasColumn('sources', 'user_id')) {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('ALTER TABLE sources ADD COLUMN user_id INTEGER NULL');
            } else {
                Schema::table('sources', function (Blueprint $table): void {
                    $table->unsignedBigInteger('user_id')->nullable();
                });
            }
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->whereNull('account_type')->update(['account_type' => 'local']);
        }

        $this->backfillExternalCreators();

        if (Schema::hasTable('schema_migrations')) {
            DB::table('schema_migrations')->insertOrIgnore([
                ['id' => '007_unify_external_creators_as_users', 'applied_at' => now()->toISOString()],
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive drops against an existing database.
    }

    private function backfillExternalCreators(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('sources') || ! Schema::hasTable('user_external_accounts')) {
            return;
        }

        $sources = DB::select('SELECT id, handle, user_id AS userId FROM sources ORDER BY id ASC');
        foreach ($sources as $source) {
            $sourceId = (int) $source->id;
            $handle = ShortVideoData::normalizeHandle((string) $source->handle);
            if ($handle === '') {
                continue;
            }

            $userId = $this->ensureExternalCreatorUser($handle, '@'.$handle, null);
            if ($userId !== null) {
                DB::table('sources')->where('id', $sourceId)->update(['user_id' => $userId]);
            }
        }

        $tweets = DB::select(
            <<<'SQL'
                SELECT
                    t.tweet_id AS tweetId,
                    t.author_handle AS authorHandle,
                    t.author_name AS authorName,
                    t.author_avatar_url AS authorAvatarUrl,
                    s.id AS sourceId,
                    s.handle AS sourceHandle
                FROM tweets t
                JOIN sources s ON s.id = t.source_id
                ORDER BY t.tweet_id ASC
            SQL
        );

        foreach ($tweets as $tweet) {
            $handle = ShortVideoData::normalizeHandle($tweet->authorHandle ?: $tweet->sourceHandle);
            if ($handle === '') {
                continue;
            }

            $userId = $this->ensureExternalCreatorUser(
                $handle,
                is_string($tweet->authorName) && trim($tweet->authorName) !== '' ? trim($tweet->authorName) : '@'.$handle,
                is_string($tweet->authorAvatarUrl) && trim($tweet->authorAvatarUrl) !== '' ? trim($tweet->authorAvatarUrl) : null
            );

            if ($userId === null) {
                continue;
            }

            if (ShortVideoData::normalizeHandle((string) $tweet->sourceHandle) === $handle) {
                DB::table('sources')->where('id', (int) $tweet->sourceId)->update(['user_id' => $userId]);
            }

            if (Schema::hasTable('videos')) {
                DB::table('videos')->where('tweet_id', (string) $tweet->tweetId)->update(['uploader_user_id' => $userId]);
            }
        }
    }

    private function ensureExternalCreatorUser(string $handle, ?string $name, ?string $avatarUrl): ?int
    {
        $normalizedHandle = ShortVideoData::normalizeHandle($handle);
        if ($normalizedHandle === '') {
            return null;
        }

        $account = DB::table('user_external_accounts')
            ->where('provider', 'x')
            ->where('handle', $normalizedHandle)
            ->first();

        if ($account) {
            $userId = (int) $account->user_id;
            $this->refreshExternalCreatorUser($userId, $normalizedHandle, $name, $avatarUrl);

            return $userId;
        }

        $existingExternalUser = DB::table('users')
            ->where('username', $normalizedHandle)
            ->where('account_type', 'external_creator')
            ->first();

        if ($existingExternalUser) {
            $userId = (int) $existingExternalUser->id;
            $this->refreshExternalCreatorUser($userId, $normalizedHandle, $name, $avatarUrl);
        } else {
            $timestamp = now();
            $userId = (int) DB::table('users')->insertGetId([
                'name' => $name !== null && trim($name) !== '' ? trim($name) : '@'.$normalizedHandle,
                'username' => $this->generateUniqueExternalUsername($normalizedHandle),
                'account_type' => 'external_creator',
                'email' => null,
                'phone' => null,
                'email_verified_at' => null,
                'password' => null,
                'avatar_url' => $avatarUrl,
                'bio' => null,
                'last_login_at' => null,
                'remember_token' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $timestamp = now();
        DB::table('user_external_accounts')->updateOrInsert(
            [
                'provider' => 'x',
                'handle' => $normalizedHandle,
            ],
            [
                'user_id' => $userId,
                'provider_user_id' => null,
                'profile_url' => 'https://x.com/'.$normalizedHandle,
                'raw_payload' => null,
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ]
        );

        return $userId;
    }

    private function refreshExternalCreatorUser(int $userId, string $handle, ?string $name, ?string $avatarUrl): void
    {
        $user = DB::table('users')->select('account_type', 'name', 'avatar_url')->where('id', $userId)->first();
        if (! $user) {
            return;
        }

        $updates = [];
        if (($user->account_type ?? 'local') === 'external_creator') {
            $nextName = is_string($name) && trim($name) !== '' ? trim($name) : (($user->name ?? null) ?: '@'.$handle);
            $updates['name'] = $nextName;

            if (is_string($avatarUrl) && trim($avatarUrl) !== '') {
                $updates['avatar_url'] = trim($avatarUrl);
            }
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = now();
        DB::table('users')->where('id', $userId)->update($updates);
    }

    private function generateUniqueExternalUsername(string $handle): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '_', $handle) ?? '';
        $base = trim($base, '_');
        $base = $base !== '' ? substr($base, 0, 24) : 'x_user';

        $candidate = $base;
        $counter = 1;
        while (DB::table('users')->where('username', $candidate)->exists()) {
            $suffix = $counter === 1 ? '_x' : '_x'.$counter;
            $candidate = substr($base, 0, max(1, 32 - strlen($suffix))).$suffix;
            $counter++;
        }

        return $candidate;
    }
};
