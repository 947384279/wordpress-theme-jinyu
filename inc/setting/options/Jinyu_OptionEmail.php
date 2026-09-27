<?php
/**
 * 设置项分组：邮件
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */



namespace Jinyu\Theme\setting\options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Jinyu_OptionEmail extends Jinyu_BaseOptionItem {

	/**
	 * Get_fields()
	 *
	 * @return array 返回值
	 */
	public function get_fields(): array {
		return [
			'key'    => 'email',
			'title'  => __( '邮件发送', 'jinyu' ),
			'icon'   => 'dashicons-email',
			'desc'   => __( '配置 SMTP 发信通道。开启后全站系统邮件（评论回复、找回密码、注册通知等）均改走 SMTP，不受虚拟主机 mail() 限制。', 'jinyu' ),
			'fields' => [
				[
					'id'    => 'smtp_enable',
					'title' => __( '启用 SMTP 发信', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => 0,
					'desc'  => __( '关闭时仍可用「维护工具 → 发送测试邮件」单独验证通道，但不影响全站发信。', 'jinyu' ),
				],
				[
					'id'          => 'smtp_host',
					'title'       => __( 'SMTP 服务器', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => 'smtp.qq.com',
					'desc'        => __( '邮件服务商提供的 SMTP 域名，不要带协议前缀。', 'jinyu' ),
				],
				[
					'id'    => 'smtp_port',
					'title' => __( '端口', 'jinyu' ),
					'type'  => 'number',
					'sdt'   => 465,
					'min'   => 1,
					'max'   => 65535,
					'step'  => 1,
					'desc'  => __( 'SSL 通常 465，STARTTLS 通常 587。', 'jinyu' ),
				],
				[
					'id'      => 'smtp_secure',
					'title'   => __( '加密方式', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'tls',
					'options' => [
						[
							'label' => __( '自动（按端口判断）', 'jinyu' ),
							'value' => 'auto',
						],
						[
							'label' => __( 'SSL', 'jinyu' ),
							'value' => 'ssl',
						],
						[
							'label' => __( 'STARTTLS', 'jinyu' ),
							'value' => 'tls',
						],
						[
							'label' => __( '不加密', 'jinyu' ),
							'value' => 'none',
						],
					],
					'desc'    => __( '端口 465 选 SSL，587 选 STARTTLS；选错会连不上（465+STARTTLS 是常见坑）。', 'jinyu' ),
				],
				[
					'id'          => 'smtp_user',
					'title'       => __( '发件账号', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => 'you@example.com',
					'desc'        => __( '完整邮箱地址，多数服务商要求与发件人一致。', 'jinyu' ),
				],
				[
					'id'    => 'smtp_pwd',
					'title' => __( '授权码 / 密码', 'jinyu' ),
					'type'  => 'password',
					'sdt'   => '',
					'desc'  => __( '第三方邮箱填「授权码」而非登录密码。留空表示不修改已保存的值。', 'jinyu' ),
				],
				[
					'id'          => 'smtp_from',
					'title'       => __( '发件人邮箱', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => 'you@example.com',
					'desc'        => __( '留空则使用「发件账号」作为发件人。', 'jinyu' ),
				],
			],
		];
	}
}
