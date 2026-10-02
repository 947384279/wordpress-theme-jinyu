=== Jinyu Lite ===
Contributors: jinyu888
Tags: blog, custom-colors, custom-logo, custom-menu, editor-style, featured-images, microformats, post-formats, rtl-language-support, sticky-post, threaded-comments, translation-ready, two-columns, three-columns, block-styles, wide-blocks, full-width-template, custom-background, theme-options
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.7
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

面向内容站点的高颜值自适应博客主题：暗色模式、多种首页版块、点赞收藏、TOC 目录与代码高亮；纯呈现层、不外联、无需插件。

== Description ==

金玉主题的 WordPress.org 发行版（Jinyu Lite）：面向内容站点的自适应博客主题，自研架构全新编写，纯呈现层实现，不含代码注入，不向第三方发送访客数据。

主要特性：
* 外观选项全部走 WordPress 标准「外观 → 自定义」（Customizer），20+ 个分组覆盖站点标识、全局风格、配色、首页版块、内容展示、评论与页脚。
* 暗色模式：手动切换 +「跟随系统」自动切换。
* 首页头部版块（幻灯片 / 四宫格 / 分类两栏），后台开关式配置，无需切换布局。
* 内容增强：目录（TOC）、面包屑、返回顶部、代码高亮、阅读进度条、相关文章、上下篇导航。
* 文章互动：点赞、收藏、分享、海报生成、二维码。
* 评论增强：UA 解析、表情选择器、评论分页、防暴破限流。
* SEO：内置 Open Graph / Twitter Card / JSON-LD 结构化数据。
* 布局：列表 / 大图 / 卡片等多种版式，双栏与三栏，侧栏可左右切换；提供「全宽页面」模板。
* 6 个 Gutenberg 区块图案，编辑器内「金玉」分类可直接插入。
* PHP 8.0+ / WordPress 6.0+；资源按需加载、关键 CSS 内联、HTML 压缩可选。

本版主题单独使用即为完整可用的博客主题，无需安装任何其他组件。

== Installation ==

1. 后台「外观 → 主题 → 安装主题 → 上传主题」，选择 `jinyu-lite-x.y.z.zip` 上传并启用；或直接把主题目录放进 `/wp-content/themes/` 后在「外观 → 主题」中启用。
2. 进入「外观 → 自定义」按需配置站点标识、配色与首页版块。
3. （可选）到「设置 → 固定链接」保存一次以刷新重写规则。

== Frequently Asked Questions ==

= 为什么和 GitHub 上的金玉主题功能不一样？ =

金玉主题有两条发行线，源码同源：自托管版（slug `jinyu`）保留独立设置中心与代码注入等后台能力；本发行版（slug `jinyu-lite`，即你正在使用的这一版）为 WordPress.org 规范版，外观选项统一走 Customizer，不含任意代码注入与后台独立设置页。两者可以共存互不干扰。

= 外观选项在哪里？ =

全部在「外观 → 自定义」里，按面板分组：基础设置、全局设置、风格外观、内容增强、评论互动、首页模块、高级设置、用户与登录、页脚设置。

= 需要额外的服务器端功能（缓存、安全加固等）怎么办？ =

这些不属于主题职责，请用相应的插件实现。主题本身不做对象缓存、不写统计、不注入代码。

= 无插件时前台功能是否完整？ =

是。主题对外只依赖 WordPress 核心能力，未安装任何插件也不会出现白屏或功能缺失。

= 这款主题会把我的访客数据发给第三方吗？ =

不会。发行版**不含任何把访客数据发往第三方的代码路径**：IP 归属地查询、头像镜像等能力已整体移除；第三方资源（Font Awesome / highlight.js / viewer.js / qrcode.js）全部本地打包，不走 CDN。

全部行为都发生在本站数据库内 —— 存储：点赞与收藏（用户元数据，各上限 2000 条）、文章点赞数与投票数、浏览量、头像地址、关注与站内通知、邮箱变更验证记录、主题配置（单个 option）、匿名共现统计（**仅文章 ID 对**，每节点上限 15、全局上限 400，不含 IP、UA 或任何用户标识）。Cookie 三个，均为本地偏好、不含个人识别信息、不用于跨站跟踪：`jinyu_liked_<文章ID>`（未登录点赞去重，30 天）、`jinyu-theme`（暗色模式偏好，1 年）、`jinyu_cookie_ok`（提示条已同意，30 天）。

只有两处需站长主动配置才会产生外部请求：在 `wp-config.php` 定义 `JINYU_HITOKOTO_REMOTE` 使用自定义每日一句语料；把头像来源从官方 Gravatar 换成第三方镜像（此时评论者邮箱的 md5 会发往该镜像）。完整清单见下方 Privacy 一节。

== Privacy ==

本主题不收集、不上报任何统计信息，不向第三方发送访客数据。发行版**不含任何把访客数据发往第三方的代码路径**（IP 归属地查询等自托管版才有的功能已整体移除）。

**本主题在本地存储的数据（均在本站数据库内，不外发）：**

* 点赞 / 收藏：登录用户存于用户元数据 `jinyu_liked_posts` / `jinyu_fav_posts`（最多各 2000 条，超出淘汰最旧）；未登录用户不写库。
* 文章计数：点赞数存文章元数据 `jinyu_likes`；「有帮助」投票存 `_jinyu_helpful_yes` / `_jinyu_helpful_no`。
* 头像：上传或填写的头像地址存用户元数据 `jinyu_avatar`。
* 站内互动：关注关系、消息通知、邮箱变更验证（`jinyu_pending_email`，含 token 哈希与过期时间）存用户元数据。
* 主题配置：全部存于单个 option `jinyu_options`。
* 共现统计：`jinyu_coview_map` 记录**匿名**访客读过的文章 ID 对（每节点最多 15 个邻居、总数上限 400 节点），不含 IP、UA 或任何用户标识，仅用于推荐「还读过」。
* 浏览量：文章浏览量存文章元数据 `jinyu_views`。

**本主题使用的 Cookie（均为本地偏好，不用于跨站跟踪）：**

* `jinyu_liked_<文章ID>`：未登录用户点赞去重，30 天。
* `jinyu-theme`：暗色 / 亮色模式偏好，1 年。
* `jinyu_cookie_ok`：Cookie 提示条已同意标记，30 天。

**唯一可能的对外通信（需站长自行启用，且仅自托管版提供）：**

* 每日一句语料：默认使用主题内置的本地语料，零外部请求；仅当站长在 `wp-config.php` 中手动定义 `JINYU_HITOKOTO_REMOTE` 常量时才会拉取该自定义地址的 JSON。
* 头像：默认使用官方 Gravatar。选择其他镜像源会把评论者邮箱的 md5 哈希发往该镜像服务器。

卸载主题不会自动删除上述数据；如需彻底清除，请在数据库中手动清理对应的 option 与用户元数据。

== Compatibility ==

* 多语言：文本域 `jinyu-lite`，随主题提供 `.pot` 模板；字符串均可被 WPML / Polylang 翻译接管，菜单使用「外观 → 菜单」注册的菜单位置。
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

= 1.2.7 =

= 1.2.3 =
* 首次提交到 WordPress.org 主题目录的发行版（Jinyu Lite），与自托管版 1.2.3 同源。
* 剔除任意代码注入字段与后台独立设置页，外观选项统一为 WordPress 标准 Customizer。
* 全部页面模板在编辑器「模板」选择器中可见；界面文案全面接入翻译体系。
* 修复 ≤600px 视口下登录用户顶栏与管理栏的相对位置错位。
