<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

class MeprPayPalCommerceGateway extends MeprBasePayPalGateway {
  /** Used in the view to identify the gateway */
  public function __construct() {
    $this->name         = __( "PayPal", 'memberpress' );
    $this->key          = __( 'paypalcommerce', 'memberpress' );
    $this->has_spc_form = true;
    $this->set_defaults();

    // Setup the notification actions for this gateway
    $this->notifiers = array(
      'ipn' => 'ipn_listener',
      'cancel'  => 'cancel_handler',
      'webhook' => 'webhook_handler',
      'return'  => 'return_handler'
    );

    $this->message_pages = array( 'cancel' => 'cancel_message' );
  }

  public function record_create_subscription() {
    // not needed, subscription will be created with PENDING satatus, before payment done
  }

  public function record_payment_failure() {
    if ( isset( $_POST['txn_id'] ) && ( $txn_res = MeprTransaction::get_one_by_trans_num( $_POST['txn_id'] ) ) && isset( $txn_res->id ) ) {
      $txn         = new MeprTransaction( $txn_res->id );
      $txn->status = MeprTransaction::$failed_str;
      $txn->store();
    } elseif ( ( isset( $_POST['recurring_payment_id'] ) and
                 ( $sub = MeprSubscription::get_one_by_subscr_id( $_POST['recurring_payment_id'] ) ) ) or
               ( isset( $_POST['subscr_id'] ) and
                 ( $sub = MeprSubscription::get_one_by_subscr_id( $_POST['subscr_id'] ) ) ) ) {
      $first_txn = $sub->first_txn();

      if ( $first_txn == false || ! ( $first_txn instanceof MeprTransaction ) ) {
        $first_txn             = new MeprTransaction();
        $first_txn->user_id    = $sub->user_id;
        $first_txn->product_id = $sub->product_id;
        $first_txn->coupon_id  = $sub->coupon_id;
      }

      $txn                  = new MeprTransaction();
      $txn->user_id         = $sub->user_id;
      $txn->product_id      = $sub->product_id;
      $txn->coupon_id       = $first_txn->coupon_id;
      $txn->txn_type        = MeprTransaction::$payment_str;
      $txn->status          = MeprTransaction::$failed_str;
      $txn->subscription_id = $sub->id;
      $txn->trans_num = ( isset( $_POST['recurring_payment_id'] ) ? $_POST['recurring_payment_id'] : uniqid() );
      $txn->gateway   = $this->id;

      $txn->set_gross( isset( $_POST['mc_gross'] ) ? $_POST['mc_gross'] : ( isset( $_POST['amount'] ) ? $_POST['amount'] : 0.00 ) );

      $txn->store();

      $sub->expire_txns(); //Expire associated transactions for the old subscription
      $sub->store();
    } else {
      return false; // Nothing we can do here ... so we outta here
    }

    MeprUtils::send_failed_txn_notices( $txn );

    return $txn;
  }

  public function record_payment() {
    // Not needed, payment will be recorded by webhook handler
  }

  public function load( $settings ) {
    $this->settings = (object) $settings;
    $this->set_defaults();
  }

  public function log( $data ) {
    if ( ! defined( 'WP_MEPR_DEBUG' ) ) {
      return;
    }

    file_put_contents( WP_CONTENT_DIR . '/paypal-connect.log', print_r( $data, true ) . PHP_EOL, FILE_APPEND );
  }

  protected function set_defaults() {
    if ( ! isset( $this->settings ) ) {
      $this->settings = array();
    }

    $this->settings  =
      (object) array_merge(
        array(
          'gateway'              => 'MeprPayPalCommerceGateway',
          'id'                   => $this->generate_id(),
          'label'                => '',
          'use_label'            => true,
          'icon'                 => MEPR_IMAGES_URL . '/checkout/paypal.png',
          'use_icon'             => true,
          'desc'                 => __( 'Pay via your PayPal account', 'memberpress' ),
          'use_desc'             => true,
          'enable_smart_button'  => false,
          'test_client_id'       => '',
          'test_client_secret'   => '',
          'live_client_id'       => '',
          'live_client_secret'   => '',
          'test_webhook_id'      => '',
          'live_webhook_id'      => '',
          'test_merchant_id'     => '',
          'live_merchant_id'     => '',
          'test_auth_code'       => '',
          'live_auth_code'       => '',
          'test_email_confirmed' => '',
          'live_email_confirmed' => '',
          'debug'                => false
        ),
        (array) $this->settings
      );
    $this->id        = $this->settings->id;
    $this->label     = $this->settings->label;
    $this->use_label = $this->settings->use_label;
    $this->icon      = $this->settings->icon;
    $this->use_icon  = $this->settings->use_icon;
    $this->desc      = $this->settings->desc;
    $this->use_desc  = $this->settings->use_desc;
    $this->debug = defined( 'WP_MEPR_DEBUG' ) && WP_MEPR_DEBUG === true;

    if ( $this->is_test_mode() ) {
      $this->settings->url          = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
      $this->settings->api_url      = 'https://api-3t.sandbox.paypal.com/nvp';
      $this->settings->rest_api_url = 'https://api-m.sandbox.paypal.com';
    } else {
      $this->settings->url          = 'https://www.paypal.com/cgi-bin/webscr';
      $this->settings->api_url      = 'https://api-3t.paypal.com/nvp';
      $this->settings->rest_api_url = 'https://api-m.paypal.com';
    }

    $this->settings->api_version = 69;

    $this->capabilities = array(
      'process-payments',
      'process-refunds',
      'create-subscriptions',
      'cancel-subscriptions',
      'update-subscriptions',
      'suspend-subscriptions',
      'resume-subscriptions',
      'subscription-trial-payment'
    );
  }

  /** Used to record a successful recurring payment by the given gateway. It
   * should have the ability to record a successful payment or a failure. It is
   * this method that should be used when receiving an IPN from PayPal or a
   * Silent Post from Authorize.net.
   */
  public function record_subscription_payment() {
    if ( ! isset( $_POST['recurring_payment_id'] ) && ! isset( $_POST['subscr_id'] ) ) {
      return;
    }

    if ( isset( $_POST['subscr_id'] ) && ! empty( $_POST['subscr_id'] ) ) {
      $sub = MeprSubscription::get_one_by_subscr_id( $_POST['subscr_id'] );
    } else {
      $sub = MeprSubscription::get_one_by_subscr_id( $_POST['recurring_payment_id'] );
    }

    if ( $sub ) {
      $timestamp = isset( $_POST['payment_date'] ) ? strtotime( $_POST['payment_date'] ) : time();
      $first_txn = new MeprTransaction( $sub->first_txn_id );

      if ( ! isset( $first_txn->id ) || empty( $first_txn->id ) ) {
        $first_txn             = new MeprTransaction();
        $first_txn->user_id    = $sub->user_id;
        $first_txn->product_id = $sub->product_id;
        $first_txn->coupon_id  = $sub->coupon_id;
      }

      //If this is a trial payment, let's just convert the confirmation txn into a payment txn
      if ( $this->is_subscr_trial_payment( $sub ) ) {
        $txn                  = $first_txn; //For use below in send notices
        $txn->created_at      = MeprUtils::ts_to_mysql_date( $timestamp );
        $txn->expires_at      = MeprUtils::ts_to_mysql_date( time() + MeprUtils::days( $sub->trial_days ), 'Y-m-d 23:59:59' );
        $txn->gateway         = $this->id;
        $txn->trans_num       = $_POST['txn_id'];
        $txn->txn_type        = MeprTransaction::$payment_str;
        $txn->status          = MeprTransaction::$complete_str;
        $txn->subscription_id = $sub->id;
        $txn->set_gross( $_POST['mc_gross'] );
        $txn->store();
      } else {
        $existing = MeprTransaction::get_one_by_trans_num( $_POST['txn_id'] );

        //There's a chance this may have already happened during the return handler, if so let's just get everything up to date on the existing one
        if ( $existing != null && isset( $existing->id ) && (int) $existing->id > 0 ) {
          $txn = new MeprTransaction( $existing->id );
        } else {
          $txn = new MeprTransaction();
        }

        $txn->created_at      = MeprUtils::ts_to_mysql_date( $timestamp );
        $txn->user_id         = $first_txn->user_id;
        $txn->product_id      = $first_txn->product_id;
        $txn->coupon_id       = $first_txn->coupon_id;
        $txn->gateway         = $this->id;
        $txn->trans_num       = $_POST['txn_id'];
        $txn->txn_type        = MeprTransaction::$payment_str;
        $txn->status          = MeprTransaction::$complete_str;
        $txn->subscription_id = $sub->id;
        $txn->set_gross( $_POST['mc_gross'] );
        $txn->store();

        //Check that the subscription status is still enabled
        if ( $sub->status != MeprSubscription::$active_str ) {
          $sub->status = MeprSubscription::$active_str;
          $sub->store();
        }
      }

      $this->email_status( "Subscription Transaction\n" . MeprUtils::object_to_string( $txn->rec, true ), $this->debug );

      MeprUtils::send_transaction_receipt_notices( $txn );

      return $txn;
    }

    return false;
  }

  /** Used to send data to a given payment gateway. In gateways which redirect
   * before this step is necessary this method should just be left blank.
   */
  public function process_payment( $txn ) {
  }

  /**
   * @param MeprTransaction $txn
   *
   * @return false|MeprTransaction
   * @throws MeprGatewayException
   */
  public function process_refund( MeprTransaction $txn ) {
    $product = $txn->product();

    if ( $product->is_one_time_payment() ) {
      $txn_number = $txn->trans_num;
      $options    = [
        'headers' => [
          'Content-Type'                  => 'application/json',
          'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
          'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
        ],
      ];
      $response   = wp_remote_post( $this->settings->rest_api_url . '/v2/payments/captures/' . $txn_number . '/refund', $options );
      $this->log( $response );
      $response = json_decode( wp_remote_retrieve_body( $response ), true );
      $this->log( $options );
    } else {
      $endpoint = '/v1/payments/sale/' . $txn->trans_num . '/refund';
      $options  = [
        'headers' => [
          'Content-Type'                  => 'application/json',
          'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
          'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
        ],
      ];
      $response = wp_remote_post( $this->settings->rest_api_url . $endpoint, $options );
      $this->log( $response );
      $response = json_decode( wp_remote_retrieve_body( $response ), true );
      $this->log( $options );

      if ( isset( $response['name'] ) && $response['name'] == 'TRANSACTION_ALREADY_REFUNDED' ) {
        $_POST['parent_txn_id'] = $txn->id;

        return $this->record_refund();
      }
    }

    if ( isset( $response['status'] ) && $response['status'] !== 'COMPLETED' ) {
      throw new MeprGatewayException( __( 'Refund request has been done unsuccessfully', 'memberpress' ) );
    }

    $_POST['parent_txn_id'] = $txn->id;

    return $this->record_refund();
  }

  /** This method should be used by the class to record a successful refund from
   * the gateway. This method should also be used by any webhook requests or Silent Posts.
   */
  public function record_refund() {
    $obj = new MeprTransaction( $_POST['parent_txn_id'] );

    if ( ! is_null( $obj ) && (int) $obj->id > 0 ) {
      $txn = $obj;

      // Seriously ... if txn was already refunded what are we doing here?
      if ( $txn->status == MeprTransaction::$refunded_str ) {
        return $txn;
      }

      $txn->status = MeprTransaction::$refunded_str;

      $this->email_status( "Processing Refund: \n" . MeprUtils::object_to_string( $_POST ) . "\n Affected Transaction: \n" . MeprUtils::object_to_string( $txn ), $this->debug );

      $txn->store();

      MeprUtils::send_refunded_txn_notices( $txn );

      return $txn;
    }

    return false;
  }

  //Not needed in PayPal since PayPal supports the trial payment inclusive of the Subscription
  public function process_trial_payment( $transaction ) {
  }

  public function record_trial_payment( $transaction ) {
  }

  /** Used to send subscription data to a given payment gateway. In gateways
   * which redirect before this step is necessary this method should just be
   * left blank.
   */
  public function process_create_subscription( $txn ) {
  }

  /**
   * @param $pp_subscription_id
   *
   * @throws MeprGatewayException
   */
  public function process_checkout_supscription_approved( $pp_subscription_id ) {
    $pp_subscription = $this->get_paypal_subscription_object( $pp_subscription_id );
    $mepr_options    = MeprOptions::fetch();
    $this->log( 'Received a subscription' );
    $this->log( $pp_subscription );
    if ( isset( $pp_subscription['custom_id'] ) && is_numeric( $pp_subscription['custom_id'] ) ) {
      $sub            = new MeprSubscription( $pp_subscription['custom_id'] );
      $sub->subscr_id = $pp_subscription['id'];

      if (
        ( $pp_subscription['status'] == 'ACTIVE' && $pp_subscription['billing_info']['next_billing_time'] ) ||
        ( $pp_subscription['status'] == 'EXPIRED' && $pp_subscription['billing_info']['cycle_executions'] )
      ) {
        $this->log( 'Creating first txn' );
        $txn = MeprTransaction::get_first_subscr_transaction( $sub->id );

        if ( $txn instanceof MeprTransaction ) {
          $sub->first_txn_id = $txn->id;
        } else {
          $txn = new MeprTransaction( $txn->id );
        }

        $first_txn = $sub->first_txn();
        $this->log( $first_txn );

        if ( $first_txn instanceof MeprTransaction ) {
          $this->activate_subscription( $first_txn, $sub );

          {
            if ( ! $mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0 ) {
              $expires_at = MeprUtils::ts_to_mysql_date( time() + MeprUtils::days( $mepr_options->grace_init_days ), 'Y-m-d 23:59:59' );
            } else {
              $expires_at = $txn->created_at; // Expire immediately
            }

            $this->log( 'Check next billing' );
            $first_txn->expires_at = $expires_at;
            $first_txn->set_gross( $sub->total );
            $first_txn->status   = \MeprTransaction::$confirmed_str;
            $first_txn->txn_type = \MeprTransaction::$subscription_confirmation_str;
            $first_txn->save();

            if ( $pp_subscription['billing_info']['cycle_executions'][0]['tenure_type'] == 'TRIAL' &&
              $pp_subscription['billing_info']['cycle_executions'][0]['sequence'] == '1' &&
              $pp_subscription['billing_info']['cycle_executions'][0]['cycles_completed'] == '1'
            ) {
              $txn                  = $first_txn;
              $txn->expires_at      = MeprUtils::ts_to_mysql_date( time() + MeprUtils::days( $sub->trial_days ), 'Y-m-d 23:59:59' );
              $txn->gateway         = $this->id;
              $txn->subscription_id = $sub->id;
              $txn->store();
            }
          }

          // This will only work before maybe_cancel_old_sub is run
          $upgrade   = $sub->is_upgrade();
          $downgrade = $sub->is_downgrade();

          $event_txn = $sub->maybe_cancel_old_sub();

          if ( $upgrade ) {
            $this->upgraded_sub( $sub, $event_txn );
          } else if ( $downgrade ) {
            $this->downgraded_sub( $sub, $event_txn );
          } else {
            $this->new_sub( $sub, true );
          }

          $sub->save();

          MeprHooks::do_action( 'mepr-signup', $first_txn );
          MeprUtils::send_signup_notices( $txn );
        }
      }
    }
  }

  /**
   * @param $pp_order_id
   *
   * @throws MeprGatewayException
   */
  public function process_checkout_order_approved( $pp_order_id ) {
    $pp_order = $this->get_paypal_order_object( $pp_order_id );

    foreach ( $pp_order['purchase_units'] as $purchase_unit ) {
      $txn = new MeprTransaction( $purchase_unit['custom_id'] );
      $this->log( 'Capturing pp order' );
      $this->log( $txn );
      if ( $txn->total == (float) $purchase_unit['amount']['value'] ) {
        $pp_order = $this->capture_paypal_commerce_order( $pp_order_id );
        $this->log( 'Captured order ' . print_r( $pp_order, 1 ) . __LINE__ );

        if ( isset( $pp_order['status'] ) && ( $pp_order['status'] == 'COMPLETED' || $pp_order['status'] == 'PENDING' ) ) {
          if ( $txn->status == MeprTransaction::$complete_str ) {
            return;
          }

          $txn->status    = MeprTransaction::$complete_str;
          $txn->trans_num = $pp_order['purchase_units'][0]['payments']['captures'][0]['id'];
          $txn->save();

          $upgrade   = $txn->is_upgrade();
          $downgrade = $txn->is_downgrade();

          $event_txn = $txn->maybe_cancel_old_sub();
          $txn->store();
          $prd = $txn->product();

          if ( $prd->period_type == 'lifetime' ) {
            if ( $upgrade ) {
              $this->upgraded_sub( $txn, $event_txn );
            } elseif ( $downgrade ) {
              $this->downgraded_sub( $txn, $event_txn );
            } else {
              $this->new_sub( $txn );
            }

            MeprUtils::send_signup_notices( $txn );
          }

          MeprUtils::send_transaction_receipt_notices( $txn );

          return $txn;
        } else {
          if ( isset( $pp_order['details'] ) && is_array( $pp_order['details'] ) ) {
            if ($pp_order['details'][0]['issue'] === 'ORDER_ALREADY_CAPTURED') {
              return $txn;
            }
            $_POST['txn_id'] = $pp_order_id;
            $this->record_payment_failure();
            $this->log( 'capture declined' );
            throw new MeprGatewayException( __( ' Payer has not yet approved the Order for payment', 'memberpress' ) );
          }
        }
      }
    }
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
   * by the class to record a successful cancellation from the gateway. This method
   * should also be used by any IPN requests or Silent Posts.
   *
   * With PayPal, we bill the outstanding amount of the previous subscription,
   * cancel the previous subscription and create a new subscription
   */
  public function process_update_subscription( $sub_id ) {
    // Account info updated on PayPal.com
  }

  /** This method should be used by the class to record a successful cancellation
   * from the gateway. This method should also be used by any IPN requests or
   * Silent Posts.
   */
  public function record_update_subscription() {
    // Account info updated on PayPal.com
  }

  /** Used to suspend a subscription by the given gateway.
   */
  public function process_suspend_subscription( $sub_id ) {
    $sub = new MeprSubscription( $sub_id );

    if ( $sub->status == MeprSubscription::$suspended_str ) {
      throw new MeprGatewayException( __( 'This subscription has already been paused.', 'memberpress' ) );
    }

    if ( $sub->in_free_trial() ) {
      throw new MeprGatewayException( __( 'Sorry, subscriptions cannot be paused during a free trial.', 'memberpress' ) );
    }

    $this->update_paypal_payment_profile( $sub_id, 'Suspend' );

    $_REQUEST['subscr_id'] = $sub->subscr_id;
    $this->record_suspend_subscription();
  }

  /** This method should be used by the class to record a successful suspension
   * from the gateway.
   */
  public function record_suspend_subscription() {
    $subscr_id = $_REQUEST['subscr_id'];
    $sub       = MeprSubscription::get_one_by_subscr_id( $subscr_id );

    if ( ! $sub ) {
      return false;
    }

    // Seriously ... if sub was already suspended what are we doing here?
    if ( $sub->status == MeprSubscription::$suspended_str ) {
      return $sub;
    }

    $sub->status = MeprSubscription::$suspended_str;
    $sub->store();

    MeprUtils::send_suspended_sub_notices( $sub );

    return $sub;
  }

  /** Used to suspend a subscription by the given gateway.
   */
  public function process_resume_subscription( $sub_id ) {
    $sub = new MeprSubscription( $sub_id );
    $this->update_paypal_payment_profile( $sub_id, 'Reactivate' );

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
    $this->record_resume_subscription();
  }

  /** This method should be used by the class to record a successful resuming of
   * as subscription from the gateway.
   */
  public function record_resume_subscription() {
    //APPARENTLY PAYPAL DOES NOT SEND OUT AN IPN FOR THIS -- SO WE CAN'T ACTUALLY RECORD THIS HERE UGH
    //BUT WE DO SET THE SUBSCR STATUS BACK TO ACTIVE WHEN THE NEXT PAYMENT CLEARS
    $subscr_id = $_REQUEST['recurring_payment_id'];
    $sub       = MeprSubscription::get_one_by_subscr_id( $subscr_id );

    if ( ! $sub ) {
      return false;
    }

    // Seriously ... if sub was already active what are we doing here?
    if ( $sub->status == MeprSubscription::$active_str ) {
      return $sub;
    }

    $sub->status = MeprSubscription::$active_str;
    $sub->store();

    //Check if prior txn is expired yet or not, if so create a temporary txn so the user can access the content immediately
    $prior_txn = $sub->latest_txn();
    if ( $prior_txn == false || ! ( $prior_txn instanceof MeprTransaction ) || strtotime( $prior_txn->expires_at ) < time() ) {
      $txn                  = new MeprTransaction();
      $txn->subscription_id = $sub->id;
      $txn->trans_num       = $sub->subscr_id . '-' . uniqid();
      $txn->status          = MeprTransaction::$confirmed_str;
      $txn->txn_type        = MeprTransaction::$subscription_confirmation_str;
      $txn->expires_at      = MeprUtils::ts_to_mysql_date( $sub->get_expires_at() );
      $txn->set_subtotal( 0.00 ); // Just a confirmation txn
      $txn->store();
    }

    MeprUtils::send_resumed_sub_notices( $sub );

    return $sub;
  }

  /** Used to cancel a subscription by the given gateway. This method should be used
   * by the class to record a successful cancellation from the gateway. This method
   * should also be used by any IPN requests or Silent Posts.
   */
  public function process_cancel_subscription( $sub_id ) {
    $sub = new MeprSubscription( $sub_id );

    // Should already expire naturally at paypal so we have no need
    // to do this when we're "cancelling" because of a natural expiration
    if ( ! isset( $_REQUEST['expire'] ) || isset( $_REQUEST['limit_payment_cycles'] ) ) {
      $this->update_paypal_payment_profile( $sub_id, 'Cancel' );
    }

    $_REQUEST['subscr_id'] = $sub->subscr_id;
    $this->record_cancel_subscription();
  }

  /** This method should be used by the class to record a successful cancellation
   * from the gateway. This method should also be used by any IPN requests or
   * Silent Posts.
   */
  public function record_cancel_subscription() {
    // Not sure how/why this would happen but fail silently if it does
    if ( ! isset( $_REQUEST['subscr_id'] ) && ! isset( $_REQUEST['recurring_payment_id'] ) ) {
      return false;
    }

    $subscr_id = ( isset( $_REQUEST['subscr_id'] ) ) ? $_REQUEST['subscr_id'] : $_REQUEST['recurring_payment_id'];
    $sub       = MeprSubscription::get_one_by_subscr_id( $subscr_id );

    if ( ! $sub ) {
      return false;
    }

    // Seriously ... if sub was already cancelled what are we doing here?
    if ( $sub->status == MeprSubscription::$cancelled_str ) {
      return $sub;
    }

    $sub->status = MeprSubscription::$cancelled_str;
    $sub->store();

    if ( isset( $_REQUEST['expire'] ) ) {
      $sub->limit_reached_actions();
    }

    if ( ! isset( $_REQUEST['silent'] ) || ( $_REQUEST['silent'] == false ) ) {
      MeprUtils::send_cancelled_sub_notices( $sub );
    }

    return $sub;
  }

  public function process_signup_form( $txn ) {
    if ( isset( $_POST['smart-payment-button'] ) && $_POST['smart-payment-button'] == true ) {
      $data = $this->setup_payment_with_paypal_commerce( $txn, true );
      wp_send_json( $data );
      die;
    } else {
      $this->setup_payment_with_paypal_commerce( $txn );
    }
  }

  /** This gets called on the 'init' hook when the signup form is processed ...
   * this is in place so that payment solutions like paypal can redirect
   * before any content is rendered.
   */
  public function display_payment_page( $txn ) {
    //
  }

  public function calculate_subscription_trial_vars( $sub ) {
    $sub_vars = array();

    //Trial Amount
    $sub_vars['a1'] = $this->format_currency( $sub->trial_total );

    //Trial Days, Weeks, Months, or Years
    if ( $sub->trial_days <= 90 ) {
      $sub_vars['p1'] = $sub->trial_days;
      $sub_vars['t1'] = 'D';
    } else {
      if ( $sub->trial_days % 30 == 0 ) { //30 days in a month
        $sub_vars['p1'] = (int) ( $sub->trial_days / 30 );
        $sub_vars['t1'] = 'M';
      } elseif ( $sub->trial_days % 365 == 0 ) { //365 days in a year
        $sub_vars['p1'] = (int) ( $sub->trial_days / 365 );
        $sub_vars['t1'] = 'Y';
      } else { //force a round to the nearest week - that's the best we can do here
        $sub_vars['p1']  = round( (int) $sub->trial_days / 7 );
        $sub_vars['t1']  = 'W';
        $sub->trial_days = (int) ( $sub_vars['p1'] * 7 );
        $sub->store();
      }
    }

    return $sub_vars;
  }

  /** This gets called on wp_enqueue_script and enqueues a set of
   * scripts for use on the page containing the payment form
   */
  public function enqueue_payment_form_scripts() {
    if ( wp_script_is( 'mepr-paypalcommerce-form', 'enqueued' ) ) {
      return;
    }

    if ( $this->is_test_mode() ) {
      $client_id = $this->settings->test_client_id;
    } else {
      $client_id = $this->settings->live_client_id;
    }
    $mepr_options  = MeprOptions::fetch();
    $currency_code = strtoupper( $mepr_options->currency_code );
    if ( $this->settings->enable_smart_button == 'on' ) {
      wp_enqueue_script( 'paypal-sdk-js', 'https://www.paypal.com/sdk/js?vault=true&enable-funding=venmo&currency=' . $currency_code . '&client-id=' . $client_id, array(), null );
      wp_enqueue_script( 'mepr-paypalcommerce-form', MEPR_GATEWAYS_URL . '/paypal/form.js', array(
        'paypal-sdk-js',
        'mepr-checkout-js',
        'jquery.payment'
      ), MEPR_VERSION );
    }
  }

  protected function get_paypal_order_object( $pp_order_id ) {
    $response = wp_remote_get( $this->settings->rest_api_url . '/v2/checkout/orders/' . $pp_order_id, [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ]
    ] );

    $response = wp_remote_retrieve_body( $response );
    $response = json_decode( $response, true );

    return $response;
  }

  public function get_pp_basic_auth_token() {
    if ( $this->is_test_mode() ) {
      return base64_encode( $this->settings->test_client_id . ':' . $this->settings->test_client_secret );
    } else {
      return base64_encode( $this->settings->live_client_id . ':' . $this->settings->live_client_secret );
    }
  }

  public function get_paypal_subscription_transactions( $pp_subscription_id ) {
    $date = new DateTime();
    $date->sub( new DateInterval( 'P1D' ) );
    $time = 'start_time=' . $date->format( 'Y-m-d' ) . 'T00:00:00.90Z&end_time=' . date( 'Y-m-d' ) . 'T23:59:59.90Z';
    $this->log( $this->settings->rest_api_url . '/v1/billing/subscriptions/' . $pp_subscription_id . '/transactions?' . $time );
    $response = wp_remote_get( $this->settings->rest_api_url . '/v1/billing/subscriptions/' . $pp_subscription_id . '/transactions?' . $time, [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ]
    ] );

    $response = wp_remote_retrieve_body( $response );
    $response = json_decode( $response, true );

    return $response;
  }

  public function get_paypal_subscription_object( $pp_subscription_id ) {
    $response = wp_remote_get( $this->settings->rest_api_url . '/v1/billing/subscriptions/' . $pp_subscription_id, [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ]
    ] );

    $response = wp_remote_retrieve_body( $response );
    $response = json_decode( $response, true );

    return $response;
  }

  /**
   * @param $pp_payment_id
   *
   * @return array|mixed|string|WP_Error
   */
  protected function get_paypal_sale_payment_object( $pp_payment_id ) {
    $response = wp_remote_get( $this->settings->rest_api_url . '/v1/payments/sale/' . $pp_payment_id, [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ]
    ] );

    $response = wp_remote_retrieve_body( $response );
    $response = json_decode( $response, true );

    return $response;
  }

  /**
   * @param $method_id
   * @param bool $sandbox
   *
   * @return bool|string
   */
  public static function get_paypal_connect_url( $method_id, $sandbox = false ) {
    $base_return_url = admin_url( 'admin.php?page=memberpress-account-login&paypal-connect=1&method_id=' . $method_id, false );

    $error_url = add_query_arg( array(
      'mepr-action' => 'error'
    ), $base_return_url );

    if ( $sandbox ) {
      $base_return_url = add_query_arg( array(
        'sandbox' => '1'
      ), $base_return_url );
    }

    $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

    if ( empty( $site_uuid ) ) {
      return false;
    }

    $mepr_options = MeprOptions::fetch();
    $pm           = new self();
    $pm->load( array( 'id' => $method_id ) );

    $payload = array(
      'method_id'           => $pm->id,
      'site_uuid'           => $site_uuid,
      'user_uuid'           => get_option( 'mepr_authenticator_user_uuid' ),
      'return_url'          => $base_return_url,
      'error_url'           => $error_url,
      'webhook_url'         => $pm->notify_url( 'whk' ),
      'service_webhook_url' => $pm->notify_url( 'paypal-service-whk' ),
      'mp_version'          => MEPR_VERSION
    );

    $jwt = MeprAuthenticatorCtrl::generate_jwt( $payload );

    if ( $sandbox ) {
      $service_url = MEPR_PAYPAL_SERVICE_URL . "/sandbox/onboarding/";
    } else {
      $service_url = MEPR_PAYPAL_SERVICE_URL . "/onboarding/";
    }

    return add_query_arg( [
      'site_uuid' => $site_uuid,
      'method_id' => $method_id,
      'jwt'       => $jwt,
    ], $service_url );
  }

  /**
   * @param $txn
   *
   * @throws MeprGatewayException
   */
  public function setup_payment_with_paypal_commerce( $txn, $return_the_object = false ) {
    $mepr_options  = MeprOptions::fetch();
    $product       = $txn->product();
    $currency_code = strtoupper( $mepr_options->currency_code );
    $api_url       = $this->settings->rest_api_url;
    $access_token = $this->get_pp_basic_auth_token();
    $return_url = add_query_arg( [ 'txn_id' => $txn->id ], $this->notify_url( 'return' ) );
    $txn->update_meta( 'is_paypal_commerce', true );
    if ( $product->is_one_time_payment() ) {
      $payload = [
        "intent"              => "CAPTURE",
        "purchase_units"      => [
          [
            "custom_id"   => $txn->id,
            "description" => $product->post_title,
            "items"       => [
              [
                "name"        => $product->post_title,
                "unit_amount" => [
                  "currency_code" => $currency_code,
                  "value"         => $txn->amount,
                ],
                "tax"         => [
                  "currency_code" => $currency_code,
                  "value"         => $txn->tax_amount,
                ],
                "quantity"    => 1,
              ],
            ],
            "amount"      => [
              "currency_code" => $currency_code,
              "value"         => $txn->total,
              "breakdown"     => [
                "item_total" => [
                  "currency_code" => $currency_code,
                  "value"         => $txn->amount,
                ],
                "tax_total"  => [
                  "currency_code" => $currency_code,
                  "value"         => $txn->tax_amount,
                ],
              ],
            ],
          ],
        ],
        "application_context" => [
          "shipping_preference" => "NO_SHIPPING",
          "user_action"         => "PAY_NOW",
          "return_url"          => $return_url,
          "cancel_url"          => $this->notify_url( 'cancel' ),
        ],
      ];

      $payload = json_encode( $payload, JSON_UNESCAPED_SLASHES );

      $response = wp_remote_post( $api_url . '/v2/checkout/orders', [
        'body'      => $payload,
        'headers'   => [
          'Content-Type'                  => 'application/json',
          'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
          'Authorization'                 => 'Basic ' . $access_token
        ]
      ] );

      $response = json_decode( wp_remote_retrieve_body( $response ), true );
      $this->log( $payload );
      $this->log( $response );
      if ( isset( $response['links'] ) ) {
        foreach ( $response['links'] as $link ) {
          if ( $link['rel'] == 'approve' ) {
            $txn->trans_num = $response['id'];
            $txn->save();

            if ( $return_the_object ) {
              return $response;
            }
            $query = parse_url( $link['href'], PHP_URL_QUERY );

// Returns a string if the URL has parameters or NULL if not
            if ( $query ) {
              $link['href'] .= '&Return=TRUE';
            } else {
              $link['href'] .= '?Return=TRUE';
            }
            MeprUtils::wp_redirect( $link['href'] );
          }
        }
      } else {
        throw new MeprGatewayException( __( 'Could not create PayPal Order', 'memberpress' ) );
      }
    }

    if ( ! $product->is_one_time_payment() && ( $sub = $txn->subscription() ) ) {
      // Get pp plan id
      $pp_plan_id = $this->get_pp_plan_id( $sub );

      $pp_subscription = $this->get_pp_subscription( $pp_plan_id, $txn, $sub, $return_the_object );

      if ( $return_the_object ) {
        return $pp_subscription;
      }

      if ( isset( $pp_subscription['links'] ) ) {
        foreach ( $pp_subscription['links'] as $link ) {
          if ( $link['rel'] == 'approve' ) {
            $sub->subscr_id = $pp_subscription['id'];
            $sub->save();
            $this->log( $link['href'] );
            MeprUtils::wp_redirect( $link['href'] );
          }
        }
      }
    }
  }

  /**
   * Returs the payment form and required fields for the gateway
   */
  public function spc_payment_fields() {
    $mepr_options        = MeprOptions::fetch();
    $payment_method      = $this;
    $payment_form_action = 'mepr-paypal-payment-form';
    $user                = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;
    $membership_id       = get_the_ID();
    $product             = new MeprProduct( $membership_id );

    return MeprView::get_string( "/checkout/MeprPayPalCommerceGateway/payment_form", get_defined_vars() );
  }

  /**
   * This gets called on the_content and just renders the payment form
   * For PayPal Standard we're loading up a hidden form and submitting it with JS
   */
  public function display_payment_form( $amount, $user, $product_id, $transaction_id ) {
    $mepr_options        = MeprOptions::fetch();
    $payment_method      = $this;
    $payment_form_action = 'mepr-paypal-payment-form';
    $user                = MeprUtils::is_user_logged_in() ? MeprUtils::get_currentuserinfo() : null;
    $membership_id       = $product_id;
    $product             = new MeprProduct( $membership_id );
    ?>
    <div class="mp_wrapper mp_payment_form_wrapper">
      <form action="" method="post" id="mepr-paypal-payment-form">
        <?php
        echo MeprView::get_string( "/checkout/MeprPayPalCommerceGateway/payment_form", get_defined_vars() );
        ?>

        <input type="submit" class="mepr-submit" value="<?php _ex( 'Submit', 'ui', 'memberpress' ); ?>"/>
      </form>
    </div>
    <?php
  }

  /** Validates the payment form before a payment is processed */
  public function validate_payment_form( $errors ) {
    // PayPal does this on their own form
  }


  /**
   * Redirects the user to Paypal checkout
   *
   * @param MeprTransaction $txn
   */
  public function process_payment_form( $txn ) {
    if ( $txn->amount > 0.00 ) {

      if ( $this->is_paypal_connected() || $this->is_paypal_connected_live() ) {
        $this->setup_payment_with_paypal_commerce( $txn );
      } else {
        throw new MeprGatewayException( __( 'Payment gateway is not fully configured. Please contact support.', 'memberpress' ) );
      }
    } else {
      MeprTransaction::create_free_transaction( $txn );
    }
  }

  /**
   * @param MeprProduct $product
   *
   * @return string
   * @throws MeprGatewayException
   */
  public function get_pp_product_id( MeprProduct $product ) {
    $args = [
      'name' => $product->post_title,
      'type' => 'SERVICE',
    ];

    $meta_key = '_mepr_paypal_product_';
    $meta_key .= $this->id . '_';

    if ( $this->is_test_mode() ) {
      $meta_key .= 'test_';
    }

    $meta_key      .= implode( '_', $args );
    $meta_key      = sanitize_title( $meta_key );
    $pp_product_id = get_post_meta( $product->ID, $meta_key, true );

    if ( empty( $pp_product_id ) ) {
      // Create new pp product id
      $responseRaw = wp_remote_post( $this->settings->rest_api_url . '/v1/catalogs/products', [
        'headers' => [
          'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
          'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
          'Content-Type'                  => 'application/json'
        ],
        'body'    => json_encode( $args )
      ] );

      $response = json_decode( wp_remote_retrieve_body( $responseRaw ), true );

      if ( isset( $response['id'] ) ) {
        $pp_product_id = $response['id'];
        update_post_meta( $product->ID, $meta_key, $response['id'] );

        return $pp_product_id;
      } else {

        $this->log( $responseRaw );
        throw new MeprGatewayException( __( 'Could not create PayPal product', 'memberpress' ) );
      }
    }

    return $pp_product_id;
  }

  /**
   * @param $pp_plan_id
   * @param $txn
   * @param $sub
   *
   * @return array|WP_Error
   * @throws MeprGatewayException
   */
  public function get_pp_subscription( $pp_plan_id, $txn, MeprSubscription $sub, $return_the_request = false ) {
    $mepr_options = MeprOptions::fetch();

    if ( $mepr_options->attr( 'tax_calc_type' ) == 'inclusive' ) {
      $tax_inclusive = true;
    } else {
      $tax_inclusive = false;
    }

    $calculate_taxes = get_option( 'mepr_calculate_taxes' );
    $args = [
      'plan_id'             => $pp_plan_id,
      'custom_id'           => $sub->id,
      "application_context" => [
        "shipping_preference" => "NO_SHIPPING",
        "user_action"         => "SUBSCRIBE_NOW",
        "cancel_url"          => $this->notify_url( 'cancel' ),
        "return_url"          => add_query_arg( [ 'txn_id' => $txn->id ], $this->notify_url( 'return' ) ),
      ],
    ];

    if ( $calculate_taxes ) {
      $args['plan'] = [
        'taxes' => [
          'percentage' => $sub->tax_rate,
          'inclusive'  => $tax_inclusive,
        ],
      ];
    }
    $args     = MeprHooks::apply_filters( 'mepr_paypal_subcription_args', $args, $sub );
    $options  = [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ],
      'body'    => json_encode( $args, JSON_UNESCAPED_SLASHES ),
    ];
    $response = wp_remote_post( $this->settings->rest_api_url . '/v1/billing/subscriptions', $options );

    $raw      = wp_remote_retrieve_body( $response );
    $response = json_decode( $raw, true );

    if ( $return_the_request ) {
      return $response;
    }

    if ( isset( $response['id'] ) ) {
      return $response;
    } else {
      $this->log( $options );
      $this->log( $raw );
      throw new MeprGatewayException( __( 'Could not create PayPal subscription', 'memberpress' ) );
    }
  }

  /**
   * @param MeprSubscription $sub
   *
   * @return string
   * @throws MeprGatewayException
   */
  public function get_pp_plan_id( MeprSubscription $sub ) {
    $product      = $sub->product();
    $mepr_options = MeprOptions::fetch();

    if ( $mepr_options->attr( 'tax_calc_type' ) == 'inclusive' ) {
      $tax_inclusive = true;
      $amount        = round( $sub->total, 2 );
      $trial_amount  = isset ( $sub->trial_total ) && $sub->trial_total ? $sub->trial_total : 0;
    } else {
      $tax_inclusive = false;
      $amount        = round( $sub->total - $sub->tax_amount, 2 );
      $trial_amount  = isset ( $sub->trial_total ) && $sub->trial_total ? $sub->trial_total - $sub->trial_tax_amount : 0;
    }

    $interval = 'day';

    if ( $sub->period_type == 'months' ) {
      $interval = 'month';
    } else if ( $sub->period_type == 'years' ) {
      $interval = 'year';
    } else if ( $sub->period_type == 'weeks' ) {
      $interval = 'week';
    }

    $args = array(
      'amount'         => $amount,
      'method_id'      => $this->id,
      'tax_inclusive'  => $tax_inclusive ? 'yes' : 'no',
      'test'           => $this->is_test_mode() ? 'test' : 'live',
      'interval'       => $interval,
      'period'         => $sub->period,
      'trial'          => $sub->trial ? 'yes' : 'no',
      'trial_total'    => $trial_amount,
      'total_cycles'   => $sub->limit_cycles_num,
      'trial_days'     => isset ( $sub->trial_days ) && $sub->trial_days ? $sub->trial_days : 0,
      'interval_count' => $sub->period,
      'currency'       => $mepr_options->currency_code,
    );

    $plan_meta_key = '_mepr_paypal_plan_' . implode( '_', $args );
    $plan_id       = get_post_meta( $product->ID, $plan_meta_key, true );

    if ( empty( $plan_id ) ) {
      $pp_product_id   = $this->get_pp_product_id( $product );
      $billing_cycles  = [];
      $sequence_number = 1;

      if ( $args['trial'] == 'yes' ) {
        $billing_cycles[] = [
          'frequency'      => [
            'interval_unit'  => 'DAY',
            'interval_count' => $args['trial_days'],
          ],
          'pricing_scheme' => [
            'fixed_price' => [
              'currency_code' => $mepr_options->currency_code,
              'value'         => $trial_amount,
            ]
          ],
          'tenure_type'    => 'TRIAL',
          'sequence'       => $sequence_number,
          'total_cycles'   => 1,
        ];

        $sequence_number ++;
      }

      $billing_cycles[] = [
        'frequency'      => [
          'interval_unit'  => strtoupper( $interval ),
          'interval_count' => intval( $sub->period ),
        ],
        'pricing_scheme' => [
          'fixed_price' => [
            'currency_code' => $mepr_options->currency_code,
            'value'         => (string) $amount,
          ]
        ],
        'tenure_type'    => 'REGULAR',
        'sequence'       => $sequence_number,
        'total_cycles'   => ( $sub->limit_cycles && $sub->limit_cycles_num >= 1 ) ? $sub->limit_cycles_num : 0,
      ];

      $request_args = [
        'product_id'          => $pp_product_id,
        'name'                => $product->post_title,
        'status'              => 'ACTIVE',
        'billing_cycles'      => $billing_cycles,
        'payment_preferences' => [
          'auto_bill_outstanding'     => true,
          'payment_failure_threshold' => 3,
        ],
        'taxes'               => [
          'percentage' => $sub->tax_rate,
          "inclusive"  => $tax_inclusive,
        ],
      ];

      $request_args = MeprHooks::apply_filters( 'mepr_paypal_plan_args', $request_args, $sub );
      $this->log( 'Sending to pp plan' . print_r( $request_args, true ) );

      $options  = [
        'headers' => [
          'Content-Type'                  => 'application/json',
          'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
          'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
        ],
        'body'    => wp_json_encode( $request_args ),
      ];
      $response = wp_remote_post( $this->settings->rest_api_url . '/v1/billing/plans', $options );
      $raw      = wp_remote_retrieve_body( $response );
      $response = json_decode( $raw, true );

      if ( isset( $response['id'] ) ) {
        $this->log( $options );
        $plan_id = $response['id'];
        update_post_meta( $product->ID, $plan_meta_key, $plan_id );

        return $plan_id;
      } else {
        $this->log( $request_args );
        $this->log( $options );
        $this->log( $raw );
        $this->log( $response );
        throw new MeprGatewayException( __( 'Could not create Plan', 'memberpress' ) );
      }
    }

    return $plan_id;
  }

  /** Displays the form for the given payment gateway on the MemberPress Options page */
  public function display_options_form() {
    $mepr_options = MeprOptions::fetch();
    $pm           = $this;
    $upgraded_from_standard = false;

    if ( isset( $mepr_options->legacy_integrations[ $this->id ] ) ) {
      $upgraded_from_standard = true;
    }

    $debug           = defined( 'WP_MEPR_DEBUG' ) && WP_MEPR_DEBUG === true;
    $settings        = $this->settings;
    $buffer_settings = get_option( 'mepr_buff_integrations', [] );

    if ( isset( $buffer_settings[ $this->id ] ) ) {
      foreach ( [ 'test_merchant_id', 'live_merchant_id', 'test_email_confirmed', 'live_email_confirmed' ] as $key ) {
        if ( isset( $buffer_settings[ $this->id ][ $key ] ) ) {
          $settings->{$key}                                = $buffer_settings[ $this->id ][ $key ];
          $mepr_options->integrations[ $this->id ][ $key ] = $buffer_settings[ $this->id ][ $key ];
        }
      }
    }

    $test_client_id_str       = "{$mepr_options->integrations_str}[{$this->id}][test_client_id]";
    $test_client_secret_str   = "{$mepr_options->integrations_str}[{$this->id}][test_client_secret]";
    $live_client_id_str       = "{$mepr_options->integrations_str}[{$this->id}][live_client_id]";
    $live_client_secret_str   = "{$mepr_options->integrations_str}[{$this->id}][live_client_secret]";
    $test_webhook_id_str      = "{$mepr_options->integrations_str}[{$this->id}][test_webhook_id]";
    $live_webhook_id_str      = "{$mepr_options->integrations_str}[{$this->id}][live_webhook_id]";
    $test_merchant_id_str     = "{$mepr_options->integrations_str}[{$this->id}][test_merchant_id]";
    $live_merchant_id_str     = "{$mepr_options->integrations_str}[{$this->id}][live_merchant_id]";
    $test_email_confirmed_str = "{$mepr_options->integrations_str}[{$this->id}][test_email_confirmed]";
    $live_email_confirmed_str = "{$mepr_options->integrations_str}[{$this->id}][live_email_confirmed]";
    $enable_smart_button_str  = "{$mepr_options->integrations_str}[{$this->id}][enable_smart_button]";

    $account_email       = get_option( 'mepr_authenticator_account_email' );
    $secret              = get_option( 'mepr_authenticator_secret_token' );
    $site_uuid           = get_option( 'mepr_authenticator_site_uuid' );
    $payment_id          = $this->id;
    $enable_smart_button = $settings->enable_smart_button == 'on';
    if ( $account_email && $secret && $site_uuid ) {
      $paypal_connect_url_sandbox = self::get_paypal_connect_url( $this->id, true );
      $paypal_connect_url         = self::get_paypal_connect_url( $this->id );
    } else {
      $memberpress_connect_url = MeprAuthenticatorCtrl::get_auth_connect_url( false, $this->id, [
        'paypal_connect' => true,
        'method_id'      => $this->id
      ] );
    }

    $base_return_url = add_query_arg( array(
      'action'   => 'mepr_paypal_connect_update_creds',
      '_wpnonce' => wp_create_nonce( 'paypal-update-creds' )
    ),
      admin_url( 'admin-ajax.php' )
    );

    $base_return_url_sandbox = add_query_arg( array(
      'action'   => 'mepr_paypal_connect_update_creds_sandbox',
      '_wpnonce' => wp_create_nonce( 'paypal-update-creds' )
    ),
      admin_url( 'admin-ajax.php' )
    );
    $paypal_js_url           = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
    MeprView::render( '/admin/gateways/paypal/connect-migrate-prompt', get_defined_vars() );
  }

  /** Validates the form for the given payment gateway on the MemberPress Options page */
  public function validate_options_form( $errors ) {
    $mepr_options = MeprOptions::fetch();

    return $errors;
  }

  /** Displays the update account form on the subscription account page **/
  public function display_update_account_form( $sub_id, $errors = array(), $message = '' ) {
    ?>
    <h3><?php _e( 'Updating your PayPal Account Information', 'memberpress' ); ?></h3>
    <div><?php printf( __( 'To update your PayPal Account Information, please go to %sPayPal.com%s, login and edit your account information there.', 'memberpress' ), '<a href="http://paypal.com" target="blank">', '</a>' ); ?></div>
    <?php
  }

  public function is_ipn_for_me() {
    if(isset($_POST['custom']) && !empty($_POST['custom'])) {
      $custom_vars = (array)json_decode($_POST['custom']);

      if(isset($custom_vars['gateway_id']) && $custom_vars['gateway_id'] == $this->id) {
        return true;
      }
    }

    return false;
  }

  public function ipn_listener() {
    $_POST = wp_unslash( $_POST );
    $this->email_status( "PayPal IPN Recieved\n" . MeprUtils::object_to_string( $_POST, true ) . "\n", $this->debug );

    if ( $this->validate_ipn() ) {
      $mepr_options = MeprOptions::fetch();

      if ( ! isset( $mepr_options->legacy_integrations[ $this->id ] ) ) {
        return false;
      }

      $standard_gateway = new MeprPayPalStandardGateway();
      $standard_gateway->load( $mepr_options->legacy_integrations[ $this->id ] );
      return $standard_gateway->process_ipn();
    }

    return false;
  }

  public function webhook_handler() {
    $request = @file_get_contents( 'php://input' );
    $request = json_decode( $request, true );
    $this->log( $request );

    if ( ! isset( $request['event_type'] ) ) {
      return;
    }

    if ( $request['event_type'] == 'CHECKOUT.ORDER.APPROVED' ) {
      if ( isset ( $request['resource'] ) && isset ( $request['resource']['id'] ) ) {
        $this->process_checkout_order_approved( $request['resource']['id'] );
      }
    } elseif ( $request['event_type'] == 'MEMBERPRESS_CAPTURE_ORDER' ) {
      try {
        $this->process_checkout_order_approved( $request['order_id'] );
        wp_send_json( [
          'error'   => 'NONE',
          'message' => esc_attr_x( 'Payment completed', 'memberpress' ),
        ] );
        die;
      } catch ( Exception $e ) {
        wp_send_json( [
          'error'   => 'INSTRUMENT_DECLINED',
          'message' => $e->getMessage(),
        ] );
        die;
      }
    } elseif ( $request['event_type'] == 'BILLING.SUBSCRIPTION.ACTIVATED' ) {
      $this->process_checkout_supscription_approved( $request['resource']['id'] );
    } elseif ( $request['event_type'] == 'BILLING.SUBSCRIPTION.CANCELLED' || $request['event_type'] == 'BILLING.SUBSCRIPTION.EXPIRED' ) {
      $_REQUEST['subscr_id'] = $request['resource']['id'];
      $this->record_cancel_subscription();
    } elseif ( $request['event_type'] == 'BILLING.SUBSCRIPTION.SUSPENDED' ) {
      $_REQUEST['subscr_id'] = $request['resource']['id'];
      $this->record_suspend_subscription();
    } elseif ( $request['event_type'] == 'PAYMENT.CAPTURE.DENIED' ) {
      $_POST['txn_id'] = $request['resource']['id'];
      $this->record_payment_failure();
    } elseif ( $request['event_type'] == 'PAYMENT.SALE.REFUNDED' ) {
      $txn_num                = $request['resource']['sale_id'];
      $existing_txn           = MeprTransaction::get_one_by_trans_num( $txn_num );
      $_POST['parent_txn_id'] = $existing_txn->id;
      $this->record_refund();
    } elseif ( in_array( $request['event_type'], [ 'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REFUNDED' ] ) ) {
      $links   = $request['resource']['links'];
      $txn_num = '';

      foreach ( $links as $link ) {
        if ( $link['rel'] == 'up' ) {
          $href    = explode( '/', $link['href'] );
          $txn_num = array_pop( $href );
        }
      }

      $existing_txn           = MeprTransaction::get_one_by_trans_num( $txn_num );
      $_POST['parent_txn_id'] = $existing_txn->id;
      $this->record_refund();
    } elseif ( $request['event_type'] == 'PAYMENT.SALE.COMPLETED' ) {
      $pp_payment = $this->get_paypal_sale_payment_object( $request['resource']['id'] );
      $this->log( 'Processing recurring payment' );
      $this->log( $pp_payment );

      if ( $pp_payment['state'] == 'completed' && isset( $pp_payment['custom'] ) ) {
        $sub = new MeprSubscription( $pp_payment['custom'] );

        if ( $sub->subscr_id == $pp_payment['billing_agreement_id'] ) {
          $_POST['recurring_payment_id'] = $pp_payment['id'];
          $_POST['txn_id']               = $pp_payment['id'];
          $_POST['mc_gross']             = $pp_payment['amount']['total'];
          $_POST['payment_date']         = $pp_payment['create_time'];
          $_POST['subscr_id']            = $pp_payment['billing_agreement_id'];

          $this->record_subscription_payment();
        }
      }
    }
  }

  /** Validates the payment form before a payment is processed */
  public function validate_update_account_form( $errors = array() ) {
    // We'll have them update their cc info on paypal.com
  }

  /** Actually pushes the account update to the payment processor */
  public function process_update_account_form( $sub_id ) {
    // We'll have them update their cc info on paypal.com
  }

  /** Returns boolean ... whether or not we should be sending in test mode or not */
  public function is_test_mode() {
    if ( $this->is_paypal_connected() && ! $this->is_paypal_connected_live() ) {
      return true;
    }

    if ( $this->is_paypal_connected_live() ) {
      return false;
    }

    return true;
  }

  public function is_paypal_connected() {
    return ! empty( $this->settings->test_client_id );
  }

  public function is_paypal_connected_live() {
    return ! empty( $this->settings->live_client_id );
  }

  public function is_paypal_email_confirmed() {
    return ! empty( $this->settings->test_email_confirmed );
  }

  public function is_paypal_email_confirmed_live() {
    return ! empty( $this->settings->live_email_confirmed );
  }

  public function force_ssl() {
    return false; // redirects off site where ssl is installed
  }

  /**
   * Checks whether the user has a Paypal payment method that uses Paypal Connect
   *
   * @return boolean
   */
  public static function has_method_with_connect_status( $status = 'connected' ) {
    $mepr_options = MeprOptions::fetch();
    foreach ( $mepr_options->integrations as $integration ) {

      if ( ! isset( $integration['gateway'] ) || 'MeprPayPalCommerceGateway' !== $integration['gateway'] ) {
        continue;
      }

      return ! empty( $integration['test_client_id'] ) || ! empty( $integration['live_client_id'] );
    }

    return false;
  }

  private function update_paypal_payment_profile( $sub_id, $action = 'cancel' ) {
    $action = strtolower( $action );
    $sub    = new MeprSubscription( $sub_id );

    $options = [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ],
      'body'    => json_encode( [
        'reason' => esc_html( __( 'On request', 'memberpress' ) ),
      ], JSON_UNESCAPED_SLASHES ),
    ];

    if ( $action == 'reactivate' ) {
      $action = 'activate';
    }

    $this->log( $options );
    $endpoint = $this->settings->rest_api_url . '/v1/billing/subscriptions/' . $sub->subscr_id . '/' . $action;
    $this->log( $endpoint );

    $response      = wp_remote_post( $endpoint, $options );
    $response_code = wp_remote_retrieve_response_code( $response );

    $this->log( $response_code );

    if ( $response_code < 200 || $response_code >= 300 ) {
      throw new MeprGatewayException( __( 'There was a problem, try logging in directly at PayPal to update the status of your recurring profile.', 'memberpress' ) );
    }

    $_REQUEST['recurring_payment_id'] = $sub->subscr_id;
  }

  public function return_handler() {
    $this->email_status( "Paypal Return \$_REQUEST:\n" . MeprUtils::object_to_string( $_REQUEST, true ) . "\n", $this->debug );
    $mepr_options = MeprOptions::fetch();

    if ( isset( $_GET['txn_id'] ) ) {
      $txn = new MeprTransaction( filter_input( INPUT_GET, 'txn_id', FILTER_SANITIZE_NUMBER_INT ) );

      if ( empty( $txn->get_meta( 'is_paypal_commerce' ) ) ) {
        if ( ! isset( $mepr_options->legacy_integrations[ $this->id ] ) ) {
          return false;
        }

        $standard_gateway = new MeprPayPalStandardGateway();
        $standard_gateway->load( $mepr_options->legacy_integrations[ $this->id ] );
        $standard_gateway->return_handler();
        return false;
      }
    }

    if ( isset( $_GET['token'] ) && ! isset ( $_GET['subscription_id'] ) ) {
      // REturn from a one time payment
      $pp_order_id = filter_var( $_GET['token'] );
      $txn         = MeprTransaction::get_one_by_trans_num( $pp_order_id );
      $this->log($pp_order_id);
      $this->log($txn);
      if ( ! empty( $txn->id ) ) {
        $txn = new MeprTransaction( $txn->id );
      }

      try {
        $txn = $this->process_checkout_order_approved( $pp_order_id );
        $product     = $txn->product();
      } catch ( \Exception $e ) {
        $product     = $txn->product();
        $product_url = MeprUtils::get_permalink( $product->ID );
        MeprUtils::wp_redirect( add_query_arg( [
          'errors' => $e->getMessage(),
        ], $product_url ) );
      }
    }

    if ( isset( $_GET['token'] ) && isset ( $_GET['subscription_id'] ) ) {
      // Return from a subscription payment
      $pp_sub_id = sanitize_text_field( $_GET['subscription_id'] );
      $this->process_checkout_supscription_approved( $pp_sub_id );
      $sub     = MeprSubscription::get_one_by_subscr_id( $pp_sub_id );
      $product = $sub->product();
    }

    $sanitized_title = sanitize_title( $product->post_title );
    $query_params    = array(
      'membership'    => $sanitized_title,
      'membership_id' => $product->ID
    );

    MeprUtils::wp_redirect( $mepr_options->thankyou_page_url( build_query( $query_params ) ) );
  }

  public function cancel_handler() {
    $mepr_options = MeprOptions::fetch();
    // Handled with a GET REQUEST by PayPal
    $this->email_status( "Paypal Cancel \$_REQUEST:\n" . MeprUtils::object_to_string( $_REQUEST, true ) . "\n", $this->debug );

    if ( isset( $_REQUEST['txn_id'] ) && is_numeric( $_REQUEST['txn_id'] ) ) {
      $txn = new MeprTransaction( $_REQUEST['txn_id'] );

      // Make sure the txn status is pending
      $txn->status = MeprTransaction::$pending_str;
      $txn->store();

      if ( $sub = $txn->subscription() ) {
        $sub->status = MeprSubscription::$pending_str;
        $sub->store();
      }

      if ( isset( $txn->product_id ) && $txn->product_id > 0 ) {
        $prd = new MeprProduct( $txn->product_id );
        MeprUtils::wp_redirect( $this->message_page_url( $prd, 'cancel' ) );
      }
    }

    //If all else fails, just send them to their account page
    MeprUtils::wp_redirect( $mepr_options->account_page_url( 'action=subscriptions' ) );
  }

  public function cancel_message() {
    $mepr_options = MeprOptions::fetch();
    ?>
    <h4><?php _e( 'Your payment at PayPal was cancelled.', 'memberpress' ); ?></h4>
    <p><?php echo MeprHooks::apply_filters( 'mepr_paypal_cancel_message', sprintf( __( 'You can retry your purchase by %1$sclicking here%2$s.', 'memberpress' ), '<a href="' . MeprUtils::get_permalink() . '">', '</a>' ) ); ?>
      <br/></p>
    <?php
  }

  protected function capture_paypal_commerce_order( $pp_order_id ) {
    $response = wp_remote_post( $this->settings->rest_api_url . '/v2/checkout/orders/' . $pp_order_id . '/capture', [
      'headers' => [
        'Content-Type'                  => 'application/json',
        'PayPal-Partner-Attribution-Id' => MeprPayPalConnectCtrl::PAYPAL_BN_CODE,
        'Authorization'                 => 'Basic ' . $this->get_pp_basic_auth_token(),
      ],
    ] );

    $response = json_decode( wp_remote_retrieve_body( $response ), true );

    return $response;
  }
}
