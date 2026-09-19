<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 敏感字段加密存储（脱敏）
 * 用 wp_salt('auth') 派生 AES-256-CBC 密钥，库泄露也不会直接暴露明文。
 * 约定密文以 "jinyu_enc::" 前缀标识；解密失败或前缀不符时回落为原文（兼容历史明文）。
 */
if (!function_exists('jinyu_sensitive_keys')) {
    function jinyu_sensitive_keys(): array
    {
        // 需要入库即加密的字段（密码 / API Key / 存储密钥）。
        // 注意：OAuth 密钥现为 dynamic-list（oauth_accounts）内的嵌套子字段 client_secret，
        // 由 jinyu_save_options() 单独处理（按 platform 沿用/加密），不在此顶层清单内。
        return ['smtp_pwd', 'ai_api_key', 'storage_secret'];
    }
}

if (!function_exists('jinyu_encrypt')) {
    function jinyu_encrypt($plain)
    {
        if ($plain === '' || $plain === null) return $plain;
        if (!function_exists('openssl_encrypt')) return $plain;
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($enc === false) return $plain;
        return 'jinyu_enc::' . base64_encode($iv . $enc);
    }
}

if (!function_exists('jinyu_decrypt')) {
    function jinyu_decrypt($val)
    {
        if (!is_string($val) || strpos($val, 'jinyu_enc::') !== 0) return $val;
        if (!function_exists('openssl_decrypt')) return $val;
        $key = hash('sha256', wp_salt('auth'), true);
        $raw = base64_decode(substr($val, strlen('jinyu_enc::')), true);
        if ($raw === false || strlen($raw) < 17) return '';
        $iv  = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? '' : $dec;
    }
}
