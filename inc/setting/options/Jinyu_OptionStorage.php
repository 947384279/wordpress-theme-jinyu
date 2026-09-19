<?php

namespace Jinyu\Theme\setting\options;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionStorage extends Jinyu_BaseOptionItem
{
    public function get_fields(): array
    {
        return [
            'key'    => 'storage',
            'title'  => __( '对象存储', JINYU ),
            'desc'   => __( '接入主流对象存储（又拍云 / 七牛 / 阿里云 OSS / 腾讯云 COS），一键推送/拉回静态文件、切换加速域名。', JINYU ),
            'icon'   => 'dashicons-cloud',
            'fields' => [
                [
                    'id'      => 'storage_provider',
                    'title'   => __( '存储服务商', JINYU ),
                    'type'    => 'select',
                    'sdt'     => 's3',
                    'options' => [
                        [ 'value' => 's3', 'label' => __( 'S3 兼容（阿里云 OSS / 腾讯云 COS / 七牛云 Kodo / 华为 OBS）', JINYU ) ],
                        [ 'value' => 'upyun', 'label' => __( '又拍云 UpYun', JINYU ) ],
                    ],
                    'desc'    => __( '阿里云 OSS、腾讯云 COS、七牛云均兼容 S3 协议，统一走 S3 通道；又拍云走独立通道。', JINYU ),
                ],
                [
                    'id'          => 'storage_bucket',
                    'title'       => __( '存储桶 / 服务名', JINYU ),
                    'type'        => 'text',
                    'sdt'         => '',
                    'placeholder' => 'bucket-name 或 name-1250000000',
                ],
                [
                    'id'          => 'storage_region',
                    'title'       => __( '区域', JINYU ),
                    'type'        => 'text',
                    'sdt'         => '',
                    'placeholder' => 'oss-cn-hangzhou / ap-guangzhou / cn-south-1',
                ],
                [
                    'id'          => 'storage_endpoint',
                    'title'       => __( 'Endpoint 节点', JINYU ),
                    'type'        => 'text',
                    'sdt'         => '',
                    'placeholder' => 'oss-cn-hangzhou.aliyuncs.com（不含桶名）',
                    'desc'        => __( 'S3 系填「桶名之后的域名」（最终访问主机为：桶名.该域名），七牛为 s3.<区域>.qiniucs.com；又拍云留空即可（默认 v0.api.upyun.com，注意不要填 CDN 访问域名）。', JINYU ),
                ],
                [
                    'id'    => 'storage_access_key',
                    'title' => __( 'AccessKey / 操作员', JINYU ),
                    'type'  => 'text',
                    'sdt'   => '',
                ],
                [
                    'id'    => 'storage_secret',
                    'title' => __( 'Secret / 密码', JINYU ),
                    'type'  => 'password',
                    'sdt'   => '',
                ],
                [
                    'id'          => 'storage_domain',
                    'title'       => __( '加速域名', JINYU ),
                    'type'        => 'text',
                    'sdt'         => '',
                    'placeholder' => 'https://cdn.example.com',
                    'desc'        => __( '对外访问前缀（CDN 或存储自带域名），请绑定到存储桶根。填写即生效（附件链接自动切换）；请先完成「一键推送」把文件推上云，否则图片会因远端无文件而 404，异常时点「停用加速」立即回退本地。', JINYU ),
                ],
                [
                    'id'          => 'storage_prefix',
                    'title'       => __( '远端路径前缀', JINYU ),
                    'type'        => 'text',
                    'sdt'         => 'wp-content/uploads',
                    'desc'        => __( '文件在桶内的根目录，默认 wp-content/uploads。', JINYU ),
                ],
                [
                    'id'      => 'storage_auto_upload',
                    'title'   => __( '新附件自动同步', JINYU ),
                    'type'    => 'switch',
                    'sdt'     => false,
                    'desc'    => __( '发布/上传新媒体时自动推送到对象存储。', JINYU ),
                ],
                [
                    'id'      => 'storage_delete_local',
                    'title'   => __( '同步后删除本地', JINYU ),
                    'type'    => 'switch',
                    'sdt'     => false,
                    'desc'    => __( '推送成功后删除本地文件（存储为唯一源）。默认关闭，安全第一。', JINYU ),
                ],
                [
                    'id'    => 'storage_ops',
                    'title' => __( '操作', JINYU ),
                    'type'  => 'storage_ops',
                    'desc'  => __( '配置保存后，可在此执行：测试连接、一键推送 / 拉回、应用加速域名。', JINYU ),
                ],
            ],
        ];
    }
}
