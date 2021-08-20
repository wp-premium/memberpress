<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprQuadernoIntegration {
  public function __construct() {
    add_filter('mepr_stripe_payment_args',              array($this, 'stripe_payment_args'), 10, 2);
    add_filter('mepr_stripe_payment_intent_args',       array($this, 'stripe_payment_args'), 10, 2);
    add_filter('mepr_stripe_subscription_args',         array($this, 'stripe_subscription_args'), 10, 3);
    add_filter('mepr_stripe_resume_subscription_args',  array($this, 'stripe_resume_subscription_args'), 10, 2);
    add_filter('mepr_stripe_customer_args',             array($this, 'stripe_customer_args'), 10, 2);
    add_filter('mepr_stripe_create_customer_args',      array($this, 'stripe_create_customer_args'), 10, 2);
    add_filter('mepr_paypal_std_custom_payment_vars',   array($this, 'paypal_std_custom_payment_vars'), 10, 2);
    add_filter('mepr_paypal_std_payment_vars',          array($this, 'paypal_std_payment_vars'), 10, 2);
  }

  public function stripe_payment_args($args, $txn) {
    $usr = $txn->user();

    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    if(isset($txn->tax_rate) && $txn->tax_rate > 0) {
      $args['metadata']['tax_rate'] = $txn->tax_rate;
    }

    $args['metadata']['vat_number']     = get_user_meta($usr->ID, 'mepr_vat_number', true);
    $args['metadata']['invoice_email']  = $usr->user_email;

    return $args;
  }

  public function stripe_subscription_args($args, $txn, $sub) {
    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    if(isset($txn->tax_rate) && $txn->tax_rate > 0) {
      $args['metadata']['tax_rate'] = $txn->tax_rate;
    }

    return $args;
  }

  public function stripe_resume_subscription_args($args, $sub) {
    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    if(isset($sub->tax_rate) && $sub->tax_rate > 0) {
      $args['metadata']['tax_rate'] = $sub->tax_rate;
    }

    return $args;
  }

  public function stripe_customer_args($args, $sub) {
    return $this->stripe_create_customer_args($args, $sub->user());
  }

  public function stripe_create_customer_args($args, $usr) {
    if(!isset($args['metadata']) || !is_array($args['metadata'])) { $args['metadata'] = array(); }

    $args['metadata']['vat_number'] = get_user_meta($usr->ID, 'mepr_vat_number', true);

    return $args;
  }

  public function paypal_std_custom_payment_vars($custom, $txn) {
    $usr = $txn->user();

    if(!is_array($custom)) { $custom = array(); }

    $custom['vat_number']   = get_user_meta($usr->ID, 'mepr_vat_number', true);
    $custom['tax_id']   = get_user_meta($usr->ID, 'mepr_vat_number', true);

    if($txn->tax_rate > 0) {
      $custom['tax']['rate'] = $txn->tax_rate;
    }

    return $custom;
  }

  public function paypal_std_payment_vars($vars, $txn) {
    $user = $txn->user();

    if(!isset($vars['first_name']) && !empty($user->first_name)) {
      $vars['first_name'] = $user->first_name;
    }

    if(!isset($vars['last_name']) && !empty($user->last_name)) {
      $vars['last_name'] = $user->last_name;
    }

    $address1 = get_user_meta($user->ID, 'mepr-address-one', true);
    if(!isset($vars['address1']) && !empty($address1)) {
      $vars['address1'] = $address1;
    }

    $city = get_user_meta($user->ID, 'mepr-address-city', true);
    if(!isset($vars['city']) && !empty($city)) {
      $vars['city'] = $city;
    }

    $zip = get_user_meta($user->ID, 'mepr-address-zip', true);
    if(!isset($vars['zip']) && !empty($zip)) {
      $vars['zip'] = $zip;
    }

    $country = get_user_meta($user->ID, 'mepr-address-country', true);
    if(!isset($vars['country']) && !empty($country)) {
      $vars['country'] = $country;
    }

    return $vars;
  }
}

new MeprQuadernoIntegration;
