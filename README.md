# 百度收录增强版 (Baidu SEO Enhanced)

[![Version](https://img.shields.io/badge/version-1.0.3-blue.svg)](https://github.com/shaobing2024/corepress-baidu-sitemap)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-green.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2-orange.svg)](LICENSE)

WordPress 百度搜索引擎优化插件。自动生成百度兼容的 XML/TXT 双格式站点地图，支持百度站长平台 API 主动推送，加速百度收录。

---

## 功能特性

### 站点地图
- **XML + TXT 双格式** — 同时生成标准 XML Sitemap 和纯文本 URL 列表
- **智能更新频率** — 根据文章发布时间动态计算 `changefreq`（daily/weekly/monthly/yearly）
- **自适应优先级** — 首页固定 1.0，文章按评论数动态浮动，页面可配置
- **ISO 8601 日期格式** — 严格输出 W3C 标准日期，兼容 360 等严格解析器
- **rewrite 美化 URL** — `/baidu-sitemap.xml` 和 `/baidu-sitemap.txt`，无需丑陋参数
- **防 trailing slash 重定向** — 阻止 WordPress 给 sitemap URL 加斜杠（360 不跟随重定向）

### 百度推送
- **自动推送** — 文章发布/更新时即时推送单条 URL 到百度
- **批量推送** — AJAX 分页推送（500 条/批），支持按时间范围筛选
- **配额预检** — 推送前查询百度 API 剩余配额，避免超限
- **Token 测试** — 一键测试百度推送 Token 是否有效
- **JS 自动推送** — 前端页面底部输出百度 `push.js`，蜘蛛抓取时自动发现新 URL

### 管理界面
- 可视化设置面板（设置 → 百度收录增强）
- 站点地图状态一览：生成状态、文件大小、直接预览链接
- 实时推送日志展示
- 一键生成 / 一键清除缓存

### 性能设计
- **双层缓存** — `wp_cache`（对象缓存）+ `transient`（持久化缓存），1 小时 TTL
- **批量 SQL** — 文章日期用 `$wpdb->prepare` 批量预加载，避免 N+1
- **挂钩触发** — 仅在 `save_post` / `delete_post` 时重建，不拖慢前台

---

## 安装

### 从 GitHub 安装

```bash
cd wp-content/plugins/
git clone https://github.com/shaobing2024/corepress-baidu-sitemap.git
```

然后进入 WordPress 后台 → 插件 → 启用「百度收录增强版」。

### 手动安装

1. 下载 ZIP 包并解压
2. 将 `corepress-baidu-sitemap` 文件夹上传到 `/wp-content/plugins/`
3. 后台插件页面激活

---

## 配置

1. 进入 **设置 → 百度收录增强**
2. 勾选「启用百度收录增强功能」
3. （可选）填写百度站长平台的推送 Token，开启自动推送
4. 点击「立即生成站点地图」

站点地图访问地址：

| 格式 | URL |
|------|-----|
| XML | `https://你的域名/baidu-sitemap.xml` |
| TXT | `https://你的域名/baidu-sitemap.txt` |

---

## 提交到百度

1. 登录 [百度站长平台](https://ziyuan.baidu.com/)
2. 验证网站所有权
3. 进入 **普通收录 → Sitemap**
4. 提交 `/baidu-sitemap.xml` 或 `/baidu-sitemap.txt`

---

## 技术架构

```
corepress-baidu-sitemap/
├── corepress-baidu-sitemap.php    # 主文件（单文件插件）
├── readme.txt                     # WordPress.org 插件说明
└── README.md                      # GitHub 说明（本文件）
```

### 核心钩子

| 钩子 | 用途 |
|------|------|
| `init` | 注册 rewrite 规则 |
| `template_redirect` | 拦截 sitemap 请求 |
| `save_post` | 文章发布时重建地图 + 推送 |
| `delete_post` | 文章删除时重建地图 |
| `wp_footer` | 输出百度 push.js |
| `robots_txt` | 自动追加 sitemap 链接 |
| `redirect_canonical` | 阻止 sitemap URL 加斜杠 |
| `admin_menu` | 注册设置页面 |

### 百度推送 API

```
POST http://data.zz.baidu.com/urls?site={域名}&token={Token}
Content-Type: text/plain

https://www.example.com/url1
https://www.example.com/url2
```

返回值含 `remain`（今日剩余配额）和 `success`（今日已推送数量）。

---

## 常见问题

**Q: 站点地图生成失败？**
- 确认 `wp-content/uploads/corepress_sitemaps/` 目录可写
- 检查 PHP 内存限制
- 查看 PHP error_log

**Q: 推送失败？**
- 确认 Token 正确（百度站长平台 → 普通收录 → 获取 token）
- 确认网站已完成所有权验证
- 使用设置页面的「测试推送」按钮诊断

**Q: sitemap URL 404？**
- 进入 设置 → 固定链接 → 点击「保存更改」刷新 rewrite 规则

**Q: 360 站长平台提交后包含 URL 为 0？**
- 已修复。插件阻止了 WordPress 对 sitemap URL 的 trailing slash 重定向，360 可直接解析。

---

## 更新日志

### v1.0.3
- 修复 PHP 8.1+ 兼容性（`FILTER_SANITIZE_STRING` 废弃警告）
- 修正插件头 `Requires PHP: 7.4`（兼容 PHP 7.4–8.x）
- 修正作者 URI 为 GitHub 主页
- 更新 README 仓库地址为 shaobing2024

### v1.0.2
- 修复 360 解析器因 trailing slash 重定向导致 0 条 URL
- 新增批量推送时间范围筛选
- 新增百度配额预检
- 新增 AJAX Token 测试推送
- 新增 AJAX 配额查询

### v1.0.0
- 初始发布
- XML + TXT 双格式站点地图
- 百度 API 推送
- 缓存优化
- 管理界面

---

## 参与贡献

欢迎提 Issue 和 PR。

1. Fork 本仓库
2. 创建功能分支 (`git checkout -b feature/xxx`)
3. 提交改动 (`git commit -m 'Add xxx'`)
4. 推送到分支 (`git push origin feature/xxx`)
5. 创建 Pull Request

---

## 许可证

[GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

**作者**：不良人  
**官网**：[小马博客](https://www.xiaomaw.cn)
