<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 站点底部
 *
 * @package WordPress
 * @subpackage Jinyu
 */
?>
<footer class="jinyu-footer">
  <div class="jinyu-container">
    <?php
    // 页脚小工具区（外观 › 小工具 › 底部小工具）：没放东西时一个节点都不输出
    if (is_active_sidebar('sidebar-footer')) : ?>
    <div class="jinyu-footer-widgets">
      <?php dynamic_sidebar('sidebar-footer'); ?>
    </div>
    <?php endif; ?>

    <?php
    // 友情链接数据提前计算，供下方整行显隐判断复用
    $jinyu_link_items = [];
    foreach (array_slice(jinyu_get_links(), 0, 6) as $jl) {
        $jl_url = get_post_meta($jl->ID, 'jy_link_url', true);
        if (!$jl_url) continue;
        $jinyu_link_items[] = ['title' => $jl->post_title, 'url' => $jl_url];
    }
    if (!$jinyu_link_items && function_exists('get_bookmarks')) {
        foreach (get_bookmarks(['limit' => 6, 'hide_invisible' => true]) as $bm) {
            $jinyu_link_items[] = ['title' => $bm->link_name, 'url' => $bm->link_url];
        }
    }

    // 三个页脚区块改为「有内容自动显示」（不再需要各自的总开关）：
    // 关于=有介绍文案、导航=已分配页脚菜单、友链=有链接数据。三项皆无时不输出整个容器。
    $jinyu_footer_about = trim((string) jinyu_get_option('footer_about', ''));
    if ($jinyu_footer_about !== '' || has_nav_menu('footer') || $jinyu_link_items) : ?>
    <div class="jinyu-footer-inner">

      <?php if ($jinyu_footer_about !== '') : ?>
      <div class="jinyu-footer-col">
        <h4><i class="fa-solid fa-circle-user" aria-hidden="true"></i><?php echo esc_html(jinyu_get_option('footer_about_title', __('关于本站', JINYU))); ?></h4>
        <p><?php echo wp_kses_post(jinyu_get_option('footer_about', get_bloginfo('description'))); ?></p>
      </div>
      <?php endif; ?>

      <?php if (has_nav_menu('footer')) : ?>
      <div class="jinyu-footer-col">
        <h4><i class="fa-solid fa-compass" aria-hidden="true"></i><?php esc_html_e('快速导航', JINYU); ?></h4>
        <?php
        wp_nav_menu([
            'theme_location' => 'footer',
            'menu_class'     => '',
            'container'      => false,
            'depth'          => 1,
            'fallback_cb'    => false,
        ]);
        ?>
      </div>
      <?php endif; ?>

      <?php if ($jinyu_link_items) : ?>
      <div class="jinyu-footer-col">
        <h4><i class="fa-solid fa-link" aria-hidden="true"></i><?php esc_html_e('友情链接', JINYU); ?></h4>
        <ul>
          <?php foreach ($jinyu_link_items as $li) : ?>
            <li>
              <a href="<?php echo esc_url($li['url']); ?>" target="_blank" rel="noopener nofollow">
                <?php echo esc_html($li['title']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

    </div>
    <?php endif; ?>

    <div class="jinyu-footer-bottom">
      <?php $social = jinyu_footer_social(); ?>
      <?php if ($social) : ?>
      <div class="jinyu-social-row">
        <?php foreach ($social as $s) : ?>
          <a href="<?php echo esc_url($s['url']); ?>" target="_blank" rel="noopener nofollow"
             title="<?php echo esc_attr($s['title']); ?>" aria-label="<?php echo esc_attr($s['title']); ?>">
            <i class="<?php echo esc_attr($s['icon']); ?>" aria-hidden="true"></i>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="jinyu-footer-info">
        <span class="jinyu-footer-info-title"><?php echo esc_html(jinyu_get_option('footer_copyright_title', __('站点信息', JINYU))); ?></span>
        <?php
        $copy = trim((string) jinyu_get_option('footer_copyright', ''));
        if ($copy) {
            echo wp_kses_post(str_replace(['{year}', '{name}'], [date('Y'), get_bloginfo('name')], $copy));
        } else {
            echo wp_kses_post(jinyu_footer_copyright());
        }
        ?>
        <?php if (($icp = jinyu_get_option('company_icp'))) : ?>
          <span class="jinyu-footer-icp"><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener nofollow"><?php echo esc_html($icp); ?></a></span>
        <?php endif; ?>
      </div>

      <?php if (jinyu_get_option('footer_runinfo', 0)) : ?>
      <div class="jinyu-footer-runinfo" data-jinyu-live="runinfo" hidden>
        <span class="jinyu-runinfo-item">查询 <b data-ri="q">—</b> 次</span>
        <span class="jinyu-runinfo-sep">·</span>
        <span class="jinyu-runinfo-item">内存 <b data-ri="mem">—</b> MB</span>
        <span class="jinyu-runinfo-sep">·</span>
        <span class="jinyu-runinfo-item">渲染 <b data-ri="ms">—</b> 秒</span>
      </div>
      <?php endif; ?>

      <?php /* 主题署名：硬编码静态输出，无后台开关、无链接 */ ?>
      <div class="jinyu-footer-credit">
        <i class="fa-brands fa-wordpress" aria-hidden="true"></i>
        <span>Theme by JinYu</span>
      </div>

      <?php get_template_part('ad/global-bottom'); ?>
    </div>
  </div>
</footer>

<div class="jinyu-scroll-util" aria-hidden="true">
  <button type="button" class="jinyu-scroll-btn jinyu-go-bottom" title="<?php esc_attr_e('去底部', JINYU); ?>" aria-label="<?php esc_attr_e('去底部', JINYU); ?>">
    <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
  </button>
  <button type="button" class="jinyu-scroll-btn jinyu-back-top" title="<?php esc_attr_e('回到顶部', JINYU); ?>" aria-label="<?php esc_attr_e('回到顶部', JINYU); ?>">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
</div>

<?php get_template_part('templates/auth', 'modal'); ?>

<?php wp_footer(); ?>
</body>
</html>
