<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="notice notice-info is-dismissible mepr-notice-dismiss-permanently" data-notice="get_started">
  <p>
    <?php
      printf(
        // translators: %1$s: open link tag, %2$s: close link tag, %3$s: open link tag, %4$s: close link tag, %5$s: open link tag, %6$s: close link tag
        esc_html__('Get started with MemberPress by %1$sadding a Payment Method%2$s, %3$sadding a Membership%4$s, then %5$sadding a Rule%6$s to protect your content.', 'memberpress'),
        sprintf(
          '<a href="%s"%s>',
          esc_url(admin_url('admin.php?page=memberpress-options#mepr-integration')),
          $has_payment_method ? ' class="mepr-strikethrough"' : ''
        ),
        '</a>',
        sprintf(
          '<a href="%s"%s>',
          esc_url(admin_url('post-new.php?post_type=memberpressproduct')),
          $has_product ? ' class="mepr-strikethrough"' : ''
        ),
        '</a>',
        sprintf(
          '<a href="%s"%s>',
          esc_url(admin_url('post-new.php?post_type=memberpressrule')),
          $has_rule ? ' class="mepr-strikethrough"' : ''
        ),
        '</a>'
      );
    ?>
  </p>
</div>
