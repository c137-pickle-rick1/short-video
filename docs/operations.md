# 运维 Runbook

## 目的

这份文档用于回答四类问题：

1. 服务应该按什么顺序启动
2. 数据库迁移应该怎么执行
3. 抓取登录态或 backoff 出问题时怎么排查
4. SQLite 数据如何备份和恢复

## 关键入口

- Web 服务入口：`server/index.js`
- 数据库迁移入口：`server/migrate.js`
- 登录态维护入口：`server/auth.js`
- 一次性抓取入口：`server/crawlOnce.js`
- pending 解析入口：`server/resolvePending.js`
- 头像回填入口：`server/backfillAvatars.js`

## 环境变量

常用变量如下：

- `PORT`
- `DB_PATH`
- `BROWSER_PROFILE_DIR`
- `X_STORAGE_STATE_PATH`
- `SCRAPE_INTERVAL_MINUTES`
- `DISCOVERY_MODE`
- `MEDIA_PROXY_TIMEOUT_MS`
- `RUN_MIGRATIONS_ON_BOOT`

默认样例见 `.env.example`。

## 启动顺序

### 本地开发

```bash
npm install
npm run playwright:install
npm run auth
npm run db:migrate
npm run dev
```

说明：

- `npm run auth` 只需要在登录态缺失或失效时执行
- `npm run dev` 会在启动前自动构建 vendor 和样式

### 更接近生产的手动启动顺序

推荐把迁移和服务启动分开：

```bash
RUN_MIGRATIONS_ON_BOOT=false npm run db:migrate
RUN_MIGRATIONS_ON_BOOT=false node server/index.js
```

这样能避免“服务进程半启动时顺带修改 schema”。

## 运行时检查

### 健康接口

接口：

- `GET /api/health`

返回字段：

- `ok`
- `backoffUntil`
- `backoffReason`

示例：

```json
{
  "ok": true,
  "backoffUntil": null,
  "backoffReason": null
}
```

排查重点：

- `backoffUntil` 非空：当前处于 backoff 窗口
- `backoffReason === "rate_limited"`：上游被限流
- `backoffReason === "auth_required"`：通常意味着登录态需要刷新

### 常用手动命令

只执行一轮 discover + resolve：

```bash
npm run crawl:once
```

只处理 pending tweet：

```bash
npm run resolve:pending
```

只回填缺失头像：

```bash
npm run backfill:avatars
```

这些命令不会启动 HTTP server，也不会启动 interval scheduler。

## 登录态维护

### 何时需要重新登录

常见信号：

- `/api/health` 返回 `backoffReason=auth_required`
- 解析阶段持续失败
- X 页面结构正常，但 resolver 无法拿到目标 tweet

### 操作步骤

```bash
npm run auth
```

然后：

1. 在打开的浏览器中登录 X
2. 保持窗口打开直到确认主页可正常访问
3. `Ctrl+C` 结束进程

说明：

- 浏览器 profile 默认保存在 `BROWSER_PROFILE_DIR`
- 登录态依赖本地 profile，清空该目录会导致重新登录

## Backoff 排查

### `rate_limited`

处理方式：

1. 查看 `/api/health`
2. 等待 `backoffUntil`
3. 先执行一次 `npm run crawl:once` 验证恢复情况

不建议：

- 在 backoff 窗口内反复重试 CLI
- 同时启动多个实例争抢同一个上游配额

### `auth_required`

处理方式：

1. 运行 `npm run auth`
2. 完成登录
3. 重新执行 `npm run resolve:pending` 或 `npm run crawl:once`

## 数据库迁移

### 显式迁移

```bash
npm run db:migrate
```

行为：

- 执行 `schema_migrations` 中尚未应用的 migration
- 输出本次新应用的 migration id

### 启动时自动迁移

默认：

```bash
RUN_MIGRATIONS_ON_BOOT=true
```

建议：

- 开发环境可以保持默认
- 更正式的部署流程建议设为 `false`，先显式迁移，再启动服务

## SQLite 备份与恢复

### 当前存储特征

- 数据库使用 SQLite
- `journal_mode = WAL`

默认数据库路径：

- `./data/app.db`

### 备份建议

最稳妥的方式是先停进程，再备份。

如果进程还在运行，不要只复制 `app.db`，至少要一并处理：

- `app.db`
- `app.db-wal`
- `app.db-shm`

### 手动备份示例

先停服务后：

```bash
cp data/app.db backup/app.db
```

如果服务未停，不建议只复制单文件。

### 恢复建议

1. 停止服务
2. 还原数据库文件
3. 执行一次 `npm run db:migrate`
4. 启动服务

## 静态资源重建

以下场景需要重建前端静态资产：

- 升级 `plyr`、`colcade`、字体或 icon 包
- 修改 `src/styles.css`
- 清空了 `public/vendor`

命令：

```bash
npm run build:vendor
npm run build:styles
```

## 故障排查清单

### 页面能开，但没有内容

检查顺序：

1. `config/sources.json` 是否存在有效来源
2. 执行 `npm run crawl:once`
3. 查看 `/api/health` 的 backoff 信息
4. 看数据库里是否存在 `pending` 或 `resolved` tweet

### API 正常，但视频无法播放

优先检查：

1. `/api/media/:tweetId` 是否返回 200/206
2. 上游媒体链接是否仍然有效
3. `MEDIA_PROXY_TIMEOUT_MS` 是否过小

### 服务启动失败

优先检查：

1. `DB_PATH` 所在目录是否可写
2. Playwright Chromium 是否已安装
3. `.env` 中的配置是否为合法值
4. `npm run db:migrate` 是否可以单独通过

## 当前已知限制

- scheduler 仍然是进程内 `setInterval`
- `backoff` 状态是内存态，进程重启后不会持久化
- 没有内置多实例锁
- 没有单独的部署脚本或 systemd/pm2 配置

如果后续补部署自动化，应该优先把这些限制一起纳入设计。
