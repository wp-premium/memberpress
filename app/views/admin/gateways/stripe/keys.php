<?php

$classes = '';
$show_keys = false;

if ( ! isset( $_GET['display-keys'] ) && ! isset( $_COOKIE['mepr_stripe_display_keys'] ) && ! defined( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
  $classes = 'class="mepr-hidden"';
} else {
  $show_keys = true;
}

?>
<?php if ( MeprStripeGateway::stripe_connect_status( $id ) == 'connected'  || $show_keys == true) { ?>
<div class="stripe-checkout-method-select">
  <label class="mepr-stripe-method <?php echo $stripe_checkout_enabled ? '' : 'selected'; ?>">
    <div align="center" class="mepr-heading-section"><span class="stripe-title">stripe</span> Elements</div>
    <ul class="stripe-features">
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Credit Cards on site", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Recurring billing", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "SCA ready", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Apple Pay", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Google Wallet", 'memberpress' ); ?></li>
    </ul>
    <input type="radio" class="mepr-toggle-checkbox" data-box="mepr_stripe_checkout_<?php echo $id; ?>_box" name="<?php echo $stripe_checkout_enabled_str; ?>" <?php checked($stripe_checkout_enabled, false); ?> value="off"/>
  </label>
  <label class="mepr-stripe-method <?php echo $stripe_checkout_enabled ? 'selected' : ''; ?>">
    <div align="center" class="mepr-heading-section"><span class="stripe-title">stripe</span> Checkout</div>
    <ul class="stripe-features">
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Offsite secure hosted solution", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Credit Cards", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Apple Pay", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Google Wallet", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept SEPA", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept iDeal", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Recurring billing", 'memberpress' ); ?></li>
      <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "SCA ready", 'memberpress' ); ?></li>
    </ul>
    <input type="radio" class="mepr-toggle-checkbox" data-box="mepr_stripe_checkout_<?php echo $id; ?>_box" name="<?php echo $stripe_checkout_enabled_str; ?>" <?php checked($stripe_checkout_enabled,true); ?> value="on"/>
  </label>
</div>
<?php } ?>
<div <?php echo $classes; ?>>
  <table id="mepr-stripe-test-keys-<?php echo $id; ?>" class="form-table mepr-stripe-test-keys mepr-hidden">
    <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_public_key_str; ?>"><?php _e('Test Publishable Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $test_public_key_str; ?>" value="<?php echo $test_public_key; ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $test_secret_key_str; ?>"><?php _e('Test Secret Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $test_secret_key_str; ?>" value="<?php echo $test_secret_key; ?>" /></td>
      </tr>
    </tbody>
  </table>
  <table id="mepr-stripe-live-keys-<?php echo $id; ?>" class="form-table mepr-stripe-live-keys mepr-hidden">
    <tbody>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $live_public_key_str; ?>"><?php _e('Live Publishable Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $live_public_key_str; ?>" value="<?php echo $live_public_key; ?>" /></td>
      </tr>
      <tr valign="top">
        <th scope="row"><label for="<?php echo $live_secret_key_str; ?>"><?php _e('Live Secret Key*:', 'memberpress'); ?></label></th>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $live_secret_key_str; ?>" value="<?php echo $live_secret_key; ?>" /></td>
      </tr>
    </tbody>
  </table>
  <input class="mepr-stripe-connect-status" type="hidden" name="<?php echo $connect_status_string; ?>" value="<?php esc_attr_e( $connect_status, 'memberpress' ); ?>" />
  <input class="mepr-stripe-service-account-id" type="hidden" name="<?php echo $service_account_id_string; ?>" value="<?php esc_attr_e( $service_account_id, 'memberpress' ); ?>" />
  <input class="mepr-stripe-service-account-name" type="hidden" name="<?php echo $service_account_name_string; ?>" value="<?php esc_attr_e( $service_account_name, 'memberpress' ); ?>" />
</div>
