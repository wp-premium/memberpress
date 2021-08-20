<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAuthorizeAPI {
  public static $sandbox_api_endpoint = 'https://apitest.authorize.net/xml/v1/request.api';
  public static $live_api_endpoint = 'https://api.authorize.net/xml/v1/request.api';
  private $api_endpoint;
  private $login_name;
  private $transaction_key;
  private $test_mode;

  public function __construct($settings = array()) {
    $this->login_name = isset($settings->login_name) ? $settings->login_name : '';
    $this->transaction_key = isset($settings->transaction_key) ? $settings->transaction_key : '';
    $this->test_mode = isset($settings->test_mode) && $settings->test_mode;
    if($this->test_mode) {
      $this->api_endpoint = self::$sandbox_api_endpoint;
    }
    else {
      $this->api_endpoint = self::$live_api_endpoint;
    }
  }

  /**
  * Fetch transaction details from the Auth.net API
  * @param int Transaction ID
  * @return object|null JSON decoded transaction object. NULL on API error.
  */
  public function get_transaction_details($id) {
    return $this->send_request('getTransactionDetailsRequest', array('transId' => $id));
  }

  /**
  * Send request to the Auth.net api
  * @param string $type API request type
  * @param array $args API request arguments
  * @return object|null JSON decoded transaction object. NULL on API error.
  */
  public function send_request($type, $args = array()) {
    $post_body = json_encode(
      array(
        $type => array(
          'merchantAuthentication' => array(
            'name' => $this->login_name,
            'transactionKey' => $this->transaction_key
          ),
          'transId' => $args['transId']
        )
      )
    );

    $api_response_body = wp_remote_retrieve_body(wp_remote_post($this->api_endpoint, array('body' => $post_body, 'headers' => array('content-type' => 'application/json'))));
    // Authorize.net is sending some garbage at the beginning of the response body that is not valid JSON
    // Reference: https://community.developer.authorize.net/t5/Integration-and-Testing/JSON-issues/td-p/48851
    $api_response_body = preg_replace('/^[^\{]*/', '', $api_response_body);
    $response_json = json_decode($api_response_body);

    if($response_json->messages->resultCode === 'Error') {
      foreach ($response_json->messages->message as $error) {
        MeprUtils::error_log('Authorize API Error ' . $error->code . '-' . $error->text);
      }
      return null;
    }
    else {
      return $response_json;
    }
  }
}
