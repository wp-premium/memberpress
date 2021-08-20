<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAuthorizeGateway extends MeprBaseRealGateway {
  public static $order_invoice_str = '_mepr_authnet_order_invoice';

  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name = __("Authorize.net", 'memberpress');
    $this->key = __('authorize', 'memberpress');
    $this->has_spc_form = true;
    $this->set_defaults();

    $this->capabilities = array(
      'process-credit-cards',
      'process-payments',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'send-cc-expirations'
    );

    // Setup the notification actions for this gateway
    $this->notifiers = array(
      'sp' => 'listener',
      'whk' => 'webhook_listener',
    );
    $this->message_pages = array();
  }

  public function load($settings) {
    $this->settings = (object)$settings;
    $this->set_defaults();
  }

  public function set_defaults() {
    if(!isset($this->settings))
      $this->settings = array();

    $this->settings = (object)array_merge(
      array(
        'gateway' => get_class($this),
        'id' => $this->generate_id(),
        'label' => '',
        'use_label' => true,
        'icon' => MEPR_IMAGES_URL . '/checkout/cards.png',
        'use_icon' => true,
        'desc' => __('Pay with your credit card via Authorize.net', 'memberpress'),
        'use_desc' => true,
        //'recurrence_type' => '',
        'login_name' => '',
        'transaction_key' => '',
        'signature_key' => '',
        'force_ssl' => false,
        'catchup_type' => 'proration',
        'debug' => false,
        //'use_cron' => false,
        'test_mode' => false,
        'aimUrl' => '',
        'arbUrl' => ''
      ),
      (array)$this->settings
    );

    $this->id    = $this->settings->id;
    $this->label = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->icon = $this->settings->icon;
    $this->use_icon = $this->settings->use_icon;
    $this->desc = $this->settings->desc;
    $this->use_desc = $this->settings->use_desc;
    //$this->recurrence_type = $this->settings->recurrence_type;
    $this->hash  = strtoupper(substr(md5($this->id),0,20)); // MD5 hashes used for Silent posts can only be 20 chars long

    if($this->is_test_mode()) {
      $this->settings->aimUrl = 'https://test.authorize.net/gateway/transact.dll';
      $this->settings->arbUrl = 'https://apitest.authorize.net/xml/v1/request.api';
    } else {
      $this->settings->aimUrl = 'https://secure2.authorize.net/gateway/transact.dll';
      $this->settings->arbUrl = 'https://api2.authorize.net/xml/v1/request.api';
    }

    // An attempt to correct people who paste in spaces along with their credentials
    $this->settings->login_name      = trim($this->settings->login_name);
    $this->settings->transaction_key = trim($this->settings->transaction_key);
    $this->settings->signature_key   = trim($this->settings->signature_key);
  }

  public function listener() {
    $this->email_status("Silent Post Just Came In (" . $_SERVER['REQUEST_METHOD'] . "):\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->settings->debug);

    if($this->validate_sp_md5()) {
      if(isset($_REQUEST['x_response_code']) && $_REQUEST['x_response_code'] > 1) {
        return $this->record_payment_failure();
      }
      // AUTHORIZE.NET HAS DEPRECATED MD5, BUT SILENT POST IS STILL AROUND
      // WE'RE GOING TO USE SP TO CAPTURE FAILED PAYMENTS STILL
      // else if(isset($_REQUEST['x_subscription_id']) and !empty($_REQUEST['x_subscription_id'])) {
        // $sub = MeprSubscription::get_one_by_subscr_id($_REQUEST['x_subscription_id']);
        // if(!$sub) { return false; }
        // return $this->record_subscription_payment();
      // }
      // else if(strtoupper($_REQUEST['x_type']) == 'VOID' || strtoupper($_REQUEST['x_type']) == 'CREDIT')
        // return $this->record_refund();

      // Nothing applied so let's bail
      return false;
    }
  }

  /**
  * Webhook listener. Responds to select Auth.net webhook notifications.
  *
  */
  public function webhook_listener() {
    $this->email_status("Webhook Just Came In (" . $_SERVER['REQUEST_METHOD'] . "):\n" . MeprUtils::object_to_string($_REQUEST, true) . "\n", $this->settings->debug);
    require_once(__DIR__ . '/MeprAuthorizeWebhooks.php');
    $webhook_handler = new MeprAuthorizeWebhooks($this->settings);
    try {
      $webhook_handler->process_webhook();
    }
    catch(Exception $e) {
      MeprUtils::error_log('MeprAuthorizeGateway Webhook Error: ' . $e->getMessage());
    }
  }

  /**
  * Validate the request using the MD5 hash
  * @deprecated 1.3.49 will be removed in future release
  * @see https://developer.authorize.net/support/hash_upgrade/
  */
  public function validate_sp_md5() {
    // $md5_input = $this->hash . $this->settings->login_name . $_REQUEST['x_trans_id'] . $_REQUEST['x_amount'];
    // $md5 = md5($md5_input);

    // return strtoupper($md5) === strtoupper($_REQUEST['x_MD5_Hash']);

    // AUTHORIZE.NET HAS DEPRECATED MD5, BUT SILENT POST IS STILL AROUND
    // WE'RE GOING TO USE SP TO CAPTURE FAILED PAYMENTS STILL
    return true;
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary -- this method should just be left blank.
    */
  public function process_payment($txn) {
    $mepr_options = MeprOptions::fetch();

    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
    }
    else
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );

    $invoice = $txn->id.'-'.time();

    if( empty($usr->first_name) or empty($usr->last_name) ) {
      $usr->first_name = sanitize_text_field(wp_unslash($_POST['mepr_first_name']));
      $usr->last_name = sanitize_text_field(wp_unslash($_POST['mepr_last_name']));
      $usr->store();
    }

    $args = array( 'x_card_num'    => sanitize_text_field($_POST['mepr_cc_num']),
                   'x_card_code'   => sanitize_text_field($_POST['mepr_cvv_code']),
                   'x_exp_date'    => sprintf('%02d', sanitize_text_field($_POST['mepr_cc_exp_month'])) . '-' . sanitize_text_field($_POST['mepr_cc_exp_year']),
                   'x_amount'      => MeprUtils::format_float($txn->total),
                   'x_description' => $prd->post_title,
                   'x_invoice_num' => $invoice,
                   'x_first_name'  => $usr->first_name,
                   'x_last_name'   => $usr->last_name );

    if($txn->tax_amount > 0.00) {
      $args['x_tax'] = $txn->tax_desc.'<|>'.MeprUtils::format_float($txn->tax_rate, 3).'%<|>'.(string)MeprUtils::format_float($txn->tax_amount);
    }

    if($mepr_options->show_address_fields && $mepr_options->require_address_fields) {
      $args = array_merge( array( 'x_address' => str_replace('&', '&amp;', get_user_meta($usr->ID, 'mepr-address-one', true)),
                                  'x_city'    => get_user_meta($usr->ID, 'mepr-address-city', true),
                                  'x_state'   => get_user_meta($usr->ID, 'mepr-address-state', true),
                                  'x_zip'     => get_user_meta($usr->ID, 'mepr-address-zip', true),
                                  'x_country' => get_user_meta($usr->ID, 'mepr-address-country', true) ), $args );
    }

    //If customer provided a new ZIP code let's add it here
    if(isset($_POST['mepr_zip_post_code']) /* && !empty($_POST['mepr_zip_post_code']) */) {
      $args['x_zip'] = sanitize_text_field(wp_unslash($_POST['mepr_zip_post_code']));
    }

    $args = MeprHooks::apply_filters('mepr_authorize_payment_args', $args, $txn);

    $res = $this->send_aim_request('AUTH_CAPTURE', $args);

    $this->email_status("translated AIM response from Authorize.net: \n" . MeprUtils::object_to_string($res, true) . "\n", $this->settings->debug);

    $txn->trans_num = $res['transaction_id'];
    $txn->store();

    $_POST['x_trans_id']  = $res['transaction_id'];
    $_POST['response']    = $res;

    return $this->record_payment();
  }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() {
    // Make sure there's a valid subscription for this request and this payment hasn't already been recorded
    if( !($sub = MeprSubscription::get_one_by_subscr_id(sanitize_text_field($_POST['x_subscription_id']))) or
        MeprTransaction::get_one_by_trans_num(sanitize_text_field($_POST['x_trans_id'])) ) {
      return false;
    }

    $first_txn = $sub->first_txn();
    if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
      $coupon_id = $sub->coupon_id;
    }
    else {
      $coupon_id = $first_txn->coupon_id;
    }

    $txn = new MeprTransaction();
    $txn->user_id         = $sub->user_id;
    $txn->product_id      = $sub->product_id;
    $txn->txn_type        = MeprTransaction::$payment_str;
    $txn->status          = MeprTransaction::$complete_str;
    $txn->coupon_id       = $coupon_id;
    $txn->trans_num       = sanitize_text_field($_POST['x_trans_id']);
    $txn->subscription_id = $sub->id;
    $txn->gateway         = $this->id;

    $txn->set_gross( sanitize_text_field($_POST['x_amount']) );

    $txn->store();

    $sub->status = MeprSubscription::$active_str;
    $sub->cc_last4 = substr(sanitize_text_field($_POST['x_account_number']), -4); // Don't get the XXXX part of the string
    //$sub->txn_count = sanitize_text_field($_POST['x_subscription_paynum']);
    $sub->gateway = $this->id;
    $sub->store();

    // Not waiting for a silent post here bro ... just making it happen even
    // though totalOccurrences is Already capped in record_create_subscription()
    $sub->limit_payment_cycles();

    MeprUtils::send_transaction_receipt_notices( $txn );
    if(!isset($_REQUEST['silence_expired_cc'])) {
      MeprUtils::send_cc_expiration_notices( $txn ); //Silence this when a user is updating their CC, or they'll get the old card notice
    }

    return $txn;
  }

  /** Used to record a declined payment. */
  public function record_payment_failure() {
    if(isset($_POST['x_trans_id']) and !empty($_POST['x_trans_id'])) {
      $txn_res = MeprTransaction::get_one_by_trans_num(sanitize_text_field($_POST['x_trans_id']));

      if(is_object($txn_res) and isset($txn_res->id)) {
        $txn = new MeprTransaction($txn_res->id);
        $txn->status = MeprTransaction::$failed_str;
        $txn->store();
      }
      else if( isset($_POST['x_subscription_id']) and
               $sub = MeprSubscription::get_one_by_subscr_id(sanitize_text_field($_POST['x_subscription_id'])) ) {
        $first_txn = $sub->first_txn();
        if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
          $coupon_id = $sub->coupon_id;
        }
        else {
          $coupon_id = $first_txn->coupon_id;
        }

        $txn = new MeprTransaction();
        $txn->user_id         = $sub->user_id;
        $txn->product_id      = $sub->product_id;
        $txn->coupon_id       = $coupon_id;
        $txn->txn_type        = MeprTransaction::$payment_str;
        $txn->status          = MeprTransaction::$failed_str;
        $txn->subscription_id = $sub->id;
        $txn->trans_num       = sanitize_text_field($_POST['x_trans_id']);
        $txn->gateway         = $this->id;

        $txn->set_gross( sanitize_text_field($_POST['x_amount']) );

        $txn->store();

        $sub->status = MeprSubscription::$active_str;
        $sub->gateway = $this->id;
        $sub->expire_txns(); //Expire associated transactions for the old subscription
        $sub->store();
      }
      else
        return false; // Nothing we can do here ... so we outta here

      MeprUtils::send_failed_txn_notices($txn);

      return $txn;
    }

    return false;
  }

  /** Used to record a successful payment by the given gateway. It should have
    * the ability to record a successful payment or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_payment()
  {
    if(isset($_POST['x_trans_id']) and !empty($_POST['x_trans_id'])) {
      $obj = MeprTransaction::get_one_by_trans_num(sanitize_text_field($_POST['x_trans_id']));

      if(is_object($obj) and isset($obj->id)) {
        $txn = new MeprTransaction();
        $txn->load_data($obj);
        $usr = $txn->user();

        // Just short circuit if the transaction has already completed
        if($txn->status == MeprTransaction::$complete_str) { return; }

        $txn->status   = MeprTransaction::$complete_str;

        // This will only work before maybe_cancel_old_sub is run
        $upgrade = $txn->is_upgrade();
        $downgrade = $txn->is_downgrade();

        $event_txn = $txn->maybe_cancel_old_sub();
        $txn->store();

        $this->email_status("record_payment: Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

        $prd = $txn->product();

        if( $prd->period_type=='lifetime' ) {
          if( $upgrade ) {
            $this->upgraded_sub($txn, $event_txn);
          }
          else if( $downgrade ) {
            $this->downgraded_sub($txn, $event_txn);
          }
          else {
            $this->new_sub($txn);
          }

          MeprUtils::send_signup_notices( $txn );
        }

        MeprUtils::send_transaction_receipt_notices( $txn );
        if(!isset($_REQUEST['silence_expired_cc'])) {
          MeprUtils::send_cc_expiration_notices( $txn ); //Silence this when a user is updating their CC, or they'll get the old card notice
        }

        return $txn;
      }
    }

    return false;
  }

  public function record_refund() {
    if(strtoupper($_REQUEST['x_type']) == 'CREDIT') {
      // This is all we've got to reference the old sale in a credit
      if(!isset($_POST['x_invoice_num'])) { return false; }

      preg_match('#^(\d+)-#',sanitize_text_field($_POST['x_invoice_num']),$m);
      $txn_id = $m[1];
      $txn_res = MeprTransaction::get_one($txn_id);
    }
    else if(strtoupper($_REQUEST['x_type']) == 'VOID')
      $txn_res = MeprTransaction::get_one_by_trans_num(sanitize_text_field($_POST['x_trans_id']));

    if(!isset($txn_res) or empty($txn_res)) { return false; }

    $txn = new MeprTransaction($txn_res->id);

    // Seriously ... if txn was already refunded what are we doing here?
    if($txn->status == MeprTransaction::$refunded_str) { return $txn->id; }

    $returned_amount = MeprUtils::format_float(sanitize_text_field($_POST['x_amount']));
    $current_amount = MeprUtils::format_float($txn->total);

    if(strtoupper(sanitize_text_field($_POST['x_type'])) == 'CREDIT' and $returned_amount < $current_amount ) {
      $txn->set_gross( $amount );
      $txn->status = MeprTransaction::$complete_str;
    }
    else
      $txn->status = MeprTransaction::$refunded_str;

    $txn->store();

    MeprUtils::send_refunded_txn_notices($txn);

    return $txn->id;
  }

  public function process_refund(MeprTransaction $txn) {}

  public function process_trial_payment($txn) {
    $mepr_options = MeprOptions::fetch();
    $sub = $txn->subscription();

    //Prepare the $txn for the process_payment method
    $txn->set_subtotal($sub->trial_amount);
    $txn->status = MeprTransaction::$pending_str;

    //Attempt processing the payment here - the send_aim_request will throw the exceptions for us
    $this->process_payment($txn);

    return $this->record_trial_payment($txn);
  }

  public function record_trial_payment($txn) {
    $sub = $txn->subscription();

    //Update the txn member vars and store
    $txn->txn_type = MeprTransaction::$payment_str;
    $txn->status = MeprTransaction::$complete_str;
    $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
    $txn->store();

    return true;
  }

  public function authorize_card_before_subscription($txn) {
    $mepr_options = MeprOptions::fetch();

    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
      $sub = $txn->subscription();
    }
    else
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );

    $invoice = $this->create_new_order_invoice($sub);

    $args = array('x_card_num'       => sanitize_text_field($_POST['mepr_cc_num']),
                  'x_card_code'      => sanitize_text_field($_POST['mepr_cvv_code']),
                  'x_exp_date'       => sprintf('%02d', sanitize_text_field($_POST['mepr_cc_exp_month'])) . '-' . sanitize_text_field($_POST['mepr_cc_exp_year']),
                  'x_amount'         => MeprUtils::format_float(MeprHooks::apply_filters('mepr_authorize_auth_only_amount', 1.00, $txn, $sub)),
                  'x_description'    => $prd->post_title,
                  'x_invoice_num'    => $invoice,
                  'x_first_name'     => $usr->first_name,
                  'x_last_name'      => $usr->last_name);

    if($mepr_options->show_address_fields && $mepr_options->require_address_fields) {
      $args = array_merge( array( 'x_address' => str_replace('&', '&amp;', get_user_meta($usr->ID, 'mepr-address-one', true)),
                                  'x_city'    => get_user_meta($usr->ID, 'mepr-address-city', true),
                                  'x_state'   => get_user_meta($usr->ID, 'mepr-address-state', true),
                                  'x_zip'     => get_user_meta($usr->ID, 'mepr-address-zip', true),
                                  'x_country' => get_user_meta($usr->ID, 'mepr-address-country', true) ), $args );
    }

    //If customer provided a new ZIP code let's add it here
    if(isset($_POST['mepr_zip_post_code']) /* && !empty($_POST['mepr_zip_post_code']) */) {
      $args['x_zip'] = sanitize_text_field(wp_unslash($_POST['mepr_zip_post_code']));
    }

    $args = MeprHooks::apply_filters('mepr_authorize_auth_card_args', $args, $txn);

    $res = $this->send_aim_request('AUTH_ONLY', $args);

    //If we made it here than the above response was successful -- otherwise an Exception would have been thrown
    //Now that we know the authorization succeeded, we should void this authorization
    $res2 = $this->send_aim_request('VOID', array('x_trans_id' => $res['transaction_id']));
  }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($txn) {
    $mepr_options = MeprOptions::fetch();

    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
      $sub = $txn->subscription();
    }
    else {
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );
    }

    //Validate card first unless we have a paid trial period as that will go through AIM and validate the card immediately
    if(!$sub->trial || ($sub->trial && $sub->trial_amount <= 0.00)) {
      $this->authorize_card_before_subscription($txn);
    }

    //$invoice = $txn->id.'-'.time();
    $invoice = $this->create_new_order_invoice($sub);

    if( empty($usr->first_name) or empty($usr->last_name) ) {
      $usr->first_name  = sanitize_text_field(wp_unslash($_POST['mepr_first_name']));
      $usr->last_name   = sanitize_text_field(wp_unslash($_POST['mepr_last_name']));
      $usr->store();
    }

    // Default to 9999 for infinite occurrences
    $total_occurrences = $sub->limit_cycles ? $sub->limit_cycles_num : 9999;

    $args = array( "refId" => $invoice,
                   "subscription" => array(
                     "name" => $prd->post_title,
                     "paymentSchedule" => array(
                       "interval" => $this->arb_subscription_interval($sub),
                       // Since Authorize doesn't allow trials that have a different period_type
                       // from the subscription itself we have to do our trials here manually
                       "startDate" => MeprUtils::get_date_from_ts((time() + (($sub->trial)?MeprUtils::days($sub->trial_days):0)), 'Y-m-d'),
                       "totalOccurrences" => $total_occurrences,
                     ),
                     "amount" => MeprUtils::format_float($sub->total), //Use $sub->total here because $txn->amount may be a trial price
                     "payment" => array(
                       "creditCard" => array(
                         "cardNumber" => sanitize_text_field($_POST['mepr_cc_num']),
                         "expirationDate" => sanitize_text_field($_POST['mepr_cc_exp_month']) . '-' . sanitize_text_field($_POST['mepr_cc_exp_year']),
                         "cardCode" => sanitize_text_field($_POST['mepr_cvv_code'])
                       )
                     ),
                     "order" => array(
                       "invoiceNumber" => $invoice,
                       "description" => $prd->post_title
                     ),
                     "billTo" => array(
                       "firstName" => $usr->first_name,
                       "lastName" => $usr->last_name
                     )
                   )
                 );

    if($mepr_options->show_address_fields && $mepr_options->require_address_fields) {
      $args['subscription']['billTo'] =
        array_merge($args['subscription']['billTo'],
                    array("address" => str_replace('&', '&amp;', get_user_meta($usr->ID, 'mepr-address-one', true)),
                          "city"    => get_user_meta($usr->ID, 'mepr-address-city', true),
                          "state"   => get_user_meta($usr->ID, 'mepr-address-state', true),
                          "zip"     => get_user_meta($usr->ID, 'mepr-address-zip', true),
                          "country" => get_user_meta($usr->ID, 'mepr-address-country', true)));
    }

    //If customer provided a new ZIP code let's add it here
    if(isset($_POST['mepr_zip_post_code'])) {
      $args['subscription']['billTo']['zip'] = sanitize_text_field(wp_unslash($_POST['mepr_zip_post_code']));
    }

    $args = MeprHooks::apply_filters('mepr_authorize_create_subscription_args', $args, $txn, $sub);

    $res = $this->send_arb_request('ARBCreateSubscriptionRequest', $args);

    $_POST['txn_id']    = $txn->id;
    $_POST['subscr_id'] = $res->subscriptionId;

    return $this->record_create_subscription();
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    $mepr_options = MeprOptions::fetch();

    if(isset($_POST['txn_id']) and is_numeric($_POST['txn_id'])) {
      $txn                = new MeprTransaction((int)$_POST['txn_id']);
      $sub                = $txn->subscription();
      $sub->subscr_id     = sanitize_text_field($_POST['subscr_id']);
      $sub->status        = MeprSubscription::$active_str;
      $sub->created_at    = gmdate('c');
      $sub->cc_last4      = substr(sanitize_text_field($_POST['mepr_cc_num']),-4); // Seriously ... only grab the last 4 digits!
      $sub->cc_exp_month  = sanitize_text_field($_POST['mepr_cc_exp_month']);
      $sub->cc_exp_year   = sanitize_text_field($_POST['mepr_cc_exp_year']);
      $sub->store();

      // This will only work before maybe_cancel_old_sub is run
      $upgrade   = $sub->is_upgrade();
      $downgrade = $sub->is_downgrade();

      $event_txn = $sub->maybe_cancel_old_sub();

      $old_total = $txn->total; // Save for later

      // If no trial or trial amount is zero then we've got to make
      // sure the confirmation txn lasts through the trial
      if(!$sub->trial || ($sub->trial and $sub->trial_amount <= 0.00)) {
        $day_count = ($sub->trial)?$sub->trial_days:$mepr_options->grace_init_days;

        $txn->expires_at  = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($day_count), 'Y-m-d H:i:s'); // Grace period before txn processes
        $txn->txn_type    = MeprTransaction::$subscription_confirmation_str;
        $txn->status      = MeprTransaction::$confirmed_str;
        $txn->trans_num   = $sub->subscr_id;
        $txn->set_subtotal(0.00); // This txn is just a confirmation txn ... it shouldn't have a cost
        $txn->store();
      }

      if($upgrade) {
        $this->upgraded_sub($sub, $event_txn);
      }
      elseif($downgrade) {
        $this->downgraded_sub($sub, $event_txn);
      }
      else {
        $this->new_sub($sub, true);
      }

      // Artificially set the txn amount for the notifications
      // $txn->set_gross($old_total);

      /// This will only send if there's a new signup
      MeprUtils::send_signup_notices($txn);
    }
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_update_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();

    $sub = new MeprSubscription($sub_id);
    if(!isset($sub->id) || (int)$sub->id <= 0)
      throw new MeprGatewayException( __('Your payment details are invalid, please check them and try again.', 'memberpress') );

    $usr = $sub->user();
    if(!isset($usr->ID) || (int)$usr->ID <= 0)
      throw new MeprGatewayException( __('Your payment details are invalid, please check them and try again.', 'memberpress') );

    $args = array( "refId" => $sub->id,
                   "subscriptionId" => $sub->subscr_id,
                   "subscription" => array(
                     "payment" => array(
                       "creditCard" => array(
                         "cardNumber" => sanitize_text_field($_POST['update_cc_num']),
                         "expirationDate" => sanitize_text_field($_POST['update_cc_exp_month']) . '-' . sanitize_text_field($_POST['update_cc_exp_year']),
                         "cardCode" => sanitize_text_field($_POST['update_cvv_code'])
                       )
                     ),
                     "billTo" => array(
                       "firstName" => $usr->first_name,
                       "lastName" => $usr->last_name
                     )
                   )
                 );

    if($mepr_options->show_address_fields && $mepr_options->require_address_fields) {
      $args['subscription']['billTo'] =
        array_merge($args['subscription']['billTo'],
                    array("address" => str_replace('&', '&amp;', get_user_meta($usr->ID, 'mepr-address-one', true)),
                          "city" => get_user_meta($usr->ID, 'mepr-address-city', true),
                          "state" => get_user_meta($usr->ID, 'mepr-address-state', true),
                          "zip" => get_user_meta($usr->ID, 'mepr-address-zip', true),
                          "country" => get_user_meta($usr->ID, 'mepr-address-country', true)));
    }

    if(isset($_POST['update_zip_post_code'])) {
      $args['subscription']['billTo']['zip'] = sanitize_text_field(wp_unslash($_POST['update_zip_post_code']));
    }

    $args = MeprHooks::apply_filters('mepr_authorize_update_subscription_args', $args, $sub);

    $res = $this->send_arb_request('ARBUpdateSubscriptionRequest', $args);

    // Calculate ARB Catch up payment
    if( $sub->is_expired() &&
        $sub->latest_txn_failed() &&
        !$sub->first_real_payment_failed() && //Only when first payment failed, Authorize.net suspends sub, when we update the card it retries it, so don't catchup
        ($catchup = $sub->calculate_catchup($this->settings->catchup_type)) &&
        $catchup->proration > 0.00 ) {
      // Create Transaction
      $txn = new MeprTransaction();
      $txn->subscription_id = $sub->id;
      $txn->user_id = $sub->user_id;
      $txn->set_subtotal($catchup->proration);
      $txn->prorated = true;
      $txn->product_id = $sub->product_id;
      $txn->gateway = $this->id;

      $now = time();
      $txn->created_at = MeprUtils::ts_to_mysql_date($now);
      $txn->expires_at = MeprUtils::ts_to_mysql_date($catchup->next_billing, 'Y-m-d 23:59:59');

      $txn->store();

      // Bill Catch-Up Payment
      $_POST['mepr_cc_num']       = sanitize_text_field($_POST['update_cc_num']);
      $_POST['mepr_cvv_code']     = sanitize_text_field($_POST['update_cvv_code']);
      $_POST['mepr_cc_exp_month'] = sanitize_text_field($_POST['update_cc_exp_month']);
      $_POST['mepr_cc_exp_year']  = sanitize_text_field($_POST['update_cc_exp_year']);

      $_REQUEST['silence_expired_cc'] = true;
      $this->process_payment($txn);
    }

    return $res;
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // I don't think we need to do anything here
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {}

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {}

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {}

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {}

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);

    if(!isset($sub->id) || (int)$sub->id <= 0)
      throw new MeprGatewayException( __('This subscription is invalid.', 'memberpress') );

    // Should already expire naturally at authorize.net so we have no need
    // to do this when we're "cancelling" because of a natural expiration
    if(!isset($_REQUEST['expire'])) {
      $args = array( "refId" => $sub->id, "subscriptionId" => $sub->subscr_id );
      $args = MeprHooks::apply_filters('mepr_authorize_cancel_subscription_args', $args, $sub);
      $res = $this->send_arb_request('ARBCancelSubscriptionRequest', $args);
    }

    $_POST['subscr_ID'] = $sub->id;
    return $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    $subscr_ID = (isset($_POST['subscr_ID'])) ? (int)$_POST['subscr_ID'] : null;
    $sub = new MeprSubscription($subscr_ID);

    if(!isset($sub->id) || $sub->id <= 0) { return false; }

    // Seriously ... if sub was already cancelled what are we doing here?
    if($sub->status == MeprSubscription::$cancelled_str) { return true; }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if(isset($_REQUEST['expire']))
      $sub->limit_reached_actions();

    if(!isset($_REQUEST['silent']) || ($_REQUEST['silent']==false))
      MeprUtils::send_cancelled_sub_notices($sub);

    return true;
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
    * this is in place so that payment solutions like paypal can redirect
    * before any content is rendered.
    */
  public function process_signup_form($txn) {
    //if($txn->amount <= 0.00) {
    //  MeprTransaction::create_free_transaction($txn);
    //  return;
    //}
  }

  public function display_payment_page($txn) {
    // Nothing here yet
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    wp_enqueue_script('mepr-gateway-checkout', MEPR_JS_URL . '/gateway/checkout.js', array('mepr-checkout-js'), MEPR_VERSION);
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    * Can be overridden if custom scripts are necessary.
    */
  public function enqueue_user_account_scripts() {
    if( MeprUtils::valid_url_param('action','update','GET') && // (routing) Are we on the update credit card page?
        MeprUtils::valid_url_param('sub', null, 'GET') && // (routing) Do we have a sub url parameter?
        MeprSubscription::exists((int)$_GET['sub']) ) { // Does the subscription exist?
      $sub = new MeprSubscription((int)$_GET['sub']);

      // Ensure that the gateway associated with the subscription we're updating is for Authorize.net
      if($sub->gateway == $this->id) {
        wp_enqueue_script('mepr-default-gateway-checkout-js');
      }
    }
  }

  /**
  * Returs the payment for and required fields for the gateway
  */
  public function spc_payment_fields() {
    $payment_method = $this;
    $payment_form_action = 'mepr-authorize-net-payment-form';
    $txn = new MeprTransaction; //FIXME: This is simply for the action mepr-authorize-net-payment-form
    return MeprView::get_string("/checkout/payment_form", get_defined_vars());
  }

  /** This spits out html for the payment form on the registration / payment
    * page for the user to fill out for payment. If we're using an offsite
    * payment solution like PayPal then this method will just redirect to it.
    */
  public function display_payment_form($amount, $usr, $product_id, $txn_id) {
    $prd = new MeprProduct($product_id);
    $coupon = false;
    $mepr_options = MeprOptions::fetch();

    $txn = new MeprTransaction($txn_id);
    $usr = $txn->user();

    //Artifically set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;
    ?>
    <div class="mp_wrapper mp_payment_form_wrapper">
      <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
      <form action="" method="post" id="mepr_authorize_net_payment_form" class="mepr-checkout-form mepr-form mepr-card-form" novalidate>
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn_id; ?>" />
        <?php // Authorize requires a firstname / lastname so if it's hidden on the signup form ...
              // guess what, the user will still have to fill it out here ?>
        <?php if( empty($usr->first_name) or empty($usr->last_name) ): ?>
          <div class="mp-form-row">
            <label><?php _e('First Name', 'memberpress'); ?></label>
            <input type="text" name="mepr_first_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_first_name'])) ? esc_attr($_POST['mepr_first_name']) : $usr->first_name; ?>" />
          </div>

          <div class="mp-form-row">
            <label><?php _e('Last Name', 'memberpress'); ?></label>
            <input type="text" name="mepr_last_name" class="mepr-form-input" value="<?php echo (isset($_POST['mepr_last_name'])) ? esc_attr($_POST['mepr_last_name']) : $usr->last_name; ?>" />
          </div>
        <?php else: ?>
          <div class="mp-form-row">
            <input type="hidden" name="mepr_first_name" value="<?php echo $usr->first_name; ?>" />
            <input type="hidden" name="mepr_last_name" value="<?php echo $usr->last_name; ?>" />
          </div>
        <?php endif; ?>

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('Credit Card Number', 'memberpress'); ?></label>
            <span class="cc-error"><?php _e('Invalid Credit Card Number', 'memberpress'); ?></span>
          </div>
          <input type="tel" class="mepr-form-input cc-number validation" pattern="\d*" autocomplete="cc-number" required />
          <input type="hidden" class="mepr-cc-num" name="mepr_cc_num"/>
          <script>
            jQuery(document).ready(function($) {
              $('input.cc-number').on('change blur', function (e) {
                var num = $(this).val().replace(/ /g, '');
                $('input.mepr-cc-num').val( num );
              });
            });
          </script>
        </div>

        <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('Expiration', 'memberpress'); ?></label>
            <span class="cc-error"><?php _e('Invalid Expiration', 'memberpress'); ?></span>
          </div>
          <input type="tel" class="mepr-form-input cc-exp validation" pattern="\d*" autocomplete="cc-exp" placeholder="mm/yy" required>
          <input type="hidden" class="cc-exp-month" name="mepr_cc_exp_month"/>
          <input type="hidden" class="cc-exp-year" name="mepr_cc_exp_year"/>
          <script>
            jQuery(document).ready(function($) {
              $('input.cc-exp').on('change blur', function (e) {
                var exp = $(this).payment('cardExpiryVal');
                $( 'input.cc-exp-month' ).val( exp.month );
                $( 'input.cc-exp-year' ).val( exp.year );
              });
            });
          </script>
        </div>

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('CVC', 'memberpress'); ?></label>
            <span class="cc-error"><?php _e('Invalid CVC Code', 'memberpress'); ?></span>
          </div>
          <input type="tel" name="mepr_cvv_code" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required />
        </div>

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _e('ZIP/Post Code', 'memberpress'); ?></label>
          </div>
          <input type="text" name="mepr_zip_post_code" class="mepr-form-input" autocomplete="off" value="<?php echo (isset($_POST['mepr_zip_post_code'])) ? esc_attr($_POST['mepr_zip_post_code']) : ''; ?>" required />
        </div>

        <div class="mepr_spacer">&nbsp;</div>

        <input type="submit" class="mepr-submit" value="<?php _e('Submit', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
        <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
      </form>
    </div>
    <?php

    MeprHooks::do_action('mepr-authorize-net-payment-form', $txn);
  }

  public function process_payment_form($txn) {
    //We're just here to update the user's name if they changed it
    $user = $txn->user();
    $first_name = sanitize_text_field(wp_unslash(($_POST['mepr_first_name'])));
    $last_name = sanitize_text_field(wp_unslash(($_POST['mepr_last_name'])));

    if(empty($user->first_name)) {
      update_user_meta($user->ID, 'first_name', $first_name);
    }

    if(empty($user->last_name)) {
      update_user_meta($user->ID, 'last_name', $last_name);
    }

    //Call the parent to handle the rest of this
    parent::process_payment_form($txn);
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if(!isset($_POST['mepr_transaction_id']) || !is_numeric($_POST['mepr_transaction_id'])) {
      $errors[] = __('An unknown error has occurred.', 'memberpress');
    }

    // IF SPC is enabled, we need to bail on validation if 100% off forever coupon was used
    $txn = new MeprTransaction((int)$_POST['mepr_transaction_id']);
    if($txn->coupon_id) {
      $coupon = new MeprCoupon($txn->coupon_id);

      // TODO - need to check if 'dollar' amount discounts also make the price free forever
      // but those are going to be much less likely to be used than 100 'percent' type discounts
      if($coupon->discount_amount == 100 && $coupon->discount_type == 'percent' && ($coupon->discount_mode == 'standard' || $coupon->discount_mode == 'trial-override' || $coupon->discount_mode == 'first-payment')) {
        return $errors;
      }
    }

    // Authorize requires a firstname / lastname so if it's hidden on the signup form ...
    // guess what, the user will still have to fill it out here
    if(!$mepr_options->show_fname_lname &&
        (!isset($_POST['mepr_first_name']) || empty($_POST['mepr_first_name']) ||
         !isset($_POST['mepr_last_name']) || empty($_POST['mepr_last_name']))) {
      $errors[] = __('Your first name and last name must not be blank.', 'memberpress');
    }

    if(!isset($_POST['mepr_cc_num']) || empty($_POST['mepr_cc_num'])) {
      $errors[] = __('You must enter your Credit Card number.', 'memberpress');
    }
    elseif(!$this->is_credit_card_valid($_POST['mepr_cc_num'])) {
      $errors[] = __('Your credit card number is invalid.', 'memberpress');
    }

    if(!isset($_POST['mepr_cvv_code']) || empty($_POST['mepr_cvv_code'])) {
      $errors[] = __('You must enter your CVV code.', 'memberpress');
    }

    return $errors;
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $login_name    = trim($this->settings->login_name);
    $txn_key       = trim($this->settings->transaction_key);
    $signature_key = trim($this->settings->signature_key);
    $test_mode     = ($this->settings->test_mode == 'on' or $this->settings->test_mode == true);
    $debug         = ($this->settings->debug == 'on' or $this->settings->debug == true);
    $force_ssl     = ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true);
    $catchup_type  = $this->settings->catchup_type;
    // $use_cron     = ($this->settings->use_cron == 'on' or $this->settings->use_cron == true);

    ?>
    <table>
      <tr>
        <td><?php _e('API Login ID*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][login_name]" value="<?php echo $login_name; ?>" /></td>
      </tr>
      <tr>
        <td><?php _e('Transaction Key*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][transaction_key]" value="<?php echo $txn_key; ?>" /></td>
      </tr>
      <tr>
        <td><?php _e('Signature Key*:', 'memberpress'); ?></td>
        <td><input type="text" class="mepr-auto-trim" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][signature_key]" value="<?php echo $signature_key; ?>" /></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][test_mode]"<?php checked($test_mode); ?> />&nbsp;<?php _e('Use Authorize.net Sandbox', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][debug]"<?php checked($debug); ?> />&nbsp;<?php _e('Send Authorize.net Debug Emails', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td colspan="2"><input type="checkbox" name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][force_ssl]"<?php checked($force_ssl); ?> />&nbsp;<?php _e('Force SSL', 'memberpress'); ?></td>
      </tr>
      <tr>
        <td><?php _e('Catch Up Payment Type', 'memberpress'); ?></td>
        <td>
          <select name="<?php echo $mepr_options->integrations_str; ?>[<?php echo $this->id;?>][catchup_type]">
            <option value="none" <?php selected($catchup_type, 'none'); ?>><?php _e('None', 'memberpress'); ?></option>
            <option value="period" <?php selected($catchup_type, 'period'); ?>><?php _e('Period', 'memberpress'); ?></option>
            <option value="proration" <?php selected($catchup_type, 'proration'); ?>><?php _e('Proration', 'memberpress'); ?></option>
            <option value="full" <?php selected($catchup_type, 'full'); ?>><?php _e('Full', 'memberpress'); ?></option>
          </select>
        </td>
      </tr>
      <tr>
        <td><?php _e('Webhook URL:', 'memberpress'); ?></td>
        <td>
          <?php MeprAppHelper::clipboard_input($this->notify_url('whk')); ?>
        </td>
      </tr>
      <tr>
        <td><?php _e('Silent Post URL:', 'memberpress'); ?></td>
        <td>
          <?php MeprAppHelper::clipboard_input($this->notify_url('sp')); ?>
        </td>
      </tr>
      <tr>
        <td><?php _e('MD5 Hash Value:', 'memberpress'); ?></td>
        <td>
          <?php MeprAppHelper::clipboard_input($this->hash); ?>
        </td>
      </tr>
    </table>
    <?php
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if( !isset($_POST[$mepr_options->integrations_str][$this->id]['login_name']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['login_name']) )
      $errors[] = __("Login Name field cannot be blank.", 'memberpress');

    if( !isset($_POST[$mepr_options->integrations_str][$this->id]['transaction_key']) or
        empty($_POST[$mepr_options->integrations_str][$this->id]['transaction_key']) )
      $errors[] = __("Transaction Key field cannot be blank.", 'memberpress');

    if(!isset($_POST[$mepr_options->integrations_str][$this->id]['signature_key']) ||
        empty($_POST[$mepr_options->integrations_str][$this->id]['signature_key'])) {
      $errors[] = __("Signature Key field cannot be blank.", 'memberpress');
    }

    return $errors;
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    $sub = new MeprSubscription($sub_id);

    $last4 = isset($_POST['update_cc_num']) ? substr(sanitize_text_field($_POST['update_cc_num']), -4) : $sub->cc_last4;
    $exp_month = isset($_POST['update_cc_exp_month']) ? sanitize_text_field($_POST['update_cc_exp_month']) : $sub->cc_exp_month;
    $exp_year = isset($_POST['update_cc_exp_year']) ? sanitize_text_field($_POST['update_cc_exp_year']) : $sub->cc_exp_year;

    // Only include the full cc number if there are errors
    if(strtolower($_SERVER['REQUEST_METHOD'])=='post' and empty($errors)) {
      $sub->cc_last4 = $last4;
      $sub->cc_exp_month = $exp_month;
      $sub->cc_exp_year = $exp_year;
      $sub->store();

      unset($_POST['update_cvv_code']); // Unset this for security
    }
    else { // If there are errors then show the full cc num ... if it's there
      $last4 = isset($_POST['update_cc_num']) ? sanitize_text_field($_POST['update_cc_num']) : $sub->cc_last4;
    }

    $ccv_code = (isset($_POST['update_cvv_code'])) ? sanitize_text_field($_POST['update_cvv_code']) : '';
    $exp = sprintf('%02d', $exp_month) . " / {$exp_year}";

    ?>
    <div class="mp_wrapper">
      <?php if( $sub->is_expired() &&
                $sub->latest_txn_failed() &&
                !$sub->first_real_payment_failed() && //Only when first payment failed, Authorize.net suspends sub, when we update the card it retries it, so don't catchup
                ($catchup = $sub->calculate_catchup($this->settings->catchup_type)) &&
                $catchup->proration > 0.00 ): ?>
        <div class="mepr_error"><?php printf(__('Note: Because your subscription is expired, when you update your credit card number our system will attempt to bill your card for the prorated amount of %s to catch you up until the next automatic billing.','memberpress'), MeprAppHelper::format_currency($catchup->proration)); ?></div>
      <?php endif; ?>

      <form action="" method="post" id="mepr_authorize_net_update_cc_form" class="mepr-checkout-form mepr-form" novalidate>
        <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
        <div class="mepr_update_account_table">
          <div><strong><?php _e('Update your Credit Card information below', 'memberpress'); ?></strong></div>
          <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
          <div class="mp-form-row">
            <label><?php _e('Credit Card Number', 'memberpress'); ?></label>
            <input type="text" class="mepr-form-input cc-number validation" pattern="\d*" autocomplete="cc-number" placeholder="<?php echo MeprUtils::cc_num($last4); ?>" required />
            <input type="hidden" class="mepr-cc-num" name="update_cc_num"/>
            <script>
              jQuery(document).ready(function($) {
                $('input.cc-number').on('change blur', function (e) {
                  var num = $(this).val().replace(/ /g, '');
                  $('input.mepr-cc-num').val( num );
                });
              });
            </script>
          </div>

          <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('Expiration', 'memberpress'); ?></label>
              <span class="cc-error"><?php _e('Invalid Expiration', 'memberpress'); ?></span>
            </div>
            <input type="text" class="mepr-form-input cc-exp validation" value="<?php echo $exp; ?>" pattern="\d*" autocomplete="cc-exp" placeholder="mm/yy" required>
            <input type="hidden" class="cc-exp-month" name="update_cc_exp_month"/>
            <input type="hidden" class="cc-exp-year" name="update_cc_exp_year"/>
            <script>
              jQuery(document).ready(function($) {
                $('input.cc-exp').on('change blur', function (e) {
                  var exp = $(this).payment('cardExpiryVal');
                  $( 'input.cc-exp-month' ).val( exp.month );
                  $( 'input.cc-exp-year' ).val( exp.year );
                });
              });
            </script>
          </div>

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('CVC', 'memberpress'); ?></label>
              <span class="cc-error"><?php _e('Invalid CVC Code', 'memberpress'); ?></span>
            </div>
            <input type="text" name="update_cvv_code" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required />
          </div>

          <div class="mp-form-row">
            <div class="mp-form-label">
              <label><?php _e('Zip code for Card', 'memberpress'); ?></label>
            </div>
            <input type="text" name="update_zip_post_code" class="mepr-form-input" autocomplete="off" value="" required />
          </div>
        </div>

        <div class="mepr_spacer">&nbsp;</div>

        <input type="submit" class="mepr-submit" value="<?php _e('Update Credit Card', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />
        <?php MeprView::render('/shared/has_errors', get_defined_vars()); ?>
      </form>
    </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    if( !isset($_POST['_mepr_nonce']) or empty($_POST['_mepr_nonce']) or
        !wp_verify_nonce($_POST['_mepr_nonce'], 'mepr_process_update_account_form') )
      $errors[] = __('An unknown error has occurred. Please try again.', 'memberpress');

    if(!isset($_POST['update_cc_num']) || empty($_POST['update_cc_num']))
      $errors[] = __('You must enter your Credit Card number.', 'memberpress');
    elseif(!$this->is_credit_card_valid($_POST['update_cc_num']))
      $errors[] = __('Your credit card number is invalid.', 'memberpress');

    if(!isset($_POST['update_cvv_code']) || empty($_POST['update_cvv_code']))
      $errors[] = __('You must enter your CVV code.', 'memberpress');

    return $errors;
  }

  /** Actually pushes the account update to the payment processor */
  public function process_update_account_form($sub_id) {
    return $this->process_update_subscription($sub_id);
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    return (isset($this->settings->test_mode) and $this->settings->test_mode);
  }

  public function force_ssl() {
    return (isset($this->settings->force_ssl) and ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true));
  }

  protected function send_aim_request($method, $args, $http_method='post') {
    $args = array_merge( array( 'x_login'          => $this->settings->login_name,
                                'x_tran_key'       => $this->settings->transaction_key,
                                'x_type'           => $method,
                                'x_version'        => '3.1',
                                'x_delim_data'     => 'TRUE',
                                'x_delim_char'     => '|',
                                'x_relay_response' => 'FALSE', // NOT SURE about this
                                'x_method'         => 'CC' ), $args );

    $args = MeprHooks::apply_filters('mepr_authorize_send_aim_request_args', $args);

    $remote = array( 'method'      => strtoupper($http_method),
                     'timeout'     => 30,
                     'redirection' => 5,
                     'httpversion' => '1.0',
                     'blocking'    => true,
                     'headers'     => array(),
                     'body'        => $args,
                     'cookies'     => array() );

    $remote = MeprHooks::apply_filters('mepr_authorize_send_aim_request', $remote);

    $this->email_status("Sending AIM request to Authorize.net: \n" . MeprUtils::object_to_string($args, true) . "\n", $this->settings->debug);

    $response = wp_remote_post($this->settings->aimUrl, $remote);

    if(is_wp_error($response))
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s: %s' , 'memberpress'), $this->name, MeprUtils::object_to_string($response) ) );
    else if($response['response']['code'] != '200')
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s: %s' , 'memberpress'), $this->name, MeprUtils::object_to_string($response) ) );

    $answers = explode('|', $response['body']);

    if(empty($answers))
      throw new MeprRemoteException( $response['body'] );

    $this->email_status("AIM response from Authorize.net: \n" . MeprUtils::object_to_string($answers, true) . "\n", $this->settings->debug);

    if(intval($answers[0])==1 or intval($answers[0])==4) {
      return array( "response_code" => $answers[0],
                    "response_subcode" => $answers[1],
                    "response_reason_code" => $answers[2],
                    "response_reason_text" => $answers[3],
                    "authorization_code" => $answers[4],
                    "avs_response" => $answers[5],
                    "transaction_id" => $answers[6],
                    "invoice_number" => $answers[7],
                    "description" => $answers[8],
                    "amount" => $answers[9],
                    "method" => $answers[10],
                    "transaction_type" => $answers[11],
                    "customer_id" => $answers[12],
                    "first_name" => $answers[13],
                    "last_name" => $answers[14],
                    "company" => $answers[15],
                    "address" => $answers[16],
                    "city" => $answers[17],
                    "state" => $answers[18],
                    "zip_code" => $answers[19],
                    "country" => $answers[20],
                    "phone" => $answers[21],
                    "fax" => $answers[22],
                    "email_address" => $answers[23],
                    "ship_to_first_name" => $answers[24],
                    "ship_to_last_name" => $answers[25],
                    "ship_to_company" => $answers[26],
                    "ship_to_address" => $answers[27],
                    "ship_to_city" => $answers[28],
                    "ship_to_state" => $answers[29],
                    "ship_to_zip" => $answers[30],
                    "ship_to_country" => $answers[31],
                    "tax" => $answers[32],
                    "duty" => $answers[33],
                    "freight" => $answers[34],
                    "tax_exempt" => $answers[35],
                    "purchase_order_number" => $answers[36],
                    "md5_hash" => $answers[37],
                    "card_code_reason" => $answers[38],
                    "cardholder_authentication_verification_response" => $answers[39],
                    "account_number" => $answers[40],
                    "card_type" => $answers[51],
                    "split_tender_id" => $answers[52],
                    "requested_amount" => $answers[53],
                    "balance_on_card" => $answers[54] );
    }

    throw new MeprRemoteException( $response['body'] );
  }

  protected function send_arb_request($method, $args, $http_method='post') {
    // This method automatically puts the authentication credentials in place
    $args = array_merge( array( "merchantAuthentication" => array(
                                  "name" => $this->settings->login_name,
                                  "transactionKey" => $this->settings->transaction_key
                                )
                              ),
                         $args );

    $args = MeprHooks::apply_filters('mepr_authorize_send_arb_request_args', $args);

    $content = $this->arb_array_to_xml($method, $args);

    $remote_array = array('method' => strtoupper($http_method),
                          'timeout' => 30,
                          'redirection' => 5,
                          'httpversion' => '1.0',
                          'blocking' => true,
                          'headers' => array('content-type' => 'application/xml'),
                          'body' => $content,
                          'cookies' => array());

    $remote_array = MeprHooks::apply_filters('mepr_authorize_send_arb_request', $remote_array);

    $response = wp_remote_post($this->settings->arbUrl, $remote_array);


    if(is_wp_error($response))
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s: %s' , 'memberpress'), $this->name, MeprUtils::object_to_string($response) ) );
    else if($response['response']['code'] != '200')
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s: %s' , 'memberpress'), $this->name, MeprUtils::object_to_string($response) ) );
    else {
      $answers = $this->simplexml2stdobject(@simplexml_load_string($response['body']));

      $this->email_status( "Got this from AuthorizeNet when sending an arb request \n" .
                           MeprUtils::object_to_string($answers, true) .
                           "\nSent with this XML:\n{$content}\n",
                           $this->settings->debug );

      if(!empty($answers) and strtolower($answers->messages->resultCode) == 'ok')
        return $answers;

      // Prevent long XML from being outputted in the browser
      if(isset($answers->messages->message->code) && isset($answers->messages->message->text)){
        $msg = $answers->messages->message->code . ' - '. $answers->messages->message->text;
        throw new MeprRemoteException( $msg );
      }

      throw new MeprRemoteException( $response['body'] );
    }
  }

  protected function arb_subscription_interval($sub) {
    // Authorize.net doesn't support 'years' or 'weeks' as a unit
    // so we just adjust manually for that case ...
    // and we can't do a longer period with auth.net than
    // one year so just suck it up dude...lol
    if($sub->period_type=='months')
      return array( "length" => $sub->period, "unit" => "months" );
    else if($sub->period_type=='years') {
      $sub->period=1; // Force this down to 1 year
      $sub->store();
      return array( "length" => 12, "unit" => "months" );
    }
    else if($sub->period_type=='weeks')
      return array( "length" => ($sub->period * 7), "unit" => "days" );
  }

  protected function get_order_invoice($sub) {
    return $sub->token;
  }

  protected function create_new_order_invoice($sub) {
    $inv = strtoupper(substr(preg_replace('/\./','',uniqid('',true)),-20));

    $sub->token = $inv;
    $sub->store();

    return $inv;
  }

  // The simplexml objects are not cool ...
  // we want something more vanilla
  protected function simplexml2stdobject($obj) {
    $array = array();
    foreach( (array)$obj as $k => $v )
      $array[$k] = ($v instanceof SimpleXMLElement) ? $this->simplexml2stdobject($v) : $v;
    return (object)$array;
  }

  protected function arb_array_to_xml($method, $array, $level=0) {
    if($level==0) {
      $xml = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n";
      $xml .= "<{$method} xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">\n";
    }
    else
      $xml = '';

    foreach($array as $key => $value ) {
      // Print indentions
      for($i=0; $i < $level+1; $i++) { $xml .= "  "; }

      // Print open tag (looks like we don't need
      // to worry about attributes with this schema)
      $xml .= "<{$key}>";

      // Print value or recursively render sub arrays
      if(is_array($value)) {
        $xml .= "\n";
        $xml .= $this->arb_array_to_xml($method,$value,$level+1);
        // Print indentions for end tag
        for($i=0; $i < $level+1; $i++) { $xml .= "  "; }
      }
      else
        $xml .= $value;

      // Print End tag
      $xml .= "</{$key}>\n";
    }

    if($level==0)
      $xml .= "</{$method}>\n";

    return $xml;
  }
}
