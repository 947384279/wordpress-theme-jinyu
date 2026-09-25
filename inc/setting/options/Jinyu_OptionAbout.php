<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionAbout extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'about',
            'title'  => __('关于', 'jinyu'),
            'desc'   => __('主题版本、更新服务器与检查更新。', 'jinyu'),
            'icon'   => 'fa-solid fa-info-circle',
            'fields' => [
                ['id'=>'version_info','title'=>__('当前版本','jinyu'),'type'=>'info','desc'=>sprintf(__('金玉主题 v%s · PHP 8.0+ · WordPress 6.0+','jinyu'), JINYU_CUR_VER)],
                ['id'=>'update_check','title'=>__('检查更新','jinyu'),'type'=>'update_check'],
            ],
        ];
    }
}
