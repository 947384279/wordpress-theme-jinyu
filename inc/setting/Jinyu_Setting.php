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
    }

    public function register_menu(): void
    {
        add_theme_page(
            __('金玉主题配置', 'jinyu'),
            __('金玉主题配置', 'jinyu'),
            'edit_theme_options',
            'jinyu-options',
            [$this, 'render_page'],
            'dashicons-admin-customizer'
        );
    }

    /**
     * 所有设置分组类名（顺序即后台导航顺序）。collect_groups / sdt_defaults / field_schema 共用同一份清单。
     */
    public static function option_classes(): array
    {
        return ['Jinyu_OptionBasic','Jinyu_OptionGlobal','Jinyu_OptionStyle','Jinyu_OptionContent','Jinyu_OptionComment','Jinyu_OptionCarousel','Jinyu_OptionResource','Jinyu_OptionExtend','Jinyu_OptionUser','Jinyu_OptionFooter','Jinyu_OptionCode'];
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
        // 合并自定义面板（维护工具等）声明的字段，使其进入保存 schema：
        // 否则这类字段会被 sanitize_fields 当未知键丢弃、或被 drop_orphan_keys 清除。
        foreach (self::custom_groups() as $cg) {
            foreach (($cg['fields'] ?? []) as $f) {
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

        // 维护工具 / 我要反馈 等自定义面板（定义见 custom_groups()）。
        // 维护工具面板内由 admin.js 自行渲染并保存个别设置字段（如 footer_runinfo），
        // 这些字段必须在 custom_groups() 的 fields 中声明，才能进入保存 schema、不被 drop_orphan_keys 清除。
        return array_merge($groups, self::custom_groups());
    }

    /**
     * 自定义面板（非普通选项分组）：由 admin.js 自行渲染并保存个别字段。
     * 若面板内含需持久化的设置字段（如维护工具的 footer_runinfo），必须在 fields 中声明，
     * 以便 each_field() 将其纳入保存 schema（sanitize / drop_orphan_keys 据此识别）。
     */
    private static function custom_groups(): array
    {
        return [
            [
                'key'     => 'tools',
                'title'   => __('维护工具', 'jinyu'),
                'desc'    => __('SMTP 测试、缓存清理与运行信息开关等运维操作。', 'jinyu'),
                'custom'  => 'tools',
                'fields'  => [
                    ['id'=>'footer_runinfo','title'=>__('页脚显示运行信息','jinyu'),'type'=>'switch','sdt'=>0,'desc'=>__('在前台页脚输出一行实时运行信息：查询数 / 内存 / 渲染耗时。数值由 JS 实时拉取，不会被整页缓存冻结。开启后可在本面板「清理缓存」使其生效','jinyu')],
                ],
            ],
        ];
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
                    // 自由文本类字段按类型兜底 sanitize（纵深防御：入库前清洗，输出端仍有转义）
                    switch ($f['type']) {
                        case 'textarea':
                            $out[$key] = sanitize_textarea_field((string) $val);
                            break;
                        case 'color':
                            $out[$key] = sanitize_hex_color((string) $val) ?? $f['sdt'];
                            break;
                        case 'upload':
                            $out[$key] = esc_url_raw((string) $val);
                            break;
                        case 'password':
                            // 密钥类：保留原始字符（sanitize 会破坏密钥），落库经 crypto 加密，输出端 esc_attr
                            $out[$key] = (string) $val;
                            break;
                        case 'dynamic-list':
                            $out[$key] = is_array($val)
                                ? array_values(array_map('sanitize_text_field', array_map('strval', $val)))
                                : $f['sdt'];
                            break;
                        default: // string
                            $out[$key] = sanitize_text_field((string) $val);
                    }
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
        if (!current_user_can('edit_theme_options')) return;
        $groups = $this->collect_groups();
        include __DIR__ . '/template.php';
    }

    public function ajax_save(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('edit_theme_options')) wp_send_json_error(__('权限不足', 'jinyu'));
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) wp_send_json_error(__('数据格式错误', 'jinyu'));
        // 服务端按字段 schema 校验/钳制，并与现有选项合并（避免前端漏字段造成配置丢失）
        $current = get_option(JINYU_OPT, []);
        if (!is_array($current)) $current = [];
        $current = $this->drop_orphan_keys($current);
        $clean = $this->sanitize_fields($input);
        jinyu_save_options(array_merge($current, $clean));
        wp_send_json_success(['msg' => __('保存成功', 'jinyu')]);
    }

    public function ajax_reset(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('edit_theme_options')) wp_send_json_error(__('权限不足', 'jinyu'));
        delete_option(JINYU_OPT);
        wp_send_json_success(['msg' => __('已重置', 'jinyu')]);
    }

    /**
     * 导出当前配置为 JSON（供备份 / 迁移）。
     */
    public function ajax_export(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('edit_theme_options')) wp_send_json_error(__('权限不足', 'jinyu'));
        $data = get_option(JINYU_OPT, []);
        wp_send_json_success([
            'msg'  => __('导出成功', 'jinyu'),
            'data' => $data,
        ]);
    }

    /**
     * 导入 JSON 配置（与现有选项合并，增量覆盖，不丢其它键）。
     */
    public function ajax_import(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('edit_theme_options')) wp_send_json_error(__('权限不足', 'jinyu'));
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || !isset($body['data']) || !is_array($body['data'])) {
            wp_send_json_error(__('数据格式错误', 'jinyu'));
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
        $msg = __('导入成功', 'jinyu');
        if ($skipped_secret > 0) {
            $msg .= sprintf(__('；%d 个加密字段（API Key / 密码等）无法跨站解密，已跳过，请重新填写', 'jinyu'), $skipped_secret);
        }
        wp_send_json_success(['msg' => $msg]);
    }

    /**
     * 仅重置某一个设置分组（移除该分组所有字段的已存值，回退到 sdt 默认值）。
     */
    public function ajax_reset_section(): void
    {
        check_ajax_referer('jinyu_save_options', 'nonce');
        if (!current_user_can('edit_theme_options')) wp_send_json_error(__('权限不足', 'jinyu'));
        $body = json_decode(file_get_contents('php://input'), true);
        $key = isset($body['key']) ? $body['key'] : '';
        if (!$key) wp_send_json_error(__('参数错误', 'jinyu'));

        $target = null;
        foreach ($this->collect_groups() as $g) {
            if (($g['key'] ?? '') === $key && empty($g['custom'])) { $target = $g; break; }
        }
        if (!$target) wp_send_json_error(__('分组不存在', 'jinyu'));

        $ids = array_column($target['fields'] ?? [], 'id');
        $opts = get_option(JINYU_OPT, []);
        if (!is_array($opts)) $opts = [];
        foreach ($ids as $id) { unset($opts[$id]); }
        update_option(JINYU_OPT, $opts);
        wp_send_json_success(['msg' => __('已重置本组', 'jinyu')]);
    }

}