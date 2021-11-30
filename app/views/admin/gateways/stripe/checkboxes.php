<?php

$classes = '';

if ( ! isset( $_GET['display-keys'] ) && ! isset( $_COOKIE['mepr_stripe_display_keys'] ) && ! defined( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
  $classes = 'class="mepr-hidden"';
}

?>

<table class="form-table">
  <tbody>
  <?php if ( MeprStripeGateway::is_stripe_connect( $id ) || MeprStripeGateway::keys_are_set( $id ) ) { ?>
    <tr valign="top">
      <th scope="row"><label for="<?php echo $test_mode_str; ?>"><?php _e('Test Mode', 'memberpress'); ?></label></th>
      <td><input class="mepr-stripe-testmode" data-integration="<?php echo $id; ?>" type="checkbox" name="<?php echo $test_mode_str; ?>"<?php echo checked($test_mode); ?> <?php disabled((defined('MEMBERPRESS_STRIPE_TESTING') && MEMBERPRESS_STRIPE_TESTING == true));?> /></td>
      </td>
    </tr>
    <tr valign="top">
      <th scope="row"><label for="<?php echo $stripe_wallet_enabled_str ?>"><?php _e('Enable Google Pay and Apple Pay', 'memberpress'); ?></label></th>
      <td>
          <input class="mepr-stripe-no-wallet" data-integration="<?php echo $id; ?>" type="checkbox" name="<?php echo $stripe_wallet_enabled_str; ?>"<?php echo checked($stripe_wallet_enabled); ?> />
      </td>
      </td>
    </tr>
  <?php } ?>
    <tr valign="top" <?php echo MeprStripeGateway::is_stripe_connect( $id ) || empty( $live_public_key ) ? 'style="display:none;"' : ''; ?>>
      <th scope="row"><label for="<?php echo $force_ssl_str; ?>"><?php _e('Force SSL', 'memberpress'); ?></label></th>
      <td><input type="checkbox" name="<?php echo $force_ssl_str; ?>"<?php echo checked($force_ssl); ?> /></td>
    </tr>
    <tr valign="top" <?php echo MeprStripeGateway::is_stripe_connect( $id ) || empty( $live_public_key ) ? 'style="display:none;"' : ''; ?>>
      <th scope="row"><label for="<?php echo $debug_str; ?>"><?php _e('Send Debug Emails', 'memberpress'); ?></label></th>
      <td><input type="checkbox" name="<?php echo $debug_str; ?>"<?php echo checked($debug); ?> /></td>
    </tr>
    <tr valign="top" <?php echo $classes; ?>>
      <th scope="row"><label><?php _e('Stripe Webhook URL:', 'memberpress'); ?></label></th>
      <td>
        <?php MeprAppHelper::clipboard_input($whk_url); ?>
      </td>
    </tr>
  </tbody>
</table>
