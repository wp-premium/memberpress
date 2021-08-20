<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="<?php echo esc_attr($mepr_options->edge_updates_str); ?>-wrap">
  <input type="checkbox" id="<?php echo esc_attr($mepr_options->edge_updates_str); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp-edge-updates')); ?>" <?php checked($mepr_options->edge_updates); ?>/>&nbsp;<?php esc_html_e('Include MemberPress edge (development) releases in automatic updates (not recommended for production websites)', 'memberpress'); ?> <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/square-loader.gif'); ?>" alt="<?php esc_attr_e('Loading...', 'memberpress'); ?>" class="mepr_loader" />
</div>
