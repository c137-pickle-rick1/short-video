<?php

namespace App\Providers;

use App\ShortVideo\Legacy\LegacyDatabaseUpgradeService;
use App\ShortVideo\Legacy\LegacySchemaPreparationService;
use App\ShortVideo\Repositories\CreatorIdentityRepository;
use App\ShortVideo\Repositories\EngagementRepository;
use App\ShortVideo\Repositories\FeedRepository;
use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Repositories\SocialGraphRepository;
use App\ShortVideo\Repositories\SourceRepository;
use App\ShortVideo\Services\RuntimeStateStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CreatorIdentityRepository::class, static function (Application $app): CreatorIdentityRepository {
            return new CreatorIdentityRepository($app['db']->connection());
        });

        $this->app->bind(SourceRepository::class, static function (Application $app): SourceRepository {
            return new SourceRepository(
                $app['db']->connection(),
                $app->make(CreatorIdentityRepository::class)
            );
        });

        $this->app->bind(FeedRepository::class, static function (Application $app): FeedRepository {
            return new FeedRepository($app['db']->connection());
        });

        $this->app->bind(EngagementRepository::class, static function (Application $app): EngagementRepository {
            return new EngagementRepository($app['db']->connection());
        });

        $this->app->bind(SocialGraphRepository::class, static function (Application $app): SocialGraphRepository {
            return new SocialGraphRepository($app['db']->connection());
        });

        $this->app->bind(LegacyDatabaseUpgradeService::class, static function (Application $app): LegacyDatabaseUpgradeService {
            return new LegacyDatabaseUpgradeService(
                $app['db']->connection(),
                $app->make(CreatorIdentityRepository::class)
            );
        });

        $this->app->bind(LegacySchemaPreparationService::class, static function (Application $app): LegacySchemaPreparationService {
            return new LegacySchemaPreparationService($app['db']->connection());
        });

        $this->app->bind(ShortVideoRepository::class, static function (Application $app): ShortVideoRepository {
            return new ShortVideoRepository(
                $app['db']->connection(),
                $app->make(SourceRepository::class),
                $app->make(FeedRepository::class),
                $app->make(EngagementRepository::class),
                $app->make(SocialGraphRepository::class),
                $app->make(CreatorIdentityRepository::class)
            );
        });

        $this->app->bind(RuntimeStateStore::class, static function (Application $app): RuntimeStateStore {
            return new RuntimeStateStore($app['db']->connection());
        });
    }
}
