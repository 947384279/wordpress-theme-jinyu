=== Jinyu Lite ===
Contributors: jinyu888
Tags: blog, custom-colors, custom-logo, custom-menu, editor-style, featured-images, microformats, post-formats, rtl-language-support, sticky-post, threaded-comments, translation-ready, two-columns, three-columns, block-styles, wide-blocks, full-width-template, custom-background, theme-options
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.3
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A clean, responsive blog theme for content sites: dark mode, multiple homepage sections, likes & bookmarks, table of contents and code highlighting. Pure presentation layer, no code injection, and no visitor data is sent to third parties.

== Description ==

Jinyu Lite is the WordPress.org-distributed variant of the Jinyu theme. It is a responsive blog theme written from scratch with an original architecture, implemented as a pure presentation layer: it contains no code injection and sends no visitor data to any third party.

Key features:

* All appearance options live in the standard WordPress **Appearance → Customize** (Customizer), with 20+ panels covering site identity, global style, color scheme, homepage sections, content display, comments, and footer.
* Dark mode: manual toggle plus an automatic "follow system" switch.
* Homepage header sections (slider / 2x2 grid / category two-column) configured via on/off switches — no layout switching required.
* Content enhancements: table of contents (TOC), breadcrumbs, back-to-top, code highlighting, reading progress bar, related posts, and previous/next post navigation.
* Post interactions: like, bookmark, share, poster generation, and QR code.
* Comment enhancements: user-agent parsing, emoji picker, comment pagination, and brute-force rate limiting.
* Social sharing: one-click share, poster generation, and a QR code for each post.
* Layouts: list / large-image / card styles, two-column and three-column, sidebar that can be switched left or right; a "Full Width Page" template is provided.
* 6 Gutenberg block patterns, insertable directly from the "Jinyu" category in the editor.
* PHP 8.0+ / WordPress 6.0+; assets are loaded on demand, critical CSS is inlined, and HTML minification is optional.

This theme is fully usable on its own — no additional components need to be installed.

== Installation ==

1. In your dashboard go to **Appearance → Themes → Add New → Upload Theme**, choose `jinyu-lite-x.y.z.zip` to upload and activate; or place the theme folder into `/wp-content/themes/` and activate it under **Appearance → Themes**.
2. Go to **Appearance → Customize** to configure site identity, color scheme, and homepage sections as needed.
3. (Optional) Visit **Settings → Permalinks** and save once to flush rewrite rules.

== Frequently Asked Questions ==

= Why is this different from the Jinyu theme on GitHub? =

Jinyu ships on two distribution lines from the same source. The self-hosted edition (slug `jinyu`) keeps the standalone settings center and code-injection capabilities. This distribution (slug `jinyu-lite`, the one you are using) is the WordPress.org-compliant edition: appearance options are unified under the Customizer, and it contains no arbitrary code injection or standalone settings page. The two can coexist without conflict.

= Where are the appearance options? =

All of them are under **Appearance → Customize**, grouped into panels: Basic, Global, Style, Content, Comments, Homepage, Advanced, User & Login, and Footer.

= What if I need server-side features (caching, hardening, etc.)? =

Those are outside the theme's responsibility; please implement them with the appropriate plugins. The theme itself does no object caching and injects no code; it stores only local, non-personally-identifying interaction data (see the Privacy section) and sends nothing to third parties.

= Does the front end work completely without plugins? =

Yes. The theme relies only on WordPress core capabilities. Installing no plugins will not cause a white screen or missing functionality.

= Does this theme send my visitors' data to third parties? =

No. The distribution contains no code path that sends visitor data to third parties: IP geolocation lookups, avatar mirrors, and similar capabilities of the self-hosted edition have been removed entirely. Third-party assets (Font Awesome / highlight.js / viewer.js / qrcode.js) are all bundled locally and never loaded from a CDN.

All behavior happens inside this site's own database — storage: likes and bookmarks (user meta, capped at 2000 each), post like counts and votes, view counts, avatar URLs, follow relationships and on-site notifications, email-change verification records, theme configuration (a single option), and anonymous co-view statistics (post ID pairs only, capped at 15 neighbors per node and 400 nodes total, with no IP, UA, or any user identifier). Three cookies, all local preferences with no personally identifiable information and no cross-site tracking: `jinyu_liked_<post ID>` (logged-out like de-duplication, 30 days), `jinyu-theme` (dark mode preference, 1 year), and `jinyu_cookie_ok` (consent banner acknowledged, 30 days).

Only two cases produce an external request, and both require the site owner to opt in: defining `JINYU_HITOKOTO_REMOTE` in `wp-config.php` to use a custom daily-quote source; or switching the avatar source away from the official Gravatar (in which case the md5 of the commenter's email is sent to that mirror). See the Privacy section below for the full list.

== Privacy ==

This theme collects no visitor-identifying data and sends no visitor data to third parties. It stores only local, anonymous interaction statistics (see below) inside this site's own database; the distribution contains no code path that sends visitor data to third parties (IP geolocation lookups and other self-hosted-only features have been removed entirely).

**Data stored locally by this theme (all inside this site's own database, never sent out):**

* Likes / bookmarks: for logged-in users stored in user meta `jinyu_liked_posts` / `jinyu_fav_posts` (up to 2000 each; oldest evicted when exceeded); logged-out users are not written to the database.
* Post counters: like count in post meta `jinyu_likes`; "helpful" votes in `_jinyu_helpful_yes` / `_jinyu_helpful_no`.
* Avatars: uploaded or entered avatar URLs stored in user meta `jinyu_avatar`.
* On-site interactions: follow relationships, message notifications, and email-change verification (`jinyu_pending_email`, containing a token hash and expiry) stored in user meta.
* Theme configuration: all stored in a single option `jinyu_options`.
* Co-view statistics: `jinyu_coview_map` records **anonymous** pairs of post IDs that visitors read together (at most 15 neighbors per node, 400 nodes total), with no IP, UA, or any user identifier; used only to recommend "also read".
* View counts: post view count stored in post meta `jinyu_views`.

**Cookies used by this theme (all local preferences, not for cross-site tracking):**

* `jinyu_liked_<post ID>`: logged-out like de-duplication, 30 days.
* `jinyu-theme`: dark / light mode preference, 1 year.
* `jinyu_cookie_ok`: consent banner acknowledged, 30 days.

**The only possible external communication (requires the site owner to enable, and only available in the self-hosted edition):**

* Daily quote source: by default the theme uses a built-in local corpus with zero external requests; an external pull happens only when the site owner manually defines the `JINYU_HITOKOTO_REMOTE` constant in `wp-config.php` to fetch JSON from that custom address.
* Avatars: by default the official Gravatar is used. Choosing another mirror source sends the md5 hash of the commenter's email to that mirror server.

Uninstalling the theme does not automatically delete the data above; to purge it completely, manually clean up the corresponding options and user meta in the database.

== Compatibility ==

* Multilingual: text domain `jinyu-lite`, a `.pot` template ships with the theme; strings can be taken over by WPML / Polylang, and menu locations are registered under **Appearance → Menus**.
* Page builders: compatible with the Gutenberg block editor (supports block-styles / wide-blocks / align-wide); a "Full Width Page" page template is provided for the full-width layout needs of builders such as Elementor.
* Store: WooCommerce deep customization is not built in, but basic style compatibility is maintained.

== Resources ==

This theme bundles the following third-party assets under `assets/`, licensed as noted:

* Font Awesome Free 6: icons under CC BY 4.0 (https://creativecommons.org/licenses/by/4.0/); fonts under SIL OFL 1.1 (https://opensource.org/licenses/OFL-1.1); code under MIT. Trademark notice: Font Awesome is a trademark of Dave Gandy; this theme uses it only under its open-source license and is not affiliated with the trademark holder.
* Highlight.js 11.9.0 (`assets/js/vendor/highlight.js`, code highlighting) — BSD-3-Clause, https://github.com/highlightjs/highlight.js/blob/main/LICENSE, copyright the Highlight.js contributors; the bundled GitHub color scheme (`assets/css/vendor/highlight.css`) belongs to the same project under the same license.
* Viewer.js 1.11.7 (`assets/js/vendor/viewer.js`, image lightbox) — MIT, https://github.com/fengyuanchen/viewerjs/blob/main/LICENSE, copyright Chen Fengyuan.
* QRCode.js (`assets/js/vendor/qrcode.js`, share QR code) — MIT, https://github.com/davidshimjs/qrcodejs, copyright Kazuhiko Arase / davidshimjs.

All of the above are provided as uncompressed source paired with their minified versions, readable and verifiable directly; all licenses are GPL-compatible (MIT / BSD-3-Clause / CC BY 4.0 / SIL OFL 1.1).
The remaining scripts and styles (homepage carousel, table of contents, likes, reading progress, etc.) are original implementations of this theme, with no third-party code or licensing obligations.

== Changelog ==

= 1.3.3 =
* WordPress.org review compliance: addressed all points raised in the theme review (screenshot accuracy, Theme URI handling, front-end string internationalization, shipping unminified sources alongside minified files, and long-title wrapping within the content area).
* Added the Font Awesome license file (assets/fonts/fa/license.txt) to declare third-party asset licensing.
* Removed the trailing PHP closing tag from the archive loop template (WPCS).
* Build output now ships unminified companion files (style.css / critical.css / jinyu.js / ai-chat.js / admin-alert.css) alongside their .min counterparts.

= 1.3.2 =
* Rebuilt the site notice banner: the "Site Notice" field now accepts one notice per line, with a dedicated toggle to show or hide the banner and a second toggle to scroll multiple notices vertically in a seamless loop. A single notice is displayed statically.
* Added a legacy engine shield: an inline script in the document head detects outdated rendering engines (Chromium below 120, Safari below 15, Firefox below 110) and marks the document so that GPU-heavy effects such as backdrop blur are disabled, preventing renderer crashes on older browsers. A dismissible notice tells visitors their browser is outdated.
* Sidebar behavior: when no widgets are added to the "Main Sidebar" in the admin, the theme no longer renders its built-in fallback widgets; the sidebar is hidden and the main column takes the full width.
* Performance: removed the front-end global styles inline stylesheet (about 15 KB); block styles are provided by the theme itself. Tag clouds and category lists now use a cross-request cache that is invalidated whenever posts are published, edited or deleted.
* Performance: author card counts (published posts and approved comments) are cached per author and invalidated precisely when the relevant post or comment changes.
* Fixed the search overlay and suggestion list entrance animation to use a plain opacity fade (no movement) and removed the overlay backdrop blur, fixing renderer crashes on older engines.
* Misc: renamed the "Standard list" style option to "Blog mode"; added three localized admin strings (close, changes applied, please try again).

= 1.3.1 =

= 1.3.0 =
* Added code syntax highlighting: post code blocks rendered by highlight.js, with light/dark themes following the site automatically (toggle under "Content" settings).
* Added a post image lightbox: click a body image to view it full-screen via viewer.js, with zoom and paging.
* Added post QR-code sharing: a QR code of the current link is generated in the post actions area (qrcode.js).
* Added an in-post table of contents (TOC): auto-generated from H2/H3/H4 and highlighted against the current section on scroll.
* Font and icon upgrade: introduced the jinyu-text font; Font Awesome upgraded to the full icon set.
* Sidebar: fixed icon display glitches and optimized sidebar animation (A/C/D tiers).
* Admin: completed internationalization (i18n) of settings labels and translator comments.
* Security: completed CSP nonce coverage for the theme's inline scripts/styles, added a deployment note; security audit confirmed no Critical/High vulnerabilities.
* Code standards: whole theme passes WPCS with 0 errors / 0 warnings.

= 1.2.9 =

= 1.2.8 =

= 1.2.7 =

= 1.2.3 =
* First submission of the WordPress.org-distributed edition (Jinyu Lite), sharing the same source as the self-hosted 1.2.3.
* Removed arbitrary code-injection fields and the standalone settings page; appearance options unified under the standard WordPress Customizer.
* All page templates visible in the editor's "Template" selector; UI strings fully wired into the translation system.
* Fixed the relative-positioning misalignment of the top bar and admin bar for logged-in users at viewports of 600px and below.
