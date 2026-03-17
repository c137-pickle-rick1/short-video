# Operations

## 启动顺序

1. 安装依赖

```bash
npm install
npm run php:install
```

2. 执行迁移

```bash
npm run db:migrate
```

3. 如需刷新登录态

```bash
npm run auth
```

4. 启动服务

```bash
npm run dev
```

## 常用命令

```bash
npm run auth
npm run db:migrate
npm run crawl:once
npm run resolve:pending
npm run backfill:avatars
npm run build:vendor
npm run build:styles
npm test
```

## 关键路径

- 来源配置：`config/sources.json`
- 数据库：`data/app.db`
- 浏览器 profile：`data/browser-profile`
- storage state：`data/x-storage-state.json`

## 常见操作

### 只执行一次抓取

```bash
npm run crawl:once
```

### 只处理 pending

```bash
npm run resolve:pending
```

### 回填头像

```bash
npm run backfill:avatars
```

### 重建前端静态资源

```bash
npm run build:vendor
npm run build:styles
```

## 排障

### 页面静态资源不一致

先重建 `laravel/public`：

```bash
npm run build:vendor
npm run build:styles
```

### 登录态失效

重新打开 sidecar 认证浏览器：

```bash
npm run auth
```

### SQLite 被锁或抓取暂停

先看：

- `runtime_states` 中的 backoff / crawl lock
- `crawl_runs` 最近一次记录
- `laravel/storage/logs/laravel.log`

必要时重跑：

```bash
npm run crawl:once
```
