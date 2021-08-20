<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-admin-addons" class="wrap">

  <h2><?php esc_html_e('MemberPress Add-ons', 'memberpress'); ?><a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-addons&refresh=true')); ?>" class="add-new-h2 mepr-addons-refresh"><?php esc_html_e('Refresh Add-ons', 'memberpress'); ?></a><input type="search" id="mepr-addons-search" placeholder="<?php esc_attr_e('Search add-ons', 'memberpress'); ?>"></h2>

  <p>
    <?php
      printf(
        // translators: %1$s: open link tag, %2$s: close link tag
        esc_html__('Improve your memberships with our premium add-ons. Missing an add-on that you think you should be able to see? Click the %1$sRefresh Add-ons%2$s button above.', 'memberpress'),
        sprintf('<a href="%s">', esc_url(admin_url('admin.php?page=memberpress-addons&refresh=true'))),
        '</a>'
      );
    ?>
  </p>

  <h4><?php esc_html_e('Available Add-ons', 'memberpress'); ?></h4>

  <?php if(!empty($addons)) : ?>

    <div id="mepr-addons-container">

      <div class="mepr-addons mepr-clearfix">

        <?php
          foreach($addons as $slug => $info) :
            $info = (object) $info;
            $status_label = '';
            $action_class = 'mepr-addon-action';

            $installed = isset($info->extra_info->directory) && is_dir(WP_PLUGIN_DIR . '/' . $info->extra_info->directory);
            $active = isset($info->extra_info->main_file) && is_plugin_active($info->extra_info->main_file);

            if($installed && $active) {
              $status = 'active';
              $status_label = esc_html__('Active', 'memberpress');
            } elseif(!$installed && $info->installable) {
              $status = 'download';
              $status_label = esc_html__('Not Installed', 'memberpress');
            } elseif($installed && !$active) {
              $status = 'inactive';
              $status_label = esc_html__('Inactive', 'memberpress');
            } else {
              $status = 'upgrade';
            }
          ?>
          <div class="mepr-addon mepr-addon-status-<?php echo esc_attr($status); ?>">
            <div class="mepr-addon-inner">

              <div class="mepr-addon-details">
                <img src="<?php echo esc_url($info->extra_info->cover_image); ?>" alt="<?php echo esc_attr($info->product_name); ?>">
                <h5 class="mepr-addon-name"><?php echo esc_html(isset($info->extra_info->list_title) ? $info->extra_info->list_title : $info->product_name); ?></h5>
                <p><?php echo $info->extra_info->description; ?></p>
              </div>

              <div class="mepr-addon-actions mepr-clearfix">

                <?php if($status != 'upgrade') : ?>

                  <div class="mepr-addon-status">
                    <strong>
                      <?php
                        printf(
                          // translators: %s: add-on status label
                          esc_html__('Status: %s', 'memberpress'),
                          sprintf(
                            '<span class="mepr-addon-status-label">%s</span>',
                            $status_label
                          )
                        );
                      ?>
                    </strong>
                  </div>

                <?php else :  ?>

                  <?php $action_class .= ' mepr-addon-action-upgrade'; ?>

                <?php endif; ?>

                <div class="<?php echo esc_attr($action_class); ?>">

                  <?php if($status == 'active') : ?>

                    <button type="button" data-plugin="<?php echo esc_attr($info->extra_info->main_file); ?>" data-type="add-on"><i class="mp-icon mp-icon-toggle-on"></i><?php esc_html_e('Deactivate', 'memberpress'); ?></button>

                  <?php elseif($status == 'inactive') : ?>

                    <button type="button" data-plugin="<?php echo esc_attr($info->extra_info->main_file); ?>" data-type="add-on"><i class="mp-icon mp-icon-toggle-on mp-flip-horizontal"></i><?php esc_html_e('Activate', 'memberpress'); ?></button>

                  <?php elseif($status == 'download') : ?>

                    <button type="button" data-plugin="<?php echo esc_attr($info->url); ?>" data-type="add-on"><i class="mp-icon mp-icon-download-cloud"></i><?php esc_html_e('Install Add-on', 'memberpress'); ?></button>

                  <?php else : ?>

                    <a href="https://memberpress.com/sign-in/?redirect_to=%2Fplans%2Fpricing" target="_blank"><?php esc_html_e('Upgrade Now', 'memberpress'); ?></a>

                  <?php endif; ?>

                </div>

              </div>
            </div>
          </div>

        <?php endforeach; ?>

      </div>
    </div>

  <?php else : ?>

    <h3><?php esc_html_e('There were no Add-ons found for your license or lack thereof...', 'memberpress'); ?></h3>

  <?php endif; ?>

</div>
