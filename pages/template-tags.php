<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Template Name: 标签大全
*/
get_header();
?>

<div class="jinyu-container jinyu-main-wrap">
  <main id="jinyu-content" class="jinyu-content jinyu-single-wrap">
    <?php jinyu_breadcrumbs(); ?>
    <article class="jinyu-single">
      <h1 class="jinyu-article-title"><?php the_title(); ?></h1>
      <div class="jinyu-article-content">
        <div class="jinyu-tags-page jinyu-tags-grouped">
          <?php
          /**
           * 获取字符串的拼音首字母（仅取首字）
           * 中文 → GB2312 区位码映射到 A~Z；英文/数字直接返回大写。
           *
           * @param  string $str 输入字符串
           * @return string     大写首字母，或 '#' 表示无法识别
           */
          function jinyu_pinyin_first($str) {
              $str = trim($str);
              if ($str === '') return '#';

              $char = $str[0];
              // ASCII 字母直接返回大写
              if (preg_match('/^[a-zA-Z]$/', $char)) {
                  return strtoupper($char);
              }
              // 数字返回 #
              if (preg_match('/^[0-9]$/', $char)) {
                  return '#';
              }

              // 中文：UTF-8 → GB2312 取区位码
              $gb = @iconv('UTF-8', 'GB2312//IGNORE', $char);
              if ($gb === false || strlen($gb) < 2) return '#';

              $asc = ord($gb[0]) * 256 + ord($gb[1]) - 65536;

              // GB2312 拼音区间表（首字母匹配）
              if      ($asc >= -20319 && $asc <= -20284) return 'A';
              else if ($asc >= -20283 && $asc <= -19776) return 'B';
              else if ($asc >= -19775 && $asc <= -19219) return 'C';
              else if ($asc >= -19218 && $asc <= -18711) return 'D';
              else if ($asc >= -18710 && $asc <= -18527) return 'E';
              else if ($asc >= -18526 && $asc <= -18240) return 'F';
              else if ($asc >= -18239 && $asc <= -17923) return 'G';
              else if ($asc >= -17922 && $asc <= -17418) return 'H';
              else if ($asc >= -17417 && $asc <= -16475) return 'J';  // 无 I
              else if ($asc >= -16474 && $asc <= -16213) return 'K';
              else if ($asc >= -16212 && $asc <= -15641) return 'L';
              else if ($asc >= -15640 && $asc <= -15166) return 'M';
              else if ($asc >= -15165 && $asc <= -14923) return 'N';
              else if ($asc >= -14922 && $asc <= -14915) return 'O';
              else if ($asc >= -14914 && $asc <= -14631) return 'P';
              else if ($asc >= -14630 && $asc <= -14150) return 'Q';
              else if ($asc >= -14149 && $asc <= -14091) return 'R';
              else if ($asc >= -14090 && $asc <= -13319) return 'S';
              else if ($asc >= -13318 && $asc <= -12839) return 'T';
              // U, V 通常无汉字
              else if ($asc >= -12838 && $asc <= -12557) return 'W';
              else if ($asc >= -12556 && $asc <= -11848) return 'X';
              else if ($asc >= -11847 && $asc <= -11056) return 'Y';
              else if ($asc >= -11055 && $asc <= -10247) return 'Z';

              return '#';
          }

          // ── 获取所有标签并按拼音首字母分组 ──
          $tags = get_tags(['hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
          $groups = []; // letter => [tag, tag, ...]

          if ($tags) {
              foreach ($tags as $t) {
                  $letter = jinyu_pinyin_first($t->name);
                  if (!isset($groups[$letter])) {
                      $groups[$letter] = [];
                  }
                  $groups[$letter][] = $t;
              }
              // 按字母排序（A-Z 在前，# 最后）
              ksort($groups, SORT_STRING);
              if (isset($groups['#'])) {
                  $hash = $groups['#'];
                  unset($groups['#']);
                  $groups['#'] = $hash;
              }
          }
          ?>

          <?php if (!empty($groups)) : ?>

            <!-- 字母导航 -->
            <nav class="jinyu-tags-nav" aria-label="<?php esc_attr_e('字母导航', JINYU); ?>">
              <?php foreach ($groups as $letter => $items) : ?>
                <a href="#jinyu-tag-<?php echo esc_attr($letter); ?>"
                   class="jinyu-tag-nav-item<?php echo count($items) > 0 ? '' : ' disabled'; ?>">
                  <?php echo esc_html($letter); ?>
                </a>
              <?php endforeach; ?>
            </nav>

            <!-- 分组列表 -->
            <div class="jinyu-tags-groups">
              <?php foreach ($groups as $letter => $items) : ?>
                <section id="jinyu-tag-<?php echo esc_attr($letter); ?>" class="jinyu-tag-group">
                  <h3 class="jinyu-tag-group-title">
                    <span class="jinyu-tag-group-letter"><?php echo esc_html($letter); ?></span>
                    <span class="jinyu-tag-group-count"><?php echo (int)count($items); ?> <?php esc_html_e('个标签', JINYU); ?></span>
                  </h3>
                  <div class="jinyu-tag-list">
                    <?php foreach ($items as $t) : ?>
                      <a class="jinyu-tag-item" href="<?php echo esc_url(get_tag_link($t->term_id)); ?>">
                        <?php echo esc_html($t->name); ?>
                        <span class="jinyu-tag-count"><?php echo (int)$t->count; ?></span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </section>
              <?php endforeach; ?>
            </div>

          <?php else : ?>
            <p><?php esc_html_e('暂无标签', JINYU); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </article>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
