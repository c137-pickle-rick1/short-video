<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$intervalMinutes = max(1, min(59, (int) config('shortvideo.scrape_interval_minutes', 10)));

Schedule::call(function (): void {
    Artisan::call('shortvideo:crawl-once', ['--json' => true]);
    Artisan::call('shortvideo:backfill-avatars', ['--limit' => 50, '--json' => true]);
})
    ->name('shortvideo:crawl-cycle')
    ->cron("*/{$intervalMinutes} * * * *")
    ->withoutOverlapping();
