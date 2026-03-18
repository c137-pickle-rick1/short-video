<?php

use App\ShortVideo\Support\ShortVideoPath;

return [
    'repo_root' => ShortVideoPath::repoRoot(),
    'port' => (int) env('PORT', 3000),
    'db_path' => ShortVideoPath::resolve(env('DB_PATH'), './data/app.db'),
    'browser_profile_dir' => ShortVideoPath::resolve(env('BROWSER_PROFILE_DIR'), './data/browser-profile'),
    'storage_state_path' => ShortVideoPath::resolve(env('X_STORAGE_STATE_PATH'), './data/x-storage-state.json'),
    'source_config_path' => ShortVideoPath::resolve(env('SOURCE_CONFIG_PATH'), './config/sources.json'),
    'dev_viewer_username' => env('SHORTVIDEO_DEV_VIEWER_USERNAME', ''),
    'scrape_interval_minutes' => max(1, (int) env('SCRAPE_INTERVAL_MINUTES', 10)),
    'discovery_mode' => in_array(env('DISCOVERY_MODE', 'hybrid'), ['hybrid', 'jina', 'browser'], true)
        ? env('DISCOVERY_MODE', 'hybrid')
        : 'hybrid',
    'media_proxy_timeout_ms' => max(1, (int) env('MEDIA_PROXY_TIMEOUT_MS', 15000)),
    'sidecar' => [
        'node_binary' => env('SIDECAR_NODE_BINARY', 'node'),
        'cli_path' => ShortVideoPath::resolve(env('SIDECAR_CLI_PATH'), './sidecar/cli.js'),
    ],
    'runtime_keys' => [
        'backoff_reason' => 'backoff_reason',
        'backoff_until' => 'backoff_until',
        'crawl_lock_owner' => 'crawl_lock_owner',
        'crawl_lock_until' => 'crawl_lock_until',
    ],
];
