<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jinyu_Setting
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('wp_ajax_jinyu_save_options', [$this, 'ajax_save']);
        add_action('wp_ajax_jinyu_reset_options', [$this, 'ajax_reset']);
        add_action('wp_ajax_jinyu_export_options', [$this, 'ajax_export']);
        add_action('wp_ajax_jinyu_import_options', [$this, 'ajax_import']);
        add_action('wp_ajax_jinyu_reset_section', [$this, 'ajax_reset_section']);
        add_action('wp_ajax_jinyu_check_update', [$this, 'ajax_check_update']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('金玉主题配置', JINYU),
            __('金玉主题配置', JINYU),
            'manage_options',
            'jinyu-options',
            [$this, 'render_page'],
            'dashicons-admin-customizer',
            59
        );
    }

    /**
     * 所有设置分组类名（顺序即后台导航顺序）。collect_groups / sdt_defaults / field_schema 共用同一份清单。
     */
    public static function option_classes(): array
    {
        return ['Jinyu_OptionBasic','Jinyu_OptionGlobal','Jinyu_OptionStyle','Jinyu_OptionContent','Jinyu_OptionCarousel','Jinyu_OptionSeo','Jinyu_OptionEmail','Jinyu_OptionResource','Jinyu_OptionStorage','Jinyu_OptionExtend','Jinyu_OptionUser','Jinyu_OptionFooter','Jinyu_OptionCode','Jinyu_OptionSales','Jinyu_OptionAbout'];
    }

    /**
     * 展平所有分组的字段定义：id => 字段数组（单一遍历入口，避免多处重复实现）。
     */
    private static function each_field(): array
    {
        require_once JINYU_ABS_DIR . '/inc/setting/options/Jinyu_BaseOptionItem.php';
        $fields = [];
        foreach (self::option_classes() as $cls) {
            $file = JINYU_ABS_DIR . '/inc/setting/options/' . $cls . '.php';
            if (!file_exists($file)) continue;
            require_once $file;
            $fqcn = 'Jinyu\\Theme\\setting\\options\\' . $cls;
            if (!class_exists($fqcn)) continue;
            try {
                $group = (new $fqcn())->get_fields();
            } catch (\Throwable $e) {
                continue;
            }
            $list = $group['fields'] ?? $group;
            if (!is_array($list)) continue;
            foreach ($list as $f) {
                if (is_array($f) && isset($f['id'])) $fields[$f['id']] = $f;
            }
        }
        return $fields;
    }

    public function collect_groups(): array
    {
        require_once JINYU_ABS_DIR . '/inc/setting/options/Jinyu_BaseOptionItem.php';
        $classes = self::option_classes();

        $groups = [];
        foreach ($classes as $cls) {
            $file = JINYU_ABS_DIR . '/inc/setting/options/' . $cls . '.php';
            if (!file_exists($file)) continue;
            require_once $file;
            $fqcn = 'Jinyu\\Theme\\setting\\options\\' . $cls;
            if (class_exists($fqcn)) {
                $group = (new $fqcn())->get_fields();
                $groups[] = $group;
            }
        }

        // 维护工具：独立的运维操作面板（非设置字段，不进入保存数据）
        $groups[] = [
            'key'     => 'tools',
            'title'   => __('维护工具', JINYU),
            'desc'    => __('SMTP 测试与缓存清理等运维操作，不写入主题设置。', JINYU),
            'custom'  => 'tools',
            'fields'  => [],
        ];

        return $groups;
    }

    /**
     * 构建 id => sdt 默认值表（静态缓存）。
     * 供 opt.php 的 jinyu_get_option 在未命中已存值时回退，使字段 sdt 成为运行时默认值单一事实来源。
     */
    public static function sdt_defaults(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        foreach (self::each_field() as $id => $f) {
            $map[$id] = $f['sdt'] ?? null;
        }
        return $map;
    }

    /**
     * id => [type, min, max, step, options, sdt]，保存时服务端校验用。
     */
    public static function field_schema(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        foreach (self::each_field() as $id => $f) {
            $map[$id] = [
                'type'    => $f['type'] ?? 'string',
                'min'     => $f['min'] ?? null,
                'max'     => $f['max'] ?? null,
                'step'    => $f['step'] ?? null,
                'options' => (isset($f['options']) && is_array($f['options'])) ? array_column($f['options'], 'value') : [],
                'sdt'     => $f['sdt'] ?? null,
            ];
        }
        return $map;
    }

    /**
     * 按字段 schema 规范化前端提交的数据：
     * - 未注册的键一律丢弃（防脏数据入库）
     * - number / slider 钳到 min~max 并按 step 取整
     * - select / radio 限枚举，非法值回退 sdt
     * - switch 归一到 0 / 1
     * 其余类型（string / textarea / color / upload / password / dynamic-list）原样保留。
     */
    private function sanitize_fields(array $input): array
    {
        $schema = self::field_schema();
        $out    = [];
        foreach ($input as $key => $val) {
            $f = $schema[$key] ?? null;
            if (!$f) continue;
            switch ($f['type']) {
                case 'switch':
                    $out[$key] = (int) (bool) filter_var($val, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'number':
                case 'slider':
                    $n    = is_numeric($val) ? (float) $val : (float) $f['sdt'];
                    $step = $f['step'] !== null ? (float) $f['step'] : 1;
                    if ($f['min'] !== null) $n = max((float) $f['min'], $n);
                    if ($f['max'] !== null) $n = min((float) $f['max'], $n);
                    if ($step > 0) $n = round($n / $step) * $step;
                    if ($f['min'] !== null) $n = max((float) $f['min'], $n);
                    if ($f['max'] !== null) $n = min((float) $f['max'], $n);
                    $out[$key] = ($n == (int) $n) ? (int) $n : $n;
                    break;
                case 'select':
                case 'radio':
                    $allowed   = array_map('strval', $f['options']);
                    $out[$key] = in_array((string) $val, $allowed, true) ? $val : $f['sdt'];
                    break;
                default:
                    $out[$key] = $val;
            }
        }
        return $out;
    }

    /**
     * 裁剪孤儿键：移除已不在任何面板字段定义中的旧键（字段 id 改名后残留的旧数据）。
     * jinyu_options 的唯一写入路径是 ajax_save / ajax_import（均经 jinyu_save_options），
     * 站内不存在面板外的运行时键（如 jinyu_indexnow_key 为独立 option），故按已注册键集裁剪是安全的。
     * 避免库随字段重构无限膨胀、且旧键永远不被读取却占用存储。
     */
    private function drop_orphan_keys(array $opts): array
    {
        $known = array_keys(self::sdt_defaults());
        return array_intersect_key($opts, array_flip($known));
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) return;
        $groups = $this->collect_groups();
        include __DIR__ . '/template.php';
    }

    public function ajax_save(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('权限不足', JINYU));
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) wp_send_json_error(__('数据格式错误', JINYU));
        // 服务端按字段 schema 校验/钳制，并与现有选项合并（避免前端漏字段造成配置丢失）
        $current = get_option(JINYU_OPT, []);
        if (!is_array($current)) $current = [];
        $current = $this->drop_orphan_keys($current);
        $clean = $this->sanitize_fields($input);
        jinyu_save_options(array_merge($current, $clean));
        wp_send_json_success(['msg' => __('保存成功', JINYU)]);
    }

    public function ajax_reset(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('权限不足', JINYU));
        delete_option(JINYU_OPT);
        wp_send_json_success(['msg' => __('已重置', JINYU)]);
    }

    /**
     * 导出当前配置为 JSON（供备份 / 迁移）。
     */
    public function ajax_export(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('权限不足', JINYU));
        $data = get_option(JINYU_OPT, []);
        wp_send_json_success([
            'msg'  => __('导出成功', JINYU),
            'data' => $data,
        ]);
    }

    /**
     * 导入 JSON 配置（与现有选项合并，增量覆盖，不丢其它键）。
     */
    public function ajax_import(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('权限不足', JINYU));
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || !isset($body['data']) || !is_array($body['data'])) {
            wp_send_json_error(__('数据格式错误', JINYU));
        }
        $incoming = $body['data'];
        // 白名单过滤：只接受已注册字段的键，避免注入无关数据
        $allowed = array_keys(Jinyu_Setting::sdt_defaults());
        $filtered = [];
        $skipped_secret = 0;
        foreach ($incoming as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            // 跨站导入的密文由源站密钥加密，本机解不开 → 直接丢弃，不写入不可用的垃圾值
            if (is_string($v) && str_starts_with($v, 'jinyu_enc::') && jinyu_decrypt($v) === '') {
                $skipped_secret++;
                continue;
            }
            $filtered[$k] = $v;
        }
        // 与 ajax_save 走同一条清洗路径（min/max/step/select 白名单钳制），避免导入绕过约束
        $filtered = $this->sanitize_fields($filtered);
        $current = get_option(JINYU_OPT, []);
        $merged = array_merge($current, $filtered);
        jinyu_save_options($merged);
        $msg = __('导入成功', JINYU);
        if ($skipped_secret > 0) {
            $msg .= sprintf(__('；%d 个加密字段（API Key / 密码等）无法跨站解密，已跳过，请重新填写', JINYU), $skipped_secret);
        }
        wp_send_json_success(['msg' => $msg]);
    }

    /**
     * 仅重置某一个设置分组（移除该分组所有字段的已存值，回退到 sdt 默认值）。
     */
    public function ajax_reset_section(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('权限不足', JINYU));
        $body = json_decode(file_get_contents('php://input'), true);
        $key = isset($body['key']) ? $body['key'] : '';
        if (!$key) wp_send_json_error(__('参数错误', JINYU));

        $target = null;
        foreach ($this->collect_groups() as $g) {
            if (($g['key'] ?? '') === $key && empty($g['custom'])) { $target = $g; break; }
        }
        if (!$target) wp_send_json_error(__('分组不存在', JINYU));

        $ids = array_column($target['fields'] ?? [], 'id');
        $opts = get_option(JINYU_OPT, []);
        if (!is_array($opts)) $opts = [];
        foreach ($ids as $id) { unset($opts[$id]); }
        update_option(JINYU_OPT, $opts);
        wp_send_json_success(['msg' => __('已重置本组', JINYU)]);
    }

    /**
     * 检查主题更新：读取「更新服务器地址」返回的版本 JSON，与当前主题版本比较。
     * 期望 JSON：{"version":"1.1.0","changelog":"...","download_url":"...","detail_url":"..."}
     */
    public function ajax_check_update(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['msg' => __('权限不足', JINYU)]);

        $theme = wp_get_theme(get_template());
        $current = $theme->get('Version');

        // 复用统一拉取入口（含 RSA 签名校验，强制刷新）。
        $info = jinyu_fetch_update_info(true);
        if (null === $info) {
            if (get_transient('jinyu_update_verify_failed')) {
                wp_send_json_error(['msg' => __('更新源签名校验失败，已阻止升级（疑似更新源被篡改）', JINYU)]);
            }
            wp_send_json_error(['msg' => __('更新源无响应或返回数据不可用', JINYU)]);
        }

        $latest = trim((string) $info['version']);
        wp_send_json_success([
            'current'      => $current,
            'latest'       => $latest,
            'has_update'   => version_compare($latest, (string) $current, '>'),
            'changelog'    => isset($info['changelog']) ? (string) $info['changelog'] : '',
            'download_url' => isset($info['download_url']) ? (string) $info['download_url'] : '',
            'detail_url'   => isset($info['detail_url']) ? (string) $info['detail_url'] : '',
            'verified'     => true,
        ]);
    }
}