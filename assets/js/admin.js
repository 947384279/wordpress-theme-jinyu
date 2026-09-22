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

    // 记忆当前选中的设置分组（刷新 / 重开页面后保持），优先 localStorage，其次 URL hash
    function getInitialKey() {
        if (!GROUPS.length) return '';
        var valid = {};
        GROUPS.forEach(function (g) { valid[g.key] = true; });
        try {
            var stored = localStorage.getItem('jinyu_set_tab_' + location.pathname);
            if (stored && valid[stored]) return stored;
        } catch (e) {}
        var h = (location.hash || '').replace(/^#/, '');
        if (h && valid[h]) return h;
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
                    '<input type="text" class="jinyu-input jinyu-dyn-sub" data-dyn-sub="upload" data-dyn-key="' + esc(m.id) + '" value="' + esc(mv || '') + '" placeholder="点击选择或输入 URL">' +
                    '<button type="button" class="button jinyu-dyn-upload" data-dyn-upload>选择</button></div>';
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
            '<span class="jinyu-dyn-grip" title="拖拽排序">⠿</span>' +
            '<span class="jinyu-dyn-title">' + esc((it && it.title) ? it.title : ('项目 ' + (i + 1))) + '</span>' +
            '<span class="jinyu-dyn-actions">' +
            '<button type="button" class="jinyu-dyn-btn" data-dyn-up title="上移">↑</button>' +
            '<button type="button" class="jinyu-dyn-btn" data-dyn-down title="下移">↓</button>' +
            '<button type="button" class="jinyu-dyn-btn" data-dyn-copy title="复制">⧉</button>' +
            '<button type="button" class="jinyu-dyn-btn jinyu-dyn-del" data-dyn-del title="删除">×</button>' +
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
            '<button type="button" class="button jinyu-dyn-add" data-dyn-add="' + esc(id) + '">+ 添加一项</button>' +
            (f.max ? ' <span class="jinyu-field-desc">' + esc('最多 ' + f.max + ' 项') + '</span>' : '') +
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

    function resyncAllDyn() {
        qsa('[data-dynlist]', root).forEach(syncDyn);
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
        if (titleEl) titleEl.textContent = titleEl.textContent + ' 副本';
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
        var frame = window.wp.media({ title: '选择图片', multiple: false });
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
                '<button type="button" class="jinyu-ms-chip-rm" data-ms-rm="' + esc(v) + '" aria-label="移除">×</button></span>';
        }).join('');
        // 自适应折叠：先渲染全部 chip + 「+N」徽标，实际显示个数由 layoutMultiChips 按触发框宽度测量决定
        // 注意：显隐必须用 style.display —— .jinyu-ms-chip 的 display:inline-flex 会覆盖 hidden 属性
        var moreChip = '<span class="jinyu-ms-chip jinyu-ms-chip-more" style="display:none"></span>';
        // 初始按钮文案：可见项（初始无过滤=全部）是否全部已选
        var allKeys = Object.keys(items || {});
        var allSel = allKeys.length > 0 && allKeys.every(function (k) { return order[k] !== undefined; });
        var allLabel = allSel ? '全不选' : '全选';
        var list = '';
        Object.keys(items || {}).forEach(function (k) {
            var on = order[k] !== undefined;
            list += '<div class="jinyu-ms-item' + (on ? ' is-on' : '') + '" data-val="' + esc(k) + '" role="option" aria-selected="' + (on ? 'true' : 'false') + '">' +
                '<span class="jinyu-ms-box" aria-hidden="true"><svg viewBox="0 0 24 24"><polyline points="4 12 10 18 20 6"/></svg></span>' +
                '<span class="jinyu-ms-nm">' + esc(items[k]) + '</span>' +
                '<span class="jinyu-ms-ord" aria-hidden="true">' + (on ? '#' + order[k] : '') + '</span></div>';
        });
        if (!list) list = '<p class="jinyu-ms-empty">' + esc(f.empty || '暂无可选') + '</p>';
        var initCount = sel.length;
        return '<div class="jinyu-field jinyu-field--multi" data-field="' + esc(id) + '">' +
            '<div class="jinyu-field-labelrow">' +
            '<label class="jinyu-field-label">' + esc(f.title) + '</label>' +
            '<span class="jinyu-multi-count" data-multi-count="' + esc(id) + '"' + (initCount ? '' : ' hidden') + '>已选 ' + initCount + ' 项</span>' +
            '</div>' +
            '<div class="jinyu-ms" data-multi-box="' + esc(id) + '">' +
            '<div class="jinyu-ms-trigger" role="button" tabindex="0" aria-haspopup="listbox">' +
            '<span class="jinyu-ms-placeholder' + (initCount ? ' is-hidden' : '') + '">' + esc(f.placeholder || '请选择…') + '</span>' +
            '<div class="jinyu-ms-chips">' + chips + moreChip + '</div>' +
            '<span class="jinyu-ms-caret" aria-hidden="true"></span>' +
            '</div>' +
            '<div class="jinyu-ms-panel">' +
            '<div class="jinyu-ms-search"><input type="text" class="jinyu-ms-q" placeholder="搜索…" autocomplete="off">' +
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
                    '<button type="button" class="jinyu-ms-chip-rm" data-ms-rm="' + esc(v) + '" aria-label="移除">×</button>';
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
        if (cnt) { cnt.textContent = '已选 ' + sel.length + ' 项'; cnt.hidden = !sel.length; }
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
        allBtn.textContent = allOn ? '全不选' : '全选';
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

    /* ---------- 字段渲染 ---------- */
    function fieldHtml(f) {
        var id = f.id || '';
        var hasVal = (SAVED[id] !== undefined && SAVED[id] !== null);
        var type = f.type || 'string';
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

        // 对象存储操作卡片（配置页底部 storage_ops 字段，不进入保存数据）
        if (type === 'storage_ops') {
            return storageHtml();
        }

        // 检查更新（按钮 + 状态区，不进入保存数据）
        if (type === 'update_check') {
            return '<div class="jinyu-field jinyu-field--update" data-field="' + esc(id) + '">' +
                '<label class="jinyu-field-label">' + esc(f.title) + '</label>' +
                '<div class="jinyu-field-body">' +
                '<div class="jinyu-update-box">' +
                '<button type="button" class="jinyu-btn jinyu-btn-primary" data-check-update>' + esc('检查更新') + '</button>' +
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
                    '<button type="button" class="button jinyu-color-clear" data-color-clear="' + esc(id) + '">清空</button>' +
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
                    '<input type="text" class="jinyu-input jinyu-upload-url" data-key="' + esc(id) + '" data-type="upload" value="' + esc(v || '') + '" placeholder="点击右侧按钮选择，或手动输入 URL">' +
                    '<button type="button" class="button jinyu-upload-btn" data-upload="' + esc(id) + '">选择</button>' +
                    '<button type="button" class="button jinyu-upload-remove" data-upload-remove="' + esc(id) + '"' + (v ? '' : ' hidden') + '>移除</button>' +
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
                    '<input type="password" class="jinyu-input" data-key="' + esc(id) + '" data-type="password" value="" autocomplete="new-password" placeholder="' + esc('已设置，留空则不修改') + '">' +
                    '<button type="button" class="jinyu-pw-toggle" data-pw-toggle="' + esc(id) + '" title="' + esc('显示/隐藏') + '"><i class="dashicons dashicons-visibility"></i></button>' +
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
            '<button type="button" class="jinyu-panel-reset" data-reset-group="' + esc(g.key) + '" title="' + esc('仅重置本组为默认值') + '">' + esc('重置本组') + '</button>';
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

    function toolsHtml() {
        var runOn = !!SAVED.footer_runinfo;
        var runSwitch = '<span class="jinyu-tool-state' + (runOn ? ' on' : '') + '" id="jinyu-runinfo-state">' + (runOn ? '已开启' : '已关闭') + '</span>' +
            '<label class="jinyu-switch jinyu-switch--sm">' +
            '<input type="checkbox" id="jinyu-runinfo-toggle"' + (runOn ? ' checked' : '') + '>' +
            '<span class="jinyu-switch-track"></span>' +
            '</label>' +
            '<span id="jinyu-runinfo-tip" class="jinyu-tools-tip"></span>';
        return '<div class="jinyu-tools-list">' +
            toolRow('admin-settings', 'SMTP 发信', '配置发信通道（主机 / 端口 / 账号 / 授权码 / 加密 / 发件人）。测试邮件优先使用表单当前值，未保存也可直接测试。', toolAction('jinyu-test-smtp', '发送测试邮件', 'jinyu-test-smtp-tip') + toolAction('jinyu-config-smtp', '展开配置', 'jinyu-config-smtp-tip')) +
            toolRow('performance', '清理主题缓存', '立即清空全部主题缓存（页面缓存与静态化资源），改版后建议执行一次。', toolAction('jinyu-clear-cache', '清理缓存', 'jinyu-clear-cache-tip')) +
            toolRow('database', '数据库优化', '清理文章修订版、自动草稿、垃圾/回收站评论、孤立 meta 与过期 transient，并对数据表执行 OPTIMIZE。仅删冗余，不动正常内容。', toolAction('jinyu-db-optimize', '一键优化', 'jinyu-db-optimize-tip')) +
            toolRow('download', '导出配置', '将当前所有主题设置导出为 JSON 文件，便于备份与多站迁移。', toolAction('jinyu-export', '导出 JSON', 'jinyu-export-tip')) +
            toolRow('upload', '导入配置', '从 JSON 文件恢复主题设置，将覆盖当前全部配置，请先导出备份。', toolAction('jinyu-import', '选择文件并导入', 'jinyu-import-tip') + '<input type="file" id="jinyu-import-file" accept="application/json,.json" hidden>') +
            toolRow('chart-bar', '页脚运行信息', '在前台页脚输出实时运行信息（查询数 / 内存 / 渲染耗时）。开启后建议清理一次缓存使其生效。', runSwitch) +
            '<div id="jinyu-smtp-card" class="jinyu-smtp-card" hidden>' + smtpCardHtml() + '</div>' +
            '</div>';
    }

    function smtpCardHtml() {
        var g = null;
        for (var i = 0; i < GROUPS.length; i++) { if (GROUPS[i].key === 'email') { g = GROUPS[i]; break; } }
        if (!g || !g.fields) return '';
        var grid = g.fields.map(function (f) {
            return '<div class="jinyu-smtp-cell">' + fieldHtml(f) + '</div>';
        }).join('');
        return '<div class="jinyu-smtp-card-head">' +
            '<i class="dashicons dashicons-email" aria-hidden="true"></i>' +
            '<span class="jinyu-smtp-card-title">发信通道</span>' +
            '<span class="jinyu-smtp-card-note">填完记得点右上角「保存设置」</span>' +
            '</div>' +
            '<div class="jinyu-smtp-grid">' + grid + '</div>';
    }

    /* ---------- 对象存储面板（推送 / 拉回 / 加速域名） ---------- */
    // 不使用 WP-Cron：由用户打开后台面板触发拉取，服务端以 ETag 判定内容是否变化（304 直接沿用本地缓存）
    function storageHtml() {
        return '<div class="jinyu-tools-list">' +
            toolRow('admin-network', '测试连接', '使用本页上方已填写并保存的配置，向存储上传并回读一个临时文件，验证服务商、桶、密钥是否正确。', toolAction('jinyu-storage-test', '测试连接', 'jinyu-storage-test-tip')) +
            toolRow('upload', '一键推送到存储', '将本地 wp-content/uploads 全部文件上传到对象存储（分批进行，可在下方查看进度）。', toolAction('jinyu-storage-push', '开始推送', 'jinyu-storage-push-tip')) +
            toolRow('download', '一键从存储拉回', '将对象存储中「远端路径前缀」下的全部文件下载回本地，用于迁移回源或备份。', toolAction('jinyu-storage-pull', '开始拉回', 'jinyu-storage-pull-tip')) +
            toolRow('admin-links', '应用加速域名', '开启加速重写：附件链接切换到上方域名并刷新全站缓存。请先完成「一键推送」，否则图片会 404。', toolAction('jinyu-storage-apply-domain', '应用域名', 'jinyu-storage-apply-domain-tip')) +
            toolRow('undo', '停用加速域名', '关闭加速重写，附件链接立即回退本地 uploads 并刷新缓存。图片异常时用它快速止血。', toolAction('jinyu-storage-unapply-domain', '停用加速', 'jinyu-storage-unapply-domain-tip')) +
            '</div>' +
            '<div class="jinyu-storage-progress" id="jinyu-storage-progress" hidden>' +
            '<div class="jinyu-storage-bar"><span id="jinyu-storage-bar-fill"></span></div>' +
            '<p class="jinyu-storage-progress-text" id="jinyu-storage-progress-text"></p>' +
            '</div>' +
            '<p class="jinyu-storage-note">提示：推送 / 拉回为服务端批处理，进度存于数据库；即使刷新本页也不会中断已在运行的任务。</p>';
    }

    function sidebarHtml() {
        var html = '<nav class="jinyu-nav" aria-label="设置分组">';
        GROUPS.forEach(function (g) {
            if (g.hidden) return;
            var ic = ICONS[g.key] || 'dashicons-admin-generic';
            html += '<button type="button" class="jinyu-nav-item' + (g.key === currentKey ? ' is-active' : '') + '" data-nav="' + esc(g.key) + '">' +
                '<span class="dashicons ' + ic + '"></span>' +
                '<span class="jinyu-nav-text">' + esc(g.title) + '</span>' +
                '</button>';
        });
        html += '</nav>';
        return html;
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
    function markDirty() {
        if (!dirtybar) return;
        var dirty = JSON.stringify(collect()) !== snapshot;
        dirtybar.hidden = !dirty;
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
    function build() {
        root.innerHTML = '<div class="jinyu-layout">' + sidebarHtml() +
            '<div class="jinyu-main"><div class="jinyu-panels">' +
            GROUPS.map(groupHtml).join('') +
            '<div class="jinyu-search-empty" hidden>没有匹配的设置项，换个关键词试试</div>' +
            '</div></div></div>';

        // 委托事件
        root.addEventListener('click', onRootClick);
        root.addEventListener('input', onInput);
        root.addEventListener('change', onInput);
        root.addEventListener('dragstart', onDynDragStart);
        root.addEventListener('dragover', onDynDragOver);
        root.addEventListener('dragend', onDynDragEnd);

        snapshot = JSON.stringify(collect());
        applyShowRef();
        layoutMultiAll();   // 初始可见面板的多选框按宽度自适应折叠

    }

    function activate(key) {
        currentKey = key;
        try { localStorage.setItem('jinyu_set_tab_' + location.pathname, key); } catch (e) {}
        if (history.replaceState) { try { history.replaceState(null, '', '#' + key); } catch (e) {} }
        qsa('.jinyu-nav-item', root).forEach(function (n) {
            n.classList.toggle('is-active', n.getAttribute('data-nav') === key);
        });
        qsa('.jinyu-panel', root).forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
        layoutMultiAll();   // 新面板可见后重算多选框折叠，避免 width=0 误裁切
        var main = qs('.jinyu-main', root);
        if (main) main.scrollTop = 0;
        // 页面级滚动（window）才是实际滚动容器，切换分组须归零，
        // 否则新面板从上个分组的滚动位置开始（看不到面板头）
        try { window.scrollTo(0, 0); } catch (e) {}

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
        var nav = e.target.closest('.jinyu-nav-item');
        if (nav) { activate(nav.getAttribute('data-nav')); return; }

        // 维护工具：展开 / 收起 SMTP 配置卡片
        var cfgBtn = e.target.closest('#jinyu-config-smtp');
        if (cfgBtn) {
            var card = qs('#jinyu-smtp-card', root);
            if (card) {
                card.hidden = !card.hidden;
                cfgBtn.textContent = card.hidden ? '展开配置' : '收起配置';
            }
            return;
        }

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

        if (e.target.closest('#jinyu-test-smtp')) { postTool('jinyu_test_smtp', qs('#jinyu-test-smtp-tip')); return; }
        if (e.target.closest('#jinyu-clear-cache')) { postTool('jinyu_clear_cache', qs('#jinyu-clear-cache-tip')); return; }
        if (e.target.closest('#jinyu-db-optimize')) { postTool('jinyu_db_optimize', qs('#jinyu-db-optimize-tip')); return; }

        // 对象存储操作面板（事件委托，面板重建后仍有效）
        if (e.target.closest('#jinyu-storage-test')) { postStorage('jinyu_storage_test', qs('#jinyu-storage-test-tip')); return; }
        if (e.target.closest('#jinyu-storage-apply-domain')) {
            var domEl = qs('[data-key="storage_domain"]', root);
            postStorage('jinyu_storage_apply_domain', qs('#jinyu-storage-apply-domain-tip'), domEl ? { storage_domain: domEl.value } : {});
            return;
        }
        if (e.target.closest('#jinyu-storage-unapply-domain')) { postStorage('jinyu_storage_unapply_domain', qs('#jinyu-storage-unapply-domain-tip')); return; }
        if (e.target.closest('#jinyu-storage-push')) { runStorageJob('jinyu_storage_push', qs('#jinyu-storage-push'), qs('#jinyu-storage-push-tip')); return; }
        if (e.target.closest('#jinyu-storage-pull')) { runStorageJob('jinyu_storage_pull', qs('#jinyu-storage-pull'), qs('#jinyu-storage-pull-tip')); return; }


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
        var frame = window.wp.media({ title: '选择图片', multiple: false });
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
        }).then(function (r) { return r.json(); });
    }

    function save(btn) {
        if (btn) btn.disabled = true;
        var data = collect();
        doFetch(S.ajax_url + '?action=jinyu_save_options&nonce=' + encodeURIComponent(S.nonce), data)
            .then(function (json) {
                if (json && json.success) {
                    SAVED = data;
                    snapshot = JSON.stringify(data);
                    if (dirtybar) dirtybar.hidden = true;
                    var st = qs('#jinyu-saved-time');
                    if (st) st.textContent = '已保存 ' + new Date().toLocaleTimeString();
                    toast((json.data && json.data.msg) ? json.data.msg : '保存成功', true);
                } else {
                    toast((json && json.data && json.data.msg) ? json.data.msg : '保存失败', false);
                }
            })
            .catch(function (err) { toast('网络错误：' + err.message, false); })
            .then(function () { if (btn) btn.disabled = false; });
    }

    function resetAll(btn) {
        if (!window.confirm('确认恢复所有选项为默认值？此操作不可撤销。')) return;
        if (btn) btn.disabled = true;
        doFetch(S.ajax_url + '?action=jinyu_reset_options&nonce=' + encodeURIComponent(S.nonce), { reset: 1 })
            .then(function (json) {
                toast((json && json.data && json.data.msg) ? json.data.msg : '完成', !!(json && json.success));
                if (json && json.success) { window.setTimeout(function () { window.location.reload(); }, 600); }
            })
            .catch(function (err) { toast('网络错误：' + err.message, false); })
            .then(function () { if (btn) btn.disabled = false; });
    }

    function resetSection(key) {
        if (!window.confirm('确认仅重置「' + key + '」分组为默认值？该分组下的自定义设置将被清除。')) return;
        doFetch(S.ajax_url + '?action=jinyu_reset_section&nonce=' + encodeURIComponent(S.nonce), { key: key })
            .then(function (json) {
                toast((json && json.data && json.data.msg) ? json.data.msg : '完成', !!(json && json.success));
                if (json && json.success) { window.setTimeout(function () { window.location.reload(); }, 600); }
            })
            .catch(function (err) { toast('网络错误：' + err.message, false); });
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
        if (tip) { tip.textContent = '导出中…'; tip.className = 'jinyu-tools-tip'; }
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
                    if (tip) { tip.textContent = '已导出'; tip.className = 'jinyu-tools-tip ok'; }
                } else {
                    if (tip) { tip.textContent = (res && res.data && res.data.msg) || '导出失败'; tip.className = 'jinyu-tools-tip err'; }
                }
            })
            .catch(function () { if (tip) { tip.textContent = '网络错误'; tip.className = 'jinyu-tools-tip err'; } });
    }

    function handleImport(input) {
        var tip = qs('#jinyu-import-tip');
        var file = input.files && input.files[0];
        if (!file) return;
        if (tip) { tip.textContent = '导入中…'; tip.className = 'jinyu-tools-tip'; }
        var reader = new FileReader();
        reader.onload = function () {
            var parsed;
            try { parsed = JSON.parse(reader.result); } catch (e) {
                if (tip) { tip.textContent = 'JSON 解析失败'; tip.className = 'jinyu-tools-tip err'; }
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
                        if (tip) { tip.textContent = (res.data && res.data.msg) || '导入成功'; tip.className = 'jinyu-tools-tip ok'; }
                        window.setTimeout(function () { window.location.reload(); }, 600);
                    } else {
                        if (tip) { tip.textContent = (res && res.data && res.data.msg) || '导入失败'; tip.className = 'jinyu-tools-tip err'; }
                    }
                    input.value = '';
                })
                .catch(function () { if (tip) { tip.textContent = '网络错误'; tip.className = 'jinyu-tools-tip err'; } input.value = ''; });
        };
        reader.readAsText(file);
    }

    /* ---------- 工具（SMTP / 缓存）：事件委托，重建面板后依然有效 ---------- */
    // 页脚运行信息开关：直接写入主题设置（复用 jinyu_save_options 的合并 + 校验路径），落库即生效
    function saveRuninfo(val, tipEl) {
        if (tipEl) { tipEl.textContent = '保存中…'; tipEl.className = 'jinyu-tools-tip'; }
        doFetch(S.ajax_url + '?action=jinyu_save_options&nonce=' + encodeURIComponent(S.nonce), { footer_runinfo: val ? 1 : 0 })
            .then(function (json) {
                if (json && json.success) {
                    SAVED.footer_runinfo = val ? 1 : 0;
                    toast('已' + (val ? '开启' : '关闭') + '页脚运行信息', true);
                    var stateEl = qs('#jinyu-runinfo-state', root);
                    if (stateEl) {
                        stateEl.textContent = val ? '已开启' : '已关闭';
                        stateEl.className = 'jinyu-tool-state' + (val ? ' on' : '');
                    }
                    if (tipEl) { tipEl.textContent = '已' + (val ? '开启' : '关闭'); tipEl.className = 'jinyu-tools-tip ok'; }
                } else {
                    toast((json && json.data && json.data.msg) ? json.data.msg : '保存失败', false);
                    if (tipEl) { tipEl.textContent = '保存失败'; tipEl.className = 'jinyu-tools-tip err'; }
                }
            })
            .catch(function (err) {
                toast('网络错误：' + err.message, false);
                if (tipEl) { tipEl.textContent = '网络错误'; tipEl.className = 'jinyu-tools-tip err'; }
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

    function postTool(action, tipEl) {
        if (!tipEl) return;
        tipEl.textContent = '处理中…';
        tipEl.className = 'jinyu-tools-tip';
        var body = '_ajax_nonce=' + encodeURIComponent(S.nonce);
        // SMTP 测试：附带表单当前（可能未保存）的 SMTP 配置，便于不保存直接测
        if (action === 'jinyu_test_smtp') {
            ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pwd', 'smtp_from'].forEach(function (k) {
                var el = qs('[data-key="' + k + '"]', root);
                if (el && el.value !== '') {
                    body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(el.value);
                }
            });
        }
        fetch(S.ajax_url + '?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                tipEl.textContent = apiMsg(res, res.success ? '成功' : '失败');
                tipEl.className = 'jinyu-tools-tip ' + (res.success ? 'ok' : 'err');
            })
            .catch(function (err) {
                var m = (err && err.message) ? ('请求失败：' + err.message) : '网络错误';
                if (window.console && console.error) console.error('[jinyu-tool]', action, err);
                tipEl.textContent = m;
                tipEl.className = 'jinyu-tools-tip err';
            });
    }

    /* ---------- 对象存储：携带当前表单配置的工具请求 ---------- */
    // 发送本页上方「对象存储」配置（storage_* 字段），便于未保存直接测试 / 推送。
    // 密钥类字段（access_key / secret）留空时不发送，服务端自动回退已保存值。
    // quiet=true 时不弹全局 toast（由调用方 runStorageJob 在结束时统一报结果）
    function postStorage(action, tipEl, extra, quiet) {
        var fields = [
            'storage_provider', 'storage_bucket', 'storage_region', 'storage_endpoint',
            'storage_access_key', 'storage_secret', 'storage_prefix', 'storage_domain'
        ];
        var body = '_ajax_nonce=' + encodeURIComponent(S.nonce);
        fields.forEach(function (k) {
            var el = qs('[data-key="' + k + '"]', root);
            if (el && el.value !== '') {
                body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(el.value);
            }
        });
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extra[k]);
            });
        }
        return fetch(S.ajax_url + '?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var msg = apiMsg(res, res.success ? '操作已完成' : '操作失败');
                if (tipEl) {
                    tipEl.textContent = msg;
                    tipEl.className = 'jinyu-tools-tip ' + (res.success ? 'ok' : 'err');
                }
                if (!quiet) toast(msg, res.success);
                return res;
            })
            .catch(function (err) {
                // 不要吞掉真实错误：JS 异常/非 JSON 响应都从这里暴露，便于定位
                var m = (err && err.message) ? ('请求失败：' + err.message) : '网络错误，请重试';
                if (window.console && console.error) console.error('[jinyu-storage]', action, err);
                if (tipEl) { tipEl.textContent = m; tipEl.className = 'jinyu-tools-tip err'; }
                if (!quiet) toast(m, false);
                return null;
            });
    }

    /* 推送 / 拉回：反复请求直至任务完成，进度实时回显；结束时用全局 toast 明确告知结果 */
    var storageTimer = null;
    var STORAGE_JOB_LABEL = { jinyu_storage_push: '推送', jinyu_storage_pull: '拉回' };
    var storageJobActive = false;

    function runStorageJob(action, btn, tipEl) {
        if (storageJobActive) { return; }
        storageJobActive = true;
        var label = STORAGE_JOB_LABEL[action] || '任务';
        var btnText = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = '处理中…'; }
        var progress = qs('#jinyu-storage-progress', root);
        var fill = qs('#jinyu-storage-bar-fill', root);
        var ptext = qs('#jinyu-storage-progress-text', root);
        if (progress) progress.hidden = false;
        if (fill) fill.style.width = '0%';
        if (ptext) ptext.textContent = '正在准备任务…';

        function finish(ok, text) {
            storageJobActive = false;
            if (storageTimer) { window.clearTimeout(storageTimer); storageTimer = null; }
            if (btn) { btn.disabled = false; btn.textContent = btnText; }
            if (progress) {
                window.setTimeout(function () {
                    if (progress) progress.hidden = true;
                    if (fill) fill.style.width = '0%';
                }, 2200);
            }
            if (tipEl) {
                tipEl.textContent = text;
                tipEl.className = 'jinyu-tools-tip ' + (ok ? 'ok' : 'err');
            }
            toast(text, ok);
        }

        function step() {
            postStorage(action, null, null, true)
                .then(function (res) {
                    if (!res || !res.success) {
                        finish(false, label + '失败：' + apiMsg(res, '请求被拒绝，请检查配置后重试'));
                        return;
                    }
                    var d = res.data || {};
                    var total = d.total || 0;
                    var done = d.done || 0;
                    var errors = d.errors || 0;
                    var pct = total ? Math.round((done / total) * 100) : 0;
                    if (fill) fill.style.width = pct + '%';
                    if (ptext) ptext.textContent = (d.message || '处理中…') + '（' + pct + '%）';
                    if (d.status === 'done' || done >= total) {
                        finish(errors === 0, label + '完成 · ' + (d.message || ('共 ' + done + ' 个文件')));
                        return;
                    }
                    storageTimer = window.setTimeout(step, 900);
                })
                .catch(function (err) { finish(false, label + '失败：' + ((err && err.message) || '网络中断')); });
        }
        step();
    }

    /* 面板加载时若存储任务仍在运行/排队，自动恢复进度条并续跑轮询。
       修复：刷新浏览器或关掉标签页后进度消失、且推送被掐停的问题（任务由浏览器轮询驱动）。 */
    function resumeStorageIfActive() {
        postStorage('jinyu_storage_status', null, null, true)
            .then(function (res) {
                if (!res || !res.success) { return; }
                var d = res.data || {};
                if (!d.active || (d.status !== 'running' && d.status !== 'pending')) { return; }
                var btn = qs('#jinyu-storage-' + d.type, root);
                var tip = qs('#jinyu-storage-' + d.type + '-tip', root);
                runStorageJob('jinyu_storage_' + d.type, btn, tip);
            })
            .catch(function () { /* 忽略：不影响面板正常使用 */ });
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
        if (status) { status.textContent = '检查中…'; status.className = 'jinyu-update-status'; }
        if (detail) detail.innerHTML = '';
        doFetch(S.ajax_url + '?action=jinyu_check_update&nonce=' + encodeURIComponent(S.nonce), {})
            .then(function (json) {
                if (!json || !json.success) {
                    if (status) { status.textContent = (json && json.data && json.data.msg) || '检查失败'; status.className = 'jinyu-update-status err'; }
                    return;
                }
                var d = json.data || {};
                if (status) {
                    if (d.has_update) {
                        status.textContent = '发现新版本 v' + d.latest;
                        status.className = 'jinyu-update-status ok';
                    } else {
                        status.textContent = '已是最新版本 v' + d.current;
                        status.className = 'jinyu-update-status';
                    }
                }
                if (detail) {
                    // 仅在确有新版本时才浮出更新卡片（更新日志 + 操作按钮）；已是最新时不渲染任何详情，不撑爆顶栏
                    var html = '';
                    if (d.has_update) {
                        html += '<div class="jinyu-update-pop">' +
                            '<div class="jinyu-update-pop-head">' +
                            '<i class="dashicons dashicons-download" aria-hidden="true"></i>' +
                            '新版本 v' + esc(d.latest) +
                            '<span>当前 v' + esc(d.current) + '</span>' +
                            '<button type="button" class="jinyu-update-pop-close" data-close-update aria-label="关闭">×</button>' +
                            '</div>' +
                            (d.changelog ? '<div class="jinyu-update-cl">' + esc(d.changelog) + '</div>' : '<p class="jinyu-update-nocl">暂无更新日志。</p>') +
                            '<div class="jinyu-update-pop-actions">' +
                            (d.download_url ? '<a class="jinyu-btn jinyu-btn-sm jinyu-btn-primary" href="' + esc(d.download_url) + '" target="_blank" rel="noopener">下载更新包</a>' : '') +
                            (d.detail_url ? '<a class="jinyu-btn jinyu-btn-sm" href="' + esc(d.detail_url) + '" target="_blank" rel="noopener">查看详情</a>' : '') +
                            '</div></div>';
                    }
                    detail.innerHTML = html;
                    if (d.has_update) attachUpdatePopClose(); else detachUpdatePopClose();
                }
            })
            .catch(function (err) { if (status) { status.textContent = '网络错误：' + err.message; status.className = 'jinyu-update-status err'; } })
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

    /* ---------- 顶栏高度实测：吸顶线跟随真实顶栏高度（顶栏换行/加高时不再遮挡面板头） ---------- */
    function syncTopbarH() {
        var wrap = document.querySelector('.jinyu-setting-wrap');
        var bar = document.querySelector('.jinyu-topbar');
        if (!wrap || !bar) return;
        var h = Math.round(bar.getBoundingClientRect().height);
        if (h > 0) wrap.style.setProperty('--jh-topbar-h', h + 'px');
    }
    function watchTopbarH() {
        syncTopbarH();
        var bar = document.querySelector('.jinyu-topbar');
        if (!bar) return;
        if ('ResizeObserver' in window) {
            new ResizeObserver(syncTopbarH).observe(bar);
        } else {
            window.addEventListener('resize', syncTopbarH);
        }
    }

    /* ---------- 入口 ---------- */
    function init() {
        root = document.getElementById('jinyu-setting-app');
        if (!root) return;
        build();
        resumeStorageIfActive();
        wireTopbar();
        // 窗口缩放时重算多选框折叠，保持触发框恒定一行
        window.addEventListener('resize', function () {
            if (root) layoutMultiAll();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
