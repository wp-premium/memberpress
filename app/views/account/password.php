<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mp_wrapper">
  <?php MeprView::render('/shared/errors', get_defined_vars()); ?>

  <form action="<?php echo parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>" class="mepr-newpassword-form mepr-form" method="post" novalidate>
    <input type="hidden" name="plugin" value="mepr" />
    <input type="hidden" name="action" value="updatepassword" />
    <?php wp_nonce_field( 'update_password', 'mepr_account_nonce' ); ?>

    <div class="mp-form-row mepr_new_password">
      <label for="mepr-new-password"><?php _ex('New Password', 'ui', 'memberpress'); ?></label>
      <div class="mp-hide-pw">
        <input type="password" name="mepr-new-password" id="mepr-new-password" class="mepr-form-input mepr-new-password" required />
        <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Show password', 'memberpress' ); ?>">
          <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
        </button>
      </div>
    </div>
    <div class="mp-form-row mepr_confirm_password">
      <label for="mepr-confirm-password"><?php _ex('Confirm New Password', 'ui', 'memberpress'); ?></label>
      <div class="mp-hide-pw">
        <input type="password" name="mepr-confirm-password" id="mepr-confirm-password" class="mepr-form-input mepr-new-password-confirm" required />
        <button type="button" class="button mp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Show password', 'memberpress' ); ?>">
          <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
        </button>
      </div>
    </div>
    <?php MeprHooks::do_action('mepr-account-after-password-fields', $mepr_current_user); ?>

    <div class="mepr_spacer">&nbsp;</div>

    <input type="submit" name="new-password-submit" value="<?php _ex('Update Password', 'ui', 'memberpress'); ?>" class="mepr-submit" />
    <?php _ex('or', 'ui', 'memberpress'); ?>
    <a href="<?php echo $mepr_options->account_page_url(); ?>"><?php _ex('Cancel', 'ui', 'memberpress'); ?></a>
    <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
    <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
  </form>

  <?php MeprHooks::do_action('mepr_account_password', $mepr_current_user); ?>
</div>
