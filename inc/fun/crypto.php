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
        $key    = hash('sha256', wp_salt('auth'), true);
        $mac_key = hash('sha256', wp_salt('auth') . '|jinyu_mac', true);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt((string) $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($enc === false) return $plain;
        // encrypt-then-MAC：HMAC 覆盖 iv+密文，防御 CBC 篡改；新密文前缀 jinyu_enc2::
        $mac = hash_hmac('sha256', $iv . $enc, $mac_key, true);
        return 'jinyu_enc2::' . base64_encode($iv . $enc . $mac);
    }
}

if (!function_exists('jinyu_decrypt')) {
    function jinyu_decrypt($val)
    {
        if (!is_string($val)) return $val;

        // 新格式：带 HMAC，先校验完整性再解密
        if (strpos($val, 'jinyu_enc2::') === 0) {
            if (!function_exists('openssl_decrypt')) return '';
            $key     = hash('sha256', wp_salt('auth'), true);
            $mac_key = hash('sha256', wp_salt('auth') . '|jinyu_mac', true);
            $raw = base64_decode(substr($val, strlen('jinyu_enc2::')), true);
            if ($raw === false || strlen($raw) < 16 + 32) return '';
            $iv  = substr($raw, 0, 16);
            $enc = substr($raw, 16, -32);
            $mac = substr($raw, -32);
            $calc = hash_hmac('sha256', $iv . $enc, $mac_key, true);
            if (!hash_equals($calc, $mac)) return ''; // 完整性校验失败，拒绝
            $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $dec === false ? '' : $dec;
        }

        // 兼容旧格式（无 MAC）：仅解密不校验，保证历史密文可读
        if (strpos($val, 'jinyu_enc::') === 0) {
            if (!function_exists('openssl_decrypt')) return $val;
            $key = hash('sha256', wp_salt('auth'), true);
            $raw = base64_decode(substr($val, strlen('jinyu_enc::')), true);
            if ($raw === false || strlen($raw) < 17) return '';
            $iv  = substr($raw, 0, 16);
            $enc = substr($raw, 16);
            $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return $dec === false ? '' : $dec;
        }

        return $val;
    }
}
