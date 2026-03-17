<?php

namespace App\Providers;

use App\ShortVideo\Repositories\ShortVideoRepository;
use App\ShortVideo\Services\RuntimeStateStore;
use GuzzleHttp\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private static bool $bootMigrationsRan = false;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, static fn () => new Client([
            'http_errors' => false,
            'stream' => true,
        ]));

        $this->app->bind(ShortVideoRepository::class, static function (Application $app): ShortVideoRepository {
            return new ShortVideoRepository($app['db']->connection());
        });

        $this->app->bind(RuntimeStateStore::class, static function (Application $app): RuntimeStateStore {
            return new RuntimeStateStore($app['db']->connection());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::connection()->statement('PRAGMA foreign_keys = ON');
        DB::connection()->statement('PRAGMA journal_mode = WAL');

        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        if (! config('shortvideo.run_migrations_on_boot') || self::$bootMigrationsRan) {
            return;
        }

        self::$bootMigrationsRan = true;
        $lockFile = storage_path('framework/cache/shortvideo-migrate.lock');
        $handle = fopen($lockFile, 'c+');
        if (! is_resource($handle)) {
            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return;
            }

            Artisan::call('migrate', ['--force' => true]);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
