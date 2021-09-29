<?php

$classes = '';

if ( ! isset( $_GET['display-keys'] ) && ! isset( $_COOKIE['mepr_stripe_display_keys'] ) && ! defined( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
  $classes = 'class="mepr-hidden"';
}
?>

<?php if ( MeprStripeGateway::stripe_connect_status( $id ) == 'connected') : ?>
  <?php
    $refresh_url = add_query_arg( array( 'action' => 'mepr_stripe_connect_refresh', 'method-id' => $id, '_wpnonce' => wp_create_nonce('stripe-refresh') ), admin_url('admin-ajax.php') );
    $disconnect_url = add_query_arg( array( 'action' => 'mepr_stripe_connect_disconnect', 'method-id' => $id, '_wpnonce' => wp_create_nonce('stripe-disconnect') ), admin_url('admin-ajax.php') );
    $disconnect_confirm_msg = __( 'Disconnecting from this Stripe Account will block webhooks from being processed, and prevent MemberPress subscriptions associated with it from working.', 'memberpress' );
  ?>
  <div id="stripe-connected-actions" class="mepr-payment-option-prompt connected">
    <?php if ( empty( $service_account_name ) ): ?>
      <?php _e( 'Connected to Stripe', 'memberpress' ); ?>
    <?php else: ?>
      <?php printf( __( 'Connected to: %1$s %2$s %3$s', 'memberpress' ), '<strong>', $service_account_name, '</strong>' ); ?>
    <?php endif; ?>
    &nbsp;
    <span <?php echo $classes; ?>>
    <a href="<?php echo $refresh_url; ?>"
       class="stripe-btn  mepr_stripe_refresh_button button-secondary"><?php _e( 'Refresh Stripe Credentials', 'memberpress' ); ?></a></span>
    <a href="<?php echo $disconnect_url; ?>" class=" stripe-btn  mepr_stripe_disconnect_button button-secondary"
       data-disconnect-msg="<?php echo $disconnect_confirm_msg; ?>">
      <?php _e( 'Disconnect', 'memberpress' ); ?>
    </a>
  </div>
<?php elseif ( ! MeprStripeGateway::is_stripe_connect( $id ) && MeprStripeGateway::keys_are_set( $id ) ) : ?>
  <div id="mepr-stripe-connect-migrate-prompt" class="mepr-payment-option-prompt">
    <div><img width="200px" src="<?php echo MEPR_IMAGES_URL . '/Stripe_with_Tagline.svg'; ?>" alt="Stripe logo"/></div>
    <p class="mepr-stripe-setting-promo"><b><?php _e( "Connect with the world's most powerful and easy to use Payment Gateway", 'memberpress' ); ?></b></p>
    <table class="stripe-feature-list" width="500px">
      <tr>
        <td>
          <ul class="stripe-features">
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept all Major Credit Cards", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Flexible subscriptions and billing terms", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept SEPA", 'memberpress' ); ?></li>
          </ul>
        </td>
        <td>
          <ul class="stripe-features">
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Apple Pay", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Google Wallet", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept iDeal", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Fraud prevention tools", 'memberpress' ); ?></li>
          </ul>
        </td>
      </tr>
    </table>
    <p>
      <a href="<?php echo $stripe_connect_url; ?>">
        <img src="<?php echo MEPR_IMAGES_URL . '/stripe-connect.png'; ?>" width="200" alt="<?php _e( '"Connect with Stripe" button', 'memberpress' ); ?>">
      </a>
    </p>
  </div>
<?php elseif ( MeprStripeGateway::stripe_connect_status( $id ) == 'disconnected' ) : ?>
  <div id="mepr-stripe-connect-migrate-prompt" class="mepr-payment-option-prompt">
    <p><strong><?php _e( 'Re-Connect to Stripe', 'memberpress' ); ?></strong></p>
    <p><?php _e( 'This Payment Method has been disconnected so it may stop working for new and recurring payments at any time. To prevent this, re-connect your Stripe account by clicking the "Connect with Stripe" button below.', 'memberpress' ); ?></p>
    <p>
      <a href="<?php echo $stripe_connect_url; ?>">
        <img src="<?php echo MEPR_IMAGES_URL . '/stripe-connect.png'; ?>" width="200" alt="<?php _e( '"Connect with Stripe" button', 'memberpress' ); ?>">
      </a>
    </p>
  </div>
<?php /***** THIS IS A NEW PAYMENT METHOD *****/ ?>
<?php elseif ( !MeprStripeGateway::keys_are_set( $id ) ) : ?>
  <div id="mepr-stripe-connect-migrate-prompt" class="mepr-payment-option-prompt">
    <div><img src="<?php echo MEPR_IMAGES_URL . '/Stripe_with_Tagline.svg'; ?>" alt="Stripe logo"/></div>
    <table class="stripe-feature-list" width="500px">
      <tr>
        <td>
          <ul class="stripe-features">
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept all Major Credit Cards", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Flexible subscriptions and billing terms", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept SEPA", 'memberpress' ); ?></li>
          </ul>
        </td>
        <td>
          <ul class="stripe-features">
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Apple Pay", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Google Wallet", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept iDeal", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Fraud prevention tools", 'memberpress' ); ?></li>
          </ul>
        </td>
      </tr>
    </table>
    <a href="" data-id="<?php echo $id; ?>" data-href="<?php echo $stripe_connect_url; ?>" data-nonce="<?php echo wp_create_nonce( "new-stripe-connect" ); ?>" class="mepr-stripe-connect-new">
        <img src="<?php echo MEPR_IMAGES_URL . '/stripe-connect.png'; ?>" width="200" alt="<?php _e( '"Connect with Stripe" button', 'memberpress' ); ?>">
      </a>
    </p>
  </div>
<?php else : ?>
  <div id="mepr-stripe-connect-migrate-prompt" class="mepr-payment-option-prompt">
    <div><img src="<?php echo MEPR_IMAGES_URL . '/Stripe_with_Tagline.svg'; ?>" alt="Stripe logo"/></div>
    <p class="mepr-stripe-setting-promo"><b><?php _e( "Connect with the world's most powerful and easy to use Payment Gateway", 'memberpress' ); ?></b></p>
    <table class="stripe-feature-list" width="500px">
      <tr>
        <td>
          <ul class="stripe-features">
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept all Major Credit Cards", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Flexible subscriptions and billing terms", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept SEPA", 'memberpress' ); ?></li>
          </ul>
        </td>
        <td>
          <ul class="stripe-features">
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Apple Pay", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept Google Wallet", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Accept iDeal", 'memberpress' ); ?></li>
            <li><img src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Fraud prevention tools", 'memberpress' ); ?></li>
          </ul>
        </td>
      </tr>
    </table>
      <a href="<?php echo $stripe_connect_url; ?>">
        <img src="<?php echo MEPR_IMAGES_URL . '/stripe-connect.png'; ?>" width="200" alt="<?php _e( '"Connect with Stripe" button', 'memberpress' ); ?>">
      </a>
    </p>
  </div>
<?php endif; ?>

