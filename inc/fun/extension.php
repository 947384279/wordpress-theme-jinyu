<?php
/**
 * 扩展插槽（Extension Slots）—— 主题与外部实现之间的唯一接缝。
 *
 * 主题是纯呈现层：任何非呈现层能力（社交登录、验证码、对象存储、性能开关……）
 * 都由外部实现方（配套插件 / 私有插件 / mu-plugin）通过过滤器应答，
 * 主题既不探测对方函数是否存在，也不认识对方的任何符号。
 *
 * 三个入口，按返回类型区分：
 *   - jinyu_ext_markup( $slot, ...$args )  → HTML 片段（默认 ''，不输出任何内容）
 *   - jinyu_ext_value( $slot, $default, ...$args ) → 标量/数组（有默认值，天然降级）
 *   - jinyu_ext_enabled( $slot )           → bool（等价于 jinyu_ext_value( $slot, false )）
 *
 * 命名一律 jinyu_ext_{类型}_{语义}，实现方 add_filter 对应同名过滤器即可接入，
 * 无需主题改一行代码。
 *
 * @package         WordPress
 * @subpackage      Jinyu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jinyu_ext_markup' ) ) {
	/**
	 * 取扩展插槽的 HTML 片段。
	 *
	 * 约定：返回 '' 表示「该插槽无人应答」，调用方据此整块跳过渲染。
	 * 返回值是插件生成的完整 HTML（含其自身的 class / 转义），主题不再二次过滤，
	 * 以免破坏 SVG、data-* 属性或 nonce。
	 *
	 * @param string $slot 插槽名，如 'oauth_login' / 'captcha'。
	 * @param mixed  ...$args 透传给实现方的场景参数（如 captcha 的场景标识）。
	 * @return string HTML 片段，无人应答时为空串。
	 */
	function jinyu_ext_markup( string $slot, ...$args ): string {
		$html = apply_filters( 'jinyu_ext_markup_' . $slot, '', ...$args );
		return is_string( $html ) ? $html : '';
	}
}

if ( ! function_exists( 'jinyu_ext_value' ) ) {
	/**
	 * 取扩展插槽的值（非 HTML）。
	 *
	 * @param string $slot    插槽名。
	 * @param mixed  $default 无人应答时的返回值。
	 * @param mixed  ...$args 透传给实现方的参数。
	 * @return mixed
	 */
	function jinyu_ext_value( string $slot, $default = null, ...$args ) {
		/**
		 * 插槽无人应答时触发。
		 *
		 * 主题的降级策略一律是「静默回落到默认值」——这保证了插件缺席时页面不报错，
		 * 但也正是它让「功能其实已死」变得不可见：相关推荐曾因数据源被删而恒返回空数组，
		 * 跨 17 个版本无人察觉（见 jinyu_get_hot_posts 的教训）。
		 *
		 * 故在回落到「无插件」默认值时广播一次，接入方（或调试面板）可据此发现缺口。
		 * 注意：默认 $default 即表示无人应答，故只在 $default 为 null/false/''/0 这类
		 * 「未启用」语义时广播，避免对正常有值的插槽刷屏。
		 *
		 * @param string $slot    插槽名。
		 * @param mixed  $default 回落用的默认值。
		 */
		if ( null === $default || false === $default || '' === $default || 0 === $default ) {
			do_action( 'jinyu_ext_unhandled', $slot, $default );
		}

		return apply_filters( 'jinyu_ext_value_' . $slot, $default, ...$args );
	}
}

if ( ! function_exists( 'jinyu_ext_enabled' ) ) {
	/**
	 * 扩展插槽是否启用（布尔语义糖）。
	 *
	 * 主题自身目前无调用点，但它是面向配套插件的解耦契约 API，
	 * 对实现方（插件 / mu-plugin）而言是更清晰的接入姿势：想要布尔开关时不必自己
	 * 写 `false !== jinyu_ext_value( $slot, false )`。删除会破坏插件侧的兼容性契约，故保留。
	 *
	 * @param string $slot 插槽名。
	 * @return bool
	 */
	function jinyu_ext_enabled( string $slot ): bool {
		return (bool) jinyu_ext_value( $slot, false );
	}
}
