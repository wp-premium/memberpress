<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeGateway extends MeprBaseRealGateway {
  const STRIPE_API_VERSION = '2020-03-02';

  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name = __("Stripe", 'memberpress');
    $this->icon = MEPR_IMAGES_URL . '/checkout/cards.png';
    $this->desc = __('Pay with your credit card via Stripe', 'memberpress');
    $this->key = __('stripe', 'memberpress');
    $this->set_defaults();
    $this->has_spc_form = true;

    $this->capabilities = array(
      'process-credit-cards',
      'process-payments',
      'process-refunds',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'suspend-subscriptions',
      'resume-subscriptions',
      'send-cc-expirations',
      'subscription-trial-payment'
    );

    // Setup the notification actions for this gateway
    $this->notifiers = array(
      'whk' => 'listener',
      'stripe-service-whk' => 'service_listener',
      'update-billing.html' => 'churn_buster'
    );
  }

  public function load($settings) {
    $this->settings = (object)$settings;
    $this->set_defaults();
  }

  protected function set_defaults() {
    if(!isset($this->settings)) {
      $this->settings = array();
    }

    $this->settings = (object)array_merge(
      array(
        'gateway' => 'MeprStripeGateway',
        'id' => $this->generate_id(),
        'label' => '',
        'use_label' => true,
        'use_icon' => true,
        'use_desc' => true,
        'email' => '',
        'sandbox' => false,
        'force_ssl' => false,
        'debug' => false,
        'test_mode' => false,
        'stripe_wallet_enabled' => true,
        'stripe_checkout_enabled' => false,
        'churn_buster_enabled' => false,
        'churn_buster_uuid' => '',
        'api_keys' => array(
          'test' => array(
            'public' => '',
            'secret' => ''
          ),
          'live' => array(
            'public' => '',
            'secret' => ''
          )
        ),
        'connect_status' => false,
        'service_account_id' => '',
        'service_account_name' => '',
      ),
      (array)$this->settings
    );

    $this->id = $this->settings->id;
    $this->label = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->use_icon = $this->settings->use_icon;
    $this->use_desc = $this->settings->use_desc;
    $this->connect_status = $this->settings->connect_status;
    $this->service_account_id = $this->settings->service_account_id;
    $this->service_account_name = $this->settings->service_account_name;
    //$this->recurrence_type = $this->settings->recurrence_type;

    if($this->is_test_mode()) {
      $this->settings->public_key = trim($this->settings->api_keys['test']['public']);
      $this->settings->secret_key = trim($this->settings->api_keys['test']['secret']);
    }
    else {
      $this->settings->public_key = trim($this->settings->api_keys['live']['public']);
      $this->settings->secret_key = trim($this->settings->api_keys['live']['secret']);
    }
  }

  /**
   * @param $subscription MeprSubscription
   *
   * @return bool
   */
  protected function hide_update_link($subscription) {
    if ($subscription->status === MeprSubscription::$suspended_str) {
      return true;
    }

    return false;
  }

  /**
   * @param $name
   * @param integer $rate
   * @param MeprProduct $product
   * @param $inclusive
   *
   * @return object
   * @throws MeprHttpException
   * @throws MeprRemoteException
   */
  public function get_stripe_tax_rate_id($name, $rate, $product, $inclusive = false)
  {
    $tax_request = [
      'inclusive' => 'false',
      'percentage' => MeprUtils::format_float((float) $rate),
    ];

    if (empty($name)) {
      $tax_request['display_name'] = "Tax";
    } else {
      $tax_request['display_name'] = $name;
    }

    if ($inclusive) {
      $tax_request['inclusive'] = 'true';
    }

    $meta_key = '_mepr_stripe_tax_id_' . $this->get_meta_gateway_id() . '_' . md5(serialize($tax_request));

    $tax_id = get_post_meta($product->ID, $meta_key, true);

    if (!empty($tax_id)) {
      return $tax_id;
    }

    $stripe_tax = (object) $this->send_stripe_request('tax_rates', $tax_request);

    update_post_meta($product->ID, $meta_key, $stripe_tax->id);

    return $stripe_tax->id;
  }

  public function get_available_methods_for_recurring_payment() {
    $methods = [
      'card',
    ];

    $mepr_option = MeprOptions::fetch();
    $currency = strtolower($mepr_option->currency_code);

    if ($currency === 'eur') {
      $methods = array_merge($methods, [
        'sepa_debit',
      ]);
    }

    $methods = MeprHooks::apply_filters('mepr-stripe-checkout-methods-for-recurring-payment', $methods);

    return $methods;
  }

  public function get_available_methods_for_onetime_payment() {
    $methods = [
      'card',
    ];

    $mepr_option = MeprOptions::fetch();
    $currency = strtolower($mepr_option->currency_code);

    if ($currency === 'eur') {
      $methods = array_merge($methods, [
        'sepa_debit',
        'ideal',
        'bancontact',
        'giropay',
        'sofort',
        'p24',
      ]);
    }

    $methods = MeprHooks::apply_filters('mepr-stripe-checkout-methods-for-onetime-payment', $methods);

    return $methods;
  }

  /**
   * Create a checkout.session object that will be used to redirect user to
   * checkout.stripe.com
   *
   * @param MeprTransaction $txn
   * @param MeprSubscription $sub
   * @param MeprProduct $product
   * @param MeprUser $usr
   * @param string $payment_method_id
   * @throws Exception
   */
  public function create_checkout_session(
      $txn,
      $product,
      $usr,
      $payment_method_id,
      $sub = null
  ) {
    $mepr_options = MeprOptions::fetch();
    $current_user = MeprUtils::get_currentuserinfo();
    $calculate_taxes = get_option('mepr_calculate_taxes') == true ? true : false;

    if($mepr_options->attr('tax_calc_type') == 'inclusive' && $calculate_taxes && $txn->tax_rate > 0) {
      $tax_inclusive = true;
    } else {
      $tax_inclusive = false;
    }

    $thankyou_page_args = [
      'membership' => sanitize_title($product->post_title),
      'transaction_id' => $txn->id,
      'membership_id' => $product->ID,
    ];

    if($sub instanceof MeprSubscription) {
      $thankyou_page_args = array_merge($thankyou_page_args, ['subscription_id' => $sub->id]);
    }

    $success_url = $mepr_options->thankyou_page_url($thankyou_page_args);
    $cancel_url = esc_url_raw(strtok($_POST['mepr_current_url'], "#"));

    if (empty($cancel_url)) {
      $cancel_url = home_url() . $_SERVER["REQUEST_URI"];
    }

    $customer_id = $this->get_customer_id($usr, $payment_method_id);

    if ($product->is_one_time_payment()) {
      // Reset subtotal so coupon not applied,
      // will create a coupon object to do the discount
      $new_txn = clone $txn;
      $new_txn->set_subtotal($product->adjusted_price());
      $price_id = $this->get_stripe_price_id($sub, $new_txn, $product, $usr);
    } else {
      $price_id = $this->get_stripe_price_id($sub, $txn, $product, $usr);
    }

    if ($tax_inclusive) {
      $tmp_txn = new MeprTransaction();
      $tmp_txn->amount = $product->price;
      $price_id = $this->get_stripe_price_id($sub, $tmp_txn, $product, $usr, $product->price);
    }

    $stripe_product_id = $this->get_product_id($product);
    $coupon = $txn->coupon();

    if($coupon instanceof MeprCoupon) {
      $discount_amount = $this->get_coupon_discount_amount($coupon, $product, $txn->tax_rate);

      if ($discount_amount > 0 && $coupon->discount_mode != 'first-payment') {
        if ($tax_inclusive) {
          if ($coupon->discount_type != 'percent') {
            $stripe_coupon_id = $this->get_coupon_id( $coupon, $this->to_stripe_decimal_amount($coupon->get_discount_amount( $product ) ));
          } else {
            $stripe_coupon_id = $this->get_coupon_id( $coupon, $coupon->get_discount_amount( $product ) );
          }
        } else {
          $stripe_coupon_id = $this->get_coupon_id( $coupon, $discount_amount );
        }
      }

      if ($coupon->discount_mode == 'first-payment'
          && $coupon->get_first_payment_discount_amount($product) > 0) {
        if ($coupon->first_payment_discount_type != 'percent') {
          $stripe_coupon_id = $this->get_coupon_id($coupon, $this->to_stripe_decimal_amount($coupon->get_first_payment_discount_amount($product)), true);
        } else {
          $stripe_coupon_id = $this->get_coupon_id($coupon, $coupon->get_first_payment_discount_amount($product), true);
        }

        if ($product->trial == false && !$product->is_one_time_payment()) {
          $tmp_sub = new MeprSubscription();
          $tmp_sub->id = 0;
          $tmp_sub->user_id = (isset($current_user->ID))?$current_user->ID:0;
          $tmp_sub->load_product_vars($product, null,true);
          $tmp_sub->maybe_prorate();
          $sub->trial_amount = $tmp_sub->price;
          if ($coupon->get_discount_amount($product) > 0) {
            $tmp_coupon = new MeprCoupon();
            $tmp_coupon->discount_amount = $coupon->get_discount_amount($product);
            $tmp_coupon->discount_type = $coupon->discount_type;
            $price = $tmp_coupon->apply_discount($product->price, false, $product);
            $price_id = $this->get_stripe_price_id($sub, $txn, $product, $usr, $price);
          }
        }
      }
    }

    if ($txn->tax_rate > 0 && $calculate_taxes) {
      $tax_rate_id = $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $product, $tax_inclusive);
    }
    if (!$product->is_one_time_payment()) {
      $checkout_session = [
        'customer'=> $customer_id,
        'payment_method_types' => $this->get_available_methods_for_recurring_payment(),
        'line_items' => [[
          'price' => $price_id,
          'quantity' => 1,
        ]],
        'mode' => 'subscription',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'metadata' => [
          'memberpress_subscription_id' => $sub->id,
        ]
      ];
    } else {
      $checkout_session = [
        'payment_method_types' => $this->get_available_methods_for_onetime_payment(),
        'customer'=> $customer_id,
        'line_items' => [[
          'price' => $price_id,
          'quantity' => 1,
        ]],
        'mode' => 'payment',
        'metadata' => [
          'memberpress_transaction_id' => $txn->id,
        ],
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
      ];
    }

    if ($sub instanceof  MeprSubscription && $sub->trial > 0) {
      $checkout_session['subscription_data'] = [
        'trial_period_days' => $sub->trial_days,
      ];

      if ($tax_inclusive) {
        $tax_rate_id = $this->get_stripe_tax_rate_id( $txn->tax_desc, $txn->tax_rate, $product, true );
        $price_include_tax_id = $this->get_stripe_price_id($sub, $txn, $product, $usr, $product->price);

        if ($coupon instanceof MeprCoupon && $coupon->get_discount_amount($product) > 0) {
          $tmp_coupon = new MeprCoupon();
          $tmp_coupon->discount_amount = $coupon->get_discount_amount($product);
          $tmp_coupon->discount_type = $coupon->discount_type;
          $price = $tmp_coupon->apply_discount($product->price, false, $product);
          $price_include_tax_id = $this->get_stripe_price_id($sub, $txn, $product, $usr, $price);
        }

        $checkout_session['line_items'][0]['price'] = $price_include_tax_id;
        $trial_amount = $sub->trial_total;
      } else {
        $trial_amount = $sub->trial_total - $sub->trial_tax_amount;
      }

//      $trial_amount = !empty($product->trial) == false && $sub->trial_amount > 0 ? $sub->trial_amount : $product->price;
      $trial_amount = (self::is_zero_decimal_currency())?MeprUtils::format_float(($trial_amount), 0):MeprUtils::format_float(($trial_amount * 100), 0);
      $trial_plan = [
        'quantity' => 1,
        'price_data' => [
          'currency' => $mepr_options->currency_code,
          'product' => $stripe_product_id,
          'unit_amount' => $trial_amount,
        ]
      ];

      if (isset($tax_rate_id)) {
        $trial_plan['tax_rates'] = [$tax_rate_id];
      }

      $checkout_session['line_items'][] = $trial_plan;
    }

    if (isset($tax_rate_id)) {
      $checkout_session['line_items'][0]['tax_rates'] = [$tax_rate_id];
    }

    if (isset($stripe_coupon_id)) {
      if (!isset($trial_amount) || $product->trial_amount == 0) {
        $checkout_session['discounts'] = [
          [ 'coupon' => $stripe_coupon_id ],
        ];
      }

      if ($coupon->discount_mode == 'first-payment'
          && $coupon->first_payment_discount_amount > 0
          && !$product->is_one_time_payment()
      ) {
        unset($checkout_session['discounts']);
      }
    }

    // active product to use in stripe checkout
    $this->send_stripe_request('products/' . $stripe_product_id, ['active' => 'true']);

    $checkout_session = MeprHooks::apply_filters('mepr_stripe_checkout_session_args', $checkout_session, $txn, $sub);

    $result = $this->send_stripe_request('checkout/sessions', $checkout_session, 'post');
    $result['public_key'] = $this->settings->public_key;
    wp_send_json( $result );
  }

  public function to_stripe_decimal_amount($amount) {
    // Handle zero decimal currencies in Stripe
    $amount = (self::is_zero_decimal_currency())?MeprUtils::format_float($amount, 0):MeprUtils::format_float(($amount * 100), 0);

    return $amount;
  }

  /**
   * Create a PaymentIntent via the Stripe API
   *
   * If the user's card requires no additional action, it will be charged and the PaymentIntent status set to
   * 'succeeded'.
   *
   * If it requires an additional action the card will not be charged, and the PaymentIntent status will be set
   * to 'requires_action'. The confirm_payment_intent() method will charge the card after the action has been
   * successfully completed.
   *
   * @param  MeprTransaction     $txn               The MemberPress transaction
   * @param  MeprUser            $usr               The MemberPress user
   * @param  string              $payment_method_id The Stripe PaymentMethod ID
   * @return stdClass                               The Stripe PaymentIntent data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function create_payment_intent(MeprTransaction $txn, MeprUser $usr, $payment_method_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = $txn->product();
    $customer_id = $this->get_customer_id($usr, $payment_method_id);

    // Handle zero decimal currencies in Stripe
    $amount = (self::is_zero_decimal_currency())?MeprUtils::format_float(($txn->total), 0):MeprUtils::format_float(($txn->total * 100), 0);

    // Create the PaymentIntent on Stripe's servers - if it succeeds the user's card will be charged
    $args = MeprHooks::apply_filters('mepr_stripe_payment_intent_args', array(
      'payment_method' => $payment_method_id,
      'amount' => $amount,
      'currency' => $mepr_options->currency_code,
      'customer' => $customer_id,
      'confirmation_method' => 'manual',
      'confirm' => 'true',
      'setup_future_usage' => 'off_session', // Required to allow rebills to use this card
      'description' => sprintf(__('%s (transaction: %s)', 'memberpress'), $prd->post_title, $txn->id),
      'metadata' => array(
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'transaction_id' => $txn->id,
        'site_url' => esc_url( get_site_url() ),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ),
      'expand' => array(
        'payment_method'
      )
    ), $txn);

    $this->email_status('Stripe PaymentIntent Happening Now ... ' . MeprUtils::object_to_string($args), $this->settings->debug);

    $payment_intent = (object) $this->send_stripe_request('payment_intents', $args, 'post');

    $this->email_status('Stripe PaymentIntent: ' . MeprUtils::object_to_string($payment_intent), $this->settings->debug);

    return $payment_intent;
  }

  /**
   * Confirm a PaymentIntent via the Stripe API
   *
   * If the user's card requires no additional action, it will be charged and the PaymentIntent status set to
   * 'succeeded'.
   *
   * @param  string              $payment_intent_id The Stripe PaymentIntent ID
   * @return stdClass                               The Stripe PaymentIntent data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function confirm_payment_intent($payment_intent_id) {
    // Confirm the PaymentIntent on Stripe's servers - if it succeeds the user's card will be charged
    $args = MeprHooks::apply_filters('mepr_stripe_confirm_payment_intent_args', array(
      'expand' => array(
        'payment_method'
      )
    ));

    $payment_intent = (object) $this->send_stripe_request( "payment_intents/{$payment_intent_id}/confirm", $args, 'post');

    $this->email_status('Stripe PaymentIntent confirm response: ' . MeprUtils::object_to_string($payment_intent), $this->settings->debug);

    return $payment_intent;
  }

  /**
   * Process a successful one-time payment
   *
   * @param  MeprTransaction $txn            The MemberPress transaction
   * @param  stdClass        $payment_intent The Stripe PaymentIntent data
   * @return bool|void
   */
  public function handle_one_time_payment(MeprTransaction $txn, $payment_intent) {
    // Get the Charge from the PaymentIntent
    $charge = (object) $payment_intent->charges['data'][0];

    $txn->trans_num = $charge->id;
    $txn->store();

    $_REQUEST['data'] = $charge;

    return $this->record_payment();
  }

  public function process_payment_form($txn) {
    if(isset($_REQUEST['mepr_payment_methods_hidden']) && $txn->amount == 0.00) {
      MeprTransaction::create_free_transaction($txn);
    }
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
    * before this step is necessary this method should just be left blank.
    */
  public function process_payment($txn) {
    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
    }
    else {
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );
    }

    $mepr_options = MeprOptions::fetch();

    //Handle zero decimal currencies in Stripe
    $amount = (self::is_zero_decimal_currency())?MeprUtils::format_float(($txn->total), 0):MeprUtils::format_float(($txn->total * 100), 0);

    // create the charge on Stripe's servers - this will charge the user's card
    $args = MeprHooks::apply_filters('mepr_stripe_payment_args', array(
      'amount' => $amount,
      'currency' => $mepr_options->currency_code,
      'description' => sprintf(__('%s (transaction: %s)', 'memberpress'), $prd->post_title, $txn->id ),
      'metadata' => array(
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'transaction_id' => $txn->id,
        'site_url' => esc_url( get_site_url() ),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      )
    ), $txn);

    // get the credit card details submitted by the form
    if(isset($_REQUEST['stripeToken'])) {
      $args['card'] = $_REQUEST['stripeToken'];
    }
    else if(isset($_REQUEST['stripe_customer'])) {
      $args['customer'] = $_REQUEST['stripe_customer'];
    }
    else if(isset($_REQUEST['mepr_cc_num'])) {
      $args['card'] = array(
        'number'    => $_REQUEST['mepr_cc_num'],
        'exp_month' => $_REQUEST['mepr_cc_exp_month'],
        'exp_year'  => $_REQUEST['mepr_cc_exp_year'],
        'cvc'       => $_REQUEST['mepr_cvv_code']
      );
    }
    else {
      ob_start();
      print_r($_REQUEST);
      $err = ob_get_clean();
      throw new MeprGatewayException( __('There was a problem sending your credit card details to the processor. Please try again later.' , 'memberpress') . ' 1 ' . $err );
    }

    $this->email_status('Stripe Charge Happening Now ... ' . MeprUtils::object_to_string($args), $this->settings->debug);

    $charge = (object)$this->send_stripe_request( 'charges', $args, 'post' );
    $this->email_status('Stripe Charge: ' . MeprUtils::object_to_string($charge), $this->settings->debug);

    $txn->trans_num = $charge->id;
    $txn->store();

    $this->email_status('Stripe Charge Happening Now ... 2', $this->settings->debug);

    $_REQUEST['data'] = $charge;

    return $this->record_payment();
  }

  /** Used to record a successful recurring payment by the given gateway. It
    * should have the ability to record a successful payment or a failure. It is
    * this method that should be used when receiving an IPN from PayPal or a
    * Silent Post from Authorize.net.
    */
  public function record_subscription_payment() {
    if(isset($_REQUEST['data'])) {
      $charge = (object) $_REQUEST['data'];

      if(!isset($charge, $charge->id, $charge->customer)) {
        return false;
      }

      $subscription = isset($_REQUEST['subscription']) ? (object) $_REQUEST['subscription'] : null;
      $invoice = isset($_REQUEST['invoice']) ? (object) $_REQUEST['invoice'] : null;
      $sub = isset($subscription, $subscription->id) ? MeprSubscription::get_one_by_subscr_id($subscription->id) : null;

      if($sub instanceof MeprSubscription) {
        $subscr_id = $subscription->id;
      } else {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($charge->customer);
        $subscr_id = $charge->customer;
      }

      // Make sure there's a valid subscription for this request and this payment hasn't already been recorded
      if(!($sub instanceof MeprSubscription) || MeprTransaction::txn_exists($charge->id)) {
        return false;
      }

      //If this isn't for us, bail
      if($sub->gateway != $this->id) { return false; }

      $first_txn = $sub->first_txn();

      if($first_txn == false || !($first_txn instanceof MeprTransaction)) {
        $coupon_id = $sub->coupon_id;
      }
      else {
        $coupon_id = $first_txn->coupon_id;
      }

      $txn = new MeprTransaction();
      $txn->user_id    = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->status     = MeprTransaction::$complete_str;
      $txn->coupon_id  = $coupon_id;
      $txn->trans_num  = $charge->id;
      $txn->gateway    = $this->id;
      $txn->subscription_id = $sub->id;

      // If this is the first payment for a paid trial, expire the transaction after the trial ends.
      // The actual number of trial days can be different from $sub->trial_days, for example when resuming, so we'll
      // calculate the trial days from the Stripe subscription data.
      if($subscription &&
        $subscription->status == 'trialing' &&
        isset($invoice->billing_reason) &&
        $invoice->billing_reason == 'subscription_create' &&
        is_numeric($subscription->trial_start) &&
        is_numeric($subscription->trial_end)
      ) {
        $trial_days = ($subscription->trial_end - $subscription->trial_start) / MeprUtils::days(1);

        if($trial_days > 0) {
          $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
        }
      }

      if(self::is_zero_decimal_currency()) {
        $txn->set_gross((float)$charge->amount);
      }
      else {
        $txn->set_gross((float)$charge->amount / 100);
      }

      $txn->store();

      // Reload the subscription in case it was modified while storing the transaction
      $sub = new MeprSubscription($sub->id);

      // Needs to be here to get around some funky GoDaddy caching issue
      $sub->subscr_id = $subscr_id;

      $this->email_status( "record_subscription_payment:" .
        "\nSubscription: " . MeprUtils::object_to_string($sub) .
        "\nTransaction: " . MeprUtils::object_to_string($first_txn),
        $this->settings->debug);

      // Update Stripe Metadata Asynchronously
      $job = new MeprUpdateStripeMetadataJob();
      $job->gateway_settings = $this->settings;
      $job->transaction_id = $txn->id;
      $job->enqueue();

      $sub->status = MeprSubscription::$active_str;

      $payment_method = isset($_REQUEST['payment_method']) ? (object) $_REQUEST['payment_method'] : null;
      if($payment_method) {
        $sub->cc_last4 = $payment_method->card['last4'];
        $sub->cc_exp_month = $payment_method->card['exp_month'];
        $sub->cc_exp_year = $payment_method->card['exp_year'];
      }
      elseif($card = $this->get_card($charge)) {
        $sub->cc_exp_month = $card['exp_month'];
        $sub->cc_exp_year  = $card['exp_year'];
        $sub->cc_last4     = $card['last4'];
      }

      $sub->gateway = $this->id;
      $sub->store();
      // If a limit was set on the recurring cycles we need
      // to cancel the subscr if the txn_count >= limit_cycles_num
      // This is not possible natively with Stripe so we
      // just cancel the subscr when limit_cycles_num is hit
      $sub->limit_payment_cycles();

      $this->email_status( "Subscription Transaction\n" .
                           MeprUtils::object_to_string($txn->rec),
                           $this->settings->debug );

      MeprUtils::send_transaction_receipt_notices( $txn );
      MeprUtils::send_cc_expiration_notices( $txn );

      return $txn;
    }

    return false;
  }

  /**
   * Record a subscription payment that doesn't have an associated charge
   *
   * Called when the invoice payment was 0.00, which can happen if the subscription amount is less than
   * the Stripe minimum payment. We want to record these as subscription payments unless it's the first "payment"
   * of a free trial.
   *
   * @return MeprTransaction|false The created transaction or false if no transaction was created
   */
  public function record_subscription_free_invoice_payment() {
    if(isset($_REQUEST['invoice'], $_REQUEST['invoice']->id)) {
      $invoice = (object) $_REQUEST['invoice'];

      $subscription = isset($_REQUEST['subscription']) ? (object) $_REQUEST['subscription'] : null;
      $sub = isset($subscription, $subscription->id) ? MeprSubscription::get_one_by_subscr_id($subscription->id) : null;

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = isset($invoice->customer['id']) ? MeprSubscription::get_one_by_subscr_id($invoice->customer['id']) : null;
      }

      // Make sure there's a valid subscription for this request and this payment hasn't already been recorded
      if(!($sub instanceof MeprSubscription) || MeprTransaction::txn_exists($invoice->id)) {
        return false;
      }

      // If this is the first invoice "payment" for a free trial, there is no need to create an extra confirmation txn
      if($invoice->billing_reason == 'subscription_create' && $sub->trial && $sub->trial_amount <= 0.00) {
        return false;
      }

      $txn = new MeprTransaction();
      $txn->user_id = $sub->user_id;
      $txn->product_id = $sub->product_id;
      $txn->status = MeprTransaction::$confirmed_str;
      $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
      $txn->trans_num = $invoice->id;
      $txn->gateway = $this->id;
      $txn->subscription_id = $sub->id;
      $txn->set_subtotal(0.00); // Just a confirmation txn

      // If this is the first invoice "payment" for a paid trial, expire the transaction after the trial ends.
      // The actual number of trial days can be different from $sub->trial_days, for example when resuming, so we'll
      // calculate the trial days from the Stripe subscription data.
      if($subscription &&
        $subscription->status == 'trialing' &&
        isset($invoice->billing_reason) &&
        $invoice->billing_reason == 'subscription_create' &&
        is_numeric($subscription->trial_start) &&
        is_numeric($subscription->trial_end)
      ) {
        $trial_days = ($subscription->trial_end - $subscription->trial_start) / MeprUtils::days(1);

        if($trial_days > 0) {
          $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($trial_days), 'Y-m-d 23:59:59');
        }
      }

      $txn->store();

      return $txn;
    }

    return false;
  }

  /**
   * Handle the Stripe `invoice.payment_succeeded` webhook
   *
   * @param  stdClass            $invoice The Stripe Invoice data
   * @throws MeprHttpException            If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException          If there was an invalid or error response from Stripe
   */
  public function handle_invoice_payment_succeeded_webhook($invoice) {
    $this->email_status('Stripe handle_invoice_payment_succeeded_webhook response: ' . MeprUtils::object_to_string($invoice), $this->settings->debug);

    // Fetch expanded invoice data from Stripe
    $args = MeprHooks::apply_filters('mepr_stripe_handle_invoice_payment_succeeded_webhook_args', array(
      'expand' => array(
        'customer',
        'charge',
        'payment_intent',
        'payment_intent.payment_method',
        'subscription',
        'subscription.default_payment_method'
      )
    ), $invoice);

    $invoice = (object) $this->send_stripe_request("invoices/{$invoice->id}", $args, 'get');

    $_REQUEST['invoice'] = $invoice;
    $_REQUEST['subscription'] = (object) $invoice->subscription;

    if(isset($invoice->payment_intent['payment_method'])) {
      $_REQUEST['payment_method'] = (object) $invoice->payment_intent['payment_method'];
    }
    elseif(isset($invoice->subscription['default_payment_method'])) {
      // For free trials, get the payment method from the subscription
      $_REQUEST['payment_method'] = (object) $invoice->subscription['default_payment_method'];
    }

    if($invoice->billing_reason == 'subscription_create') {
      $_REQUEST['data'] = (object) $invoice->customer;

      $this->record_create_subscription();
    }

    if(isset($invoice->charge)) {
      $_REQUEST['data'] = (object) $invoice->charge;

      $this->record_subscription_payment();
    }
    else {
      $this->record_subscription_free_invoice_payment();
    }
  }

  /**
   * Handle the invoice.payment_failed webhook
   *
   * Sends an email to the customer to pay the outstanding invoice.
   *
   * @param stdClass $invoice The Stripe Invoice object
   */
  public function handle_invoice_payment_failed_webhook($invoice) {
    try {
      $email = MeprEmailFactory::fetch('MeprUserStripeInvoiceEmail');

      if($email->enabled() && $invoice->hosted_invoice_url) {
        $sub = MeprSubscription::get_one_by_subscr_id($invoice->subscription);

        if(!($sub instanceof MeprSubscription)) {
          // Look for an old cus_xxx subscription
          $sub = MeprSubscription::get_one_by_subscr_id($invoice->customer);
        }

        if($sub instanceof MeprSubscription && $sub->id > 0 && $sub->txn_count > 1) {
          $usr = $sub->user();

          if($usr->ID > 0) {
            $email->to = $usr->formatted_email();

            $params = array_merge(
              MeprSubscriptionsHelper::get_email_params($sub),
              array('stripe_invoice_url' => $invoice->hosted_invoice_url)
            );

            $email->send($params);
          }
        }
      }
    } catch (Exception $e) {
      // Fail silently
    }
  }

  /** Used to record a declined payment. */
  public function record_payment_failure() {
    if(isset($_REQUEST['data'])) {
      $charge = (object) $_REQUEST['data'];
      $txn_res = MeprTransaction::get_one_by_trans_num($charge->id);

      if(is_object($txn_res) and isset($txn_res->id)) {
        $txn = new MeprTransaction($txn_res->id);
        $txn->status = MeprTransaction::$failed_str;
        $txn->store();
      }
      else {
        // Fetch expanded charge data from Stripe
        $args = [
          'expand' => [
            'invoice'
          ]
        ];

        $charge = (object) $this->send_stripe_request("charges/{$charge->id}", $args, 'get');

        $sub = isset($charge->invoice['subscription']) ? MeprSubscription::get_one_by_subscr_id($charge->invoice['subscription']) : null;

        if(!($sub instanceof MeprSubscription)) {
          // Look for an old cus_xxx subscription
          $sub = isset($charge->customer) ? MeprSubscription::get_one_by_subscr_id($charge->customer) : null;
        }

        if($sub instanceof MeprSubscription) {
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
          $txn->status = MeprTransaction::$failed_str;
          $txn->subscription_id = $sub->id;
          $txn->trans_num = $charge->id;
          $txn->gateway = $this->id;

          if(self::is_zero_decimal_currency()) {
            $txn->set_gross((float)$charge->amount);
          }
          else {
            $txn->set_gross((float)$charge->amount / 100);
          }

          $txn->store();

          // Reload the subscription in case it was modified while storing the transaction
          $sub = new MeprSubscription($sub->id);

          //If first payment fails, Stripe will not set up the subscription, so we need to mark it as cancelled in MP
          if($sub->txn_count == 0 && !($sub->trial && $sub->trial_amount == 0.00)) {
            $sub->status = MeprSubscription::$cancelled_str;
          }

          $sub->gateway = $this->id;
          $sub->expire_txns(); //Expire associated transactions for the old subscription
          $sub->store();
        }
        else {
          return false; // Nothing we can do here ... so we outta here
        }
      }

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
  public function record_payment($charge = null) {
    $this->email_status( "Starting record_payment: " . MeprUtils::object_to_string($_REQUEST), $this->settings->debug );

    if (empty($charge)) {
      $charge = isset($_REQUEST['data']) ? (object)$_REQUEST['data'] : [];
    } else {
      $charge = (object)$charge;
    }

    if(!empty($charge)) {
      $this->email_status("record_payment: \n" . MeprUtils::object_to_string($charge, true) . "\n", $this->settings->debug);
      $obj = MeprTransaction::get_one_by_trans_num($charge->id);

      if(is_object($obj) and isset($obj->id)) {
        $txn = new MeprTransaction();
        $txn->load_data($obj);
        $usr = $txn->user();

        // Just short circuit if the txn has already completed
        if($txn->status == MeprTransaction::$complete_str)
          return;

        $txn->status    = MeprTransaction::$complete_str;
        // This will only work before maybe_cancel_old_sub is run
        $upgrade = $txn->is_upgrade();
        $downgrade = $txn->is_downgrade();

        $event_txn = $txn->maybe_cancel_old_sub();
        $txn->store();

        $this->email_status("Standard Transaction\n" . MeprUtils::object_to_string($txn->rec, true) . "\n", $this->settings->debug);

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
        MeprUtils::send_cc_expiration_notices( $txn );
      }
    }

    return false;
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_refund(MeprTransaction $txn) {
    $args = MeprHooks::apply_filters('mepr_stripe_refund_args', array(), $txn);
    $refund = (object)$this->send_stripe_request( "charges/{$txn->trans_num}/refund", $args );
    $this->email_status( "Stripe Refund: " . MeprUtils::object_to_string($refund), $this->settings->debug );
    $_REQUEST['data'] = $refund;
    return $this->record_refund();
  }

  /** This method should be used by the class to record a successful refund from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function record_refund() {
    if(isset($_REQUEST['data']))
    {
      $charge = (object)$_REQUEST['data'];
      $obj = MeprTransaction::get_one_by_trans_num($charge->id);

      if(!is_null($obj) && (int)$obj->id > 0) {
        $txn = new MeprTransaction($obj->id);

        // Seriously ... if txn was already refunded what are we doing here?
        if($txn->status == MeprTransaction::$refunded_str) { return $txn->id; }

        $txn->status = MeprTransaction::$refunded_str;
        $txn->store();

        MeprUtils::send_refunded_txn_notices($txn);

        return $txn->id;
      }
    }

    return false;
  }

  /* Is this even being used? */
  public function process_trial_payment($txn) {
    $mepr_options = MeprOptions::fetch();
    $sub = $txn->subscription();

    // get the credit card details submitted by the form
    if(isset($_REQUEST['stripeToken']))
      $card = $_REQUEST['stripeToken'];
    elseif(isset($_REQUEST['mepr_cc_num'])) {
      $card = array( 'number'    => $_REQUEST['mepr_cc_num'],
                     'exp_month' => $_REQUEST['mepr_cc_exp_month'],
                     'exp_year'  => $_REQUEST['mepr_cc_exp_year'],
                     'cvc'       => $_REQUEST['mepr_cvv_code'] );
    }
    else {
      throw new MeprGatewayException( __('There was a problem sending your credit card details to the processor. Please try again later.', 'memberpress') );
    }

    $customer = $this->legacy_stripe_customer($txn->subscription_id, $card);

    //Prepare the $txn for the process_payment method
    $txn->set_subtotal($sub->trial_amount);
    $txn->status = MeprTransaction::$pending_str;

    unset($_REQUEST['stripeToken']);
    $_REQUEST['stripe_customer'] = $customer->id;

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

  public function process_stripe_checkout_session_completed($event) {
    $data = $event->data['object'];
    $stripe_subscription_id = isset($data['subscription']) && !empty($data['subscription']) ? $data['subscription'] : null;
    $metadata = $data['metadata'];

    if ($data['mode'] == 'payment') {
      // Processing one time payment notification

      if (isset($metadata['memberpress_transaction_id'])) {
        $_REQUEST['data'] = $data;
        $payment_intent_id = $data['payment_intent'];
        $payment_intent = $this->retrieve_payment_intent($payment_intent_id);
        $charges = $payment_intent->charges['data'];
        $charge = end($charges);

        /** @var MeprTransaction $txn */
        $txn = new MeprTransaction($metadata['memberpress_transaction_id']);
        $txn->trans_num = $charge['id'];
        $txn->store();
        $this->record_payment($charge);
        MeprHooks::do_action('mepr-signup', $txn);
        return;
      }
    }

    if (empty($stripe_subscription_id)) {
      return;
    }

    if (!isset($metadata['memberpress_subscription_id'])) {
      return;
    }

    $memberpress_subscription_id = $metadata['memberpress_subscription_id'];

    $stripe_subscription = (object) $this->send_stripe_request('subscriptions/' . $stripe_subscription_id, [], 'get');

    if ($stripe_subscription->status == 'active' or $stripe_subscription->status == 'trialing') {
      $memberpress_subscription = new MeprSubscription($memberpress_subscription_id);

      if ($memberpress_subscription->gateway && $memberpress_subscription->gateway == $this->id) {
        $memberpress_subscription->subscr_id = $stripe_subscription->id;
        $first_txn = $memberpress_subscription->first_txn();

        if ($first_txn instanceof MeprTransaction) {
          $this->activate_subscription($first_txn, $memberpress_subscription);
          MeprHooks::do_action('mepr-signup', $first_txn);
        }

        $memberpress_subscription->store();
      }
    }
  }

  /** Used to send subscription data to a given payment gateway. In gateways
    * which redirect before this step is necessary this method should just be
    * left blank.
    */
  public function process_create_subscription($txn) {
    if(isset($txn) and $txn instanceof MeprTransaction) {
      $usr = $txn->user();
      $prd = $txn->product();
    }
    else {
      throw new MeprGatewayException( __('Payment was unsuccessful, please check your payment details and try again.', 'memberpress') );
    }

    $mepr_options = MeprOptions::fetch();
    $sub = $txn->subscription();

    // get the credit card details submitted by the form
    if(isset($_REQUEST['stripeToken'])) {
      $card = $_REQUEST['stripeToken'];
    }
    elseif(isset($_REQUEST['mepr_cc_num'])) {
      $card = array(
        'number'    => $_REQUEST['mepr_cc_num'],
        'exp_month' => $_REQUEST['mepr_cc_exp_month'],
        'exp_year'  => $_REQUEST['mepr_cc_exp_year'],
        'cvc'       => $_REQUEST['mepr_cvv_code']
      );
    }
    else {
      if ($this->settings->stripe_checkout_enabled == 'on') {

      } else {
        throw new MeprGatewayException( __('There was a problem sending your credit card details to the processor. Please try again later.', 'memberpress') );
      }
    }

    $customer = $this->legacy_stripe_customer($txn->subscription_id, $card);

    $tax_rate_id = $txn->tax_rate > 0 ? $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd, false) : null;

    if($sub->trial && (float) $sub->trial_amount > 0.00) {
      // Use a temporary transaction to calculate the paid trial amount without tax
      $tmp_txn = new MeprTransaction();
      $tmp_txn->product_id = $prd->ID;
      $tmp_txn->user_id = $usr->ID;
      $tmp_txn->set_subtotal($sub->trial_amount);

      $amount = self::is_zero_decimal_currency() ? MeprUtils::format_float($tmp_txn->amount, 0) : MeprUtils::format_float($tmp_txn->amount * 100, 0);

      $plan = $this->stripe_plan($txn->subscription(), true);

      // For paid trials, add the trial payment amount as an invoice item before creating the subscription
      $args = [
        'customer' => $customer->id,
        'amount' => $amount,
        'currency' => $mepr_options->currency_code,
        'description' => __('Initial Payment', 'memberpress'),
        'metadata' => [
          'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
          'transaction_id' => $txn->id,
          'site_url' => get_site_url(),
          'ip_address' => $_SERVER['REMOTE_ADDR']
        ],
        'discountable' => 'false'
      ];

      if($tax_rate_id) {
        $args['tax_rates'] = [$tax_rate_id];
      }

      $args = MeprHooks::apply_filters('mepr_stripe_paid_trial_invoice_args', $args, $txn, $sub);

      $this->send_stripe_request('invoiceitems', $args, 'post');
    }

    $args = array(
      'plan' => $plan->id,
      'metadata' => array(
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'transaction_id' => $txn->id,
        'site_url' => esc_url( get_site_url() ),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ),
    );

    if($tax_rate_id) {
      $args['tax_rates'] = [$tax_rate_id];
    }

    $args = MeprHooks::apply_filters('mepr_stripe_subscription_args', $args, $txn, $sub);

    if($sub->trial) {
      $args = array_merge(array('trial_period_days' => $sub->trial_days), $args);
    }

    $this->email_status("process_create_subscription: \n" . MeprUtils::object_to_string($txn) . "\n", $this->settings->debug);

    $subscr = $this->send_stripe_request("customers/{$customer->id}/subscriptions", $args);

    $sub->subscr_id = $customer->id;
    $sub->store();

    $this->activate_subscription($txn, $sub);
  }

  /** Used to record a successful subscription by the given gateway. It should have
    * the ability to record a successful subscription or a failure. It is this method
    * that should be used when receiving an IPN from PayPal or a Silent Post
    * from Authorize.net.
    */
  public function record_create_subscription() {
    $mepr_options = MeprOptions::fetch();

    if(isset($_REQUEST['data'])) {
      $customer = (object) $_REQUEST['data'];
      $subscription = isset($_REQUEST['subscription']) ? (object) $_REQUEST['subscription'] : null;
      $payment_method = isset($_REQUEST['payment_method']) ? (object) $_REQUEST['payment_method'] : null;
      $sub = isset($subscription, $subscription->id) ? MeprSubscription::get_one_by_subscr_id($subscription->id) : null;

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($customer->id);
      }

      // Skip if the subscription was not found
      if($sub instanceof MeprSubscription) {
        $sub->status = MeprSubscription::$active_str;

        if($payment_method) {
          $sub->cc_last4 = $payment_method->card['last4'];
          $sub->cc_exp_month = $payment_method->card['exp_month'];
          $sub->cc_exp_year = $payment_method->card['exp_year'];
        }
        elseif($card = $this->get_default_card($customer)) {
          $sub->cc_last4 = $card['last4'];
          $sub->cc_exp_month = $card['exp_month'];
          $sub->cc_exp_year = $card['exp_year'];
        }

        if(empty($sub->created_at)) {
          $sub->created_at = gmdate('c');
        }

        $sub->store();

        // This will only work before maybe_cancel_old_sub is run
        $upgrade = $sub->is_upgrade();
        $downgrade = $sub->is_downgrade();

        $event_txn = $sub->maybe_cancel_old_sub();

        $txn = $sub->first_txn();
        if ($txn == false || !($txn instanceof MeprTransaction)) {
          $txn = new MeprTransaction();
          $txn->user_id = $sub->user_id;
          $txn->product_id = $sub->product_id;
        }

        if ($upgrade) {
          $this->upgraded_sub($sub, $event_txn);
        } else if ($downgrade) {
          $this->downgraded_sub($sub, $event_txn);
        } else {
          $this->new_sub($sub, true);
        }

        MeprUtils::send_signup_notices($txn);

        return array('subscription' => $sub, 'transaction' => $txn);
      }
    }

    return false;
  }

  public function process_update_subscription($sub_id) {
    // This is handled via Ajax
  }

  /**
   * Create a SetupIntent to be used for updating card details for a subscription
   *
   * @param  MeprSubscription    $sub               The MemberPress subscription
   * @param  string              $payment_method_id The Stripe PaymentMethod ID
   * @return stdClass                               The Stripe SetupIntent object
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function create_setup_intent(MeprSubscription $sub, $payment_method_id) {
    $prd = $sub->product();

    // Create the SetupIntent on Stripe's servers
    $args = MeprHooks::apply_filters('mepr_stripe_account_setup_intent_args', array(
      'payment_method' => $payment_method_id,
      'confirm' => 'true',
      'description' => $prd->post_title,
      'metadata' => array(
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'site_url' => esc_url( get_site_url() ),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ),
      'expand' => array(
        'payment_method'
      )
    ));

    $this->email_status('Stripe SetupIntent Happening Now ... ' . MeprUtils::object_to_string($args), $this->settings->debug);

    $intent = (object) $this->send_stripe_request( 'setup_intents', $args, 'post' );

    $this->email_status('Stripe SetupIntent: ' . MeprUtils::object_to_string($intent), $this->settings->debug);

    return $intent;
  }

  /**
   * Update a payment method for a subscription
   *
   * @param  MeprSubscription     $sub              The MemberPress subscription
   * @param  MeprUser             $usr              The MemberPress user
   * @param  stdClass             $payment_method   The Stripe PaymentMethod data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function update_subscription_payment_method(MeprSubscription $sub, MeprUser $usr, $payment_method) {
    // Attach the payment method to the customer and set this as the default payment method for the subscription
    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $subscription = (object) $this->send_stripe_request('subscriptions/' . $sub->subscr_id, [], 'get');
      $this->send_stripe_request('payment_methods/' . $payment_method->id . '/attach', ['customer' => $subscription->customer]);
      $this->send_stripe_request('subscriptions/' . $sub->subscr_id, ['default_payment_method' => $payment_method->id]);

      $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

      if(!is_string($customer_id) || strpos($customer_id, 'cus_') !== 0) {
        // If the Stripe customer ID isn't saved locally for this user, let's save it. This can happen if sub_
        // subscriptions are imported and the cus_ IDs aren't imported for users.
        $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $subscription->customer);
      }
    }
    else {
      $this->stripe_customer($sub->id, $payment_method->id);
    }

    // Save the card details
    $sub->cc_last4 = $payment_method->card['last4'];
    $sub->cc_exp_month = $payment_method->card['exp_month'];
    $sub->cc_exp_year = $payment_method->card['exp_year'];
    $sub->store();
  }

  /**
   * Get the Stripe subscription for the given customer ID
   *
   * @param  string              $customer_id The Stripe customer ID
   * @return object                           The Stripe subscription object
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function get_customer_subscription($customer_id) {
    $args = array(
      'expand' => array(
        'latest_invoice'
      )
    );

    $subscription = (object) $this->send_stripe_request("customers/{$customer_id}/subscription", $args, 'get');

    return $subscription;
  }

  /**
   * Retry the payment for the given invoice ID
   *
   * @param  string              $invoice_id The Stripe invoice ID
   * @throws MeprHttpException               If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException             If there was an invalid or error response from Stripe
   */
  public function retry_invoice_payment($invoice_id) {
    $this->send_stripe_request("invoices/{$invoice_id}/pay", array(), 'post');
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_update_subscription() {
    // No need for this one with stripe
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_suspend_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);

    if($sub->status == MeprSubscription::$suspended_str) {
      throw new MeprGatewayException(__('This subscription has already been paused.', 'memberpress'));
    }

    if(!MeprUtils::is_mepr_admin() && $sub->in_free_trial()) {
      throw new MeprGatewayException(__('Sorry, subscriptions cannot be paused during a free trial.', 'memberpress'));
    }

    $args = MeprHooks::apply_filters('mepr_stripe_suspend_subscription_args', array(), $sub);

    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $res = $this->send_stripe_request("subscriptions/{$sub->subscr_id}", $args, 'delete');
    }
    else {
      // If there's not already a customer then we're done here
      if(!($customer = $this->stripe_customer($sub_id))) { return false; }

      // Yeah ... we're cancelling here bro ... with stripe we should be able to restart again
      $res = $this->send_stripe_request( "customers/{$customer->id}/subscription", $args, 'delete' );
    }

    $_REQUEST['data'] = $res;

    return $this->record_suspend_subscription();
  }

  /** This method should be used by the class to record a successful suspension
    * from the gateway.
    */
  public function record_suspend_subscription() {
    if(isset($_REQUEST['data'])) {
      $subscription = (object) $_REQUEST['data'];

      $sub = MeprSubscription::get_one_by_subscr_id($subscription->id);

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($subscription->customer);
      }

      if($sub instanceof MeprSubscription) {
        // Seriously ... if sub was already cancelled what are we doing here?
        if($sub->status == MeprSubscription::$suspended_str) { return $sub; }

        $sub->status = MeprSubscription::$suspended_str;
        $sub->store();

        MeprUtils::send_suspended_sub_notices($sub);
      }
    }

    return false;
  }

  /** Used to suspend a subscription by the given gateway.
    */
  public function process_resume_subscription($sub_id) {
    $mepr_options = MeprOptions::fetch();
    MeprHooks::do_action('mepr-pre-stripe-resume-subscription', $sub_id); //Allow users to change the subscription programatically before resuming it
    $sub = new MeprSubscription($sub_id);

    if($sub->status == MeprSubscription::$active_str) {
      throw new MeprGatewayException(__('This subscription has already been resumed.', 'memberpress'));
    }

    $orig_trial        = $sub->trial;
    $orig_trial_days   = $sub->trial_days;
    $orig_trial_amount = $sub->trial_amount;
    $tax_inclusive = $mepr_options->attr('tax_calc_type') == 'inclusive';

    if( $sub->is_expired() and !$sub->is_lifetime() ) {
      $expiring_txn = $sub->expiring_txn();

      // if it's already expired with a real transaction
      // then we want to resume immediately
      if( $expiring_txn != false && $expiring_txn instanceof MeprTransaction &&
          $expiring_txn->status!=MeprTransaction::$confirmed_str ) {
        $sub->trial = false;
        $sub->trial_days = 0;
        $sub->trial_amount = 0.00;
        $sub->store();
      }
    }
    else {
      $sub->trial = true;
      $sub->trial_days = MeprUtils::tsdays(strtotime($sub->expires_at) - time());
      $sub->trial_amount = 0.00;
      $sub->store();
    }

    $usr = $sub->user();
    $prd = $sub->product();

    $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());
    $customer_id_valid = is_string($customer_id) && strpos($customer_id, 'cus_') === 0;
    $save_customer_id = false;

    if(!$customer_id_valid) {
      // The user does not have a saved customer ID
      if(strpos($sub->subscr_id, 'cus_') === 0) {
        // For cus_ subscriptions, save the subscription ID as their customer ID
        $customer_id = $sub->subscr_id;
        $customer_id_valid = true;
        $save_customer_id = true;
      }
      else {
        // For sub_ subscriptions, look up the old subscription to find the customer ID
        $old_subscription = $this->retrieve_subscription($sub->subscr_id);

        if(!empty($old_subscription->customer) && is_string($old_subscription->customer)) {
          $customer_id = $old_subscription->customer;
          $customer_id_valid = true;
          $save_customer_id = true;
        }
      }
    }

    if($customer_id_valid) {
      $customer = $this->retrieve_customer($customer_id);

      if($customer->currency && strtolower($customer->currency) != strtolower($mepr_options->currency_code)) {
        throw new MeprGatewayException(__('The subscription is locked to a different currency. Please contact us to resume your subscription.', 'memberpress'));
      }
    }
    else {
      throw new MeprGatewayException(__('Customer not found', 'memberpress'));
    }

    // This needs to be after the currency check above
    if($save_customer_id) {
      $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $customer_id);
    }

    if($tax_inclusive) {
      $plan_id = $this->get_stripe_plan_id($sub, $prd, $usr, $sub->total);
      $tax_rate_id = $sub->tax_rate > 0 ? $this->get_stripe_tax_rate_id($sub->tax_desc, $sub->tax_rate, $prd, true) : null;
    }
    else {
      $prd->price = $sub->price;
      $plan_id = $this->get_stripe_plan_id($sub, $prd, $usr);
      $tax_rate_id = $sub->tax_rate > 0 ? $this->get_stripe_tax_rate_id($sub->tax_desc, $sub->tax_rate, $prd, false) : null;
    }

    $item = ['plan' => $plan_id];

    if($tax_rate_id) {
      $item['tax_rates'] = [$tax_rate_id];
    }

    $args = MeprHooks::apply_filters('mepr_stripe_resume_subscription_args', [
      'customer' => $customer->id,
      'items' => [$item],
      'expand' => [
        'latest_invoice',
      ],
      'metadata' => [
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'site_url' => get_site_url(),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ],
      'off_session' => 'true'
    ], $sub);

    // Specifically set a default_payment_method on the subscription
    if(!empty($customer->invoice_settings['default_payment_method']['id'])) {
      $args = array_merge(['default_payment_method' => $customer->invoice_settings['default_payment_method']['id']], $args);
    }

    if($sub->trial) {
      $args = array_merge(['trial_period_days' => $sub->trial_days], $args);
    }

    $subscription = (object) $this->send_stripe_request('subscriptions', $args);

    $sub->subscr_id    = $subscription->id;
    $sub->trial        = $orig_trial;
    $sub->trial_days   = $orig_trial_days;
    $sub->trial_amount = $orig_trial_amount;
    $sub->store();

    $this->email_status( "process_resume_subscription: \n" . MeprUtils::object_to_string($sub) . "\n", $this->settings->debug );

    $invoice = (object) $subscription->latest_invoice;

    if($invoice->status == 'open') {
      throw new MeprGatewayRequiresActionException(
        sprintf(
          // translators: %1$s: open link tag, %2$s: close link tag
          __('The subscription could not be resumed automatically, %1$sclick here%2$s to complete the payment.', 'memberpress'),
          sprintf('<a href="%s" target="_blank">', esc_url($invoice->hosted_invoice_url)),
          '</a>'
        )
      );
    }

    $_REQUEST['data'] = $customer;
    $_REQUEST['sub'] = $sub;

    return $this->record_resume_subscription();
  }

  /** This method should be used by the class to record a successful resuming of
    * as subscription from the gateway.
    */
  public function record_resume_subscription() {
    if(isset($_REQUEST['data'], $_REQUEST['sub'])) {
      $customer = (object) $_REQUEST['data'];
      $sub = $_REQUEST['sub'];

      if($sub instanceof MeprSubscription) {
        $sub->status = MeprSubscription::$active_str;

        if($card = $this->get_default_card($customer)) {
          $sub->cc_last4 = $card['last4'];
          $sub->cc_exp_month = $card['exp_month'];
          $sub->cc_exp_year = $card['exp_year'];
        }

        $sub->store();

        //Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately
        $prior_txn = $sub->latest_txn();
        if($prior_txn == false || !($prior_txn instanceof MeprTransaction) || strtotime($prior_txn->expires_at) < time()) {
          $txn = new MeprTransaction();
          $txn->subscription_id = $sub->id;
          $txn->trans_num = $sub->subscr_id . '-' . uniqid();
          $txn->status = MeprTransaction::$confirmed_str;
          $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
          $txn->expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days(0), 'Y-m-d 23:59:59');
          $txn->set_subtotal(0.00); // Just a confirmation txn
          $txn->store();
        }

        MeprUtils::send_resumed_sub_notices($sub);

        return array('subscription' => $sub, 'transaction' => (isset($txn)) ? $txn : $prior_txn);
      }
    }

    return false;
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
    * by the class to record a successful cancellation from the gateway. This method
    * should also be used by any IPN requests or Silent Posts.
    */
  public function process_cancel_subscription($sub_id) {
    $sub = new MeprSubscription($sub_id);

    if($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
      throw new MeprGatewayException(__('This subscription has already been cancelled.', 'memberpress'));
    }

    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $endpoint = "subscriptions/{$sub->subscr_id}";
    }
    else {
      // If there's not already a customer then we're done here
      if(!($customer = $this->stripe_customer($sub_id))) { return false; }

      $endpoint = "customers/{$customer->id}/subscription";
    }

    $args = MeprHooks::apply_filters('mepr_stripe_cancel_subscription_args', [], $sub);

    $subscription = $this->send_stripe_request($endpoint, $args, 'delete');

    $_REQUEST['data'] = $subscription;

    return $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
    * from the gateway. This method should also be used by any IPN requests or
    * Silent Posts.
    */
  public function record_cancel_subscription() {
    if(isset($_REQUEST['data'])) {
      $subscription = (object) $_REQUEST['data'];
      $sub = MeprSubscription::get_one_by_subscr_id($subscription->id);

      if(!($sub instanceof MeprSubscription)) {
        // Look for an old cus_xxx subscription
        $sub = MeprSubscription::get_one_by_subscr_id($subscription->customer);
      }

      if($sub instanceof MeprSubscription) {
        // Seriously ... if sub was already cancelled what are we doing here?
        // Also, for stripe, since a suspension is only slightly different
        // than a cancellation, we kick it into high gear and check for that too
        if($sub->status == MeprSubscription::$cancelled_str || $sub->status == MeprSubscription::$suspended_str) {
          return $sub;
        }

        $sub->status = MeprSubscription::$cancelled_str;
        $sub->store();

        if(isset($_REQUEST['expire'])) {
          $sub->limit_reached_actions();
        }

        if(!isset($_REQUEST['silent']) || ($_REQUEST['silent']==false)) {
          MeprUtils::send_cancelled_sub_notices($sub);
        }
      }
    }

    return false;
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
    // Nothing to do here ...
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the page containing the payment form
    */
  public function enqueue_payment_form_scripts() {
    if (wp_script_is('mepr-stripe-form', 'enqueued')) {
      return;
    }

    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), MEPR_VERSION);
    wp_enqueue_script('mepr-stripe-form', MEPR_GATEWAYS_URL . '/stripe/form.js', array('stripe-js', 'mepr-checkout-js', 'jquery.payment'), MEPR_VERSION);
    wp_localize_script('mepr-stripe-form', 'MeprStripeGateway', array(
      'style' => $this->get_element_style(),
      'ajax_url' => admin_url('admin-ajax.php'),
      'hide_postal_code' =>  intval(MeprHooks::apply_filters('mepr-stripe-form-hide-postal-code', false)),
      'ajax_error' => __('An error occurred, please DO NOT submit the form again as you may be double charged. Please contact us for further assistance instead.', 'memberpress'),
      'invalid_response_error' => __('The response from the server was invalid', 'memberpress'),
      'error_please_try_again' => __('An error occurred, please try again', 'memberpress'),
      'top_error' => sprintf(
        // translators: %1$s: open strong tag, %2$s: close strong tag, %3$s: error message
        esc_html__('%1$sERROR%2$s: %3$s', 'memberpress'),
        '<strong>',
        '</strong>',
        '%s'
      )
    ));
  }

  /**
   * Get the styles for the Stripe card element
   *
   * @return array
   */
  private function get_element_style() {
    $style = array(
      'base' => array(
        'lineHeight' => '30px',
        'fontFamily' => 'proxima-nova, sans-serif'
      )
    );

    $style = MeprHooks::apply_filters('mepr-stripe-checkout-element-style', $style);

    return $style;
  }

  /**
  * Returs the payment for and required fields for the gateway
  */
  public function spc_payment_fields() {
    $mepr_options = MeprOptions::fetch();
    $payment_method = $this;
    $payment_form_action = 'mepr-stripe-payment-form';
    $txn = new MeprTransaction; //FIXME: This is simply for the action mepr-authorize-net-payment-form
    $user = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;


      return MeprView::get_string("/checkout/MeprStripeGateway/payment_form", get_defined_vars());

  }

  /** This gets called on the_content and just renders the payment form
    */
  public function display_payment_form($amount, $user, $product_id, $txn_id) {
    $mepr_options = MeprOptions::fetch();
    $prd = new MeprProduct($product_id);
    $coupon = false;

    $txn = new MeprTransaction($txn_id);

    //Artifically set the price of the $prd in case a coupon was used
    if($prd->price != $amount) {
      $coupon = true;
      $prd->price = $amount;
    }

    $invoice = MeprTransactionsHelper::get_invoice($txn);
    echo $invoice;

    ?>
      <div class="mp_wrapper mp_payment_form_wrapper">
        <?php
            $this->display_on_site_form($txn);
        ?>
      </div>
    <?php
  }

  //In the future, this could open the door to Apple Pay and Bitcoin?
  //Bitcoin can NOT be used for auto-recurring subs though - not sure about Apple Pay
  public function display_stripe_checkout_form($txn) {
    $mepr_options = MeprOptions::fetch();
    $user         = $txn->user();
    $prd          = $txn->product();
    $amount       = (self::is_zero_decimal_currency())?MeprUtils::format_float(($txn->total), 0):MeprUtils::format_float(($txn->total * 100), 0);
    //Adjust for trial periods/coupons
    if(($sub = $txn->subscription()) && $sub->trial) {
      $amount = (self::is_zero_decimal_currency())?MeprUtils::format_float(($sub->trial_amount), 0):MeprUtils::format_float(($sub->trial_amount * 100), 0);
    }
    ?>
      <form action="" method="POST">
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn->id; ?>" />
        <input type="hidden" name="mepr_stripe_is_checkout" value="Y" />
        <script
          src="https://checkout.stripe.com/checkout.js"
          class="stripe-button"
          data-amount="<?php echo $amount; ?>"
          data-key="<?php echo $this->settings->public_key; ?>"
          data-image="<?php echo MeprHooks::apply_filters('mepr-stripe-checkout-data-image-url', '', $txn); ?>"
          data-name="<?php echo esc_attr($prd->post_title); ?>"
          data-panel-label="<?php _ex('Submit', 'ui', 'memberpress'); ?>"
          data-label="<?php _ex('Pay Now', 'ui', 'memberpress'); ?>"
          data-zip-code="true"
          data-billing-address="<?php echo ($mepr_options->show_address_fields && $mepr_options->require_address_fields)?'true':'false'; ?>"
          data-email="<?php echo esc_attr($user->user_email); ?>"
          data-currency="<?php echo $mepr_options->currency_code; ?>"
          data-locale="<?php echo $mepr_options->language_code; ?>"
          data-bitcoin="false"> <!-- Eventually we will add bitcoin for non-recurring? -->
        </script>
      </form>
    <?php
  }

  public function display_on_site_form($txn) {
    $mepr_options = MeprOptions::fetch();
    $user         = $txn->user();
    ?>
      <form action="" method="post" id="mepr-stripe-payment-form">
        <input type="hidden" name="mepr_process_payment_form" value="Y" />
        <input type="hidden" name="mepr_transaction_id" value="<?php echo $txn->id; ?>" />
        <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />

        <?php
          if($user instanceof MeprUser) {
            MeprView::render("/checkout/MeprStripeGateway/payment_gateway_fields", get_defined_vars());
          }
        ?>

        <?php if($this->settings->stripe_wallet_enabled == 'on' && $this->settings->stripe_checkout_enabled != 'on') { ?>
          <div class="mepr-stripe-payment-request-wrapper">
            <div id="mepr-stripe-payment-request-element" style="max-width: 300px" class="mepr-stripe-payment-request-element" data-stripe-public-key="<?php echo esc_attr($this->settings->public_key); ?>" data-payment-method-id="<?php echo esc_attr($this->settings->id); ?>" data-locale-code="<?php echo $mepr_options->language_code; ?>" data-currency-code="<?php echo $mepr_options->currency_code; ?>" data-total-text="<?php echo esc_attr(__('Total', 'memberpress')); ?>">
              <!-- a Stripe Payment Request Element will be inserted here. -->
            </div>
            <br>
          </div>
        <?php } ?>
    <?php if($this->settings->stripe_checkout_enabled == 'on'): ?>
      <?php MeprHooks::do_action('mepr-stripe-payment-form-before-name-field', $txn); ?>
      <input type="hidden" name="mepr_stripe_is_checkout" value="1"/>
      <input type="hidden" name="mepr_stripe_checkout_page_mode" value="1"/>
      <div><?php _e('Pay with your Credit Card via Stripe Checkout', 'memberpress'); ?></div>
      <span role="alert" class="mepr-stripe-checkout-errors"></span>
    <?php else: ?>
        <div class="mp-form-row">
          <div class="mp-form-label">
            <label for="mepr_strip_card_name"><?php _e('Name on the card:*', 'memberpress'); ?></label>
            <span class="cc-error"><?php _ex('Name on the card is required.', 'ui', 'memberpress'); ?></span>
          </div>
          <input type="text" name="card-name" id="mepr_strip_card_name" class="mepr-form-input stripe-card-name" required value="<?php echo esc_attr($user->get_full_name()); ?>" />
        </div>

        <div class="mp-form-row">
          <div class="mp-form-label">
            <label><?php _ex('Credit Card', 'ui', 'memberpress'); ?></label>
            <span role="alert" class="mepr-stripe-card-errors"></span>
          </div>
          <div class="mepr-stripe-card-element" data-stripe-public-key="<?php echo esc_attr($this->settings->public_key); ?>" data-payment-method-id="<?php echo esc_attr($this->settings->id); ?>" data-locale-code="<?php echo esc_attr(self::get_locale_code()); ?>">
            <!-- a Stripe Element will be inserted here. -->
          </div>
        </div>

        <?php MeprHooks::do_action('mepr-stripe-payment-form', $txn); ?>
    <?php endif; ?>
        <div class="mepr_spacer">&nbsp;</div>
        <input type="submit" class="mepr-submit" value="<?php _ex('Submit', 'ui', 'memberpress'); ?>" />
        <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />

        <noscript><p class="mepr_nojs"><?php _e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
      </form>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form($errors) {
    // This is done in the javascript with Stripe
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();

    $id = $this->id;
    $integrations = $mepr_options->integrations;
    $whk_url = $this->notify_url( 'whk' );
    $update_billing_url = $this->notify_url( 'update-billing.html', true );

    $test_secret_key      = trim($this->settings->api_keys['test']['secret']);
    $test_public_key      = trim($this->settings->api_keys['test']['public']);
    $live_secret_key      = trim($this->settings->api_keys['live']['secret']);
    $live_public_key      = trim($this->settings->api_keys['live']['public']);
    $force_ssl            = ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true);
    $debug                = ($this->settings->debug == 'on' or $this->settings->debug == true);
    $test_mode            = ($this->settings->test_mode == 'on' or $this->settings->test_mode == true);
    $connect_status       = trim($this->settings->connect_status);
    $service_account_id   = trim($this->settings->service_account_id);
    $service_account_name = stripslashes(trim($this->settings->service_account_name));
    $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' or $this->settings->churn_buster_enabled == true);
    $stripe_checkout_enabled = $this->settings->stripe_checkout_enabled == 'on';
    $stripe_wallet_enabled = $this->settings->stripe_wallet_enabled == 'on' or $this->settings->stripe_wallet_enabled == true;
    $churn_buster_uuid    = trim($this->settings->churn_buster_uuid);

    $test_secret_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][secret]";
    $test_public_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][test][public]";
    $live_secret_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][secret]";
    $live_public_key_str      = "{$mepr_options->integrations_str}[{$this->id}][api_keys][live][public]";
    $force_ssl_str            = "{$mepr_options->integrations_str}[{$this->id}][force_ssl]";
    $debug_str                = "{$mepr_options->integrations_str}[{$this->id}][debug]";
    $test_mode_str            = "{$mepr_options->integrations_str}[{$this->id}][test_mode]";
    $connect_status_string    = "{$mepr_options->integrations_str}[{$this->id}][connect_status]";
    $service_account_id_string= "{$mepr_options->integrations_str}[{$this->id}][service_account_id]";
    $service_account_name_string= "{$mepr_options->integrations_str}[{$this->id}][service_account_name]";
    $churn_buster_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_enabled]";
    $stripe_checkout_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][stripe_checkout_enabled]";
    $stripe_wallet_enabled_str = "{$mepr_options->integrations_str}[{$this->id}][stripe_wallet_enabled]";
    $churn_buster_uuid_str    = "{$mepr_options->integrations_str}[{$this->id}][churn_buster_uuid]";

    $account_email = get_option( 'mepr_authenticator_account_email' );
    $secret = get_option( 'mepr_authenticator_secret_token' );
    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    // $stripe_connect_url = 'https://connect.stripe.com/express/oauth/authorize?response_type=code&amp;client_id=ca_32D88BD1qLklliziD7gYQvctJIhWBSQ7&amp;scope=read_write';

    // If we're logged in then let's present a stripe url otherwise an authenticator url
    if( $account_email && $secret && $site_uuid ) {
      $stripe_connect_url = self::get_stripe_connect_url($this->id);
    }
    else {
      $stripe_connect_url = MeprAuthenticatorCtrl::get_auth_connect_url( true, $this->id );
    }

    if ( ! defined ( 'MEPR_DISABLE_STRIPE_CONNECT' ) ) {
      MeprView::render('/admin/gateways/stripe/connect-migrate-prompt', get_defined_vars());
    }
    MeprView::render('/admin/gateways/stripe/keys', get_defined_vars());
    MeprView::render('/admin/gateways/stripe/checkboxes', get_defined_vars());
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form($errors) {
    $mepr_options = MeprOptions::fetch();

    if ( ! isset( $_REQUEST[ $mepr_options->integrations_str ][ $this->id ]['stripe_wallet_enabled'] ) ) {
      $_POST[ $mepr_options->integrations_str ][ $this->id ]['stripe_wallet_enabled'] = false;
    }

    $testmode = isset($_REQUEST[$mepr_options->integrations_str][$this->id]['test_mode']);
    $testmodestr  = $testmode ? 'test' : 'live';

    // Bail if connecting to a Stripe Connect account, since the keys won't be set at this time
    // Also, bail if the payment method is not currently connected.
    if ( isset( $_REQUEST['stripe_connect_account_number'] ) || self::stripe_connect_status( $this->id ) == 'not-connected' ) {
      return $errors;
    }

    if( !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
         empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['secret']) or
        !isset($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) or
         empty($_REQUEST[$mepr_options->integrations_str][$this->id]['api_keys'][$testmodestr]['public']) ) {
      $errors[] = __("All Stripe keys must be filled in.", 'memberpress');
    }

    return $errors;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
    * scripts for use on the front end user account page.
    */
  public function enqueue_user_account_scripts() {
    $sub = (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['sub'])) ? new MeprSubscription((int)$_GET['sub']) : false;
    if($sub !== false && $sub->gateway == $this->id) {
      wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), MEPR_VERSION . time());
      wp_enqueue_script('stripe-account-create-token', MEPR_GATEWAYS_URL . '/stripe/account_create_token.js', array('stripe-js'), MEPR_VERSION . time());
      wp_localize_script('stripe-account-create-token', 'MeprStripeAccountForm', array(
        'style' => $this->get_element_style(),
        'public_key' => $this->settings->public_key,
        'ajax_url' => admin_url('admin-ajax.php'),
        'ajax_error' => __('Ajax error', 'memberpress'),
        'invalid_response_error' => __('The response from the server was invalid', 'memberpress')
      ));
    }
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form($sub_id, $errors=array(), $message='') {
    $mepr_options = MeprOptions::fetch();
    $sub = new MeprSubscription($sub_id);
    $user = $sub->user();
    if(MeprUtils::is_post_request() && empty($errors) && !empty($_POST['mepr_stripe_update_is_payment'])) {
      $message = __('Update successful, please allow some time for the payment to process. Your account will reflect the updated payment soon.', 'memberpress');
    }
    ?>
      <div class="mp_wrapper">
        <form action="" method="post" id="mepr-stripe-payment-form" data-sub-id="<?php echo esc_attr($sub->id); ?>">
          <input type="hidden" name="_mepr_nonce" value="<?php echo wp_create_nonce('mepr_process_update_account_form'); ?>" />
          <input type="hidden" name="address_required" value="<?php echo $mepr_options->show_address_fields && $mepr_options->require_address_fields ? 1 : 0 ?>" />

          <?php
            if($user instanceof MeprUser) {
              MeprView::render("/checkout/MeprStripeGateway/payment_gateway_fields", get_defined_vars());
            }
          ?>

          <div class="mepr_update_account_table">
            <div><strong><?php _e('Update your Credit Card information below', 'memberpress'); ?></strong></div><br/>
            <div class="mepr-stripe-errors"></div>
            <?php MeprView::render('/shared/errors', get_defined_vars()); ?>

            <div class="mp-form-row">
              <div class="mp-form-label">
                <label for="mepr_strip_card_name"><?php _e('Name on the card:*', 'memberpress'); ?></label>
                <span class="cc-error"><?php _ex('Name on the card is required.', 'ui', 'memberpress'); ?></span>
              </div>
              <input type="text" name="card-name" id="mepr_strip_card_name" class="mepr-form-input stripe-card-name" required value="<?php echo esc_attr($user->get_full_name()); ?>" />
            </div>

            <div class="mp-form-row">
              <div class="mp-form-label">
                <label><?php _ex('Credit Card', 'ui', 'memberpress'); ?></label>
                <span id="card-errors" role="alert" class="mepr-stripe-card-errors"></span>
              </div>
              <div id="card-element" class="mepr-stripe-card-element">
                <!-- a Stripe Element will be inserted here. -->
              </div>
            </div>

            <div class="mepr_spacer">&nbsp;</div>
            <input type="submit" class="mepr-submit" value="<?php _ex('Submit', 'ui', 'memberpress'); ?>" />
            <img src="<?php echo admin_url('images/loading.gif'); ?>" alt="<?php _e('Loading...', 'memberpress'); ?>" style="display: none;" class="mepr-loading-gif" />

            <noscript><p class="mepr_nojs"><?php _e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
          </div>
        </form>
      </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form($errors=array()) {
    return $errors;
  }

  /** Used to update the credit card information on a subscription by the given gateway.
    * This method should be used by the class to record a successful cancellation from
    * the gateway. This method should also be used by any IPN requests or Silent Posts.
    */
  public function process_update_account_form($sub_id) {
    $this->process_update_subscription($sub_id);
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    if (defined('MEMBERPRESS_STRIPE_TESTING') && MEMBERPRESS_STRIPE_TESTING == true) {
      $this->settings->test_mode = true;
      return true;
    }

    return (isset($this->settings->test_mode) && $this->settings->test_mode);
  }

  public function force_ssl() {
    return (isset($this->settings->force_ssl) and ($this->settings->force_ssl == 'on' or $this->settings->force_ssl == true));
  }

  /** Get the renewal base date for a given subscription. This is the date MemberPress will use to calculate expiration dates.
    * Of course this method is meant to be overridden when a gateway requires it.
    */
  public function get_renewal_base_date(MeprSubscription $sub) {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT e.created_at
          FROM {$mepr_db->events} AS e
         WHERE e.event='subscription-resumed'
           AND e.evt_id_type='subscriptions'
           AND e.evt_id=%d
         ORDER BY e.created_at DESC
         LIMIT 1
      ",
      $sub->id
    );

    $renewal_base_date = $wpdb->get_var($q);
    if(!empty($renewal_base_date)) {
      return $renewal_base_date;
    }

    return $sub->created_at;
  }

  /**
   * Process an incoming webhook from the Stripe Connect service
   *
   * @return void
   */
  public function service_listener() {

    $mepr_options = MeprOptions::fetch();

    // retrieve the request's body and parse it as JSON
    $body = @file_get_contents('php://input');

    MeprUtils::debug_log('********* WEBHOOK CONTENTS: ' . $body);
    $header_signature = MeprUtils::get_http_header('Signature');

    if(empty($header_signature)) {
      MeprUtils::debug_log('*** Exiting with no signature');
      MeprUtils::exit_with_status(403, __('No Webhook Signature', 'memberpress'));
    }

    $secret = get_option( 'mepr_authenticator_secret_token' );
    $signature = hash_hmac( 'sha256', $body, $secret );

    MeprUtils::debug_log('********* WEBHOOK SECRETS -- SERVICE: [' . $header_signature . '] LOCAL: [' . $signature . ']');

    if($header_signature != $signature) {
      MeprUtils::debug_log('*** Exiting with incorrect signature');
      MeprUtils::exit_with_status(403, __('Incorrect Webhook Signature', 'memberpress'));
    }

    $body = json_decode($body, true);

    if(!isset($body['event']) || empty($body['event'])) {
      MeprUtils::exit_with_status(403, __('No `event` set', 'memberpress'));
    }

    $event = sanitize_text_field( $body['event'] );

    $auth_site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    if($event == 'update-credentials') {

      $site_uuid = sanitize_text_field( $body['data']['site_uuid'] );
      if($auth_site_uuid != $site_uuid) {
        MeprUtils::exit_with_status(404, __('Request was sent to the wrong site?', 'memberpress'));
      }

      $method_id = sanitize_text_field( $body['data']['payment_method'] );
      $pm = $mepr_options->payment_method($method_id);
      if(empty($pm)) {
        MeprUtils::exit_with_status(404, __('No payment method like that exists on this site', 'memberpress'));
      }

      $pm->update_connect_credentials();

      MeprUtils::debug_log("*** MeprStripeGateway::service_listener stored payment methods [{$method_id}]: " . print_r($mepr_options->integrations[$method_id]['api_keys']['test']['secret'],true));

      wp_send_json( array( 'credentials' => 'saved' ) );

    }

    MeprUtils::exit_with_status(404, __('Webhook not supported', 'memberpress'));
  }

  /** STRIPE SPECIFIC METHODS **/

  public function listener() {
    // retrieve the request's body and parse it as JSON
    $body = @file_get_contents('php://input');
    $event_json = (object)json_decode($body,true);


    if(!isset($event_json->id)) return;

    // Use the id to pull the event directly from the API (purely a security measure)
    try {
      $event = (object)$this->send_stripe_request( "events/{$event_json->id}", array(), 'get' );
    }
    catch( Exception $e ) {
      http_response_code(202); //Throw a 202 here so stripe doesn't send out a billion webhook broken emails
      die($e->getMessage()); // Do nothing
    }
    //$event = $event_json;

    $_REQUEST['data'] = $obj = (object)$event->data['object'];

    if($event->type=='charge.succeeded') {
      // For one time payment with stripe checkout session
    }
    else if($event->type=='charge.failed') {
      $this->record_payment_failure();
    }
    else if($event->type=='charge.refunded') {
      $this->record_refund();
    }
    else if($event->type=='charge.disputed') {
      // Not worried about this right now
    }
    else if($event->type=='customer.subscription.created') {
      //$this->record_create_subscription(); // done on page
    }
    else if($event->type=='checkout.session.completed') {
      $this->process_stripe_checkout_session_completed($event);
    }
    else if($event->type=='customer.subscription.updated') {
      //$this->record_update_subscription(); // done on page
    }
    else if($event->type=='customer.subscription.deleted') {
      $this->record_cancel_subscription();
    }
    else if($event->type=='customer.subscription.trial_will_end') {
      // We may want to implement this feature at some point
    }
    else if($event->type=='invoice.payment_succeeded') {
      $this->handle_invoice_payment_succeeded_webhook($obj);
    }
    else if ($event->type=='invoice.payment_failed') {
      $this->handle_invoice_payment_failed_webhook($obj);
    }
    else if($event->type=='customer.deleted') {
      MeprUser::delete_stripe_customer_id($this->get_meta_gateway_id(), $obj->id);
    }
    else if($event->type=='product.deleted') {
      MeprProduct::delete_stripe_product_id($this->get_meta_gateway_id(), $obj->id);
    }
    else if($event->type=='plan.deleted') {
      MeprProduct::delete_stripe_plan_id($this->get_meta_gateway_id(), $obj->id);
    }
    else if($event->type=='coupon.deleted') {
      MeprCoupon::delete_stripe_coupon_id($this->get_meta_gateway_id(), $obj->id);
    }
  }

  /** Renders a custom page where a credit card can be updated */
  public function churn_buster() {
    $churn_buster_enabled = ($this->settings->churn_buster_enabled == 'on' || $this->settings->churn_buster_enabled == true);
    $uuid = trim($this->settings->churn_buster_uuid);

    if($churn_buster_enabled && !empty($uuid)) {
      $mepr_options = MeprOptions::fetch();
      $company = $mepr_options->attr('biz_name');

      if(empty($company)) {
        $company = MeprUtils::blogname();
      }

      MeprView::render('account/churn_buster', compact('uuid','company'));
    }
    else {
      MeprUtils::exit_with_status(404);
    }
  }

  /**
   * Sends an email to the customer with a link to confirm the payment for a subscription invoice
   *
   * @param  MeprSubscription     $sub The MemberPress subscription
   * @throws MeprGatewayException      If the customer could not be found or the invoice does not require action
   * @throws MeprHttpException         If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException       If there was an invalid or error response from Stripe
   */
  public function send_latest_invoice_payment_email(MeprSubscription $sub) {
    $user = $sub->user();

    if(!$user->ID) {
      throw new MeprGatewayException(__('User not found', 'memberpress'));
    }

    if(strpos($sub->subscr_id, 'sub_') === 0) {
      $endpoint = "subscriptions/{$sub->subscr_id}";
      $subscr_num = $sub->subscr_id;
    }
    else {
      $customer = $this->stripe_customer($sub->id);

      if (!$customer) {
        throw new MeprGatewayException(__('Customer not found', 'memberpress'));
      }

      $endpoint = "customers/{$customer->id}/subscription";
      $subscr_num = $customer->id;
    }

    $args = MeprHooks::apply_filters('mepr_stripe_send_latest_invoice_payment_email_args', array(
      'expand' => array(
        'latest_invoice'
      )
    ), $sub);

    $subscr = (object) $this->send_stripe_request($endpoint, $args, 'get');

    $invoice = (object) $subscr->latest_invoice;

    if ($invoice->status == 'open') {
      /* translators: In this string, %s is the Blog Name/Title */
      $subject = sprintf( __('[%s] Confirm Subscription Payment', 'memberpress'), MeprUtils::blogname());

      $body = MeprView::get_string('/emails/user_confirm_payment', array(
        'blogname' => MeprUtils::blogname(),
        'subscr_num' => $subscr_num,
        'hosted_invoice_url' => $invoice->hosted_invoice_url
      ));

      $message = MeprView::get_string('/emails/template', compact('body'));

      MeprUtils::wp_mail($user->formatted_email(), $subject, $message, array("Content-Type: text/html"));
    } else {
      throw new MeprGatewayException(__('The invoice does not require action from the customer.', 'memberpress'));
    }
  }

  // Originally I thought these should be associated with
  // our membership objects but now I realize they should be
  // associated with our subscription objects
  public function stripe_plan($sub, $is_new = false) {
    $mepr_options = MeprOptions::fetch();
    $prd = $sub->product();

    try {
      if($is_new)
        $plan_id = $this->create_new_plan_id($sub);
      else
        $plan_id = $this->get_plan_id($sub);

      $stripe_plan = $this->send_stripe_request( "plans/{$plan_id}", array(), 'get' );
    }
    catch( Exception $e ) {
      // The call resulted in an error ... meaning that
      // there's no plan like that so let's create one
      if( $sub->period_type == 'months' )
        $interval = 'month';
      else if( $sub->period_type == 'years' )
        $interval = 'year';
      else if( $sub->period_type == 'weeks' )
        $interval = 'week';

      //Setup a new plan ID and store the meta with this subscription
      $new_plan_id = $this->create_new_plan_id($sub);

      //Handle zero decimal currencies in Stripe
      $amount = (self::is_zero_decimal_currency())?MeprUtils::format_float(($sub->price), 0):MeprUtils::format_float(($sub->price * 100), 0);

      $args = MeprHooks::apply_filters('mepr_stripe_create_plan_args', array(
        'amount' => $amount,
        'interval' => $interval,
        'interval_count' => $sub->period,
        'currency' => $mepr_options->currency_code,
        'id' => $new_plan_id,
        'product' => array(
          'name' => $prd->post_title
        )
      ), $sub);

      // Prevent a Stripe error if the user is using the pre-1.6.0 method of setting the statement_descriptor
      if(array_key_exists('statement_descriptor', $args)) {
        $statement_descriptor = $args['statement_descriptor'];
        unset($args['statement_descriptor']);
      }
      else {
        $statement_descriptor = $this->get_statement_descriptor($prd);
      }

      if(strlen($statement_descriptor) > 1) {
        $args['product']['statement_descriptor'] = $statement_descriptor;
      }

      // Don't enclose this in try/catch ... we want any errors to bubble up
      $stripe_plan = $this->send_stripe_request( 'plans', $args );
    }

    return (object)$stripe_plan;
  }

  /**
   * Sanitize the statement descriptor
   *
   * Removes invalid chars and limits the length.
   *
   * @param  string $statement_descriptor
   * @return string
   */
  private function sanitize_statement_descriptor($statement_descriptor) {
    if(!is_string($statement_descriptor)) {
      return '';
    }

    $statement_descriptor = preg_replace('/[^a-zA-Z0-9.\-_ ]/', '', $statement_descriptor);
    $statement_descriptor = trim(substr($statement_descriptor, 0, 22));

    return $statement_descriptor;
  }

  public function get_plan_id($sub) {
    $meta_plan_id = $sub->token;

    if(is_null($meta_plan_id)) {
      return $sub->id;
    }
    else {
      return $meta_plan_id;
    }
  }

  public function create_new_plan_id($sub) {
    $parse = parse_url(home_url());
    $new_plan_id = $sub->id . '-' . $parse['host'] . '-' . uniqid();
    $new_plan_id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $new_plan_id);
    $sub->token = $new_plan_id;
    $sub->store();
    return $new_plan_id;
  }

  /**
   * Create or retrieve the Stripe customer for the given subscription ID
   *
   * If a $payment_method_id is provided it will be set as the default payment method for the customer.
   *
   * @param  int                 $sub_id            The MemberPress subscription ID
   * @param  string|null         $payment_method_id The Stripe PaymentMethod ID
   * @return stdClass|bool                          The Stripe Customer object, or false on failure
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function stripe_customer($sub_id, $payment_method_id = null) {
    $sub              = new MeprSubscription($sub_id);
    $user             = $sub->user();
    $stripe_customer  = null;
    $uid              = uniqid();

    $this->email_status("###{$uid} Stripe Customer (should be blank at this point): \n" . MeprUtils::object_to_string($stripe_customer) . "\n", $this->settings->debug);
    if(strpos($sub->subscr_id, 'cus_') === 0) {
      $stripe_customer = (object)$this->send_stripe_request( 'customers/' . $sub->subscr_id, array(), 'get' );

      if(isset($stripe_customer->error)) {
        return false;
      }

      if (!empty($payment_method_id)) {
        // Attach this payment method to the existing Customer
        try {
          $this->send_stripe_request("payment_methods/{$payment_method_id}/attach", array(
            'customer' => $sub->subscr_id
          ));

          $this->send_stripe_request('customers/' . $sub->subscr_id, array(
            'invoice_settings' => array(
              'default_payment_method' => $payment_method_id
            )
          ));
        } catch (MeprRemoteException $e) {
          // Payment method is already attached to the customer, so make sure this is the default
          $this->send_stripe_request('customers/' . $sub->subscr_id, array(
            'invoice_settings' => array(
              'default_payment_method' => $payment_method_id
            )
          ));
        }
      }
    }
    elseif(!empty($payment_method_id)) {
      $stripe_args = MeprHooks::apply_filters('mepr_stripe_customer_args', array(
        'payment_method' => $payment_method_id,
        'invoice_settings' => array(
          'default_payment_method' => $payment_method_id,
        ),
        'name' => $user->get_full_name(),
        'email' => $user->user_email
      ), $sub);
      $stripe_customer = (object)$this->send_stripe_request( 'customers', $stripe_args );
      $sub->subscr_id = $stripe_customer->id;
      $sub->store();
    }
    else {
      return false;
    }
    $this->email_status("###{$uid} Stripe Customer (should not be blank at this point): \n" . MeprUtils::object_to_string($stripe_customer) . "\n", $this->settings->debug);

    return (object)$stripe_customer;
  }

  /**
   * Retrieve or create a Stripe Customer
   *
   * For Stripe Checkout only, this function sets the non-SCA 'card' property when creating the Customer.
   */
  public function legacy_stripe_customer($sub_id, $cc_token = null) {
    $mepr_options     = MeprOptions::fetch();
    $sub              = new MeprSubscription($sub_id);
    $user             = $sub->user();
    $stripe_customer  = null;
    $uid              = uniqid();

    $this->email_status("###{$uid} Stripe Customer (should be blank at this point): \n" . MeprUtils::object_to_string($stripe_customer, true) . "\n", $this->settings->debug);
    if(strpos($sub->subscr_id, 'cus_') === 0) {
      $stripe_customer = (object)$this->send_stripe_request( 'customers/' . $sub->subscr_id );

      if(isset($stripe_customer->error)) {
        return false;
      }
    }
    elseif(!empty($cc_token)) {
      $stripe_args = MeprHooks::apply_filters('mepr_stripe_customer_args', array(
        'card' => $cc_token,
        'email' => $user->user_email,
        'description' => $user->get_full_name()
      ), $sub);
      $stripe_customer = (object)$this->send_stripe_request( 'customers', $stripe_args );
      $sub->subscr_id = $stripe_customer->id;
      $sub->store();
    }
    else {
      return false;
    }
    $this->email_status("###{$uid} Stripe Customer (should not be blank at this point): \n" . MeprUtils::object_to_string($stripe_customer, true) . "\n", $this->settings->debug);

    return (object)$stripe_customer;
  }

  public function send_stripe_request( $endpoint,
                                       $args=array(),
                                       $method='post',
                                       $domain='https://api.stripe.com/v1/',
                                       $blocking=true,
                                       $idempotency_key=false ) {
    $mepr_options = MeprOptions::fetch();
    $uri = "{$domain}{$endpoint}";

    $args = MeprHooks::apply_filters('mepr_stripe_request_args', $args);

    $arg_array = array(
      'method'    => strtoupper($method),
      'body'      => $args,
      'timeout'   => 15,
      'blocking'  => $blocking,
      'sslverify' => $mepr_options->sslverify,
      'headers'   => $this->get_headers()
    );

    if(false !== $idempotency_key) {
      $arg_array['headers']['Idempotency-Key'] = $idempotency_key;
    }

    $arg_array = MeprHooks::apply_filters('mepr_stripe_request', $arg_array);

    $uid = uniqid();
    // $this->email_status("###{$uid} Stripe Call to {$uri} API Key: {$this->settings->secret_key}\n" . MeprUtils::object_to_string($arg_array, true) . "\n", $this->settings->debug);

    $resp = wp_remote_request( $uri, $arg_array );

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if( $blocking==false )
      return true;

    if( is_wp_error( $resp ) ) {
      throw new MeprHttpException( sprintf( __( 'You had an HTTP error connecting to %s' , 'memberpress'), $this->name ) );
    }
    else {
      if( null !== ( $json_res = json_decode( $resp['body'], true ) ) ) {
        //$this->email_status("###{$uid} Stripe Response from {$uri}\n" . MeprUtils::object_to_string($json_res, true) . "\n", $this->settings->debug);
        if( isset($json_res['error']) )
          throw new MeprRemoteException( "{$json_res['error']['message']} ({$json_res['error']['type']})" );
        else
          return $json_res;
      }
      else // Un-decipherable message
        throw new MeprRemoteException( sprintf( __( 'There was an issue with the credit card processor. Try again later.', 'memberpress'), $this->name ) );
    }

    return false;
  }

  /** Get the default card object from a subscription creation response */
  public function get_default_card($data) {
    $data = (object)$data; // ensure we're dealing with a stdClass object

    if(isset($data->default_source)) { // Added in version 2015-02-15 of stripe's API
      foreach($data->sources['data'] as $source) {
        if($source['id']==$data->default_source) {
          if(isset($source['card']) && is_array($source['card'])) {
            return $source['card'];
          }
          return $source;
        }
      }
    }
    else if(isset($data->default_card)) { // Added in version 2013-07-05 of stripe's API
      foreach($data->cards['data'] as $card) {
        if($card['id']==$data->default_card) { return $card; }
      }
    }
    else if(isset($data->active_card)) { // Removed in version 2013-07-05 of stripe's API
      return $data->active_card;
    }

    return false;
  }

  /** Get card object from a charge response */
  public function get_card($data) {
    // the card object is no longer returned as of 2015-02-18 ... instead it returns 'source'
    if(isset($data->source) && $data->source['object']=='card') {
      return $data->source;
    }
    elseif(isset($data->card)) {
      return $data->card;
    }
  }

  /**
    * Generates the user agent we use to pass to API request so
    * Stripe can identify our application.
    */
  public function get_user_agent() {
    $app_info = [
      'name'       => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
      'version'    => MEPR_VERSION,
      'url'        => 'https://memberpress.com',
      'partner_id' => 'pp_partner_EbxCWHE5ve1yUk',
    ];

    return [
      'lang'         => 'php',
      'lang_version' => phpversion(),
      'publisher'    => 'memberpress',
      'uname'        => php_uname(),
      'application'  => $app_info,
    ];
  }

  /**
   * Generates the headers to pass to API request.
   */
  public function get_headers() {
    $user_agent = $this->get_user_agent();
    $app_info   = $user_agent['application'];

    return apply_filters(
      'mepr_stripe_request_headers', [
        'Authorization'              => 'Basic ' . base64_encode("{$this->settings->secret_key}:"),
        'Stripe-Version'             => self::STRIPE_API_VERSION,
        'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
        'X-Stripe-Client-User-Agent' => json_encode( $user_agent ),
      ]
    );
  }

  /**
   * Assembles the URL for redirecting to Stripe Connect
   *
   * @param  string   $id   Payment ID
   *
   * @return string
   */
  public static function get_stripe_connect_url($method_id) {

    $base_return_url = add_query_arg( array(
        'action' => 'mepr_stripe_connect_update_creds',
        '_wpnonce' => wp_create_nonce( 'stripe-update-creds' )
      ),
      admin_url( 'admin-ajax.php' )
    );

    $error_url = add_query_arg( array(
      'mepr-action' => 'error'
    ), $base_return_url );

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    if ( empty( $site_uuid ) ) {
      return false;
    }

    $mepr_options = MeprOptions::fetch();
    $pm = new MeprStripeGateway();
    $pm->load(array('id'=>$method_id));

    $payload = array(
      'method_id' => $pm->id,
      'site_uuid' => $site_uuid,
      'user_uuid' => get_option( 'mepr_authenticator_user_uuid' ),
      'return_url'=> $base_return_url,
      'error_url' => $error_url,
      'webhook_url'  => $pm->notify_url( 'whk' ),
      'service_webhook_url' => $pm->notify_url( 'stripe-service-whk' ),
      'mp_version' => MEPR_VERSION
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );
    return MEPR_STRIPE_SERVICE_URL  . "/connect/{$site_uuid}/{$method_id}/{$jwt}";
  }

  public static function get_stripe_connect_button_url($method_id) {
    MeprAuthenticatorCtrl::get_auth_connect_url( true, $id );
  }

  public static function keys_are_set( $method_id ) {
    $mepr_options = MeprOptions::fetch();

    if (!isset($mepr_options->integrations[$method_id])) {
      return false;
    }

    $integ = $mepr_options->integrations[$method_id];

    return (
      ( isset($integ['api_keys']['test']['public']) && !empty($integ['api_keys']['test']['public']) ) ||
      ( isset($integ['api_keys']['test']['secret']) && !empty($integ['api_keys']['test']['secret']) ) ||
      ( isset($integ['api_keys']['live']['public']) && !empty($integ['api_keys']['live']['public']) ) ||
      ( isset($integ['api_keys']['live']['secret']) && !empty($integ['api_keys']['live']['secret']) )
    );
  }

  public static function is_stripe_connect( $method_id ) {
    $connect_status = self::stripe_connect_status( $method_id );
    return ($connect_status !== 'not-connected');
  }

  public static function stripe_connect_status( $method_id ) {
    $mepr_options = MeprOptions::fetch();

    if (!isset($mepr_options->integrations[$method_id])) {
      return 'not-connected';
    }

    $integ = $mepr_options->integrations[$method_id];

    return ( !isset( $integ['connect_status'] ) || empty( $integ['connect_status'] ) ) ? 'not-connected' : $integ['connect_status'];
  }

  /**
   * Checks whether the user has a Stripe payment method that uses Stripe Connect
   *
   * @return boolean
   */
  public static function has_method_with_connect_status($status = 'connected') {
    $mepr_options = MeprOptions::fetch();
    foreach ( $mepr_options->integrations as $integration ) {

      // Only check Stripe payment methods
      if ( 'MeprStripeGateway' !== $integration['gateway'] ) {
        continue;
      }

      if ( $status == self::stripe_connect_status( $integration['id'] ) ) {
        return true;
      }
    }

    return false;
  }

  /** Fetches the credentials from MP-Stripe-Connect and updates them in the payment method. */
  public function update_connect_credentials() {
    $mepr_options = MeprOptions::fetch();

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    $payload = array(
      'site_uuid' => $site_uuid
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

    // Make sure the request came from the Connect service
    $response = wp_remote_get( MEPR_STRIPE_SERVICE_URL . "/api/credentials/{$this->id}", array(
      'headers' => MeprUtils::jwt_header($jwt, MEPR_STRIPE_SERVICE_DOMAIN)
    ) );

    $creds = json_decode( wp_remote_retrieve_body( $response ), true );

    MeprUtils::debug_log("*** MeprStripeGateway::update_connect_credentials updating creds for method_id = [{$this->id}]");

    // Store the credentials
    foreach($mepr_options->integrations as $method_id => $integ) {
      // Update ALL of the payment methods connected to this account
      if( $method_id == $this->id || (
          isset($mepr_options->integrations[$method_id]['service_account_id']) &&
          $mepr_options->integrations[$method_id]['service_account_id'] == $creds['service_account_id'] ) )
      {
        MeprUtils::debug_log("*** MeprStripeGateway::update_connect_credentials updating payment method with this data: " . print_r($creds,true));

        $integ['api_keys']['test']['public'] = sanitize_text_field( $creds['test_publishable_key'] );
        $integ['api_keys']['test']['secret'] = sanitize_text_field( $creds['test_secret_key'] );
        $integ['api_keys']['live']['public'] = sanitize_text_field( $creds['live_publishable_key'] );
        $integ['api_keys']['live']['secret'] = sanitize_text_field( $creds['live_secret_key'] );
        $integ['service_account_id'] = sanitize_text_field( $creds['service_account_id'] );
        $integ['service_account_name'] = sanitize_text_field( $creds['service_account_name'] );
        $integ['connect_status'] = 'connected';

        $mepr_options->integrations[$method_id] = $integ;
      }
    }

    return $mepr_options->store(false);
  }

  /**
   * Get the Stripe Customer ID
   *
   * If the Stripe Customer does not exist for the given user, one will be created.
   * If a Stripe PaymentMethod ID is given it will be attached to the customer.
   *
   * @param  MeprUser            $usr               The MemberPress user
   * @param  string              $payment_method_id The Stripe PaymentMethod ID to attach to the customer
   * @return string                                 The Stripe Customer ID
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function get_customer_id(MeprUser $usr, $payment_method_id = null) {
    $customer_id = $usr->get_stripe_customer_id($this->get_meta_gateway_id());

    if(is_string($customer_id) && strpos($customer_id, 'cus_') === 0) {
      if($payment_method_id) {
        $this->send_stripe_request('payment_methods/' . $payment_method_id . '/attach', ['customer' => $customer_id], 'post');
      }
    }
    else {
      $customer = $this->create_customer($usr, $payment_method_id);
      $usr->set_stripe_customer_id($this->get_meta_gateway_id(), $customer->id);
      $customer_id = $customer->id;
    }

    return $customer_id;
  }

  /**
   * Create a Stripe Customer
   *
   * @param  MeprUser            $usr               The MemberPress user
   * @param  string|null         $payment_method_id The Stripe PaymentMethod ID to attach to the customer
   * @return stdClass                               The Stripe Customer data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function create_customer(MeprUser $usr, $payment_method_id = null) {
    $args = [
      'email' => $usr->user_email,
      'expand' => [
        'invoice_settings.default_payment_method'
      ]
    ];

    if($full_name = $usr->get_full_name()) {
      $args['name'] = $full_name;
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_customer_args', $args, $usr);

    if($payment_method_id) {
      $args['payment_method'] = $payment_method_id;
      $args['invoice_settings']['default_payment_method'] = $payment_method_id;
    }

    $customer = (object) $this->send_stripe_request('customers', $args, 'post');

    return $customer;
  }

  /**
   * Get the Stripe Product ID
   *
   * If the Stripe Product does not exist for the given product, one will be created.
   *
   * @param  MeprProduct         $prd The MemberPress product
   * @return string                   The Stripe Product ID
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function get_product_id(MeprProduct $prd) {
    $product_id = $prd->get_stripe_product_id($this->get_meta_gateway_id());

    if(!is_string($product_id) || strpos($product_id, 'prod_') !== 0) {
      $product = $this->create_product($prd);
      $prd->set_stripe_product_id($this->get_meta_gateway_id(), $product->id);
      $product_id = $product->id;
    }

    return $product_id;
  }

  /**
   * Create a Stripe Plan
   *
   * @param  string              $product_id The Stripe Product ID
   * @param  MeprSubscription    $sub        The MemberPress subscription
   * @param  string              $amount     The payment amount (excluding tax)
   * @return stdClass                        The Stripe Plan data
   * @throws MeprHttpException               If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException             If there was an invalid or error response from Stripe
   */
  public function create_plan($product_id, MeprSubscription $sub, $amount) {
    $mepr_options = MeprOptions::fetch();

    if($sub->period_type == 'months') {
      $interval = 'month';
    } elseif($sub->period_type == 'years') {
      $interval = 'year';
    } elseif($sub->period_type == 'weeks') {
      $interval = 'week';
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_plan_args', [
      'amount' => $amount,
      'interval' => $interval,
      'interval_count' => $sub->period,
      'currency' => $mepr_options->currency_code,
      'product' => $product_id
    ], $sub);

    // Prevent a Stripe error if the user is using the pre-1.6.0 method of setting the statement_descriptor
    if(array_key_exists('statement_descriptor', $args)) {
      unset($args['statement_descriptor']);
    }

    // Prevent a Stripe error if the 'product' value is modified by hook
    if(!isset($args['product']) || is_array($args['product'])) {
      $args['product'] = $product_id;
    }

    // Don't enclose this in try/catch ... we want any errors to bubble up
    $plan = (object) $this->send_stripe_request('plans', $args, 'post');

    return $plan;
  }

  /**
   * Create a Stripe Product
   *
   * @param  MeprProduct         $prd The MemberPress product
   * @return stdClass                 The Stripe Product data
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function create_product(MeprProduct $prd) {
    $args = MeprHooks::apply_filters('mepr_stripe_create_product_args', [
      'name' => $prd->post_title,
      'type' => 'service'
    ], $prd);

    // Prevent a Stripe error in the rare case that a membership has an empty title
    if(empty($args['name'])) {
      $args['name'] = sprintf(
        /* translators: %d: the product ID */
        __('Product %d', 'memberpress'),
        $prd->ID
      );
    }

    if(!array_key_exists('statement_descriptor', $args)) {
      $statement_descriptor = $this->get_statement_descriptor($prd);

      if(strlen($statement_descriptor) > 1) {
        $args['statement_descriptor'] = $statement_descriptor;
      }
    }

    $product = (object) $this->send_stripe_request('products', $args, 'post');

    return $product;
  }

  /**
   * Get stripe price id
   * If there is a plan id attached to product then use it, if not use price id instead
   * If a price id doesn't exist, create one
   *
   * @param MeprSubscription $sub
   * @param MeprProduct $prd
   * @param MeprTransaction $txn
   * @param MeprUser $usr
   * @param null|float $price
   */
  public function get_stripe_price_id($sub, MeprTransaction $txn, MeprProduct $prd, MeprUser $usr, $price = null) {
    $mepr_options = MeprOptions::fetch();
    if ($prd->is_one_time_payment()) {
      $meta_key = '_mepr_stripe_onetime_price_id_' .
                  md5(serialize([
                    'currency' => $mepr_options->currency_code,
                    'amount' => $txn->amount,
                    'payment_method' => $this->get_meta_gateway_id(),
                  ]));
      $price_id = get_post_meta(
        $prd->ID,
        $meta_key,
        true
      );

      if (!empty($price_id)) {
        return $price_id;
      }
    } else {
      $plan_id = $this->get_stripe_plan_id( $sub, $prd, $usr, $price );

      if ( ! empty( $plan_id ) ) {
        return $plan_id;
      }

      $meta_key = '_mepr_stripe_subscription_price_id_' .
                  md5(serialize([
                    'currency' => $mepr_options->currency_code,
                    'amount' => $txn->amount,
                    'payment_method' => $this->get_meta_gateway_id(),
                  ]));
      $price_id = get_post_meta(
        $prd->ID,
        $meta_key,
        true
      );

      if ( ! empty( $price_id ) ) {
        return $price_id;
      }
    }

    // Handle zero decimal currencies in Stripe
    $amount = self::is_zero_decimal_currency() ? MeprUtils::format_float($txn->amount, 0) : MeprUtils::format_float(($txn->amount * 100), 0);

    $mepr_options = MeprOptions::fetch();
    $product_id = $prd->ID;

    if ($prd->is_one_time_payment()) {
      $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
        'product' => $this->get_product_id($prd),
        'unit_amount' => $amount,
        'currency' => $mepr_options->currency_code,
      ], $sub);

      $price = (object) $this->send_stripe_request('prices', $args, 'post');
      update_post_meta($product_id, $meta_key, $price->id);

      return $price->id;
    }

    $interval = '';

    if($sub->period_type == 'months') {
      $interval = 'month';
    } elseif($sub->period_type == 'years') {
      $interval = 'year';
    } elseif($sub->period_type == 'weeks') {
      $interval = 'week';
    }

    $args = MeprHooks::apply_filters('mepr_stripe_create_price_args', [
      'product' => $this->get_product_id($prd),
      'unit_amount' => $amount,
      'currency' => $mepr_options->currency_code,
      'recurring' => [
        'interval' => $interval,
      ],
    ], $sub);

    // Prevent a Stripe error if the 'product' value is modified by hook
    if(!isset($args['product']) || is_array($args['product'])) {
      $args['product'] = $product_id;
    }

    $price = (object) $this->send_stripe_request('prices', $args, 'post');
    update_post_meta($product_id, $meta_key, $price->id);

    return $price->id;
  }

  /**
   * Get the Stripe Plan ID
   *
   * If the Stripe Plan does not exist for the given subscription, one will be created.
   *
   * @param  MeprSubscription    $sub The MemberPress subscription
   * @param  MeprProduct         $prd The MemberPress product
   * @param  MeprUser            $usr The MemberPress user
   * @param  float            $price
   * @return string                   The Stripe Plan ID
   * @throws MeprHttpException        If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException      If there was an invalid or error response from Stripe
   */
  public function get_stripe_plan_id(MeprSubscription $sub, MeprProduct $prd, MeprUser $usr, $price = null) {
    // Use a temporary transaction to calculate the subscription amount without tax and without a coupon
    $txn = new MeprTransaction();
    $txn->product_id = $prd->ID;
    $txn->user_id = $usr->ID;
    $txn->set_subtotal($prd->price);

    if (empty($price)) {
      // Handle zero decimal currencies in Stripe
      $amount = self::is_zero_decimal_currency() ? MeprUtils::format_float( $txn->amount, 0 ) : MeprUtils::format_float( ( $txn->amount * 100 ), 0 );
    } else {
      $amount = self::is_zero_decimal_currency() ? MeprUtils::format_float( $price, 0 ) : MeprUtils::format_float( ( $price * 100 ), 0 );
    }

    $plan_id = $prd->get_stripe_plan_id($this->get_meta_gateway_id(), $amount);

    if(!is_string($plan_id) || strpos($plan_id, 'plan_') !== 0) {
      $plan = $this->create_plan($this->get_product_id($prd), $sub, $amount);
      $prd->set_stripe_plan_id($this->get_meta_gateway_id(), $amount, $plan->id);
      $plan_id = $plan->id;
    }

    return $plan_id;
  }

  /**
   * Create a Stripe Coupon
   *
   * @param  MeprCoupon          $cpn             The MemberPress coupon
   * @param  string              $discount_amount The coupon discount amount
   * @return stdClass                             The Stripe Coupon data
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function create_coupon(MeprCoupon $cpn, $discount_amount, $onetime) {
    $args = MeprHooks::apply_filters('mepr_stripe_create_coupon_args', [
      'name' => substr($cpn->post_title, 0 , 40),
      'duration' => 'forever'
    ], $cpn);

    $mepr_options = MeprOptions::fetch();


    if ($onetime) {
      $args['duration'] = 'once';

      if ($cpn->first_payment_discount_type == 'percent') {
        $args = array_merge( [
          'percent_off' => $discount_amount
        ], $args );
      } else {
        $args = array_merge([
          'amount_off' => $discount_amount,
          'currency' => $mepr_options->currency_code,
        ], $args);
      }
    } else {
      if ( $cpn->discount_type == 'percent' ) {
        $args = array_merge( [
          'percent_off' => $discount_amount
        ], $args );
      } else {
        $args = array_merge( [
          'amount_off' => $discount_amount,
          'currency'   => $mepr_options->currency_code,
        ], $args );
      }
    }

    $coupon = (object) $this->send_stripe_request('coupons', $args, 'post');

    return $coupon;
  }

  /**
   * Get the Stripe Coupon ID
   *
   * @param  MeprCoupon          $cpn             The MemberPress coupon
   * @param  string              $discount_amount The coupon discount amount
   * @param  bool              $onetime
   * @return string                               The Stripe Coupon ID
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function get_coupon_id(MeprCoupon $cpn, $discount_amount, $onetime = false) {
    $coupon_id = $cpn->get_stripe_coupon_id($this->get_meta_gateway_id(), $discount_amount, $onetime);

    if(!is_string($coupon_id) || $coupon_id === '') {
      $coupon = $this->create_coupon($cpn, $discount_amount, $onetime);
      $cpn->set_stripe_coupon_id($this->get_meta_gateway_id(), $discount_amount, $coupon->id, $onetime);
      $coupon_id = $coupon->id;
    }

    return $coupon_id;
  }

  /**
   * Get the statement descriptor
   *
   * @param  MeprProduct $product The MemberPress product
   * @return string               The statement descriptor
   */
  private function get_statement_descriptor(MeprProduct $product) {
    $descriptor = MeprUtils::blogname();

    if(empty($descriptor)) {
      $descriptor = parse_url(get_option('siteurl'), PHP_URL_HOST);
    }

    $descriptor = MeprHooks::apply_filters('mepr_stripe_statement_descriptor', $descriptor, $product);

    return $this->sanitize_statement_descriptor($descriptor);
  }

  /**
   * Create a Stripe Subscription
   *
   * @param  MeprTransaction     $txn               The MemberPress transaction
   * @param  MeprSubscription    $sub               The MemberPress subscription
   * @param  MeprProduct         $prd               The MemberPress product
   * @param  MeprUser            $usr               The MemberPress user
   * @param  string              $payment_method_id The Stripe PaymentMethod ID to use for payment or setup
   * @return stdClass                               The Stripe Subscription data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function create_subscription(MeprTransaction $txn, MeprSubscription $sub, MeprProduct $prd, MeprUser $usr, $payment_method_id) {
    $mepr_options = MeprOptions::fetch();
    $customer_id = $this->get_customer_id($usr, $payment_method_id);
    $plan_id = $this->get_stripe_plan_id($sub, $prd, $usr);
    $tax_inclusive = $mepr_options->attr('tax_calc_type') == 'inclusive';
    if ($txn->tax_rate <= 0) {
      $tax_inclusive = false;
    }

    if ($tax_inclusive) {
      $plan_id = $this->get_stripe_plan_id($sub, $prd, $usr, $prd->price);
      $tax_rate_id = $txn->tax_rate > 0 ? $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd, $tax_inclusive) : null;
    } else {
      $tax_rate_id = $txn->tax_rate > 0 ? $this->get_stripe_tax_rate_id($txn->tax_desc, $txn->tax_rate, $prd) : null;
    }

    if($sub->trial && (float) $sub->trial_amount > 0.00) {
      // Use a temporary transaction to calculate the paid trial amount without tax
      $tmp_txn = new MeprTransaction();
      $tmp_txn->product_id = $prd->ID;
      $tmp_txn->user_id = $usr->ID;
      $tmp_txn->set_subtotal($sub->trial_amount);

      if ($tax_inclusive) {
        $amount = $tmp_txn->total;
      } else {
        $amount = $tmp_txn->amount;
      }

      $amount = self::is_zero_decimal_currency() ? MeprUtils::format_float($amount, 0) : MeprUtils::format_float($amount * 100, 0);

      // For paid trials, add the trial payment amount as an invoice item before creating the subscription
      $args = [
        'customer' => $customer_id,
        'amount' => $amount,
        'currency' => $mepr_options->currency_code,
        'description' => __('Initial Payment', 'memberpress'),
        'metadata' => [
          'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
          'transaction_id' => $txn->id,
          'site_url' => get_site_url(),
          'ip_address' => $_SERVER['REMOTE_ADDR']
        ],
        'discountable' => 'false'
      ];

      if($tax_rate_id) {
        $args['tax_rates'] = [$tax_rate_id];
      }

      $args = MeprHooks::apply_filters('mepr_stripe_paid_trial_invoice_args', $args, $txn, $sub);

      $this->send_stripe_request('invoiceitems', $args, 'post');
    }

    $item = ['plan' => $plan_id];

    if($tax_rate_id) {
      $item['tax_rates'] = [$tax_rate_id];
    }

    $args = MeprHooks::apply_filters('mepr_stripe_subscription_args', [
      'customer' => $customer_id,
      'default_payment_method' => $payment_method_id,
      'items' => [$item],
      'expand' => [
        'latest_invoice.payment_intent',
        'pending_setup_intent'
      ],
      'metadata' => [
        'platform' => 'MemberPress Connect acct_1FIIDhKEEWtO8ZWC',
        'transaction_id' => $txn->id,
        'site_url' => get_site_url(),
        'ip_address' => $_SERVER['REMOTE_ADDR']
      ],
    ], $txn, $sub);

    if($sub->trial) {
      $args = array_merge(['trial_period_days' => $sub->trial_days], $args);
    }

    $coupon = $sub->coupon();

    if($coupon instanceof MeprCoupon) {
      $discount_amount = $this->get_coupon_discount_amount($coupon, $prd, $txn->tax_rate);

      if($discount_amount > 0) {
        $args = array_merge(['coupon' => $this->get_coupon_id($coupon, $discount_amount)], $args);
      }
    }

    $this->email_status("create_subscription: \n" . MeprUtils::object_to_string($txn) . "\n", $this->settings->debug);

    $subscription = (object) $this->send_stripe_request('subscriptions', $args, 'post');

    $sub->subscr_id = $subscription->id;
    $sub->store();

    return $subscription;
  }

  /**
   * Get the discount amount for the given coupon
   *
   * @param  MeprCoupon  $coupon   The coupon being used
   * @param  MeprProduct $product  The membership being purchased
   * @param  string      $tax_rate The tax rate for the transaction
   * @return string|int            The formatted discount amount or 0 if no discount
   */
  public function get_coupon_discount_amount(MeprCoupon $coupon, MeprProduct $product, $tax_rate) {
    $mepr_options = MeprOptions::fetch();
    $discount_amount = $coupon->get_discount_amount($product);

    if($discount_amount > 0) {
      if($coupon->discount_type == 'percent') {
        $discount_amount = MeprUtils::format_float($discount_amount);
      }
      else {
        $discount_amount = self::is_zero_decimal_currency() ? MeprUtils::format_float($discount_amount, 0) : MeprUtils::format_float($discount_amount * 100, 0);
      }

      if($discount_amount > 0) {
        return $discount_amount;
      }
    }

    return 0;
  }

  /**
   * Retrieve a Stripe Subscription
   *
   * @param  string              $subscription_id The Stripe Subscription ID
   * @return stdClass                             The Stripe Subscription data
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function retrieve_subscription($subscription_id) {
    $args = MeprHooks::apply_filters('mepr_stripe_retrieve_subscription_args', [
      'expand' => [
        'latest_invoice',
        'latest_invoice.payment_intent',
        'pending_setup_intent'
      ]
    ]);

    return (object) $this->send_stripe_request('subscriptions/' . $subscription_id, $args, 'get');
  }

  /**
   * Update the payment method for a Stripe Subscription and retry payment
   *
   * @param  string              $subscription_id   The Stripe Subscription ID
   * @param  MeprUser            $usr               The MemberPress user
   * @param  string              $payment_method_id The Stripe PaymentMethod ID
   * @return stdClass                               The Stripe Subscription data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function retry_subscription_payment($subscription_id, MeprUser $usr, $payment_method_id) {
    // Attach the payment method to the customer
    $this->get_customer_id($usr, $payment_method_id);

    $subscription = (object) $this->send_stripe_request('subscriptions/' . $subscription_id, ['default_payment_method' => $payment_method_id], 'post');

    try {
      $this->send_stripe_request('invoices/' . $subscription->latest_invoice . '/pay', [], 'post');
    } catch (MeprRemoteException $e) {
      // We want to silently ignore HTTP 402 responses here, as it would display an error to the customer about the
      // card requiring authentication. We'll manually handle SCA after this in MeprStripeCtrl. Some other error
      // responses will also be "swallowed" by this catch block, but the end result should be the same - if the
      // payment failed the customer will be notified by the logic in MeprStripeCtrl.
    }

    return $this->retrieve_subscription($subscription->id);
  }

  /**
   * Retrieve a Stripe SetupIntent
   *
   * @param  string              $setup_intent_id The Stripe SetupIntent ID
   * @return stdClass                             The Stripe SetupIntent data
   * @throws MeprHttpException                    If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                  If there was an invalid or error response from Stripe
   */
  public function retrieve_setup_intent($setup_intent_id) {
    return (object) $this->send_stripe_request( "setup_intents/{$setup_intent_id}", ['expand' => ['payment_method']], 'get');
  }

  /**
   * Retrieve a Stripe PaymentIntent
   *
   * @param  string              $payment_intent_id The Stripe PaymentIntent ID
   * @return stdClass                               The Stripe PaymentIntent data
   * @throws MeprHttpException                      If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException                    If there was an invalid or error response from Stripe
   */
  public function retrieve_payment_intent($payment_intent_id) {
    return (object) $this->send_stripe_request('payment_intents/' . $payment_intent_id, [], 'get');
  }

  /**
   * Retrieve a Stripe Invoice
   *
   * @param  string              $invoice_id The Stripe Invoice ID
   * @return stdClass                        The Stripe Invoice data
   * @throws MeprHttpException               If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException             If there was an invalid or error response from Stripe
   */
  public function retrieve_invoice($invoice_id) {
    return (object) $this->send_stripe_request('invoices/' . $invoice_id, ['expand' => ['payment_intent']], 'get');
  }

  /**
   * Retrieve a Stripe Customer
   *
   * @param  string              $customer_id The Stripe Customer ID
   * @return stdClass                         The Stripe Customer data
   * @throws MeprHttpException                If there was an HTTP error connecting to Stripe
   * @throws MeprRemoteException              If there was an invalid or error response from Stripe
   */
  public function retrieve_customer($customer_id) {
    return (object) $this->send_stripe_request('customers/' . $customer_id, ['expand' => ['invoice_settings.default_payment_method']], 'get');
  }

  /**
   * Get the gateway ID for storing Stripe object IDs
   *
   * Object IDs in test mode do not exist in live mode, so we need to differentiate.
   *
   * @return string
   */
  public function get_meta_gateway_id() {
    $key = $this->id;

    if($this->is_test_mode()) {
      $key .= '_test';
    }

    return $key;
  }

  /**
   * Is the currency a zero-decimal currency?
   *
   * 'HUF' is a special case for Stripe - it's a zero-decimal currency that must be multiplied by 100.
   *
   * @return bool
   */
  public static function is_zero_decimal_currency() {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->currency_code == 'HUF') {
      return false;
    }

    return MeprUtils::is_zero_decimal_currency();
  }

  /**
   * Get the locale code
   *
   * Converts the configured MP language code into a Stripe-compatible locale code
   *
   * @see https://stripe.com/docs/js/appendix/supported_locales
   * @return string
   */
  public static function get_locale_code() {
    $mepr_options = MeprOptions::fetch();
    $locale_code = 'auto';

    $locales = array(
      'US' => 'en',
      'AE' => 'ar',
      'AR' => 'es-419',
      'AU' => 'en',
      'BG' => 'bg',
      'BR' => 'pt-BR',
      'CH' => 'auto',
      'CN' => 'zh',
      'CO' => 'es-419',
      'CZ' => 'cs',
      'DE' => 'de',
      'DK' => 'da',
      'EN' => 'en',
      'ES' => 'es',
      'FI' => 'fi',
      'FR' => 'fr',
      'GB' => 'en-GB',
      'HE' => 'he',
      'HR' => 'hr',
      'HU' => 'hu',
      'ID' => 'id',
      'IS' => 'auto',
      'IT' => 'it',
      'JP' => 'ja',
      'KR' => 'ko',
      'MS' => 'en',
      'MX' => 'es-419',
      'NL' => 'nl',
      'NO' => 'nb',
      'PE' => 'es-419',
      'PH' => 'fil',
      'PL' => 'pl',
      'PT' => 'pt',
      'RO' => 'ro',
      'RU' => 'ru',
      'SE' => 'sv',
      'SK' => 'sk',
      'SR' => 'nl',
      'SW' => 'auto',
      'TH' => 'th',
      'TN' => 'ar',
      'TR' => 'tr',
      'TW' => 'zh-TW',
      'VI' => 'vi',
      'ZA' => 'auto',
    );

    if(isset($locales[$mepr_options->language_code])) {
      $locale_code = $locales[$mepr_options->language_code];
    }

    return MeprHooks::apply_filters('mepr_stripe_locale_code', $locale_code);
  }
}
