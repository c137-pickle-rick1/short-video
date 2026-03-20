<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyPrepareCommandTest extends TestCase
{
    public function test_prepare_legacy_db_command_builds_legacy_schema_before_migrate(): void
    {
        $this->useUnmigratedShortVideoDatabase();

        $this->artisan('shortvideo:prepare-legacy-db --json')->assertExitCode(0);

        $schema = DB::connection()->getSchemaBuilder();

        $this->assertTrue($schema->hasTable('sources'));
        $this->assertTrue($schema->hasTable('tweets'));
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('videos'));
        $this->assertTrue($schema->hasTable('user_external_accounts'));
        $this->assertTrue($schema->hasColumn('sources', 'user_id'));
        $this->assertTrue($schema->hasColumn('users', 'account_type'));
        $this->assertTrue($schema->hasColumn('video_comments', 'reply_to_comment_id'));
        $this->assertTrue($schema->hasColumn('video_comments', 'deleted_at'));

        $this->artisan('migrate --force')->assertExitCode(0);

        $this->assertTrue($schema->hasTable('sessions'));
        $this->assertTrue($schema->hasTable('video_views'));
    }
}
