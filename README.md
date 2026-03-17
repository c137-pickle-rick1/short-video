# short-video

一个用于抓取 X 视频、解析可播放媒体、写入 SQLite，并通过 Laravel SSR 首页和 JSON API 对外提供 feed 的项目。

当前仓库只保留两类实现：

- `laravel/`: 唯一的 Web / API / scheduler / crawl 主应用
- `sidecar/`: Node + Playwright / Jina sidecar，只负责 discovery、resolve 和认证浏览器

浏览器端源码统一放在 `laravel/public/`，不再维护根目录 `public/` / `shared/` 的镜像副本。

## 环境要求

- PHP 8.2+
- Composer
- Node.js 20+
- npm
- 已安装 Playwright Chromium：
  `npm run playwright:install`

## 快速开始

1. 安装 Node 依赖

```bash
npm install
```

2. 安装 Laravel 依赖

```bash
npm run php:install
```

3. 检查配置

- 环境变量样例在 `laravel/.env.example`
- 来源配置在 `config/sources.json`
- 默认数据库在 `data/app.db`

4. 执行数据库迁移

```bash
npm run db:migrate
```

5. 如果需要刷新 X 登录态，打开认证浏览器

```bash
npm run auth
```

6. 启动 Laravel Octane + Swoole

```bash
npm run dev
```

默认服务地址是 `http://127.0.0.1:3000`。

## 常用命令

- `npm run dev`: 启动 Laravel Octane + Swoole
- `npm run auth`: 打开 X 登录浏览器
- `npm run db:migrate`: 执行 Laravel 迁移
- `npm run crawl:once`: 执行一次 discover + resolve
- `npm run resolve:pending`: 只处理 pending tweet
- `npm run backfill:avatars`: 回填缺失头像
- `npm run build:vendor`: 同步本地 vendor 静态资源到 `laravel/public/vendor`
- `npm run build:styles`: 编译 Tailwind 样式到 `laravel/public/styles.css`
- `npm test`: 运行 Laravel 测试、sidecar 单测和浏览器 smoke test
- `npm run php:test`: 只运行 Laravel Feature 测试

## 目录概览

- `laravel/app`: Laravel 控制器、服务、仓储、命令
- `laravel/public`: 浏览器端源码和最终静态资源
- `laravel/resources/views`: Blade 页面
- `laravel/resources/shortvideo`: Tailwind 输入源文件
- `sidecar/lib`: Jina discovery、Playwright resolve、认证浏览器逻辑
- `sidecar/cli.js`: sidecar 命令入口
- `config/sources.json`: 抓取来源配置
- `data/`: SQLite、浏览器 profile、X storage state

## 架构与运维

- 架构说明：`docs/architecture.md`
- 运维说明：`docs/operations.md`
