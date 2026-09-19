/* 金玉主题：块编辑器（Gutenberg）短代码快捷入口
   由 _build.js 编译为 assets/dist/js/shortcodes-gb.min.js。
   清单数据来自 window.JINYU_SC_GB（后台 enqueue_block_editor_assets 经 wp_localize_script 注入）。
   实现采用稳定的 PluginSidebar + PluginSidebarMoreMenuItem（右上「⋯」菜单 + 右侧面板），
   点击任一短码即在光标处插入一个 core/shortcode 块。 */
(function () {
    if (typeof wp === 'undefined' || !wp.plugins || !wp.editPost || !wp.element ||
        !wp.components || !wp.data || !wp.blocks) {
        return;
    }

    var plugins   = wp.plugins;
    var editPost  = wp.editPost;
    var el        = wp.element.createElement;
    var Fragment  = wp.element.Fragment;
    var useEffect = wp.element.useEffect;
    var Button    = wp.components.Button;
    var list      = (window.JINYU_SC_GB && window.JINYU_SC_GB.list) || [];

    var STYLE_ID = 'jinyu-sc-gb-style';
    var STYLE    = '' +
        '.jinyu-sc-gb-panel{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:12px;}' +
        '.jinyu-sc-gb-panel .jinyu-sc-gb-btn{justify-content:center;height:auto;min-height:32px;' +
        'padding:6px 8px;line-height:1.3;white-space:normal;text-align:center;}' +
        '.jinyu-sc-gb-empty{padding:12px;color:#757575;font-size:12px;}';

    function injectStyle() {
        if (document.getElementById(STYLE_ID)) {
            return;
        }
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = STYLE;
        document.head.appendChild(s);
    }

    function insertShortcode(tpl) {
        var block = wp.blocks.createBlock('core/shortcode', { text: tpl });
        wp.data.dispatch('core/block-editor').insertBlocks(block);
    }

    function ShortcodePanel() {
        useEffect(function () { injectStyle(); }, []);

        var children;
        if (list.length) {
            children = list.map(function (item) {
                return el(Button, {
                    key: item.key,
                    isSecondary: true,
                    className: 'jinyu-sc-gb-btn',
                    onClick: function () { insertShortcode(item.tpl); }
                }, item.label);
            });
        } else {
            children = el('div', { className: 'jinyu-sc-gb-empty' }, '暂无可用短代码');
        }

        return el(Fragment, null,
            el(editPost.PluginSidebarMoreMenuItem, {
                target: 'jinyu-sc-gb-sidebar'
            }, '金玉短码'),
            el(editPost.PluginSidebar, {
                name: 'jinyu-sc-gb-sidebar',
                title: '金玉短码',
                icon: 'shortcode'
            },
            el('div', { className: 'jinyu-sc-gb-panel' }, children))
        );
    }

    plugins.registerPlugin('jinyu-shortcodes-gb', {
        render: ShortcodePanel
    });
})();
