<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAuthenticatorCtrl extends MeprBaseCtrl
{
  const LIVE_AUTHENTICATOR_ENDPOINT = 'https://auth.caseproof.com';
  public function load_hooks() {
    if(!defined('MEPR_AUTH_SERVICE_DOMAIN')) {
      define('MEPR_AUTH_SERVICE_DOMAIN', 'auth.caseproof.com');
    }
    define('MEPR_AUTH_SERVICE_URL', 'https://' . MEPR_AUTH_SERVICE_DOMAIN);

    add_action( 'admin_init', array( $this, 'clear_connection_data' ) );
    add_action( 'init', array( $this, 'process_connect' ) );
    add_action( 'init', array( $this, 'process_disconnect' ) );
  }

  public function clear_connection_data() {
    if ( isset( $_GET['mp-clear-connection-data'] ) ) {
      // Admins only
      if ( current_user_can( 'manage_options' ) ) {
        delete_option( 'mepr_authenticator_site_uuid' );
        delete_option( 'mepr_authenticator_account_email' );
        delete_option( 'mepr_authenticator_secret_token' );
      }
    }
  }

  /**
   * Process a Connect
   *
   * @return void
   */
  public function process_connect() {

    // Make sure we've entered our Authenticator process
    if ( ! isset( $_GET['mepr-connect'] ) || 'true' !== $_GET['mepr-connect'] ) {
      return;
    }

    // Validate the nonce on the WP side of things
    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'mepr-connect' ) ) {
      return;
    }

    // Make sure the user is authorized
    if ( ! MeprUtils::is_mepr_admin() ) {
      return;
    }

    $site_uuid = sanitize_text_field( $_GET['site_uuid'] );
    $auth_code = sanitize_text_field( $_GET['auth_code'] );

    // GET request to obtain token
    $response = wp_remote_get( MEPR_AUTH_SERVICE_URL . "/api/tokens/{$site_uuid}", array(
      'sslverify' => false,
      'headers' => array(
        'accept' => 'application/json'
      ),
      'body' => array(
        'auth_code' => $auth_code
      )
    ) );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['account_email'] ) && ! empty( $body['account_email'] ) ) {
      $email_saved = update_option( 'mepr_authenticator_account_email', sanitize_text_field( $body['account_email'] ) );
    }

    if ( isset( $body['secret_token'] ) && ! empty( $body['secret_token'] ) ) {
      $token_saved = update_option( 'mepr_authenticator_secret_token', sanitize_text_field( $body['secret_token'] ) );
    }

    if ( isset( $body['user_uuid'] ) && ! empty( $body['user_uuid'] ) ) {
      $user_uuid_saved = update_option( 'mepr_authenticator_user_uuid', sanitize_text_field( $body['user_uuid'] ) );
    }

    if ( $site_uuid ) {
      update_option( 'mepr_authenticator_site_uuid', $site_uuid );
    }

    if ( isset( $_GET['stripe_connect'] ) && 'true' === $_GET['stripe_connect'] && isset( $_GET['method_id'] ) && ! empty( $_GET['method_id'] ) ) {
      wp_redirect( MeprStripeGateway::get_stripe_connect_url( $_GET['method_id'] ) );
      exit;
    }

    wp_redirect( remove_query_arg( array( 'mepr-connect', 'nonce', 'site_uuid', 'auth_code' ) ) );
    exit;
  }

  /**
   * Process a Disconnect
   *
   * @return void
   */
  public function process_disconnect() {

    // Make sure we've entered our Authenticator process
    if ( ! isset( $_GET['mepr-disconnect'] ) || 'true' !== $_GET['mepr-disconnect'] ) {
      return;
    }

    // Validate the nonce on the WP side of things
    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'mepr-disconnect' ) ) {
      return;
    }

    // Make sure the user is authorized
    if ( ! MeprUtils::is_mepr_admin() ) {
      return;
    }

    $site_email = get_option( 'mepr_authenticator_account_email' );
    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    MeprHooks::do_action('mepr_memberpress_com_pre_disconnect', $site_uuid, $site_email);

    // Create token payload
    $payload = array(
      'email' => $site_email,
      'site_uuid' => $site_uuid
    );

    // Create JWT
    $jwt = self::generate_jwt( $payload );

    // DELETE request to obtain token
    $response = wp_remote_request( MEPR_AUTH_SERVICE_URL . "/api/disconnect/memberpress", array(
      'method' => 'DELETE',
      'sslverify' => false,
      'headers' => MeprUtils::jwt_header($jwt, MEPR_AUTH_SERVICE_DOMAIN),
    ) );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['disconnected'] ) && true === $body['disconnected'] ) {
      delete_option( 'mepr_authenticator_account_email' );
      delete_option( 'mepr_authenticator_secret_token' );
      delete_option( 'mepr_authenticator_site_uuid', $site_uuid );
    }

    wp_redirect( remove_query_arg( array( 'mepr-disconnect', 'nonce' ) ) );
    exit;
  }

  /**
   * Generates a JWT, signed by the stored secret token
   *
   * @param  array  $payload  Payload data
   * @param  sring $secret    Used to sign the JWT
   *
   * @return string
   */
  public static function generate_jwt( $payload, $secret = false ) {

    if ( false === $secret ) {
      $secret = get_option( 'mepr_authenticator_secret_token' );
    }

    // Create token header
    $header = array(
      'typ' => 'JWT',
      'alg' => 'HS256'
    );
    $header = json_encode( $header );
    $header = self::base64url_encode( $header );

    // Create token payload
    $payload = json_encode( $payload );
    $payload = self::base64url_encode( $payload );

    // Create Signature Hash
    $signature = hash_hmac( 'sha256', "{$header}.{$payload}", $secret );
    $signature = json_encode( $signature );
    $signature = self::base64url_encode( $signature );

    // Create JWT
    $jwt = "{$header}.{$payload}.{$signature}";
    return $jwt;
  }

  /**
   * Ensure that the Base64 string is passed within URLs without any URL encoding
   *
   * @param  string $value
   *
   * @return string
   */
  public static function base64url_encode( $value ) {
    return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
  }

  /**
   * Assembles a URL for connecting to our Authentication service
   *
   * @param boolean   $stripe_connect   Will add a query string that is used to redirect to Stripe Connect after returning from Auth service
   * @param array   $additional_params
   *
   * @return string
   */
  public static function get_auth_connect_url( $stripe_connect = false, $payment_method_id = false, $additional_params = [] ) {
    $return_url = admin_url( 'admin.php?page=memberpress-account-login', false );

    $connect_params = array(
      'return_url' => urlencode( add_query_arg( 'mepr-connect', 'true', $return_url ) ),
      'nonce' => wp_create_nonce( 'mepr-connect' )
    );

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    if ( $site_uuid ) {
      $connect_params['site_uuid'] = $site_uuid;
    }

    if ( true === $stripe_connect && ! empty( $payment_method_id ) ) {
      $connect_params['stripe_connect'] = 'true';
      $connect_params['method_id'] = $payment_method_id;
      $endpoint = MEPR_AUTH_SERVICE_URL;
    } else {
      $endpoint = self::LIVE_AUTHENTICATOR_ENDPOINT;
    }

    if ( ! empty( $additional_params ) ) {
      $connect_params = array_merge($connect_params, $additional_params);
    }

    return add_query_arg( $connect_params, $endpoint . '/connect/memberpress' );
  }
}

