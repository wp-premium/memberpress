<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="wrap">
  <div class="mepr-sister-plugin mepr-sister-plugin-wp-mail-smtp" data-config="<?php echo esc_attr(wp_json_encode($plugin)); ?>">

    <div class="mepr-sister-plugin-image">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/memberpress-wp-mail-smtp.png'); ?>" width="800" height="216" alt="">
    </div>

    <div class="mepr-sister-plugin-title">
      <?php esc_html_e('Making Email Deliverability Easy for WordPress', 'memberpress'); ?>
    </div>

    <div class="mepr-sister-plugin-description">
      <?php esc_html_e('WP Mail SMTP allows you to easily set up WordPress to use a trusted provider to reliably send emails, including MemberPress notifications. Built by the same folks behind WPForms, which is a sister company of MemberPress.', 'memberpress'); ?>
    </div>

    <div class="mepr-sister-plugin-info mepr-clearfix">
      <div class="mepr-sister-plugin-info-image">
        <div>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/wp-mail-smtp-mailers.png'); ?>" alt="<?php esc_attr_e('WP Mail SMTP mailers', 'memberpress'); ?>">
        </div>
      </div>
      <div class="mepr-sister-plugin-info-features">
        <ul>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('Over 1,000,000 websites use WP Mail SMTP', 'memberpress'); ?></li>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('Send emails authenticated via trusted parties', 'memberpress'); ?></li>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('Transactional Mailers: SendinBlue, Mailgun, SendGrid, Amazon SES', 'memberpress'); ?></li>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('Web Mailers: Gmail, G Suite, Office 365, Outlook.com', 'memberpress'); ?></li>
        </ul>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo !$plugin['active'] ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">1</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Install and Activate WP Mail SMTP', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('Install WP Mail SMTP from the WordPress.org plugin repository.', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($plugin['active']) : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Installed & Active', 'memberpress'); ?></button>
          <?php elseif($plugin['installed']) : ?>
            <button type="button" class="button button-primary button-hero"><?php esc_html_e('Activate WP Mail SMTP', 'memberpress'); ?></button>
          <?php else : ?>
            <button type="button" class="button button-primary button-hero"><?php esc_html_e('Install WP Mail SMTP', 'memberpress'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo $plugin['active'] ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">2</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Setup WP Mail SMTP', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('Select and configure your mailer.', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($plugin['active']) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-smtp')); ?>" class="button button-primary button-hero"><?php esc_html_e('Start Setup', 'memberpress'); ?></a>
          <?php else : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Start Setup', 'memberpress'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
