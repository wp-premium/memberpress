<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mp-form-row">
  <label><?php _e('First Name on Card', 'memberpress'); ?></label>
  <input type="text" name="mepr_first_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_first_name'])) ? $_POST['mepr_first_name'] : ''; ?>" />
</div>

<div class="mp-form-row">
  <label><?php _e('Last Name on Card', 'memberpress'); ?></label>
  <input type="text" name="mepr_last_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_last_name'])) ? $_POST['mepr_last_name'] : ''; ?>" />
</div>

<div class="mp-form-row">
  <div class="mp-form-label">
    <label><?php _e('Zip code for Card', 'memberpress'); ?></label>
  </div>
  <input type="text" name="mepr_zip_post_code" class="mepr-form-input" autocomplete="off" value="" required />
</div>

<?php MeprHooks::do_action('mepr-authorize-net-payment-form', $txn); ?>
