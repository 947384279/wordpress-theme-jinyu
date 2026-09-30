=== Jinyu Theme Lite ===
Contributors: jinyu888
Tags: blog, custom-colors, custom-logo, custom-menu, editor-style, featured-images, microformats, post-formats, rtl-language-support, sticky-post, threaded-comments, translation-ready, two-columns, three-columns, block-styles, wide-blocks, full-width-template, custom-background, theme-options
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.3
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

金玉主题的 WordPress.org 发行版（Jinyu Theme Lite）：一款面向内容站点的自适应博客主题，自研架构全新编写，纯呈现层实现，不含代码注入与外部服务依赖。

主要特性：
* 外观选项全部走 WordPress 标准「外观 → 自定义」（Customizer），20+ 个分组覆盖站点标识、全局风格、配色、首页版块、内容展示、评论与页脚。
* 暗色模式：手动切换 + 「跟随系统」自动切换。
* 首页头部版块（幻灯片 / 四宫格 / 分类两栏），后台开关式配置，无需切换布局。
* 文章内容增强：目录（TOC）、面包屑、返回顶部、代码高亮、阅读进度条、相关文章、上一篇/下一篇。
* 文章互动：点赞、收藏、分享、海报生成、二维码。
* 评论增强：UA 解析、表情选择器、评论分页、防暴破限流（邮件通知由站点自身的 SMTP 方案负责）。
* SEO：内置 Open Graph / Twitter Card / JSON-LD 结构化数据。
* 布局：列表 / 大图 / 卡片等多种版式，双栏与三栏，侧栏可左右切换；提供「全宽页面」模板。
* 6 个 Gutenberg 区块图案（patterns/），编辑器内「金玉」图案分类可直接插入。
* PHP 8.0+ / WordPress 6.0+；资源按需加载、关键 CSS 内联、HTML 压缩可选。

与本主题同源的完整版（自托管发行版）另含后台独立设置中心——安全加固、对象缓存、浏览量统计写入、SMTP 发信、统计代码注入等非呈现功能。那些功能不属于主题职责范围，故本发行版不包含；本版主题单独使用即为完整可用的博客主题，无需安装任何其他组件。

== Installation ==

1. 后台「外观 → 主题 → 安装主题 → 上传主题」，选择 `jinyu-theme-lite-x.y.z.zip` 上传并启用；或直接把主题目录放进 `/wp-content/themes/` 后在「外观 → 主题」中启用。
2. 进入「外观 → 自定义」按需配置站点标识、配色与首页版块。
3. （可选）到「设置 → 固定链接」保存一次以刷新重写规则。

== Frequently Asked Questions ==

= 为什么和 GitHub 上的金玉主题功能不一样？ =

金玉主题有两条发行线，源码同源：自托管版（slug `jinyu`）保留独立设置中心与代码注入等后台能力；本发行版（slug `jinyu-theme-lite`，即你正在使用的这一版）为 WordPress.org 规范版，外观选项统一走 Customizer，不含任意代码注入与后台独立设置页。两者可以共存互不干扰。

= 外观选项在哪里？ =

全部在「外观 → 自定义」里，按面板分组：基础设置、全局设置、风格外观、内容增强、评论互动、首页模块、高级设置、用户与登录、页脚设置。

= 需要额外的服务器端功能（缓存、安全加固等）怎么办？ =

这些不属于主题职责，请用相应的插件实现。主题本身不做对象缓存、不写统计、不注入代码。

= 无插件时前台功能是否完整？ =

是。主题对外只依赖 WordPress 核心能力，未安装任何插件也不会出现白屏或功能缺失。

== Privacy ==

本发行版默认不向任何第三方服务器发送访客数据，不收集、不上报统计信息。仅以下功能会在站长显式开启后才与外部服务通信：

* 侧栏访客归属地查询（高级设置 › 侧栏访客归属地查询，默认关闭）：开启后会把访客 IP 发往 whois.pconline.com.cn 查询归属地，结果在站内缓存 12 小时。
* 头像来源（用户互动 › 头像来源，默认 Gravatar）：选择国内镜像（Cravatar / WeAvatar 等）时，评论者邮箱哈希会按同一协议发往所选镜像。
* 每日一句语料（默认本地内置语料，零外部请求）：仅当站长手动定义 JINYU_HITOKOTO_REMOTE 常量时才拉取远程 JSON。

== Compatibility ==

* 多语言：文本域 `jinyu-theme-lite`，随主题提供 `.pot` 模板；字符串均可被 WPML / Polylang 翻译接管，菜单使用「外观 → 菜单」注册的菜单位置。
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

== Changelog ==

= 1.2.3 =
* 首次提交到 WordPress.org 主题目录的发行版（Jinyu Theme Lite），与自托管版 1.2.3 同源。
* 剔除任意代码注入字段与后台独立设置页，外观选项统一为 WordPress 标准 Customizer。
* 全部页面模板在编辑器「模板」选择器中可见；界面文案全面接入翻译体系。
* 修复 ≤600px 视口下登录用户顶栏与管理栏的相对位置错位。
