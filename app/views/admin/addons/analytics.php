<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="wrap">
  <div class="mepr-sister-plugin mepr-sister-plugin-monsterinsights" data-config="<?php echo esc_attr(wp_json_encode($plugin)); ?>">

    <div class="mepr-sister-plugin-image">
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/memberpress-monsterinsights.png'); ?>" width="800" height="216" alt="">
    </div>

    <div class="mepr-sister-plugin-title">
      <?php esc_html_e('The Best Google Analytics Plugin for WordPress', 'memberpress'); ?>
    </div>

    <div class="mepr-sister-plugin-description">
      <?php esc_html_e('MonsterInsights connects MemberPress to Google Analytics, providing a powerful integration with their eCommerce add-on. MonsterInsights is a sister company of MemberPress.', 'memberpress'); ?>
    </div>

    <div class="mepr-sister-plugin-info mepr-clearfix">
      <div class="mepr-sister-plugin-info-image">
        <div>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/monsterinsights-ecommerce-report.png'); ?>" alt="<?php esc_attr_e('MonsterInsights eCommerce report', 'memberpress'); ?>">
        </div>
      </div>
      <div class="mepr-sister-plugin-info-features">
        <ul>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('Track important eCommerce metrics', 'memberpress'); ?></li>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('View total revenue, conversion rate, average order value and more', 'memberpress'); ?></li>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('See your top products and top referral sources', 'memberpress'); ?></li>
          <li><i class="mp-icon mp-icon-right-big"></i><?php esc_html_e('Automatic integration with MemberPress', 'memberpress'); ?></li>
        </ul>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo $step == 1 ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">1</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Install and Activate MonsterInsights', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('Install MonsterInsights from the WordPress.org plugin repository.', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($plugin['active']) : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Installed & Active', 'memberpress'); ?></button>
          <?php elseif($plugin['installed']) : ?>
            <button type="button" class="button button-primary button-hero"><?php esc_html_e('Activate MonsterInsights', 'memberpress'); ?></button>
          <?php else : ?>
            <button type="button" class="button button-primary button-hero"><?php esc_html_e('Install MonsterInsights', 'memberpress'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo $step == 2 ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">2</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Setup MonsterInsights', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('MonsterInsights has an intuitive setup wizard to guide you through the setup process.', 'memberpress'); ?>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($step == 2) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=monsterinsights-onboarding')); ?>" class="button button-primary button-hero"><?php esc_html_e('Run Setup Wizard', 'memberpress'); ?></a>
          <?php else : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Run Setup Wizard', 'memberpress'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mepr-sister-plugin-step mepr-clearfix<?php echo $step == 3 ? ' mepr-sister-plugin-step-current' : ''; ?>">
      <div class="mepr-sister-plugin-step-left">
        <div class="mepr-sister-plugin-step-number">3</div>
      </div>
      <div class="mepr-sister-plugin-step-detail">
        <div class="mepr-sister-plugin-step-title"><?php esc_html_e('Get MemberPress Tracking', 'memberpress'); ?></div>
        <div class="mepr-sister-plugin-step-description">
          <?php esc_html_e('With eCommerce tracking, you can see revenue metrics, conversion rate and much more.', 'memberpress'); ?>
          <span class="mepr-sister-plugin-step-pro"><?php esc_html_e('(Requires Pro Subscription)', 'memberpress'); ?></span>
        </div>
        <div class="mepr-sister-plugin-step-button">
          <?php if($step == 3) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=monsterinsights_settings#/addons')); ?>" class="button button-primary button-hero"><?php esc_html_e('Activate Now', 'memberpress'); ?></a>
          <?php else : ?>
            <button type="button" class="button button-secondary button-hero" disabled><?php esc_html_e('Activate Now', 'memberpress'); ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
