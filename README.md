# WordPress + Vibe Coding 独立站

WordPress 负责文章，Astro 负责前端，GitHub 保存代码并通过 GitHub Pages 发布静态网站。现在是本地开发环境；生产 WordPress 需要另外托管。

## 本地使用（Windows）

需要 Node.js 22.12+（本次使用 Node.js 24）。项目自带便携 npm，不需要全局安装 PHP、MySQL 或 Docker。

首次安装：

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup.ps1
```

启动前后台：双击 `start.cmd`，或运行：

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-dev.ps1
```

- 前端：http://127.0.0.1:4321
- 后台：http://127.0.0.1:9400/wp-admin/
- Playground 本地开发默认账号：admin，密码：password。仅用于本机开发。
- WordPress 文件、媒体和 SQLite 数据在 `.local/wordpress/`，已排除 Git。首次启动会下载 WordPress 和 PHP WASM，需联网。
- 停止后内容保存在本地。备份整个 `.local/wordpress/` 前先停止后台。

发布或修改 WordPress 文章后，同步到前端：

```powershell
node .tools/package/bin/npm-cli.js run sync
```

生成生产静态文件：

```powershell
node .tools/package/bin/npm-cli.js run build
```

输出目录为 `dist/`。手动检查环境：`node scripts/doctor.mjs`。

## 用自然语言开发

直接告诉 Codex 想修改的效果，例如“把首页做成英文工业品牌官网，加产品分类和询盘入口”。页面入口是 `src/pages/index.astro`，文章页是 `src/pages/posts/[id].astro`。内容来自 WordPress 已发布文章，构建后访客不直接访问本地后台。

## GitHub 仓库

本项目使用 https://github.com/XB0121/My-Site ，默认分支为 main。旧演示仓库不参与本项目。

GitHub Pages 项目地址为 `https://xb0121.github.io/My-Site/`，启用部署后生效。当前 GitHub 账户的免费方案需要公开仓库才能使用 Pages；保留私有仓库时，可升级 GitHub 方案或选用支持私有源码的其他托管平台。

仓库 Settings → Pages → Source 选择 **GitHub Actions**。已有 `.github/workflows/deploy.yml`，推送 main 自动构建部署，并支持手动 Run workflow。

本地后台无法被 GitHub Actions 访问。当前流程会部署已同步并提交的 `src/data/posts.json`。图片如果仍指向本地 WordPress，外部访客无法访问；上线前需要把 WordPress 和媒体迁移到 HTTPS 主机。

线上后台准备好后，在仓库 Settings → Secrets and variables → Actions → Variables 添加 `WORDPRESS_URL=https://你的后台域名`。构建将读取该后台的公开文章；拉取失败会终止部署。生产前端从 GitHub Pages 访问，不需要 PHP。

WordPress 修改不会自行触发 GitHub 构建：初期手动运行工作流，之后可接入发布 webhook，触发 `repository_dispatch` 的 `wordpress-update` 事件。不要把 GitHub token 放进前端代码。

## 当前范围

这是内容网站的开发底座，不包含购物车、支付、会员或表单服务。如果需要 WooCommerce 等动态功能，需要进一步选择配套托管和接口方案。
