<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

abstract class MeprBaseRealGateway extends MeprBaseGateway {
  /**
   * Activate the subscription
   *
   * Also sets up the grace period confirmation transaction (if enabled).
   *
   * @param MeprTransaction  $txn The MemberPress transaction
   * @param MeprSubscription $sub The MemberPress subscription
   */
  public function activate_subscription(MeprTransaction $txn, MeprSubscription $sub) {
    $mepr_options = MeprOptions::fetch();

    $sub->status = MeprSubscription::$active_str;
    $sub->created_at = gmdate('c');
    $sub->store();

    // If trial amount is zero then we've got to make sure the confirmation txn lasts through the trial
    if($sub->trial && $sub->trial_amount <= 0.00) {
      $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($sub->trial_days), 'Y-m-d 23:59:59');
    } elseif(!$mepr_options->disable_grace_init_days && $mepr_options->grace_init_days > 0) {
      $expires_at = MeprUtils::ts_to_mysql_date(time() + MeprUtils::days($mepr_options->grace_init_days), 'Y-m-d 23:59:59');
    } else {
      $expires_at = $txn->created_at; // Expire immediately
    }

    $txn->trans_num = $sub->subscr_id;
    $txn->status = MeprTransaction::$confirmed_str;
    $txn->txn_type = MeprTransaction::$subscription_confirmation_str;
    $txn->expires_at = $expires_at;
    $txn->set_subtotal(0.00); // Just a confirmation txn

    // Ensure that the `mepr-txn-store` hook is called with an active subscription
    $txn->store(true);
  }
}
