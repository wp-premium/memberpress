<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="error" style="padding: 10px;">
  <?php
    printf(
      // translators: %1$s: open b tag, %2$s: close b tag
      esc_html__('The %1$sUse Stripe Checkout (Beta)%2$s option is deprecated, please go to the Payments tab and disable it.', 'memberpress'),
      '<b>',
      '</b>'
    );
  ?>
</div>
