<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

require_once(__DIR__ . '/MeprAuthorizeAPI.php');
require_once(__DIR__ . '/../jobs/MeprAuthorizeRetryJob.php');

class MeprAuthorizeWebhooks {
  private $gateway_settings;
  private $authorize_api;

  public function __construct($gateway_settings, $authorize_api = null) {
    $this->gateway_settings = $gateway_settings;
    // This allows me to pass in a mock API for tests.
    $this->authorize_api = isset($authorize_api) ? $authorize_api : new MeprAuthorizeAPI($gateway_settings);
  }

  /**
  * Validate and process select Authorize.net webhooks
  * @throws MeprGatewayException
  * @return object|false MeprTransaction or false
  */
  public function process_webhook() {
    $request_body = $this->get_input_stream();
    MeprUtils::debug_log('Authorize.net Webhook Received ' . $request_body);
    if($this->validate_webhook($request_body)) {
      MeprUtils::debug_log('Authorize.net Validate Webhook Passed');
      $request_json = json_decode($request_body);
      if($request_json && preg_match('/^net.authorize.payment/', $request_json->eventType)) {
        MeprUtils::debug_log('Authorize.net Valid eventType');
        $auth_transaction = $this->authorize_api->get_transaction_details($request_json->payload->id);
        if($auth_transaction && $auth_transaction !== '') {
          MeprUtils::debug_log('Authorize.net auth_transaction: ' . MeprUtils::object_to_string($auth_transaction));
          switch($request_json->eventType) {
            case 'net.authorize.payment.authcapture.created':
            case 'net.authorize.payment.capture.created':
            case 'net.authorize.payment.fraud.approved':
              if($request_json->payload->responseCode > 1) {
                return $this->record_payment_failure($auth_transaction->transaction);
              }
              else {
                return $this->record_subscription_payment($auth_transaction->transaction);
              }
              break;
            case 'net.authorize.payment.refund.created':
              return $this->record_refund($auth_transaction->transaction);
              break;
            default:
              MeprUtils::debug_log('Authorize.net Webhook not processed: ' . $request_json->eventType);
          }
        }
        else {
          // Transaction details are null
          throw new MeprGatewayException(__('MeprAuthorizeAPI Error: Unable to retrieve transaction details. Check your logs for errors.', 'memberpress'));
        }
      } // We are only listening for payment webhooks
    }
    else {
      throw new MeprGatewayException(__('This is not a valid Webhook! Check your settings.', 'memberpress'));
    }

    return false;
  }

  public function get_input_stream() {
    return file_get_contents("php://input");
  }

  /**
  * Validate the webhook signature from Authorize.net
  * @param string $request_body Raw HTTP request body
  * @return boolean
  */
  private function validate_webhook($request_body) {
    if(isset($_SERVER['HTTP_X_ANET_SIGNATURE'])) {
      $webhook_signature = strtoupper(explode('=', $_SERVER['HTTP_X_ANET_SIGNATURE'])[1]);
      $hashed_body = strtoupper(hash_hmac('sha512', $request_body, $this->gateway_settings->signature_key));
      return $webhook_signature === $hashed_body;
    }
    return false;
  }

  /**
  * Handle payment failure webhook notifications (responseCode > 1)
  * Only used for recurring payments through ARB
  * net.authorize.payment.authcapture.created
  * net.authorize.payment.capture.created
  * net.authorize.payment.fraud.approved
  * @param object $auth_transaction JSON transaction object
  * @return object|false MeprTransaction or false
  */
  private function record_payment_failure($auth_transaction) {
    if(isset($auth_transaction->transId) and !empty($auth_transaction->transId)) {
      $txn_res = MeprTransaction::get_one_by_trans_num($auth_transaction->transId);

      if(is_object($txn_res) and isset($txn_res->id)) {
        $txn = new MeprTransaction($txn_res->id);
        $txn->status = MeprTransaction::$failed_str;
        $txn->store();
      }
      else if(isset($auth_transaction->subscription->id) && $sub = MeprSubscription::get_one_by_subscr_id($auth_transaction->subscription->id) ) {
        $txn = $this->insert_transaction($sub, $auth_transaction, MeprTransaction::$failed_str);

        $sub->status = MeprSubscription::$active_str;
        $sub->gateway = $this->gateway_settings->id;
        $sub->expire_txns();
        $sub->store();
      }
      else {
        return false;
      }

      if(!defined('TESTS_RUNNING')) {
        MeprUtils::send_failed_txn_notices($txn);
      }

      return $txn;
    }

    return false;
  }

  /**
  * Handle successful payment webhook notifications (responseCode 1)
  * Only used for recurring payments through ARB
  * net.authorize.payment.authcapture.created
  * net.authorize.payment.capture.created
  * net.authorize.payment.fraud.approved
  * @param object $auth_transaction JSON transaction object
  * @return object|false MeprTransaction or false
  */
  public function record_subscription_payment($auth_transaction, $setup_job = true) {
    if($setup_job && !isset($auth_transaction->subscription)) {
      // Enqueue a job to try again in 30 minutes
      $job                    = new MeprAuthorizeRetryJob();
      $job->gateway_settings  = $this->gateway_settings;
      $job->transaction_data  = json_encode($auth_transaction);
      $job->enqueue_in('10m'); //Try again in 10 minutes. Then it will retry every 30 minutes after
      return false;
    }

    if(!$sub = MeprSubscription::get_one_by_subscr_id($auth_transaction->subscription->id)) {
      return false;
    }

    $txn = $this->insert_transaction($sub, $auth_transaction, MeprTransaction::$complete_str);

    $sub->status    = MeprSubscription::$active_str;
    $sub->cc_last4  = substr($auth_transaction->payment->creditCard->cardNumber, -4); // Don't get the XXXX part of the string
    $sub->gateway   = $this->gateway_settings->id;
    $sub->store();
    $sub->limit_payment_cycles();

    if(!defined('TESTS_RUNNING')) {
      MeprUtils::send_transaction_receipt_notices($txn);
    }

    return $txn;
  }

  /**
  * Handle payment refund webhook notifications
  * Only used for recurring payments through ARB
  * net.authorize.payment.refund.created
  * @param object $auth_transaction JSON transaction object
  * @return object|false MeprTransaction or false
  */
  private function record_refund($auth_transaction) {
    $txn_res = MeprTransaction::get_one_by_trans_num($auth_transaction->transId);

    if(!isset($txn_res) or empty($txn_res)) { return false; }

    $txn = new MeprTransaction($txn_res->id);

    if($txn->status == MeprTransaction::$refunded_str) { return $txn; }

    $txn->status = MeprTransaction::$refunded_str;
    $txn->store();

    if(!defined('TESTS_RUNNING')) {
      MeprUtils::send_refunded_txn_notices($txn);
    }

    return $txn;
  }

  /**
  * Create a MeprTransaction from the Authorize.net transaction
  * @param object $sub MeprSubscription
  * @param object $auth_transaction AuthorizeNet transaction object
  * @param string $status
  * @return object MeprTransaction
  */
  private function insert_transaction($sub, $auth_transaction, $status) {
    $first_txn = $sub->first_txn();
    if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
      $coupon_id = $sub->coupon_id;
    }
    else {
      $coupon_id = $first_txn->coupon_id;
    }

    $txn = new MeprTransaction();
    $txn->user_id = $sub->user_id;
    $txn->product_id = $sub->product_id;
    $txn->coupon_id = $coupon_id;
    $txn->txn_type = MeprTransaction::$payment_str;
    $txn->status = $status;
    $txn->subscription_id = $sub->id;
    $txn->trans_num = $auth_transaction->transId;
    $txn->gateway = $this->gateway_settings->id;
    $txn->set_gross(isset($auth_transaction->settleAmount) ? $auth_transaction->settleAmount : $auth_transaction->authAmount);
    $txn->store();

    return $txn;
  }
}
