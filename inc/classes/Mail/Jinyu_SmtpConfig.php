<?php

namespace Jinyu\Mail;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SMTP 配置（OOP 实现，供 phpmailer_init 钩子调用）
 */
class Jinyu_SmtpConfig
{
    /**
     * 测试邮件时临时覆盖（优先于数据库值）。
     * 允许 SMTP 测试在未「保存」的情况下，直接使用表单当前填写的值发信。
     * @var array|null
     */
    public static ?array $testOverride = null;

    /**
     * 将后台 SMTP 设置（或测试覆盖）应用到 PHPMailer 实例。
     *
     * @param object $phpmailer PHPMailer 实例
     */
    public function apply($phpmailer): void
    {
        $cfg = self::$testOverride ?? $this->cfgFromDb();
        self::applyConfig($phpmailer, $cfg);
    }

    /**
     * 从数据库读取 SMTP 配置（敏感字段已被 jinyu_get_option 透明解密）。
     */
    private function cfgFromDb(): array
    {
        return [
            'host'   => jinyu_get_option('smtp_host', ''),
            'port'   => (int) jinyu_get_option('smtp_port', 465),
            'secure' => jinyu_get_option('smtp_secure', 'ssl'),
            'user'   => jinyu_get_option('smtp_user', ''),
            'pwd'    => jinyu_get_option('smtp_pwd', ''),
            'from'   => jinyu_get_option('smtp_from', ''),
        ];
    }

    /**
     * 将一组 SMTP 配置应用到 PHPMailer 实例（无副作用，可被测试覆盖复用）。
     *
     * @param object $phpmailer PHPMailer 实例
     * @param array  $cfg       host/port/secure/user/pwd/from
     */
    public static function applyConfig($phpmailer, array $cfg): void
    {
        if (!is_object($phpmailer)) return;
        if (empty($cfg['host'])) return; // 未配置则不接管，走默认 mail()

        $phpmailer->isSMTP();
        $phpmailer->Host       = (string) $cfg['host'];
        $phpmailer->Port       = (int) ($cfg['port'] ?? 465);
        $secure                = (string) ($cfg['secure'] ?? 'ssl');
        $phpmailer->SMTPSecure = ($secure === 'none') ? '' : $secure;

        if (!empty($cfg['user'])) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = (string) $cfg['user'];
            $phpmailer->Password = (string) ($cfg['pwd'] ?? '');
        }

        $from = (string) ($cfg['from'] ?? '');
        if ($from && is_email($from)) {
            $phpmailer->From      = $from;
            $phpmailer->FromName  = get_bloginfo('name');
            $phpmailer->addReplyTo($from, get_bloginfo('name'));
        }
    }
}
