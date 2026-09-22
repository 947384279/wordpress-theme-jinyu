<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 侧边栏
 *
 * 后台「外观 > 小工具」里若向「主侧边栏」拖拽了主题专属小工具，则优先渲染拖拽内容；
 * 若未配置或 sidebar 为空，则自动 fallback 到主题内置的默认小工具。
 *
 * @package WordPress
 * @subpackage Jinyu
 */

// 后台选择「不显示侧边栏」时直接跳过
if (jinyu_get_option('sidebar_pos', 'right') === 'none') {
    return;
}
?>
<aside class="jinyu-sidebar">

    <?php if (is_active_sidebar('sidebar-main')) : ?>
        <?php dynamic_sidebar('sidebar-main'); ?>
    <?php else : ?>

        <!-- 站点信息卡 -->
        <section class="jinyu-widget jinyu-widget--author jinyu-author-card">
            <div class="jinyu-author-cover"></div>
            <div class="jinyu-author-avatar">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
            </div>
            <div class="jinyu-author-info">
                <h4 class="jinyu-author-name"><?php echo esc_html(get_bloginfo('name')); ?></h4>
                <p class="jinyu-author-desc"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
        </section>

        <!-- 热门文章（数据源与「金玉·热门文章」小工具共用：inc/fun/related.php） -->
        <?php $hot_posts = function_exists('jinyu_get_hot_posts') ? jinyu_get_hot_posts(5) : []; ?>
        <section class="jinyu-widget">
            <h3 class="jinyu-widget-title"><i class="fa-solid fa-fire"></i> <?php esc_html_e('热门文章', 'jinyu'); ?></h3>
            <ul class="jinyu-hot-list">
                <?php if ($hot_posts) : ?>
                    <?php foreach ($hot_posts as $jy_i => $jy_p) : ?>
                        <li class="jinyu-hot-item">
                            <span class="jinyu-hot-rank jinyu-rank-<?php echo esc_attr(min(3, $jy_i + 1)); ?>">
                                <?php echo esc_html($jy_i + 1); ?>
                            </span>
                            <div class="jinyu-hot-info">
                                <div class="jinyu-hot-title">
                                    <a href="<?php echo esc_url(get_permalink($jy_p)); ?>"><?php echo esc_html(get_the_title($jy_p)); ?></a>
                                </div>
                                <?php if (jinyu_show_views()) : ?>
                                <div class="jinyu-hot-views">
                                    <i class="fa-regular fa-eye" aria-hidden="true"></i> <?php echo esc_html(jinyu_get_post_views($jy_p->ID)); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else : ?>
                    <li class="jinyu-hot-item">
                        <span class="jinyu-hot-rank">1</span>
                        <div class="jinyu-hot-info"><div class="jinyu-hot-title"><?php esc_html_e('暂无热门文章', 'jinyu'); ?></div></div>
                    </li>
                <?php endif; ?>
            </ul>
        </section>

        <!-- 分类目录 -->
        <section class="jinyu-widget">
            <h3 class="jinyu-widget-title"><i class="fa-regular fa-folder-open"></i> <?php esc_html_e('分类目录', 'jinyu'); ?></h3>
            <?php
            $cats = get_categories(['hide_empty' => true]);
            if ($cats) :
            ?>
                <ul class="jinyu-widget-list jinyu-cat-list">
                    <?php foreach ($cats as $c) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_category_link($c->term_id)); ?>">
                                <?php echo esc_html($c->name); ?>
                                <span class="jinyu-cat-count"><?php echo (int) $c->count; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="jinyu-widget-empty"><?php esc_html_e('暂无分类', 'jinyu'); ?></p>
            <?php endif; ?>
        </section>

        <!-- 最新评论（与「金玉·最新评论」小工具共用：inc/fun/comment.php） -->
        <?php $recent_comments = jinyu_recent_comments_list(5); ?>
        <section class="jinyu-widget">
            <h3 class="jinyu-widget-title"><i class="fa-regular fa-comment"></i> <?php esc_html_e('最新评论', 'jinyu'); ?></h3>
            <?php if ($recent_comments) : ?>
                <?php echo $recent_comments; // phpcs:ignore WordPress.Security.EscapeOutput -- 内部函数已逐字段转义 ?>
            <?php else : ?>
                <p class="jinyu-widget-empty"><?php esc_html_e('暂无评论', 'jinyu'); ?></p>
            <?php endif; ?>
        </section>

        <!-- 标签云（与小工具共用 jinyu_tag_cloud_html()，样式/分级只有一套实现） -->
        <?php $tag_cloud = jinyu_tag_cloud_html(15); ?>
        <?php if ($tag_cloud !== '') : ?>
        <section class="jinyu-widget">
            <h3 class="jinyu-widget-title"><i class="fa-solid fa-tags"></i> <?php esc_html_e('标签云', 'jinyu'); ?></h3>
            <div class="jinyu-tag-cloud"><?php echo $tag_cloud; // phpcs:ignore WordPress.Security.EscapeOutput -- 内部函数已逐字段转义 ?></div>
        </section>
        <?php endif; ?>

        <!-- 搜索 -->
        <section class="jinyu-widget">
            <h3 class="jinyu-widget-title"><i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e('搜索', 'jinyu'); ?></h3>
            <form class="jinyu-search-box" method="get" action="<?php echo esc_url(home_url('/')); ?>" role="search">
                <input type="search" name="s" placeholder="<?php esc_attr_e('输入关键词', 'jinyu'); ?>" aria-label="<?php esc_attr_e('搜索', 'jinyu'); ?>">
                <button type="submit" aria-label="<?php esc_attr_e('搜索', 'jinyu'); ?>">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
            </form>
        </section>

    <?php endif; ?>

</aside>
