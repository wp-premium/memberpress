<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** This will pick up some of the hook based events, more in-depth
  * processing of certain events and event cleanup maintenance tasks.
  */
class MeprEventsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('user_register', array($this, 'user_register'));
    add_action('delete_user', array($this, 'delete_user'));
    add_action('mepr-txn-expired', array($this, 'txn_expired'), 10, 2);
    add_filter('mepr_create_subscription', array($this, 'sub_created'));
  }

  public function user_register($user_id) {
    if(!empty($user_id)) {
      MeprEvent::record('member-added', (new MeprUser($user_id)));
    }
  }

  public function delete_user($user_id) {
    if(!empty($user_id)) {
      // Since the 'delete_user' action fires just before the user is deleted
      // we should still have access to the full MeprUser object for them
      MeprEvent::record('member-deleted', (new MeprUser($user_id)));
    }
  }

  /** Let's figure some stuff out from the txn-expired hook yo ... and send some proper events */
  public function txn_expired($txn, $sub_status) {
    // Assume the txn is expired (otherwise this action wouldn't fire)
    // Then ensure the subscription is expired before sending a sub expired event
    if( !empty($txn) &&
        $txn instanceof MeprTransaction &&
        (int)$txn->subscription_id > 0 &&
        ($sub = $txn->subscription()) &&
        $sub->status == MeprSubscription::$cancelled_str &&
        $sub->is_expired() ) {
      MeprEvent::record('subscription-expired', $sub, $txn);
    }
  }

  public function sub_created($sub_id) {
    $sub = new MeprSubscription($sub_id);
    MeprEvent::record('subscription-created', $sub);
    return $sub_id;
  }
} //End class
