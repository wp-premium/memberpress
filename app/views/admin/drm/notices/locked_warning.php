<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mepr-notice-wrapper">
   <h3 class="mepr-notice-title"><?php echo $drm_info['heading']; ?></h3>
   <p class="mepr-notice-desc"><?php echo esc_html__( 'Your MemberPress license key is not found or is invalid. Without an active license key, your frontend is unaffected. However, you can no longer: Issue customer refunds, Add new members, Manage memberships.', 'memberpress' ); ?>
   </p>
   <p class="mepr-notice-actions">
      <a href="<?php echo esc_url( $drm_info['account_link'] ); ?>" class="button button-primary"><?php echo esc_html__( 'Click here to purchase or renew your license key', 'memberpress' ); ?></a>
   </p>
</div>

