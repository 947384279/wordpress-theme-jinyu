<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
 if (function_exists('jinyu_ad_badge_wrap')) {
     echo jinyu_ad_badge_wrap( jinyu_get_option('ad_comment_top'), 'comment_top' );
 } ?>
