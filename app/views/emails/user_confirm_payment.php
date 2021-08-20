<?php if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); } ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php echo esc_html_x('Confirm subscription payment', 'ui', 'memberpress'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;">
    <?php
      echo esc_html(sprintf(
        // translators: %1$s: subscription ID, %2$s: blog name
        _x('%1$s &ndash; %2$s', 'ui', 'memberpress'),
        $subscr_num,
        $blogname
      ));
    ?>
  </h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div class="section" style="display: block; margin-bottom: 24px;">
    <?php
      echo esc_html(sprintf(
        // translators: %s: blog name
        _x('We tried to process a payment for your subscription to %s, but your bank requested authentication for this transaction. Please confirm the payment by clicking on the following link:', 'ui', 'memberpress'),
        $blogname
      ));
    ?>
  </div>
  <div class="section" style="display: block; margin-bottom: 24px;">
    <a href="<?php echo esc_url($hosted_invoice_url); ?>"><?php echo esc_html($hosted_invoice_url); ?></a>
  </div>
</div>
