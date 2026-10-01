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
			'title'  => __( 'Email sending', 'jinyu' ),
			'icon'   => 'dashicons-email',
			'desc'   => __( 'Configure the SMTP mail channel. Once enabled, all system emails (comment replies, password resets, registration notices, etc.) go through SMTP, no longer limited by shared-hosting mail().', 'jinyu' ),
			'fields' => [
				[
					'id'    => 'smtp_enable',
					'title' => __( 'Enable SMTP mail', 'jinyu' ),
					'type'  => 'switch',
					'sdt'   => 0,
					'desc'  => __( 'When off, you can still verify the channel separately via "Maintenance Tools → Send test email", but site-wide sending is unaffected.', 'jinyu' ),
				],
				[
					'id'          => 'smtp_host',
					'title'       => __( 'SMTP server', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => 'smtp.qq.com',
					'desc'        => __( 'The SMTP host provided by your email provider, without a protocol prefix.', 'jinyu' ),
				],
				[
					'id'    => 'smtp_port',
					'title' => __( 'Port', 'jinyu' ),
					'type'  => 'number',
					'sdt'   => 465,
					'min'   => 1,
					'max'   => 65535,
					'step'  => 1,
					'desc'  => __( 'SSL usually uses 465; STARTTLS usually uses 587.', 'jinyu' ),
				],
				[
					'id'      => 'smtp_secure',
					'title'   => __( 'Encryption', 'jinyu' ),
					'type'    => 'select',
					'sdt'     => 'tls',
					'options' => [
						[
							'label' => __( 'Auto (detected by port)', 'jinyu' ),
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
							'label' => __( 'No encryption', 'jinyu' ),
							'value' => 'none',
						],
					],
					'desc'    => __( 'Choose SSL for port 465 and STARTTLS for 587; a wrong choice will fail to connect (465+STARTTLS is a common pitfall).', 'jinyu' ),
				],
				[
					'id'          => 'smtp_user',
					'title'       => __( 'Sender account', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => 'you@example.com',
					'desc'        => __( 'The full email address; most providers require it to match the sender.', 'jinyu' ),
				],
				[
					'id'    => 'smtp_pwd',
					'title' => __( 'Authorization code / password', 'jinyu' ),
					'type'  => 'password',
					'sdt'   => '',
					'desc'  => __( 'For third-party mailboxes, enter the "authorization code" instead of the login password. Leave empty to keep the saved value.', 'jinyu' ),
				],
				[
					'id'          => 'smtp_from',
					'title'       => __( 'Sender email', 'jinyu' ),
					'type'        => 'text',
					'sdt'         => '',
					'placeholder' => 'you@example.com',
					'desc'        => __( 'Leave empty to use the "Sender account" as the sender.', 'jinyu' ),
				],
			],
		];
	}
}
