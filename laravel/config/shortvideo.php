<?php

use App\ShortVideo\Support\ShortVideoPath;

return [
    'repo_root' => ShortVideoPath::repoRoot(),
    'port' => (int) env('PORT', 3000),
    'db_path' => ShortVideoPath::resolve(env('DB_PATH'), './data/app.db'),
    'browser_cdp_url' => env('BROWSER_CDP_URL'),
    'browser_profile_dir' => ShortVideoPath::resolve(env('BROWSER_PROFILE_DIR'), './data/browser-profile'),
    'storage_state_path' => ShortVideoPath::resolve(env('X_STORAGE_STATE_PATH'), './data/x-storage-state.json'),
    'source_config_path' => ShortVideoPath::resolve(env('SOURCE_CONFIG_PATH'), './config/sources.json'),
    'dev_viewer_username' => env('SHORTVIDEO_DEV_VIEWER_USERNAME', ''),
    'scrape_interval_minutes' => max(1, (int) env('SCRAPE_INTERVAL_MINUTES', 10)),
    'discovery_scroll_rounds' => max(1, (int) env('DISCOVERY_SCROLL_ROUNDS', 6)),
    'discovery_mode' => in_array(env('DISCOVERY_MODE', 'hybrid'), ['hybrid', 'jina', 'browser', 'api', 'api_hybrid'], true)
        ? env('DISCOVERY_MODE', 'hybrid')
        : 'hybrid',
    'x_api' => [
        'bearer_token' => env('X_API_BEARER_TOKEN'),
        'max_pages' => max(1, min(20, (int) env('X_API_MAX_PAGES', 4))),
        'page_size' => max(5, min(100, (int) env('X_API_PAGE_SIZE', 100))),
        'include_replies' => filter_var(env('X_API_INCLUDE_REPLIES', false), FILTER_VALIDATE_BOOLEAN),
        'include_retweets' => filter_var(env('X_API_INCLUDE_RETWEETS', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'media_proxy_timeout_ms' => max(1, (int) env('MEDIA_PROXY_TIMEOUT_MS', 15000)),
    'feed_media_frame_mode' => in_array(env('SHORTVIDEO_FEED_MEDIA_FRAME_MODE', 'adaptive'), ['adaptive', '16:9'], true)
        ? env('SHORTVIDEO_FEED_MEDIA_FRAME_MODE', 'adaptive')
        : 'adaptive',
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
