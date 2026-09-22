/**
 * 金玉主题 · 前端主脚本
 *
 * 约定：
 *  - 全站事件统一走事件委托，避免逐个元素绑定
 *  - 所有模块通过 MODULES 数组注册，新增功能只需追加一个函数
 *  - 主题色模式由 <html data-theme> 驱动，PHP 已按 cookie 预渲染，避免首屏闪烁
 */
(function (window, document) {
    'use strict';

    var CFG = window.JINYU_CONFIG || {};
    var doc = document;
    var root = doc.documentElement;

    function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }

    // 前端可翻译文案：优先用 PHP 经 wp_localize_script 注入的 window.JINYU_I18N，
    // 未配置翻译时回退到内置中文，保证默认语言零破绽。
    var JINYU_I18N = window.JINYU_I18N || {};
    function _t(key, fallback) { return JINYU_I18N[key] || fallback; }

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer = window.matchMedia('(pointer: fine)').matches;

    /* ======================================================================
       工具
       ====================================================================== */
    var Util = {
        ajax: function (action, data, cb) {
            var build = function () {
                var body = ['action=' + encodeURIComponent(action)];
                // 优先用懒加载到位的 fresh nonce；缺失时回退到调用方传入的（兼容极少数直传场景）
                if (CFG.nonce) body.push('_ajax_nonce=' + encodeURIComponent(CFG.nonce));
                else if (data && data._ajax_nonce) body.push('_ajax_nonce=' + encodeURIComponent(data._ajax_nonce));
                for (var k in data) {
                    if (!Object.prototype.hasOwnProperty.call(data, k)) continue;
                    if (k === '_ajax_nonce') continue; // 已在上方按优先级处理，避免重复写入
                    body.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
                }
                return body.join('&');
            };
            var fire = function () {
                fetch(CFG.ajax_url || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: build()
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) { cb(null, res); })
                    .catch(function (err) { cb(err); });
            };
            if (CFG.nonce) { fire(); }
            else { Util.refreshNonce().then(fire).catch(fire); }
        },

        /**
         * 从 jinyu_nonce 端点拉取当下有效的 jinyu_front nonce，写入 JINYU_CONFIG.nonce。
         * 缓存 Promise，避免重复请求。nonce 为应下发给客户端的令牌，无泄露风险。
         */
        refreshNonce: function () {
            if (window.__jinyuNonceP) return window.__jinyuNonceP;
            if (!CFG.nonce_endpoint) return Promise.resolve();
            window.__jinyuNonceP = fetch(CFG.nonce_endpoint, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.nonce) { window.JINYU_CONFIG.nonce = res.nonce; }
                    return res;
                })
                .catch(function () { /* 失败保留空值，下次调用再尝试 */ });
            return window.__jinyuNonceP;
        },

        copy: function (text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            return Promise.reject(new Error('clipboard unavailable'));
        },

        toast: function (msg) {
            var el = doc.createElement('div');
            el.className = 'jinyu-toast';
            el.textContent = msg;
            doc.body.appendChild(el);
            requestAnimationFrame(function () { el.classList.add('jinyu-toast-in'); });
            setTimeout(function () {
                el.classList.remove('jinyu-toast-in');
                setTimeout(function () { el.remove(); }, 300);
            }, 2000);
        },

        onScroll: function (fn) {
            var ticking = false;
            window.addEventListener('scroll', function () {
                if (ticking) return;
                ticking = true;
                window.requestAnimationFrame(function () { fn(); ticking = false; });
            }, { passive: true });
        },

        /**
         * 以 multipart/form-data 提交（用于带文件的表单 / 头像上传）
         */
        ajaxForm: function (action, formData, cb) {
            if (CFG.nonce && !formData.has('_ajax_nonce')) {
                formData.append('_ajax_nonce', CFG.nonce);
            }
            formData.append('action', action);
            fetch(CFG.ajax_url || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (r) { return r.json(); })
                .then(function (res) { cb(null, res); })
                .catch(function (err) { cb(err); });
        },

        /**
         * 在 tip 元素上展示结果（ok / err 状态）
         */
        showTip: function (tip, msg, ok) {
            if (!tip) return;
            tip.textContent = msg || '';
            if (ok === true) tip.setAttribute('data-jinyu-state', 'ok');
            else if (ok === false) tip.setAttribute('data-jinyu-state', 'err');
            else tip.removeAttribute('data-jinyu-state');
        }
    };

    // 轻量 HTML 转义（用于把接口返回的纯文本安全插进 innerHTML）
    function escHtml(s) {
        var d = doc.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /* ======================================================================
       模块：明暗主题
       ====================================================================== */
    function theme() {
        var STORE_KEY = 'jinyu-theme';
        var btn = $('[data-jinyu-theme-toggle]');
        var mq = window.matchMedia('(prefers-color-scheme: dark)');

        function stored() {
            try { return localStorage.getItem(STORE_KEY); } catch (e) { return null; }
        }
        // 仅 'light' / 'dark' 视为用户已手动选择；其余（含 null）视为「未选，跟随系统」
        function isExplicit() {
            var s = stored();
            return s === 'light' || s === 'dark';
        }
        function systemDark() { return mq.matches; }
        function defaultDark() {
            // theme_mode = auto → 跟随系统；否则取后台设定值
            if (CFG.theme_mode === 'auto') return systemDark();
            return CFG.theme_mode === 'dark';
        }
        function isDark() {
            var s = stored();
            if (s === 'dark') return true;
            if (s === 'light') return false;
            return defaultDark();
        }

        function apply(dark) {
            root.setAttribute('data-theme', dark ? 'dark' : 'light');
        }

        function syncIcon() {
            if (!btn) return;
            var dark = isDark();
            var icon = btn.querySelector('i');
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.setAttribute('title', dark ? '切换到亮色模式' : '切换到暗色模式');
            if (icon) icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }

        function set(dark) {
            try { localStorage.setItem(STORE_KEY, dark ? 'dark' : 'light'); } catch (e) { /* 隐私模式忽略 */ }
            apply(dark);
            // cookie 供服务端预渲染，避免首屏闪烁
            doc.cookie = STORE_KEY + '=' + (dark ? 'dark' : 'light') + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';samesite=lax';
            syncIcon();
        }

        if (btn) {
            // 两态切换：亮 ⇄ 暗，每次点击必有变化（修复原三态「自动」与「亮」同形导致首点无反应）
            btn.addEventListener('click', function () {
                set(!isDark());
            });
        }

        // 系统主题变化：开启「跟随系统」且用户尚未手动选择时才跟随
        var onSchemeChange = function () {
            if (CFG.theme_mode === 'auto' && !isExplicit()) { apply(systemDark()); syncIcon(); }
        };
        if (mq.addEventListener) mq.addEventListener('change', onSchemeChange);
        else if (mq.addListener) mq.addListener(onSchemeChange);

        apply(isDark());
        syncIcon();
    }

    /* ======================================================================
       模块：全站灰度（哀悼模式）
       ====================================================================== */
    function greyMode() {
        if (CFG.grey) root.classList.add('jinyu-grey');
    }

    /* ======================================================================
       模块：移动端导航（全屏浮层面板 + 手风琴子菜单）

       设计要点（为什么不直接切 body overflow 就完事）：
        - 状态单一来源：面板的 .jinyu-menu-open 与汉堡的 aria-expanded 同步写入；
          morph 图标、面板动画全部由 CSS 依据 aria 属性驱动，杜绝「类和视觉不同步」。
        - 滚动锁只用 overflow:hidden，不用 iOS 常见的 position:fixed 方案 ——
          后者会把 body 钉到负值，sticky 头部随之移出视口，反而看不到汉堡按钮。
          同时补偿滚动条宽度，避免窄桌面窗口打开菜单时内容横向跳动。
        - Esc 关闭、Tab 焦点在「汉堡 → 面板」之间成环、关闭后焦点归还汉堡按钮。
        - 断点用 matchMedia 监听（旧代码是 resize + 写死 992，与实际的 1240 折叠断点不一致）。
       ====================================================================== */
    function nav() {
        var toggle = $('[data-jinyu-nav-toggle]');
        var panel = $('[data-jinyu-nav-panel]');
        if (!toggle || !panel) return;

        var mqDesktop = window.matchMedia('(min-width: 1241px)');
        var labelOpen = toggle.getAttribute('data-label-open') || '展开菜单';
        var labelClose = toggle.getAttribute('data-label-close') || '关闭菜单';
        var opened = false;
        var padRight = '';

        /* ---------- 子菜单：注入展开按钮 + 手风琴 ---------- */
        $$('li', panel).forEach(function (li) {
            var sub = li.querySelector(':scope > ul');
            if (!sub) return;
            li.classList.add('jinyu-has-sub');
            if (li.querySelector(':scope > .jinyu-sub-toggle')) return;   // 幂等：重复初始化不叠加按钮
            var label = li.querySelector(':scope > a');
            var btn = doc.createElement('button');
            btn.type = 'button';
            btn.className = 'jinyu-sub-toggle';
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', (label ? label.textContent.trim() : '') + ' 子菜单');
            btn.innerHTML = '<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>';
            li.insertBefore(btn, sub);
        });

        /* ---------- 滚动锁 ---------- */
        function lockScroll() {
            var gap = window.innerWidth - root.clientWidth;
            if (gap > 0) {
                padRight = doc.body.style.paddingRight;
                doc.body.style.paddingRight = gap + 'px';
            }
            doc.body.style.overflow = 'hidden';
            root.style.overflow = 'hidden';
        }
        function unlockScroll() {
            doc.body.style.overflow = '';
            root.style.overflow = '';
            doc.body.style.paddingRight = padRight;
            padRight = '';
        }

        /* ---------- 焦点环：汉堡按钮 + 面板内可见可聚焦元素 ---------- */
        function focusRing() {
            var items = [toggle];
            var list = $$('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', panel);
            for (var i = 0; i < list.length; i++) {
                if (list[i].offsetParent !== null) items.push(list[i]);
            }
            return items;
        }

        function openNav() {
            if (opened) return;
            opened = true;
            panel.classList.add('jinyu-menu-open');
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('tabindex', '-1');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', labelClose);
            lockScroll();
            // 焦点交给面板本身：读屏能进入对话框，也不会像聚焦首个链接那样画出一圈描边
            panel.focus({ preventScroll: true });
        }

        function closeNav(returnFocus) {
            if (!opened) return;
            opened = false;
            panel.classList.remove('jinyu-menu-open');
            panel.removeAttribute('role');
            panel.removeAttribute('aria-modal');
            panel.removeAttribute('tabindex');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', labelOpen);
            unlockScroll();
            if (returnFocus !== false) toggle.focus();
        }

        /* ---------- 交互 ---------- */
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (opened) closeNav(); else openNav();
        });

        panel.addEventListener('click', function (e) {
            var subBtn = e.target.closest ? e.target.closest('.jinyu-sub-toggle') : null;
            if (subBtn) {
                e.preventDefault();
                var li = subBtn.parentElement;
                var isOpen = li.classList.toggle('jinyu-sub-open');
                subBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                return;
            }
            // 点了菜单里的链接：关闭面板让新页面/锚点露出来
            if (e.target.closest('a')) closeNav(false);
        });

        doc.addEventListener('keydown', function (e) {
            if (!opened) return;
            if (e.key === 'Escape') { closeNav(); return; }
            if (e.key !== 'Tab') return;
            var ring = focusRing();
            if (!ring.length) return;
            var first = ring[0], last = ring[ring.length - 1];
            if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
        });

        /* ---------- 桌面自适应：平铺 + 流体缩放 + 工具区兜底 ----------
           桌面端（≥1241）菜单始终平铺，绝不藏项、不切汉堡。空间不够时按三级策略自适应：
           1) 流体缩放：菜单字号与项间距沿 14px→13px / 2px→1px 平滑收缩（CSS 变量 --jinyu-nav-fs / --jinyu-nav-gap 驱动），
              中间宽度区间平滑过渡，无「突然多一个更多」的跳变；
           2) 工具区兜底：缩到地板仍放不下时，给 <html> 加 .jinyu-tools-compact，收起用户名/下拉箭头（只留头像）、
              隐藏社交图标，把固定宽度的工具区让出来，菜单可再平铺一截；
           3) 极端兜底：compact + 地板字号仍放不下（菜单项极多）时，菜单整条可横向滚动（隐藏滚动条），项全可见、可横向拖看。
           移动端（≤1240）保持汉堡浮层：fitNav 先清理所有内联样式/类，交回 CSS 媒体查询的浮层。
           JS 不可用时降级：菜单按 14px 平铺（可能裁剪），不顶飞工具区。 */
        var navEl = panel.querySelector('.jinyu-nav');
        var FS_BASE = 14, FS_MIN = 13, GAP_BASE = 2, GAP_MIN = 1;

        function setFluid(fs, gap) {
            navEl.style.setProperty('--jinyu-nav-fs', fs + 'px');
            navEl.style.setProperty('--jinyu-nav-gap', gap + 'px');
        }
        function resetFluid() {
            navEl.style.removeProperty('--jinyu-nav-fs');
            navEl.style.removeProperty('--jinyu-nav-gap');
            navEl.classList.remove('jinyu-nav-scroll');
            navEl.style.overflowX = '';
            navEl.style.maxWidth = '';
            root.classList.remove('jinyu-tools-compact');
        }

        // 测菜单自然宽（脱离 flex 收缩，按当前字号/间距渲染 max-content）；调用期间临时改 width，结束即还原
        function measureNav() {
            var prevW = navEl.style.width, prevMin = navEl.style.minWidth, prevOf = navEl.style.overflow;
            navEl.style.width = 'max-content';
            navEl.style.minWidth = '0';
            navEl.style.overflow = 'visible';
            var w = navEl.getBoundingClientRect().width;
            navEl.style.width = prevW;
            navEl.style.minWidth = prevMin;
            navEl.style.overflow = prevOf;
            return w;
        }

        // 从基础字号向下缩到地板，能放进 avail 返回 true（期间已写入最终字号/间距）
        function shrinkToFit(avail) {
            var fs = FS_BASE, gap = GAP_BASE;
            setFluid(fs, gap);
            if (measureNav() <= avail) return true;
            while (fs > FS_MIN || gap > GAP_MIN) {
                fs = Math.max(FS_MIN, fs - 0.5);
                gap = Math.max(GAP_MIN, gap - 0.5);
                setFluid(fs, gap);
                if (measureNav() <= avail) return true;
            }
            return false;
        }

        function computeAvailable() {
            var inner = doc.querySelector('.jinyu-header-inner');
            var logo = doc.querySelector('.jinyu-logo');
            var tools = doc.querySelector('.jinyu-header-tools');
            if (!inner || !logo || !tools) return 0;
            var ics = getComputedStyle(inner);
            var contentW = inner.clientWidth - (parseFloat(ics.paddingLeft) || 0) - (parseFloat(ics.paddingRight) || 0);
            var innerGap = parseFloat(ics.columnGap) || 0;
            var panelGap = parseFloat(getComputedStyle(panel).columnGap) || parseFloat(getComputedStyle(panel).gap) || 0;
            // 内联模式横向占宽：Logo + 菜单 + 工具区，及 logo↔panel、panel↔tools 两个 inner 列间距 + panel 内 nav↔tools 间距
            return contentW - logo.getBoundingClientRect().width - tools.getBoundingClientRect().width
                - innerGap * 2 - panelGap;
        }

        function fitNav() {
            if (!mqDesktop.matches) {
                resetFluid();              // 移动端：清理内联样式与类，交回汉堡浮层
                return;
            }
            if (opened) closeNav(false);   // 浮层（移动端）切回桌面时收起打开的菜单
            resetFluid();
            var avail = computeAvailable();
            if (shrinkToFit(avail)) return;                 // 1) 仅靠流体缩放即可放下
            root.classList.add('jinyu-tools-compact');      // 2) 工具区兜底：收起用户名/社交，再试
            avail = computeAvailable();
            if (shrinkToFit(avail)) return;
            // 3) 极端兜底：整条横向滚动，项全可见（隐藏滚动条）
            navEl.classList.add('jinyu-nav-scroll');
            navEl.style.maxWidth = avail + 'px';
        }

        var fitTimer = 0;
        function onFitResize() {
            clearTimeout(fitTimer);
            fitTimer = setTimeout(fitNav, 100);
        }
        window.addEventListener('resize', onFitResize);
        window.addEventListener('orientationchange', onFitResize);
        window.addEventListener('load', fitNav);             // Logo 等资源到位后再校一次
        if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(fitNav);
        if (mqDesktop.addEventListener) mqDesktop.addEventListener('change', fitNav);
        else if (mqDesktop.addListener) mqDesktop.addListener(fitNav);
        fitNav();
    }

    /* ======================================================================
       模块：搜索弹窗
       ====================================================================== */
    function search() {
        var openBtn = $('[data-jinyu-search-toggle]');
        var mask = $('.jinyu-search-mask');
        if (!openBtn || !mask) return;

        var input = mask.querySelector('input[name="s"]');

        function open() {
            mask.hidden = false;
            if (input) input.focus();
            doc.body.style.overflow = 'hidden';
        }
        function close() {
            mask.hidden = true;
            doc.body.style.overflow = '';
        }

        openBtn.addEventListener('click', open);
        mask.addEventListener('click', function (e) {
            if (e.target === mask || e.target.closest('[data-jinyu-search-close]')) close();
        });
        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !mask.hidden) close();
        });
    }

    /* ======================================================================
       模块：阅读进度条
       ====================================================================== */
    function readProgress() {
        if (!CFG.read_progress_enable) return;
        var bar = $('.jinyu-read-bar');
        if (!bar) return;
        var inner = $('.jinyu-read-inner', bar);
        if (!inner) return;

        Util.onScroll(function () {
            var h = doc.documentElement;
            var max = h.scrollHeight - h.clientHeight;
            var pct = max > 0 ? (h.scrollTop / max) * 100 : 0;
            inner.style.width = Math.min(100, Math.max(0, pct)) + '%';
        });
    }

    /* ======================================================================
       模块：阅读进度提示（已读 X% · 预计还需 Y 分钟）
       基于正文内容范围计算，比整页滚动更准确；浮于左下角，避开右下滚动工具组。
       ====================================================================== */
    function readEstimate() {
        if (!CFG.read_progress_enable) return;
        var content = $('.jinyu-article-content');
        if (!content) return;
        var totalMin = parseInt(content.getAttribute('data-jinyu-read-min'), 10);
        if (!totalMin || isNaN(totalMin)) return;

        var pill = doc.createElement('div');
        pill.className = 'jinyu-read-estimate';
        pill.setAttribute('aria-live', 'polite');
        pill.innerHTML = '<i class="fa-regular fa-clock" aria-hidden="true"></i>' +
            '<span class="jinyu-read-estimate-text"></span>';
        doc.body.appendChild(pill);
        var label = $('.jinyu-read-estimate-text', pill);

        function compute() {
            var rect = content.getBoundingClientRect();
            var vh = window.innerHeight || doc.documentElement.clientHeight;
            // 已滚过正文底部的比例：内容进入视口下沿 → 离开视口下沿
            var passed = vh - rect.top;
            var frac = rect.height > 0 ? passed / rect.height : 0;
            if (frac < 0) frac = 0;
            if (frac > 1) frac = 1;
            return frac;
        }

        function update() {
            var frac = compute();
            var pct = Math.round(frac * 100);
            if (pct >= 100) {
                label.textContent = _t('readDone', '已读完 ✓');
                pill.classList.add('jinyu-done');
            } else {
                pill.classList.remove('jinyu-done');
                var remain = totalMin * (1 - frac);
                var remainTxt = remain < 1 ? _t('lessThanMinute', '不到 1 分钟') : (_t('aboutMinutes', '约 %d 分钟').replace('%d', Math.ceil(remain)));
                label.textContent = _t('readProgress', '已读 %d% · 还需 %s').replace('%d', pct).replace('%s', remainTxt);
            }
        }

        function onScroll() {
            var y = window.scrollY || doc.documentElement.scrollTop;
            pill.classList.toggle('jinyu-visible', y > 400);
            update();
        }

        Util.onScroll(onScroll);
        onScroll();
    }

    /* ======================================================================
       模块：回到顶部 / 去底部（悬浮工具组，复用模板已有按钮，不重复注入）
       ====================================================================== */
    function backTop() {
        if (!CFG.back_top_enable) return;
        var group = $('.jinyu-scroll-util');
        var topBtn = $('.jinyu-back-top');
        var bottomBtn = $('.jinyu-go-bottom');
        if (!group || !topBtn) return;

        Util.onScroll(function () {
            var y = window.scrollY || doc.documentElement.scrollTop;
            var docH = doc.documentElement.scrollHeight;
            var winH = window.innerHeight;
            var nearBottom = (y + winH) >= (docH - 48);
            // 滚过一段距离才整体浮现
            group.classList.toggle('jinyu-visible', y > 400);
            group.setAttribute('aria-hidden', y > 400 ? 'false' : 'true');
            // 已贴近底部时隐藏「去底部」
            if (bottomBtn) bottomBtn.classList.toggle('jinyu-hidden', nearBottom);
        });

        topBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
        });

        if (bottomBtn) {
            bottomBtn.addEventListener('click', function () {
                window.scrollTo({ top: doc.documentElement.scrollHeight, behavior: reduceMotion ? 'auto' : 'smooth' });
            });
        }
    }

    /* ======================================================================
       模块：文章目录（生成 + 滚动高亮）
       ====================================================================== */
    function toc() {
        if (!CFG.toc_enable) return;
        var content = $('.jinyu-article-content');
        if (!content) return;

        var depth = CFG.toc_depth || 3;
        var sel = 'h2';
        if (depth >= 3) sel += ', h3';
        if (depth >= 4) sel += ', h4';
        var headings = $$(sel, content);
        // 门槛降到 2：单节长文也有目录可用
        if (headings.length < 2) return;

        var wrap = doc.createElement('div');
        wrap.className = 'jinyu-toc';
        wrap.innerHTML = '<div class="jinyu-toc-title">文章目录' +
            '<button type="button" class="jinyu-toc-toggle" aria-label="展开/收起目录"><i class="fa-solid fa-chevron-down"></i></button></div>' +
            '<ul class="jinyu-toc-list"></ul>';
        var list = $('.jinyu-toc-list', wrap);
        var titleEl = $('.jinyu-toc-title', wrap);

        var ids = [];
        headings.forEach(function (h, i) {
            if (!h.id) h.id = 'jinyu-h-' + i;
            ids.push(h.id);
            var li = doc.createElement('li');
            if (h.tagName === 'H3') li.className = 'jinyu-toc-sub';
            var dot = doc.createElement('span');
            dot.className = 'jinyu-toc-dot';
            var a = doc.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent;
            li.appendChild(dot);
            li.appendChild(a);
            list.appendChild(li);
        });

        // 目录作为与右侧小工具同级的独立左列，插到 .jinyu-main-wrap 内、文章卡片之外
        var mainWrap = content.closest('.jinyu-main-wrap') || (content.parentNode && content.parentNode.parentNode);
        if (mainWrap) {
            var tocRef = mainWrap.querySelector('.jinyu-content') || mainWrap.firstChild;
            mainWrap.insertBefore(wrap, tocRef);
            mainWrap.classList.add('jinyu-has-toc');
        } else {
            content.parentNode.insertBefore(wrap, content);
        }

        var links = $$('a', list);
        var items = $$('li', list);

        list.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a) return;
            e.preventDefault();
            var id = a.getAttribute('href').slice(1);
            var target = doc.getElementById(id);
            if (target) target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            if (history.replaceState) history.replaceState(null, '', '#' + id);
        });

        // 移动端：标题整条可点击折叠/展开
        titleEl.addEventListener('click', function () {
            wrap.classList.toggle('jinyu-toc-open');
        });

        // 滚动高亮 + 章节进度点 + URL hash 同步
        if (!('IntersectionObserver' in window)) return;
        var seen = {};
        var syncActive = function () {
            var cur = -1;
            ids.forEach(function (id, i) { if (seen[id]) cur = i; });
            if (cur < 0) return;
            links.forEach(function (a, i) { a.classList.toggle('jinyu-toc-active', i === cur); });
            items.forEach(function (li, i) { li.classList.toggle('jinyu-toc-done', i <= cur); });
            if (history.replaceState) history.replaceState(null, '', '#' + ids[cur]);
        };
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                seen[entry.target.id] = entry.isIntersecting;
            });
            syncActive();
        }, { rootMargin: '-80px 0px -70% 0px' });

        headings.forEach(function (h) { observer.observe(h); });
    }

    /* ======================================================================
       模块：段落级复制（hover 段落右上角出现复制按钮，仅精确指针设备）
       ====================================================================== */
    function paraCopy() {
        var content = $('.jinyu-article-content');
        if (!content) return;
        if (window.matchMedia && !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        $$('p, blockquote, li, h2, h3, h4, td', content).forEach(function (el) {
            if (el.closest('pre')) return;            // 代码块自带复制按钮
            if (el.dataset.jinyuParaCopy) return;
            el.dataset.jinyuParaCopy = '1';
            el.classList.add('jinyu-para');
            var btn = doc.createElement('button');
            btn.type = 'button';
            btn.className = 'jinyu-para-copy';
            btn.setAttribute('aria-label', '复制本段');
            btn.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i>';
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                Util.copy(el.innerText).then(function () {
                    var old = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>';
                    btn.classList.add('jinyu-copied');
                    setTimeout(function () { btn.innerHTML = old; btn.classList.remove('jinyu-copied'); }, 1400);
                }).catch(function () { Util.toast(_t('copyFailed', '复制失败，请手动选择')); });
            });
            el.appendChild(btn);
        });
    }

    /* ======================================================================
       模块：代码块（标题栏 + 复制）
       ====================================================================== */
    function codeBlock() {
        $$('.jinyu-article-content pre').forEach(function (pre) {
            if (pre.parentNode.classList.contains('jinyu-code-wrap')) return;

            var wrap = doc.createElement('div');
            wrap.className = 'jinyu-code-wrap';
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);

            var bar = doc.createElement('div');
            bar.className = 'jinyu-code-bar';
            bar.innerHTML = '<span class="jinyu-code-dot r"></span><span class="jinyu-code-dot y"></span><span class="jinyu-code-dot g"></span>';
            wrap.insertBefore(bar, pre);

            var btn = doc.createElement('button');
            btn.className = 'jinyu-code-copy';
            btn.type = 'button';
            btn.setAttribute('aria-label', '复制代码');
            btn.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i><span class="jinyu-code-copy-label">复制</span>';
            wrap.appendChild(btn);

            var label = btn.querySelector('.jinyu-code-copy-label');
            btn.addEventListener('click', function () {
                Util.copy(pre.innerText).then(function () {
                    if (label) label.textContent = _t('copied', '已复制');
                    btn.classList.add('jinyu-copied');
                    setTimeout(function () {
                        if (label) label.textContent = _t('copy', '复制');
                        btn.classList.remove('jinyu-copied');
                    }, 1500);
                }).catch(function () {
                    Util.toast(_t('copyFailed', '复制失败，请手动选择'));
                });
            });
        });
    }

    /* ======================================================================
       模块：封面图裂图兜底（把 404 封面替换为渐变占位）
       ====================================================================== */
    function coverFallback() {
        doc.addEventListener('error', function (e) {
            var img = e.target;
            if (!img || !img.classList.contains('jinyu-post-cover-img')) return;
            var wrap = img.closest('.jinyu-post-cover-link') || img.parentNode;
            if (!wrap) return;
            var ph = doc.createElement('div');
            ph.className = 'jinyu-post-cover-ph';
            ph.innerHTML = '<i class="fa-solid fa-fire" aria-hidden="true"></i>';
            wrap.replaceChild(ph, img);
        }, true);
    }

    /* ======================================================================
       模块：图片懒加载兜底（无原生 loading 支持时）
       ====================================================================== */
    function lazyImg() {
        if (!('loading' in HTMLImageElement.prototype)) return;
        $$('img[data-src]').forEach(function (img) {
            img.loading = 'lazy';
            img.src = img.dataset.src;
        img.removeAttribute('data-src');
    });
    }

    /* ======================================================================
       模块：图片 blur-up 淡入
       配合 PHP 端给媒体图 / 正文图加的 .jinyu-blur-img 类与 data-ph（缩略图 LQIP
       占位）。未加载时图隐藏并以模糊占位呈现，加载完成后从模糊淡入清晰。
       ====================================================================== */
    function blurImg() {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return; // 减少动效：不做模糊淡入（CSS 也不隐藏，图直接显示）

        doc.documentElement.classList.add('jinyu-blur-ready');

        function setup(img) {
            if (img.dataset.blurDone) return;
            img.dataset.blurDone = '1';
            var ph = img.getAttribute('data-ph');
            if (ph) img.style.backgroundImage = 'url("' + ph + '")';
            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('is-loaded');
                return;
            }
            img.addEventListener('load', function () { img.classList.add('is-loaded'); });
            // 加载失败也标记已加载，避免占位色永久停留
            img.addEventListener('error', function () { img.classList.add('is-loaded'); });
        }

        $$('img.jinyu-blur-img').forEach(setup);

        // 动态注入内容（pjax / 加载更多 / AJAX）兜底
        if (window.MutationObserver) {
            var mo = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    m.addedNodes.forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        if (n.matches && n.matches('img.jinyu-blur-img')) setup(n);
                        if (n.querySelectorAll) {
                            var list = n.querySelectorAll('img.jinyu-blur-img');
                            for (var i = 0; i < list.length; i++) setup(list[i]);
                        }
                    });
                });
            });
            mo.observe(doc.body, { childList: true, subtree: true });
        }
    }

/* ======================================================================
   模块：点赞 / 收藏
   ====================================================================== */
    function postActions() {
        doc.addEventListener('click', function (e) {
            // 点赞
            var like = e.target.closest('.jinyu-like-btn');
            if (like) {
                e.preventDefault();
                if (like.classList.contains('jinyu-loading')) return;
                var willLike = !like.classList.contains('jinyu-liked');
                like.classList.add('jinyu-loading');
                Util.ajax('jinyu_like', { post_id: like.dataset.postId, action_type: willLike ? 'like' : 'unlike' }, function (err, res) {
                    like.classList.remove('jinyu-loading');
                    if (err || !res || !res.success) {
                        // 已点赞 / 限流 / 网络异常：给出可见反馈，避免“点了没反应”
                        Util.toast((res && res.data) ? res.data : '操作失败，请稍后重试');
                        return;
                    }
                    like.classList.toggle('jinyu-liked', willLike);
                    like.setAttribute('aria-pressed', willLike ? 'true' : 'false');
                    var count = like.querySelector('.jinyu-like-count');
                    if (count) {
                        var from = parseInt(count.textContent, 10) || 0;
                        var to = parseInt(res.data, 10);
                        if (!isNaN(to)) {
                            animateCount(count, from, to);
                            if (willLike) popLike(like); // 仅点赞触发弹跳 + 粒子，取消赞只滚动数字
                        } else {
                            count.textContent = res.data;
                        }
                    }
                });
                return;
            }

            // 收藏（登录用户写服务端，未登录降级到本地）
            var fav = e.target.closest('.jinyu-fav-btn');
            if (fav) {
                e.preventDefault();
                var label = fav.querySelector('.jinyu-fav-label');
                var willFav = !fav.classList.contains('jinyu-faved');

                if (!CFG.logged_in) {
                    applyFavState(fav, label, toggleLocalFav(fav.dataset.postId, fav.dataset.title, willFav));
                    return;
                }

                Util.ajax('jinyu_fav', {
                    post_id: fav.dataset.postId,
                    fav: willFav ? '1' : '0'
                }, function (err, res) {
                    if (err || !res || !res.success) return;
                    applyFavState(fav, label, !!res.data);
                });
            }
        });

        function applyFavState(btn, label, faved) {
            btn.classList.toggle('jinyu-faved', faved);
            if (label) label.textContent = faved ? '已收藏' : '收藏';
        }

        function toggleLocalFav(id, title, willFav) {
            var KEY = 'jinyu_favs';
            var list = [];
            try { list = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { list = []; }
            var idx = -1;
            list.forEach(function (f, i) { if (String(f.id) === String(id)) idx = i; });

            if (willFav && idx < 0) list.push({ id: id, title: title, time: Date.now() });
            if (!willFav && idx >= 0) list.splice(idx, 1);

            try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* 忽略 */ }
            return willFav;
        }

        /* 计数滚动：从旧值缓动到新值（easeOutCubic） */
        function animateCount(el, from, to) {
            if (from === to) { el.textContent = to; return; }
            var dur = 420, start = null;
            function step(ts) {
                if (start === null) start = ts;
                var p = Math.min(1, (ts - start) / dur);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(from + (to - from) * eased);
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = to;
            }
            requestAnimationFrame(step);
        }

        /* 点赞成功：心跳弹跳 + 心形粒子迸发 */
        function popLike(btn) {
            btn.classList.remove('jinyu-like-pop');
            void btn.offsetWidth; // 强制回流以重启动画
            btn.classList.add('jinyu-like-pop');
            setTimeout(function () { btn.classList.remove('jinyu-like-pop'); }, 560);
            spawnParticles(btn);
        }

        function spawnParticles(btn) {
            var icon = btn.querySelector('.fa-heart');
            if (!icon || !doc.createElement) return;
            var br = btn.getBoundingClientRect();
            var ir = icon.getBoundingClientRect();
            var cx = ir.left + ir.width / 2 - br.left;
            var cy = ir.top + ir.height / 2 - br.top;
            for (var i = 0; i < 6; i++) {
                var p = doc.createElement('span');
                p.className = 'jinyu-like-particle';
                p.textContent = '♥';
                var ang = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.1; // 向上扇形
                var dist = 18 + Math.random() * 22;
                p.style.left = cx + 'px';
                p.style.top = cy + 'px';
                p.style.setProperty('--dx', (Math.cos(ang) * dist).toFixed(1) + 'px');
                p.style.setProperty('--dy', (Math.sin(ang) * dist).toFixed(1) + 'px');
                p.style.fontSize = (8 + Math.random() * 8).toFixed(1) + 'px';
                btn.appendChild(p);
                (function (el) {
                    el.addEventListener('animationend', function () { el.remove(); });
                })(p);
            }
        }

        // 已点赞 / 已收藏状态回填
        $$('.jinyu-like-btn').forEach(function (btn) {
            if (CFG.liked_posts && CFG.liked_posts.indexOf(Number(btn.dataset.postId)) > -1) {
                btn.classList.add('jinyu-liked');
            }
        });
        $$('.jinyu-fav-btn').forEach(function (btn) {
            if (CFG.fav_posts && CFG.fav_posts.indexOf(Number(btn.dataset.postId)) > -1) {
                btn.classList.add('jinyu-faved');
                var label = btn.querySelector('.jinyu-fav-label');
                if (label) label.textContent = _t('favorited', '已收藏');
            }
        });

        /* 点赞数水合：页面缓存把 jinyu_likes 烤死在静态 HTML 里，刷新会显示旧值。
           加载时批量拉取真实计数+已赞态覆盖，保证与 DB 一致（文章页按钮 / 列表计数通用）。 */
        var likeNodes = [];
        $$('.jinyu-like-count[data-post-id]').forEach(function (n) {
            likeNodes.push({ node: n, pid: n.dataset.postId });
        });
        $$('.jinyu-like-btn[data-post-id]').forEach(function (btn) {
            var c = btn.querySelector('.jinyu-like-count');
            if (c && !c.hasAttribute('data-post-id')) {
                c.setAttribute('data-post-id', btn.dataset.postId);
                likeNodes.push({ node: c, pid: btn.dataset.postId });
            }
        });
        if (likeNodes.length) {
            var ids = [];
            likeNodes.forEach(function (x) { if (ids.indexOf(x.pid) < 0) ids.push(x.pid); });
            Util.ajax('jinyu_like_state', { ids: ids.join(',') }, function (err, res) {
                if (err || !res || !res.success) return;
                var data = res.data || {};
                likeNodes.forEach(function (x) {
                    var info = data[x.pid];
                    if (!info) return;
                    x.node.textContent = info.count;
                    var btn = x.node.closest('.jinyu-like-btn');
                    if (btn) btn.classList.toggle('jinyu-liked', !!info.liked);
                });
            });
        }
    }

    /* ======================================================================
       模块：分享弹窗
       ====================================================================== */
    var SHARE_TARGETS = {
        weibo: { label: '微博', icon: 'fa-brands fa-weibo', url: function (u, t) { return 'https://service.weibo.com/share/share.php?url=' + encodeURIComponent(u) + '&title=' + encodeURIComponent(t); } },
        qq: { label: 'QQ', icon: 'fa-brands fa-qq', url: function (u, t) { return 'https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(u) + '&title=' + encodeURIComponent(t); } },
        qzone: { label: '空间', icon: 'fa-solid fa-star', url: function (u, t) { return 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' + encodeURIComponent(u) + '&title=' + encodeURIComponent(t); } },
        wechat: { label: '微信', icon: 'fa-brands fa-weixin', url: null },
        link: { label: '复制链接', icon: 'fa-solid fa-link', url: null }
    };

    function share() {
        doc.addEventListener('click', function (e) {
            if (!e.target.closest('[data-jinyu-share]')) return;

            var url = location.href;
            var title = doc.title;

            var mask = doc.createElement('div');
            mask.className = 'jinyu-mask';

            var box = doc.createElement('div');
            box.className = 'jinyu-share-box';
            box.innerHTML = '<div class="jinyu-share-title">分享到</div><div class="jinyu-share-btns"></div>';

            var btns = $('.jinyu-share-btns', box);
            var enabled = (CFG.share_channels || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            Object.keys(SHARE_TARGETS).forEach(function (key) {
                if (enabled.length && enabled.indexOf(key) === -1) return;
                var t = SHARE_TARGETS[key];
                var b = doc.createElement('button');
                b.type = 'button';
                b.setAttribute('data-share', key);
                b.innerHTML = '<i class="' + t.icon + '"></i><span>' + t.label + '</span>';
                btns.appendChild(b);
            });

            box.appendChild(btns);
            mask.appendChild(box);
            doc.body.appendChild(mask);

            function close() { mask.remove(); }
            mask.addEventListener('click', function (ev) { if (ev.target === mask) close(); });

            btns.addEventListener('click', function (ev) {
                var b = ev.target.closest('[data-share]');
                if (!b) return;
                var key = b.getAttribute('data-share');
                var target = SHARE_TARGETS[key];

                if (key === 'wechat') {
                    showWechatShare(url);
                    close();
                    return;
                }
                if (!target.url) {
                    Util.copy(url).then(function () { Util.toast(_t('linkCopied', '链接已复制')); })
                        .catch(function () { window.prompt('复制此链接：', url); });
                } else {
                    window.open(target.url(url, title), '_blank', 'width=600,height=500');
                }
                close();
            });

            doc.addEventListener('keydown', function handler(ev) {
                if (ev.key === 'Escape') { close(); doc.removeEventListener('keydown', handler); }
            });
        });
    }

    /* 微信分享：微信内无法直接拉起分享，给右上角菜单引导；其它环境给文章二维码「扫一扫」 */
    function showWechatShare(pageUrl) {
        var ua = navigator.userAgent || '';
        if (/MicroMessenger/i.test(ua)) {
            var ov = doc.createElement('div');
            ov.className = 'jinyu-poster-fullscreen';
            ov.innerHTML =
                '<div class="jinyu-wechat-guide">' +
                    '<div class="jinyu-wechat-guide-arrow">···</div>' +
                    '<div class="jinyu-wechat-guide-tip">点击右上角 <b>···</b><br>即可分享给好友或朋友圈</div>' +
                '</div>';
            ov.addEventListener('click', function (e) { if (e.target === ov) ov.remove(); });
            doc.body.appendChild(ov);
            return;
        }
        var mask = doc.createElement('div');
        mask.className = 'jinyu-mask';
        mask.innerHTML =
            '<div class="jinyu-modal-card jinyu-wechat-qr-card">' +
                '<div class="jinyu-modal-head">' +
                    '<span class="jinyu-modal-title">微信扫码打开</span>' +
                    '<button type="button" class="jinyu-modal-close" data-jinyu-modal-close aria-label="关闭"><i class="fa-solid fa-xmark"></i></button>' +
                '</div>' +
                '<div class="jinyu-modal-body" style="text-align:center">' +
                    '<div class="jinyu-wechat-qr-box" id="jinyu-wechat-qr"></div>' +
                    '<p class="jinyu-qr-url">用微信「扫一扫」识别上方二维码，即可在微信中打开本文。<br>手机可截屏后于微信内扫本地图片；或关闭后点分享栏「复制链接」粘贴到微信。</p>' +
                '</div>' +
            '</div>';
        mask.addEventListener('click', function (e) { if (e.target === mask || e.target.closest('[data-jinyu-modal-close]')) mask.remove(); });
        doc.body.appendChild(mask);
        try {
            if (typeof window.qrcode === 'function') {
                var qr = window.qrcode(0, 'M');
                qr.addData(pageUrl.split('#')[0]);
                qr.make();
                var n = qr.getModuleCount(), size = 220, cell = size / n;
                var cv = doc.createElement('canvas');
                cv.width = size; cv.height = size;
                var cx = cv.getContext('2d');
                cx.fillStyle = '#ffffff'; cx.fillRect(0, 0, size, size);
                cx.fillStyle = '#111827';
                for (var r = 0; r < n; r++) {
                    for (var c = 0; c < n; c++) { if (qr.isDark(r, c)) cx.fillRect(c * cell, r * cell, cell + .5, cell + .5); }
                }
                var holder = mask.querySelector('#jinyu-wechat-qr');
                if (holder) holder.appendChild(cv);
            }
        } catch (e) { /* 二维码失败不阻断 */ }
    }

    /* ======================================================================
       模块：滚动入场动画
       ====================================================================== */
    function reveal() {
        var els = $$('.jinyu-post-card, .jinyu-widget, .jinyu-relevant-card, .jinyu-single, .jinyu-pagination');
        if (!els.length) return;

        if (reduceMotion || !('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('jinyu-visible'); });
            return;
        }

        var show = function (el) { el.classList.add('jinyu-visible'); };
        var inView = function (el) {
            var r = el.getBoundingClientRect();
            var h = window.innerHeight || doc.documentElement.clientHeight || 0;
            return r.top < h && r.bottom > 0;
        };

        els.forEach(function (el) { el.classList.add('jinyu-reveal'); });

        // 首屏已在视口内的元素下一帧显现：先渲染 opacity:0 再过渡，保留淡入动画；
        // 不依赖 IO 回调，避免某些环境 IO 不触发导致内容永久隐藏
        requestAnimationFrame(function () {
            els.forEach(function (el) { if (inView(el)) show(el); });
        });

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                show(entry.target);
                io.unobserve(entry.target);
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        els.forEach(function (el) { if (!el.classList.contains('jinyu-visible')) io.observe(el); });

        // 安全兜底：load 后与固定延时后强制显现全部，彻底杜绝 IO 失效时内容不可见
        var safety = function () { els.forEach(show); };
        if (doc.readyState === 'complete') setTimeout(safety, 1200);
        else window.addEventListener('load', function () { setTimeout(safety, 400); });
        setTimeout(safety, 2500);
    }

    /* ======================================================================
       模块：卡片 3D 倾斜 / 鼠标光晕（仅在精确指针 + 未降低动效时启用）
       ====================================================================== */
    function pointerEffects() {
        if (reduceMotion || !finePointer) return;

        // 卡片 3D 倾斜：mousemove 高频触发，用 rAF 批处理、仅取最近一次坐标合成，
        // 避免每次指针移动都重排/重绘合成层（原实现每 move 直接写 transform，低端机易卡）
        $$('.jinyu-post-card, .jinyu-relevant-card').forEach(function (card) {
            var queued = false, lx = 0, ly = 0;
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect();
                lx = (e.clientX - r.left) / r.width - 0.5;
                ly = (e.clientY - r.top) / r.height - 0.5;
                if (queued) return;
                queued = true;
                requestAnimationFrame(function () {
                    queued = false;
                    card.style.transform = 'perspective(800px) rotateY(' + (lx * 6) + 'deg) rotateX(' + (-ly * 6) + 'deg) translateY(-4px)';
                });
            }, { passive: true });
            card.addEventListener('mouseleave', function () {
                queued = false;
                card.style.transform = '';
            });
        });

        // 注：原全局鼠标光晕（jinyu-cursor-glow）已移除——纯装饰、无信息量，全站跟随
        // 光斑易显廉价；如需可放在首页 hero 区局部实现，而非挂到 body 全局。
    }

    /* ======================================================================
       模块：头部滚动态
       ====================================================================== */
    function headerScroll() {
        var header = $('.jinyu-header');
        if (!header) return;
        Util.onScroll(function () {
            header.classList.toggle('jinyu-header-scrolled', window.scrollY > 20);
        });
    }

    /* ======================================================================
       模块：加载更多
       ====================================================================== */
    function loadMore() {
        var btn = $('.jinyu-load-more');
        if (!btn) return;

        var page = Number(btn.dataset.page || 1);
        var loading = false;
        var done = false;

        function load() {
            if (loading || done) return;
            loading = true;
            btn.disabled = true;
            btn.textContent = _t('loading', '加载中…');

            Util.ajax('jinyu_load_more', { page: page }, function (err, res) {
                loading = false;
                btn.disabled = false;

                if (err || !res || !res.success) {
                    done = true;
                    btn.textContent = _t('noMore', '没有更多了');
                    btn.disabled = true;
                    return;
                }

                var wrap = $(btn.dataset.target || '.jinyu-post-grid');
                if (wrap && res.data.html) wrap.insertAdjacentHTML('beforeend', res.data.html);

                page = Number(res.data.page || (page + 1));
                btn.dataset.page = page;

                if (res.data.has_more) {
                    btn.textContent = _t('loadMore', '加载更多');
                } else {
                    done = true;
                    btn.textContent = _t('noMore', '没有更多了');
                    btn.disabled = true;
                }
            });
        }

        btn.addEventListener('click', load);

        if (CFG.load_more_infinite && 'IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) load();
            }, { rootMargin: '200px' });
            io.observe(btn);
        }
    }

    /* ======================================================================
       模块：分页页码直达（输入页码跳到指定页）
       ====================================================================== */
    function pageJump() {
        var forms = doc.querySelectorAll('.jinyu-pagination-jump');
        if (!forms.length) return;

        Array.prototype.forEach.call(forms, function (form) {
            if (!form.dataset.base) return;
            var btn   = $('.jinyu-jump-btn', form);
            var input = $('.jinyu-page-jump-input', form);
            if (!input) return;

            var max = parseInt(input.getAttribute('max'), 10) || parseInt(form.dataset.total, 10) || 0;
            var cur = parseInt(form.dataset.current, 10) || 0;
            var leaveTimer = null;

            function clamp(n) {
                if (!n || isNaN(n) || n < 1) return 1;
                if (max && n > max) return max;
                return n;
            }

            function open() {
                form.classList.add('is-open');
                clearTimeout(leaveTimer);
                // 等过渡起来再聚焦，避免 panel 尚为 hidden 时 focus 失败
                leaveTimer = setTimeout(function () {
                    try { input.focus(); input.select(); } catch (e) {}
                }, 120);
            }

            function close() {
                clearTimeout(leaveTimer);
                form.classList.remove('is-open');
                input.value = '';
                // 收起时让输入框失焦，否则 :focus-within 会撑着面板不收
                try { if (doc.activeElement === input) input.blur(); } catch (e) {}
            }

            // 点击按钮（触屏 / 无 hover 场景）展开并聚焦
            if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); open(); });

            // 鼠标移入整个区域即展开（含移到按钮上）
            form.addEventListener('mouseenter', open);

            // 鼠标移出：延迟收回，给「移动到 panel 上」留出时间。
            // 注意：不能因为输入框仍聚焦就跳过收回，否则 hover 过后面板永远展开。
            form.addEventListener('mouseleave', function () {
                clearTimeout(leaveTimer);
                leaveTimer = setTimeout(function () {
                    form.classList.remove('is-open');
                    input.value = '';
                    try { if (doc.activeElement === input) input.blur(); } catch (e) {}
                }, 180);
            });

            // 输入过程中实时纠正到合法区间
            input.addEventListener('input', function () {
                var v = input.value.replace(/[^0-9]/g, '');
                if (v !== input.value) input.value = v;
                var n = parseInt(v, 10);
                if (!isNaN(n) && n > max) input.value = String(max);
                if (!isNaN(n) && n < 1)  input.value = '1';
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    close();
                    if (btn) { try { btn.focus(); } catch (e2) {} }
                }
            });

            // 失焦即纠正越界/非法输入，避免输入框长期显示无效页码
            input.addEventListener('blur', function () {
                input.value = clamp(parseInt(input.value, 10));
            });

            // 触屏：点表单外部收回
            doc.addEventListener('click', function (e) {
                if (!form.contains(e.target)) close();
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var raw = input.value.trim();
                if (!raw) {
                    try { input.focus(); } catch (e2) {}
                    flash(form, '请输入页码');
                    return;
                }
                var n = clamp(parseInt(raw, 10));
                input.value = n;
                if (n === cur) { flash(form, '已是第 ' + n + ' 页'); return; }
                location.assign(form.dataset.base.replace('__PAGE__', n));
            });

            function flash(el, text) {
                var old = el.querySelector('.jinyu-jump-tip');
                if (old) old.remove();
                var t = doc.createElement('span');
                t.className = 'jinyu-jump-tip';
                t.textContent = text;
                el.appendChild(t);
                requestAnimationFrame(function () {
                    t.style.opacity = '1';
                    t.style.transform = 'translateX(-50%) translateY(-4px)';
                });
                setTimeout(function () {
                    t.style.opacity = '0';
                    setTimeout(function () { try { t.remove(); } catch (e2) {} }, 240);
                }, 1500);
            }
        });
    }

    /* ======================================================================
       模块：短代码交互（选项卡 / 折叠）
       ====================================================================== */
    function shortcodes() {
        doc.addEventListener('click', function (e) {
            // 代码块复制（pre 短代码）
            var preCopy = e.target.closest('[data-copy]');
            if (preCopy) {
                var preBox = preCopy.closest('.jinyu-pre');
                var codeEl = preBox && preBox.querySelector('.jinyu-pre-code');
                if (codeEl) {
                    Util.copy(codeEl.innerText).then(function () {
                        preCopy.textContent = _t('copied', '已复制');
                        preCopy.classList.add('jinyu-copied');
                        setTimeout(function () {
                            preCopy.textContent = _t('copy', '复制');
                            preCopy.classList.remove('jinyu-copied');
                        }, 1500);
                    }).catch(function () {
                        Util.toast(_t('copyFailed', '复制失败，请手动选择'));
                    });
                }
                return;
            }

            // 选项卡
            var tab = e.target.closest('.jinyu-tab-btn');
            if (tab) {
                var nav = tab.closest('.jinyu-tabs');
                if (!nav) return;
                var idx = Array.prototype.indexOf.call(nav.querySelectorAll('.jinyu-tab-btn'), tab);
                $$('.jinyu-tab-btn', nav).forEach(function (b, i) { b.classList.toggle('jinyu-tab-active', i === idx); });
                $$('.jinyu-tab-pane', nav).forEach(function (p, i) { p.classList.toggle('jinyu-tab-active', i === idx); });
                return;
            }
        });
    }

    /* ======================================================================
       模块：Ajax 评论
       ====================================================================== */
    function ajaxComment() {
        var form = $('.jinyu-comment-respond form, #commentform');
        if (!form) return;

        var tip = doc.createElement('div');
        tip.className = 'jinyu-comment-tip';
        form.appendChild(tip);

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var submit = form.querySelector('input[type="submit"]');
            var originText = submit ? submit.value : '';
            if (submit) { submit.disabled = true; submit.value = _t('submitting', '提交中…'); }

            var payload = new FormData(form);
            payload.append('action', 'jinyu_comment');
            if (CFG.nonce) payload.append('_ajax_nonce', CFG.nonce);

            fetch(CFG.ajax_url, { method: 'POST', credentials: 'same-origin', body: payload })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (submit) { submit.disabled = false; submit.value = originText; }

                    if (!res || !res.success) {
                        tip.className = 'jinyu-comment-tip jinyu-comment-tip-err';
                        tip.textContent = (res && res.data) ? res.data : _t('submitFailed', '提交失败，请稍后重试');
                        return;
                    }

                    tip.className = 'jinyu-comment-tip jinyu-comment-tip-ok';
                    tip.textContent = res.data.message || _t('commentPosted', '评论已提交');

                    var list = $('.jinyu-comment-list');
                    if (list && res.data.html) {
                        list.insertAdjacentHTML('beforeend', res.data.html);
                    }
                    form.querySelector('textarea[name="comment"]').value = '';
                })
                .catch(function () {
                    if (submit) { submit.disabled = false; submit.value = originText; }
                    tip.className = 'jinyu-comment-tip jinyu-comment-tip-err';
                    tip.textContent = _t('networkError', '网络异常，请稍后重试');
                });
        });
    }

    /* ======================================================================
       模块：头部用户菜单
       ====================================================================== */
    function userMenu() {
        var menu = $('[data-jinyu-user-menu]');
        if (!menu) return;
        var toggle = $('[data-jinyu-user-toggle]', menu);
        var drop = $('[data-jinyu-user-drop]', menu);
        if (!toggle || !drop) return;

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = drop.hidden;
            drop.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        });
        doc.addEventListener('click', function (e) {
            if (!menu.contains(e.target) && !drop.hidden) {
                drop.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ======================================================================
       模块：登录 / 注册 / 找回密码弹窗
       ====================================================================== */
    function auth() {
        userMenu();

        var modal = $('.jinyu-auth-mask');
        if (!modal) return;

        // 打开
        $$('[data-jinyu-auth-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openAuth(modal, btn.getAttribute('data-jinyu-auth-open') || 'login');
            });
        });

        // 关闭：点遮罩 / 关闭按钮 / Esc
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('[data-jinyu-auth-close]')) {
                closeAuth(modal);
            }
        });
        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeAuth(modal);
        });

        // Tab 切换
        $$('[data-jinyu-auth-tab]', modal).forEach(function (t) {
            t.addEventListener('click', function () {
                switchAuthTab(modal, t.getAttribute('data-jinyu-auth-tab'));
            });
        });

        // 验证码：刷新 / 点图换一张
        modal.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-jinyu-captcha-refresh], .jinyu-captcha-img');
            if (trigger) refreshCaptcha(trigger.closest('[data-jinyu-captcha]'));
        });

        // 提交
        $$('.jinyu-auth-form', modal).forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitAuth(form);
            });
        });

        function openAuth(m, tab) {
            doc.body.style.overflow = 'hidden';
            m.hidden = false;
            switchAuthTab(m, tab || 'login');
        }
        function closeAuth(m) {
            m.hidden = true;
            doc.body.style.overflow = '';
        }
        function switchAuthTab(m, tab) {
            $$('[data-jinyu-auth-tab]', m).forEach(function (t) {
                t.classList.toggle('is-active', t.getAttribute('data-jinyu-auth-tab') === tab);
            });
            $$('.jinyu-auth-form', m).forEach(function (f) {
                f.hidden = f.getAttribute('data-jinyu-auth-pane') !== tab;
            });
            var pane = $('.jinyu-auth-form[data-jinyu-auth-pane="' + tab + '"]', m);
            var cap = pane ? $('[data-jinyu-captcha]', pane) : null;
            if (cap && cap.querySelector('.jinyu-captcha-img').hidden) refreshCaptcha(cap);
        }
        function refreshCaptcha(cap) {
            if (!cap) return;
            var img = cap.querySelector('.jinyu-captcha-img');
            img.hidden = false;
            img.src = cap.getAttribute('data-jinyu-captcha-src') + '&r=' + Date.now();
        }
        function submitAuth(form) {
            var tip = $('[data-jinyu-auth-tip]', form);
            var btn = form.querySelector('.jinyu-auth-submit');
            var pane = form.getAttribute('data-jinyu-auth-pane');
            var action = pane === 'login' ? 'jinyu_login'
                : pane === 'register' ? 'jinyu_register' : 'jinyu_reset_password';

            var data = {};
            Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (el) {
                if (el.name) data[el.name] = el.value;
            });

            Util.showTip(tip, '');
            if (btn) { btn.disabled = true; var label = btn.textContent; btn.textContent = _t('processing', '处理中…'); }

            Util.ajax(action, data, function (err, res) {
                if (btn) { btn.disabled = false; btn.textContent = label; }
                if (err || !res || !res.success) {
                    Util.showTip(tip, (res && res.data) ? res.data : '请求失败，请稍后重试', false);
                    var cap = form.querySelector('[data-jinyu-captcha]');
                    if (cap) refreshCaptcha(cap);
                    return;
                }
                Util.showTip(tip, res.data.message || _t('opSuccess', '操作成功'), true);
                setTimeout(function () {
                    if (res.data && res.data.redirect) location.href = res.data.redirect;
                    else location.reload();
                }, 600);
            });
        }
    }

    /* ======================================================================
       模块：用户中心表单（资料 / 密码 / 投稿 / 头像）
       ====================================================================== */
    function userForms() {
        var wrap = $('.jinyu-user-center');
        if (!wrap) return;

        // 资料更新
        var pf = $('[data-jinyu-profile]', wrap);
        if (pf) pf.addEventListener('submit', function (e) {
            e.preventDefault();
            var tip = $('[data-jinyu-profile-tip]', pf);
            var fd = new FormData(pf);
            Util.ajaxForm('jinyu_update_profile', fd, function (err, res) {
                if (err || !res || !res.success) {
                    Util.showTip(tip, (res && res.data) ? res.data : '保存失败', false);
                    return;
                }
                Util.showTip(tip, res.data.message, true);
                var url = (fd.get('avatar_url') || '').toString();
                if (url) { var p = $('.jinyu-avatar-preview', wrap); if (p) p.src = url; }
            });
        });

        // 修改密码
        var pw = $('[data-jinyu-password]', wrap);
        if (pw) pw.addEventListener('submit', function (e) {
            e.preventDefault();
            var tip = $('[data-jinyu-password-tip]', pw);
            Util.ajaxForm('jinyu_update_password', new FormData(pw), function (err, res) {
                if (err || !res || !res.success) {
                    Util.showTip(tip, (res && res.data) ? res.data : '修改失败', false);
                } else {
                    Util.showTip(tip, res.data.message, true);
                    pw.reset();
                }
            });
        });

        // 投稿
        var sp = $('[data-jinyu-submit-post]', wrap);
        if (sp) sp.addEventListener('submit', function (e) {
            e.preventDefault();
            var tip = $('[data-jinyu-submit-tip]', sp);
            if (tip) Util.showTip(tip, '', null);

            // —— 提交前前端字段校验（避免无谓请求 + 即时反馈）——
            var title = sp.querySelector('[name="post_title"]');
            var content = sp.querySelector('[name="post_content"]');
            var firstError = '';
            $$('.jinyu-field.is-error', sp).forEach(function (f) { f.classList.remove('is-error'); });
            if (content) content.classList.remove('is-error');

            var t = (title && title.value || '').trim();
            if (t.length < 4 || t.length > 100) {
                if (title) { var tf = title.closest('.jinyu-field'); if (tf) tf.classList.add('is-error'); }
                firstError = '标题需 4-100 个字符';
            }
            var c = (content && content.value || '').trim();
            if (c.length < 20) {
                if (content) content.classList.add('is-error');
                firstError = firstError || '正文至少 20 个字符';
            }
            if (firstError) {
                if (tip) Util.showTip(tip, firstError, false);
                var bad = sp.querySelector('.is-error');
                if (bad) bad.focus();
                return;
            }

            var btn = sp.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; var l = btn.textContent; btn.textContent = _t('submitting', '提交中…'); }
            Util.ajaxForm('jinyu_submit_post', new FormData(sp), function (err, res) {
                if (btn) { btn.disabled = false; btn.textContent = l; }
                if (err || !res || !res.success) {
                    if (tip) Util.showTip(tip, (res && res.data) ? res.data : _t('submitFailed', '提交失败，请稍后再试'), false);
                    return;
                }
                // 成功 → 渲染「审核中」成功面板，完成投稿闭环
                showSubmitSuccess(sp, (res.data && res.data.message) || '投稿成功');
            });
        });

        // 投稿成功面板：展示「审核中」状态并给出去「我的文章」查看进度的入口
        function showSubmitSuccess(form, msg) {
            var card = doc.createElement('div');
            card.className = 'jinyu-submit-success';
            card.innerHTML =
                '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>' +
                '<h3>' + escHtml(msg) + '</h3>' +
                '<p>您的文章已进入审核队列，通过后将自动发布。期间可前往「我的文章」查看审核进度。</p>' +
                '<div class="jinyu-submit-success-actions">' +
                  '<a class="jinyu-btn jinyu-btn-primary" href="' + escHtml(jinyuTabUrl('posts')) + '">查看我的投稿</a>' +
                  '<button type="button" class="jinyu-btn" data-jinyu-submit-again>再投一篇</button>' +
                '</div>';
            form.style.display = 'none';
            if (form.parentNode) form.parentNode.insertBefore(card, form.nextSibling);
            var again = card.querySelector('[data-jinyu-submit-again]');
            if (again) again.addEventListener('click', function () {
                if (card.parentNode) card.parentNode.removeChild(card);
                form.style.display = '';
                form.reset();
                var tip = form.querySelector('[data-jinyu-submit-tip]');
                if (tip) Util.showTip(tip, '', null);
            });
        }

        // 同页切换用户中心页签（server-render 的 tab），带上当前 query 其它参数
        function jinyuTabUrl(tab) {
            try {
                var u = new URL(location.href);
                u.searchParams.set('tab', tab);
                return u.pathname + u.search;
            } catch (e) {
                return location.pathname + '?tab=' + encodeURIComponent(tab);
            }
        }

        // 撤回 / 删除待审核投稿
        $$('.jinyu-post-del', wrap).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pid = btn.getAttribute('data-post-id');
                if (!pid) return;
                if (!window.confirm('确定撤回该投稿吗？撤回后不可恢复。')) return;
                btn.disabled = true;
                var fd = new FormData();
                fd.append('post_id', pid);
                Util.ajaxForm('jinyu_delete_post', fd, function (err, res) {
                    if (err || !res || !res.success) {
                        btn.disabled = false;
                        Util.toast((res && res.data) ? res.data : '操作失败');
                        return;
                    }
                    var item = btn.closest('.jinyu-user-post-item');
                    if (item) item.remove();
                    Util.toast(res.data.message);
                });
            });
        });

        // 头像上传
        var pick = $('[data-jinyu-avatar]', wrap);
        if (pick) pick.addEventListener('change', function () {
            if (!pick.files || !pick.files[0]) return;
            var fd = new FormData();
            fd.append('avatar', pick.files[0]);
            var tip = $('[data-jinyu-profile-tip]', wrap);
            Util.ajaxForm('jinyu_upload_avatar', fd, function (err, res) {
                if (err || !res || !res.success) {
                    Util.showTip(tip, (res && res.data) ? res.data : '上传失败', false);
                } else {
                    Util.showTip(tip, res.data.message, true);
                    if (res.data.url) {
                        var p = $('.jinyu-avatar-preview', wrap);
                        if (p) p.src = res.data.url;
                        var urlInput = $('input[name="avatar_url"]', wrap);
                        if (urlInput) urlInput.value = res.data.url;
                    }
                }
            });
        });
    }

    /* ======================================================================
       启动
       ====================================================================== */
    /* ======================================================================
       模块：通用模态框（海报 / 二维码 / 打赏 复用）
       ====================================================================== */
    function openModal(opts) {
        closeModal();
        var m = doc.createElement('div');
        m.id = 'jinyu-generic-modal';
        m.className = 'jinyu-mask jinyu-generic-modal' + (opts.className ? ' ' + opts.className : '');
        m.setAttribute('role', 'dialog');
        m.setAttribute('aria-modal', 'true');
        m.innerHTML =
            '<div class="jinyu-modal-card">' +
                '<div class="jinyu-modal-head">' +
                    '<span class="jinyu-modal-title">' + (opts.title || '') + '</span>' +
                    '<button type="button" class="jinyu-modal-close" data-jinyu-modal-close aria-label="' + (CFG.i18n && CFG.i18n.close ? CFG.i18n.close : '关闭') + '"><i class="fa-solid fa-xmark"></i></button>' +
                '</div>' +
                '<div class="jinyu-modal-body">' + (opts.body || '') + '</div>' +
            '</div>';
        doc.body.appendChild(m);
        doc.body.style.overflow = 'hidden';
        m.addEventListener('click', function (e) {
            if (e.target === m || e.target.closest('[data-jinyu-modal-close]')) closeModal();
        });
        doc.addEventListener('keydown', escClose);
        return m;
    }
    function escClose(e) { if (e.key === 'Escape') closeModal(); }
    function closeModal() {
        var m = $('#jinyu-generic-modal');
        if (m) m.remove();
        doc.body.style.overflow = '';
        doc.removeEventListener('keydown', escClose);
    }

    /* ======================================================================
       模块：文章海报（前端 Canvas 生成）
       服务端 GD 无中文字体必乱码（服务器无法保证装字体），改由访客浏览器
       渲染：中文完美、封面不变形、标题自动换行、二维码直接画进海报。
       ====================================================================== */
    function poster() {
        $$('[data-jinyu-poster]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pid = btn.getAttribute('data-post-id');
                var m = openModal({ title: '文章海报', className: 'jinyu-poster-modal', body: '<div class="jinyu-modal-loading"><i class="fa-solid fa-spinner fa-spin"></i> 生成中…</div>' });
                var body = m.querySelector('.jinyu-modal-body');

                var tEl = doc.querySelector('.jinyu-article-title') || doc.querySelector('h1');
                var coverSrc = btn.getAttribute('data-cover') || '';
                if (!coverSrc) {
                    var og = doc.querySelector('meta[property="og:image"]');
                    coverSrc = og ? og.getAttribute('content') : '';
                }

                loadImg(coverSrc, 8000).then(function (cover) {
                    var canvas;
                    try {
                        canvas = drawPoster((tEl ? tEl.textContent : doc.title).trim(), cover);
                    } catch (err) {
                        body.innerHTML = '<p class="jinyu-empty">海报生成失败：' + (err && err.message ? err.message : '渲染错误') + '</p>';
                        return;
                    }
                    body.innerHTML = '';

                    // 导出为 dataURL（同源封面不会污染画布；跨域无 CORS 时封面已降级为 null）
                    var dataUrl = '';
                    try { dataUrl = canvas.toDataURL('image/png'); } catch (e) { dataUrl = ''; }

                    // 用真实 <img> 展示：避开 iOS/微信 在 backdrop-filter 祖先里渲染 <canvas> 空白的坑，且支持长按保存
                    var img = doc.createElement('img');
                    img.className = 'jinyu-poster-img';
                    img.alt = '文章海报';
                    if (dataUrl) { img.src = dataUrl; } else { canvas.className = 'jinyu-poster-img'; body.appendChild(canvas); }

                    var actions = doc.createElement('div');
                    actions.className = 'jinyu-poster-actions';

                    var ua = navigator.userAgent || '';
                    var isMobile = /Mobi|Android|iPhone|iPad|iPod|HarmonyOS|Windows Phone/i.test(ua) ||
                                  (('ontouchstart' in window) && Math.min(screen.width, screen.height) < 820);

                    if (dataUrl && !isMobile) {
                        // 桌面端：原生下载
                        var dl = doc.createElement('a');
                        dl.className = 'jinyu-btn jinyu-btn-primary';
                        dl.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i> 下载海报';
                        dl.setAttribute('download', 'poster-' + pid + '.png');
                        dl.href = dataUrl;
                        actions.appendChild(dl);
                    } else if (dataUrl) {
                        // 移动端（含华为 QQ 浏览器等忽略 <a download> 的环境）：
                        // 全屏放大图片，用户长按即可保存到相册，所有手机浏览器通用
                        var save = doc.createElement('button');
                        save.type = 'button';
                        save.className = 'jinyu-btn jinyu-btn-primary';
                        save.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i> 保存图片';
                        save.addEventListener('click', function () { showFullscreenPoster(dataUrl); });
                        actions.appendChild(save);
                    } else {
                        // 画布被污染无法导出：禁用下载并说明
                        var noDl = doc.createElement('span');
                        noDl.className = 'jinyu-btn jinyu-btn-disabled';
                        noDl.innerHTML = '<i class="fa-solid fa-ban" aria-hidden="true"></i> 暂不支持下载';
                        actions.appendChild(noDl);
                    }

                    var close = doc.createElement('button');
                    close.type = 'button';
                    close.className = 'jinyu-btn';
                    close.setAttribute('data-jinyu-modal-close', '');
                    close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i> 关闭';
                    actions.appendChild(close);

                    if (dataUrl && isMobile) {
                        var tip = doc.createElement('p');
                        tip.className = 'jinyu-poster-tip';
                        tip.textContent = _t('saveHint', '点「保存图片」放大后长按图片，即可保存到相册');
                        actions.appendChild(tip);
                    }

                    if (dataUrl) body.appendChild(img);
                    body.appendChild(actions);
                }).catch(function () {
                    body.innerHTML = '<p class="jinyu-empty">海报生成失败，请刷新后重试</p>';
                });
            });
        });

        function loadImg(src, timeout) {
            return new Promise(function (resolve) {
                if (!src) { resolve(null); return; }
                var done = false;
                var finish = function (v) { if (done) return; done = true; resolve(v); };
                // 用 fetch + CORS 取干净 blob：避开「页面已用普通 <img> 加载并缓存了无 CORS 头的同一张图」
                // 导致的画布污染——命中缓存的非 CORS 响应会让 crossOrigin 判定失败 → 画布被污染 → toDataURL 抛错 → 下载禁用。
                // blob: URL 同源，drawImage 不会污染画布，下载永远可用（cdn 已开 CORS *，fetch 必然成功）。
                if (typeof fetch === 'function') {
                    fetch(src, { mode: 'cors', cache: 'no-store' })
                        .then(function (res) { if (!res.ok) throw new Error('bad'); return res.blob(); })
                        .then(function (blob) {
                            var bu = URL.createObjectURL(blob);
                            var im = new Image();
                            im.onload = function () { URL.revokeObjectURL(bu); finish(im); };
                            im.onerror = function () { URL.revokeObjectURL(bu); finish(null); };
                            im.src = bu;
                        })
                        .catch(function () {
                            // 无 CORS 的跨域图：回退普通 <img>（能显示但画布会被污染，下载降级为禁用，属可接受的边缘情况）
                            var im = new Image();
                            im.crossOrigin = 'anonymous';
                            im.onload = function () { finish(im); };
                            im.onerror = function () { finish(null); };
                            im.src = src;
                        });
                } else {
                    var img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.onload = function () { finish(img); };
                    img.onerror = function () { finish(null); };
                    img.src = src;
                }
                setTimeout(function () { finish(null); }, timeout || 12000);
            });
        }

        /* 移动端保存：全屏放大海报图片，用户长按即可保存到相册（绕开各浏览器对 <a download> 的限制） */
        function showFullscreenPoster(src) {
            var ov = doc.createElement('div');
            ov.className = 'jinyu-poster-fullscreen';
            ov.innerHTML =
                '<div class="jinyu-poster-fs-inner">' +
                    '<img src="' + src + '" alt="文章海报">' +
                    '<p class="jinyu-poster-fs-tip">长按图片选择「保存图片 / 保存到相册」</p>' +
                '</div>';
            ov.addEventListener('click', function (e) {
                if (e.target === ov || (e.target.classList && e.target.classList.contains('jinyu-poster-fs-tip'))) ov.remove();
            });
            doc.body.appendChild(ov);
        }

        function rr(ctx, x, y, w, h, r) {
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + w, y, x + w, y + h, r);
            ctx.arcTo(x + w, y + h, x, y + h, r);
            ctx.arcTo(x, y + h, x, y, r);
            ctx.arcTo(x, y, x + w, y, r);
            ctx.closePath();
        }

        function wrapLines(ctx, text, maxW, maxLines) {
            var lines = [], line = '', truncated = false;
            for (var i = 0; i < text.length; i++) {
                var t = line + text.charAt(i);
                if (line && ctx.measureText(t).width > maxW) {
                    if (lines.length === maxLines - 1) { truncated = true; break; }
                    lines.push(line);
                    line = text.charAt(i);
                } else {
                    line = t;
                }
            }
            if (!truncated && line) lines.push(line);
            if (truncated) lines.push(line.replace(/[\s，、。；：！？,.;:!?]+$/, '') + '…');
            return lines;
        }

        function drawPoster(title, cover) {
            var W = 750, H = 1080;
            var canvas = doc.createElement('canvas');
            canvas.width = W;
            canvas.height = H;
            var ctx = canvas.getContext('2d');
            var primary = getComputedStyle(doc.documentElement).getPropertyValue('--jinyu-c-primary').trim() || '#FF6B35';
            var FONT = "'PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif";
            var M = 56;

            // 背景
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, W, H);

            // 顶部主色头带：站名 + 日期
            ctx.fillStyle = primary;
            ctx.fillRect(0, 0, W, 150);
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 32px ' + FONT;
            ctx.fillText(CFG.site_name || '', M, 96);
            ctx.fillStyle = 'rgba(255,255,255,.85)';
            ctx.font = '400 20px ' + FONT;
            ctx.textAlign = 'right';
            ctx.fillText(new Date().toLocaleDateString('zh-CN'), W - M, 96);
            ctx.textAlign = 'left';

            // 封面：圆角裁剪 + 轻描边（无封面给占位）
            var coverTop = 190, coverW = W - 2 * M, coverH = 400;
            if (cover) {
                ctx.save();
                rr(ctx, M, coverTop, coverW, coverH, 22);
                ctx.clip();
                var s = Math.max(coverW / cover.width, coverH / cover.height);
                var dw = cover.width * s, dh = cover.height * s;
                ctx.drawImage(cover, M + (coverW - dw) / 2, coverTop + (coverH - dh) / 2, dw, dh);
                ctx.restore();
                rr(ctx, M, coverTop, coverW, coverH, 22);
                ctx.lineWidth = 2;
                ctx.strokeStyle = 'rgba(0,0,0,.06)';
                ctx.stroke();
            } else {
                rr(ctx, M, coverTop, coverW, coverH, 22);
                ctx.fillStyle = '#f2f3f5';
                ctx.fill();
                ctx.fillStyle = 'rgba(0,0,0,.25)';
                ctx.font = '400 24px ' + FONT;
                ctx.textAlign = 'center';
                ctx.fillText(_t('noCover', '暂无封面'), W / 2, coverTop + coverH / 2);
                ctx.textAlign = 'left';
            }

            // 标题区：主色小装饰条 + 自动换行（最多 3 行/无封面 4 行）
            var titleTop = coverTop + coverH + 52;
            rr(ctx, M, titleTop - 36, 46, 8, 4);
            ctx.fillStyle = primary;
            ctx.fill();
            ctx.fillStyle = '#1f2329';
            ctx.font = '700 42px ' + FONT;
            var maxLines = cover ? 3 : 4;
            var lineH = 60;
            var lines = wrapLines(ctx, title, W - 2 * M, maxLines);
            lines.forEach(function (ln, i) {
                ctx.fillText(ln, M, titleTop + i * lineH);
            });

            // 底部卡片：二维码 + 引导文案
            var cardX = 40, cardW = W - 80, cardY = 900, cardH = 160;
            rr(ctx, cardX, cardY, cardW, cardH, 22);
            ctx.fillStyle = '#f7f8fa';
            ctx.fill();

            var qrSize = 140, qrX = cardX + 26, qrY = cardY + (cardH - qrSize) / 2;
            rr(ctx, qrX - 8, qrY - 8, qrSize + 16, qrSize + 16, 16);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.strokeStyle = '#eef0f2';
            ctx.lineWidth = 1;
            ctx.stroke();
            try {
                if (typeof window.qrcode === 'function') {
                    var qr = window.qrcode(0, 'M');
                    qr.addData(location.href.split('#')[0]);
                    qr.make();
                    var n = qr.getModuleCount();
                    var cell = qrSize / n;
                    var ox = qrX, oy = qrY;
                    ctx.fillStyle = '#1f2329';
                    for (var r = 0; r < n; r++) {
                        for (var c = 0; c < n; c++) {
                            if (qr.isDark(r, c)) ctx.fillRect(ox + c * cell, oy + r * cell, cell + .5, cell + .5);
                        }
                    }
                }
            } catch (e) { /* 二维码失败不阻断海报 */ }

            var tx = qrX + qrSize + 28;
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#1f2329';
            ctx.font = '700 30px ' + FONT;
            ctx.fillText(_t('scanToRead', '长按 / 扫码阅读全文'), tx, qrY + 56);
            ctx.fillStyle = '#6b7280';
            ctx.font = '400 22px ' + FONT;
            ctx.fillText(CFG.site_name || location.host, tx, qrY + 96);
            ctx.fillStyle = '#9ca3af';
            ctx.font = '400 18px ' + FONT;
            ctx.fillText(location.host, tx, qrY + 126);

            // 底部主色收尾条
            ctx.fillStyle = primary;
            ctx.fillRect(0, H - 10, W, 10);

            return canvas;
        }
    }

    /* ======================================================================
       模块：文章二维码（本地 qrcode 库，无外部依赖）
       ====================================================================== */
    function qrcodeModule() {
        $$('[data-jinyu-qr]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url') || location.href;
                var m = openModal({ title: '扫码阅读', className: 'jinyu-qr-modal', body: '<div class="jinyu-qr-box" id="jinyu-qr-canvas"></div><p class="jinyu-qr-url">' + url + '</p>' });
                var box = m.querySelector('#jinyu-qr-canvas');
                try {
                    if (typeof window.qrcode !== 'function') throw new Error('lib missing');
                    var qr = window.qrcode(0, 'M');
                    qr.addData(url);
                    qr.make();
                    box.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
                } catch (e) {
                    // 本地方案不可用时只给文本提示：绝不把访问 URL 外发给 api.qrserver.com 这类第三方
                    box.innerHTML = '<p class="jinyu-qr-url">二维码生成失败，请复制链接分享</p>';
                }
            });
        });
    }

    /* ======================================================================
       模块：文章打赏
       ====================================================================== */
    function donate() {
        var btn = $('[data-jinyu-donate]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var wx  = btn.getAttribute('data-wx') || '';
            var ali = btn.getAttribute('data-ali') || '';
            var body = '';
            if (wx)  body += '<div class="jinyu-donate-item"><div class="jinyu-donate-name">微信</div><img src="' + wx + '" alt="wechat"><span>微信扫一扫</span></div>';
            if (ali) body += '<div class="jinyu-donate-item"><div class="jinyu-donate-name">支付宝</div><img src="' + ali + '" alt="alipay"><span>支付宝扫一扫</span></div>';
            if (!body) body = '<p class="jinyu-empty">暂未配置收款码</p>';
            if (CFG.reward_text) body = '<p class="jinyu-donate-text">' + CFG.reward_text + '</p>' + body;
            openModal({ title: CFG.reward_title || '赞赏作者', className: 'jinyu-donate-modal', body: body });
        });
    }

    /* ======================================================================
       模块：文章图片灯箱（Viewer.js）
       作用域：.jinyu-article-content 内的图片，点击放大并支持相册切换
       ====================================================================== */
    function lightbox() {
        if (!window.Viewer) return;
        $$('.jinyu-article-content').forEach(function (container) {
            if (container.dataset.viewerReady) return;
            container.dataset.viewerReady = '1';

            /* WordPress 正文图常被默认包进 <a href="原图">，Viewer.js 的 click 不 preventDefault，
               导致点图时浏览器直接跳走看原图、灯箱不弹出。捕获阶段拦截「图片指向图片文件」的
               默认跳转，保留真实外链（指向文章的链接照常跳转），让 Viewer 正常接管放大。 */
            container.addEventListener('click', function (e) {
                var a = e.target.closest && e.target.closest('a');
                if (!a) return;
                var img = e.target.closest && e.target.closest('img');
                var href = a.getAttribute('href') || '';
                if (img && a.contains(img) && /\.(png|jpe?g|gif|webp|svg|bmp|avif)(\?|#|$)/i.test(href)) {
                    e.preventDefault();
                }
            }, true);

            /* eslint-disable no-new */
            new Viewer(container, {
                navbar: false,
                title: function (image) {
                    return image.alt || (image.src || '').split('/').pop();
                },
                toolbar: {
                    zoomIn: 1, zoomOut: 1, oneToOne: 1, reset: 1,
                    prev: 1, next: 1, rotateLeft: 1, rotateRight: 1,
                    flipHorizontal: 0, flipVertical: 0, play: 0
                },
                movable: true, rotatable: true, scalable: true, zoomable: true,
                fullscreen: true, transition: true, tooltip: true
            });
        });
    }

    /* ======================================================================
       模块：代码高亮（highlight.js）
       对正文 <pre><code> 进行语法高亮，明暗主题随 html[data-theme] 自动切换
       ====================================================================== */
    function codeHighlight() {
        if (!CFG.code_highlight_enable) return;
        if (!window.hljs) return;
        $$('.jinyu-article-content pre code').forEach(function (block) {
            if (block.classList.contains('hljs')) return;
            try { hljs.highlightElement(block); } catch (e) { /* 忽略个别语言解析异常 */ }
        });
    }

    /* ======================================================================
       模块：首页轮播（Swiper）
       ====================================================================== */
    function carousel() {
        if (!window.Swiper) return;
        $$('[data-jinyu-carousel]').forEach(function (el) {
            if (el.dataset.carouselReady) return;
            el.dataset.carouselReady = '1';
            var autoplay = parseInt(el.dataset.autoplay || '0', 10);
            var slides = el.querySelectorAll('.swiper-slide').length;
            /* eslint-disable no-new */
            new Swiper(el, {
                loop: el.dataset.loop === '1' && slides > 1,
                autoplay: autoplay > 0 ? { delay: autoplay, disableOnInteraction: false } : false,
                pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
                navigation: {
                    prevEl: el.querySelector('.swiper-button-prev'),
                    nextEl: el.querySelector('.swiper-button-next')
                },
                effect: (el.dataset.effect || 'slide'),
                mousewheel: el.dataset.mousewheel === '1' ? { forceToAxis: true } : false,
                speed: 600,
                grabCursor: true
            });
        });
    }

    /* ======================================================================
       模块：PJAX 无刷新导航（默认关闭，需在后台开启）
       ====================================================================== */
    function pjax() {
        if (!CFG.pjax) return;
        if (!('fetch' in window) || !('history' in window)) return;

        var mainSelector = '.jinyu-content';

        function isInternal(href) {
            if (!href) return false;
            var a = doc.createElement('a');
            a.href = href;
            if (a.hostname !== location.hostname) return false;
            if (a.protocol !== location.protocol) return false;
            if (a.pathname.indexOf('/wp-admin') === 0) return false;
            if (/\.(pdf|zip|rar|tar|gz|docx?|xlsx?|pptx?|jpg|png|gif|mp3|mp4)$/i.test(a.pathname)) return false;
            return true;
        }

        function load(url, push) {
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) {
                    if (!r.ok) throw new Error('http ' + r.status);
                    return r.text();
                })
                .then(function (html) {
                    var doc2 = doc.implementation.createHTMLDocument('');
                    doc2.documentElement.innerHTML = html;

                    var fresh = doc2.querySelector(mainSelector);
                    var cur = $(mainSelector);
                    if (!fresh || !cur) { location.href = url; return; }

                    cur.innerHTML = fresh.innerHTML;

                    var title = doc2.querySelector('title');
                    if (title) document.title = title.textContent;

                    // 更新导航高亮
                    $$('.jinyu-nav a').forEach(function (link) {
                        link.classList.toggle('current', link.getAttribute('href') === url);
                    });

                    if (push !== false) history.pushState({ jy_pjax: 1 }, '', url);

                    window.scrollTo(0, 0);
                    if (window.jinyuReinit) window.jinyuReinit();
                })
                .catch(function () { location.href = url; });
        }

        doc.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var link = e.target.closest('a');
            if (!link) return;
            if (link.target && link.target !== '_self') return;
            if (link.hasAttribute('download')) return;
            if (link.closest('[data-jinyu-no-pjax]')) return;
            if (link.getAttribute('data-jinyu-no-pjax') !== null) return;
            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#' || !isInternal(href)) return;
            if (href === location.pathname + location.search + location.hash) return;
            e.preventDefault();
            load(href, true);
        });

        window.addEventListener('popstate', function () {
            if (history.state && history.state.jy_pjax) load(location.href, false);
        });
    }

    /**
     * 内容置换后重新初始化依赖 DOM 的模块（PJAX / 加载更多后调用）
     */
    /* ======================================================================
       模块：手气不错（随机文章跳转）
       ====================================================================== */
    function luck() {
        doc.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('#jinyu-luck-btn');
            if (!btn) return;
            e.preventDefault();
            if (btn.classList.contains('jinyu-loading')) return;
            btn.classList.add('jinyu-loading');
            Util.ajax('jinyu_random_post', {}, function (err, res) {
                btn.classList.remove('jinyu-loading');
                if (err || !res || !res.success || !res.data || !res.data.url) {
                    Util.toast(_t('noJumpPost', '没有可跳转的文章'));
                    return;
                }
                window.location.href = res.data.url;
            });
        });
    }

    /* ======================================================================
       模块：动态 favicon 未读角标（仅管理员可见）
       ====================================================================== */
    function faviconBadge() {
        if (!CFG.favicon_badge) return;
        Util.ajax('jinyu_favicon_count', {}, function (err, res) {
            if (err || !res || !res.success) return;
            var count = parseInt((res.data && res.data.count) || 0, 10);
            if (count <= 0) return;
            drawFaviconBadge(count);
        });

        function drawFaviconBadge(count) {
            var link = doc.querySelector('link[rel~="icon"]');
            if (!link) return;
            var size = 64;
            var img = new Image();
            img.onload = function () {
                var canvas = doc.createElement('canvas');
                canvas.width = canvas.height = size;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, size, size);
                var r = size * 0.26;
                var cx = size - r, cy = r;
                ctx.beginPath();
                ctx.arc(cx, cy, r, 0, Math.PI * 2);
                ctx.fillStyle = '#e53935';
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold ' + Math.round(size * 0.28) + 'px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(count > 99 ? '99+' : String(count), cx, cy + 1);
                try { link.href = canvas.toDataURL('image/png'); } catch (e) { /* 忽略污染画布 */ }
            };
            img.src = link.href;
        }
    }

    /* ======================================================================
       模块：首屏骨架屏（页面加载完成后移除占位）
       ====================================================================== */
    function skeleton() {
        var el = doc.getElementById('jinyu-skeleton');
        if (!el) return;
        function hide() {
            if (el._jinyuHidden) return;
            el._jinyuHidden = 1;
            el.classList.add('jinyu-skeleton--hide');
            setTimeout(function () {
                if (el && el.parentNode) el.parentNode.removeChild(el);
            }, 420);
        }
        // jinyu.js 以 defer 加载：执行时 HTML 主体已就绪，立即撤掉首屏骨架屏，
        // 不必等待图片等子资源（否则会长时间盖住真实内容并持续闪烁）。
        if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', hide);
        else hide();
        window.addEventListener('load', hide); // 双保险
        setTimeout(hide, 2000);               // 兜底：防异常导致常驻
    }

    /* ======================================================================
       模块：评论表情选择器（点击插入表情到评论文本框光标处）
       ====================================================================== */
    function commentSmiley() {
        var btn = doc.querySelector('.jinyu-comment-smiley-btn');
        var panel = doc.getElementById('jinyu-smiley-panel');
        if (!btn || !panel) return;

        var emojis = ['😀','😁','😂','🤣','😊','😍','😘','😎','🤔','😅','😭','😡','👍','👎','👏','🙏','💪','🎉','❤️','💔','🔥','✨','🌟','🍻'];
        emojis.forEach(function (e) {
            var b = doc.createElement('button');
            b.type = 'button';
            b.className = 'jinyu-smiley-item';
            b.setAttribute('data-emoji', e);
            b.textContent = e;
            panel.appendChild(b);
        });

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            panel.hidden = !panel.hidden;
        });

        panel.addEventListener('click', function (e) {
            var t = e.target.closest('.jinyu-smiley-item');
            if (!t) return;
            insertEmojiAtCursor(t.getAttribute('data-emoji'));
            panel.hidden = true;
        });

        doc.addEventListener('click', function (e) {
            if (!panel.hidden && e.target !== btn && !panel.contains(e.target)) panel.hidden = true;
        });

        function insertEmojiAtCursor(text) {
            var ta = doc.getElementById('comment');
            if (!ta) return;
            var start = ta.selectionStart || 0, end = ta.selectionEnd || 0;
            ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
            var pos = start + text.length;
            ta.selectionStart = ta.selectionEnd = pos;
            ta.focus();
        }
    }

    /* ======================================================================
       模块：实时搜索下拉（输入即预读结果）
       ====================================================================== */
    function liveSearch() {
        var forms = $$('form[role="search"]');
        if (!forms.length) return;

        forms.forEach(function (form) {
            var input = form.querySelector('input[name="s"]');
            if (!input) return;
            // pjax 重跑时跳过已初始化的表单，避免重复面板
            if (form.querySelector('.jinyu-search-suggest')) return;

            var panel = doc.createElement('div');
            panel.className = 'jinyu-search-suggest';
            panel.hidden = true;
            form.appendChild(panel);

            var timer = null;
            var lastQ = '';

            function hide() { panel.hidden = true; }

            function render(res) {
                if (!res || !res.success) { hide(); return; }
                var html = res.data.html;
                if (!html) {
                    panel.innerHTML = '<div class="jinyu-search-suggest-empty">没有找到相关文章</div>';
                } else {
                    panel.innerHTML = html +
                        '<a class="jinyu-search-sg-more" href="' + (res.data.more || '#') + '">查看全部 ' + (res.data.count || 0) + ' 条结果 ›</a>';
                }
                panel.classList.remove('jinyu-search-suggest--up');
                panel.style.maxHeight = '';
                panel.hidden = false;

                // 默认显示在搜索框「下方」。仅当下方可用空间明显不足（<220px）且上方更宽裕时，
                // 才翻转到输入框上方，避免长页面滚动到侧栏搜索框时下拉被顶到框上方、又被限高裁短。
                // 高度直接占满可用空间（不写死最小值），结果多时可完整滚动浏览。
                var formRect = form.getBoundingClientRect();
                var pr = panel.getBoundingClientRect();
                var spaceBelow = window.innerHeight - (formRect.bottom + 8);
                var spaceAbove = formRect.top - 8;
                if (pr.bottom > window.innerHeight - 8) {
                    if (spaceAbove > spaceBelow && spaceAbove > 220) {
                        panel.classList.add('jinyu-search-suggest--up');
                        pr = panel.getBoundingClientRect();
                        panel.style.maxHeight = Math.max(0, spaceAbove - 8) + 'px';
                    } else {
                        panel.style.maxHeight = Math.max(0, spaceBelow - 8) + 'px';
                    }
                }
            }

            function query(q) {
                if (q === lastQ) return;
                lastQ = q;
                if (q.length < 1) { hide(); return; }
                Util.ajax('jinyu_search', { q: q }, function (err, res) {
                    if (err) { hide(); return; }
                    render(res);
                });
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                var q = input.value.trim();
                timer = setTimeout(function () { query(q); }, 250);
            });
            input.addEventListener('focus', function () {
                if (lastQ && input.value.trim()) query(input.value.trim());
            });
            // 点击建议项：阻止默认提交，直接跳转（链接本身已带 href）
            panel.addEventListener('mousedown', function (e) {
                var a = e.target.closest('a.jinyu-search-sg');
                if (a) e.preventDefault();
            });
            form.addEventListener('submit', hide);
            doc.addEventListener('click', function (e) {
                if (!form.contains(e.target)) hide();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { hide(); }
            });
        });
    }

    /* ======================================================================
       模块：阅读字号调节（A⁻/A⁺/默认，记忆到 localStorage）
       ====================================================================== */
    function fontScale() {
        var content = $('.jinyu-article-content');
        if (!content) return;
        var btns = $$('[data-jinyu-fs]');
        if (!btns.length) return;

        var KEY = 'jinyu_fs_scale';
        var MIN = 0.85, MAX = 1.4, STEP = 0.1, DEF = 1;

        function updateState(scale) {
            var el = $('[data-jinyu-fs-state]');
            if (!el) return;
            var pct = Math.round(scale * 100);
            el.textContent = pct + '%';
            // 直观提示当前是放大 / 缩小 / 默认，便于用户判断是否点过头
            el.classList.toggle('jinyu-fs-up', scale > DEF + 1e-6);
            el.classList.toggle('jinyu-fs-down', scale < DEF - 1e-6);
        }

        function apply(scale) {
            content.style.setProperty('--reader-scale', scale);
            try { localStorage.setItem(KEY, String(scale)); } catch (e) {}
            updateState(scale);
        }

        try {
            var saved = parseFloat(localStorage.getItem(KEY));
            if (!isNaN(saved)) apply(Math.min(MAX, Math.max(MIN, saved)));
            else updateState(DEF);
        } catch (e) {}

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cur = parseFloat(getComputedStyle(content).getPropertyValue('--reader-scale')) || DEF;
                var act = btn.getAttribute('data-jinyu-fs');
                var next = act === 'inc' ? cur + STEP : act === 'dec' ? cur - STEP : DEF;
                next = Math.min(MAX, Math.max(MIN, Math.round(next * 100) / 100));
                apply(next);
            });
        });
    }

    /* ======================================================================
       模块：站内链接悬停预览卡（Hover Card）
       仅对正文内指向本站的链接生效；鼠标悬停 220ms 后拉取标题/摘要/封面，
       浮层定位自动避让视口边缘；移入卡片本身不消失，便于点击。
       ====================================================================== */
    function hoverCard() {
        var box = $('.jinyu-article-content');
        if (!box || !finePointer) return;
        if (box.dataset.jinyuHcReady) return;
        box.dataset.jinyuHcReady = '1';

        var site = (CFG.site_url || location.origin || '').replace(/\/+$/, '');
        var card = null, timer = null, curLink = null, cache = {};

        function ensureCard() {
            if (card) return card;
            card = doc.createElement('div');
            card.className = 'jinyu-hover-card';
            card.hidden = true;
            card.setAttribute('role', 'tooltip');
            doc.body.appendChild(card);
            return card;
        }

        function show(link) {
            var href = link.href || '';
            if (site && href.indexOf(site) !== 0) return;          // 仅站内
            if (/\.(png|jpe?g|gif|webp|mp4|pdf|zip)$/i.test(href)) return; // 排除媒体
            if (link.closest('.jinyu-code-wrap')) return;
            if (link.getAttribute('data-jinyu-no-pjax') !== null) return;

            var cached = cache[href];
            if (cached) { render(cached); return; }
            Util.ajax('jinyu_link_preview', { url: href }, function (err, res) {
                if (err || !res || !res.success || !res.data || !res.data.ok) return;
                cache[href] = res.data;
                if (curLink === link) render(res.data);
            });
        }

        function render(d) {
            ensureCard();
            card.innerHTML =
                '<a class="jinyu-hc-thumb" href="' + encodeURI(d.url || '') + '">' +
                    (d.thumb ? '<img src="' + encodeURI(d.thumb) + '" alt="" loading="lazy" decoding="async">' : '') +
                '</a>' +
                '<div class="jinyu-hc-body">' +
                    '<a class="jinyu-hc-title" href="' + encodeURI(d.url || '') + '">' + escHtml(d.title) + '</a>' +
                    '<p class="jinyu-hc-excerpt">' + escHtml(d.excerpt) + '</p>' +
                    '<span class="jinyu-hc-date">' + escHtml(d.date) + '</span>' +
                '</div>';
            position();
            card.hidden = false;
        }

        function position() {
            if (!curLink) return;
            var r = curLink.getBoundingClientRect();
            card.style.visibility = 'hidden';
            card.hidden = false;
            var cw = card.offsetWidth, ch = card.offsetHeight;
            var top = r.bottom + 10;
            if (top + ch > window.innerHeight - 8) top = r.top - ch - 10;
            if (top < 8) top = 8;
            var left = r.left;
            if (left + cw > window.innerWidth - 8) left = window.innerWidth - cw - 8;
            if (left < 8) left = 8;
            card.style.top = top + 'px';
            card.style.left = left + 'px';
            card.style.visibility = 'visible';
        }

        function hide() { if (card) card.hidden = true; curLink = null; }

        box.addEventListener('mouseover', function (e) {
            var link = e.target.closest('a');
            if (!link) return;
            curLink = link;
            clearTimeout(timer);
            timer = setTimeout(function () { show(link); }, 220);
        });
        box.addEventListener('mouseout', function (e) {
            var to = e.relatedTarget;
            if (to && card && card.contains(to)) return;
            clearTimeout(timer);
            hide();
        });
        if (card) {
            card.addEventListener('mouseleave', hide);
            window.addEventListener('scroll', hide, { passive: true });
        }
    }

    /* ======================================================================
       模块：协同过滤推荐「读过这篇的人还读了」
       上报本次浏览的前驱文章（sessionStorage 记录上篇），服务端按共现频次
       给出推荐；数据不足时由服务端回退到热门相关，保证区域常驻。
       ====================================================================== */
    function coView() {
        var box = $('[data-jinyu-coview]');
        if (!box || box.dataset.jinyuCoviewReady) return;
        box.dataset.jinyuCoviewReady = '1';

        var pid = parseInt(box.getAttribute('data-jinyu-post'), 10);
        if (!pid) return;

        var prev = '';
        try { prev = sessionStorage.getItem('jinyu_last_post') || ''; } catch (e) {}
        if (prev && prev !== String(pid)) {
            Util.ajax('jinyu_coview_track', { prev: prev, cur: pid }, function () {});
        }
        try { sessionStorage.setItem('jinyu_last_post', String(pid)); } catch (e) {}

        Util.ajax('jinyu_coview', { post_id: pid, num: 4 }, function (err, res) {
            if (err || !res || !res.success) { box.style.display = 'none'; return; }
            var list = (res.data && res.data.list) || [];
            if (!list.length) { box.style.display = 'none'; return; }
            box.innerHTML =
                '<h3 class="jinyu-widget-title">读过这篇的人还读了</h3>' +
                '<div class="jinyu-coview-grid">' + list.map(function (it) {
                    return '<a class="jinyu-coview-card" href="' + encodeURI(it.url) + '">' +
                        '<div class="jinyu-coview-cover">' +
                            (it.thumb ? '<img class="jinyu-blur-img" src="' + encodeURI(it.thumb) + '" alt="" loading="lazy">' : '') +
                        '</div>' +
                        '<div class="jinyu-coview-title">' + escHtml(it.title) + '</div>' +
                    '</a>';
                }).join('') + '</div>';
            if (window.jinyuBlurInit) { try { window.jinyuBlurInit(box); } catch (e) {} }
        });
    }

    /* ======================================================================
       模块：文末「这篇有帮助」投票
       ====================================================================== */
    function articleVote() {
        var box = $('[data-jinyu-vote]');
        if (!box || box.dataset.jinyuVoteReady) return;
        box.dataset.jinyuVoteReady = '1';

        var pid = parseInt(box.getAttribute('data-jinyu-post'), 10);
        var yesBtn = $('[data-jinyu-vote-yes]', box);
        var noBtn  = $('[data-jinyu-vote-no]', box);
        var yesNum = $('[data-jinyu-vote-yes-n]', box);
        var noNum  = $('[data-jinyu-vote-no-n]', box);
        if (!pid || !yesBtn || !noBtn) return;

        var KEY = 'jinyu_vote_' + pid;
        var done = '';
        try { done = localStorage.getItem(KEY) || ''; } catch (e) {}

        function paint() {
            if (done === 'yes') {
                yesBtn.classList.add('is-active'); yesBtn.disabled = true; noBtn.disabled = true;
            } else if (done === 'no') {
                noBtn.classList.add('is-active'); noBtn.disabled = true; yesBtn.disabled = true;
            }
        }
        paint();

        function vote(dir) {
            if (done) return;
            Util.ajax('jinyu_vote', { post_id: pid, dir: dir }, function (err, res) {
                if (err || !res || !res.success) { Util.toast(_t('opFailed', '操作失败，请稍后再试')); return; }
                if (yesNum) yesNum.textContent = res.data.yes;
                if (noNum) noNum.textContent = res.data.no;
                done = dir;
                try { localStorage.setItem(KEY, dir); } catch (e) {}
                paint();
                Util.toast(dir === 'yes' ? _t('thanksSupport', '感谢支持 ♥') : _t('thanksFeedback', '感谢你的反馈'));
            });
        }

        yesBtn.addEventListener('click', function () { vote('yes'); });
        noBtn.addEventListener('click', function () { vote('no'); });
    }

    /* ======================================================================
       模块：复制正文自动附带出处（防搬运 + 回流）
       仅当选区落在正文内、且复制字数 ≥ 20 时追加「出自《站名》：链接」声明。
       ====================================================================== */
    function copyGuard() {
        var box = $('.jinyu-article-content');
        if (!box || box.dataset.jinyuCopyGuard) return;
        box.dataset.jinyuCopyGuard = '1';

        box.addEventListener('copy', function (e) {
            var sel = window.getSelection();
            if (!sel || sel.isCollapsed || !sel.rangeCount) return;
            // 选区必须落在正文内
            if (!box.contains(sel.anchorNode) || !box.contains(sel.focusNode)) return;
            var text = sel.toString();
            if (text.length < 20) return;

            var site   = box.getAttribute('data-jinyu-site') || (CFG.site_name || doc.title) || '';
            var author = box.getAttribute('data-jinyu-author') || '';
            var anno = '\n\n—— 本文出自《' + site + '》：' + location.href +
                       (author ? '（作者：' + author + '）' : '');
            var cd = e.clipboardData || window.clipboardData;
            if (!cd) return;
            e.preventDefault();
            cd.setData('text/plain', text + anno);
        });
    }

    /* ======================================================================
       模块：最近浏览 / 继续阅读（匿名游客也记，存 localStorage）
       ====================================================================== */
    function recentViewed() {
        var single = $('.jinyu-single');
        if (single) {
            var title = ($('.jinyu-article-title') || {}).textContent || doc.title;
            var img = '';
            var feat = $('.jinyu-featured');
            if (feat && feat.src) img = feat.src;
            else {
                var first = $('.jinyu-article-content img.jinyu-blur-img');
                if (first && first.src) img = first.src;
            }
            var url = location.href;
            var KEY = 'jinyu_recent';
            try {
                var list = JSON.parse(localStorage.getItem(KEY) || '[]');
                if (!Array.isArray(list)) list = [];
                list = list.filter(function (it) { return it.url !== url; });
                list.unshift({ title: title, url: url, img: img });
                list = list.slice(0, 10);
                localStorage.setItem(KEY, JSON.stringify(list));
            } catch (e) {}
        }

        // 填充侧栏容器
        $$('[data-jinyu-recent]').forEach(function (box) {
            var list = [];
            try { list = JSON.parse(localStorage.getItem('jinyu_recent') || '[]'); } catch (e) {}
            if (!list.length) {
                box.innerHTML = '<div class="jinyu-rv-item--empty">浏览文章后这里会显示你的足迹</div>';
                return;
            }
            var html = list.map(function (it) {
                return '<a class="jinyu-rv-item" href="' + encodeURI(it.url) + '">' +
                    '<span class="jinyu-rv-cover">' + (it.img ? '<img src="' + encodeURI(it.img) + '" alt="" loading="lazy">' : '') + '</span>' +
                    '<span class="jinyu-rv-title">' + (it.title || '').replace(/</g, '') + '</span></a>';
            }).join('');
            box.innerHTML = html;
        });
    }

    /* 侧栏实时模块：时钟 / 运行时长 / 访客信息 / 站点性能，共享一次 Ajax 取回 */
    function sidebarLive() {
        var CFG = window.JINYU_CONFIG || {};
        var boxes = {
            visitor: document.querySelector('.jinyu-visitor[data-jinyu-live="visitor"]'),
            perf: document.querySelector('.jinyu-perf[data-jinyu-live="perf"]'),
            clock: document.querySelector('.jinyu-clock[data-jinyu-live="clock"]'),
            uptime: document.querySelector('.jinyu-uptime[data-since]'),
            runinfo: document.querySelector('.jinyu-footer-runinfo[data-jinyu-live="runinfo"]')
        };
        var hasLive = boxes.visitor || boxes.perf || boxes.clock || boxes.runinfo;
        var hasUptime = boxes.uptime;
        if (!hasLive && !hasUptime) return;
        if (!CFG.ajax_url) return;
        if (!CFG.nonce) { Util.refreshNonce().then(sidebarLive); return; } // nonce 懒加载到位后再初始化

        // pjax 重入：清掉上一轮的定时器
        if (window.__jinyuLive && window.__jinyuLive.timer) {
            clearInterval(window.__jinyuLive.timer);
        }
        var state = { payload: null, timer: null, serverNow: 0, fetchedAt: 0 };
        window.__jinyuLive = state;

        // 时钟 DOM 引用缓存一次（pjax 后 sidebarLive 重入会重建闭包 → 自然刷新）
        var clockRefs = null;
        function getClockRefs() {
            if (!boxes.clock) return null;
            if (!clockRefs) {
                var c = boxes.clock;
                clockRefs = {
                    hm: c.querySelector('[data-clock="hm"]'),
                    sec: c.querySelector('[data-clock="sec"]'),
                    date: c.querySelector('[data-clock="date"]'),
                    week: c.querySelector('[data-clock="week"]'),
                    bar: c.querySelector('.jinyu-clock-sweep > i')
                };
            }
            return clockRefs;
        }

        function tickClock() {
            var refs = getClockRefs();
            if (!refs) return;
            var now = state.serverNow
                ? state.serverNow + Math.floor((Date.now() - state.fetchedAt) / 1000)
                : Math.floor(Date.now() / 1000);
            var off = state.payload ? state.payload.time.off : 0;
            var local = now + off; // 站点本地 epoch（按 UTC 解释，避开访客本机时区）
            var d = new Date(local * 1000);
            var ss = d.getUTCSeconds();
            var hm = ('0' + d.getUTCHours()).slice(-2) + ':' + ('0' + d.getUTCMinutes()).slice(-2);
            if (hm !== state.lastHm) { // 分/日/周每分钟才变，避免每秒无谓写 DOM
                state.lastHm = hm;
                if (refs.hm) refs.hm.textContent = hm;
                if (refs.date) refs.date.textContent = d.getUTCFullYear() + '年' + (d.getUTCMonth() + 1) + '月' + d.getUTCDate() + '日';
                if (refs.week) refs.week.textContent = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'][d.getUTCDay()];
            }
            if (refs.sec) refs.sec.textContent = ('0' + ss).slice(-2);
            if (refs.bar) refs.bar.style.transform = 'scaleX(' + (ss / 60).toFixed(4) + ')';
        }

        /* 光标跟随：指针悬停时钟时瞳孔朝指针方向微移（纯 transform，无布局抖动）。
           仅真指针设备 + 未开启「减少动效」时绑定；触屏 / 降级条件下瞳孔恒居中（CSS 默认 0）。 */
        function bindClockEyes() {
            var c = boxes.clock;
            if (!c || c.__jinyuEyeBound) return; // 防重复绑定（pjax 换节点后属性自然失效）
            if (!window.matchMedia) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (!window.matchMedia('(hover: hover)').matches) return; // 触屏不做无意义跟随
            c.__jinyuEyeBound = true;
            var raf = 0, tx = 0, ty = 0;
            function flush() {
                raf = 0;
                c.style.setProperty('--jc-px', tx.toFixed(3));
                c.style.setProperty('--jc-py', ty.toFixed(3));
            }
            function onMove(e) {
                var r = c.getBoundingClientRect();
                if (!r.width || !r.height) return;
                tx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / (r.width / 2)));
                ty = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2)) / (r.height / 2)));
                if (!raf) raf = requestAnimationFrame(flush);
            }
            function reset() {
                tx = 0; ty = 0;
                if (!raf) raf = requestAnimationFrame(flush);
            }
            c.addEventListener('mousemove', onMove, { passive: true });
            c.addEventListener('mouseleave', reset);
        }

        function tickUptime() {
            var u = boxes.uptime;
            if (!u) return;
            var since = parseInt(u.getAttribute('data-since'), 10);
            if (!since) return;
            var now = state.serverNow
                ? state.serverNow + Math.floor((Date.now() - state.fetchedAt) / 1000)
                : Math.floor(Date.now() / 1000);
            var diff = Math.max(0, now - since);
            var day = Math.floor(diff / 86400);
            var hh = Math.floor((diff % 86400) / 3600);
            var mm = Math.floor((diff % 3600) / 60);
            var ss = diff % 60;
            var set = function (k, v) { var e = u.querySelector('[data-up="' + k + '"]'); if (e) e.textContent = v; };
            set('d', day); set('h', ('0' + hh).slice(-2)); set('m', ('0' + mm).slice(-2)); set('s', ('0' + ss).slice(-2));
        }

        function fillVisitor(p) {
            var v = boxes.visitor; if (!v || !p.visitor) return;
            var vis = p.visitor;
            var setv = function (k, val) { var e = v.querySelector('[data-vis="' + k + '"]'); if (e) e.textContent = val || '—'; };
            setv('ip', vis.ip);
            setv('os', vis.os);
            setv('browser', (vis.browser + (vis.version ? ' ' + vis.version : '')).trim());
            var greet = v.querySelector('[data-vis="greet"]');
            if (greet) greet.textContent = vis.browser ? ('你好，' + vis.browser + '用户') : '你好，朋友';
            var loc = v.querySelector('[data-vis="loc"]');
            if (loc) {
                if (vis.loc) { loc.textContent = vis.loc; loc.hidden = false; }
                else { loc.hidden = true; }
            }
        }

        /* 毫秒 → 秒显示：≥1s 两位小数，≥0.1s 三位，其余四位（对齐 0.001877 秒的精度感） */
        function fmtSec(msVal) {
            var s = (Number(msVal) || 0) / 1000;
            return s >= 1 ? s.toFixed(2) : s >= 0.1 ? s.toFixed(3) : s.toFixed(4);
        }
        /* 数值 + 小号单位写入 <b>：unit 走 <i class="unit">，样式见 widgets.less */
        function setNumUnit(el, num, unit) {
            if (!el) return;
            el.textContent = num;
            if (unit) {
                var i = document.createElement('i');
                i.className = 'unit';
                i.textContent = unit;
                el.appendChild(i);
            }
        }

        /* 折线 → 平滑曲线：Catmull-Rom 转三次贝塞尔，比折线更有「仪表盘」质感。
           入参是 viewBox(100×36) 坐标；过冲由 SVG 自身裁剪，不会溢出卡片。 */
        function smoothPath(pts) {
            if (!pts.length) return '';
            var d = 'M' + pts[0][0].toFixed(2) + ' ' + pts[0][1].toFixed(2);
            if (pts.length === 1) return d;
            for (var i = 0; i < pts.length - 1; i++) {
                var p0 = pts[i - 1] || pts[i];
                var p1 = pts[i];
                var p2 = pts[i + 1];
                var p3 = pts[i + 2] || p2;
                d += ' C' +
                    (p1[0] + (p2[0] - p0[0]) / 6).toFixed(2) + ' ' + (p1[1] + (p2[1] - p0[1]) / 6).toFixed(2) + ' ' +
                    (p2[0] - (p3[0] - p1[0]) / 6).toFixed(2) + ' ' + (p2[1] - (p3[1] - p1[1]) / 6).toFixed(2) + ' ' +
                    p2[0].toFixed(2) + ' ' + p2[1].toFixed(2);
            }
            return d;
        }

        /* 趋势行。三个指标都是「越小越好」，故上涨=红、下降=绿——但只在站点已经
           偏慢 / 拥堵时才上色：健康的站点每次采样都在 ±5% 抖动，一片红只会让人麻木。
           变化 <2% 记「持平」，无上一采样记「—」（三张卡的第四行永远有内容，高度才齐）。 */
        function paintTrend(el, cur, prev, alarm) {
            if (!el) return;
            var c = Number(cur) || 0;
            var p = Number(prev);
            if (!isFinite(p) || p <= 0) {
                el.textContent = '—';
                el.className = 'jinyu-perf-trend';
                return;
            }
            var diff = (c - p) / p * 100;
            if (Math.abs(diff) < 2) {
                el.textContent = _t('flat', '持平');
                el.className = 'jinyu-perf-trend';
                return;
            }
            el.textContent = (diff > 0 ? '▲' : '▼') + Math.abs(Math.round(diff)) + '%';
            el.className = 'jinyu-perf-trend' + (alarm ? (diff > 0 ? ' is-up' : ' is-down') : '');
            el.setAttribute('title', '上一采样 ' + fmtSec(p) + ' 秒');
        }

        function fillPerf(p) {
            var el = boxes.perf; if (!el || !p.perf) return;
            var perf = p.perf;

            var ms = el.querySelector('[data-perf="ms"]'); setNumUnit(ms, fmtSec(perf.ms), '秒');
            var q = el.querySelector('[data-perf="q"]'); setNumUnit(q, perf.q, '次');
            var mem = el.querySelector('[data-perf="mem"]'); setNumUnit(mem, perf.mem, 'MB');
            if (mem && perf.mem_max) mem.title = '占 PHP 上限 ' + perf.mem_max + 'MB 的 ' + perf.mem_pct + '%';

            /* 状态：等级由服务端算好（阈值单点维护），前端只做「等级 → 文案」映射 */
            var level = perf.level || 'ok';
            el.setAttribute('data-level', level);
            var status = el.querySelector('[data-perf="status"]');
            if (status) status.textContent = { fast: '实时', ok: '实时', warn: '偏慢', bad: '拥堵' }[level] || '实时';
            var dot = el.querySelector('[data-perf="dot"]');
            if (dot) dot.className = 'jinyu-perf-dot';
            var samples = el.querySelector('[data-perf="samples"]');
            if (samples && perf.samples) {
                samples.textContent = _t('nearSamples', '近 %d 次采样').replace('%d', perf.samples);
                samples.hidden = false;
            }

            /* 健康分环：r=17 → 周长 106.8，靠 dashoffset 收口 */
            var scoreBox = el.querySelector('[data-perf="scorebox"]');
            if (scoreBox && typeof perf.score === 'number') {
                scoreBox.hidden = false;
                var score = el.querySelector('[data-perf="score"]');
                if (score) score.textContent = perf.score;
                var ring = el.querySelector('[data-perf="ring"]');
                if (ring) {
                    var C = 2 * Math.PI * 17;
                    var pct = Math.max(0, Math.min(100, perf.score));
                    ring.setAttribute('stroke-dasharray', C.toFixed(1));
                    ring.setAttribute('stroke-dashoffset', (C * (1 - pct / 100)).toFixed(1));
                }
            }

            /* 指标卡的第四行：耗时/查询给趋势，内存给「占 PHP 上限多少」 */
            var prev = perf.prev || null;
            var alarm = level === 'warn' || level === 'bad';
            paintTrend(el.querySelector('[data-perf="ms-trend"]'), perf.ms, prev && prev.ms, alarm);
            paintTrend(el.querySelector('[data-perf="q-trend"]'), perf.q, prev && prev.q, alarm);
            var memPct = el.querySelector('[data-perf="mem-pct"]');
            if (memPct) memPct.textContent = perf.mem_max ? ('占 ' + perf.mem_pct + '%') : '—';

            /* 底部元信息：均值 / 峰值 / 负载（负载取不到时降级为内存占用） */
            var avgEl = el.querySelector('[data-perf="avg"]');
            if (avgEl) avgEl.textContent = fmtSec(perf.avg || perf.ms) + 's';
            var maxEl = el.querySelector('[data-perf="max"]');
            if (maxEl) maxEl.textContent = fmtSec(perf.max || perf.ms) + 's';
            var extra = el.querySelector('[data-perf="extra"]');
            var extraLabel = el.querySelector('[data-perf="extra-label"]');
            if (extra) {
                if (typeof perf.load === 'number') {
                    extra.textContent = perf.load.toFixed(2);
                    if (extraLabel) extraLabel.textContent = _t('load', '负载');
                } else if (perf.mem_max) {
                    extra.textContent = perf.mem_pct + '%';
                    if (extraLabel) extraLabel.textContent = _t('memory', '内存占用');
                } else {
                    extra.textContent = (perf.samples || 0) + ' 次';
                    if (extraLabel) extraLabel.textContent = _t('sample', '采样');
                }
            }

            /* 心跳曲线 */
            var beats = perf.beats || [];
            var spark = el.querySelector('[data-perf="spark"]');
            var area = el.querySelector('[data-perf="area"]');
            var peak = el.querySelector('[data-perf="peak"]');
            var avgLine = el.querySelector('[data-perf="avgline"]');
            if (!spark || !beats.length) {
                if (spark) spark.setAttribute('d', '');
                if (area) area.setAttribute('d', '');
                if (peak) peak.hidden = true;
                if (avgLine) avgLine.hidden = true;
                return;
            }

            // 量程取 p90 而不是最大值：一个 2s 级离群请求会把其余样本全压到贴底，
            // 曲线退化成「一条平线 + 一根尖刺」（面积层会把这个缺陷放大成楔形）。
            // 取 p90 并对超出部分削顶，正常流量才能占满纵向空间。
            var n = beats.length;
            var sorted = beats.slice().sort(function (a, b) { return a - b; });
            // p90 作为量程上界：单个离群请求不会把曲线压成贴底平线。
            var cap = sorted[Math.min(n - 1, Math.floor(n * 0.9))] || 1;
            // 样本偏少时 p90 会等于最大值（如 5 个样本里夹一个冷启动 2.8s），
            // 把量程拉爆 → 正常流量全贴底、曲线「假死」。此时只要最大值明显离群
            // （> 其余样本均值 2.5 倍）就改用「剔除该离群后的最大值」做量程，曲线才立得起来；
            // 若多个样本都慢，说明站点真慢，保留原量程不动。
            if (n < 10) {
                var rest = sorted.slice(0, n - 1);
                var restAvg = rest.reduce(function (a, b) { return a + b; }, 0) / rest.length;
                if (sorted[n - 1] > restAvg * 2.5) {
                    cap = Math.max(rest[rest.length - 1], 1);
                }
            }
            if (cap < 1) cap = 1;
            var H = 36, PAD = 3;
            var yOf = function (b) { return H - PAD - (Math.min(b, cap) / cap) * (H - PAD * 2); };
            var pts = beats.map(function (b, i) {
                return [n === 1 ? 0 : (i / (n - 1)) * 100, yOf(b)];
            });
            if (n === 1) pts.push([100, pts[0][1]]); // 单样本也画成一条水平线
            var line = smoothPath(pts);
            spark.setAttribute('d', line);
            if (area) area.setAttribute('d', line + ' L100,' + H + ' L0,' + H + ' Z');

            // 峰值点用 HTML 元素绝对定位：viewBox 被非等比拉伸，SVG 里的圆会被压成椭圆
            if (peak) {
                var peakV = -1, peakI = 0;
                for (var i = 0; i < n; i++) {
                    if (beats[i] > peakV) { peakV = beats[i]; peakI = i; }
                }
                peak.hidden = false;
                peak.style.left = (n === 1 ? 0 : (peakI / (n - 1)) * 100).toFixed(2) + '%';
                peak.style.top = (yOf(peakV) / H * 100).toFixed(2) + '%';
                peak.title = '峰值 ' + fmtSec(peakV) + ' 秒';
            }
            // 均值参考线：线在曲线下方即代表这次比平均快
            if (avgLine) {
                var avgV = perf.avg > 0 ? perf.avg : beats.reduce(function (a, b) { return a + b; }, 0) / n;
                avgLine.hidden = false;
                avgLine.style.top = (yOf(avgV) / H * 100).toFixed(2) + '%';
            }
        }

        function onPayload(p) {
            state.payload = p;
            state.serverNow = p.time ? p.time.ts : Math.floor(Date.now() / 1000);
            state.fetchedAt = Date.now();
            fillVisitor(p);
            fillPerf(p);
            fillRuninfo(p);
            tickClock();
            tickUptime();
        }

        /* 页脚运行信息：复用实时载荷里的 perf（查询数 / 内存 / 渲染耗时）。
           元素由 footer.php 按 footer_runinfo 开关输出；不命中时 boxes.runinfo 为 null，静默跳过。 */
        function fillRuninfo(p) {
            var el = boxes.runinfo;
            if (!el || !p.perf) return;
            var perf = p.perf;
            var set = function (k, v) {
                var e = el.querySelector('[data-ri="' + k + '"]');
                if (e) e.textContent = v;
            };
            set('q', perf.q);
            set('mem', perf.mem);
            set('ms', fmtSec(perf.ms));
            el.hidden = false; // 首包到达后再显示，避免闪烁「—」
        }

        var url = CFG.ajax_url + '?action=jinyu_sidebar_live&_ajax_nonce=' + encodeURIComponent(CFG.nonce);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.success && j.data) { onPayload(j.data); }
                else { state.serverNow = Math.floor(Date.now() / 1000); tickClock(); tickUptime(); }
            })
            .catch(function () { state.serverNow = Math.floor(Date.now() / 1000); tickClock(); tickUptime(); });

        bindClockEyes(); // 光标跟随（内含「减少动效 / 触屏」降级判断，未启用时瞳孔恒居中）
        state.timer = setInterval(function () { tickClock(); tickUptime(); }, 1000);
    }

    /* 小工具动效升级：进场淡入上浮（IntersectionObserver 错峰）。
       纯 transform/opacity，零重排；无脚本 / 不支持 IO / 用户开启「减少动效」时自动降级为直接显示。
       不改动任何小工具 PHP 逻辑与类名契约。 */
    function widgetAnim() {
        var root = doc.documentElement;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window)) {
            return; // 不挂 ready 类 → CSS 隐藏态不生效，小工具正常显示
        }
        var targets = doc.querySelectorAll('.jinyu-widget, .jinyu-footer-widget, .jinyu-single-widget');
        if (!targets.length) return;

        root.classList.add('jinyu-widget-anim-ready'); // 触发隐藏初始态

        // 按父容器分组计算序号，实现「同一侧栏从上到下依次摆好」
        var seen = {};
        Array.prototype.forEach.call(targets, function (el) {
            var p = el.parentElement;
            var key = p ? (p.className || p.tagName) : 'x';
            seen[key] = (seen[key] || 0);
            el.setAttribute('data-jinyu-anim-i', seen[key]);
            seen[key]++;

            // 标签云逐项错峰序号（CSS 用 transition-delay: calc(var(--jinyu-tag-i) * 26ms)）
            // 用 setProperty 追加，不会覆盖 PHP 内联写入的 --jinyu-tag-ratio
            Array.prototype.forEach.call(el.querySelectorAll('.jinyu-tag-cloud a'), function (a, ti) {
                a.style.setProperty('--jinyu-tag-i', ti);
            });
        });

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                var i = parseInt(el.getAttribute('data-jinyu-anim-i') || '0', 10);
                el.style.transitionDelay = (i * 70) + 'ms';
                // 双 rAF 确保「隐藏态 → is-in」确实触发过渡，而非被浏览器合并跳过
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        el.classList.add('is-in');
                    });
                });
                var clear = function () {
                    el.style.transitionDelay = '';
                    el.removeEventListener('transitionend', clear);
                };
                el.addEventListener('transitionend', clear);
                setTimeout(clear, 1400); // 兜底，防止 transitionend 漏触发导致 hover 残留延迟
                io.unobserve(el);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

        Array.prototype.forEach.call(targets, function (el) { io.observe(el); });
    }

    /* 每日一句「每次刷新随机」：句子在 HTML 静态缓存里是冻死的，
       必须在客户端重抽才有效。语录表由 PHP 以 data-jinyu-quotes 下发，
       只在 random 模式且语录 > 1 条时存在，daily 模式整个模块空转。
       random 模式下另有「换一句」按钮，与首屏重抽共用同一套换句逻辑。 */
    function hitokoto() {
        var boxes = $$('.jinyu-hitokoto[data-jinyu-quotes]');
        if (!boxes.length) return;

        /* 抽一条与当前不同的下标。语录 ≥2 时必得不同值（在「总数 - 1」里抽，
           当前下标之后的整体后移一位），保证点了「换一句」一定看得见变化。 */
        function pickOther(box, list) {
            var cur = parseInt(box.getAttribute('data-jinyu-quote-idx'), 10);
            if (isNaN(cur) || cur < 0 || cur >= list.length) cur = -1;
            var pick = Math.floor(Math.random() * (list.length - 1));
            if (cur >= 0 && pick >= cur) pick++;
            return pick;
        }

        function swapTo(box, list, pick) {
            if (pick < 0 || pick >= list.length) return;
            if (box.classList.contains('is-swapping')) return; // 连点：等这次淡出结束，别排队
            var item = list[pick];
            var textEl = $('.jinyu-hitokoto-text', box);
            if (!textEl) return;
            var fromEl = $('.jinyu-hitokoto-from', box);

            box.setAttribute('data-jinyu-quote-idx', pick);

            var apply = function () {
                textEl.textContent = item.text || '';
                if (item.from) {
                    if (fromEl) {
                        fromEl.textContent = item.from;
                    } else {
                        fromEl = doc.createElement('cite');
                        fromEl.className = 'jinyu-hitokoto-from';
                        fromEl.textContent = item.from;
                        var meta = $('.jinyu-hitokoto-meta', box);
                        (meta || box).appendChild(fromEl);
                    }
                } else if (fromEl) {
                    fromEl.parentNode.removeChild(fromEl);
                }
                box.classList.remove('is-swapping');
            };

            // 首屏 swap 发生在小工具入场动画之前，看不到闪动；
            // PJAX 换页时内容已可见，走一个短淡出再落字。
            if (box.classList.contains('is-in') || !root.classList.contains('jinyu-widget-anim-ready')) {
                box.classList.add('is-swapping');
                setTimeout(apply, 160);
            } else {
                apply();
            }
        }

        boxes.forEach(function (box) {
            // 每个实例只初始化一次：PJAX 换页会换成新 DOM（无此标记 → 重抽首屏那条），
            // 同一份 DOM 上重复调用（reinit）则直接跳过，避免二次随机与重复绑定。
            if (box.hasAttribute('data-hitokoto-init')) return;

            var list;
            try {
                list = JSON.parse(box.getAttribute('data-jinyu-quotes'));
            } catch (e) {
                return; // 数据异常就保持 PHP 首屏那条，不破坏渲染
            }
            if (!list || list.length < 2) return;

            box.setAttribute('data-hitokoto-init', '1');
            swapTo(box, list, pickOther(box, list));

            var btn = $('[data-hitokoto-next]', box);
            if (btn) {
                btn.addEventListener('click', function () {
                    swapTo(box, list, pickOther(box, list));
                });
            }
        });
    }

    /* ======================================================================
       销售与变现触点（CTA 条 / 悬浮客服 / 广告追踪 / 订阅）
       ====================================================================== */
    function sales() {
        // 全站悬浮 CTA 条：延迟出现 + 关闭
        var ctaBar = $('#jinyu-cta-bar');
        if (ctaBar) {
            var delay = parseInt(ctaBar.getAttribute('data-delay'), 10);
            if (isNaN(delay) || delay < 0) delay = 3;
            setTimeout(function () {
                ctaBar.hidden = false;
                ctaBar.classList.add('is-visible');
            }, delay * 1000);
            var ctaClose = $('#jinyu-cta-close');
            if (ctaClose) ctaClose.addEventListener('click', function () {
                ctaBar.classList.remove('is-visible');
                ctaBar.hidden = true;
            });
            var ctaText = $('.jinyu-cta-text', ctaBar);
            var ctaLink = $('.jinyu-cta-link', ctaBar);
            if (ctaLink) ctaLink.addEventListener('click', function () {
                trackEvent('cta', ctaText ? ctaText.textContent.trim() : 'cta');
            });
        }

        // 侧边悬浮客服 / 微信：点击切换，外部点击收起
        var floatBtn = $('#jinyu-float-btn');
        var floatPop = $('#jinyu-float-pop');
        if (floatBtn && floatPop) {
            floatBtn.addEventListener('click', function () {
                if (floatPop.hasAttribute('hidden')) floatPop.removeAttribute('hidden');
                else floatPop.setAttribute('hidden', '');
            });
            doc.addEventListener('click', function (e) {
                if (floatPop.hasAttribute('hidden')) return;
                if (!floatPop.contains(e.target) && e.target !== floatBtn) {
                    floatPop.setAttribute('hidden', '');
                }
            });
        }

        // 广告位曝光 / 点击追踪
        $$('.jinyu-ad[data-ad-slot]').forEach(function (ad) {
            var slot = ad.getAttribute('data-ad-slot') || '';
            if ('IntersectionObserver' in window) {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (en) {
                        if (en.isIntersecting) {
                            trackEvent('ad_imp', slot);
                            io.unobserve(en.target);
                        }
                    });
                }, { threshold: 0.5 });
                io.observe(ad);
            }
            ad.addEventListener('click', function (e) {
                var a = e.target && e.target.closest ? e.target.closest('a') : null;
                if (a) trackEvent('ad_clk', slot);
            });
        });

        // 邮件订阅表单（短代码 / 小工具 / 文末自动）
        $$('[data-jinyu-subscribe]').forEach(function (box) {
            var form = $('.jinyu-subscribe-form', box);
            if (!form) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var emailEl = form.querySelector('input[name="email"]');
                var msg = $('.jinyu-subscribe-msg', box);
                if (!emailEl || !emailEl.value) return;
                var btn = form.querySelector('.jinyu-subscribe-btn');
                if (btn) btn.disabled = true;
                var srcEl = form.querySelector('input[name="source"]');
                var source = srcEl ? srcEl.value : 'form';
                Util.ajax('jinyu_subscribe', { email: emailEl.value, source: source, nonce: CFG.nonce }, function (res) {
                    if (btn) btn.disabled = false;
                    if (!msg) return;
                    if (res && res.success) {
                        msg.textContent = (res.data && res.data.msg) || '订阅成功';
                        msg.setAttribute('data-ok', '1');
                        form.reset();
                    } else {
                        msg.textContent = (res && res.data && res.data.msg) || '订阅失败，请重试';
                        msg.setAttribute('data-ok', '0');
                    }
                    msg.hidden = false;
                });
            });
        });

        function trackEvent(kind, meta) {
            Util.ajax('jinyu_track_event', {
                kind: kind,
                meta: meta || '',
                post_id: (typeof CFG.post_id !== 'undefined' ? CFG.post_id : 0),
                nonce: CFG.nonce
            });
        }
    }

    function social() {
        doc.addEventListener('click', function (e) {
            // 关注 / 取关（用户或系列）
            var btn = e.target.closest('[data-jinyu-follow]');
            if (btn) {
                e.preventDefault();
                if (!CFG.logged_in) {
                    if (window.jinyuAuthOpen) window.jinyuAuthOpen('login');
                    return;
                }
                if (btn.classList.contains('jinyu-loading')) return;
                btn.classList.add('jinyu-loading');
                Util.ajax('jinyu_toggle_follow', {
                    target: btn.dataset.target,
id: btn.dataset.id
                }, function (err, res) {
                    btn.classList.remove('jinyu-loading');
                    if (err || !res || !res.success) {
                        if (res && res.data) Util.toast(res.data);
                        return;
                    }
                    var on = !!res.data.following;
                    btn.classList.toggle('is-following', on);
                    var span = btn.querySelector('span');
                    var icon = btn.querySelector('i');
                    if (btn.dataset.target === 'user') {
                        if (icon) icon.className = 'fa-solid ' + (on ? 'fa-user-check' : 'fa-user-plus');
                        if (span) span.textContent = on ? '已关注' : '关注';
                    } else {
                        if (span) span.textContent = on ? '已收藏系列' : '收藏系列';
                    }
                });
                return;
            }

            // 全部已读
            var readAll = e.target.closest('[data-jinyu-notif-readall]');
            if (readAll) {
                e.preventDefault();
                if (!CFG.logged_in) return;
                Util.ajax('jinyu_mark_read', {}, function (err, res) {
                    if (err || !res || !res.success) return;
                    var listEl = doc.querySelector('[data-jinyu-notif-list]');
                    if (listEl) {
                        var items = listEl.querySelectorAll('.jinyu-notif-item');
                        for (var i = 0; i < items.length; i++) items[i].classList.add('is-read');
                    }
                    updateNotifBadge(res.data.unread);
                    Util.toast(_t('allRead', '已全部标记已读'));
                });
                return;
            }
        });

        // 首屏若在消息 tab，自动加载列表
        var listEl = doc.querySelector('[data-jinyu-notif-list]');
        if (listEl && !listEl.dataset.loaded) {
            listEl.dataset.loaded = '1';
            Util.ajax('jinyu_get_notifications', { page: 1 }, function (err, res) {
                if (err || !res || !res.success) return;
                listEl.innerHTML = res.data.html;
                updateNotifBadge(res.data.unread);
            });
        }

        function updateNotifBadge(unread) {
            var link = doc.querySelector('.jinyu-user-notif-link');
            if (!link) return;
            var badge = link.querySelector('.jinyu-badge');
            unread = parseInt(unread, 10) || 0;
            if (unread > 0) {
                if (!badge) {
                    badge = doc.createElement('span');
                    badge.className = 'jinyu-badge';
                    link.appendChild(badge);
                }
                badge.textContent = unread > 99 ? '99+' : String(unread);
            } else if (badge) {
                badge.parentNode.removeChild(badge);
            }
        }
    }

    /* 作者卡封面离子动画：轻量 canvas。性能要点：
       - 预渲染发光精灵，避免每帧 shadowBlur（最贵的操作）；
       - 单一共享 RAF，只绘制「可见」实例，离屏/切后台/减弱动画即停；
       - 粒子数极少（20）、DPR 封顶 2、颜色读主题主色，暗亮主题自动和谐；
       - 用模块级实例表，PJAX 后重复调用只追加新卡、不重复开循环。 */
    var ionInstances = [];
    var ionLoop = 0;
    var ionHidden = document.visibilityState === 'hidden';
    var ionBound = false;

    function ionPrimaryRGB() {
        var v = getComputedStyle(root).getPropertyValue('--jinyu-c-primary-rgb').trim();
        return v || '124,58,237'; // 缺省紫，呼应头像
    }

    function ionMakeGlow(rgb, a) {
        var s = 32, c = doc.createElement('canvas');
        c.width = c.height = s;
        var g = c.getContext('2d');
        var grd = g.createRadialGradient(s / 2, s / 2, 0, s / 2, s / 2, s / 2);
        grd.addColorStop(0, 'rgba(' + rgb + ',' + a + ')');
        grd.addColorStop(0.3, 'rgba(' + rgb + ',' + (a * 0.6) + ')');
        grd.addColorStop(1, 'rgba(' + rgb + ',0)');
        g.fillStyle = grd;
        g.fillRect(0, 0, s, s);
        return c;
    }

    function ionSize(inst) {
        var rect = inst.cover.getBoundingClientRect();
        var w = Math.max(1, Math.round(rect.width));
        var h = Math.max(1, Math.round(rect.height));
        inst.dpr = Math.min(window.devicePixelRatio || 1, 2);
        inst.canvas.width = w * inst.dpr;
        inst.canvas.height = h * inst.dpr;
        inst.canvas.style.width = w + 'px';
        inst.canvas.style.height = h + 'px';
        inst.w = w; inst.h = h;
        inst.ctx.setTransform(inst.dpr, 0, 0, inst.dpr, 0, 0);
    }

    function ionSeed(inst) {
        inst.particles = [];
        for (var i = 0; i < 20; i++) {
            inst.particles.push({
                x: Math.random() * inst.w,
                y: Math.random() * inst.h,
                vx: (Math.random() - 0.5) * 0.25,
                vy: (Math.random() - 0.5) * 0.25,
                r: 1 + Math.random() * 2,
                ph: Math.random() * Math.PI * 2,
                sp: 0.01 + Math.random() * 0.02,
                b: Math.random() < 0.5 ? inst.glowA : inst.glowB
            });
        }
    }

    function ionDraw(inst) {
        var c = inst.ctx, w = inst.w, h = inst.h, ps = inst.particles, pr = inst.pr;
        c.clearRect(0, 0, w, h);
        var i, j, p, q, dx, dy, dist;
        for (i = 0; i < ps.length; i++) {
            p = ps[i];
            for (j = i + 1; j < ps.length; j++) {
                q = ps[j];
                dx = p.x - q.x; dy = p.y - q.y;
                dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 40) {
                    c.globalAlpha = (1 - dist / 40) * 0.22;
                    c.strokeStyle = 'rgba(' + pr + ',1)';
                    c.lineWidth = 0.6;
                    c.beginPath();
                    c.moveTo(p.x, p.y);
                    c.lineTo(q.x, q.y);
                    c.stroke();
                }
            }
        }
        for (i = 0; i < ps.length; i++) {
            p = ps[i];
            p.x += p.vx; p.y += p.vy; p.ph += p.sp;
            if (p.x < -4) p.x = w + 4; else if (p.x > w + 4) p.x = -4;
            if (p.y < -4) p.y = h + 4; else if (p.y > h + 4) p.y = -4;
            var tw = 0.55 + 0.45 * Math.sin(p.ph);
            var size = p.r * 6;
            c.globalAlpha = tw;
            c.drawImage(p.b, p.x - size / 2, p.y - size / 2, size, size);
        }
        c.globalAlpha = 1;
    }

    function ionFrame() {
        ionLoop = 0;
        var any = false;
        for (var i = 0; i < ionInstances.length; i++) {
            var inst = ionInstances[i];
            if (inst.visible && !inst.reduce) { any = true; ionDraw(inst); }
        }
        if (any && !ionHidden) ionLoop = requestAnimationFrame(ionFrame);
    }

    function ionEnsure() {
        if (ionLoop || ionHidden) return;
        for (var i = 0; i < ionInstances.length; i++) {
            if (ionInstances[i].visible && !ionInstances[i].reduce) {
                ionLoop = requestAnimationFrame(ionFrame);
                break;
            }
        }
    }

    function coverIons() {
        var covers = $$('.jinyu-author-cover');
        if (!covers.length) return;
        if (!window.requestAnimationFrame || !doc.createElement('canvas').getContext) return;

        var pr = ionPrimaryRGB();

        covers.forEach(function (cover) {
            if (cover.querySelector('.jinyu-cover-ion')) return; // 已初始化
            var canvas = doc.createElement('canvas');
            canvas.className = 'jinyu-cover-ion';
            cover.insertBefore(canvas, cover.firstChild);
            var inst = {
                cover: cover, canvas: canvas, ctx: canvas.getContext('2d'),
                w: 0, h: 0, dpr: 1, particles: [],
                visible: true, reduce: reduceMotion,
                pr: pr, glowA: ionMakeGlow(pr, 0.9), glowB: ionMakeGlow('34,211,238', 0.85)
            };
            ionSize(inst);
            ionSeed(inst);
            if (inst.reduce) ionDraw(inst); // 减弱动画：画一帧静态即可
            ionInstances.push(inst);

            if ('IntersectionObserver' in window) {
                var io = new IntersectionObserver(function (ents) {
                    ents.forEach(function (e) {
                        inst.visible = e.isIntersecting;
                        if (inst.visible) ionEnsure();
                    });
                }, { threshold: 0.05 });
                io.observe(cover);
            }
        });

        if (!ionBound) {
            ionBound = true;
            var rz;
            window.addEventListener('resize', function () {
                clearTimeout(rz);
                rz = setTimeout(function () {
                    ionInstances.forEach(function (inst) {
                        ionSize(inst); ionSeed(inst); if (inst.reduce) ionDraw(inst);
                    });
                }, 200);
            });
            document.addEventListener('visibilitychange', function () {
                ionHidden = document.visibilityState === 'hidden';
                if (!ionHidden) ionEnsure();
            });
        }

        ionEnsure();
    }

    /* 申请友链页：本站信息一键复制 + 申请表单提交（验证码按需懒载，避免无表单的页面白白请求） */
    function flinkApply() {
        var wrap = $('.jinyu-flink-apply-page');
        if (!wrap) return;

        $$('[data-jinyu-copy]', wrap).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-jinyu-copy') || '';
                if (!text) return;
                var label = btn.querySelector('span');
                var old = label ? label.textContent : '';
                var done = function (ok) {
                    if (!ok) { window.prompt('复制此内容：', text); return; }
                    btn.classList.add('is-done');
                    if (label) label.textContent = _t('copied', '已复制');
                    setTimeout(function () {
                        btn.classList.remove('is-done');
                        if (label) label.textContent = old;
                    }, 1400);
                };
                Util.copy(text).then(function () { done(true); })
                    .catch(function () { done(false); });
            });
        });

        var form = $('[data-jinyu-flink-apply]', wrap);
        if (!form) return;

        var cap = $('[data-jinyu-captcha]', form);
        var capImg = cap ? cap.querySelector('.jinyu-captcha-img') : null;
        function loadCaptcha() {
            if (!cap || !capImg) return;
            capImg.hidden = false;
            capImg.src = cap.getAttribute('data-jinyu-captcha-src') + '&r=' + Date.now();
        }
        loadCaptcha();
        if (cap) cap.addEventListener('click', function (e) {
            if (e.target.closest('[data-jinyu-captcha-refresh], .jinyu-captcha-img')) loadCaptcha();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var tip = $('[data-jinyu-flink-tip]', form);
            var btn = form.querySelector('button[type="submit"]');
            var label = btn ? btn.textContent : '';
            function get(n) {
                var el = form.querySelector('[name="' + n + '"]');
                return el ? el.value.trim() : '';
            }

            $$('input.is-error', form).forEach(function (el) { el.classList.remove('is-error'); });

            var name = get('site_name'), url = get('site_url'), email = get('site_email');
            var bad = '';
            if (name.length < 2 || name.length > 50) bad = '网站名称需 2-50 个字符';
            else if (!/^https?:\/\/[^\s/$.?#][^\s]*$/i.test(url)) bad = '网站地址需以 http:// 或 https:// 开头';
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) bad = '请填写有效的联系邮箱';
            else if (capImg && !capImg.hidden && !get('captcha')) bad = '请填写验证码';

            if (bad) { Util.showTip(tip, bad, false); return; }

            var data = {};
            ['site_name', 'site_url', 'site_desc', 'site_icon', 'site_email', 'captcha', 'jy_hp'].forEach(function (n) {
                data[n] = get(n);
            });

            if (btn) { btn.disabled = true; btn.textContent = _t('submitting', '提交中…'); }
            Util.showTip(tip, '');

            Util.ajax('jinyu_flink_apply', data, function (err, res) {
                if (err || !res || !res.success) {
                    if (btn) { btn.disabled = false; btn.textContent = label; }
                    Util.showTip(tip, (res && res.data) ? res.data : '提交失败，请稍后重试', false);
                    loadCaptcha();   // 验证码一次性，失败后必须换一张
                    return;
                }
                var done = $('[data-jinyu-flink-done]', wrap);
                form.hidden = true;
                if (done) {
                    done.hidden = false;
                    done.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
                } else if (btn) {
                    btn.disabled = false; btn.textContent = label;
                    Util.showTip(tip, res.data.message, true);
                }
            });
        });
    }

    window.jinyuReinit = function () {
        [toc, codeBlock, codeHighlight, lightbox, reveal, carousel,
         readProgress, backTop, loadMore, pageJump, headerScroll, coverFallback,
         liveSearch, fontScale, recentViewed, hoverCard, coView, articleVote, copyGuard, paraCopy, sidebarLive,
         widgetAnim, hitokoto, sales, social, coverIons, flinkApply].forEach(function (fn) {
            try { fn(); } catch (e) { /* 单模块异常不阻断 */ }
        });
    };

    /* Web Vitals 采集：原生 PerformanceObserver，零依赖、零第三方。
       浏览器仅向本站 admin-ajax 发同源请求，由服务端本地落库，供后台「性能优化中心」展示。
       LCP/INP/CLS 为主指标，FCP/TTFB 辅助定位瓶颈。 */
    function webVitals() {
        if (typeof PerformanceObserver === 'undefined') return;

        var m = { lcp: 0, inp: 0, cls: 0, fcp: 0, ttfb: 0 };
        var done = false;

        function send() {
            if (done) return;
            if (!m.lcp && !m.inp && !m.cls && !m.fcp && !m.ttfb) return;
            done = true;
            var payload = {
                lcp: Math.round(m.lcp),
                inp: Math.round(m.inp),
                cls: Math.round(m.cls * 1000) / 1000,
                fcp: Math.round(m.fcp),
                ttfb: Math.round(m.ttfb),
                path: location.pathname
            };
            if (CFG.theme_version) payload.theme_version = CFG.theme_version;
            // 同源发往本站 admin-ajax（action=jinyu_web_vitals）：服务端本地落库，供后台「性能优化中心」展示。无私有后端依赖。
            try {
                var fd = new URLSearchParams();
                fd.set('action', 'jinyu_web_vitals');
                fd.set('data', JSON.stringify(payload));
                var url = CFG.ajax_url || '/wp-admin/admin-ajax.php';
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, fd);
                } else {
                    fetch(url, { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin' });
                }
            } catch (e) { /* 采集失败不应影响页面 */ }
        }

        try {
            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                if (entries.length) {
                    var last = entries[entries.length - 1];
                    m.lcp = last.renderTime || last.loadTime;
                }
            }).observe({ type: 'largest-contentful-paint', buffered: true });

            var clsVal = 0;
            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                for (var i = 0; i < entries.length; i++) {
                    if (!entries[i].hadRecentInput) clsVal += entries[i].value;
                }
                m.cls = clsVal;
            }).observe({ type: 'layout-shift', buffered: true });

            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                for (var i = 0; i < entries.length; i++) {
                    var v = entries[i].processingEnd - entries[i].startTime;
                    if (v > m.inp) m.inp = v;
                }
            }).observe({ type: 'event', buffered: true, durationThreshold: 16 });

            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                if (entries.length) m.fcp = entries[0].startTime;
            }).observe({ type: 'paint', buffered: true });

            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                if (entries.length) m.ttfb = entries[0].responseStart;
            }).observe({ type: 'navigation', buffered: true });
        } catch (e) {
            return; // 旧浏览器不支持，静默跳过
        }

        // 主上报：页面卸载时（INP/CLS 已充分累积，数据最完整）
        window.addEventListener('pagehide', send);
        // 兜底：切换标签 / 切入后台（覆盖只切走不关闭的场景）
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') send();
        });
        // 长停留兜底：加载后 10s 仍未卸载则上报，避免数据永不发出
        // （10s 时 LCP/FCP/TTFB/CLS 已基本稳定，比原 1.5s 失真更小）
        window.addEventListener('load', function () { setTimeout(send, 10000); });
    }

    var MODULES = [
        theme, greyMode, nav, search, readProgress, readEstimate, backTop, toc, liveSearch, fontScale, recentViewed,
        hoverCard, coView, articleVote, copyGuard, paraCopy,
        codeBlock, codeHighlight, coverFallback, lazyImg, blurImg, postActions, share, reveal, pointerEffects,
        headerScroll, loadMore, pageJump, shortcodes, ajaxComment, auth, userForms,
        poster, qrcodeModule, donate, lightbox, carousel, pjax, luck, faviconBadge, sidebarLive,
        widgetAnim, hitokoto, sales, social, webVitals, coverIons, flinkApply
    ];

    function boot() {
        skeleton();
        commentSmiley(); // 显式直接调用，避免被 terser 当 dead code 删除
        MODULES.forEach(function (fn) {
            try {
                fn();
            } catch (err) {
                // 单个模块异常不应阻断其余功能
                if (window.console && console.error) console.error('[jinyu] module failed:', fn.name, err);
            }
        });
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})(window, document);
