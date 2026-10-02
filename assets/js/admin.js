/**
 * 金玉主题 · 后台设置中心
 * 纯原生 JS（经 babel→uglify 构建），无第三方依赖。
 * 设计要点：单次渲染 + 事件委托 + 脏检查，保证无 bug 与高性能。
 */
(function () {
    'use strict';

    var S = window.JINYU_SETTING || {};
    var GROUPS = S.groups || [];
    var SAVED = S.options || {};

    // 后台界面文案字典：由 PHP 侧 wp_localize_script( 'JINYU_ADMIN_I18N' ) 注入。
    // 本页所有界面都是 JS 动态拼装的，走不到 PHP 的 __()，所以必须单独传一份。
    // 早前这些文案直接硬编码英文 → 中文站后台「设置」页整片英文（Add item / Reset this
    // group / Selected: N items / 整个维护工具面板），而语言包里其实早有对应译文。
    var T = window.JINYU_ADMIN_I18N || {};

    /**
     * 取译文。T 里没有的键回退到英文原文本身 —— 词条表与代码不同步时最多显示英文，
     * 不会渲染成 "undefined"。
     *
     * @param {string} key  词条键
     * @param {string} fallback 缺译文时的英文原文
     * @return {string}
     */
    function t(key, fallback) {
        var v = T[key];
        return (typeof v === 'string' && v !== '') ? v : fallback;
    }

    /**
     * 带占位符的译文（printf 风格）。
     *
     * @param {string} key       词条键
     * @param {Array}  params    替换值
     * @param {string} fallback  缺译文时的英文原文（需已含占位符）
     * @return {string}
     */
    function tf(key, params, fallback) {
        var s = t(key, fallback), i = 0;
        // 三条必须这么写，缺一条都会静默出错：
        //  1. `%%` 先存成哨兵 —— 否则译文里的字面量百分号（如 "100%% 完成"）会被当占位符
        //  2. 裸占位符（%s / %d）用游标顺序推进 —— 不推进的话 "%s 与 %s" 会得到 "A 与 A"，
        //     而中文译者改写译文时几乎一定会改成裸占位符（中文语序不需要保持英文的位置式）
        //  3. 位置式（%1$s）按序号取 —— 供"同一参数要出现两次"或"跳过第一个"的情况
        s = s.replace(/%%/g, '\u0000PCT\u0000');
        s = s.replace(/%(\d+\$)?[sd]/g, function (m, pos) {
            var idx = pos ? parseInt(pos, 10) - 1 : i++;
            return params[idx] !== undefined ? params[idx] : m;
        });
        return s.replace(/\u0000PCT\u0000/g, '%');
    }

    // 分组图标（WordPress 内置 dashicons，后台必然可用）
    var ICONS = {
        basic: 'dashicons-admin-home',
        global: 'dashicons-admin-site',
        style: 'dashicons-admin-appearance',
        footer: 'dashicons-admin-page',
        content: 'dashicons-admin-post',
        home_modules: 'dashicons-images-alt2',
        seo: 'dashicons-search',
        user: 'dashicons-admin-users',
        email: 'dashicons-email',
        resource: 'dashicons-admin-tools',
        extend: 'dashicons-admin-plugins',
        ai: 'dashicons-format-chat',
        code: 'dashicons-editor-code',
        about: 'dashicons-info',
        tools: 'dashicons-admin-settings',
        storage: 'dashicons-cloud'
    };

    var currentKey = getInitialKey();
    var snapshot = '{}';
    var root = null;
    var dirtybar = null;

    /* 左侧导航：扁平高级列表，无分区标题（保持大厂审美）。分组级脏检测仍按字段映射进行。 */

    /* 分组 → 字段 id 映射（仅取已在面板渲染的字段），用于分组级脏检测 */
    var groupFieldMap = {};
    GROUPS.forEach(function (g) {
        groupFieldMap[g.key] = (g.fields || []).map(function (f) { return f.id; });
    });
    var groupSnapshots = {};
    function groupSig(all, key) {
        var ids = groupFieldMap[key] || [];
        var sub = {};
        ids.forEach(function (id) { if (id in all) sub[id] = all[id]; });
        return JSON.stringify(sub);
    }
    function buildGroupSnapshots() {
        var all = collect();
        GROUPS.forEach(function (g) {
            if (groupFieldMap[g.key]) groupSnapshots[g.key] = groupSig(all, g.key);
        });
    }
    function refreshNavModified() {
        if (!root) return;
        var all = collect();
        GROUPS.forEach(function (g) {
            if (!groupFieldMap[g.key]) return;
            var nav = qs('.jinyu-nav-item[data-nav="' + g.key + '"]', root);
            if (!nav) return;
            nav.classList.toggle('is-modified', groupSig(all, g.key) !== groupSnapshots[g.key]);
        });
    }

    /* ---------- 工具函数 ---------- */
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    // 初始分组：URL hash 优先（后台提醒「前往重建」会带 #tools），其次 localStorage 记忆，最后默认首组
    function getInitialKey() {
        if (!GROUPS.length) return '';
        var valid = {};
        GROUPS.forEach(function (g) { valid[g.key] = true; });
        var h = (location.hash || '').replace(/^#/, '');
        if (h && valid[h]) return h;
        try {
            var stored = localStorage.getItem('jinyu_set_tab_' + location.pathname);
            if (stored && valid[stored]) return stored;
        } catch (e) {}
        return GROUPS[0].key;
    }

    /* ---------- 动态列表（拖拽构建器） ---------- */
    var dynDragEl = null;

    function renderDynItem(f, it, i) {
        var model = f.dynamicModel || [];
        var sub = '<div class="jinyu-dyn-item-body">';
        model.forEach(function (m) {
            var mv = (it && it[m.id] !== undefined) ? it[m.id] : (m.sdt !== undefined ? m.sdt : (m.type === 'switch' ? false : ''));
            var inner = '';
            if (m.type === 'switch') {
                inner = '<label class="jinyu-switch jinyu-switch--sm"><input type="checkbox" data-dyn-sub="switch" data-dyn-key="' + esc(m.id) + '"' + (mv ? ' checked' : '') + '><span class="jinyu-switch-track"></span></label>';
            } else if (m.type === 'img' || m.type === 'upload') {
                inner = '<div class="jinyu-upload jinyu-upload--sm">' +
                    '<input type="text" class="jinyu-input jinyu-dyn-sub" data-dyn-sub="upload" data-dyn-key="' + esc(m.id) + '" value="' + esc(mv || '') + '" placeholder="' + esc(t('clickToSelectSm', 'Click to select or enter a URL')) + '">' +
                    '<button type="button" class="button jinyu-dyn-upload" data-dyn-upload>' + esc(t('selectBtn', 'Select')) + '</button></div>';
            } else if (m.type === 'select') {
                var sopts = '';
                (m.options || []).forEach(function (o) {
                    sopts += '<option value="' + esc(o.value) + '"' + (String(mv) === String(o.value) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                });
                inner = '<select class="jinyu-input jinyu-dyn-sub" data-dyn-sub="select" data-dyn-key="' + esc(m.id) + '">' + sopts + '</select>';
            } else if (m.type === 'password') {
                // 密钥类子字段：永远空渲染，不把明文/密文写回 value；保存时空值表示沿用原值（opt.php 保留库中已加密值）
                inner = '<input type="password" class="jinyu-input jinyu-dyn-sub" data-dyn-sub="password" data-dyn-key="' + esc(m.id) + '" value="" autocomplete="new-password" placeholder="' + esc(m.placeholder || '') + '">';
            } else {
                inner = '<input type="text" class="jinyu-input jinyu-dyn-sub" data-dyn-sub="text" data-dyn-key="' + esc(m.id) + '" value="' + esc(mv || '') + '">';
            }
            sub += '<div class="jinyu-dyn-subrow"><span class="jinyu-dyn-sublabel">' + esc(m.label || m.id) + '</span>' + inner + '</div>';
        });
        sub += '</div>';
        return '<div class="jinyu-dyn-item" draggable="true">' +
            '<div class="jinyu-dyn-item-bar">' +
            '<span class="jinyu-dyn-grip" title="' + esc(t('dragToSort', 'Drag to sort')) + '">⠿</span>' +
            '<span class="jinyu-dyn-title">' + esc((it && it.title) ? it.title : tf('item', [i + 1], 'Item ' + (i + 1))) + '</span>' +
            '<span class="jinyu-dyn-actions">' +
            '<button type="button" class="jinyu-dyn-btn" data-dyn-up title="' + esc(t('moveUp', 'Move up')) + '">↑</button>' +
            '<button type="button" class="jinyu-dyn-btn" data-dyn-down title="' + esc(t('moveDown', 'Move down')) + '">↓</button>' +
            '<button type="button" class="jinyu-dyn-btn" data-dyn-copy title="' + esc(t('copyItem', 'Copy')) + '">⧉</button>' +
            '<button type="button" class="jinyu-dyn-btn jinyu-dyn-del" data-dyn-del title="' + esc(t('deleteItem', 'Delete')) + '">×</button>' +
            '</span></div>' + sub + '</div>';
    }

    function renderDynamicList(f, value) {
        var id = f.id;
        var items = value;
        if (typeof value === 'string') {
            try { items = JSON.parse(value); } catch (e) { items = []; }
        }
        if (!Array.isArray(items)) items = [];
        var model = f.dynamicModel || [];
        var showRefAttr = f.showRefId ? ' data-show-ref="' + esc(f.showRefId) + '"' : '';
        var maxAttr = (f.max && f.max > 0) ? ' data-max="' + esc(f.max) + '"' : '';
        var html = '<div class="jinyu-field jinyu-field--dynlist" data-field="' + esc(id) + '"' + showRefAttr + '>' +
            '<label class="jinyu-field-label">' + esc(f.title) +
            (f.max ? ' <span class="jinyu-dyn-count"></span>' : '') + '</label>' +
            '<input type="hidden" data-key="' + esc(id) + '" data-type="dynamic-list" value="' + esc(JSON.stringify(items)) + '">' +
            '<div class="jinyu-dynlist" data-dynlist="' + esc(id) + '" data-model="' + esc(JSON.stringify(model)) + '"' + maxAttr + '">';
        items.forEach(function (it, i) { html += renderDynItem(f, it, i); });
        html += '</div>' +
            '<button type="button" class="button jinyu-dyn-add" data-dyn-add="' + esc(id) + '">+ ' + esc(t('addItem', 'Add item')) + '</button>' +
            (f.max ? ' <span class="jinyu-field-desc">' + esc(tf('upToItems', [f.max], 'Up to ' + f.max + ' items')) + '</span>' : '') +
            (f.desc ? '<p class="jinyu-field-desc">' + esc(f.desc) + '</p>' : '') +
            '</div>';
        return html;
    }

    function syncDyn(wrapper) {
        var id = wrapper.getAttribute('data-dynlist');
        var hidden = qs('input[data-key="' + id + '"]', root);
        if (!hidden) return;
        var arr = [];
        qsa('.jinyu-dyn-item', wrapper).forEach(function (item) {
            var obj = {};
            qsa('[data-dyn-sub]', item).forEach(function (s) {
                var subType = s.getAttribute('data-dyn-sub');
                var subKey = s.getAttribute('data-dyn-key');
                obj[subKey] = (subType === 'switch') ? s.checked : s.value;
            });
            arr.push(obj);
        });
        hidden.value = JSON.stringify(arr);
        refreshDynUI(wrapper);
    }

    function dynAtMax(wrapper) {
        var max = parseInt(wrapper.getAttribute('data-max') || '0', 10);
        if (!max) return false;
        return qsa('.jinyu-dyn-item', wrapper).length >= max;
    }

    function refreshDynUI(wrapper) {
        var field = wrapper.closest('.jinyu-field--dynlist');
        var max = parseInt(wrapper.getAttribute('data-max') || '0', 10);
        var count = qsa('.jinyu-dyn-item', wrapper).length;
        if (max) {
            var lbl = field ? qs('.jinyu-dyn-count', field) : null;
            if (lbl) lbl.textContent = '(' + count + '/' + max + ')';
            var add = field ? qs('.jinyu-dyn-add', field) : null;
            if (add) add.disabled = count >= max;
        }
    }

    function addDynItem(id) {
        var wrapper = qs('[data-dynlist="' + id + '"]', root);
        if (!wrapper) return;
        if (dynAtMax(wrapper)) return;
        var model = [];
        try { model = JSON.parse(wrapper.getAttribute('data-model') || '[]'); } catch (e) {}
        var f = { dynamicModel: model };
        var idx = qsa('.jinyu-dyn-item', wrapper).length;
        wrapper.insertAdjacentHTML('beforeend', renderDynItem(f, {}, idx));
        syncDyn(wrapper);
        refreshDynUI(wrapper);
        markDirty();
    }

    function copyDynItem(item) {
        var wrapper = item.closest('[data-dynlist]');
        if (!wrapper || dynAtMax(wrapper)) return;
        var clone = item.cloneNode(true);
        // 复制品的标题加「副本」后缀，避免与原件混淆
        var titleEl = qs('.jinyu-dyn-title', clone);
        if (titleEl) titleEl.textContent = titleEl.textContent + t('copySuffix', ' copy');
        item.parentNode.insertBefore(clone, item.nextSibling);
        syncDyn(wrapper);
        refreshDynUI(wrapper);
        markDirty();
    }

    function moveDyn(item, dir) {
        var sibling = dir < 0 ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        if (dir < 0) item.parentNode.insertBefore(item, sibling);
        else item.parentNode.insertBefore(sibling, item);
        syncDyn(item.closest('[data-dynlist]'));
        markDirty();
    }

    function openMediaDyn(item) {
        if (typeof window.wp === 'undefined' || !window.wp.media) return;
        var input = qs('.jinyu-dyn-sub[data-dyn-sub="upload"]', item);
        if (!input) return;
        var frame = window.wp.media({ title: t('selectImage', 'Select image'), multiple: false });
        frame.on('select', function () {
            var url = frame.state().get('selection').first().toJSON().url;
            input.value = url;
            syncDyn(item.closest('[data-dynlist]'));
            markDirty();
        });
        frame.open();
    }

    function onDynDragStart(e) {
        var grip = e.target.closest('.jinyu-dyn-grip');
        var item = e.target.closest('.jinyu-dyn-item');
        if (!item || !grip) return;
        dynDragEl = item;
        item.classList.add('jinyu-dyn-dragging');
        e.dataTransfer.effectAllowed = 'move';
    }
    function onDynDragOver(e) {
        if (!dynDragEl) return;
        var item = e.target.closest('.jinyu-dyn-item');
        if (!item || item === dynDragEl) return;
        e.preventDefault();
        var rect = item.getBoundingClientRect();
        var next = (e.clientY - rect.top) / rect.height > 0.5;
        if (next) dynDragEl.parentNode.insertBefore(dynDragEl, item.nextSibling);
        else dynDragEl.parentNode.insertBefore(dynDragEl, item);
    }
    function onDynDragEnd() {
        if (dynDragEl) {
            dynDragEl.classList.remove('jinyu-dyn-dragging');
            syncDyn(dynDragEl.closest('[data-dynlist]'));
            markDirty();
            dynDragEl = null;
        }
    }

    /* ---------- 多选下拉（category-multi / page-multi / checkboxes） ----------
       收起态仅一行：占位符或已选 chip；展开态：搜索 + 可滚动勾选列表。
       存储仍为逗号分隔字符串，勾选顺序即写入顺序（前端分栏顺序）。 */
    function renderMultiSelect(f, items, checkedStr) {
        var id = f.id || '';
        // 勾选顺序 = 存储顺序（前端分栏顺序），初始渲染即按此回填 chips 与列表勾选态
        var sel = String(checkedStr || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var order = {};
        sel.forEach(function (v, i) { order[v] = i + 1; });
        var chips = sel.map(function (v) {
            var name = (items || {})[v];
            return '<span class="jinyu-ms-chip"><span class="jinyu-ms-chip-txt">' + esc(name || v) + '</span>' +
                '<span class="jinyu-ms-chip-ord">' + order[v] + '</span>' +
                '<button type="button" class="jinyu-ms-chip-rm" data-ms-rm="' + esc(v) + '" aria-label="' + esc(t('removeChip', 'Remove')) + '">×</button></span>';
        }).join('');
        // 自适应折叠：先渲染全部 chip + 「+N」徽标，实际显示个数由 layoutMultiChips 按触发框宽度测量决定
        // 注意：显隐必须用 style.display —— .jinyu-ms-chip 的 display:inline-flex 会覆盖 hidden 属性
        var moreChip = '<span class="jinyu-ms-chip jinyu-ms-chip-more" style="display:none"></span>';
        // 初始按钮文案：可见项（初始无过滤=全部）是否全部已选
        var allKeys = Object.keys(items || {});
        var allSel = allKeys.length > 0 && allKeys.every(function (k) { return order[k] !== undefined; });
        var allLabel = allSel ? t('deselectAll', 'Deselect all') : t('selectAll', 'Select all');
        var list = '';
        Object.keys(items || {}).forEach(function (k) {
            var on = order[k] !== undefined;
            list += '<div class="jinyu-ms-item' + (on ? ' is-on' : '') + '" data-val="' + esc(k) + '" role="option" aria-selected="' + (on ? 'true' : 'false') + '">' +
                '<span class="jinyu-ms-box" aria-hidden="true"><svg viewBox="0 0 24 24"><polyline points="4 12 10 18 20 6"/></svg></span>' +
                '<span class="jinyu-ms-nm">' + esc(items[k]) + '</span>' +
                '<span class="jinyu-ms-ord" aria-hidden="true">' + (on ? '#' + order[k] : '') + '</span></div>';
        });
        if (!list) list = '<p class="jinyu-ms-empty">' + esc(f.empty || t('noOptions', 'No options available')) + '</p>';
        var initCount = sel.length;
        return '<div class="jinyu-field jinyu-field--multi" data-field="' + esc(id) + '">' +
            '<div class="jinyu-field-labelrow">' +
            '<label class="jinyu-field-label">' + esc(f.title) + '</label>' +
            '<span class="jinyu-multi-count" data-multi-count="' + esc(id) + '"' + (initCount ? '' : ' hidden') + '>' + esc(tf('selectedItems', [initCount], 'Selected: ' + initCount + ' items')) + '</span>' +
            '</div>' +
            '<div class="jinyu-ms" data-multi-box="' + esc(id) + '">' +
            '<div class="jinyu-ms-trigger" role="button" tabindex="0" aria-haspopup="listbox">' +
            '<span class="jinyu-ms-placeholder' + (initCount ? ' is-hidden' : '') + '">' + esc(f.placeholder || t('selectPlaceholder', 'Select…')) + '</span>' +
            '<div class="jinyu-ms-chips">' + chips + moreChip + '</div>' +
            '<span class="jinyu-ms-caret" aria-hidden="true"></span>' +
            '</div>' +
            '<div class="jinyu-ms-panel">' +
            '<div class="jinyu-ms-search"><input type="text" class="jinyu-ms-q" placeholder="' + esc(t('searchPlaceholder', 'Search…')) + '" autocomplete="off">' +
            '<button type="button" class="jinyu-ms-all" data-ms-all>' + allLabel + '</button></div>' +
            '<div class="jinyu-ms-list" role="listbox" aria-multiselectable="true">' + list + '</div>' +
            '</div>' +
            '</div>' +
            '<input type="hidden" data-key="' + esc(id) + '" data-type="category-multi" value="' + esc(checkedStr || '') + '">' +
            (f.desc ? '<p class="jinyu-field-desc">' + esc(f.desc) + '</p>' : '') +
            '</div>';
    }

    // 依据 hidden input 当前值刷新下拉框视图（chips / 列表勾选态 / 计数 / 清空按钮）
    function refreshMulti(box) {
        var id = box.getAttribute('data-multi-box');
        var hidden = qs('input[data-key="' + id + '"]', root);
        if (!hidden) return;
        var sel = String(hidden.value || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        var order = {};
        sel.forEach(function (v, i) { order[v] = i + 1; });

        var names = {};
        qsa('.jinyu-ms-item', box).forEach(function (it) {
            var nm = qs('.jinyu-ms-nm', it);
            names[it.getAttribute('data-val')] = nm ? nm.textContent : '';
        });

        var chipsWrap = qs('.jinyu-ms-chips', box);
        if (chipsWrap) {
            // 重建全部 chip（旧的 chip 与「+N」徽标一并清掉，徽标含 jinyu-ms-chip 类）
            qsa('.jinyu-ms-chip', chipsWrap).forEach(function (n) { n.remove(); });
            sel.forEach(function (v) {
                var chip = document.createElement('span');
                chip.className = 'jinyu-ms-chip';
                chip.innerHTML = '<span class="jinyu-ms-chip-txt">' + esc(names[v] || v) + '</span>' +
                    '<span class="jinyu-ms-chip-ord">' + order[v] + '</span>' +
                    '<button type="button" class="jinyu-ms-chip-rm" data-ms-rm="' + esc(v) + '" aria-label="' + esc(t('removeChip', 'Remove')) + '">×</button>';
                chipsWrap.appendChild(chip);
            });
            if (!qs('.jinyu-ms-chip-more', chipsWrap)) {
                var more0 = document.createElement('span');
                more0.className = 'jinyu-ms-chip jinyu-ms-chip-more';
                more0.style.display = 'none';
                chipsWrap.appendChild(more0);
            }
            layoutMultiChips(box);   // 按触发框实际宽度自适应折叠，保证单行
        }
        var ph = qs('.jinyu-ms-placeholder', box);
        if (ph) ph.classList.toggle('is-hidden', sel.length > 0);

        // 全选/全不选 按钮文案随「可见项是否全部已选」切换
        refreshMultiAllBtn(box);

        qsa('.jinyu-ms-item', box).forEach(function (it) {
            var v = it.getAttribute('data-val');
            var on = order[v] !== undefined;
            it.classList.toggle('is-on', on);
            it.setAttribute('aria-selected', on ? 'true' : 'false');
            var ord = qs('.jinyu-ms-ord', it);
            if (ord) ord.textContent = on ? '#' + order[v] : '';
        });

        var cnt = qs('[data-multi-count="' + id + '"]', root);
        if (cnt) { cnt.textContent = tf('selectedItems', [sel.length], 'Selected: ' + sel.length + ' items'); cnt.hidden = !sel.length; }
    }

    // 依据当前可见项是否全部已选，切换全选/全不选按钮文案
    function refreshMultiAllBtn(box) {
        var allBtn = qs('.jinyu-ms-all', box);
        if (!allBtn) return;
        var order = {};
        var hidden = qs('input[data-key="' + box.getAttribute('data-multi-box') + '"]', root);
        String(hidden && hidden.value || '').split(',').forEach(function (s) {
            s = s.trim(); if (s) order[s] = true;
        });
        var vis = [];
        qsa('.jinyu-ms-item', box).forEach(function (it) { if (!it.hidden) vis.push(it.getAttribute('data-val')); });
        var allOn = vis.length > 0 && vis.every(function (v) { return order[v] === true; });
        allBtn.textContent = allOn ? t('deselectAll', 'Deselect all') : t('selectAll', 'Select all');
    }

    // 自适应折叠：按触发框（.jinyu-ms-chips）实际可用宽度显示尽可能多的 chip，
    // 放不下的折叠进「+N」徽标，保证触发框恒定一行。面板不可见（display:none）时跳过，待可见后重算。
    function layoutMultiChips(box) {
        var wrap = qs('.jinyu-ms-chips', box);
        var more = qs('.jinyu-ms-chip-more', box);
        if (!wrap || !more) return;
        var chips = qsa('.jinyu-ms-chip:not(.jinyu-ms-chip-more)', wrap);
        if (!chips.length) { more.style.display = 'none'; return; }
        var avail = wrap.clientWidth;
        if (!avail) return;
        var gap = 6;
        // 先全部可见，判断是否整行能放下（无需 +N）
        chips.forEach(function (c) { c.style.display = ''; });
        more.style.display = 'none';
        var used = 0, allFit = true;
        chips.forEach(function (c) {
            var w = c.offsetWidth;
            if (used + w > avail) allFit = false;
            used += w + gap;
        });
        if (allFit) return;
        // 放不下：显示徽标（先给占位文案保证有真实宽度）测出 reserve，再按预算裁切
        more.textContent = '+' + chips.length;
        more.style.display = '';
        var reserve = more.offsetWidth + gap;
        var budget = avail - reserve;
        used = 0; var shown = 0;
        chips.forEach(function (c) {
            var w = c.offsetWidth;
            var need = w + (shown ? gap : 0);
            if (used + need <= budget) { used += need; shown++; }
            else { c.style.display = 'none'; }
        });
        var hiddenN = chips.length - shown;
        if (hiddenN > 0) { more.textContent = '+' + hiddenN; }
        else { more.style.display = 'none'; }
    }
    function layoutMultiAll() {
        qsa('.jinyu-ms', root).forEach(layoutMultiChips);
    }

    function setMultiValue(box, values) {
        var id = box.getAttribute('data-multi-box');
        var hidden = qs('input[data-key="' + id + '"]', root);
        if (!hidden) return;
        hidden.value = values.join(',');
        refreshMulti(box);
        markDirty();
    }

    // 多选下拉：按关键字过滤列表项（仅显隐，不改选中态）
    function filterMultiList(box, kw) {
        kw = String(kw || '').trim().toLowerCase();
        var shown = 0;
        qsa('.jinyu-ms-item', box).forEach(function (it) {
            var nm = qs('.jinyu-ms-nm', it);
            var hit = !kw || (nm ? nm.textContent.toLowerCase().indexOf(kw) >= 0 : false);
            it.hidden = !hit;
            if (hit) shown++;
        });
        var empty = qs('.jinyu-ms-empty', box);
        if (empty) empty.hidden = !!shown;
    }

    function closeAllMsPop(except) {
        qsa('.jinyu-ms.is-open', root).forEach(function (b) {
            if (b !== except) b.classList.remove('is-open');
        });
    }

    /**
     * 多选面板定位：默认在触发框下方展开；当下方可视空间放不下整块面板、且上方更宽敞时，
     * 翻到触发框上方（is-up），避免面板越过内容区底边被裁掉、或压住下一行字段。
     */
    function placeMsPanel(box) {
        var panel = qs('.jinyu-ms-panel', box);
        if (!panel) return;
        box.classList.remove('is-up');
        var rect = box.getBoundingClientRect();
        var scroller = qs('.jinyu-main', root);
        var area = scroller ? scroller.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
        var below = area.bottom - rect.bottom;
        var above = rect.top - area.top;
        if (panel.offsetHeight + 16 > below && above > below) box.classList.add('is-up');
    }

    /* ---------- 字段渲染 ---------- */
    function fieldHtml(f) {
        var id = f.id || '';
        var hasVal = (SAVED[id] !== undefined && SAVED[id] !== null);
        var type = f.type || 'string';
        // 面板内小节标题：整行分节标题，无 id、不进入保存数据。
        // （必须显式处理：否则会掉进 default 分支被渲染成空 <input> 输入框）
        if (type === 'subhead') {
            return '<div class="jinyu-subhead">' + esc(f.title || '') + '</div>';
        }
        var v;
        if (hasVal) {
            v = SAVED[id];
        } else if (f.sdt !== undefined) {
            v = f.sdt;
        } else {
            v = '';
        }
        // 文本类字段：已存值为空字符串时，输入框仍展示 sdt 出厂默认值，便于用户基于模板直接修改
        if ((v === '' || v === null) && (type === 'string' || type === 'text' || type === 'textarea') && f.sdt !== undefined && f.sdt !== '') {
            v = f.sdt;
        }
        var showRefAttr = f.showRefId ? ' data-show-ref="' + esc(f.showRefId) + '"' : '';

        // 多选（分类 / 页面 ID 复选框树），存逗号分隔字符串，向后兼容旧文本字段
        if (type === 'category-multi') {
            return renderMultiSelect(f, S.cats || {}, v);
        }

        // 预设选项多选（分享渠道等）：复用多选渲染器，选项来自 f.options
        if (type === 'checkboxes') {
            var optMap = {};
            (f.options || []).forEach(function (o) { optMap[o.value] = o.label; });
            return renderMultiSelect(f, optMap, v);
        }

        // 开关：独立布局（标题+描述在左，开关在右）
        if (type === 'switch') {
            return '<div class="jinyu-field jinyu-field--switch" data-field="' + esc(id) + '"' + showRefAttr + '">' +
                '<div class="jinyu-field-head jinyu-field-head--switch">' +
                '<div class="jinyu-field-labelwrap">' +
                '<span class="jinyu-field-label">' + esc(f.title) +
                (f.tip ? '<span class="jinyu-field-tip" title="' + esc(f.tip) + '">?</span>' : '') + '</span>' +
                (f.desc ? '<p class="jinyu-field-desc">' + esc(f.desc) + '</p>' : '') +
                '</div>' +
                '<label class="jinyu-switch">' +
                '<input type="checkbox" data-key="' + esc(id) + '" data-type="switch"' + (v ? ' checked' : '') + '>' +
                '<span class="jinyu-switch-track"></span>' +
                '</label>' +
                '</div></div>';
        }

        // 信息提示
        if (type === 'info') {
            return '<div class="jinyu-field jinyu-info" data-field="' + esc(id) + '">' +
                '<div class="jinyu-info-box">' + esc(f.desc || '') + '</div></div>';
        }

        // 检查更新（按钮 + 状态区，不进入保存数据）
        if (type === 'update_check') {
            return '<div class="jinyu-field jinyu-field--update" data-field="' + esc(id) + '">' +
                '<label class="jinyu-field-label">' + esc(f.title) + '</label>' +
                '<div class="jinyu-field-body">' +
                '<div class="jinyu-update-box">' +
                '<button type="button" class="jinyu-btn jinyu-btn-primary" data-check-update>' + esc(t('checkUpdates', 'Check for updates')) + '</button>' +
                '<span class="jinyu-update-status" data-update-status></span>' +
                '</div>' +
                '<div class="jinyu-update-detail" data-update-detail></div>' +
                '</div>' +
                '</div>';
        }

        if (type === 'dynamic-list') {
            return renderDynamicList(f, v);
        }

        var body = '';
        switch (type) {
            case 'textarea':
                body = '<textarea class="jinyu-input jinyu-textarea' + (f.code ? ' jinyu-textarea--code' : '') + '" data-key="' + esc(id) + '" data-type="textarea" rows="' + (f.rows || 4) + '"' + (f.code ? ' spellcheck="false"' : '') + (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '') + '>' + esc(v) + '</textarea>';
                break;
            case 'select': {
                // 数据源：原生 select（视觉隐藏，collect/showRef 等逻辑照常读 .value）
                var curLabel = '';
                body = '<div class="jinyu-selectwrap">';
                body += '<select class="jinyu-select-src" data-key="' + esc(id) + '" data-type="select">';
                (f.options || []).forEach(function (o) {
                    if (String(v) === String(o.value)) curLabel = o.label;
                    body += '<option value="' + esc(o.value) + '"' + (String(v) === String(o.value) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                });
                body += '</select>';
                // 自定义下拉 UI（品牌化样式，替代原生 select 的浏览器默认外观）
                body += '<div class="jinyu-select" data-select="' + esc(id) + '">' +
                    '<button type="button" class="jinyu-select-btn" data-select-toggle aria-haspopup="listbox" aria-expanded="false">' +
                    '<span class="jinyu-select-label">' + esc(curLabel) + '</span>' +
                    '<span class="jinyu-select-caret dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>' +
                    '</button>' +
                    '<div class="jinyu-select-pop" role="listbox" hidden>';
                (f.options || []).forEach(function (o) {
                    var act = String(v) === String(o.value);
                    body += '<button type="button" class="jinyu-select-opt' + (act ? ' is-active' : '') + '" role="option" aria-selected="' + (act ? 'true' : 'false') + '" data-select-opt value="' + esc(o.value) + '">' +
                        '<span class="jinyu-select-opt-label">' + esc(o.label) + '</span>' +
                        (act ? '<span class="jinyu-select-check dashicons dashicons-yes" aria-hidden="true"></span>' : '') +
                        '</button>';
                });
                body += '</div></div></div>';
                break;
            }
            case 'radio':
                body = '<div class="jinyu-radios">';
                (f.options || []).forEach(function (o) {
                    body += '<label class="jinyu-radio"><input type="radio" name="rd_' + esc(id) + '" data-key="' + esc(id) + '" data-type="radio" value="' + esc(o.value) + '"' + (String(v) === String(o.value) ? ' checked' : '') + '><span>' + esc(o.label) + '</span></label>';
                });
                body += '</div>';
                break;
            case 'color':
                body = '<div class="jinyu-color">' +
                    '<input type="color" class="jinyu-color-swatch" data-color-swatch="' + esc(id) + '" value="' + esc(v || '#1C60F3') + '">' +
                    '<input type="text" class="jinyu-input jinyu-color-text" data-key="' + esc(id) + '" data-type="color" value="' + esc(v || '') + '" placeholder="#1C60F3">' +
                    '<button type="button" class="button jinyu-color-clear" data-color-clear="' + esc(id) + '">' + esc(t('clearColor', 'Clear')) + '</button>' +
                    '</div>';
                if (f.presets && f.presets.length) {
                    body += '<div class="jinyu-color-presets">';
                    f.presets.forEach(function (p) {
                        body += '<button type="button" class="jinyu-color-preset" data-color-preset="' + esc(id) + '" data-color-val="' + esc(p) + '" style="background:' + esc(p) + '" title="' + esc(p) + '"></button>';
                    });
                    body += '</div>';
                }
                break;
            case 'upload':
                body = '<div class="jinyu-upload">' +
                    '<input type="text" class="jinyu-input jinyu-upload-url" data-key="' + esc(id) + '" data-type="upload" value="' + esc(v || '') + '" placeholder="' + esc(t('clickToSelect', 'Click the button on the right to select, or enter a URL')) + '">' +
                    '<button type="button" class="button jinyu-upload-btn" data-upload="' + esc(id) + '">' + esc(t('selectBtn', 'Select')) + '</button>' +
                    '<button type="button" class="button jinyu-upload-remove" data-upload-remove="' + esc(id) + '"' + (v ? '' : ' hidden') + '>' + esc(t('removeFile', 'Remove')) + '</button>' +
                    '</div>' +
                    '<div class="jinyu-upload-preview" data-upload-preview="' + esc(id) + '">' + (v ? '<img src="' + esc(v) + '" alt="">' : '') + '</div>';
                break;
            case 'slider':
                var mi = (f.min !== undefined ? f.min : 0);
                var ma = (f.max !== undefined ? f.max : 100);
                var st = (f.step !== undefined ? f.step : 1);
                body = '<div class="jinyu-range">' +
                    '<input type="range" class="jinyu-range-input" data-key="' + esc(id) + '" data-type="slider" min="' + mi + '" max="' + ma + '" step="' + st + '" value="' + esc(v) + '">' +
                    '<input type="number" class="jinyu-input jinyu-range-num" data-range-num="' + esc(id) + '" min="' + mi + '" max="' + ma + '" step="' + st + '" value="' + esc(v) + '">' +
                    (f.unit ? '<span class="jinyu-unit">' + esc(f.unit) + '</span>' : '') +
                    '</div>';
                break;
            case 'number':
                body = '<div class="jinyu-number">' +
                    '<input type="number" class="jinyu-input" data-key="' + esc(id) + '" data-type="number"' +
                    (f.min !== undefined ? ' min="' + f.min + '"' : '') +
                    (f.max !== undefined ? ' max="' + f.max + '"' : '') +
                    (f.step !== undefined ? ' step="' + f.step + '"' : '') +
                    ' value="' + esc(v) + '">' +
                    (f.unit ? '<span class="jinyu-unit">' + esc(f.unit) + '</span>' : '') +
                    '</div>';
                break;
            case 'password':
                body = '<div class="jinyu-password">' +
                    '<input type="password" class="jinyu-input" data-key="' + esc(id) + '" data-type="password" value="" autocomplete="new-password" placeholder="' + esc(t('pwdKeepUnchanged', 'Set. Leave empty to keep unchanged')) + '">' +
                    '<button type="button" class="jinyu-pw-toggle" data-pw-toggle="' + esc(id) + '" title="' + esc(t('showHide', 'Show/hide')) + '"><i class="dashicons dashicons-visibility"></i></button>' +
                    '</div>';
                break;
            default: // string
                body = '<input type="text" class="jinyu-input" data-key="' + esc(id) + '" data-type="string" value="' + esc(v) + '"' + (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '') + '>';
        }

        return '<div class="jinyu-field jinyu-field--' + esc(type) + '" data-field="' + esc(id) + '"' + showRefAttr + '>' +
            '<label class="jinyu-field-label" for="jinyu-f-' + esc(id) + '">' + esc(f.title) +
            (f.tip ? '<span class="jinyu-field-tip" title="' + esc(f.tip) + '">?</span>' : '') + '</label>' +
            '<div class="jinyu-field-body">' + body + '</div>' +
            (f.desc ? '<p class="jinyu-field-desc">' + esc(f.desc) + '</p>' : '') +
            '</div>';
    }

    function groupHtml(g) {
        if (g.hidden) return '';
        var body;
        if (g.custom === 'tools') {
            body = toolsHtml();
        } else {
            body = (g.fields || []).map(fieldHtml).join('');
        }
        // 自定义面板（维护工具 / 我要反馈）不持有设置字段，无需「重置本组」
        var resetBtn = g.custom ? '' :
            '<button type="button" class="jinyu-panel-reset" data-reset-group="' + esc(g.key) + '" title="' + esc(t('resetGroupTitle', 'Reset only this group to defaults')) + '">' + esc(t('resetGroup', 'Reset this group')) + '</button>';
        var gIcon = ICONS[g.key] || 'dashicons-admin-generic';
        // 自定义面板（维护工具 / 我要反馈）的内容自带独立卡片（jinyu-tools-list / jinyu-fb），
        // 不再包 .jinyu-panel-body 外层圆角白卡，避免卡片套卡片的双层背景
        var wrappedBody = g.custom ? body : ('<div class="jinyu-panel-body">' + body + '</div>');
        return '<section class="jinyu-panel' + (g.key === currentKey ? ' is-active' : '') + '" data-panel="' + esc(g.key) + '">' +
            '<header class="jinyu-panel-head">' +
            '<span class="jinyu-panel-icon dashicons ' + gIcon + '"></span>' +
            '<div class="jinyu-panel-headtext">' +
            '<h2 class="jinyu-panel-title">' + esc(g.title) + '</h2>' +
            (g.desc ? '<p class="jinyu-panel-desc">' + esc(g.desc) + '</p>' : '') +
            '</div>' +
            resetBtn +
            '</header>' +
            wrappedBody +
            '</section>';
    }

    /* 维护工具面板（并入「扩展与开发」分区，仅操作按钮，不进入保存数据） */

    /* 工具行（维护工具 / 对象存储共用）：左图标 + 中文案 + 右动作 */
    function toolRow(icon, title, desc, actionHtml) {
        return '<div class="jinyu-tool-row">' +
            '<span class="jinyu-tool-ico"><i class="dashicons dashicons-' + icon + '"></i></span>' +
            '<div class="jinyu-tool-main">' +
            '<div class="jinyu-tool-title">' + title + '</div>' +
            '<p class="jinyu-tool-desc">' + desc + '</p>' +
            '</div>' +
            '<div class="jinyu-tool-side">' + actionHtml + '</div>' +
            '</div>';
    }
    function toolAction(btnId, btnText, tipId) {
        return '<button type="button" class="jinyu-tool-btn" id="' + btnId + '">' + btnText + '</button>' +
            '<span id="' + tipId + '" class="jinyu-tools-tip"></span>';
    }

    /**
     * 重建封面缩略图那一行 + 其下方的进度区。
     * 进度区常驻 DOM（只是默认隐藏），这样切换设置分组再切回来仍能看见进度，
     * 不必依赖「必须正在点按钮」这个前提。
     */
    function thumbsToolRow() {
        // dashicons 没有 "images" 这个类（只有 images-alt / images-alt2 / format-gallery），
        // 写错会渲染出一个空白图标框
        return toolRow('format-gallery', t('regenThumbs', 'Regenerate cover thumbnails'), t('regenThumbsDesc', 'Generate the theme image sizes jinyu-cover (768×512) and jinyu-thumb (400×267) for previously uploaded covers, so cards load small images instead of originals. Only images missing these sizes are processed, in batches until complete.'), toolAction('jinyu-regenerate-thumbs', t('startRebuild', 'Start rebuild'), 'jinyu-regenerate-thumbs-tip')) +
            '<div class="jinyu-thumbs-progress" id="jinyu-thumbs-progress" hidden>' +
            '<div class="jinyu-thumbs-progress-head">' +
            '<span class="jinyu-thumbs-progress-label" id="jinyu-thumbs-progress-label">' + esc(t('preparing', 'Preparing…')) + '</span>' +
            '<span class="jinyu-thumbs-progress-pct" id="jinyu-thumbs-progress-pct">0%</span>' +
            '</div>' +
            '<div class="jinyu-thumbs-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="jinyu-thumbs-progress-bar">' +
            '<span class="jinyu-thumbs-progress-fill" id="jinyu-thumbs-progress-fill"></span>' +
            '</div>' +
            '<div class="jinyu-thumbs-progress-foot">' +
            '<span class="jinyu-thumbs-progress-detail" id="jinyu-thumbs-progress-detail"></span>' +
            '<button type="button" class="jinyu-tool-btn jinyu-tool-btn--sm" id="jinyu-thumbs-stop">' + esc(t('stop', 'Stop')) + '</button>' +
            '</div>' +
            '</div>';
    }

    function toolsHtml() {
        var runOn = !!SAVED.footer_runinfo;
        var runSwitch = '<span class="jinyu-tool-state' + (runOn ? ' on' : '') + '" id="jinyu-runinfo-state">' + (runOn ? esc(t('enabled', 'Enabled')) : esc(t('disabled', 'Disabled'))) + '</span>' +
            '<label class="jinyu-switch jinyu-switch--sm">' +
            '<input type="checkbox" id="jinyu-runinfo-toggle"' + (runOn ? ' checked' : '') + '>' +
            '<span class="jinyu-switch-track"></span>' +
            '</label>' +
                    '<span id="jinyu-runinfo-tip" class="jinyu-tools-tip"></span>';
        return '<div class="jinyu-tools-list" id="jinyu-tools">' +
            thumbsToolRow() +
            toolRow('download', t('exportSettings', 'Export settings'), t('exportSettingsDesc', 'Export all theme settings to a JSON file for backup and multi-site migration.'), toolAction('jinyu-export', t('exportJson', 'Export JSON'), 'jinyu-export-tip')) +
            toolRow('upload', t('importSettings', 'Import settings'), t('importSettingsDesc', 'Restore theme settings from a JSON file. This overwrites all current settings — export a backup first.'), toolAction('jinyu-import', t('chooseFileImport', 'Choose a file and import'), 'jinyu-import-tip') + '<input type="file" id="jinyu-import-file" accept="application/json,.json" hidden>') +
            // 注：标题原本就是中文硬编码，JS 侧取不到 PHP 的 __()。
            // 保持中文字面量，不要"统一"成 __()——那是 PHP 函数，写进 JS 会 ReferenceError 导致整页白屏。
            toolRow('chart-bar', '页脚运行信息', t('runinfoDesc', 'Output real-time run info in the footer (queries / memory / render time). After enabling, clear the cache once in "Jinyu Booster" for it to take effect.'), runSwitch) +
            '</div>' +
            // 注：此前这里有一行「以下能力由金玉增强插件提供……」的归属说明，
            // 按站长要求已移除。邮件 SMTP / 缓存清理 / 数据库优化等运维能力本就归属
            // 配套插件（jinyu-theme-companion），主题侧不重复提供入口 ——
            // 两套实现会各持一套 nonce，跨插件调用必然被 check_ajax_referer 打回。
            '</div>';
    }

    function sidebarHtml() {
        var head = '<div class="jinyu-nav-head">' +
            '<span class="jinyu-nav-brand"><span class="jinyu-nav-logo" aria-hidden="true"></span>' +
            '<span class="jinyu-nav-title">' + esc(t('brandTitle', 'Jinyu Theme Settings')) + '</span></span>' +
            '<button type="button" class="jinyu-nav-toggle" data-nav-toggle aria-label="' + esc(t('collapseSidebar', 'Collapse / expand sidebar')) + '" title="' + esc(t('collapseSidebar', 'Collapse / expand sidebar')) + '">' +
            '<span class="dashicons dashicons-arrow-left-alt2"></span></button>' +
            '</div>';
        var html = '<nav class="jinyu-nav" aria-label="' + esc(t('settingsGroups', 'Settings groups')) + '">' + head;
        GROUPS.forEach(function (g) {
            if (g.hidden) return;
            html += navItemHtml(g);
        });
        html += '</nav>';
        return html;
    }

    function navItemHtml(g) {
        var ic = ICONS[g.key] || 'dashicons-admin-generic';
        return '<button type="button" class="jinyu-nav-item' + (g.key === currentKey ? ' is-active' : '') + '" data-nav="' + esc(g.key) + '" data-label="' + esc(g.title) + '">' +
            '<span class="jinyu-nav-ic"><span class="dashicons ' + ic + '"></span></span>' +
            '<span class="jinyu-nav-text">' + esc(g.title) + '</span>' +
            '<span class="jinyu-nav-dot" aria-hidden="true"></span>' +
            '</button>';
    }

    /* ---------- 数据收集 ---------- */
    function collect() {
        var data = {};
        qsa('[data-key]', root).forEach(function (el) {
            var key = el.getAttribute('data-key');
            var type = el.getAttribute('data-type');
            if (type === 'radio' && !el.checked) return;
            var val = (type === 'switch') ? (el.checked ? 1 : 0) : el.value;
            data[key] = val;
        });
        return data;
    }

    /* ---------- 脏检查 / 提示 ---------- */
    /* 与快照逐键比对，返回改动项数量（用于提示条上的计数徽标） */
    function dirtyCount(cur) {
        var base = {};
        try { base = JSON.parse(snapshot) || {}; } catch (e) { base = {}; }
        var n = 0;
        Object.keys(cur).forEach(function (k) {
            if (!(k in base) || String(base[k]) !== String(cur[k])) n++;
        });
        Object.keys(base).forEach(function (k) { if (!(k in cur)) n++; });
        return n;
    }

    function markDirty() {
        if (!dirtybar) return;
        var cur = collect();
        var dirty = JSON.stringify(cur) !== snapshot;
        dirtybar.hidden = !dirty;
        var badge = qs('.jinyu-dirty-count', dirtybar);
        if (badge) {
            var n = dirty ? dirtyCount(cur) : 0;
            badge.textContent = n + ' ' + t('items', 'items');
            badge.hidden = n < 1;
        }
        refreshNavModified();
    }

    /* ---------- 条件显示（showRefId 依赖） ---------- */
    function applyShowRef() {
        qsa('[data-show-ref]', root).forEach(function (f) {
            var refId = f.getAttribute('data-show-ref');
            var ref = qs('[data-key="' + refId + '"]', root);
            var show = ref ? (ref.type === 'checkbox' ? ref.checked : !!ref.value) : true;
            f.hidden = !show;
        });
    }

    /* ---------- 渲染 ---------- */

    /**
     * 把模板里的顶栏搬进 .jinyu-panels 首位，与内容卡共享同一个 padding 盒。
     * 幂等：已是内容列子节点则不动（「放弃更改」会清空 root 重跑 build）。
     */
    function dockTopbar() {
        var bar = document.querySelector('.jinyu-topbar');
        var panels = root && qs('.jinyu-panels', root);
        if (!bar || !panels || bar.parentNode === panels) return;
        panels.insertBefore(bar, panels.firstChild);
    }

    /** 面包屑末级跟随当前分组名（分组名不再在内容卡卡头重复出现，由面包屑承载） */
    function syncCrumb(key) {
        var crumbCur = document.getElementById('jinyu-crumb-cur');
        if (!crumbCur) return;
        var cg = GROUPS.filter(function (x) { return x.key === key; })[0];
        if (cg) crumbCur.textContent = cg.title;
    }

    function build() {
        root.innerHTML = '<div class="jinyu-layout">' + sidebarHtml() +
            '<div class="jinyu-main"><div class="jinyu-panels">' +
            GROUPS.map(groupHtml).join('') +
            '<div class="jinyu-search-empty" hidden>' + esc(t('noMatching', 'No matching settings. Try a different keyword')) + '</div>' +
            '</div></div></div>';

        // 顶栏归位到内容列：它必须与内容卡共享同一个 padding 盒（.jinyu-panels），
        // 左右缘才能严格共线。此前它是 .jinyu-setting-wrap 的直接子级，右缘比内容卡多出 42px
        // （漏掉内容列 32px 侧沟 + .jinyu-main 的 10px 滚动条槽），正是「顶栏右边超出内容区」的根因。
        // 放进滚动容器后 position:sticky 依然生效（相对 .jinyu-main 的滚动口吸顶）。
        dockTopbar();
        syncCrumb(currentKey);

        // 还原侧栏折叠态（localStorage 持久化，刷新不丢）。
        // 仅桌面恢复：移动端标签条没有折叠交互，带着折叠类会把胶囊压成细条（文字被 width:0 隐藏）
        try {
            if ((!window.matchMedia || matchMedia('(min-width: 901px)').matches)
                && localStorage.getItem('jinyu_nav_collapsed') === '1') {
                var lay = qs('.jinyu-layout', root);
                if (lay) lay.classList.add('nav-collapsed');
            }
        } catch (e) {}

        // 委托事件
        root.addEventListener('click', onRootClick);
        root.addEventListener('input', onInput);
        root.addEventListener('change', onInput);
        root.addEventListener('dragstart', onDynDragStart);
        root.addEventListener('dragover', onDynDragOver);
        root.addEventListener('dragend', onDynDragEnd);

        snapshot = JSON.stringify(collect());
        applyShowRef();
        qsa('[data-dynlist]', root).forEach(refreshDynUI);   // 初始同步「N/M」计数与「添加一项」可用态
        layoutMultiAll();   // 初始可见面板的多选框按宽度自适应折叠
        buildGroupSnapshots();
        refreshNavModified();

        // 带 hash 直达（后台提醒「前往重建」→ #tools）时滚到「维护工具」面板，
        // 首次渲染时元素才就位，滚动只能放在这里
        var anchor = qs('.jinyu-panel.is-active #jinyu-tools', root);
        if (anchor && anchor.scrollIntoView) {
            try { anchor.scrollIntoView({ block: 'start' }); } catch (e) {}
        }
    }

    /**
     * 切换面板。viaHash=true 表示由 #tools 锚点直达（应停在该面板的维护工具区），
     * 否则是用户点侧栏导航（一律回到页面顶部）。
     */
    function activate(key, viaHash) {
        currentKey = key;
        try { localStorage.setItem('jinyu_set_tab_' + location.pathname, key); } catch (e) {}
        if (history.replaceState) { try { history.replaceState(null, '', '#' + key); } catch (e) {} }
        syncCrumb(key);
        qsa('.jinyu-nav-item', root).forEach(function (n) {
            n.classList.toggle('is-active', n.getAttribute('data-nav') === key);
        });
        qsa('.jinyu-panel', root).forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
        layoutMultiAll();   // 新面板可见后重算多选框折叠，避免 width=0 误裁切
        var main = qs('.jinyu-main', root);
        if (main) main.scrollTop = 0;   // 横向滚动槽归零

        // 先让面板高度定型，再决定滚到哪：面板切换会大幅改变文档高度，
        // 浏览器随即把 scrollTop 钳制回上限，这一步不动，后面读到的就是被钳制后的值。
        if (viaHash) {
            // 锚点直达（后台提醒「前往重建」→ #tools）：落到维护工具区
            var anchor = qs('.jinyu-panel.is-active #jinyu-tools', root);
            if (anchor && anchor.scrollIntoView) {
                try { anchor.scrollIntoView({ block: 'start' }); } catch (e) {}
                restoreThumbsProgress();
                return;
            }
        }
        // 点侧栏导航：一律回到页面顶部。此刻不能沿用旧滚动位置——新面板比旧面板矮时
        // 浏览器会把它钳制到新高度（表现为「切到维护工具后整页往上窜」），
        // 而 #jinyu-tools 只存在于维护工具面板，旧代码据此误判「这里要锚点跳转」。
        scrollTopNow();
        // 面板可能刚重绘（切分组 / 首次进入），进度区要跟着恢复，
        // 否则正在跑的重建任务在用户切走再切回后就「消失」了
        restoreThumbsProgress();
    }

    /** 立即（非平滑）把页面滚动归零。CSS 有 scroll-behavior:smooth 时 scrollTo(0,0)
     *  也会被拉成动画，与「切面板应即时回顶」冲突，故显式传 behavior:'instant'。 */
    function scrollTopNow() {
        try { window.scrollTo({ top: 0, left: 0, behavior: 'instant' }); }
        catch (e) { try { window.scrollTo(0, 0); } catch (err) {} }
    }

    /**
     * 重绘后恢复进度区：只在仍有任务在跑时显示，收尾后不打扰。
     * 服务端状态是唯一事实来源，这里只负责把已有的最后一帧数据画出来。
     */
    var thumbsLastFrame = null;
    function restoreThumbsProgress() {
        if (!thumbsLastFrame) return;
        renderThumbsProgress(thumbsLastFrame.data, thumbsLastFrame.running);
    }

    /* ---------- 自定义下拉（select） ---------- */
    function closeAllSelectPops() {
        qsa('.jinyu-select-pop', root).forEach(function (p) {
            p.hidden = true;
            var w = p.closest('.jinyu-select');
            var b = w && qs('[data-select-toggle]', w);
            if (b) b.setAttribute('aria-expanded', 'false');
        });
    }

    function onRootClick(e) {
        // 折叠 / 展开侧栏开关
        var tog = e.target.closest('[data-nav-toggle]');
        if (tog) {
            var layout = qs('.jinyu-layout', root);
            if (layout) {
                var collapsed = layout.classList.toggle('nav-collapsed');
                try { localStorage.setItem('jinyu_nav_collapsed', collapsed ? '1' : '0'); } catch (err) {}
            }
            return;
        }

        var nav = e.target.closest('.jinyu-nav-item');
        if (nav) { activate(nav.getAttribute('data-nav'), false); return; }


        // 自定义下拉：展开 / 收起
        var sTg = e.target.closest('[data-select-toggle]');
        if (sTg) {
            var sWrap = sTg.closest('.jinyu-select');
            var sPop = sWrap && qs('.jinyu-select-pop', sWrap);
            if (sPop) {
                var opening = sPop.hidden;
                closeAllSelectPops();
                if (opening) { sPop.hidden = false; sWrap.classList.add('is-open'); sTg.setAttribute('aria-expanded', 'true'); }
            }
            return;
        }
        // 自定义下拉：选中选项 → 同步回隐藏的原生 select
        var sOpt = e.target.closest('[data-select-opt]');
        if (sOpt) {
            var oWrap = sOpt.closest('.jinyu-select');
            var oId = oWrap ? oWrap.getAttribute('data-select') : '';
            var src = oId ? qs('select[data-key="' + oId + '"]', root) : null;
            if (src && src.value !== sOpt.getAttribute('value')) {
                src.value = sOpt.getAttribute('value');
                markDirty();
            }
            if (oWrap) {
                var lbl = qs('.jinyu-select-label', oWrap);
                if (lbl) lbl.textContent = qs('.jinyu-select-opt-label', sOpt).textContent;
                qsa('[data-select-opt]', oWrap).forEach(function (b) {
                    var act = (b === sOpt);
                    b.classList.toggle('is-active', act);
                    b.setAttribute('aria-selected', act ? 'true' : 'false');
                    var chk = qs('.jinyu-select-check', b);
                    if (act && !chk) b.insertAdjacentHTML('beforeend', '<span class="jinyu-select-check dashicons dashicons-yes" aria-hidden="true"></span>');
                    if (!act && chk) chk.remove();
                });
            }
            closeAllSelectPops();
            return;
        }

        // 密码字段「显示/隐藏」
        var pw = e.target.closest('[data-pw-toggle]');
        if (pw) {
            var key = pw.getAttribute('data-pw-toggle');
            var inp = qs('input[data-key="' + key + '"]', root);
            if (inp) {
                var icon = pw.querySelector('i');
                if (inp.type === 'password') { inp.type = 'text'; if (icon) icon.className = 'dashicons dashicons-hidden'; }
                else { inp.type = 'password'; if (icon) icon.className = 'dashicons dashicons-visibility'; }
            }
            return;
        }

        // 动态列表：上移 / 下移 / 删除 / 复制
        var dynBtn = e.target.closest('[data-dyn-up],[data-dyn-down],[data-dyn-del],[data-dyn-copy]');
        if (dynBtn) {
            var dItem = dynBtn.closest('.jinyu-dyn-item');
            if (!dItem) return;
            if (dynBtn.hasAttribute('data-dyn-up')) moveDyn(dItem, -1);
            else if (dynBtn.hasAttribute('data-dyn-down')) moveDyn(dItem, 1);
            else if (dynBtn.hasAttribute('data-dyn-del')) {
                var dWrap = dItem.closest('[data-dynlist]');
                dItem.remove();
                if (dWrap) { syncDyn(dWrap); markDirty(); }
            } else if (dynBtn.hasAttribute('data-dyn-copy')) copyDynItem(dItem);
            return;
        }

        // 动态列表：新增一项
        var dynAdd = e.target.closest('[data-dyn-add]');
        if (dynAdd) {
            addDynItem(dynAdd.getAttribute('data-dyn-add'));
            return;
        }

        // 动态列表：子字段选择图片（媒体库）
        var dynUpload = e.target.closest('[data-dyn-upload]');
        if (dynUpload) {
            var upItem = dynUpload.closest('.jinyu-dyn-item');
            if (upItem) openMediaDyn(upItem);
            return;
        }

        // 重建缩略图：服务端分批返回进度，最多自动续跑 40 轮（够把几千张图跑完）
        if (e.target.closest('#jinyu-regenerate-thumbs')) {
            openThumbsProgress();
            postTool('jinyu_regenerate_thumbs', qs('#jinyu-regenerate-thumbs-tip'), {
                moreRounds: 60,
                onProgress: function (d, done) { renderThumbsProgress(d, !done); }
            });
            return;
        }
        if (e.target.closest('#jinyu-thumbs-stop')) {
            // 停止同样走一次请求：服务端清掉队列与闸门，下次点击重新扫描
            postTool('jinyu_regenerate_thumbs', qs('#jinyu-regenerate-thumbs-tip'), {
                onProgress: function (d) { renderThumbsProgress(d, false); }
            });
            return;
        }

        // 关于：检查主题更新
        if (e.target.closest('[data-check-update]')) { checkUpdate(e.target.closest('[data-check-update]')); return; }

        // 配置导入 / 导出
        if (e.target.closest('#jinyu-export')) { doExport(); return; }
        var impBtn = e.target.closest('#jinyu-import');
        if (impBtn) { var fi = qs('#jinyu-import-file', root); if (fi) fi.click(); return; }

        // 单分区重置
        var resetGrp = e.target.closest('[data-reset-group]');
        if (resetGrp) { resetSection(resetGrp.getAttribute('data-reset-group')); return; }

        // 多选下拉：全选/全不选 当前可见（过滤后）的项 —— 可见项全部已选则反选（移除可见），否则勾选全部可见
        var mAll = e.target.closest('[data-ms-all]');
        if (mAll) {
            var aBox = mAll.closest('[data-multi-box]');
            if (aBox) {
                var aHidden = qs('input[data-key="' + aBox.getAttribute('data-multi-box') + '"]', root);
                var aCur = String(aHidden && aHidden.value || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                var aVis = [];
                qsa('.jinyu-ms-item', aBox).forEach(function (it) {
                    if (!it.hidden) aVis.push(it.getAttribute('data-val'));
                });
                var aAllOn = aVis.length > 0 && aVis.every(function (v) { return aCur.indexOf(v) !== -1; });
                if (aAllOn) {
                    var aSet = {};
                    aVis.forEach(function (v) { aSet[v] = true; });
                    aCur = aCur.filter(function (v) { return !aSet[v]; });
                } else {
                    aVis.forEach(function (v) { if (aCur.indexOf(v) === -1) aCur.push(v); });
                }
                setMultiValue(aBox, aCur);
            }
            return;
        }

        // 多选下拉：chip × 移除（保持其余项的勾选顺序）
        var mRm = e.target.closest('[data-ms-rm]');
        if (mRm) {
            var rBox = mRm.closest('[data-multi-box]');
            if (rBox) {
                var rHidden = qs('input[data-key="' + rBox.getAttribute('data-multi-box') + '"]', root);
                var rVal = mRm.getAttribute('data-ms-rm');
                var rCur = String(rHidden && rHidden.value || '').split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s && s !== rVal; });
                setMultiValue(rBox, rCur);
            }
            return;
        }

        // 多选下拉：列表项勾选 / 取消（新勾选追加到末尾 → 勾选顺序即存储顺序）
        var mItem = e.target.closest('.jinyu-ms-item');
        if (mItem) {
            var iBox = mItem.closest('[data-multi-box]');
            if (iBox) {
                var iHidden = qs('input[data-key="' + iBox.getAttribute('data-multi-box') + '"]', root);
                var iVal = mItem.getAttribute('data-val');
                var iCur = String(iHidden && iHidden.value || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                var at = iCur.indexOf(iVal);
                if (at !== -1) iCur.splice(at, 1); else iCur.push(iVal);
                setMultiValue(iBox, iCur);
                var iQ = qs('.jinyu-ms-q', iBox);
                if (iQ) { iQ.value = ''; filterMultiList(iBox, ''); iQ.focus(); }
            }
            return;
        }

        // 多选下拉：展开 / 收起（同时收起其它已展开的下拉）
        var mTrg = e.target.closest('.jinyu-ms-trigger');
        if (mTrg) {
            var tBox = mTrg.closest('[data-multi-box]');
            if (tBox) {
                var willOpen = !tBox.classList.contains('is-open');
                closeAllMsPop();
                if (willOpen) placeMsPanel(tBox);
                tBox.classList.toggle('is-open', willOpen);
                if (willOpen) {
                    var tQ = qs('.jinyu-ms-q', tBox);
                    if (tQ) { tQ.value = ''; filterMultiList(tBox, ''); tQ.focus(); }
                }
            }
            return;
        }

        var up = e.target.closest('.jinyu-upload-btn');
        if (up) { openMedia(up.getAttribute('data-upload')); return; }

        var rm = e.target.closest('[data-upload-remove]');
        if (rm) {
            var rk = rm.getAttribute('data-upload-remove');
            var rfield = qs('[data-key="' + rk + '"]', root);
            var rprev = qs('[data-upload-preview="' + rk + '"]', root);
            if (rfield) rfield.value = '';
            if (rprev) rprev.innerHTML = '';
            rm.hidden = true;
            markDirty();
            return;
        }

        var cp = e.target.closest('[data-color-preset]');
        if (cp) {
            var pk = cp.getAttribute('data-color-preset');
            var pv = cp.getAttribute('data-color-val');
            var ptxt = qs('[data-key="' + pk + '"]', root);
            var psw = qs('[data-color-swatch="' + pk + '"]', root);
            if (ptxt) ptxt.value = pv;
            if (psw) psw.value = pv;
            markDirty();
            return;
        }

        var cc = e.target.closest('[data-color-clear]');
        if (cc) {
            var key = cc.getAttribute('data-color-clear');
            var txt = qs('[data-key="' + key + '"]', root);
            var sw = qs('[data-color-swatch="' + key + '"]', root);
            if (txt) txt.value = '';
            if (sw) sw.value = '#1C60F3';
            markDirty();
            return;
        }
    }

    function onInput(e) {
        var t = e.target;
        // 维护工具 › 页脚运行信息：即时保存单个开关（不经过表单脏检查，立即落库）
        if (t.id === 'jinyu-runinfo-toggle') {
            saveRuninfo(t.checked, qs('#jinyu-runinfo-tip', root));
            return;
        }
        // 多选下拉：搜索关键字 → 过滤列表（不影响保存值），并同步全选按钮文案
        if (t.classList && t.classList.contains('jinyu-ms-q')) {
            var sqBox = t.closest('[data-multi-box]');
            if (sqBox) {
                filterMultiList(sqBox, t.value);
                refreshMultiAllBtn(sqBox);
                return;
            }
        }
        if (t.id === 'jinyu-import-file') { handleImport(t); return; }
        if (t.id === 'jinyu-fb-msg') { fbCount(); return; }

        if (t.hasAttribute('data-color-swatch')) {
            var key = t.getAttribute('data-color-swatch');
            var txt = qs('[data-key="' + key + '"]', root);
            if (txt) txt.value = t.value;
        } else if (t.hasAttribute('data-range-num')) {
            var k = t.getAttribute('data-range-num');
            var rng = qs('input[type=range][data-key="' + k + '"]', root);
            if (rng) rng.value = t.value;
        } else if (t.type === 'range' && t.hasAttribute('data-key')) {
            // 反向同步：拖动滑杆时更新右侧数字输入框
            var num = qs('input[data-range-num="' + t.getAttribute('data-key') + '"]', root);
            if (num) num.value = t.value;
        }
        var dyn = t.closest ? t.closest('[data-dynlist]') : null;
        if (dyn) { syncDyn(dyn); markDirty(); return; }
        if (t.getAttribute('data-type') === 'switch') applyShowRef();
        markDirty();
    }

    function openMedia(key) {
        if (typeof window.wp === 'undefined' || !window.wp.media) {
            var field = qs('[data-key="' + key + '"]', root);
            if (field) field.focus();
            return;
        }
        var frame = window.wp.media({ title: t('selectImage', 'Select image'), multiple: false });
        frame.on('select', function () {
            var url = frame.state().get('selection').first().toJSON().url;
            var field = qs('[data-key="' + key + '"]', root);
            if (field) field.value = url;
            var preview = qs('[data-upload-preview="' + key + '"]', root);
            if (preview) preview.innerHTML = url ? '<img src="' + esc(url) + '" alt="">' : '';
            markDirty();
        });
        frame.open();
    }

    /* ---------- 搜索过滤 ---------- */
    function refSwitchOn(field) {
        var ref = qs('[data-key="' + field.getAttribute('data-show-ref') + '"]', root);
        return ref ? (ref.type === 'checkbox' ? ref.checked : !!ref.value) : true;
    }

    function applySearch(q) {
        var panels = qsa('.jinyu-panel', root);
        var navs = qsa('.jinyu-nav-item', root);
        var emptyTip = qs('.jinyu-search-empty', root);
        var navBox = qs('.jinyu-nav', root);
        if (navBox) navBox.classList.toggle('is-searching', !!q);
        if (!q) {
            navs.forEach(function (n) {
                n.hidden = false;
                n.classList.toggle('is-active', n.getAttribute('data-nav') === currentKey);
            });
            panels.forEach(function (p) {
                qsa('.jinyu-field', p).forEach(function (f) { f.hidden = false; });
                p.classList.toggle('is-active', p.getAttribute('data-panel') === currentKey);
            });
            applyShowRef(); // 恢复条件显隐（依赖开关关闭的字段继续隐藏）
            if (emptyTip) emptyTip.hidden = true;
            return;
        }
        var firstActive = null;
        var firstKey = null;
        var anyMatch = false;
        GROUPS.forEach(function (g) {
            var gMatch = (g.title || '').toLowerCase().indexOf(q) >= 0;
            var panel = qs('.jinyu-panel[data-panel="' + g.key + '"]', root);
            var nav = qs('.jinyu-nav-item[data-nav="' + g.key + '"]', root);
            if (!panel || !nav) return;
            var visible = 0;
            qsa('.jinyu-field', panel).forEach(function (f) {
                var label = qs('.jinyu-field-label', f);
                var txt = label ? label.textContent.toLowerCase() : '';
                var desc = qs('.jinyu-field-desc', f);
                var dTxt = desc ? desc.textContent.toLowerCase() : '';
                var show = gMatch || txt.indexOf(q) >= 0 || dTxt.indexOf(q) >= 0;
                // 条件显隐字段：依赖开关处于关闭状态时不参与搜索结果
                if (show && f.hasAttribute('data-show-ref') && !refSwitchOn(f)) show = false;
                f.hidden = !show;
                if (show) visible++;
            });
            // 标题命中也保留面板（覆盖无字段的自定义面板，如「维护工具」）
            var gVisible = visible > 0 || gMatch;
            panel.classList.toggle('is-active', gVisible);
            nav.hidden = !gVisible;
            if (gVisible) {
                anyMatch = true;
                if (!firstActive) firstActive = panel;
                if (!firstKey) firstKey = g.key;
            }
        });
        // 侧栏高亮与内容区对齐：仅第一个命中组高亮
        navs.forEach(function (n) { n.classList.remove('is-active'); });
        if (firstKey) {
            var fnav = qs('.jinyu-nav-item[data-nav="' + firstKey + '"]', root);
            if (fnav) fnav.classList.add('is-active');
        }
        if (emptyTip) emptyTip.hidden = anyMatch;
        layoutMultiAll();   // 搜索切换面板可见性后重算多选框折叠
    }

    /* ---------- 保存 / 重置 ---------- */
    function doFetch(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) {
            // 先取文本再解析：接口未注册 / PHP 报错 / 登录态失效时 WP 会返回 HTML 页面，
            // 直接 r.json() 只会抛出「Unexpected token <」这类无法定位的报错。
            return r.text().then(function (t) {
                var head = t.charAt(0);
                if ('<' === head || '{' !== head) {
                    throw new Error(tf('apiHttpError', [r.status], 'API request failed (HTTP ' + r.status + '). Make sure the Jinyu theme companion plugin is enabled'));
                }
                try {
                    return JSON.parse(t);
                } catch (e) {
                    throw new Error(tf('apiParseError', [r.status], 'Could not parse the API response (HTTP ' + r.status + ')'));
                }
            });
        });
    }

    function save(btn) {
        if (btn) btn.disabled = true;
        var data = collect();
        doFetch(S.ajax_url + '?action=jinyu_save_options&nonce=' + encodeURIComponent(S.nonce), data)
            .then(function (json) {
                if (json && json.success) {
                    SAVED = data;
                    snapshot = JSON.stringify(data);
                    buildGroupSnapshots();
                    refreshNavModified();
                    if (dirtybar) dirtybar.hidden = true;
                    var st = qs('#jinyu-saved-time');
                    if (st) st.textContent = tf('savedAt', [new Date().toLocaleTimeString()], 'Saved at ' + new Date().toLocaleTimeString());
                    toast((json.data && json.data.msg) ? json.data.msg : t('savedSuccessfully', 'Saved successfully'), true);
                } else {
                    toast((json && json.data && json.data.msg) ? json.data.msg : t('saveFailed', 'Save failed'), false);
                }
            })
            .catch(function (err) { toast(tf('networkErrorWith', [err.message], 'Network error: ' + err.message), false); })
            .then(function () { if (btn) btn.disabled = false; });
    }

    function resetAll(btn) {
        if (!window.confirm(t('resetAllConfirm', 'Restore all options to defaults? This action cannot be undone.'))) return;
        if (btn) btn.disabled = true;
        doFetch(S.ajax_url + '?action=jinyu_reset_options&nonce=' + encodeURIComponent(S.nonce), { reset: 1 })
            .then(function (json) {
                toast((json && json.data && json.data.msg) ? json.data.msg : t('done', 'Done'), !!(json && json.success));
                if (json && json.success) { window.setTimeout(function () { window.location.reload(); }, 600); }
            })
            .catch(function (err) { toast(tf('networkErrorWith', [err.message], 'Network error: ' + err.message), false); })
            .then(function () { if (btn) btn.disabled = false; });
    }

    function resetSection(key) {
        if (!window.confirm(tf('resetGroupConfirm', [key], 'Reset the "' + key + '" group to defaults? Customized settings in this group will be cleared.'))) return;
        doFetch(S.ajax_url + '?action=jinyu_reset_section&nonce=' + encodeURIComponent(S.nonce), { key: key })
            .then(function (json) {
                toast((json && json.data && json.data.msg) ? json.data.msg : t('done', 'Done'), !!(json && json.success));
                if (json && json.success) { window.setTimeout(function () { window.location.reload(); }, 600); }
            })
            .catch(function (err) { toast(tf('networkErrorWith', [err.message], 'Network error: ' + err.message), false); });
    }

    /* ---------- Toast ---------- */
    var toastTimer = null;
    function toast(msg, ok) {
        var t = qs('#jinyu-admin-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'jinyu-admin-toast';
            document.body.appendChild(t);
        }
        t.textContent = (ok ? '✓ ' : '✕ ') + msg;
        t.className = 'jinyu-admin-toast ' + (ok ? 'ok' : 'err');
        t.style.display = 'block';
        if (window.clearTimeout) window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { t.style.display = 'none'; }, 3200);
    }

    /* ---------- 配置导入 / 导出 ---------- */
    function doExport() {
        var tip = qs('#jinyu-export-tip');
        if (tip) { tip.textContent = t('exporting', 'Exporting…'); tip.className = 'jinyu-tools-tip'; }
        fetch(S.ajax_url + '?action=jinyu_export_options&nonce=' + encodeURIComponent(S.nonce), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_ajax_nonce=' + encodeURIComponent(S.nonce)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success && res.data && res.data.data) {
                    var blob = new Blob([JSON.stringify(res.data.data, null, 2)], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'jinyu-options-' + (S.version || '') + '.json';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    if (tip) { tip.textContent = t('exported', 'Exported'); tip.className = 'jinyu-tools-tip ok'; }
                } else {
                    if (tip) { tip.textContent = (res && res.data && res.data.msg) || t('exportFailed', 'Export failed'); tip.className = 'jinyu-tools-tip err'; }
                }
            })
            .catch(function () { if (tip) { tip.textContent = t('networkError', 'Network error'); tip.className = 'jinyu-tools-tip err'; } });
    }

    function handleImport(input) {
        var tip = qs('#jinyu-import-tip');
        var file = input.files && input.files[0];
        if (!file) return;
        if (tip) { tip.textContent = t('importing', 'Importing…'); tip.className = 'jinyu-tools-tip'; }
        var reader = new FileReader();
        reader.onload = function () {
            var parsed;
            try { parsed = JSON.parse(reader.result); } catch (e) {
                if (tip) { tip.textContent = t('jsonParseFailed', 'JSON parse failed'); tip.className = 'jinyu-tools-tip err'; }
                input.value = '';
                return;
            }
            fetch(S.ajax_url + '?action=jinyu_import_options&nonce=' + encodeURIComponent(S.nonce), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ data: parsed })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        if (tip) { tip.textContent = (res.data && res.data.msg) || t('importedSuccessfully', 'Imported successfully'); tip.className = 'jinyu-tools-tip ok'; }
                        window.setTimeout(function () { window.location.reload(); }, 600);
                    } else {
                        if (tip) { tip.textContent = (res && res.data && res.data.msg) || t('importFailed', 'Import failed'); tip.className = 'jinyu-tools-tip err'; }
                    }
                    input.value = '';
                })
                .catch(function () { if (tip) { tip.textContent = t('networkError', 'Network error'); tip.className = 'jinyu-tools-tip err'; } input.value = ''; });
        };
        reader.readAsText(file);
    }

    /* ---------- 工具（SMTP / 缓存）：事件委托，重建面板后依然有效 ---------- */
    // 页脚运行信息开关：直接写入主题设置（复用 jinyu_save_options 的合并 + 校验路径），落库即生效
    function saveRuninfo(val, tipEl) {
        if (tipEl) { tipEl.textContent = t('saving', 'Saving…'); tipEl.className = 'jinyu-tools-tip'; }
        doFetch(S.ajax_url + '?action=jinyu_save_options&nonce=' + encodeURIComponent(S.nonce), { footer_runinfo: val ? 1 : 0 })
            .then(function (json) {
                if (json && json.success) {
                    SAVED.footer_runinfo = val ? 1 : 0;
                    toast('已' + (val ? '开启' : '关闭') + '页脚运行信息', true);
                    var stateEl = qs('#jinyu-runinfo-state', root);
                    if (stateEl) {
                        stateEl.textContent = val ? t('enabled', 'Enabled') : t('disabled', 'Disabled');
                        stateEl.className = 'jinyu-tool-state' + (val ? ' on' : '');
                    }
                    if (tipEl) { tipEl.textContent = '已' + (val ? '开启' : '关闭'); tipEl.className = 'jinyu-tools-tip ok'; }
                } else {
                    toast((json && json.data && json.data.msg) ? json.data.msg : t('saveFailed', 'Save failed'), false);
                    if (tipEl) { tipEl.textContent = t('saveFailed', 'Save failed'); tipEl.className = 'jinyu-tools-tip err'; }
                }
            })
            .catch(function (err) {
                toast(tf('networkErrorWith', [err.message], 'Network error: ' + err.message), false);
                if (tipEl) { tipEl.textContent = t('networkError', 'Network error'); tipEl.className = 'jinyu-tools-tip err'; }
            });
    }
    /* WP 的 wp_send_json_success/error 既可返回字符串（data 直接是文案），
       也可返回数组（data.msg / data.message）。统一取文案，避免解析不到时
       退化成「成功 / 失败」这种毫无信息量的提示（真实失败原因会被吞掉）。 */
    function apiMsg(res, fallback) {
        var d = res && res.data;
        if (typeof d === 'string' && d !== '') return d;
        if (d && typeof d === 'object') return d.msg || d.message || fallback;
        return fallback;
    }

    /**
     * 维护工具请求。opts.moreRounds > 1 时，服务端返回 more=true 会自动续跑下一批，
     * 用于「重建缩略图」这类分批处理、单次请求装不下的任务。
     * opts.onProgress 用于在批次之间刷新进度（进度条、剩余时间提示）。
     */
    var thumbsRunning = false;

    function postTool(action, tipEl, opts) {
        if (!tipEl) return;
        opts = opts || {};
        var maxRounds = opts.moreRounds || 1;
        var round = 0;
        var body = '_ajax_nonce=' + encodeURIComponent(S.nonce);
        var onProgress = opts.onProgress || null;
        function run(isStop) {
            tipEl.textContent = isStop ? t('stopping', 'Stopping…') : t('processing', 'Processing…');
            tipEl.className = 'jinyu-tools-tip';
            if (isStop) body += '&stop=1';
            fetch(S.ajax_url + '?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    var d = (res && res.data) || {};
                    // 交给调用方自行收拾（例如自动续跑撞到并发闸门时要退避重试）：
                    // 返回 true 表示已接管，这里就不再改动进度区与提示文案
                    if (!res.success && typeof opts.onFail === 'function' && opts.onFail(d, res) === true) {
                        return;
                    }
                    // 批次任务：服务端说还有活，且本轮未到上限 → 直接续跑，用户不用反复点
                    if (res.success && d.more && round < maxRounds) {
                        round++;
                        if (onProgress) onProgress(d, false);
                        return run(false);
                    }
                    if (onProgress) onProgress(d, true);
                    tipEl.textContent = apiMsg(res, res.success ? t('success', 'Success') : t('failed', 'Failed'));
                    tipEl.className = 'jinyu-tools-tip ' + (res.success && !d.more ? 'ok' : 'err');
                })
                .catch(function (err) {
                    if (onProgress) onProgress({}, false, true);
                    var m = (err && err.message) ? tf('requestFailed', [err.message], 'Request failed: ' + err.message) : t('networkError', 'Network error');
                    if (window.console && console.error) console.error('[jinyu-tool]', action, err);
                    tipEl.textContent = m;
                    tipEl.className = 'jinyu-tools-tip err';
                });
        }
        run(false);
    }

    /* ---------- 重建封面缩略图：进度区 ---------- */

    /**
     * 刷新进度区。running=false 表示收尾（完成 / 停止 / 出错）。
     */
    function renderThumbsProgress(data, running) {
        var box = qs('#jinyu-thumbs-progress', root);
        if (!box) return;
        thumbsLastFrame = { data: data || {}, running: !!running };
        thumbsRunning = !!running;   // 供 resumeThumbsIfPending() 判断是否在跑，避免并发发起
        var pct   = Math.max(0, Math.min(100, Math.round((data && data.percent) || 0)));
        var done  = (data && (data.done || 0)) || 0;
        var total = (data && (data.total || 0)) || 0;
        var fill  = qs('#jinyu-thumbs-progress-fill', box);
        var label = qs('#jinyu-thumbs-progress-label', box);
        var pctEl = qs('#jinyu-thumbs-progress-pct', box);
        var detail = qs('#jinyu-thumbs-progress-detail', box);
        var bar   = qs('#jinyu-thumbs-progress-bar', box);
        var stopBtn = qs('#jinyu-thumbs-stop', box);

        box.hidden = false;
        box.classList.toggle('is-done', !running);
        box.setAttribute('aria-live', 'polite');
        if (fill) fill.style.width = pct + '%';
        if (bar) bar.setAttribute('aria-valuenow', String(pct));
        if (pctEl) pctEl.textContent = pct + '%';
        if (label) {
            label.textContent = running
                ? t('regenerating', 'Regenerating cover thumbnails…')
                : ((data && data.percent === 100) ? t('rebuildComplete', 'Rebuild complete') : t('stopped', 'Stopped'));
        }
        if (detail) {
            var bits = [];
            if (total > 0) bits.push(tf('processedXofY', [fmtNum(done), fmtNum(total)], 'Processed ' + fmtNum(done) + ' / ' + fmtNum(total) + ' images'));
            if (data && data.gen) bits.push(tf('generatedCount', [fmtNum(data.gen)], 'Generated ' + fmtNum(data.gen) + ' images'));
            if (data && data.skip) bits.push(tf('skippedCount', [fmtNum(data.skip)], 'Skipped ' + fmtNum(data.skip) + ' images'));
            if (data && data.fail) bits.push(tf('failedCount', [fmtNum(data.fail)], 'Failed ' + fmtNum(data.fail) + ' images'));
            detail.textContent = bits.join(' · ');
        }
        if (stopBtn) stopBtn.hidden = !running;
    }

    function fmtNum(n) {
        n = Number(n) || 0;
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /** 首次点击「开始重建」时把进度区清零打开，避免沿用上一次的残留数据。 */
    function openThumbsProgress() {
        renderThumbsProgress({ percent: 0, done: 0, total: 0, gen: 0, skip: 0, fail: 0 }, true);
    }

    /**
     * 页面重新打开 / 刷新后，先问服务端有没有上次没跑完的重建任务。
     * 任务没有常驻进程（关页面就随 FPM 一起死），所以中断只可能来自关页面、断网或 PHP 超时；
     * 服务端按状态里的时间戳把这类孤儿任务判为可接管，这里补发一次请求即可接着跑，用户不用重新点。
     */
    var thumbsResumeChecked = false;
    function resumeThumbsIfPending() {
        // 用户当前在别的分组、面板还没渲染出来时不打扰；同一次会话只查一次
        if (thumbsResumeChecked || !root || !qs('#jinyu-regenerate-thumbs', root) || thumbsRunning) return;
        thumbsResumeChecked = true;
        fetch(S.ajax_url + '?action=jinyu_regen_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_ajax_nonce=' + encodeURIComponent(S.nonce)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var d = (res && res.data) || {};
                if (!res.success || !d.running || Number(d.percent) >= 100) return;
                openThumbsProgress();
                renderThumbsProgress(d, true);
                var tip = qs('#jinyu-regenerate-thumbs-tip');
                var tries = 0;
                (function go() {
                    postTool('jinyu_regenerate_thumbs', tip, {
                        moreRounds: 60,
                        onProgress: function (dd, done) { renderThumbsProgress(dd, !done); },
                        // 撞到并发闸门（上一次请求还没收尾 / 另一个标签页正在跑）时退避重试：
                        // 任务并没有停，不能把进度区翻成「已停止」让用户以为白跑了
                        onFail: function (dd) {
                            if (!dd || dd.code !== 'busy' || tries >= 3) return false;
                            tries++;
                            if (tip) {
                                tip.className = 'jinyu-tools-tip';
                                tip.textContent = t('thumbBusyResume', 'A task is already running; it will resume automatically…');
                            }
                            window.setTimeout(go, 4000);
                            return true;
                        }
                    });
                })();
            })
            .catch(function () { /* 探不到就当没有待办任务，用户手动点开始即可 */ });
    }

    /* ---------- 关于：检查主题更新 ---------- */
    function closeUpdatePop() {
        var detail = qs('.jinyu-update-detail--head');
        if (detail) detail.innerHTML = '';
        detachUpdatePopClose();
    }
    var updatePopCloseHandler = null;
    function detachUpdatePopClose() {
        if (updatePopCloseHandler) {
            document.removeEventListener('click', updatePopCloseHandler, true);
            document.removeEventListener('keydown', updatePopCloseHandler._esc, true);
            updatePopCloseHandler = null;
        }
    }
    function attachUpdatePopClose() {
        detachUpdatePopClose();
        var onDoc = function (e) {
            if (e.target.closest && e.target.closest('[data-close-update]')) { closeUpdatePop(); return; }
            var box = qs('.jinyu-update-box--head');
            if (box && box.contains(e.target)) return; // 点在弹卡或「检查更新」按钮内不关闭
            closeUpdatePop();
        };
        var onEsc = function (e) { if (e.key === 'Escape') closeUpdatePop(); };
        updatePopCloseHandler = onDoc;
        updatePopCloseHandler._esc = onEsc;
        document.addEventListener('click', onDoc, true);
        document.addEventListener('keydown', onEsc, true);
    }
    function checkUpdate(btn) {
        btn.disabled = true;
        var box = btn.closest('.jinyu-update-box');
        var status = box ? qs('[data-update-status]', box) : null;
        var detail = box ? qs('[data-update-detail]', box) : null;
        if (status) { status.textContent = t('checking', 'Checking…'); status.className = 'jinyu-update-status'; }
        if (detail) detail.innerHTML = '';
        doFetch(S.ajax_url + '?action=jinyu_check_update&nonce=' + encodeURIComponent(S.nonce), {})
            .then(function (json) {
                if (!json || !json.success) {
                    var emsg = (json && json.data && json.data.msg) || t('checkFailed', 'Check failed');
                    if (status) { status.textContent = emsg; status.className = 'jinyu-update-status err'; }
                    else toast(emsg, false);   // 顶栏按钮没有状态区容器，用 toast 反馈
                    return;
                }
                var d = json.data || {};
                if (status) {
                    if (d.has_update) {
                        status.textContent = tf('newVersion', [d.latest], 'New version v' + d.latest);
                        status.className = 'jinyu-update-status ok';
                    } else {
                        status.textContent = tf('upToDate', [d.current], 'Already up to date: v' + d.current);
                        status.className = 'jinyu-update-status';
                    }
                }
                if (!box) {
                    // 顶栏「检查更新」：没有 .jinyu-update-box 容器，结果弹卡无处渲染，
                    // 必须用全局 toast 给出结果——否则点击后毫无反馈，看起来像按钮坏了
                    toast(d.has_update ? (tf('newVersion', [d.latest], 'New version v' + d.latest) + '. ' + t('downloadFromAbout', 'Download it from the About panel')) : tf('upToDate', [d.current], 'Already up to date: v' + d.current), true);
                }
                if (detail) {
                    // 仅在确有新版本时才浮出更新卡片（更新日志 + 操作按钮）；已是最新时不渲染任何详情，不撑爆顶栏
                    var html = '';
                    if (d.has_update) {
                        html += '<div class="jinyu-update-pop">' +
                            '<div class="jinyu-update-pop-head">' +
                            '<i class="dashicons dashicons-download" aria-hidden="true"></i>' +
                            esc(tf('newVersion', [d.latest], 'New version v' + d.latest)) +
                            '<span>' + esc(tf('currentVersion', [d.current], 'Current v' + d.current)) + '</span>' +
                            '<button type="button" class="jinyu-update-pop-close" data-close-update aria-label="' + esc(t('close', 'Close')) + '">×</button>' +
                            '</div>' +
                            (d.changelog ? '<div class="jinyu-update-cl">' + esc(d.changelog) + '</div>' : '<p class="jinyu-update-nocl">' + esc(t('noChangelog', 'No changelog yet.')) + '</p>') +
                            '<div class="jinyu-update-pop-actions">' +
                            (d.download_url ? '<a class="jinyu-btn jinyu-btn-sm jinyu-btn-primary" href="' + esc(d.download_url) + '" target="_blank" rel="noopener">' + esc(t('downloadPackage', 'Download update package')) + '</a>' : '') +
                            (d.detail_url ? '<a class="jinyu-btn jinyu-btn-sm" href="' + esc(d.detail_url) + '" target="_blank" rel="noopener">' + esc(t('viewDetails', 'View details')) + '</a>' : '') +
                            '</div></div>';
                    }
                    detail.innerHTML = html;
                    if (d.has_update) attachUpdatePopClose(); else detachUpdatePopClose();
                }
            })
            .catch(function (err) { if (status) { status.textContent = tf('networkErrorWith', [err.message], 'Network error: ' + err.message); status.className = 'jinyu-update-status err'; } else toast(tf('networkErrorWith', [err.message], 'Network error: ' + err.message), false); })
            .then(function () { btn.disabled = false; });
    }

    /* ---------- 顶栏 / 全局按钮 ---------- */
    function wireTopbar() {
        var saveBtn = qs('#jinyu-save');
        var resetBtn = qs('#jinyu-reset');
        var saveFloat = qs('#jinyu-save-float');
        var discard = qs('#jinyu-discard');
        var search = qs('#jinyu-search');
        var checkUpdateBtn = qs('#jinyu-check-update');
        dirtybar = qs('#jinyu-dirtybar');

        // 快捷键提示随平台自适应：Mac 显示 ⌘K，其余显示 Ctrl + K（与 (ctrlKey||metaKey) 实际监听一致）
        var kbdHint = qs('.jinyu-search-kbd');
        if (kbdHint && /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent)) {
            kbdHint.textContent = '⌘K';
        }

        if (checkUpdateBtn) checkUpdateBtn.addEventListener('click', function () { checkUpdate(checkUpdateBtn); });
        if (saveBtn) saveBtn.addEventListener('click', function () { save(saveBtn); });
        if (saveFloat) saveFloat.addEventListener('click', function () { save(saveFloat); });
        if (resetBtn) resetBtn.addEventListener('click', function () { resetAll(resetBtn); });
        if (discard) discard.addEventListener('click', function () {
            if (root) root.innerHTML = '';
            build();
            if (dirtybar) dirtybar.hidden = true;
        });
        if (search) {
            var searchTimer = null;
            var clearBtn = qs('#jinyu-search-clear');
            function runSearch() {
                var val = search.value.trim().toLowerCase();
                applySearch(val);
                if (clearBtn) clearBtn.hidden = !val;
            }
            search.addEventListener('input', function (e) {
                if (e.isComposing) return; // 中文输入法组合中不过滤，避免拼音残串闪结果
                clearTimeout(searchTimer);
                searchTimer = setTimeout(runSearch, 120);
            });
            search.addEventListener('compositionend', runSearch);
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    search.value = '';
                    applySearch('');
                    clearBtn.hidden = true;
                    search.focus();
                });
            }
        }

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                save(saveBtn);
            }
            if (e.key === 'Escape') { closeAllSelectPops(); closeAllMsPop(); }
        });
        // 点击下拉区域外 → 收起全部下拉（自定义 select + 多选下拉）
        document.addEventListener('click', function (e) {
            if (root && !e.target.closest('.jinyu-select')) closeAllSelectPops();
            if (root && !e.target.closest('.jinyu-ms')) closeAllMsPop();
        });
    }

    /* ---------- 入口 ---------- */
    function init() {
        root = document.getElementById('jinyu-setting-app');
        if (!root) return;
        build();
        resumeThumbsIfPending();   // 上次没跑完的重建任务在这里自动接上
        wireTopbar();

        // 顶栏报警图标（.jinyu-alert）是 <a href="…#tools">：浏览器只改 hash、不触发任何
        // JS 面板切换，而 activate() 写回 hash 用的是 replaceState（不产生 hashchange 事件），
        // 二者不会成环。必须自己监听 hashchange，页内锚点跳转才会真正切到「维护工具」面板，
        // 否则图标「点了没反应」。
        window.addEventListener('hashchange', function () {
            var k = (location.hash || '').replace(/^#/, '');
            var valid = {};
            GROUPS.forEach(function (g) { valid[g.key] = true; });
            if (k && valid[k] && k !== currentKey) activate(k, true);
        });
        // 窗口缩放时重算多选框折叠（触发框保持一行）与已展开面板的上下方向
        window.addEventListener('resize', function () {
            if (!root) return;
            layoutMultiAll();
            qsa('.jinyu-ms.is-open', root).forEach(placeMsPanel);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
