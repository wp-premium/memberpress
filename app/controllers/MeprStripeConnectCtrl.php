<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeConnectCtrl extends MeprBaseCtrl {
  public function load_hooks() {

    if(!defined('MEPR_STRIPE_SERVICE_DOMAIN')) {
      define('MEPR_STRIPE_SERVICE_DOMAIN', 'stripe.memberpress.com');
    }

    define('MEPR_STRIPE_SERVICE_URL', 'https://' . MEPR_STRIPE_SERVICE_DOMAIN);

    if ( defined( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
      return;
    }

    add_action( 'admin_init', array( $this, 'persist_display_keys' ) );
    // add_action( 'mepr_stripe_connect_check_domain', array( $this, 'maybe_update_domain' ) );
    add_action( 'update_option_home', array( $this, 'url_changed' ), 10, 3 );
    add_action( 'update_option_siteurl', array( $this, 'url_changed' ), 10, 3 );
    add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
    add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    add_filter( 'site_status_tests', array( $this, 'add_site_health_test' ) );
    add_action( 'mepr-weekly-summary-email-inner-table-top-tr', array( $this, 'maybe_add_notice_to_weekly_summary_email' ) );
    add_action( 'wp_ajax_mepr_stripe_connect_update_creds', array( $this, 'process_update_creds' ) );
    add_action( 'wp_ajax_mepr_stripe_connect_refresh', array( $this, 'process_refresh_tokens' ) );
    add_action( 'wp_ajax_mepr_stripe_connect_disconnect', array( $this, 'process_disconnect' ) );

    add_action( 'mepr_memberpress_com_pre_disconnect', array( $this, 'disconnect_all' ), 10, 2 );

    add_action( 'mepr_process_options', array( $this, 'disconnect_deleted_methods' ) );

    add_action( 'wp_ajax_mepr_create_new_payment_method', array( $this, 'create_new_payment_method' ) );

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    /*if ( ! wp_next_scheduled ( 'mepr_stripe_connect_check_domain' ) && ! empty( $site_uuid ) ) {
      wp_schedule_event( time(), 'daily', 'mepr_stripe_connect_check_domain' );
    }*/

    // Remove wp-cron Stripe Connect job
    if ( $timestamp = wp_next_scheduled( 'mepr_stripe_connect_check_domain' ) ) {
      wp_unschedule_event( $timestamp, 'mepr_stripe_connect_check_domain' );
    }
  }

  /**
   * When the ?display-keys query param is set, set a cookie to persist the "selection"
   *
   * @return void
   */
  public function persist_display_keys() {
    if ( isset($_GET['page']) && $_GET['page']=='memberpress-options' && isset( $_GET['display-keys'] ) ) {
      setcookie( 'mepr_stripe_display_keys', '1', time() + HOUR_IN_SECONDS, '/' );
    }
  }

  /**
   * Run the process for updating a webhook when a site's home or site URL changes
   *
   * @param  string   $old_url  Old setting (URL)
   * @param  string   $new_url  New setting
   * @param  string   $option   Option name
   *
   * @return string
   */
  public function url_changed( $old_url, $new_url, $option ) {
    if ( $new_url !== $old_url ) {
      $this->maybe_update_domain();
    }
  }

  /**
   * This checks if the current site's domain has changed from what we have stored on the Authentication service.
   * If the domain has changed, we need to update the site on the Auth service, and the connection on the Stripe Connect service.
   *
   * @return void
   */
  public function maybe_update_domain() {

    $old_site_url = get_option( 'mepr_old_site_url',  get_site_url() );

    // Exit if the home URL hasn't changed
    if($old_site_url==get_site_url()) {
      return;
    }

    $mepr_options = MeprOptions::fetch();
    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    $payload = array(
      'site_uuid' => $site_uuid
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );
    $domain = parse_url( get_site_url(), PHP_URL_HOST );

    // Request to change the domain with the auth service (site.domain)
    $response = wp_remote_post( MEPR_AUTH_SERVICE_URL . "/api/domains/update", array(
      'sslverify' => false,
      'headers' => MeprUtils::jwt_header($jwt, MEPR_AUTH_SERVICE_DOMAIN),
      'body' => array(
        'domain' => $domain
      )
    ) );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Request to change the notification/webhook URL on the Stripe Connect service (account.webhook_url)
    $webhooks = array();
    foreach( $mepr_options->integrations as $id => $integration ) {
      if ( 'connected' === $integration['connect_status'] ) {
        $pm = $mepr_options->payment_method( $id );
        $webhooks[$id] = array(
          'webhook_url' => $pm->notify_url( 'whk' ),
          'service_webhook_url' => $pm->notify_url( 'stripe-service-whk' )
        );
      }
    }

    $response = wp_remote_post( MEPR_STRIPE_SERVICE_URL . "/api/webhooks/update", array(
      'sslverify' => false,
      'headers' => MeprUtils::jwt_header($jwt, MEPR_STRIPE_SERVICE_DOMAIN),
      'body' => compact( 'webhooks' )
    ) );

    $body = wp_remote_retrieve_body( $response );

    MeprUtils::debug_log("maybe_update_webhooks recived this from Stripe Service: " . print_r($body, true));

    // Store for next time
    update_option( 'mepr_old_site_url', get_site_url() );
  }

  /**
   * Display an admin notice for upgrading Stripe payment methods to Stripe Connect
   *
   * @return void
   */
  public function upgrade_notice() {
    if ( MeprStripeGateway::has_method_with_connect_status( 'not-connected' ) && ( ! isset( $_COOKIE['mepr_stripe_connect_upgrade_dismissed'] ) || false == $_COOKIE['mepr_stripe_connect_upgrade_dismissed'] ) ) {
      ?>
        <div class="notice notice-error mepr-notice is-dismissible" id="mepr_stripe_connect_upgrade_notice">
          <p>
            <p><span class="dashicons dashicons-warning mepr-warning-notice-icon"></span><strong class="mepr-warning-notice-title"><?php _e( 'MemberPress Security Notice', 'memberpress' ); ?></strong></p>
            <p><strong><?php _e( 'Your current Stripe payment connection is out of date and may become insecure. Please click the button below to re-connect your Stripe payment method now.', 'memberpress' ); ?></strong></p>
            <p><a href="<?php echo admin_url( 'admin.php?page=memberpress-options#mepr-integration' ); ?>" class="button button-primary"><?php _e('Re-connect Stripe Payments to Fix this Error Now', 'memberpress'); ?></a></p>
          </p>
          <?php wp_nonce_field( 'mepr_stripe_connect_upgrade_notice_dismiss', 'mepr_stripe_connect_upgrade_notice_dismiss' ); ?>
        </div>
      <?php
    }
  }

  /**
   * Adds admin notices depending on what action was completed
   *
   * @return void
   */
  public function admin_notices() {

    if ( isset( $_GET['mepr-action'] ) && 'error' === $_GET['mepr-action'] && isset( $_GET['error'] ) && ! empty( $_GET['error'] ) ) : ?>
      <div class="notice notice-error mepr-removable-notice is-dismissible">
        <p><?php echo strip_tags( urldecode( $_GET['error'] ) ); ?></p>
      </div>
    <?php endif;

    if ( isset( $_REQUEST['stripe-action'] ) ) {

        switch ( $_REQUEST['stripe-action'] ) {

          case 'connected':
            $notice_text = __( 'Your payment method was successfully connected to your Stripe account.', 'memberpress' );
            break;

          case 'updated':
            $notice_text = __( 'Your payment method\'s Stripe Connect keys were successfully updated.', 'memberpress' );
            break;

          case 'refreshed':
            $notice_text = __( 'Your Stripe tokens were successfully refreshed.', 'memberpress' );
            break;

          case 'disconnected':
            $notice_text = __( 'You successfully disconnected this payment method from your Stripe account.', 'memberpress' );
            break;

          default:
            break;
        }

      ?>

      <div class="notice notice-success mepr-removable-notice is-dismissible">
        <p><?php echo $notice_text; ?></p>
      </div>

      <?php

    }
  }

  /**
   * Add a site health test callback
   *
   * @param  array   $tests   Array of tests to be run
   *
   * @return array
   */
  public function add_site_health_test( $tests ) {

    $tests['direct']['mepr_stripe_connect_test'] = array(
      'label' => __( 'MemberPress - Stripe Connect Security', 'memberpress' ),
      'test'  => array( $this, 'run_site_health_test' )
    );

    return $tests;
  }

  /**
   * Run a site health check and return the result
   *
   * @return array
   */
  public function run_site_health_test() {

    $result = array(
      'label'   => __( 'MemberPress is securely connected to Stripe', 'memberpress' ),
      'status'  => 'good',
      'badge'   => array(
        'label'   => __( 'Security', 'memberpress' ),
        'color'   => 'blue',
      ),
      'description' => sprintf(
        '<p>%s</p>',
        __( 'Your MemberPress Stripe connection is complete and secure.', 'memberpress' )
      ),
      'actions' => '',
      'test'    => 'run_site_health_test',
    );

    if ( class_exists( 'MeprStripeGateway' ) && MeprStripeGateway::has_method_with_connect_status( 'not-connected' ) ) {
      $result = array(
        'label'   => __( 'MemberPress is not securely connected to Stripe', 'memberpress' ),
        'status'  => 'critical',
        'badge'   => array(
          'label'   => __( 'Security', 'memberpress' ),
          'color'   => 'red',
        ),
        'description' => sprintf(
          '<p>%s</p>',
          __( 'Your current Stripe payment connection is out of date and may become insecure or stop working. Please click the button below to re-connect your Stripe payment method now.', 'memberpress' )
        ),
        'actions' => '<a href="' . admin_url( 'admin.php?page=memberpress-options#mepr-integration' ) . '" class="button button-primary">' . __( 'Re-connect Stripe Payments to Fix this Error Now', 'memberpress' ) . '</a>',
        'test'    => 'run_site_health_test',
      );
    }

    return $result;
  }

  /**
   * Adds a notice to the top of the Weekly Summary email about Stripe Connect
   *
   * @return void
   */
  public function maybe_add_notice_to_weekly_summary_email() {
    if ( class_exists( 'MeprStripeGateway' ) && MeprStripeGateway::has_method_with_connect_status( 'not-connected' ) ) {
      ?>
        <tr>
          <td valign="top">
            <div style="padding:30px;background-color:#f1f1f1;">
              <h2 style="color:#dc3232;"><?php _e( 'MemberPress Security Notice', 'memberpress' ); ?></h2>
              <p style="font-family:Helvetica,Arial,sans-serif;line-height:1.5;">
                <?php _e( 'Your current Stripe payment connection is out of date and may become insecure. Please click the link below to re-connect your Stripe payment method now.', 'memberpress' ); ?>
              </p>
              <p><a href="<?php echo admin_url( 'admin.php?page=memberpress-options#mepr-integration' ); ?>"><?php _e( 'Re-connect Stripe Payments to Fix this Error Now', 'memberpress' ); ?></a></p>
            </div>
          </td>
        </tr>
      <?php
    }
  }

  /**
   * Process a request to retrieve credentials after a connection
   *
   * @return void
   */
  public function process_update_creds() {

    // Security check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'stripe-update-creds' ) ) {
      wp_die(__('Sorry, updating your credentials failed. (security)', 'memberpress'));
    }

    // Check for the existence of any errors passed back from the service
    if ( isset( $_GET['error'] ) ) {
      wp_die( sanitize_text_field( urldecode( $_GET['error'] ) ) );
    }

    // Make sure we have a method ID
    if ( ! isset( $_GET['pmt'] ) ) {
      wp_die(__('Sorry, updating your credentials failed. (pmt)', 'memberpress'));
    }

    // Make sure the user is authorized
    if ( ! MeprUtils::is_mepr_admin() ) {
      wp_die(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();

    $method_id = sanitize_text_field( $_GET['pmt'] );
    $pm = $mepr_options->payment_method( $method_id );

    if(!($pm instanceof MeprStripeGateway)) {
      wp_die(__('Sorry, this only works with Stripe.', 'memberpress'));
    }

    $pm->update_connect_credentials();

    MeprUtils::debug_log("*** MeprStripeConnectCtrl->process_update_creds() stored payment methods [{$method_id}]: " . print_r($mepr_options->integrations[$method_id]['api_keys']['test']['secret'],true));

    $stripe_action = ( ! empty( $_GET['stripe-action'] ) ? sanitize_text_field( $_GET['stripe-action'] ) : 'updated' );

    $redirect_url = add_query_arg( array(
      'page' => 'memberpress-options',
      'stripe-action' => $stripe_action
    ), admin_url('admin.php') ) . '#mepr-integration';

    wp_redirect($redirect_url);
    exit;
  }

  /**
   * Process a request to refresh tokens
   *
   * @return void
   */
  public function process_refresh_tokens() {

    // Security check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'stripe-refresh' ) ) {
      wp_die(__('Sorry, the refresh failed.', 'memberpress'));
    }

    // Make sure we have a method ID
    if ( ! isset( $_GET['method-id'] ) ) {
      wp_die(__('Sorry, the refresh failed.', 'memberpress'));
    }

    // Make sure the user is authorized
    if ( ! MeprUtils::is_mepr_admin() ) {
      wp_die(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $method_id = sanitize_text_field( $_GET['method-id'] );
    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    $payload = array(
      'site_uuid' => $site_uuid
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

    // Send request to Connect service
    $response = wp_remote_post( MEPR_STRIPE_SERVICE_URL . "/api/refresh/{$method_id}", array(
      'headers' => MeprUtils::jwt_header($jwt, MEPR_STRIPE_SERVICE_DOMAIN),
    ) );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! isset( $body['connect_status'] ) || 'refreshed' !== $body['connect_status'] ) {
      wp_die(__('Sorry, the refresh failed.', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();

    $integration_updated_count = 0;

    foreach($mepr_options->integrations as $method_id => $integ) {
      // Update ALL of the payment methods connected to this account
      if( isset($mepr_options->integrations[$method_id]['service_account_id']) &&
          $mepr_options->integrations[$method_id]['service_account_id'] == sanitize_text_field($body['service_account_id']) )
      {
        $mepr_options->integrations[$method_id]['service_account_name'] = sanitize_text_field($body['service_account_name']);
        $mepr_options->integrations[$method_id]['api_keys']['test']['public'] = sanitize_text_field( $body['test_publishable_key'] );
        $mepr_options->integrations[$method_id]['api_keys']['test']['secret'] = sanitize_text_field( $body['test_secret_key'] );
        $mepr_options->integrations[$method_id]['api_keys']['live']['public'] = sanitize_text_field( $body['live_publishable_key'] );
        $mepr_options->integrations[$method_id]['api_keys']['live']['secret'] = sanitize_text_field( $body['live_secret_key'] );
        $integration_updated_count++;
      }
    }

    if($integration_updated_count > 0) {
      $mepr_options->store(false);
    }

    $redirect_url = add_query_arg( array(
      'page' => 'memberpress-options',
      'stripe-action' => 'refreshed'
    ), admin_url('admin.php') ) . '#mepr-integration';

    wp_redirect($redirect_url);
    exit;
  }

  /**
   * Process a request to disconnect
   *
   * @return void
   */
  public function process_disconnect() {

    // Security check
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'stripe-disconnect' ) ) {
      wp_die(__('Sorry, the disconnect failed.', 'memberpress'));
    }

    // Make sure we have a method ID
    if ( ! isset( $_GET['method-id'] ) ) {
      wp_die(__('Sorry, the disconnect failed.', 'memberpress'));
    }

    // Make sure the user is authorized
    if ( ! MeprUtils::is_mepr_admin() ) {
      wp_die(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $method_id = sanitize_text_field( $_GET['method-id'] );

    $res = $this->disconnect( $method_id );

    if(!$res) {
      wp_die(__('Sorry, the disconnect failed.', 'memberpress'));
    }

    $redirect_url = add_query_arg( array(
      'page' => 'memberpress-options',
      'stripe-action' => 'disconnected'
    ), admin_url('admin.php') ) . '#mepr-integration';

    wp_redirect($redirect_url);
    exit;
  }

  /**
   * Disconnect ALL stripe connected payment methods
   *
   * @return void
   */
  public function disconnect_all($site_uuid, $site_email) {
    MeprUtils::debug_log("********** IN disconnect_all!");
    $mepr_options = MeprOptions::fetch();
    $pms = $mepr_options->payment_methods(false);
    foreach( $pms as $method_id => $pm ) {
      MeprUtils::debug_log("********** disconnect_all: $method_id");
      if( $pm instanceof MeprStripeGateway && MeprStripeGateway::is_stripe_connect( $method_id ) ) {
        MeprUtils::debug_log("********** disconnect_all: Disconnecting: $method_id");
        $res = $this->disconnect( $method_id );
        MeprUtils::debug_log("********** disconnect_all: Disconnection " . ($res ? "SUCCESSFUL!" : "FAILED!"));
      }
    }
  }

  public function disconnect($method_id, $disconnect_type='full') {

    if($disconnect_type==='full') {
      // Update connection data
      $mepr_options = MeprOptions::fetch();
      $integ = $mepr_options->integrations[$method_id];
      $integ['connect_status'] = 'disconnected';
      unset( $integ['service_account_id'] );
      unset( $integ['service_account_name'] );

      $mepr_options->integrations[$method_id] = $integ;
      $mepr_options->store(false);
    }

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    // Attempt to disconnect at the service
    $payload = array(
      'method_id' => $method_id,
      'site_uuid' => $site_uuid
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

    // Send request to Connect service
    $response = wp_remote_request( MEPR_STRIPE_SERVICE_URL . "/api/disconnect/{$method_id}", array(
      'method' => 'DELETE',
      'headers' => MeprUtils::jwt_header($jwt, MEPR_STRIPE_SERVICE_DOMAIN),
    ) );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! isset( $body['connect_status'] ) || 'disconnected' !== $body['connect_status'] ) {
      return false;
    }

    return true;
  }

  /** Create a new payment method before redirection to Stripe Connect */
  public function create_new_payment_method() {
    check_ajax_referer( 'new-stripe-connect', 'security' );

    $form_data = urldecode($_POST['form_data']);

    $pm = array();
    parse_str($form_data, $pm);

    $mepr_options = MeprOptions::fetch();
    $mepr_options->integrations = array_merge($mepr_options->integrations, $pm['mepr-integrations']);
    $mepr_options->store(false);

    echo json_encode(array('status' => 'success', 'message' => __('You successfully stored a new payment method yo.', 'memberpress')));
    exit;
  }

  /**
   * When connected payment method is deleted, it should be disconnected.
   *
   * @return void
   */
  public function disconnect_deleted_methods( $params ) {
    $mepr_options = MeprOptions::fetch();

    // Bail early if no payment methods have been deleted
    if ( empty( $params['mepr_deleted_payment_methods'] ) ) {
      return;
    }

    foreach ( $params['mepr_deleted_payment_methods'] as $method_id ) {
      if(empty($mepr_options->integrations[$method_id])) { continue; }

      $integ = $mepr_options->integrations[$method_id];

      if ( $integ['gateway'] === 'MeprStripeGateway' && MeprStripeGateway::is_stripe_connect( $method_id ) ) {
        $this->disconnect( $method_id, 'remote-only' );
      }
    }
  }
}

