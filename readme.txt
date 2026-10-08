=== Jinyu ===
Contributors: jinyu888
Tags: blog, custom-colors, custom-logo, custom-menu, editor-style, featured-images, microformats, post-formats, rtl-language-support, sticky-post, threaded-comments, translation-ready, two-columns, three-columns, block-styles, wide-blocks, full-width-template, custom-background, theme-options
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Privacy ==

本主题默认不向任何第三方服务器发送访客数据，不收集、不上报统计信息。以下功能仅在站长显式开启后才会与外部服务通信：

* 侧栏访客归属地查询（高级设置 › 侧栏访客归属地查询，默认关闭）：开启后会把访客 IP 发往 whois.pconline.com.cn 查询归属地，结果在站内缓存 12 小时。
* 头像来源（用户互动 › 头像来源，默认 Gravatar）：选择国内镜像（Cravatar / WeAvatar 等）时，评论者邮箱哈希会按同一协议发往所选镜像。
* 每日一句语料（默认本地内置语料，零外部请求）：仅当站长手动定义 JINYU_HITOKOTO_REMOTE 常量时才拉取远程 JSON。

== Compatibility ==

* 多语言：文本域 `jinyu`，随主题提供 `.pot` 模板；字符串均可被 WPML / Polylang 翻译接管，推荐菜单使用「外观 → 菜单」注册的菜单位置。
* 页面构建器：兼容 Gutenberg 区块编辑器（支持 block-styles / wide-blocks / align-wide）；提供「全宽页面」页面模板，适配 Elementor 等构建器的全宽布局需求。
* 商城：未内置 WooCommerce 深度定制，但保持基础样式兼容。

== Resources ==

本主题在 `assets/` 下打包了以下第三方资源，其许可如下：

* Font Awesome Free 6：图标采用 CC BY 4.0 许可（https://creativecommons.org/licenses/by/4.0/）；
  字体采用 SIL OFL 1.1 许可（https://opensource.org/licenses/OFL-1.1）；代码采用 MIT 许可。
  商标声明：Font Awesome 为 Dave Gandy 的商标，本主题仅按其开源许可使用，与商标持有人无隶属关系。
* Highlight.js 11.9.0（`assets/js/vendor/highlight.js`，代码高亮）——BSD-3-Clause 许可，
  https://github.com/highlightjs/highlight.js/blob/main/LICENSE ，版权归 Highlight.js 贡献者所有；
  随附的 GitHub 配色（`assets/css/vendor/highlight.css`）属同一项目、同一许可。
* Viewer.js 1.11.7（`assets/js/vendor/viewer.js`，正文图片查看器）——MIT 许可，
  https://github.com/fengyuanchen/viewerjs/blob/main/LICENSE ，版权归 Chen Fengyuan 所有。
* QRCode.js（`assets/js/vendor/qrcode.js`，分享二维码）——MIT 许可，
  https://github.com/davidshimjs/qrcodejs ，版权归 Kazuhiko Arase / davidshimjs 所有。

以上资源均以未压缩源码 + 压缩版成对提供，可直接阅读与核对；许可均为 GPL 兼容（MIT / BSD-3-Clause / CC BY 4.0 / SIL OFL 1.1）。
其余脚本与样式（首页轮播、目录、点赞、阅读进度等）均为本主题自研实现，无第三方代码与许可义务。

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
* PHP 8.0+ / WordPress 6.0+，资源按需加载、HTML 压缩、关键 CSS 内联。

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

= 1.3.0 =
* 新增代码语法高亮：文章代码块由 highlight.js 渲染，明暗双主题跟随站点主题自动切换（后台「内容」设置可开关）。
* 新增文章图片灯箱：点击正文图片以 viewer.js 全屏查看，支持缩放与翻页。
* 新增文章二维码分享：文章操作区生成当前链接二维码（qrcode.js）。
* 新增正文目录（TOC）：基于 H2/H3/H4 自动生成目录并随滚动高亮当前章节。
* 字体与图标升级：引入 jinyu-text 文本字体，FontAwesome 升级为全量图标集。
* 侧边栏：修复图标显示异常，优化侧栏动画（A/C/D 三档）。
* 后台：补全设置项文案国际化（i18n）与 translators 注释。
* 安全：补全主题内联脚本/样式缺失的 CSP nonce（前台模板、维护页、邮箱校验页、后台设置页），新增 SECURITY.md 部署须知；安全审计确认无 Critical/High 漏洞。
* 代码规范：全主题 WPCS 校验 0 error / 0 warning（清理存量编码规范问题）。

= 1.2.9 =
* 修复分类/标签/搜索/作者等归档页因 get_template_part 调用写法错误导致文章列表不显示的问题（v1.2.8 抽离循环模板时引入）；并收尾主题侧解耦契约与打包合规闸门的未提交改动。

= 1.2.8 =
* 常规维护与缺陷修复。；code style: WPCS 全量合规治理

= 1.2.7 =
* RSS 订阅增强：feed 输出文章缩略图，取图优先级为「特色图 → 正文首图 → 子附件」；RSS 2.0 注入 enclosure、Atom 注入 rel=enclosure，摘要与正文自动前置封面图。
* 页脚社交图标支持 RSS 条目：后台未填写链接时自动兜底指向全站 feed 地址。
* 全站界面文案国际化：界面原文统一为英文，附 zh_CN 翻译包（901 条），覆盖前台与后台设置页。
* 修复 WP 7.x 下主题内置语言目录不被加载的问题：翻译包文件名由 jinyu-zh_CN.mo 改为 zh_CN.mo，与 WP 的 {locale}.mo 查找约定一致。
* 编码规范全量归零：WPCS 校验 0 error / 0 warning，补齐 translators 注释。
* 移除已废弃的 imagedestroy() 调用（PHP 7.0 起为空操作），小工具统计数组对齐规范化。

= 1.2.3 =
* 缓存失效机制重构：保存设置或切换主题后前台立即生效，无需等待缓存过期（缓存 key 统一拼入版本号盐值，版本号 bump 后旧 key 立即失效，并由每日任务回收孤儿 transient）。
* 评论列表加载提速：最新评论的父级评论改为循环前批量预取，消除 N+1 查询。
* 全部页面模板已在编辑器「模板」选择器中可见（theme.json 注册 customTemplates，与 pages/ 目录一一对应）。
* 界面文案全面接入翻译体系：补齐硬编码中文，阅读进度占位符改用 %1$d / %2$s 并同步语言包。
* 修复语录组件占位符显示异常。
* 官方标签规范化（新增 accessibility-ready、custom-background、theme-options），移除与实际不符的标签。
* 全项目代码格式规范化：换行统一为 LF，空白与缩进统一。

= 1.2.2 =
* 后台「主题设置」页双垂直滚动条修复：设置外壳高度改为扣除 WP 固定顶栏（calc(100vh - var(--wp-admin--admin-bar--height))），文档高度与视口等高，页面级滚动条消失，仅主区域内部滚动。
* 移除锁 overflow 的 `html:has(...)` 兜底：含 `:has()` 的选择器列表在 Chromium 105 以下会被整条丢弃，导致修复在旧版浏览器（Edge 100 等）上静默失效。当前方案只使用 calc() / 100vh / var() 回退值，兼容各主流浏览器。
* 正文任务列表（Gutenberg `li.task-list-item`）样式改为不依赖 `:has()` 的两层写法，旧版浏览器下 checkbox 仍正确对齐；`:has()` 仅作增强层单独成条，用于清除第三方插件输出任务列表的项目符号。

= 1.2.0 =
* 新增 6 个 Gutenberg 区块图案（patterns/ 目录，WordPress 6.0+ 自动注册）：图文横排、三栏特性卡、常见问答、行动号召、精选文章三栏（动态取最新文章）、作者介绍卡。编辑器插入面板新增「金玉」图案分类。
* 新增 inc/fun/patterns.php 注册图案分类；block.less 追加 jy-pat-* 图案专属样式（跟随明暗模式与主题主色）。

= 1.1.1 =
* 兼容性声明补全：style.css / readme.txt 补充 .org 官方标签（block-styles、wide-blocks、full-width-template、custom-logo、custom-menu），并新增 Compatibility 段说明多语言（WPML/Polylang）与页面构建器（Gutenberg/Elementor）兼容。
* 新增「全宽页面」页面模板（pages/template-fullwidth.php），适配 Elementor 等构建器的全宽布局需求。

= 1.1.0 =
* 隐私合规：侧栏 IP 归属地外部查询改为默认关闭，需在「高级设置」显式开启（新增开关），并在 readme 增加隐私披露。
* 主题显示名改为 ASCII「Jinyu」，符合 .org 提交规范。
* Requires at least 由 7.1 降为 6.0（主题未使用 6.0 之后的新 API，实测无需更高版本）。

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
