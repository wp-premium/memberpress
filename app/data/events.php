<?php
return array(
  /***** Events for Members *****/
  'member-added' => (object)array(
    'unique' => true
  ),
  'member-signup-completed' => (object)array(
    'unique' => true
  ),
  'member-account-updated' => (object)array(
    'unique' => false
  ),
  'member-deleted' => (object)array(
    'unique' => true
  ),
  'login' => (object)array(
    'unique' => false
  ),

  /***** Events for Subscriptions *****/
  'subscription-created' => (object)array(
    'unique' => true
  ),
  'subscription-paused' => (object)array(
    'unique' => false
  ),
  'subscription-resumed' => (object)array(
    'unique' => false
  ),
  'subscription-stopped' => (object)array(
    'unique' => true
  ),
  'subscription-upgraded' => (object)array(
    'unique' => true
  ),
  'subscription-downgraded' => (object)array(
    'unique' => true
  ),
  'subscription-upgraded-to-one-time' => (object)array(
    'unique' => true
  ),
  'subscription-upgraded-to-recurring' => (object)array(
    'unique' => true
  ),
  'subscription-downgraded-to-one-time' => (object)array(
    'unique' => true
  ),
  'subscription-downgraded-to-recurring' => (object)array(
    'unique' => true
  ),
  'subscription-expired' => (object)array(
    'unique' => false
  ),

  /***** Events for Transactions *****/
  'transaction-completed' => (object)array(
    'unique' => true
  ),
  'transaction-refunded' => (object)array(
    'unique' => true
  ),
  'transaction-failed' => (object)array(
    'unique' => true
  ),
  'transaction-expired' => (object)array(
    'unique' => true
  ),
  'offline-payment-pending' => (object)array(
    'unique' => true
  ),
  'offline-payment-complete' => (object)array(
    'unique' => true
  ),
  'offline-payment-refunded' => (object)array(
    'unique' => true
  ),
  // Recurring Transactions
  'recurring-transaction-completed' => (object)array(
    'unique' => true
  ),
  'renewal-transaction-completed' => (object)array(
    'unique' => true
  ),
  'recurring-transaction-failed' => (object)array(
    'unique' => true
  ),
  'recurring-transaction-expired' => (object)array(
    'unique' => true
  ),
  // Non-Recurring Transactions
  'non-recurring-transaction-completed' => (object)array(
    'unique' => true
  ),
  'non-recurring-transaction-expired' => (object)array(
    'unique' => true
  ),

  /***** Events from Reminders *****/
  // Note, uniqueness of Reminders is handled by the reminders routines
  // So all reminders should be classified as non-unique here.
  'after-member-signup-reminder' => (object)array(
    'unique' => false
  ),
  'after-signup-abandoned-reminder' => (object)array(
    'unique' => false
  ),
  'before-sub-expires-reminder' => (object)array(
    'unique' => false
  ),
  'after-sub-expires-reminder' => (object)array(
    'unique' => false
  ),
  'before-sub-renews-reminder' => (object)array(
    'unique' => false
  ),
  'after-cc-expires-reminder' => (object)array(
    'unique' => false
  ),
  'before-cc-expires-reminder' => (object)array(
    'unique' => false
  ),
  'before-sub-trial-ends' => (object)array(
    'unique' => false
  ),

  /** Events for Corporate Accounts */
  'sub-account-added' => (object)array(
    'unique' => false
  ),
  'sub-account-removed' => (object)array(
    'unique' => false
  ),
);

