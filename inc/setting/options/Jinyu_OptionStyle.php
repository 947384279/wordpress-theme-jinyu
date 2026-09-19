<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionStyle extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'style',
            'title'  => __('风格外观', JINYU),
            'desc'   => __('主题色、明暗模式、圆角与字体等视觉风格。', JINYU),
            'icon'   => 'fa-solid fa-palette',
            'fields' => [
                ['id'=>'style_color_primary','title'=>__('主题主色',JINYU),'type'=>'color','sdt'=>'#FF6B35','presets'=>['#FF6B35','#1C60F3','#07c160','#ff4d4f','#722ed1','#000000']],
                ['id'=>'style_radius','title'=>__('圆角大小',JINYU),'type'=>'slider','sdt'=>6,'min'=>0,'max'=>16,'step'=>1,'unit'=>'px'],
                ['id'=>'content_font_size','title'=>__('正文字号',JINYU),'type'=>'slider','sdt'=>16,'min'=>13,'max'=>20,'step'=>1,'unit'=>'px','desc'=>__('文章正文基准字号',JINYU)],
                ['id'=>'dark_palette','title'=>__('暗色配色方案',JINYU),'type'=>'select','sdt'=>'default','desc'=>__('「默认深灰」更柔和耐看；「纯黑」对比更强、更省电，适合 OLED 屏',JINYU),'options'=>[
                    ['label'=>__('默认深灰',JINYU),'value'=>'default'],
                    ['label'=>__('纯黑',JINYU),'value'=>'pureblack'],
                ]],
                ['id'=>'hide_admin_bar','title'=>__('屏蔽前台 Admin Bar',JINYU),'type'=>'switch','sdt'=>true],
                ['id'=>'cn_typography','title'=>__('中文排版优化',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('开启两端对齐、标点挤压、舒适行距等中文排版增强',JINYU)],
                ['id'=>'cn_autospace','title'=>__('中英文自动加空格',JINYU),'type'=>'switch','sdt'=>false,'desc'=>__('PHP 级处理：在中英文/数字边界自动插入空格（保护 HTML 标签与链接，不影响已排版内容）',JINYU)],
                ['id'=>'ext_link_target','title'=>__('外链新窗口 + nofollow',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('正文外链新窗口打开并加 rel="nofollow noopener"',JINYU)],

                /* ─── 主题模式 ─── */
                ['id'=>'theme_mode','title'=>__('默认主题模式',JINYU),'type'=>'select','sdt'=>'auto','desc'=>__('日光 / 暗黑为固定默认模式；「跟随系统」按访客系统自动切换。无论选哪种，前台右上角始终显示明暗切换按钮，访客可随时手动切换并记忆选择。',JINYU),'options'=>[
                    ['label'=>__('日光模式',JINYU),'value'=>'light'],
                    ['label'=>__('暗黑模式',JINYU),'value'=>'dark'],
                    ['label'=>__('跟随系统',JINYU),'value'=>'auto'],
                ]],
                ['id'=>'nav_blur','title'=>__('导航栏毛玻璃效果',JINYU),'type'=>'switch','sdt'=>true,'desc'=>__('头部导航的半透明磨砂玻璃效果；浏览器不支持 backdrop-filter 时自动降级为纯色',JINYU)],

                /* ─── 字体 ─── */
                ['id'=>'content_font','title'=>__('正文字体',JINYU),'type'=>'select','sdt'=>'system','options'=>[
                    ['label'=>__('系统无衬线（默认）',JINYU),'value'=>'system'],
                    ['label'=>__('宋体 / 衬线',JINYU),'value'=>'serif'],
                    ['label'=>__('等宽',JINYU),'value'=>'mono'],
                    ['label'=>__('圆体（幼圆/微软雅黑）',JINYU),'value'=>'round'],
                ],'desc'=>__('覆盖正文与界面全局字体栈',JINYU)],
            ],
        ];
    }
}
