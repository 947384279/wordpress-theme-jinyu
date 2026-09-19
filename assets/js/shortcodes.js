/* 金玉主题：编辑器短代码快捷入口（TinyMCE 插件）
   由 _build.js 编译为 assets/dist/js/shortcodes.min.js。
   清单数据来自 window.JINYU_SC（后台 admin_head 注入）。 */
(function () {
    if (typeof tinymce === 'undefined') {
        return;
    }
    tinymce.PluginManager.add('jinyu_shortcodes', function (editor) {
        var list = (window.JINYU_SC && window.JINYU_SC.list) || [];
        var menu = list.map(function (item) {
            return {
                text: item.label,
                onclick: function () {
                    editor.insertContent(item.tpl);
                }
            };
        });
        editor.addButton('jinyu_shortcodes', {
            text: '金玉短码',
            type: 'menubutton',
            icon: false,
            menu: menu
        });
    });
})();
