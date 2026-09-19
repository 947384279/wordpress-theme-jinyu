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
            'title'  => __('关于', JINYU),
            'desc'   => __('主题版本、更新服务器与检查更新。', JINYU),
            'icon'   => 'fa-solid fa-info-circle',
            'fields' => [
                ['id'=>'version_info','title'=>__('当前版本',JINYU),'type'=>'info','desc'=>__('金玉主题 v' . JINYU_CUR_VER . ' · PHP 8.0+ · WordPress 7.1+',JINYU)],
                ['id'=>'update_check','title'=>__('检查更新',JINYU),'type'=>'update_check'],
            ],
        ];
    }
}
