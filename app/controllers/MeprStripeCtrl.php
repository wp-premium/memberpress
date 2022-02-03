<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprStripeCtrl extends MeprBaseCtrl
{
  public function load_hooks() {
    add_action('wp_ajax_mepr_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_nopriv_mepr_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_mepr_stripe_create_checkout_session', array($this, 'create_checkout_session'));
    add_action('wp_ajax_nopriv_mepr_stripe_create_checkout_session', array($this, 'create_checkout_session'));
    add_action('wp_ajax_mepr_stripe_update_payment_method', array($this, 'update_payment_method'));
    add_action('wp_ajax_nopriv_mepr_stripe_update_payment_method', array($this, 'update_payment_method'));
    add_action('wp_ajax_mepr_stripe_debug_checkout_error', array($this, 'debug_checkout_error'));
    add_action('wp_ajax_nopriv_mepr_stripe_debug_checkout_error', array($this, 'debug_checkout_error'));
    add_action('mepr-update-new-user-email', array($this, 'update_user_email'), 10, 1);
  }

  /**
   * Update email of stripe user object when customer change email
   * on Memberpress account page
   *
   * @param MeprUser $mepr_current_user
   */
  public function update_user_email($mepr_current_user) {
    $mepr_options = MeprOptions::fetch();

    foreach ( $mepr_options->integrations as $integration ) {
      if ( $integration['gateway'] == 'MeprStripeGateway' ) {
        $payment_method = new MeprStripeGateway();
        $payment_method->load( $integration );
        $stripe_customer_id = $mepr_current_user->get_stripe_customer_id($payment_method->get_meta_gateway_id());

        if ( empty( $stripe_customer_id ) ) {
          // Continue on other instances of MeprStripeGateway
          continue;
        }

        $args = [ 'email' => $mepr_current_user->user_email ];

        try {
          $payment_method->send_stripe_request( 'customers/' . $stripe_customer_id, $args, 'post' );
        } catch (\Exception $exception) {}
      }
    }
  }

  public function create_checkout_session() {
    $this->do_confirm_payment(true);
  }

  public function confirm_payment() {
    MeprHooks::do_action('mepr_stripe_before_confirm_payment');

    try {
      $this->do_confirm_payment();
    } catch (Throwable $t) { // Errors and exceptions in PHP 7
      $content = $t->__toString();
    } catch (Exception $e) { // Exceptions in PHP 5
      $content = $e->__toString();
    }

    $this->send_checkout_error_debug_email(
      $content,
      isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : null,
      isset($_POST['user_email']) && is_string($_POST['user_email']) ? sanitize_text_field(wp_unslash($_POST['user_email'])) : null
    );

    wp_send_json(array('error' => __('An error occurred, please DO NOT submit the form again as you may be double charged. Please contact us for further assistance instead.', 'memberpress')));
  }

  public function do_confirm_payment($is_stripe_checkout_page = false) {
    $stripe_payment_method_id = isset($_POST['payment_method_id']) && is_string($_POST['payment_method_id']) ? sanitize_text_field(wp_unslash($_POST['payment_method_id'])) : '';
    $stripe_payment_intent_id = isset($_POST['payment_intent_id']) && is_string($_POST['payment_intent_id']) ? sanitize_text_field(wp_unslash($_POST['payment_intent_id'])) : '';
    $subscription_id = isset($_POST['subscription_id']) && is_string($_POST['subscription_id']) ? sanitize_text_field(wp_unslash($_POST['subscription_id'])) : '';
    $transaction_id = isset($_POST['mepr_transaction_id']) && is_numeric($_POST['mepr_transaction_id']) ? (int) $_POST['mepr_transaction_id'] : 0;

    if (empty($stripe_payment_method_id) && empty($stripe_payment_intent_id) && empty($subscription_id) && !$is_stripe_checkout_page) {
      wp_send_json(array('error' => __('Bad request', 'memberpress')));
    }

    if ($transaction_id > 0) {
      // Non-SPC
      $txn = new MeprTransaction($transaction_id);

      if (!$txn->id) {
        wp_send_json(array('error' => __('Transaction not found', 'memberpress')));
      }

      $pm = $txn->payment_method();

      if (!($pm instanceof MeprStripeGateway)) {
        wp_send_json(array('error' => __('Invalid payment gateway', 'memberpress')));
      }

      $product = $txn->product();

      if (!$product->ID) {
        wp_send_json(array('error' => __('Product not found', 'memberpress')));
      }

      $usr = $txn->user();

      if (!$usr->ID) {
        wp_send_json(array('error' => __('User not found', 'memberpress')));
      }

      // Prevent duplicate charges if the user is already subscribed
      $this->check_if_already_subscribed($usr, $product);
    } else {
      // We don't have a transaction ID (i.e. this is the Single Page Checkout), so let's create the user and transaction
      // This code is essentially the same as MeprCheckoutCtrl::process_signup_form
      $mepr_options = MeprOptions::fetch();
      $disable_checkout_password_fields = $mepr_options->disable_checkout_password_fields;

      // Validate the form post
      $mepr_current_url = isset($_POST['mepr_current_url']) && is_string($_POST['mepr_current_url']) ? sanitize_text_field(wp_unslash($_POST['mepr_current_url'])) : '';
      $errors = MeprHooks::apply_filters('mepr-validate-signup', MeprUser::validate_signup($_POST, array(), $mepr_current_url));
      if(!empty($errors)) {
        wp_send_json(array('errors' => $errors));
      }

      // Check if the user is logged in already
      $is_existing_user = MeprUtils::is_user_logged_in();

      if($is_existing_user) {
        $usr = MeprUtils::get_currentuserinfo();
      }
      else { // If new user we've got to create them and sign them in
        $usr = new MeprUser();
        $usr->user_login = ($mepr_options->username_is_email)?sanitize_email($_POST['user_email']):sanitize_user($_POST['user_login']);
        $usr->user_email = sanitize_email($_POST['user_email']);
        $usr->first_name = isset($_POST['user_first_name']) && !empty($_POST['user_first_name']) ? sanitize_text_field(wp_unslash($_POST['user_first_name'])) : '';
        $usr->last_name = isset($_POST['user_last_name']) && !empty($_POST['user_last_name']) ? sanitize_text_field(wp_unslash($_POST['user_last_name'])) : '';

        $password = ($disable_checkout_password_fields === true) ? wp_generate_password() : $_POST['mepr_user_password'];
        //Have to use rec here because we unset user_pass on __construct
        $usr->set_password($password);

        try {
          $usr->store();

          // We need to refresh the user object. In the case where emails are used as
          // usernames, the email & username could differ after the user is saved.
          $usr = new MeprUser($usr->ID);

          if($disable_checkout_password_fields === true) {
            $usr->send_password_notification('new');
          }
          // Log the new user in
          if(MeprHooks::apply_filters('mepr-auto-login', true, $_POST['mepr_product_id'], $usr)) {
            wp_signon(
              array(
                'user_login'    => $usr->user_login,
                'user_password' => $password
              ),
              MeprUtils::is_ssl() //May help with the users getting logged out when going between http and https
            );
          }

          MeprEvent::record('login', $usr); //Record the first login here
        }
        catch(MeprCreateException $e) {
          wp_send_json(array('error' => __('The user was unable to be saved.', 'memberpress')));
        }
      }

      // Create a new transaction and set our new membership details
      $txn = new MeprTransaction();
      $txn->user_id = $usr->ID;

      // Get the membership in place
      $txn->product_id = sanitize_text_field($_POST['mepr_product_id']);
      $product = $txn->product();

      if(empty($product->ID)) {
        wp_send_json(array('error' => __('Sorry, we were unable to find the membership.', 'memberpress')));
      }

      // Prevent duplicate charges if the user is already subscribed
      $this->check_if_already_subscribed($usr, $product);

      // If we're showing the fields on logged in purchases, let's save them here
      if(!$is_existing_user || ($is_existing_user && $mepr_options->show_fields_logged_in_purchases)) {
        MeprUsersCtrl::save_extra_profile_fields($usr->ID, true, $product, true);
        $usr = new MeprUser($usr->ID); //Re-load the user object with the metadata now (helps with first name last name missing from hooks below)
      }

      // Needed for autoresponders (SPC + Stripe + Free Trial issue)
      MeprHooks::do_action('mepr-signup-user-loaded', $usr);

      // Set default price, adjust it later if coupon applies
      $price = $product->adjusted_price();

      // Default coupon object
      $cpn = (object)array('ID' => 0, 'post_title' => null);

      // Adjust membership price from the coupon code
      if(isset($_POST['mepr_coupon_code']) && !empty($_POST['mepr_coupon_code'])) {
        // Coupon object has to be loaded here or else txn create will record a 0 for coupon_id
        $cpn = MeprCoupon::get_one_from_code(sanitize_text_field($_POST['mepr_coupon_code']));

        if(($cpn !== false) || ($cpn instanceof MeprCoupon)) {
          $price = $product->adjusted_price($cpn->post_title);
        }
      }

      $txn->set_subtotal($price);

      // Set the coupon id of the transaction
      $txn->coupon_id = $cpn->ID;

      // Figure out the Payment Method
      if(isset($_POST['mepr_payment_method']) && !empty($_POST['mepr_payment_method'])) {
        $txn->gateway = sanitize_text_field($_POST['mepr_payment_method']);
      }

      $pm = $txn->payment_method();

      if (!($pm instanceof MeprStripeGateway)) {
        wp_send_json(array('error' => __('Invalid payment gateway', 'memberpress')));
      }

      // Create a new subscription
      if($product->is_one_time_payment()) {
        $signup_type = 'non-recurring';
      }
      else {
        $signup_type = 'recurring';

        $sub = new MeprSubscription();
        $sub->user_id = $usr->ID;
        $sub->gateway = $pm->id;
        $sub->load_product_vars($product, $cpn->post_title, true);
        $sub->maybe_prorate(); // sub to sub
        $sub->store();

        $txn->subscription_id = $sub->id;
      }

      $txn->store();

      if(empty($txn->id)) {
        // Don't want any loose ends here if the $txn didn't save for some reason
        if($signup_type==='recurring' && ($sub instanceof MeprSubscription)) {
          $sub->destroy();
        }

        wp_send_json(array('error' => __('Sorry, we were unable to create a transaction.', 'memberpress')));
      }
    }

    try {
      if ($is_stripe_checkout_page) {
        if (!isset($sub)) { $sub = $txn->subscription(); }

        MeprHooks::do_action('mepr-process-signup', $txn->amount, $usr, $product->ID, $txn->id);

        $pm->create_checkout_session(
          $txn,
          $product,
          $usr,
          $stripe_payment_method_id,
          $sub
        );

        return;
      }
      if ($product->is_one_time_payment()) {
        // For one-time payments use a PaymentIntent
        if (empty($stripe_payment_intent_id)) {
          $payment_intent = $pm->create_payment_intent($txn, $usr, $stripe_payment_method_id);
        } else {
          $payment_intent = $pm->confirm_payment_intent($stripe_payment_intent_id);
        }

        if (!empty($payment_intent->status) && $payment_intent->status == 'requires_action') {
          if (!empty($payment_intent->next_action['type']) && $payment_intent->next_action['type'] == 'use_stripe_sdk') {
            // Tell the client to handle the action
            wp_send_json(array(
              'requires_action' => true,
              'action' => 'handleCardAction',
              'client_secret' => $payment_intent->client_secret,
              'transaction_id' => $txn->id
            ));
          } else {
            // This should never happen, but we can't continue
            throw new Exception(sprintf(__('Sorry, your payment could not be processed (%s)', 'memberpress'), 'unsupported next_action type'));
          }
        } elseif (!empty($payment_intent->status) && $payment_intent->status == 'succeeded') {
          $pm->handle_one_time_payment($txn, $payment_intent);
        } else {
          throw new Exception(sprintf(__('Sorry, there was an error processing your card (%s)', 'memberpress'), 'invalid PaymentIntent status'));
        }
      } else {
        $sub = $txn->subscription();

        if (!($sub instanceof MeprSubscription)) {
          wp_send_json(array(
            'error' => __('Subscription not found', 'memberpress'),
            'transaction_id' => $txn->id
          ));
        }

        if (empty($subscription_id)) {
          $subscription = $pm->create_subscription($txn, $sub, $product, $usr, $stripe_payment_method_id);
        } elseif (!empty($stripe_payment_method_id)) {
          $subscription = $pm->retry_subscription_payment($subscription_id, $usr, $stripe_payment_method_id);
        } else {
          $subscription = $pm->retrieve_subscription($subscription_id);
        }

        $invoice = (object) $subscription->latest_invoice;
        $payment_intent = isset($invoice->payment_intent) ? (object) $invoice->payment_intent : null;
        $setup_intent = isset($subscription->pending_setup_intent) ? (object) $subscription->pending_setup_intent : null;

        if(!empty($payment_intent->status) && $payment_intent->status == 'requires_action') {
          if(!empty($payment_intent->next_action['type']) && $payment_intent->next_action['type'] == 'use_stripe_sdk') {
            // Tell the client to handle the action
            wp_send_json(array(
              'requires_action' => true,
              'action' => 'confirmCardPayment',
              'client_secret' => $payment_intent->client_secret,
              'subscription_id' => $subscription->id,
              'transaction_id' => $txn->id
            ));
          } else {
            // This should never happen, but we can't continue
            throw new Exception(sprintf(__('Sorry, your payment could not be processed (%s)', 'memberpress'), 'unsupported next_action type'));
          }
        } elseif(!empty($setup_intent->status) && $setup_intent->status == 'requires_action') {
          if(!empty($setup_intent->next_action['type']) && $setup_intent->next_action['type'] == 'use_stripe_sdk') {
            // Tell the client to handle the action
            wp_send_json(array(
              'requires_action' => true,
              'action' => 'confirmCardSetup',
              'client_secret' => $setup_intent->client_secret,
              'subscription_id' => $subscription->id,
              'transaction_id' => $txn->id
            ));
          } else {
            // This should never happen, but we can't continue
            throw new Exception(sprintf(__('Sorry, your card setup could not be processed (%s)', 'memberpress'), 'unsupported next_action type'));
          }
        } elseif(!empty($payment_intent->status) && $payment_intent->status == 'requires_payment_method') {
          MeprHooks::do_action('mepr_stripe_payment_failed');

          wp_send_json(array(
            'error' => __('Card payment failed, please try another payment method', 'memberpress'),
            'subscription_id' => $subscription->id,
            'transaction_id' => $txn->id
          ));
        } elseif(!empty($setup_intent->status) && $setup_intent->status == 'requires_payment_method') {
          wp_send_json(array(
            'error' => __('Card setup failed, please try another payment method', 'memberpress'),
            'subscription_id' => $subscription->id,
            'transaction_id' => $txn->id
          ));
        } elseif($this->can_subscription_be_activated($subscription, $invoice, $payment_intent, $setup_intent)) {
          // Activate the subscription
          $pm->activate_subscription($txn, $sub);
        } else {
          throw new Exception(sprintf(__('Sorry, there was an error processing your card (%s)', 'memberpress'), 'invalid subscription state'));
        }
      }

      MeprHooks::do_action('mepr_stripe_payment_success', $txn, $usr );

      wp_send_json(array(
        'success' => true,
        'transaction_id' => $txn->id
      ));
    } catch (Exception $e) {
      if($e instanceof MeprRemoteException && strpos($e->getMessage(), '(card_error)') !== false) {
        MeprHooks::do_action('mepr_stripe_payment_failed');
      }

      wp_send_json(array(
        'error' => $e->getMessage(),
        'transaction_id' => $txn->id
      ));
    }
  }

  /**
   * Ends execution with a JSON error response if the user is already subscribed to this product
   *
   * @param MeprUser $usr
   * @param MeprProduct $product
   */
  private function check_if_already_subscribed($usr, $product) {
    $mepr_options = MeprOptions::fetch();

    if($usr->is_already_subscribed_to($product->ID) && !$product->simultaneous_subscriptions && !$product->allow_renewal && !$product->allow_gifting) {
      wp_send_json(array(
        'error' => sprintf(
          /* translators: %1$s: open link tag, %2$s: close link tag */
          esc_html__('You are already subscribed to this item, %1$sclick here%2$s to view your subscriptions.', 'memberpress'),
          '<a href="' . esc_url(add_query_arg(array('action' => 'subscriptions'), $mepr_options->account_page_url())) . '">',
          '</a>'
        )
      ));
    }
  }

  /**
   * Is the subscription in the correct state to be activated?
   *
   * @param  stdClass      $subscription   The Stripe Subscription data
   * @param  stdClass      $invoice        The Stripe Invoice data
   * @param  stdClass|null $payment_intent The Stripe PaymentIntent data (if any)
   * @param  stdClass|null $setup_intent   The Stripe SetupIntent data (if any)
   * @return bool
   */
  private function can_subscription_be_activated($subscription, $invoice, $payment_intent, $setup_intent) {
    if(!in_array($subscription->status, ['active', 'trialing'])) {
      return false;
    }

    if($invoice->status != 'paid') {
      return false;
    }

    if($payment_intent && !empty($payment_intent->status) && $payment_intent->status != 'succeeded') {
      return false;
    }

    if($setup_intent && !empty($setup_intent->status) && $setup_intent->status != 'succeeded') {
      return false;
    }

    return true;
  }

  /**
   * Handle the Ajax request to update the payment method for a subscription
   */
  public function update_payment_method() {
    $subscription_id = isset($_POST['subscription_id']) && is_numeric($_POST['subscription_id']) ? (int) $_POST['subscription_id'] : 0;
    $stripe_payment_method_id = isset($_POST['payment_method_id']) && is_string($_POST['payment_method_id']) ? sanitize_text_field($_POST['payment_method_id']) : '';
    $stripe_setup_intent_id = isset($_POST['setup_intent_id']) && is_string($_POST['setup_intent_id']) ? sanitize_text_field($_POST['setup_intent_id']) : '';
    $stripe_payment_intent_id = isset($_POST['payment_intent_id']) && is_string($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';

    if (empty($subscription_id)) {
      wp_send_json(array('error' => __('Bad request', 'memberpress')));
    }

    if (!is_user_logged_in()) {
      wp_send_json(array('error' => __('Sorry, you must be logged in to do this.', 'memberpress')));
    }

    if (!check_ajax_referer('mepr_process_update_account_form', '_mepr_nonce', false)) {
      wp_send_json(array('error' => __('Security check failed.', 'memberpress')));
    }

    $sub = new MeprSubscription($subscription_id);

    if (!($sub instanceof MeprSubscription)) {
      wp_send_json(array('error' => __('Subscription not found', 'memberpress')));
    }

    $usr = $sub->user();

    if ($usr->ID != get_current_user_id()) {
      wp_send_json(array('error' => __('This subscription is for another user.', 'memberpress')));
    }

    $pm = $sub->payment_method();

    if (!($pm instanceof MeprStripeGateway)) {
      wp_send_json(array('error' => __('Invalid payment gateway', 'memberpress')));
    }

    try {
      if(!empty($stripe_setup_intent_id)) {
        $setup_intent = $pm->retrieve_setup_intent($stripe_setup_intent_id);
        $payment_intent = null;
      }
      elseif(!empty($stripe_payment_intent_id)) {
        $payment_intent = $pm->retrieve_payment_intent($stripe_payment_intent_id);
        $setup_intent = null;
      }
      else {
        $setup_intent = $pm->create_setup_intent($sub, $stripe_payment_method_id);
        $payment_intent = null;
      }

      if($setup_intent) {
        if(!empty($setup_intent->status) && $setup_intent->status == 'requires_action') {
          if(!empty($setup_intent->next_action['type']) && $setup_intent->next_action['type'] == 'use_stripe_sdk') {
            // Tell the client to handle the action
            wp_send_json(array(
              'requires_action' => true,
              'action' => 'confirmCardSetup',
              'client_secret' => $setup_intent->client_secret
            ));
          } else {
            // This should never happen, but we can't continue
            throw new Exception(sprintf(__('Sorry, your card setup could not be processed (%s)', 'memberpress'), 'unsupported next_action type'));
          }
        }
        elseif(!empty($setup_intent->status) && $setup_intent->status == 'succeeded') {
          $pm->update_subscription_payment_method($sub, $usr, (object) $setup_intent->payment_method);

          // Check if there is an outstanding invoice to be paid
          if(strpos($sub->subscr_id, 'sub_') === 0) {
            $subscription = $pm->retrieve_subscription($sub->subscr_id);
          }
          else {
            $subscription = $pm->get_customer_subscription($sub->subscr_id);
          }

          if($subscription->latest_invoice && $subscription->latest_invoice['status'] == 'open') {
            try {
              $pm->retry_invoice_payment($subscription->latest_invoice['id']);
            } catch(Exception $e) {
              // If the invoice payment fails, the card may require authentication. So let's retrieve the payment intent
              // to see what needs to be done.
              $invoice = $pm->retrieve_invoice($subscription->latest_invoice['id']);
              $payment_intent = isset($invoice->payment_intent) ? (object) $invoice->payment_intent : null;
            }
          }
        }
        else {
          throw new Exception(sprintf(__('Sorry, there was an error processing your card (%s)', 'memberpress'), 'invalid SetupIntent status'));
        }
      }

      if($payment_intent) {
        if(!empty($payment_intent->status) && $payment_intent->status == 'requires_action') {
          if(!empty($payment_intent->next_action['type']) && $payment_intent->next_action['type'] == 'use_stripe_sdk') {
            // Tell the client to handle the action
            wp_send_json(array(
              'requires_action' => true,
              'action' => 'confirmCardPayment',
              'client_secret' => $payment_intent->client_secret
            ));
          }
          else {
            // This should never happen, but we can't continue
            throw new Exception(sprintf(__('Sorry, your payment could not be processed (%s)', 'memberpress'), 'unsupported next_action type'));
          }
        }
        elseif(!empty($payment_intent->status) && $payment_intent->status == 'requires_payment_method') {
          throw new Exception(__('Card payment failed, please try another payment method', 'memberpress'));
        }
        elseif(empty($payment_intent->status) || $payment_intent->status != 'succeeded') {
          throw new Exception(sprintf(__('Sorry, there was an error processing your card (%s)', 'memberpress'), 'invalid PaymentIntent status'));
        }
      }

      wp_send_json(array(
        'success' => true,
        'is_payment' => !empty($payment_intent)
      ));
    } catch (Exception $e) {
      wp_send_json(array('error' => $e->getMessage()));
    }
  }

  /**
   * Handle the Ajax request to debug a checkout error
   */
  public function debug_checkout_error() {
    if (!MeprUtils::is_post_request() || !isset($_POST['data']) || !is_string($_POST['data'])) {
      wp_send_json_error();
    }

    $data = json_decode(wp_unslash($_POST['data']), true);

    if (!is_array($data)) {
      wp_send_json_error();
    }

    $allowed_keys = array(
      'text_status' => 'textStatus',
      'error_thrown' => 'errorThrown',
      'status' => 'jqXHR.status (200 expected)',
      'status_text' => 'jqXHR.statusText (OK expected)',
      'response_text' => 'jqXHR.responseText (JSON object expected)'
    );

    $content = 'INVALID SERVER RESPONSE' . "\n\n";

    foreach ($allowed_keys as $key => $label) {
      if (!array_key_exists($key, $data)) {
        continue;
      }

      ob_start();
      var_dump($data[$key]);
      $value = ob_get_clean();

      $content .= sprintf(
        "%s:\n%s\n",
        $label,
        $value
      );
    }

    $this->send_checkout_error_debug_email(
      $content,
      isset($data['transaction_id']) && is_numeric($data['transaction_id']) ? (int) $data['transaction_id'] : null,
      isset($data['customer_email']) && is_string($data['customer_email']) ? sanitize_text_field($data['customer_email']) : null
    );

    wp_send_json_success();
  }

  /**
   * Sends an email to the admin email addresses alerting them of the given checkout error
   *
   * @param string      $content
   * @param int|null    $transaction_id
   * @param string|null $customer_email
   */
  private function send_checkout_error_debug_email($content, $transaction_id = null, $customer_email = null) {
    if (MeprHooks::apply_filters('mepr_disable_checkout_error_debug_email', false)) {
      return;
    }

    $message = 'An error occurred during the MemberPress checkout which resulted in an error message being displayed to the customer. The transaction may not have fully completed.' . "\n\n";
    $message .= 'The error may have happened due to a plugin conflict or custom code, please carefully check the details below to identify the cause, or you can send this email to support@memberpress.com for help.' . "\n\n";

    if ($transaction_id) {
      $message .= sprintf("MemberPress transaction ID: %s\n", $transaction_id);

      if (!$customer_email) {
        $transaction = new MeprTransaction($transaction_id);
        $user = $transaction->user();

        if ($user->ID > 0 && $user->user_email) {
          $customer_email = $user->user_email;
        }
      }
    }

    if ($customer_email) {
      $message .= sprintf("Customer email: %s\n", $customer_email);
    }

    $message .= sprintf("Customer IP: %s\n", $_SERVER['REMOTE_ADDR']);
    $message .= sprintf("Customer User Agent: %s\n", !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '(empty)');
    $message .= sprintf("Date (UTC): %s\n\n", gmdate('Y-m-d H:i:s'));

    MeprUtils::wp_mail_to_admin('[MemberPress] IMPORTANT: Checkout error', $message . $content);
  }
}
