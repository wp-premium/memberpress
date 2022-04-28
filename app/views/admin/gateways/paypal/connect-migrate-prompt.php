<?php
$classes = '';
/** @var MeprPayPalCommerceGateway $pm */
?>
<div id="mepr-paypal-connect-migrate-prompt" class="mepr-payment-option-prompt">
  <input type="hidden" name="<?php echo $test_client_id_str; ?>" value="<?php echo esc_attr($settings->test_client_id); ?>"/>
  <input type="hidden" name="<?php echo $live_client_id_str; ?>" value="<?php echo esc_attr($settings->live_client_id); ?>"/>
  <input type="hidden" name="<?php echo $test_client_secret_str; ?>" value="<?php echo esc_attr($settings->test_client_secret); ?>"/>
  <input type="hidden" name="<?php echo $live_client_secret_str; ?>" value="<?php echo esc_attr($settings->live_client_secret); ?>"/>
  <input type="hidden" name="<?php echo $test_webhook_id_str; ?>" value="<?php echo esc_attr($settings->test_webhook_id); ?>"/>
  <input type="hidden" name="<?php echo $live_webhook_id_str; ?>" value="<?php echo esc_attr($settings->live_webhook_id); ?>"/>
  <input type="hidden" name="<?php echo $test_merchant_id_str; ?>" value="<?php echo esc_attr($settings->test_merchant_id); ?>"/>
  <input type="hidden" name="<?php echo $live_merchant_id_str; ?>" value="<?php echo esc_attr($settings->live_merchant_id); ?>"/>
  <input type="hidden" name="<?php echo $test_email_confirmed_str; ?>" value="<?php echo esc_attr(intval($settings->test_email_confirmed)); ?>"/>
  <input type="hidden" name="<?php echo $live_email_confirmed_str; ?>" value="<?php echo esc_attr(intval($settings->live_email_confirmed)); ?>"/>
  <div><img width="200px" src="<?php echo MEPR_IMAGES_URL . '/PayPal_with_Tagline.svg'; ?>" alt="PayPal logo"/>
  </div>
  <?php if ( $pm->is_paypal_connected() or $pm->is_paypal_connected_live() ) { ?>
    <?php if ( $pm->is_paypal_connected() ) { ?>
      <p class="mepr-paypal-setting-promo">
        <b><?php _e( "Connected to PayPal Commerce Platform - Sandbox mode", 'memberpress' ); ?></b>
        <?php if ( ! empty( $settings->test_merchant_id ) ) { ?>
        <br/>
        <span><?php echo esc_html( sprintf( __( 'PayPal Merchant ID: %s', 'memberpress' ), $settings->test_merchant_id ) ) ?></span>
        <?php } ?>
      </p>

      <?php if ( ! $pm->is_paypal_email_confirmed() ) { ?>
        <p class="mepr-paypal-setting-promo">
          <b><?php _e( "You need to confirm your email to accept payments", 'memberpress' ); ?></b>
          <button
              x-data="{
                verifyEmail() {
                  window.location.href = $el.getAttribute('data-verify-url')
                }
              }"
              type="button"
              data-verify-url="<?php echo esc_url_raw( admin_url( 'admin.php?mepr-paypal-commerce-confirm-email=1&sandbox=1&method-id=' . $payment_id . '&page=memberpress-options#mepr-integration' ) ); ?>"
              x-on:click="verifyEmail"><?php _e( "My email is verified", 'memberpress' ); ?></button>
        </p>
      <?php } ?>
    <?php } ?>

    <?php if ( $pm->is_paypal_connected_live() ) { ?>
      <p class="mepr-paypal-setting-promo">
        <b><?php _e( "Connected to PayPal Commerce Platform - Live mode", 'memberpress' ); ?></b>
        <?php if ( ! empty( $settings->live_merchant_id ) ) { ?>
          <br/>
          <span><?php echo esc_html( sprintf( __( 'PayPal Merchant ID: %s', 'memberpress' ), $settings->live_merchant_id ) ) ?></span>
        <?php } ?>
      </p>

      <?php if ( ! $pm->is_paypal_email_confirmed_live() ) { ?>
        <p class="mepr-paypal-setting-promo">
          <b><?php _e( "You need to confirm your email to accept payments", 'memberpress' ); ?></b>
          <button
              x-data="{
                verifyEmail() {
                  window.location.href = $el.getAttribute('data-verify-url')
                }
              }"
              x-on:click="verifyEmail"
              type="button"
              data-verify-url="<?php echo esc_url_raw( admin_url( 'admin.php?mepr-paypal-commerce-confirm-email=1&method-id=' . $payment_id . '&page=memberpress-options#mepr-integration' ) ); ?>"
          ><?php _e( "My email is verified", 'memberpress' ); ?></button>
        </p>
      <?php } ?>
    <?php } ?>
    <?php } else { ?>
    <p class="mepr-paypal-setting-promo">
      <b><?php _e( "Connect with the world's most powerful and easy to use Payment Gateway", 'memberpress' ); ?></b>
      <?php if ( ! empty( $settings->live_merchant_id ) ) { ?>
      <br/>
      <span><?php echo esc_html( sprintf( __( 'PayPal Merchant ID: %s', 'memberpress' ), $settings->live_merchant_id ) ) ?></span>
      <?php } ?>
    </p>
    <table class="paypal-feature-list" width="500px">
      <tr>
        <td>
          <ul class="paypal-features">
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Pay Securely", 'memberpress' ); ?>
            </li>
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Pay with PayPal", 'memberpress' ); ?>
            </li>
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Pay with PayPal Credit", 'memberpress' ); ?>
            </li>
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Global reach", 'memberpress' ); ?>
            </li>
          </ul>
        </td>
        <td>
          <ul class="paypal-features">
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Automatic configuration", 'memberpress' ); ?>
            </li>
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Recurring subscription billing", 'memberpress' ); ?>
            </li>
            <li>
              <img
                  src="<?php echo MEPR_IMAGES_URL; ?>/Check_Mark.svg"/><?php _e( "Non-recurring payments", 'memberpress' ); ?>
            </li>
          </ul>
        </td>
      </tr>
    </table>

    <?php if ( ! empty( $settings->test_auth_code ) || ! empty( $settings->live_auth_code ) ) { ?>
      <p><small style="color: red"><?php esc_html_e('If you refresh the page and see this message for more than 5 minutes, disconnect and try again.', 'memberpress'); ?></small></p>
      <button
          x-data="{
            confirmRollBack() {
              window.location.href = ajaxurl + '?action=mepr_paypal_connect_disconnect&retry=1&method-id=' + $el.getAttribute('data-method-id');
            }
          }"
          x-on:click="confirmRollBack"
          type="button"
          class="button mepr-paypal-onboarding-button"
          data-method-id="<?php echo esc_attr( $payment_id ); ?>"><?php _e( 'Disconnect and Retry', 'memberpress' ); ?></button>
    <?php } ?>
  <?php } ?>
  <?php if ($upgraded_from_standard) { ?>
    <p><b><?php echo esc_html(__('IPN URL: ', 'memberpress')); ?></b>
      <input type="text" onfocus="this.select();" onclick="this.select();" readonly="true" value="<?php echo esc_html($pm->notify_url('ipn')); ?>">
    </p>
  <?php } ?>
  <?php if ( empty( $settings->test_auth_code ) && empty( $settings->live_auth_code ) ) { ?>
  <div>
    <script>
        function onboardedCallback<?php echo esc_js( md5( $payment_id ) ); ?>(authCode, sharedId) {
            fetch('<?php echo esc_url_raw( $base_return_url ); ?>', {
                method: 'POST',
                headers: {
                    'content-type': 'application/json'
                },
                body: JSON.stringify({
                    authCode: authCode,
                    sharedId: sharedId,
                    payment_method_id: '<?php echo esc_attr( $payment_id ); ?>'
                })
            }).then(function (res) {
                if (!res.ok) {
                    alert("Something went wrong!");
                }
            });
        }

        function onboardedCallbackSandbox<?php echo esc_js( md5( $payment_id ) ); ?>(authCode, sharedId) {
            fetch('<?php echo esc_url_raw( $base_return_url_sandbox ); ?>', {
                method: 'POST',
                headers: {
                    'content-type': 'application/json'
                },
                body: JSON.stringify({
                    authCode: authCode,
                    sharedId: sharedId,
                    payment_method_id: '<?php echo esc_attr( $payment_id ); ?>'
                })
            }).then(function (res) {
                if (!res.ok) {
                    alert("Something went wrong!");
                }
            });
        }
    </script>
    <?php if ( ! $pm->is_paypal_connected_live() && ! $pm->is_paypal_connected() && isset( $memberpress_connect_url )) { ?>
      <a class="button button-primary"
         href="<?php echo $memberpress_connect_url; ?>"
      >
        <?php _e( 'Connect MemberPress', 'memberpress' ); ?>
      </a>
    <?php } ?>
    <?php if ( $pm->is_paypal_connected_live() ) {
      $disconnect_confirm_msg = __( 'Are you sure you want to disconnect? Your future renewing payments will not track properly...', 'memberpress' );
      ?>
      <button type="button"
              class="button mepr-paypal-onboarding-button"
              data-disconnect-confirm-msg="<?php echo esc_attr( $disconnect_confirm_msg ); ?>"
              data-method-id="<?php echo esc_attr( $payment_id ); ?>"
              data-mepr-disconnect-paypal="1"><?php _e( 'Disconnect', 'memberpress' ); ?></button>
    <?php } else {
      $connect_confirm_msg = __( 'Going live will stop your Sandbox connection. Any subscriptions on your site connected to Sandbox will no longer track their renewals. Are you sure you\'re ready to Go Live?', 'memberpress' );
      ?>
      <?php if ( ! isset( $memberpress_connect_url ) || empty( $memberpress_connect_url ) ) { ?>
        <span
            x-data="{
          loaded: false
          }"
        >
        <a
            x-init="jQuery(window).on('load',function () {
          loaded = true;
          });"
            x-show="loaded"
            target="_blank" class="tooltip button button-primary mepr-paypal-onboarding-button"
            data-paypal-onboard-complete="onboardedCallback<?php echo esc_js( md5( $payment_id ) ); ?>"
            title="<?php echo esc_attr( $connect_confirm_msg ); ?>"
            data-paypal-connect-live="true"
            data-save-url="<?php echo esc_url_raw( admin_url( 'admin.php?page=memberpress-options&paypal=1&method-id=' . $payment_id . '#mepr-integration' ) ); ?>"
            data-connect-confirm-msg="<?php echo esc_attr( $connect_confirm_msg ); ?>"
            href="<?php echo $paypal_connect_url; ?>&displayMode=embedded"
            data-paypal-button="true">
          <img class="mepr-pp-icon" src="<?php echo MEPR_IMAGES_URL . '/PayPal_Icon_For_Button.svg'; ?>"/>
          <?php _e( 'Connect Live', 'memberpress' ); ?>
      </a>
        <i x-show="!loaded" class="mp-icon-spinner"></i>
        </span>
          <?php } else { ?>
          <?php } ?>
    <?php } ?>
    <?php if ( $pm->is_paypal_connected() ) { ?>
      <?php
      $disconnect_confirm_msg = __( 'Are you sure you want to disconnect? Your future renewing payments will not track properly...', 'memberpress' );
      ?>
      <button type="button"
              class="button mepr-paypal-onboarding-button"
              data-paypal-sandbox="true"
              data-method-id="<?php echo esc_attr( $payment_id ); ?>"
              data-disconnect-confirm-msg="<?php echo esc_attr( $disconnect_confirm_msg ); ?>"
              data-mepr-disconnect-paypal="1"><?php _e( 'Disconnect', 'memberpress' ); ?></button>
    <?php } else { ?>
    <?php if ( ! isset( $memberpress_connect_url ) || empty( $memberpress_connect_url ) ) { ?>
      <a
          x-data="{
          loaded: false
          }"
          x-init="jQuery(window).on('load',function () {
          loaded = true;
          });"
          x-show="loaded"
          target="_blank" class="mepr-paypal-onboarding-button"
          data-paypal-sandbox="true"
          data-save-url="<?php echo esc_url_raw( admin_url( 'admin.php?page=memberpress-options&sandbox=1&paypal=1&method-id=' . $payment_id . '#mepr-integration' ) ); ?>"
          data-paypal-onboard-complete="onboardedCallbackSandbox<?php echo esc_js( md5( $payment_id ) ); ?>"
          href="<?php echo $paypal_connect_url_sandbox; ?>&displayMode=embedded"
          data-paypal-button="true">
      <?php _e( 'Connect Sandbox', 'memberpress' ); ?>
      </a>
    <?php } else { ?>
    <?php } ?>
    <?php } ?>
  </div>
  <?php } ?>
  <?php if ($pm->is_paypal_connected_live() || $pm->is_paypal_connected()) { ?>
    <small><?php esc_html_e('Enable Smart Payment Buttons', 'memberpress'); ?></small>&nbsp;<input type="checkbox" name="<?php echo esc_attr( $enable_smart_button_str ); ?>" <?php checked($enable_smart_button); ?>/>
  <?php } ?>
  <?php if ( $upgraded_from_standard && ! MeprPayPalCommerceGateway::has_method_with_connect_status( 'connected' ) && ! $pm->is_paypal_connected_live() && ! $pm->is_paypal_connected() ) { ?>
    <br>
    <button
        x-data="{
          confirmMessage: '<?php esc_html_e('Are you sure?', 'memberpress'); ?>',
          confirmRollBack() {
              if (window.confirm(this.confirmMessage)) {
                window.location.href = ajaxurl + '?action=mepr_paypal_connect_rollback&method-id=' + $el.getAttribute('data-method-id');
              }
          }
        }"
        x-on:click="confirmRollBack"
        type="button"
        data-method-id="<?php echo esc_attr( $payment_id ); ?>"
        class="button mepr-paypal-rollback-button"
    ><?php esc_html_e('Rollback to PayPal standard', 'memberpress'); ?></button>
  <?php } ?>
  <div class="mepr-paypal-save-option" style="display: none" x-data="{
  loading: false,
  confirmation(url) {
   $data.loading = true;
   setTimeout(function () {
       window.location.href = url;
    }, 2500);
    }
  }">
    <button data-save-url="<?php echo  esc_url_raw(admin_url( 'admin.php?page=memberpress-options&paypal=1&method-id=' . $payment_id . '#mepr-integration' )); ?>"  class="button button-primary" x-show="!loading" type="button" @click="confirmation($event.target.getAttribute('data-save-url'))"><?php esc_html_e('Save settings & Connect', 'memberpress'); ?></button>
    <i x-show="loading" class="mp-icon-spinner"></i><span x-show="loading" style="color: red"><?php esc_html_e('Processing', 'memberpress'); ?></span>
  </div>
</div>
