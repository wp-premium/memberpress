<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprTaxJarIntegration {

  public static $api_key, $endpoint_base, $tax_taxjar_enabled, $sandbox_enabled, $calculate_taxes;

  public function __construct() {

    add_action( 'mepr_tax_rate_options', array( $this, 'options' ) );
    add_action( 'mepr-process-options', array( $this, 'store_options' ) );

    self::$sandbox_enabled = (bool) get_option( 'mepr_tax_taxjar_enable_sandbox' );
    self::$endpoint_base = self::$sandbox_enabled ? 'https://api.sandbox.taxjar.com/v2/' : 'https://api.taxjar.com/v2/';

    $livemode = self::$sandbox_enabled ? 'sandbox' : 'live';
    self::$api_key = sanitize_text_field( get_option( "mepr_tax_taxjar_api_key_{$livemode}" ) );

    self::$calculate_taxes = get_option( 'mepr_calculate_taxes' );
    self::$tax_taxjar_enabled = get_option( 'mepr_tax_taxjar_enabled' );

    // Filter for tax calculation
    if ( self::$calculate_taxes && self::$tax_taxjar_enabled && self::$api_key ) {
      add_filter( 'mepr_found_tax_rate', array( $this,'find_rate' ), 10, 6 );
    }

    // Push order/refund data when TaxJar is enabled
    if ( self::$tax_taxjar_enabled && self::$api_key ) {
      add_action( 'mepr-event-transaction-completed', array( $this, 'send_order' ) );
      add_action( 'mepr-event-transaction-refunded', array( $this, 'send_refund' ) );
    }

    // Admin notice when business state is not 2-character code
    if ( self::$calculate_taxes && self::$tax_taxjar_enabled && 2 !== strlen( get_option( 'mepr_biz_state' ) ) ) {
      add_action( 'mepr_before_options_form', array( $this, 'edit_business_state_notice' ) );
    }
  }

  /**
   * Sends a POST request to TaxJar API at the given endpoint (considers whether sandbox is enabled)
   * For available endpoints, see https://developers.taxjar.com/api/reference/
   *
   * @param string  $endpoint   API endpoint
   * @param array   $args       Request args
   *
   * @return mixed  Response from API if successful, or WP_Error
   */
  public static function request( $endpoint, $args ) {

    $response = wp_remote_post( self::$endpoint_base . $endpoint, array(
      'headers' => array(
        'Authorization' => 'Bearer ' . self::$api_key,
        'Content-Type' => 'application/json'
      ),
      'body' => json_encode( $args )
    ) );

    // Log any error
    if ( is_wp_error( $response ) ) {
      MeprUtils::debug_log( print_r( $response, true ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Alert admin about bad requests related to from_* parameters
    if ( isset( $body['status'] ) && 400 === $body['status'] && false !== strpos( $body['detail'], 'from_' ) ) {
      $message = __( 'The TaxJar API returned the following error after a recent request: ', 'memberpress' );
      $message .= sprintf( '<pre>%s</pre>', $body['detail'] );
      $message .= __( 'Please resolve the issue to continue using the TaxJar API successfully, or contact MemberPress for support.', 'memberpress' );
      MeprUtils::wp_mail_to_admin(
        __( 'TaxJar API Error: Bad Request', 'memberpress' ),
        $message,
        array(
          'Content-type: text/html'
        )
      );
    }

    return $response;
  }

  public function edit_business_state_notice() { ?>

    <div class="notice notice-warning" style="padding: 10px;">
      <?php printf( __( 'You must use a valid 2-character state code as your %1$sBusiness Address%2$s state field for TaxJar to work properly.', 'memberpress' ), '<a href="' . admin_url( 'admin.php?page=memberpress-options#mepr-info' ) . '" onclick="mpActivateInfoTab()">', '</a>' ); ?>
    </div>
    <script>
      function mpActivateInfoTab() {
        var navLink = document.getElementById('info');
        var bizStateInput = document.querySelector('input[name=mepr_biz_state]');
        navLink.click();
        bizStateInput.select();
        bizStateInput.scrollIntoView();
      }
    </script>

    <?php
  }

  /**
   * Render the options on the MP settings page (Taxes section)
   *
   * @return void
   */
  public function options() {
    $tax_taxjar_enabled = isset( $_POST['mepr_tax_taxjar_enabled'] ) && ! empty( $_POST['mepr_tax_taxjar_enabled'] ) ? true : get_option( 'mepr_tax_taxjar_enabled' );
    MeprView::render('/admin/taxes/taxjar_options', get_defined_vars());
  }

  /**
   * Save the settings to the database
   *
   * @return void
   */
  public function store_options() {
    update_option( 'mepr_tax_taxjar_enabled', isset( $_POST['mepr_tax_taxjar_enabled'] ) );
    update_option( 'mepr_tax_taxjar_api_key_live', isset( $_POST['mepr_tax_taxjar_api_key_live'] ) ? sanitize_text_field( trim($_POST['mepr_tax_taxjar_api_key_live']) ) : '' );
    update_option( 'mepr_tax_taxjar_api_key_sandbox', isset( $_POST['mepr_tax_taxjar_api_key_sandbox'] ) ? sanitize_text_field( trim($_POST['mepr_tax_taxjar_api_key_sandbox']) ) : '' );
    update_option( 'mepr_tax_taxjar_enable_sandbox', isset( $_POST['mepr_tax_taxjar_enable_sandbox'] ) ? 1 : '' );
  }

  /**
   * Determine the tax rate for the customer
   *
   * @param string   $tax_rate
   * @param string   $country
   * @param string   $state
   * @param string   $postcode
   * @param string   $city
   * @param string   $street
   *
   * @return object
   */
  public function find_rate( $tax_rate, $country, $state, $postcode, $city, $street ) {

    // Zero out tax if user provided a valid VAT #
    if ( ! empty( $_POST['mepr_vat_number'] ) ) {
      $vat_ctrl = new MeprVatTaxCtrl;
      if ( $vat_ctrl->vat_number_is_valid( sanitize_text_field( $_POST['mepr_vat_number'] ), sanitize_text_field( $_POST['mepr-address-country'] ) ) ) {
        $tax_rate->tax_rate = 0;
        return $tax_rate;
      }
    }

    // For available and required parameters, see https://developers.taxjar.com/api/reference/?shell#post-calculate-sales-tax-for-an-order
    $args = MeprHooks::apply_filters( 'mepr_taxjar_api_find_rate_args', array(
      'to_country' => sanitize_text_field( $country ),
      'to_state' => sanitize_text_field( $state ),
      'to_city' => sanitize_text_field( $city ),
      'to_street' => sanitize_text_field( $street ),
      'to_zip' => sanitize_text_field( $postcode ),
      'from_country' => sanitize_text_field( get_option( 'mepr_biz_country' ) ),
      'from_zip' => sanitize_text_field( get_option( 'mepr_biz_postcode' ) ),
      'from_state' => sanitize_text_field( get_option( 'mepr_biz_state' ) ), // This has to be two-letter ISO state code
      'from_city' => sanitize_text_field( get_option( 'mepr_biz_city' ) ),
      'from_street' => sanitize_text_field( get_option( 'mepr_biz_address1' ) ),
      'shipping' => 0.00,
      'amount' => 1.00
    ), $tax_rate );

    // Hit the TaxJar API taxes endpoint for fetching the tax rate
    $response = self::request( 'taxes', $args );

    if ( ! is_wp_error( $response ) ) {

      $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

      // Check for bad requests
      if ( isset( $response_body['error'] ) ) {
        MeprUtils::debug_log( print_r( $response_body, true ) );
        return $tax_rate;
      }

      // Get the rate
      if ( isset( $response_body['tax']['rate'] ) ) {
        $tax_rate->tax_rate = $response_body['tax']['rate'] * 100; // MP expects a percent
        $tax_rate->tax_desc = __( 'tax', 'memberpress' );
      }
    }

    return $tax_rate;
  }

  /**
   * Send purchase data to TaxJar
   *
   * @param object  $event
   *
   * @return void
   */
  public function send_order( $event ) {

    $transaction = $event->get_data();
    $user = $transaction->user();
    $should_send = isset( $transaction->tax_amount ) && $transaction->tax_amount > 0.00;

    // Only push to TaxJar if transaction has tax
    if ( apply_filters( 'mepr_taxjar_should_send_txn', $should_send, $event ) ) {

      // For available and required parameters, see https://developers.taxjar.com/api/reference/#post-create-an-order-transaction
      $args = MeprHooks::apply_filters( 'mepr_taxjar_api_create_order_args', array(
        'to_country' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-country', true ) ),
        'to_state' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-state', true ) ),
        'to_city' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-city', true ) ),
        'to_street' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-one', true ) ),
        'to_zip' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-zip', true ) ),
        'from_country' => sanitize_text_field( get_option( 'mepr_biz_country' ) ),
        'from_zip' => sanitize_text_field( get_option( 'mepr_biz_postcode' ) ),
        'from_state' => sanitize_text_field( get_option( 'mepr_biz_state' ) ), // This has to be two-letter ISO state code
        'from_city' => sanitize_text_field( get_option( 'mepr_biz_city' ) ),
        'from_street' => sanitize_text_field( get_option( 'mepr_biz_address1' ) ),
        'shipping' => 0.00,
        'amount' => MeprUtils::format_float( $transaction->amount, 2 ),
        'sales_tax' => MeprUtils::format_float( $transaction->tax_amount, 3 ),
        'transaction_id' => $transaction->trans_num,
        'transaction_date' => $transaction->created_at
      ), $transaction );

      // Send request
      $response = self::request( 'transactions/orders', $args );

      if ( ! is_wp_error( $response ) ) {

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check for bad requests
        if ( isset( $response_body['error'] ) ) {
          MeprUtils::debug_log( print_r( $response_body, true ) );
          return;
        }

        // $response_body['order]

        // Order created
      }
    }
  }

  /**
  * Send refund data to TaxJar
  *
  * @param object  $event
  *
  * @return void
  */
  public function send_refund( $event ) {

    $transaction = $event->get_data();
    $user = $transaction->user();
    $should_send = isset( $transaction->tax_amount ) && $transaction->tax_amount > 0.00;

    // Only push to TaxJar if transaction has tax
    if ( apply_filters( 'mepr_taxjar_should_refund_txn', $should_send, $event ) ) {

      // For available and required parameters, see https://developers.taxjar.com/api/reference/#post-create-a-refund-transaction
      $args = MeprHooks::apply_filters( 'mepr_taxjar_api_create_refund_args', array(
        'to_country' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-country', true ) ),
        'to_state' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-state', true ) ),
        'to_city' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-city', true ) ),
        'to_street' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-one', true ) ),
        'to_zip' => sanitize_text_field( get_user_meta( $user->ID, 'mepr-address-zip', true ) ),
        'from_country' => sanitize_text_field( get_option( 'mepr_biz_country' ) ),
        'from_zip' => sanitize_text_field( get_option( 'mepr_biz_postcode' ) ),
        'from_state' => sanitize_text_field( get_option( 'mepr_biz_state' ) ),
        'from_city' => sanitize_text_field( get_option( 'mepr_biz_city' ) ),
        'from_street' => sanitize_text_field( get_option( 'mepr_biz_address1' ) ),
        'shipping' => 0.00,
        'amount' => MeprUtils::format_float( $transaction->amount, 2 ),
        'sales_tax' => MeprUtils::format_float( $transaction->tax_amount, 3 ),
        'transaction_id' => $transaction->trans_num,
        'transaction_reference_id' => $transaction->trans_num,
        'transaction_date' => $transaction->created_at
      ), $transaction );

      // Send request
      $response = self::request( 'transactions/refunds', $args );

      if ( ! is_wp_error( $response ) ) {

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check for bad requests
        if ( isset( $response_body['error'] ) ) {
          MeprUtils::debug_log( print_r( $response_body, true ) );
          return;
        }

        // $response_body['refund]

        // Refund created
      }
    }
  }
}

new MeprTaxJarIntegration;