<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

$ajax_url           = add_query_arg( array(
  'action' => 'mepr_paypal_commerce_create_smart_button',
),
  admin_url( 'admin-ajax.php' )
);
$thankyou_page_args = [
  'membership_id' => $membership_id,
  'method-id' => $payment_method->id,
];
$success_url = $payment_method->notify_url( 'return' );
$webhook_url = $payment_method->notify_url( 'webhook' );
$is_one_time = $product->is_one_time_payment();
$smart_payment_on = $payment_method->settings->enable_smart_button == 'on';
?>

<div class="mp-form-row">
  <div class="mp-form-label">
    <label><?php _ex( $payment_method->settings->desc, 'ui', 'memberpress' ); ?></label>
    <div role="alert" class="mepr-paypal-card-errors"></div>
  </div>
</div>

<?php if ( $smart_payment_on ) { ?>
<div class="mepr-paypal-button-container"
     style="display: inline-block"
     data-method-id="<?php esc_attr_e( $payment_method->id, 'memberpress' ); ?>"
     data-ajax-url="<?php esc_attr_e( $ajax_url, 'memberpress' ); ?>"
     data-webhook-url="<?php esc_attr_e( $webhook_url, 'memberpress' ); ?>"
     data-success-url="<?php esc_attr_e( $success_url, 'memberpress' ); ?>"
     data-is-one-time="<?php esc_attr_e( $is_one_time, 'memberpress' ); ?>"
     id="paypal-button-container-<?php esc_attr_e( $payment_method->id, 'memberpress' ); ?>"></div>

<noscript><p
      class="mepr_nojs"><?php _e( 'Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress' ); ?></p>
</noscript>
<?php } ?>
