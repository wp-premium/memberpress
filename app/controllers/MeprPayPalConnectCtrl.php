<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

class MeprPayPalConnectCtrl extends MeprBaseCtrl {
  const PAYPAL_BN_CODE = 'Memberpress_SP_PPCP';
  const PAYPAL_URL_LIVE = 'https://api-m.paypal.com';
  const PAYPAL_URL_SANDBOX = 'https://api-m.sandbox.paypal.com';

  public function load_hooks() {
    if ( ! defined( 'MEPR_PAYPAL_SERVICE_DOMAIN' ) ) {
      define( 'MEPR_PAYPAL_SERVICE_DOMAIN', 'paypal.memberpress.com' );
    }

    if ( ! defined( 'MEPR_PAYPAL_SERVICE_URL' ) ) {
      define( 'MEPR_PAYPAL_SERVICE_URL', 'https://' . MEPR_PAYPAL_SERVICE_DOMAIN );
    }

    //add_filter( 'site_status_tests', array( $this, 'add_site_health_test' ) );
    add_filter( 'http_request_timeout', function ( $seconds ) {
      return $seconds + 15;
    } );

    add_action( 'admin_init', [ $this, 'admin_init' ] );
    $this->add_ajax_endpoints();
  }

  public function admin_init() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'memberpress-options' ) {
      return;
    }

    if ( ! isset( $_GET['paypal'] ) || ! isset( $_GET['method-id'] ) ) {
      return;
    }

    if ( isset( $_GET['sandbox'] ) & ! empty( $_GET['sandbox'] ) ) {
      $sandbox = true;
    } else {
      $sandbox = false;
    }

    $methodId = filter_input(INPUT_GET, 'method-id');
    $mepr_options = MeprOptions::fetch();
    $integrations = $mepr_options->integrations;

    if ( ! isset( $integrations[ $methodId ] ) ) {
      $integrations[ $methodId ] = [
        'label'   => esc_html( __( 'PayPal', 'memberpress' ) ),
        'id'      => $methodId,
        'gateway' => 'MeprPayPalCommerceGateway',
        'saved'   => true,
      ];
      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
    }
  }

  /**
   * Add a site health test callback
   *
   * @param array $tests Array of tests to be run
   *
   * @return array
   */
  public function add_site_health_test( $tests ) {
    $tests['direct']['mepr_paypal_connect_test'] = array(
      'label' => __( 'MemberPress - PayPal Connect Security', 'memberpress' ),
      'test'  => array( $this, 'run_site_health_test' )
    );

    return $tests;
  }

  public function check_and_show_upgrade_notices() {
    $mepr_options = MeprOptions::fetch();
    $integrations = $mepr_options->integrations;

    if ( ! is_array( $integrations ) ) {
      return;
    }

    $has_old_paypal_integration = false;

    foreach ( $integrations as $integration ) {
      if ( isset( $integration['gateway'] ) && $integration['gateway'] === 'MeprPayPalStandardGateway' ) {
        $has_old_paypal_integration = true;
        break;
      }
    }

    if ($has_old_paypal_integration === false) {
      return;
    }

    $has_commerce_gateway = false;

    foreach ( $mepr_options->integrations as $integration ) {
      if ( isset( $integration['gateway'] ) && 'MeprPayPalCommerceGateway' === $integration['gateway'] ) {
        $has_commerce_gateway = true;
        break;
      }
    }

    if ( ! $has_commerce_gateway && ( ! isset( $_COOKIE['mepr_paypal_connect_upgrade_dismissed'] ) || false == $_COOKIE['mepr_paypal_connect_upgrade_dismissed'] ) ) {
      ?>
      <div class="notice notice-error mepr-notice is-dismissible" id="mepr_paypal_connect_upgrade_notice">
        <p>
        <p><span class="dashicons dashicons-warning mepr-warning-notice-icon"></span><strong class="mepr-warning-notice-title"><?php _e( 'MemberPress Security Notice', 'memberpress' ); ?></strong></p>
        <p><strong><?php _e( 'Your current PayPal payment connection is out of date and may become insecure. Please click the button below to upgrade your PayPal payment method now.', 'memberpress' ); ?></strong></p>
        <p><a href="<?php echo admin_url( 'admin.php?page=memberpress-options#mepr-integration' ); ?>" class="button button-primary"><?php _e('Upgrade PayPal Standard to Fix this Error Now', 'memberpress'); ?></a></p>
        </p>
        <?php wp_nonce_field( 'mepr_paypal_connect_upgrade_notice_dismiss', 'mepr_paypal_connect_upgrade_notice_dismiss' ); ?>
      </div>
      <?php
    }
  }

  public function show_notices_if_commerce_not_connected() {
    $mepr_options = MeprOptions::fetch();
    $has_commerce_gateway = false;

    foreach ( $mepr_options->integrations as $integration ) {
      if ( isset( $integration['gateway'] ) && 'MeprPayPalCommerceGateway' === $integration['gateway'] ) {
        $has_commerce_gateway = true;
        break;
      }
    }

    if ( $has_commerce_gateway && ! MeprPayPalCommerceGateway::has_method_with_connect_status( 'not-connected' ) ) {
      ?>
      <div class="notice notice-error mepr-notice" id="mepr_stripe_connect_upgrade_notice">
        <p>
        <p><span class="dashicons dashicons-warning mepr-warning-notice-icon"></span><strong class="mepr-warning-notice-title"><?php _e( 'Your MemberPress PayPal Connection is incomplete', 'memberpress' ); ?></strong></p>
        <p><strong><?php _e( 'Your PayPal connection in MemberPress must be connected in order to accept PayPal payments. Please click the button below to finish connecting your PayPal payment method now.', 'memberpress' ); ?></strong></p>
        <p><a href="<?php echo admin_url( 'admin.php?page=memberpress-options#mepr-integration' ); ?>" class="button button-primary"><?php _e('Connect PayPal Payment Method', 'memberpress'); ?></a></p>
        </p>
      </div>
      <?php
    }
  }

  public function admin_notices() {
    if ( ! isset( $_REQUEST['paypal-gateway-message'] ) && ! isset( $_REQUEST['paypal-gateway-message-success'] ) ) {
      return;
    }

    if ( isset( $_REQUEST['paypal-gateway-message-success'] ) ) {
      $class   = 'notice notice-success';
      $message = sanitize_text_field( $_REQUEST['paypal-gateway-message-success'] );
    } else {
      $class   = 'notice notice-error';
      $message = sanitize_text_field( $_REQUEST['paypal-gateway-message'] );
    }

    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
  }

  protected function add_ajax_endpoints() {
    add_action( 'wp_ajax_mepr_paypal_connect_rollback', array( $this, 'rollback_paypal_to_standard' ) );
    add_action( 'wp_ajax_mepr_paypal_connect_upgrade_standard_gateway', array( $this, 'upgrade_standard_gateway' ) );
    add_action( 'wp_ajax_mepr_paypal_connect_update_creds', array( $this, 'process_update_creds' ) );
    add_action( 'wp_ajax_mepr_paypal_connect_update_creds_sandbox', array( $this, 'process_update_creds_sandbox' ) );
    add_action( 'wp_ajax_mepr_paypal_connect_disconnect', array( $this, 'process_remove_creds' ) );
    add_action( 'wp_ajax_mepr_paypal_commerce_create_smart_button', array( $this, 'generate_smart_button_object' ) );
    add_action( 'wp_ajax_nopriv_mepr_paypal_commerce_create_smart_button', array( $this, 'generate_smart_button_object' ) );
    add_action( 'admin_init', array( $this, 'onboarding_success' ) );
    //add_action( 'admin_notices', array( $this, 'check_and_show_upgrade_notices' ) );
    //add_action( 'admin_notices', array( $this, 'show_notices_if_commerce_not_connected' ) );
    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    add_filter('mepr_signup_form_payment_description', array($this, 'maybe_render_payment_form'), 10, 3);
  }

  /**
   * Renders the payment form if SPC is enabled and supported by the payment method
   * Called from: mepr_signup_form_payment_description filter
   * Returns: description includding form for SPC if enabled
   */
  public function maybe_render_payment_form( $description, $payment_method, $first ) {
    $mepr_options = MeprOptions::fetch();

    if ( ! $payment_method instanceof MeprPayPalCommerceGateway ) {
      return $description;
    }

    if ( ! ( $mepr_options->enable_spc && $payment_method->has_spc_form ) ) {
      // Include smart buttons in spc
      wp_register_script('mepr-checkout-js', MEPR_JS_URL . '/checkout.js', array('jquery', 'jquery.payment'), MEPR_VERSION);
      wp_enqueue_script('mepr-checkout-js');
      $payment_method->enqueue_payment_form_scripts();
      $description = $payment_method->spc_payment_fields();
    }

    return $description;
  }

  public function onboarding_success() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    if ( isset ( $_GET['mepr-paypal-commerce-confirm-email'] ) && $_GET['mepr-paypal-commerce-confirm-email'] == '1' ) {
      $sandbox      = isset( $_GET['sandbox'] ) && $_GET['sandbox'] == '1';
      $mepr_options = MeprOptions::fetch();
      $integrations = $mepr_options->integrations;
      $methodId     = filter_var( $_GET['method-id'] );
      $site_uuid    = get_option( 'mepr_authenticator_site_uuid' );
      $buffer_settings = get_option( 'mepr_buff_integrations', [] );

      if ( isset( $buffer_settings[ $methodId ] ) ) {
        foreach ( [ 'test_merchant_id', 'live_merchant_id', 'test_email_confirmed', 'live_email_confirmed' ] as $key ) {
          if ( isset( $buffer_settings[ $methodId ][ $key ] ) ) {
            $mepr_options->integrations[ $methodId ][ $key ] = $buffer_settings[ $methodId ][ $key ];
          }
        }
      }

      if ( $sandbox ) {
        $endpoint = MEPR_PAYPAL_SERVICE_URL . "/sandbox/credentials/{$methodId}";
        $payload  = array(
          'site_uuid'   => $site_uuid,
          'merchant_id' => $integrations[ $methodId ]['test_merchant_id'],
        );
      } else {
        $endpoint = MEPR_PAYPAL_SERVICE_URL . "/credentials/{$methodId}";
        $payload  = array(
          'site_uuid'   => $site_uuid,
          'merchant_id' => $integrations[ $methodId ]['live_merchant_id'],
        );
      }

      $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

      $options = array(
        'headers' => MeprUtils::jwt_header( $jwt, MEPR_PAYPAL_SERVICE_DOMAIN )
      );

      $response = wp_remote_get( $endpoint, $options );
      $creds    = wp_remote_retrieve_body( $response );
      self::debug_log( $endpoint );
      self::debug_log( $options );
      $creds = json_decode( $creds, true );
      self::debug_log( $creds );

      if ( isset( $creds['primary_email_confirmed'] ) && ! empty( $creds['primary_email_confirmed'] ) ) {
        if ( $sandbox ) {
          $integrations[ $methodId ]['test_email_confirmed'] = true;
        } else {
          $integrations[ $methodId ]['live_email_confirmed'] = true;
        }

        $mepr_options->integrations = $integrations;
        $mepr_options->store( false );
      }
    }
    if ( isset( $_GET['paypal-connect'] ) && $_GET['paypal-connect'] == '1' ) {
      $mepr_options = MeprOptions::fetch();
      $methodId     = filter_var( $_GET['method_id'] );
      $integrations = $mepr_options->integrations;
      self::debug_log( $_GET );
      if ( isset( $_GET['merchantIdInPayPal'] ) ) {
        if ( isset  ( $_GET['sandbox'] ) && $_GET['sandbox'] == '1' ) {
          $integrations[ $methodId ]['test_merchant_id'] = esc_sql( $_GET['merchantIdInPayPal'] );
        } else {
          $integrations[ $methodId ]['live_merchant_id'] = esc_sql( $_GET['merchantIdInPayPal'] );
        }
      }
      if ( isset( $_GET['isEmailConfirmed'] ) ) {
        $isConfirmed = ! ( $_GET['isEmailConfirmed'] == 'false' );

        if ( isset  ( $_GET['sandbox'] ) && $_GET['sandbox'] == '1' ) {
          $integrations[ $methodId ]['test_email_confirmed'] = $isConfirmed;
        } else {
          $integrations[ $methodId ]['live_email_confirmed'] = $isConfirmed;
        }
      }
      self::debug_log( $integrations );
      $mepr_options->integrations = $integrations;
      $buffer = get_option( 'mepr_buff_integrations' );

      if (empty($buffer)) {
        $buffer = [];
      }

      $buffer[ $methodId ] = $integrations[ $methodId ];
      update_option( 'mepr_buff_integrations', $buffer );

      $mepr_options->store( false );
      MeprUtils::wp_redirect( admin_url( 'admin.php?page=memberpress-options#mepr-integration' ) );
      exit;
    }
  }

  public function create_webhook( $webhook_url, $client_id, $client_secret, $sandbox = false ) {
    self::debug_log( 'Attempt to create webhook' );

    $webhook_url = str_ireplace( 'http://', 'https://', $webhook_url );
    $url         = self::get_base_paypal_endpoint( $sandbox );
    $payload     = [
      "url"         => $webhook_url,
      "event_types" => [
        [
          "name" => "INVOICING.INVOICE.PAID",
        ],
        [
          "name" => "CHECKOUT.ORDER.COMPLETED",
        ],
        [
          "name" => "CHECKOUT.ORDER.PROCESSED",
        ],
        [
          "name" => "PAYMENT.SALE.COMPLETED",
        ],
        [
          "name" => "PAYMENT.CAPTURE.REFUNDED",
        ],
        [
          "name" => "PAYMENT.CAPTURE.DENIED",
        ],
        [
          "name" => "PAYMENT.SALE.REFUNDED",
        ],
        [
          "name" => "BILLING.SUBSCRIPTION.ACTIVATED",
        ],
        [
          "name" => "BILLING.SUBSCRIPTION.SUSPENDED",
        ],
        [
          "name" => "BILLING.SUBSCRIPTION.EXPIRED",
        ],
        [
          "name" => "BILLING.SUBSCRIPTION.CANCELLED",
        ],
      ],
    ];
    $json_string = json_encode( $payload, JSON_UNESCAPED_SLASHES );

    $response = wp_remote_post( $url . '/v1/notifications/webhooks', [
      "headers"   => [
        "Authorization"                 => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
        "PayPal-Partner-Attribution-Id" => self::PAYPAL_BN_CODE,
        "Content-Type"                  => "application/json"
      ],
      "body"      => $json_string,
      "method"    => "POST"
    ] );

    $raw = wp_remote_retrieve_body( $response );
    self::debug_log( $json_string );
    self::debug_log( $raw );
    $paypal_webhook = json_decode( $raw, true );

    if ( isset( $paypal_webhook['id'] ) ) {
      return $paypal_webhook['id'];
    }
  }

  /**
   * @param $webhook_id
   * @param $token
   *
   * @return bool
   */
  public static function delete_webhook( $webhook_id, $token ) {
    $url     = self::get_base_paypal_endpoint() . '/v1/notifications/webhooks/' . $webhook_id;
    $options = [
      "headers" => [
        "Authorization"                 => 'Basic ' . $token,
        "PayPal-Partner-Attribution-Id" => self::PAYPAL_BN_CODE,
        "Content-Type"                  => "application/json"
      ],
      'method'  => 'DELETE',
    ];

    $response      = wp_remote_request( $url, $options );
    $response_code = wp_remote_retrieve_response_code( $response );

    if ( $response_code >= 200 && $response_code < 300 ) {
      return true;
    }

    self::debug_log( $response );

    return false;
  }

  public static function debug_log( $data ) {
    if ( ! defined( 'WP_MEPR_DEBUG' ) ) {
      return;
    }

    file_put_contents( WP_CONTENT_DIR . '/paypal-connect.log', print_r( $data, true ) . PHP_EOL, FILE_APPEND );
  }

  public function upgrade_standard_gateway() {
    $mepr_options = MeprOptions::fetch();
    $id           = filter_input( INPUT_GET, 'method-id', FILTER_SANITIZE_STRING );
    $standard_gateway_settings = $mepr_options->integrations[ $id ];

    if ( ! isset( $mepr_options->legacy_integrations ) ) {
      $mepr_options->legacy_integrations = [];
    }

    $mepr_options->legacy_integrations[ $id ] = $standard_gateway_settings;
    $mepr_options->integrations[ $id ]['gateway'] = MeprPayPalCommerceGateway::class;
    $mepr_options->store( false );
    $url = admin_url( 'admin.php?page=memberpress-options#mepr-integration' );
    MeprUtils::wp_redirect( $url );
  }

  public function process_remove_creds() {
    $mepr_options = MeprOptions::fetch();
    $site_uuid    = get_option( 'mepr_authenticator_site_uuid' );
    $methodId     = sanitize_text_field( $_REQUEST['method-id'] );
    $payload      = array(
      'site_uuid' => $site_uuid
    );

    $sandbox = filter_var( isset( $_GET['sandbox'] ) ? $_GET['sandbox'] : 0 );
    $retry = filter_var( isset( $_GET['retry'] ) ? $_GET['retry'] : 0 );

    if ( $retry ) {
      $integrations   = $mepr_options->integrations;
      $integrations[ $methodId ]['live_auth_code'] = '';
      $integrations[ $methodId ]['test_auth_code'] = '';
      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
      $message = esc_html( __( 'You have disconnected your PayPal. You should login to your PayPal account and go to Developer settings to delete the app created by this gateway', 'memberpress' ) );
      $url     = admin_url( 'admin.php?page=memberpress-options&paypal-gateway-message-success=' . $message . '#mepr-integration' );
      MeprUtils::wp_redirect( $url );
    }

    self::debug_log( $sandbox );

    if ( ! empty( $sandbox ) ) {
      $endpoint = MEPR_PAYPAL_SERVICE_URL . "/sandbox/credentials/{$methodId}";
    } else {
      $endpoint = MEPR_PAYPAL_SERVICE_URL . "/credentials/{$methodId}";
    }

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

    // Make sure the request came from the Connect service
    $options = array(
      'body'    => [
        'method-id' => $methodId,
      ],
      'method'  => 'DELETE',
      'headers' => MeprUtils::jwt_header( $jwt, MEPR_PAYPAL_SERVICE_DOMAIN )
    );

    $response      = wp_remote_request( $endpoint, $options );
    $response_code = wp_remote_retrieve_response_code( $response );
    $body          = wp_remote_retrieve_body( $response );
    $integrations  = $mepr_options->integrations;
    $payment_method = $mepr_options->payment_method( $methodId );

    if ( empty( $sandbox ) ) {
      self::delete_webhook( $payment_method->settings->live_webhook_id, $payment_method->get_pp_basic_auth_token() );
      $integrations[ $methodId ]['live_webhook_id'] = '';
    } else {
      $integrations[ $methodId ]['test_webhook_id'] = '';
      self::delete_webhook( $payment_method->settings->test_webhook_id, $payment_method->get_pp_basic_auth_token() );
    }

    self::debug_log( $body );

    if ( $response_code === 200 ) {
      if ( empty( $sandbox ) ) {
        $integrations[ $methodId ]['live_client_id']     = '';
        $integrations[ $methodId ]['live_client_secret'] = '';
        $integrations[ $methodId ]['live_merchant_id'] = '';
      } else {
        $integrations[ $methodId ]['test_client_id']     = '';
        $integrations[ $methodId ]['test_client_secret'] = '';
        $integrations[ $methodId ]['test_merchant_id'] = '';
      }

      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
      $message = esc_html( __( 'You have disconnected your PayPal. You should login to your PayPal account and go to Developer settings to delete the app created by this gateway', 'memberpress' ) );
      $url     = admin_url( 'admin.php?page=memberpress-options&paypal-gateway-message-success=' . $message . '#mepr-integration' );
    } else {
      self::debug_log( $options );
      self::debug_log( $endpoint );
      $message = esc_html( __( 'Something could not be executed', 'memberpress' ) );
      $url     = admin_url( 'admin.php?page=memberpress-options&paypal-gateway-message=' . $message . '#mepr-integration' );
    }

    MeprUtils::wp_redirect( $url );
  }

  public function process_update_creds_sandbox() {
    $this->process_update_creds( true );
  }

  public function process_update_creds( $sandbox = false ) {
    // Security check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'paypal-update-creds' ) ) {
      wp_die( __( 'Sorry, updating your credentials failed. (security)', 'memberpress' ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( __( 'You do not have sufficient permission to perform this operation', 'memberpress' ) );
    }

    $posted = json_decode( @file_get_contents( 'php://input' ), true );
    self::debug_log( $posted );
    $authCode     = $posted['authCode'];
    $sharedId     = $posted['sharedId'];
    $methodId     = $posted['payment_method_id'];
    $pm           = new MeprPayPalCommerceGateway();
    $mepr_options = MeprOptions::fetch();
    $integrations = $mepr_options->integrations;

    if ( ! isset( $integrations[ $methodId ] ) ) {
      $integrations[ $methodId ] = [
        'label'   => esc_html( __( 'PayPal', 'memberpress' ) ),
        'id'      => $methodId,
        'gateway' => 'MeprPayPalCommerceGateway',
        'saved' => true,
      ];

      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
      $pm->load( array( 'id' => $methodId ) );
      $pm->id = $methodId;
    }

    $pm->load( $integrations[ $methodId ] );

    if ( $sandbox ) {
      if ( isset( $integrations[ $methodId ]['test_auth_code'] ) && ! empty( $integrations[ $methodId ]['test_auth_code'] ) ) {
        die('An auth code is being processed');
      }
      $integrations[ $methodId ]['test_auth_code'] = $authCode;
      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
    } else {
      if ( isset( $integrations[ $methodId ]['live_auth_code'] ) && ! empty( $integrations[ $methodId ]['live_auth_code'] ) ) {
        die('An auth code is being processed');
      }
      $integrations[ $methodId ]['live_auth_code'] = $authCode;
      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
    }

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    $payload = array(
      'site_uuid' => $site_uuid
    );

    $jwt     = MeprAuthenticatorCtrl::generate_jwt( $payload );
    $options = array(
      'body'    => [
        'auth_code' => $authCode,
        'share_id'  => $sharedId,
      ],
      'headers' => MeprUtils::jwt_header( $jwt, MEPR_PAYPAL_SERVICE_DOMAIN )
    );

    if ( $sandbox ) {
      $endpoint = MEPR_PAYPAL_SERVICE_URL . "/sandbox/credentials/{$methodId}";
    } else {
      $endpoint = MEPR_PAYPAL_SERVICE_URL . "/credentials/{$methodId}";
    }

    $response = wp_remote_post( $endpoint, $options );
    $creds    = wp_remote_retrieve_body( $response );
    $creds = json_decode( $creds, true );
    self::debug_log( $endpoint );
    self::debug_log( $options );
    self::debug_log( $creds );
    self::debug_log( $response );

    if ( isset( $creds['client_id'] ) && isset( $creds['client_secret'] ) ) {
      $webhook_id   = self::create_webhook( $pm->notify_url( 'webhook' ), $creds['client_id'], $creds['client_secret'], $sandbox );

      self::debug_log( 'saving gateway' );
      if ( $sandbox ) {
        $integrations[ $methodId ]['test_client_id']     = $creds['client_id'];
        $integrations[ $methodId ]['test_client_secret'] = $creds['client_secret'];
        $integrations[ $methodId ]['test_auth_code']     = '';
        $integrations[ $methodId ]['test_webhook_id']    = $webhook_id;
      } else {
        $integrations[ $methodId ]['live_client_id']     = $creds['client_id'];
        $integrations[ $methodId ]['live_client_secret'] = $creds['client_secret'];
        $integrations[ $methodId ]['live_auth_code']     = '';
        $integrations[ $methodId ]['live_webhook_id']    = $webhook_id;
      }
      self::debug_log( $integrations );
      $mepr_options->integrations = $integrations;
      $mepr_options->store( false );
    }
  }

  public function generate_smart_button_object() {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $_POST = $input;
    $_POST['smart-payment-button'] = true;
    $checkout_ctrl = MeprCtrlFactory::fetch( 'checkout' );
    $checkout_ctrl->process_signup_form();
    if ( isset( $_REQUEST['errors'] ) ) {
      wp_send_json( $_REQUEST, 400 );
    }

    wp_send_json( $_REQUEST );
  }

  public static function get_base_paypal_endpoint( $sandbox = false ) {
    if ( $sandbox ) {
      return self::PAYPAL_URL_SANDBOX;
    }

    return self::PAYPAL_URL_LIVE;
  }

  public function rollback_paypal_to_standard() {
    $mepr_options = MeprOptions::fetch();
    $id           = filter_input( INPUT_GET, 'method-id', FILTER_SANITIZE_STRING );

    if ( ! isset( $mepr_options->legacy_integrations[ $id ] ) ) {
      return;
    }

    $mepr_options->integrations[ $id ] = $mepr_options->legacy_integrations[ $id ];
    $mepr_options->integrations[ $id ]['gateway'] = MeprPayPalStandardGateway::class;
    $mepr_options->store( false );
    $message = esc_html( __( 'You have reverted PayPal to legacy gateway', 'memberpress' ) );
    $url     = admin_url( 'admin.php?page=memberpress-options&paypal-gateway-message=' . $message . '#mepr-integration' );
    MeprUtils::wp_redirect( $url );
  }

  /**
   * Run a site health check and return the result
   *
   * @return array
   */
  public function run_site_health_test() {
    $result = array(
      'label'       => __( 'MemberPress is securely connected to PayPal', 'memberpress' ),
      'status'      => 'good',
      'badge'       => array(
        'label' => __( 'Security', 'memberpress' ),
        'color' => 'blue',
      ),
      'description' => sprintf(
        '<p>%s</p>',
        __( 'Your MemberPress PayPal connection is complete and secure.', 'memberpress' )
      ),
      'actions'     => '',
      'test'        => 'run_site_health_test',
    );

    if ( class_exists( 'MeprPaypalCommerceGateway' ) && ! MeprPayPalCommerceGateway::has_method_with_connect_status( 'not-connected' ) ) {
      $result = array(
        'label'       => __( 'MemberPress is not securely connected to PayPal', 'memberpress' ),
        'status'      => 'critical',
        'badge'       => array(
          'label' => __( 'Security', 'memberpress' ),
          'color' => 'red',
        ),
        'description' => sprintf(
          '<p>%s</p>',
          __( 'Your current PayPal payment connection is out of date and may become insecure or stop working. Please click the button below to re-connect your PayPal payment method now.', 'memberpress' )
        ),
        'actions'     => '<a href="' . admin_url( 'admin.php?page=memberpress-options#mepr-integration' ) . '" class="button button-primary">' . __( 'Re-connect PayPal Payments to Fix this Error Now', 'memberpress' ) . '</a>',
        'test'        => 'run_site_health_test',
      );
    }

    return $result;
  }
}

