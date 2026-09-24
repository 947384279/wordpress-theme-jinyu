=== Jinyu ===
Contributors: huanxiang88
Tags: blog, cms, responsive, dark-mode, custom-colors, editor-style, featured-images, microformats, post-formats, rtl-language-support, sticky-post, threaded-comments, translation-ready, two-columns, three-columns
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Privacy ==

本主题默认不向任何第三方服务器发送访客数据，不收集、不上报统计信息。以下功能仅在站长显式开启后才会与外部服务通信：

* 侧栏访客归属地查询（高级设置 › 侧栏访客归属地查询，默认关闭）：开启后会把访客 IP 发往 whois.pconline.com.cn 查询归属地，结果在站内缓存 12 小时。
* 头像来源（用户互动 › 头像来源，默认 Gravatar）：选择国内镜像（Cravatar / WeAvatar 等）时，评论者邮箱哈希会按同一协议发往所选镜像。
* 每日一句语料（默认本地内置语料，零外部请求）：仅当站长手动定义 JINYU_HITOKOTO_REMOTE 常量时才拉取远程 JSON。

== Resources ==

本主题在 `assets/` 下打包了以下第三方资源，其许可如下：

* Font Awesome Free 6：图标采用 CC BY 4.0 许可（https://creativecommons.org/licenses/by/4.0/）；
  字体采用 SIL OFL 1.1 许可（https://opensource.org/licenses/OFL-1.1）；代码采用 MIT 许可。
  商标声明：Font Awesome 为 Dave Gandy 的商标，本主题仅按其开源许可使用，与商标持有人无隶属关系。
* Swiper 11（assets/css/vendor/swiper-bundle.min.css 及其字体）：MIT 许可（https://opensource.org/licenses/MIT）。

== 关于 inc/fun/crypto.php 中的 base64 ==

本主题 `inc/fun/crypto.php` 使用 `base64_encode` / `base64_decode` 仅为对**主题设置项（jinyu_options）的敏感字段做对称加密存储**（如社交登录密钥等，避免以明文落库）。它不属于代码混淆、后门或远程通信；算法为公开的 AES-256-CBC + HMAC，密钥由站点自身持有，无可执行载荷、无外部网络请求。该文件不含任何规避审查的逻辑，符合 GPL 与 .org 主题库对「加密仅用于数据保护」的允许范围。

高颜值自适应 WordPress 主题，自研架构全新编写。支持后台可视化配置、暗色模式、多种布局、短代码、评论邮件通知、点赞与无限加载。

== Description ==

金玉（Jinyu）是一款面向内容站点的自适应 WordPress 主题，后台提供可视化配置中心，覆盖站点、风格、SEO、内容展示、用户互动、扩展开发等维度。

主要特性：
* 后台可视化配置中心（19 个设置分组，支持导入/导出 JSON、单组重置、分类多选器、密码显敏存储）。
* 暗色模式，支持手动切换与「跟随系统」自动切换。
* 首页头部版块（幻灯片 / 四宫格 / 分类两栏）可视化配置，开开关即可启用，无需切换布局。
* 文章内容增强：目录（TOC）、面包屑、返回顶部、代码高亮、阅读进度条、相关文章、上一篇/下一篇。
* 文章互动：点赞、收藏、分享、海报生成、二维码。
* 评论：邮件通知、UA 解析、表情选择器、防暴破限流。
* SEO：内置 Open Graph / Twitter Card / JSON-LD 结构化数据。
* 维护模式、统计代码注入、访问统计面板。
* PHP 8.0+ / WordPress 7.1+，资源按需加载、HTML 压缩、关键 CSS 内联。

== Installation ==

1. 将主题文件夹上传到 `/wp-content/themes/`。
2. 在后台「外观 → 主题」中启用「金玉」。
3. 进入「金玉 → 设置」按需配置站点与展示选项。
4. （可选）到「设置 → 固定链接」保存一次以刷新重写规则。

== Frequently Asked Questions ==

= 配置会保存在哪里？ =

所有设置保存在 `wp_options` 表的 `jinyu_options` 字段（序列化数组）。保存时按本次提交的字段做增量合并，未提交的分组与其它键不会被覆盖；敏感字段（邮箱密码等）以密文形式存储。
可在「扩展与开发 → 维护工具」中导出/导入 JSON 备份。

= 如何重置某项设置？ =

每个设置分组面板头部都有「重置本组」按钮，仅清空该分组、回退默认值，不影响其它设置。

== Changelog ==

= 1.1.0 =
* 隐私合规：侧栏 IP 归属地外部查询改为默认关闭，需在「高级设置」显式开启（新增开关），并在 readme 增加隐私披露。
* 主题显示名改为 ASCII「Jinyu」，符合 .org 提交规范。

= 1.0.9 =
* 修复：暗色模式下「主题主色」失效——内置暗色令牌把主色写死成橙色且权重大于 `:root`，导致蓝色站点在暗色下变橙。现按后台配置的主色自动派生暗色变体（自动提亮至可读对比度），并覆盖暗色下的主色/悬浮色/底纹变量。
* 链接建设：新增「自动内链」——正文里出现的其它文章标题自动转为站内链接（长词优先、每词仅首次、单篇上限 5、跳过标题/代码块/已有链接），关键词索引用 transient 缓存并在发布/更新/删除文章时失效重建。后台「金玉 › SEO › 自动内链」可开关（默认开）。

= 1.0.8 =
* GEO：增肥 /llms.txt，从「仅最新 12 篇」升级为完整内容地图——新增「热门文章」（按浏览量）、「系列教程」（jinyu_series 分类法）、扩展「重要页面」（关于/友链/标签/归档/留言板），并规避已弃用的 get_page_by_path。

= 1.0.7 =
* SEO：归档页（分类/标签/作者/日期/自定义文章类型）自定义标题，提升可读性与搜索引擎表现。
* GEO：/llms.txt 新增后台开关（设置 › SEO › 输出 /llms.txt），关闭后访问返回 404。

= 1.0.6 =
* 修复 FAQPage 结构化数据（JSON-LD）因正则开合标签别名不一致而永不输出的问题。
* 新增 GEO 支持：动态生成 /llms.txt，供 AI 助理（ChatGPT / Claude / Perplexity / 元宝等）抓取与引用。

= 1.0.5 =
* 配置中心：新增内容增强分组（TOC / 面包屑 / 返回顶部 / 代码高亮 / 阅读进度 / 相关文章 / 上一篇下一篇 / 分享 / 海报 / 二维码 / 点赞 / 收藏 / 封面 独立开关）。
* 维护模式、Open Graph / Twitter Card 增强、配置导入导出、单组重置、密码脱敏存储。
* 暗色跟随系统、正文字号调节、分享渠道多选、JSON-LD 总开关、评论分页、统计代码独立字段、默认缩略图、摘要字数。
* 清理 DPlayer / 彩虹聚合登录占位代码；移除 Twemoji（CDN 依赖），Emoji 改由系统字体原生渲染。
* 安全加固：ABSPATH 守卫、SQL 预处理、REST API 关闭方式修正。

== Upgrade Notice ==

= 1.0.5 =
建议先到「维护工具」导出当前配置 JSON 备份，再升级。
