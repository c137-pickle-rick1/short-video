# 静态 HTML 交付说明

## 目的

这套目录用于给其他协作方交付纯静态页面，只包含：

- 静态 `.html` 页面
- 共享样式 `assets/styles.css`
- 字体、图标、Plyr 样式等静态资源

它不依赖 Laravel 路由、Blade 运行时、Vite 开发服务，也不包含交互脚本。

## 目录结构

- `delivery/static-html/export.php`
  静态导出脚本
- `delivery/static-html/*.html`
  已生成的交付页面
- `delivery/static-html/assets/`
  共享样式和 vendor 资源

## 日常使用

在仓库根目录执行：

```bash
npm run export:static
```

本命令会：

- 重新编译静态交付用的 Tailwind 样式
- 刷新 `assets/` 下的共享资源
- 重新生成全部静态 HTML 页面

本地预览：

```bash
npm run preview:static
```

然后在浏览器打开：

- `http://127.0.0.1:8765/index.html`

停止预览服务时按 `Ctrl+C`。

## 实现原则

本次交付采用“真实渲染快照”方案，不手工重写页面：

1. `export.php` bootstrap Laravel 应用。
2. 通过 `Http Kernel` 直接请求目标页面。
3. 对需要登录态的页面，使用 `Auth::onceUsingId(...)` 渲染指定用户视图。
4. 将渲染结果保存成静态 `.html`。
5. 对导出后的 HTML 做统一后处理。

后处理包含：

- 删除 `<script>`、Vite 资源、CSRF meta
- 将站内链接改写成静态 `.html`
- 将表单改为静态占位容器，保留视觉结构
- 将字体、图标、Plyr 样式改写到 `assets/` 相对路径
- 如遇本地管理头像 `/avatars/...`，复制到交付目录并改写引用

## 当前导出覆盖

当前脚本会导出以下页面：

- 首页、探索、榜单、登录页
- 注册页、忘记密码页
- 订阅页的游客态、空关注态、选择账号态、已选账号态
- 公开主页示例页
- 公开主页和个人主页的关注/粉丝 modal
- 个人页的编辑资料、上传视频 modal
- 登录用户个人中心的 overview、creator、history、bookmarks、interactions
- `/me/history`、`/me/bookmarks`、`/me/interactions`
- 视频详情示例页
- 视频详情带评论示例页 `video-273-comments.html`
- 首页打开视频弹窗的带评论示例页 `index-video-273-comments-modal.html`

如果后续新增了新的交付页面或新的关键状态，需要同步更新：

- `delivery/static-html/export.php` 里的 `PAGES`
- 站内链接改写规则

## 已知限制

- 这是静态交付包，不包含真实交互逻辑。
- 远程图片、封面、视频地址默认保留外链，不做全量媒体镜像。
- 某些页面中的未覆盖站内链接会退化为 `#`，表示当前交付包未单独导出该目标页。
- 如果业务页面结构发生明显变化，重新导出后应抽查关键页面。

## 建议验收方式

每次重新生成后，至少检查：

- `index.html`
- `subscriptions-selection.html`
- `profile-own-bookmarks.html`
- `video-278.html`
- `video-273-comments.html`
- `index-video-273-comments-modal.html`

重点确认：

- 样式、字体、图标是否正常
- 页面主导航是否能在静态包内跳转
- 页面内容是否为空壳
- HTML 中是否还残留脚本、Vite 资源、CSRF token
- 带评论版本里的评论数量、回复层级和文案是否完整

## 评论样本说明

- `video-273-comments.html` 和 `index-video-273-comments-modal.html` 使用导出脚本内置的模拟评论 fixture。
- 这组评论不会写入数据库，也不依赖当前环境里是否真的存在评论数据。
- 两个页面复用同一组评论与回复，用于交付时展示“非空评论区”的真实效果。

## 维护建议

当你以后修改了 Blade、Tailwind class、页面结构或导出映射时，推荐流程：

```bash
npm run export:static
npm run preview:static
```

如果只是查看交付效果，不需要启动 Laravel 的 `serve` 或 Vite。
