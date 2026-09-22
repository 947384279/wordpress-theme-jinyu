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
 * 一次性选项 key 迁移：cms_* → home_*
 * 旧版将首页版块配置以 cms_ 前缀存入 jinyu_options，前缀泄漏了“CMS/杂志”布局概念，
 * 且会在字段改名后被 drop_orphan_keys() 静默清空。统一重命名为 home_ 前缀，
 * 已保存的配置原样迁过来，避免老用户首页版块配置丢失。仅执行一次（用 transient 兜底去重）。
 */
function jinyu_migrate_cms_option_keys(): void
{
    if (get_transient('jinyu_migrated_cms_keys')) {
        return;
    }
    $map = [
        'cms_new_exclude_cats' => 'home_exclude_cats',
        'cms_show_four_grid'   => 'home_show_four_grid',
        'cms_four_grid_list'    => 'home_four_grid_list',
        'cms_show_2box'         => 'home_show_2box',
        'cms_show_2box_id'      => 'home_show_2box_id',
        'cms_show_2box_num'     => 'home_show_2box_num',
    ];
    $opts = get_option(JINYU_OPT, []);
    if (!is_array($opts) || !array_intersect_key($map, $opts)) {
        set_transient('jinyu_migrated_cms_keys', 1, MONTH_IN_SECONDS);
        return;
    }
    foreach ($map as $old => $new) {
        if (array_key_exists($old, $opts) && !array_key_exists($new, $opts)) {
            $opts[$new] = $opts[$old];
        }
        unset($opts[$old]);
    }
    update_option(JINYU_OPT, $opts);
    set_transient('jinyu_migrated_cms_keys', 1, MONTH_IN_SECONDS);
}
add_action('init', 'jinyu_migrate_cms_option_keys', 5);

/**
 * 升级内置垃圾评论关键词库：仅当站点仍停留在旧版内置列表（或旧版回退值）时，
 * 一键替换为更全的新版内置库；用户已自定义（含主动清空）的列表保持不变，绝不覆盖。
 * 用 transient 去重，保证只跑一次。
 */
function jinyu_migrate_spam_words(): void
{
    if (get_transient('jinyu_migrated_spam_words')) {
        return;
    }
    $opts = get_option(JINYU_OPT, []);
    if (!is_array($opts)) {
        set_transient('jinyu_migrated_spam_words', 1, MONTH_IN_SECONDS);
        return;
    }
    // 旧版内置库（文本框默认值 / 运行期回退值）的已知形态，命中即视为“未自定义”。
    $known_old = [
        '彩票,色情,赌博,代写,刷量',
        '彩票,色情,赌博,代写,刷量,贷款,发票,办证,加微信,返利,兼职,代运营',
    ];
    if (!array_key_exists('anti_spam_words', $opts)) {
        // 从未保存过该字段：jinyu_get_option 已回退 sdt（新版内置库），无需写库
        set_transient('jinyu_migrated_spam_words', 1, MONTH_IN_SECONDS);
        return;
    }
    if (!in_array(trim((string) $opts['anti_spam_words']), $known_old, true)) {
        // 已自定义（含清空）→ 不动
        set_transient('jinyu_migrated_spam_words', 1, MONTH_IN_SECONDS);
        return;
    }
    $opts['anti_spam_words'] = JINYU_DEFAULT_SPAM_WORDS;
    update_option(JINYU_OPT, $opts);
    set_transient('jinyu_migrated_spam_words', 1, MONTH_IN_SECONDS);
}
add_action('init', 'jinyu_migrate_spam_words', 6);

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
    // 非敏感键缺失保护：以库中现有值为底、本次传入覆盖，避免局部写入误删其它配置
    $data = array_merge($current, $data);
    return update_option(JINYU_OPT, $data);
}
