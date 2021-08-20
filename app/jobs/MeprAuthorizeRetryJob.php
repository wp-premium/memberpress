<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

require_once(__DIR__ . '/../gateways/MeprAuthorizeAPI.php');
require_once(__DIR__ . '/../gateways/MeprAuthorizeWebhooks.php');

class MeprAuthorizeRetryJob extends MeprBaseJob {

  public function perform() {
    $last_transaction = json_decode($this->transaction_data);
    $authorize_api    = new MeprAuthorizeAPI((object)$this->gateway_settings);
    $auth_transaction = $authorize_api->get_transaction_details($last_transaction->transId);

    if($auth_transaction && $auth_transaction !== '') {
      if(!isset($auth_transaction->transaction->subscription)) {
        throw new Exception( __('No subscription data available', 'memberpress') );
      }
      else {
        $auth_webhook = new MeprAuthorizeWebhooks((object)$this->gateway_settings);
        $auth_webhook->record_subscription_payment($auth_transaction->transaction, false);
      }
    }
    else {
      throw new Exception( __('There was a problem with the Authorize.net API request.', 'memberpress') );
    }
  }

}
