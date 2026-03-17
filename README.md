# short-video

一个基于 Node.js 的单体应用，用来抓取 X 上的视频内容，解析可播放媒体，落库到 SQLite，并通过 SSR 首页和 JSON API 对外提供 feed。

## 环境要求

- Node.js 20+
- npm
- 已安装 Playwright Chromium 时体验更稳定：
  `npm run playwright:install`

## 快速开始

1. 安装依赖

```bash
npm install
```

2. 检查配置

- 环境变量样例在 `.env.example`
- 来源配置在 `config/sources.json`

3. 如果需要登录态，先打开认证浏览器

```bash
npm run auth
```

4. 执行数据库迁移

```bash
npm run db:migrate
```

5. 启动开发环境

```bash
npm run dev
```

启动前会自动构建：

- 本地 vendor 资产
- Tailwind 样式

默认服务地址是 `http://localhost:3000`。

## 常用命令

- `npm run dev`: 启动 Web 服务和定时抓取
- `npm run crawl:once`: 只执行一次 discover + resolve，不启动 HTTP 和 scheduler
- `npm run resolve:pending`: 只处理 pending tweet
- `npm run backfill:avatars`: 回填缺失头像
- `npm run db:migrate`: 显式执行数据库迁移
- `npm run auth`: 打开 X 登录浏览器
- `npm test`: 运行单元、接口和浏览器 smoke test

## 目录概览

- `server/app`: runtime 装配
- `server/http`: API 和 SSR 路由
- `server/services`: 应用服务和抓取 phase
- `server/infra`: 外部系统适配层
- `server/db`: SQLite 读写和 migration
- `server/pages`: SSR 页面渲染
- `shared`: 前后端共享的 feed 映射和模板
- `public`: 浏览器端入口、样式和本地 vendor 静态资源

## 关键运行文件

- `server/index.js`: Web 服务入口
- `server/migrate.js`: 数据库迁移入口
- `server/auth.js`: X 登录态入口
- `config/sources.json`: 抓取来源列表

## 架构说明

详细分层、主链路和演进约束见：

- `docs/architecture.md`

## 运维说明

启动顺序、迁移、登录态刷新、backoff 排查和 SQLite 备份恢复见：

- `docs/operations.md`
