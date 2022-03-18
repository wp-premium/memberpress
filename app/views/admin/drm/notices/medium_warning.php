<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mepr-notice-wrapper">
   <h3 class="mepr-notice-title"><?php echo $drm_info['heading']; ?></h3>
   <p class="mepr-notice-desc"><?php echo $drm_info['simple_message']; ?></p>

   <?php if( $drm_info['event_name'] == MeprDrmHelper::INVALID_LICENSE_EVENT ): ?>
     <ul class="mepr-drm-action-items">
       <li><?php echo sprintf( __( 'Go to MemberPress.com and make your selection. <a target="_blank" href="%s">Pricing Page</a>.', 'memberpress' ), $drm_info['pricing_link'] ); ?></li>
       <li><?php echo sprintf( __('<a href="%s">Click here</a> to enter and activate your new license key.', 'memberpress'), $drm_info['activation_link'] ); ?></li>
       <li><?php _e( 'That’s it!', 'memberpress' ); ?></li>
     </ul>
   <?php else: ?>
     <ul class="mepr-drm-action-items">
       <li><?php echo sprintf( __( 'Grab your key from your <a target="_blank" href="%s">Account Page</a>.', 'memberpress' ), $drm_info['account_link'] ); ?></li>
       <li><?php echo sprintf( __('<a href="%s">Click here</a> to enter and activate it.', 'memberpress'), $drm_info['activation_link'] ); ?></li>
       <li><?php _e( 'That’s it!', 'memberpress' ); ?></li>
     </ul>
 <?php endif; ?>
</div>