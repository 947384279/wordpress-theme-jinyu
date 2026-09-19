<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 选项读写
 * jinyu_get_option() / jinyu_is_checked() 命名风格
 */

/**
 * 获取设置项（带静态缓存 + 字段 sdt 默认值回退）
 *
 * 说明：选项字段定义里的 sdt 是“出厂默认值”，但历史实现只把它们用于设置页 UI 渲染，
 * 运行期只读数据库里已保存的 JINYU_OPT 数组。这导致用户未点过「保存」的站点上，
 * 所有带 sdt 的开关（如 html_minify、theme_mode、disable_emoji）都不会生效。
 * 这里在未命中已存值时回退到 sdt，使默认值成为单一事实来源。
 */
function jinyu_get_option(string $key, mixed $default = ''): mixed
{
    static $options = null;
    if ($options === null) {
        $options = get_option(JINYU_OPT, []);
        if (!is_array($options)) $options = [];
    }
    if (array_key_exists($key, $options)) {
        $val = $options[$key];
        // 敏感字段入库为密文，读取时透明解密（消费方无感知）
        if (in_array($key, jinyu_sensitive_keys(), true)) {
            $val = jinyu_decrypt($val);
        }
        return $val;
    }
    $sdt = jinyu_option_sdt($key);
    return $sdt !== null ? $sdt : $default;
}

/**
 * 获取单个选项的 sdt 出厂默认值（未保存时回退用）。
 * 委托 Jinyu_Setting::sdt_defaults() 构建——该方法会先加载基类与选项类，
 * 因此这里在类尚未加载时（如前台请求）自行 require Jinyu_Setting.php，
 * 避免前台因类不存在而拿不到默认值。
 */
function jinyu_option_sdt(string $key): mixed
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $file = defined('JINYU_ABS_DIR') ? JINYU_ABS_DIR . '/inc/setting/Jinyu_Setting.php' : '';
        if ($file && is_file($file)) {
            if (!class_exists('Jinyu_Setting', false)) {
                require_once $file;
            }
            if (class_exists('Jinyu_Setting', false)) {
                // 预注册空翻译域：get_fields() 内的 __() 在 WP 文本域就绪前被调用时，
                // 直接返回原文（中文主题原文即中文），不再触发 WP 6.7 的
                // load_textdomain_just_in_time “翻译加载过早” Notice，且 sdt 默认值照常生效。
                if (!isset($GLOBALS['l10n']['jinyu']) && class_exists('NOOP_Translations', false)) {
                    $GLOBALS['l10n']['jinyu'] = new NOOP_Translations();
                }
                $map = Jinyu_Setting::sdt_defaults();
            }
        }
    }
    return $map[$key] ?? null;
}

/**
 * 判断开关类选项是否开启
 */
function jinyu_is_checked(string $key): bool
{
    return (bool) jinyu_get_option($key, false);
}

/**
 * 批量保存全部选项（后台设置页使用）
 * 敏感字段在写入前加密，避免以明文落库。
 */
function jinyu_save_options(array $data): bool
{
    $current = get_option(JINYU_OPT, []);
    if (!is_array($current)) $current = [];
    foreach (jinyu_sensitive_keys() as $k) {
        if (!array_key_exists($k, $data)) {
            continue; // 本批保存不含该密钥，保留库中现有值
        }
        $val = $data[$k];
        if ($val === '' || $val === null) {
            // 留空 = 不修改：密码框留空表示沿用原值，保留库中已加密的值，避免误清空
            $data[$k] = $current[$k] ?? '';
            continue;
        }
        // 已是密文则不再二次包装
        if (strpos((string) $val, 'jinyu_enc::') !== 0) {
            $data[$k] = jinyu_encrypt($val);
        }
    }
    // 动态列表内的嵌套敏感子字段（OAuth 密钥）：
    // oauth_accounts 以 JSON 字符串落库，其 client_secret 子字段需单独加密。
    // 空值表示不修改（沿用按 platform 匹配的原值），已加密则跳过二次包装。
    if (isset($data['oauth_accounts'])) {
        $acc = is_string($data['oauth_accounts']) ? json_decode($data['oauth_accounts'], true) : $data['oauth_accounts'];
        if (!is_array($acc)) {
            $acc = [];
        }
        $curRaw = isset($current['oauth_accounts']) ? $current['oauth_accounts'] : [];
        $curAcc = is_string($curRaw) ? json_decode($curRaw, true) : $curRaw;
        if (!is_array($curAcc)) {
            $curAcc = [];
        }
        foreach ($acc as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $secret   = $item['client_secret'] ?? '';
            $platform = $item['platform'] ?? '';
            if ($secret === '' || $secret === null) {
                // 按 platform 沿用原值，避免拖拽 reorder 后索引错位导致密钥错配
                $acc[$i]['client_secret'] = '';
                foreach ($curAcc as $c) {
                    if (is_array($c) && ($c['platform'] ?? '') === $platform) {
                        $acc[$i]['client_secret'] = $c['client_secret'] ?? '';
                        break;
                    }
                }
            } elseif (strpos((string) $secret, 'jinyu_enc::') !== 0) {
                $acc[$i]['client_secret'] = jinyu_encrypt($secret);
            }
        }
        $data['oauth_accounts'] = json_encode($acc, JSON_UNESCAPED_UNICODE);
    }
    return update_option(JINYU_OPT, $data);
}
