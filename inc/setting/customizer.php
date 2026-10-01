<?php
/**
 * 主题选项注册进 WordPress Customizer（w.org 合规「主题选项须走 Customizer」）。
 *
 * 设计要点：
 *  - 数据仍落于 jinyu_options 数组（与独立设置页同一份），前端 jinyu_get_option() 零改动。
 *  - 自托管版：独立设置页（Jinyu_Setting）保留不动，本文件仅「补充」Customizer 入口（双入口并存）。
 *  - w.org 变体：独立设置页不加载，Customizer 是唯一的外观选项入口。
 *  - password 类字段不进主题 Customizer（敏感/功能性，由 companion 插件管理），仍可在独立页编辑。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 收集各分组字段定义（复用 jinyu_option_classes() 的清单与命名空间约定）。
 * 不依赖 Jinyu_Setting 类，故在 w.org 变体（该类不加载）中同样可用。
 *
 * @return array
 */
function jinyu_cz_groups(): array {
	if ( ! function_exists( 'jinyu_option_classes' ) ) {
		require_once __DIR__ . '/option-classes.php';
	}
	// 基类必须先于各 Option 文件加载（Jinyu_OptionXxx extends Jinyu_BaseOptionItem）。
	// 独立设置页走 Jinyu_Setting::each_field() 时会顺手 require，此处不可省：
	// 否则 customize_register 触发时基类尚未定义 → Fatal error。
	require_once JINYU_ABS_DIR . '/inc/setting/options/Jinyu_BaseOptionItem.php';

	$groups = [];
	foreach ( jinyu_option_classes() as $cls ) {
		$file = JINYU_ABS_DIR . '/inc/setting/options/' . $cls . '.php';
		if ( ! is_file( $file ) ) {
			continue;
		}
		require_once $file;
		$fqcn = 'Jinyu\\Theme\\setting\\options\\' . $cls;
		if ( ! class_exists( $fqcn ) ) {
			continue;
		}
		$group = ( new $fqcn() )->get_fields();
		if ( is_array( $group ) && ! empty( $group['fields'] ) ) {
			$groups[] = $group;
		}
	}
	return $groups;
}

/**
 * 单项 sanitize（复刻 Jinyu_Setting::sanitize_fields 的单支逻辑，供 Customizer setting 使用）。
 *
 * @param mixed $val
 * @param array $f
 * @return mixed
 */
function jinyu_cz_sanitize_one( $val, array $f ) {
	$type = $f['type'] ?? 'string';
	switch ( $type ) {
		case 'switch':
			return (int) (bool) filter_var( $val, FILTER_VALIDATE_BOOLEAN );
		case 'number':
		case 'slider':
			$n    = is_numeric( $val ) ? (float) $val : (float) ( $f['sdt'] ?? 0 );
			$step = isset( $f['step'] ) && $f['step'] !== null ? (float) $f['step'] : 1;
			if ( isset( $f['min'] ) && $f['min'] !== null ) {
				$n = max( (float) $f['min'], $n );
			}
			if ( isset( $f['max'] ) && $f['max'] !== null ) {
				$n = min( (float) $f['max'], $n );
			}
			if ( $step > 0 ) {
				$n = round( $n / $step ) * $step;
			}
			if ( isset( $f['min'] ) && $f['min'] !== null ) {
				$n = max( (float) $f['min'], $n );
			}
			if ( isset( $f['max'] ) && $f['max'] !== null ) {
				$n = min( (float) $f['max'], $n );
			}
			return ( $n == (int) $n ) ? (int) $n : $n; /* phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- 比较双方类型已知一致，== 符合业务意图 */
		case 'select':
		case 'radio':
			$allowed = isset( $f['options'] ) && is_array( $f['options'] )
				? array_map( 'strval', array_column( $f['options'], 'value' ) )
				: [];
			return in_array( (string) $val, $allowed, true ) ? $val : ( $f['sdt'] ?? '' );
		case 'textarea':
			return ! empty( $f['html'] )
				? wp_kses_post( (string) $val )
				: sanitize_textarea_field( (string) $val );
		case 'color':
			return sanitize_hex_color( (string) $val ) ?: ( $f['sdt'] ?? '' );
		case 'upload':
			return esc_url_raw( (string) $val );
		case 'dynamic-list':
			if ( is_array( $val ) ) {
				return array_values( array_map( 'sanitize_text_field', array_map( 'strval', $val ) ) );
			}
			return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $val ) ) ) );
		default: // string
			return ! empty( $f['html'] )
				? wp_kses_post( (string) $val )
				: sanitize_text_field( (string) $val );
	}
}

/**
 * options 数组转 WP_Customize_Control 的 choices。
 *
 * @param array $f
 * @return array
 */
function jinyu_cz_choices( array $f ): array {
	$out = [];
	foreach ( $f['options'] ?? [] as $o ) {
		if ( is_array( $o ) && isset( $o['value'] ) ) {
			$out[ (string) $o['value'] ] = $o['label'] ?? (string) $o['value'];
		}
	}
	return $out;
}

/**
 * 注册回调：把所有主题选项接入 Customizer。
 *
 * @param WP_Customize_Manager $wp_customize
 */
function jinyu_cz_register( $wp_customize ) {
	if ( ! class_exists( 'WP_Customize_Control' ) ) {
		return;
	}

	// ── 自定义控件（仅在 Customizer 环境定义，避免前台父类不存在 fatal） ──
	if ( ! class_exists( 'Jinyu_CZ_Heading_Control' ) ) {
		class Jinyu_CZ_Heading_Control extends WP_Customize_Control {
			public $type = 'jinyu_heading';
			public function render_content(): void {
				printf(
					'<h3 class="customize-control-title" style="margin-top:1.2em;text-transform:none;letter-spacing:0;font-size:13px;color:#1d2327;">%s</h3>',
					esc_html( $this->label )
				);
			}
		}

		class Jinyu_CZ_Info_Control extends WP_Customize_Control {
			public $type = 'jinyu_info';
			public function render_content(): void {
				if ( $this->label ) {
					printf( '<span class="customize-control-title">%s</span>', esc_html( $this->label ) );
				}
				if ( $this->description ) {
					printf( '<span class="description customize-control-description">%s</span>', wp_kses_post( $this->description ) );
				}
			}
		}

		class Jinyu_CZ_Range_Control extends WP_Customize_Control {
			public $type = 'jinyu_range';
			public $min  = 0;
			public $max  = 100;
			public $step = 1;
			public $unit = '';
			public function render_content(): void {
				$val = $this->value();
				?>
				<label>
					<?php if ( $this->label ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif; ?>
					<input type="range" <?php $this->link(); ?> value="<?php echo esc_attr( $val ); ?>"
						min="<?php echo esc_attr( $this->min ); ?>" max="<?php echo esc_attr( $this->max ); ?>" step="<?php echo esc_attr( $this->step ); ?>"
						oninput="this.nextElementSibling.textContent=this.value">
					<output><?php echo esc_html( $val ); ?><?php echo esc_html( $this->unit ); ?></output>
				</label>
				<?php if ( $this->description ) : ?>
					<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
				<?php endif; ?>
				<?php
			}
		}

		class Jinyu_CZ_List_Control extends WP_Customize_Control {
			public $type = 'jinyu_list';
			public function render_content(): void {
				$val  = $this->value();
				$text = is_array( $val ) ? implode( "\n", $val ) : (string) $val;
				?>
				<label>
					<?php if ( $this->label ) : ?>
						<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
					<?php endif; ?>
					<textarea rows="4" class="widefat" <?php $this->link(); ?>><?php echo esc_textarea( $text ); ?></textarea>
				</label>
				<?php if ( $this->description ) : ?>
					<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
				<?php endif; ?>
				<?php
			}
		}
	}

	$groups = jinyu_cz_groups();
	if ( empty( $groups ) ) {
		return;
	}

	$panel_id = 'jinyu_theme_options';
	$wp_customize->add_panel(
		$panel_id,
		[
			'title'       => __( 'Jinyu Theme Settings', 'jinyu' ),
			'description' => __( 'Theme appearance and layout options. Changes can be previewed live on the right.', 'jinyu' ),
			'priority'    => 30,
		]
	);

	foreach ( $groups as $gi => $group ) {
		$section_id = 'jinyu_sec_' . ( $group['key'] ?? (string) $gi );
		$wp_customize->add_section(
			$section_id,
			[
				'title'       => $group['title'] ?? __( 'Settings', 'jinyu' ),
				'description' => $group['desc'] ?? '',
				'panel'       => $panel_id,
				'priority'    => 10 + (int) $gi,
			]
		);

		foreach ( $group['fields'] ?? [] as $fi => $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$type = $f['type'] ?? 'string';

			// subhead / info：纯展示，无 setting
			if ( 'subhead' === $type ) {
				$wp_customize->add_control(
					new Jinyu_CZ_Heading_Control(
						$wp_customize,
						'jinyu_h_' . $gi . '_' . $fi,
						[
							'section'  => $section_id,
							'label'    => $f['title'] ?? '',
							'settings' => [],
						]
					)
				);
				continue;
			}
			if ( 'info' === $type ) {
				$wp_customize->add_control(
					new Jinyu_CZ_Info_Control(
						$wp_customize,
						'jinyu_i_' . $gi . '_' . $fi,
						[
							'section'     => $section_id,
							'label'       => $f['title'] ?? '',
							'description' => $f['desc'] ?? '',
							'settings'    => [],
						]
					)
				);
				continue;
			}

			// 密码类不进主题 Customizer（敏感/功能性，由 companion 插件接管）
			if ( 'password' === $type ) {
				continue;
			}

			$id = $f['id'] ?? null;
			if ( null === $id ) {
				continue;
			}

			$setting_id = JINYU_OPT . '[' . $id . ']';
			$sdt        = $f['sdt'] ?? '';
			// 控件 ID 必须匹配 [a-z0-9_-]（WP Customizer 规范）：core 用
			// '#customize-control-' + id.replace(/\[/g,'-').replace(/\]/g,'') 构造选择器，
			// 含中文/空格的 ID 会让选择器失配，控件渲染失败。故用「分组序号_字段序号」，
			// 不能拿 title（中文/空格）或 setting id（含方括号）充当控件 ID。
			$ctrl_id = 'jinyu_c_' . $gi . '_' . $fi;

			$wp_customize->add_setting(
				$setting_id,
				[
					'type'              => 'option',
					'capability'        => 'edit_theme_options',
					'default'           => $sdt,
					'sanitize_callback' => function ( $val ) use ( $f ) {
						return jinyu_cz_sanitize_one( $val, $f );
					},
					'transport'         => 'refresh',
				]
			);

			$control_args = [
				'label'       => $f['title'] ?? $id,
				'description' => $f['desc'] ?? '',
				'section'     => $section_id,
				'settings'    => $setting_id,
			];

			switch ( $type ) {
				case 'color':
					$wp_customize->add_control(
						new WP_Customize_Color_Control( $wp_customize, $ctrl_id, $control_args )
					);
					break;
				case 'select':
					$control_args['type']    = 'select';
					$control_args['choices'] = jinyu_cz_choices( $f );
					$wp_customize->add_control( $ctrl_id, $control_args );
					break;
				case 'radio':
					$control_args['type']    = 'radio';
					$control_args['choices'] = jinyu_cz_choices( $f );
					$wp_customize->add_control( $ctrl_id, $control_args );
					break;
				case 'slider':
					$wp_customize->add_control(
						new Jinyu_CZ_Range_Control(
							$wp_customize,
							$ctrl_id,
							array_merge(
								$control_args,
								[
									'min'  => $f['min'] ?? 0,
									'max'  => $f['max'] ?? 100,
									'step' => $f['step'] ?? 1,
									'unit' => $f['unit'] ?? '',
								]
							)
						)
					);
					break;
				case 'number':
					$control_args['type']        = 'number';
					$control_args['input_attrs'] = array_filter(
						[
							'min'  => $f['min'] ?? null,
							'max'  => $f['max'] ?? null,
							'step' => $f['step'] ?? null,
						]
					);
					$wp_customize->add_control( $ctrl_id, $control_args );
					break;
				case 'textarea':
					$control_args['type'] = 'textarea';
					$wp_customize->add_control( $ctrl_id, $control_args );
					break;
				case 'upload':
					$wp_customize->add_control(
						new WP_Customize_Upload_Control( $wp_customize, $ctrl_id, $control_args )
					);
					break;
				case 'dynamic-list':
					$wp_customize->add_control(
						new Jinyu_CZ_List_Control( $wp_customize, $ctrl_id, $control_args )
					);
					break;
				case 'switch':
					$control_args['type'] = 'checkbox';
					$wp_customize->add_control( $ctrl_id, $control_args );
					break;
				default: // string
					$control_args['type'] = 'text';
					$wp_customize->add_control( $ctrl_id, $control_args );
			}
		}
	}
}
add_action( 'customize_register', 'jinyu_cz_register' );
