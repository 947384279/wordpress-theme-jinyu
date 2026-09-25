<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 友情链接数据：优先站点自定义友链 CPT（jy_link），回退 WP 原生链接管理器（bookmarks）
$jinyu_link_items = [];
foreach ( array_slice( function_exists('jinyu_get_links') ? jinyu_get_links() : [], 0, 6 ) as $jl ) {
	$jl_url = get_post_meta( $jl->ID, 'jy_link_url', true );
	if ( ! $jl_url ) {
		continue;
	}
	$jinyu_link_items[] = [ 'title' => $jl->post_title, 'url' => $jl_url ];
}
if ( ! $jinyu_link_items && function_exists( 'get_bookmarks' ) ) {
	foreach ( get_bookmarks( [ 'limit' => 6, 'hide_invisible' => true ] ) as $bm ) {
		$jinyu_link_items[] = [ 'title' => $bm->link_name, 'url' => $bm->link_url ];
	}
}

// 无友链数据时不输出任何节点
if ( ! $jinyu_link_items ) {
	return;
}
?>
<div class="jinyu-flinks">
  <div class="jinyu-flinks-head">
    <h4><?php esc_html_e( '友情链接', 'jinyu'); ?></h4>
    <?php
    // 入口 URL：后台手填优先；未填时自动指向使用「申请友链」模板的页面（都没有则不显示入口）
    $flink_apply = function_exists( 'jinyu_flink_apply_url' )
        ? jinyu_flink_apply_url()
        : trim( (string) jinyu_get_option( 'flink_apply_url', '' ) );
    if ( '' !== $flink_apply ) :
    ?>
    <a class="jinyu-flinks-apply" href="<?php echo esc_url( $flink_apply ); ?>">
      <?php esc_html_e( '申请友链', 'jinyu'); ?>
      <i class="fa-solid fa-angle-right" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
  </div>
  <ul class="jinyu-flinks-list">
    <?php foreach ( $jinyu_link_items as $li ) :
    $jinyu_link_host = '';
    $jinyu_link_parsed = wp_parse_url( $li['url'] );
    if ( ! empty( $jinyu_link_parsed['host'] ) ) {
        $jinyu_link_host = $jinyu_link_parsed['host'];
    }
    // 不调用任何第三方 favicon API：直接取该网站自己根目录的 /favicon.ico（向其自身请求，非第三方）。
    // 取不到或加载失败时，JS 兜底（data-jinyu-fallback-remove）删掉 <img>，露出底层默认链接图标（主题自带 Font Awesome，零网络请求）。
    // 沿用友链自身协议（http/https），避免对仅 http 的站点强行 https 而取不到。
    $jinyu_link_scheme = ( ! empty( $jinyu_link_parsed['scheme'] ) && in_array( $jinyu_link_parsed['scheme'], array( 'http', 'https' ), true ) )
        ? $jinyu_link_parsed['scheme'] : 'https';
    $jinyu_fav_url = $jinyu_link_host ? $jinyu_link_scheme . '://' . $jinyu_link_host . '/favicon.ico' : '';
?>
    <li>
      <a href="<?php echo esc_url( $li['url'] ); ?>" target="_blank" rel="noopener nofollow">
        <span class="jinyu-flink-ico">
          <i class="fa-solid fa-link" aria-hidden="true"></i>
          <?php if ( $jinyu_fav_url ) : ?>
          <img class="jinyu-flink-fav" src="<?php echo esc_url( $jinyu_fav_url ); ?>"
               alt="" width="16" height="16" loading="lazy" decoding="async" referrerpolicy="no-referrer"
               data-jinyu-fallback-remove>
          <?php endif; ?>
        </span>
        <?php echo esc_html( $li['title'] ); ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
