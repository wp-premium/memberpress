<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
$check_mark_svg = file_get_contents(MEPR_IMAGES_PATH . '/Check_Mark.svg');
?>

<div class="wrap">
  <div class="mepr-sister-plugin mepr-sister-plugin-easy-affiliate" data-config="<?php echo esc_attr(wp_json_encode($plugin)); ?>">

    <div class="mepr-sister-plugin-image">
      <?php echo file_get_contents(MEPR_IMAGES_PATH . '/memberpress-easy-affiliate.svg'); ?>
    </div>

    <div class="mepr-sister-plugin-title">
      <?php esc_html_e('The Best Affiliate Program Plugin for WordPress', 'memberpress'); ?>
    </div>

    <div class="mepr-sister-plugin-description">
      <?php esc_html_e('Easy Affiliate helps you create a completely self-hosted affiliate program for your MemberPress site or ecommerce store within minutes. Start growing your sales with the power of referral marketing.', 'memberpress'); ?>
    </div>

    <div class="mepr-sister-plugin-info mepr-clearfix">
      <div class="mepr-sister-plugin-info-image">
        <div>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/easy-affiliate-screens.png'); ?>" alt="">
        </div>
      </div>
      <div class="mepr-sister-plugin-info-features">
        <ul>
          <li><?php echo $check_mark_svg; ?><?php esc_html_e('Integrates with WordPress ecommerce and email marketing solutions', 'memberpress'); ?></li>
          <li><?php echo $check_mark_svg; ?><?php esc_html_e('Pre-styled, theme-neutral Pro Dashboard', 'memberpress'); ?></li>
          <li><?php echo $check_mark_svg; ?><?php esc_html_e('Tracks commissions without using third-party cookies', 'memberpress'); ?></li>
          <li><?php echo $check_mark_svg; ?><?php esc_html_e('Real-Time Reports and 1-click affiliate payouts', 'memberpress'); ?></li>
          <li><?php echo $check_mark_svg; ?><?php esc_html_e('Detects affiliate fraud before you pay out', 'memberpress'); ?></li>
          <li><?php echo $check_mark_svg; ?><?php esc_html_e('Minus the fees and restrictions of other affiliate program solutions', 'memberpress'); ?></li>
        </ul>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo !$plugin['active'] && !$plugin['installed'] && empty($plugin['url']) ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">1</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Get Easy Affiliate', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('Go to EasyAffiliate.com to get started on your MemberPress affiliate program.', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <a href="https://memberpress.com/memberpress_plugin/affiliates_menu/easy_affiliate_pricing" class="button button-primary button-hero<?php echo !$plugin['active'] && !$plugin['installed'] && empty($plugin['url']) ? '' : ' disabled'; ?>" target="_blank"><?php esc_html_e('Get Easy Affiliate', 'memberpress'); ?></a>
        </div>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo !$plugin['active'] ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">2</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Install and Activate Easy Affiliate', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('Install Easy Affiliate from EasyAffiliate.com', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($plugin['active']) : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Installed & Active', 'memberpress'); ?></button>
          <?php elseif($plugin['installed']) : ?>
            <button type="button" class="button button-primary button-hero"><?php esc_html_e('Activate Easy Affiliate', 'memberpress'); ?></button>
          <?php elseif(!empty($plugin['url'])) : ?>
            <button type="button" class="button button-primary button-hero mepr-sister-plugin-auto-installer"><?php esc_html_e('Install & Activate', 'memberpress'); ?></button>
          <?php else : ?>
            <a href="<?php echo esc_url($installer_url); ?>" class="button button-primary button-hero"><?php esc_html_e('Install & Activate', 'memberpress'); ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo $plugin['active'] ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">3</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Setup Easy Affiliate', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('Easy Affiliate has an intuitive setup wizard to guide you through the setup process.', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($plugin['active']) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=easy-affiliate-onboarding')); ?>" class="button button-primary button-hero"><?php esc_html_e('Run Setup Wizard', 'memberpress'); ?></a>
          <?php else : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Run Setup Wizard', 'memberpress'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
