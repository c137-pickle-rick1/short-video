# Architecture

## 运行分层

### Laravel 主应用

`laravel/` 负责：

- `/` SSR 首页
- `/api/feed`、`/api/sources`、`/api/stats`、`/api/media/{tweetId}`、`/api/health`
- `shortvideo:*` Artisan 命令
- scheduler 与共享运行时状态
- SQLite 读写与迁移

### Sidecar

`sidecar/` 负责：

- Jina discovery
- Playwright resolve
- 认证浏览器

Laravel 通过 `App\ShortVideo\Services\SidecarClient` 调用 `sidecar/cli.js`，不再存在第二套 Node Web 应用。

### 浏览器端

浏览器端源码统一在 `laravel/public/`：

- `app.js`: hydration 入口
- `app/detailModal.js`
- `app/feedGrid.js`
- `render.js`
- `videoPreview.js`
- `shared/feed/render/*`
- `vendor/*`
- `styles.css`

Tailwind 输入文件在 `laravel/resources/shortvideo/styles.css`，编译结果写回 `laravel/public/styles.css`。

## 数据与状态

- 来源配置：`config/sources.json`
- SQLite：`data/app.db`
- 浏览器 profile：`data/browser-profile`
- X storage state：`data/x-storage-state.json`

Laravel migration 负责维护：

- `sources`
- `tweets`
- `media_assets`
- `crawl_runs`
- `schema_migrations`
- `runtime_states`

`runtime_states` 用于共享 backoff 和 crawl 锁，不依赖单进程内存。

## 请求与抓取链路

### 首页 / Feed

1. 浏览器请求 Laravel 首页
2. `HomeController` 读取 feed，并通过 Blade + `HomePageRenderer` 输出 SSR
3. 浏览器 hydration 后继续从 `/api/feed` 分页加载

### 媒体播放

1. feed item 返回 `videoUrl` 作为 `/api/media/{tweetId}` 代理地址
2. 如有 `hlsUrl`，浏览器优先直连 HLS
3. 失败时回退到 Laravel 媒体代理

### Crawl

1. `shortvideo:sync-sources` 同步 `config/sources.json`
2. `shortvideo:crawl-once` 串行执行 discover + resolve
3. `shortvideo:backfill-avatars` 处理缺失头像
4. scheduler 在 `laravel/routes/console.php` 中按间隔触发

## 设计约束

- Laravel 是唯一主应用，不保留第二套 HTTP/SSR/runtime 实现
- Node 只保留 sidecar，不持有数据库、HTTP 或 scheduler 逻辑
- 浏览器源码只保留 `laravel/public` 一份，不再镜像到根目录
- 顶层 npm 命令保留原有名字，但统一转发到 Laravel/sidecar 实现
