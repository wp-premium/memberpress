<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprReminder extends MeprCptModel {
  public static $trigger_length_str   = 'mepr_trigger_length';
  public static $trigger_interval_str = 'mepr_trigger_interval';
  public static $trigger_timing_str   = 'mepr_trigger_timing';
  public static $trigger_event_str    = 'mepr_trigger_event';
  public static $filter_products_str  = '_mepr_reminder_filter_products_str';
  public static $products_str         = '_mepr_reminder_products';
  public static $emails_str           = '_mepr_emails';

  public static $nonce_str    = 'mepr_reminders_nonce';
  public static $last_run_str = 'mepr_reminders_db_cleanup_last_run';

  public static $cpt = 'mp-reminder';

  public $trigger_intervals, $trigger_timings, $trigger_events, $event_actions;

  /*** Instance Methods ***/
  public function __construct($obj = null) {
    $this->load_cpt(
      $obj,
      self::$cpt,
      array(
        'trigger_length'    => 1,
        'trigger_interval'  => 'days',
        'trigger_timing'    => 'before',
        'trigger_event'     => 'sub-expires',
        'filter_products'   => false, //Send only for specific memberships?
        'products'          => array(), //Empty array means ALL memberships
        'emails'            => array()
      )
    );

    $this->trigger_intervals = array('hours','days','weeks','months','years');
    $this->trigger_timings = array('before','after');
    $this->trigger_events = array(
      'sub-expires',
      'sub-renews',
      'cc-expires',
      'member-signup',
      'signup-abandoned',
      'sub-trial-ends'
    );

    $this->event_actions = array();
    foreach($this->trigger_events as $e) {
      foreach($this->trigger_timings as $t) {
        $this->event_actions[] = "mepr-event-{$t}-{$e}-reminder";
      }
    }
  }

  public function validate() {
    $this->validate_is_numeric($this->trigger_length, 0, null, 'trigger_length');
    $this->validate_is_in_array($this->trigger_interval, $this->trigger_intervals, 'trigger_interval');
    $this->validate_is_in_array($this->trigger_timing, $this->trigger_timings, 'trigger_timings');
    $this->validate_is_in_array($this->trigger_event, $this->trigger_events, 'trigger_events');
    $this->validate_is_bool($this->filter_products, 'filter_products');
    $this->validate_is_array($this->products, 'products');
    $this->validate_is_array($this->emails, 'emails');
  }

  public function events() {
  }

  public function trigger_event_name() {
    switch ($this->trigger_event) {
      case 'sub-expires': return __('Subscription Expires', 'memberpress');
      case 'sub-renews':  return __('Subscription Renews', 'memberpress');
      case 'cc-expires':  return __('Credit Card Expires', 'memberpress');
      case 'member-signup':  return __('Member Signs Up', 'memberpress');
      case 'signup-abandoned': return __('Sign Up Abandoned', 'memberpress');
      case 'sub-trial-ends':  return __('Subscription Trial Ending', 'memberpress');
      default: return $this->trigger_event;
    }
  }

  public function get_trigger_interval_str() {
    return MeprUtils::period_type_name( $this->trigger_interval, $this->trigger_length );
  }

  public function store_meta() {
    global $wpdb;
    $skip_name_override = false;
    $id = $this->ID;

    if(isset($_POST["post_title"]) && !empty($_POST["post_title"])) {
      $skip_name_override = true;
    }

    if (!$skip_name_override) {
      $title = sprintf(__('%d %s %s %s', 'memberpress'),
        $this->trigger_length,
        strtolower($this->get_trigger_interval_str()),
        $this->trigger_timing,
        $this->trigger_event_name());

      // Direct SQL so we don't issue any actions / filters
      // in WP itself that could get us in an infinite loop
      $sql = "UPDATE {$wpdb->posts} SET post_title=%s WHERE ID=%d";
      $sql = $wpdb->prepare($sql, $title, $id);
      $wpdb->query($sql);
    }

    update_post_meta( $id, self::$trigger_length_str,   $this->trigger_length );
    update_post_meta( $id, self::$trigger_interval_str, $this->trigger_interval );
    update_post_meta( $id, self::$trigger_timing_str,   $this->trigger_timing );
    update_post_meta( $id, self::$trigger_event_str,    $this->trigger_event );
    update_post_meta( $id, self::$filter_products_str,  $this->filter_products );
    update_post_meta( $id, self::$products_str,         $this->products );
    update_post_meta( $id, self::$emails_str,           $this->emails );
  }

  // Singularize and capitalize
  private function db_trigger_interval() {
    return strtoupper( substr( $this->trigger_interval, 0, -1 ) );
  }

  public function get_formatted_products() {
    $formatted_array = array();

    if($this->filter_products && isset($this->products) && is_array($this->products) && !empty($this->products)) {
      foreach($this->products as $product_id) {
        $product = get_post($product_id);

        if(isset($product->post_title) && !empty($product->post_title)) {
          $formatted_array[] = $product->post_title;
        }
      }
    }
    else { //If empty, then All products
      $formatted_array[] = __("All Memberships", 'memberpress');
    }

    return $formatted_array;
  }

  public function get_query_products($join_name) {
    if($this->filter_products && is_array($this->products) && !empty($this->products)) {
      $product_ids = implode(',', $this->products);
      return "AND {$join_name} IN({$product_ids})";
    }

    return '';
  }

  // Used for Subscription Expiration Reminders
  public function get_next_expiring_txn() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();
    $op = ( $this->trigger_timing=='before' ? 'DATE_SUB' : 'DATE_ADD' );

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('tr.product_id');

    $query = $wpdb->prepare(
      // Get all info about expiring transactions
      "SELECT tr.* FROM {$mepr_db->transactions} AS tr\n" .

       // Lifetimes don't expire
       "WHERE tr.expires_at <> %s\n" .

         //Make sure only real users are grabbed
         "AND tr.user_id > 0\n" .

         // Make sure that only transactions that are
         // complete or (confirmed and in a free trial) get picked up
         "AND ( tr.status = %s
                OR ( tr.status = %s
                     AND ( SELECT sub.trial
                             FROM {$mepr_db->subscriptions} AS sub
                            WHERE sub.id = tr.subscription_id AND sub.trial_amount = 0.00 ) = 1 ) )\n" .

         // Determine if expiration is accurate based on the subscription
         // If sub_id is 0 then treat as expiration
         "AND ( tr.subscription_id = 0 OR
                     ( SELECT sub.status
                         FROM {$mepr_db->subscriptions} AS sub
                        WHERE sub.id = tr.subscription_id ) IN (%s, %s) )\n" .

         // Ensure that we're in the 2 day window after the expiration / trigger
         "AND {$op}( tr.expires_at, INTERVAL {$this->trigger_length} {$unit} ) <= %s
          AND DATE_ADD(
                {$op}( tr.expires_at, INTERVAL {$this->trigger_length} {$unit} ),
                INTERVAL 2 DAY
              ) >= %s\n" .

         // Make sure that if our timing is beforehand
         // then we don't send after the expiration
         ( $this->trigger_timing=='before' ? $wpdb->prepare("AND tr.expires_at >= %s\n", MeprUtils::db_now()) : '' ) .

         // Let's make sure the reminder event hasn't already fired ...
         // This will ensure that we don't send a second reminder
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=tr.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%d
                 LIMIT 1 ) IS NULL\n" .

         // Let's make sure we're not sending expire reminders
         // when your subscription is being upgraded or downgraded
         "AND ( SELECT ev2.id
                  FROM {$mepr_db->events} AS ev2
                 WHERE ev2.evt_id=tr.id
                   AND ev2.evt_id_type='transactions'
                   AND ev2.event='subscription-changed'
                 LIMIT 1 ) IS NULL\n" .

         // Let's make sure this is the latest transaction for the subscription
         // in case there is a more recent transaction that expires later
         "AND ( tr.subscription_id = 0
                OR tr.id = ( SELECT tr2.id
                             FROM {$mepr_db->transactions} AS tr2
                             WHERE tr2.subscription_id = tr.subscription_id
                             ORDER BY tr2.expires_at DESC
                             LIMIT 1 ) )\n" .

         "{$and_products} " .

       // We're just getting one of these at a time ... we need the oldest one first
       "ORDER BY tr.expires_at
        LIMIT 1\n",

      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprSubscription::$cancelled_str,
      MeprSubscription::$suspended_str,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    $res = $wpdb->get_row($query);

    return $res;
  }

  // Used for After Subscription Renews Reminders
  public function get_renewed_txn() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('tr.product_id');

    $query = $wpdb->prepare(
    // Get all info about renewing transactions
      "SELECT tr.* FROM {$mepr_db->transactions} AS tr\n" .

      // Lifetimes don't renew
      "WHERE tr.expires_at <> %s\n" .

      //Make sure it's recurring
      "AND tr.subscription_id > 0\n" .

      //Make sure only real users are grabbed
      "AND tr.user_id > 0\n" .

      // Make sure that only transactions that are
      // complete get picked up
      "AND tr.status = %s \n" .

      // Ensure that we're defined timeframe
      // Giving it a 2 days buffer to the past
      "AND DATE_ADD( tr.created_at, INTERVAL {$this->trigger_length} {$unit} ) <= %s AND DATE_ADD(
        DATE_ADD( tr.created_at, INTERVAL {$this->trigger_length} {$unit} ), INTERVAL 2 DAY) >= %s\n" .

      "AND tr.created_at <= %s\n " .

      // Let's make sure the reminder event hasn't already fired ...
      // This will ensure that we don't send a second reminder
      "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=tr.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%d
                 LIMIT 1 ) IS NULL\n" .

      "{$and_products} " .

      // We're just getting one of these at a time ... we need the oldest one first
      "ORDER BY tr.created_at
        LIMIT 1\n",

      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );
    $res = $wpdb->get_row($query);

    return $res;
  }

  // Used for Before Subscription Renews Reminders
  public function get_next_renewing_txn() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('tr.product_id');

    $query = $wpdb->prepare(
      // Get all info about renewing transactions
      "SELECT tr.* FROM {$mepr_db->transactions} AS tr\n" .

       // Lifetimes don't renew
       "WHERE tr.expires_at <> %s\n" .

         //Make sure it's recurring
         "AND tr.subscription_id <> 0\n" .

         //Make sure only real users are grabbed
         "AND tr.user_id > 0\n" .

         // Make sure that only transactions that are
         // complete or (confirmed and in a free trial) get picked up
         "AND ( tr.status = %s
                OR ( tr.status = %s
                     AND ( SELECT sub.trial
                             FROM {$mepr_db->subscriptions} AS sub
                            WHERE sub.id = tr.subscription_id AND sub.trial_amount = 0.00 ) = 1 ) )\n" .

         // Determine if renewal is accurate based on the subscription
         "AND ( SELECT sub.status
                  FROM {$mepr_db->subscriptions} AS sub
                  WHERE sub.id = tr.subscription_id ) = %s\n" .

         // Ensure that we're in the 2 day window after the renewal / trigger
         "AND DATE_SUB( tr.expires_at, INTERVAL {$this->trigger_length} {$unit} ) <= %s
          AND DATE_ADD(
                DATE_SUB( tr.expires_at, INTERVAL {$this->trigger_length} {$unit} ),
                INTERVAL 2 DAY
              ) >= %s\n" .

         // Make sure that if our timing is beforehand
         // then we don't send after the renewal
         "AND tr.expires_at >= %s\n" .

         // Let's make sure the reminder event hasn't already fired ...
         // This will ensure that we don't send a second reminder
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=tr.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%d
                 LIMIT 1 ) IS NULL\n" .

         // Let's make sure we're not sending renewal reminders
         // when your subscription is being upgraded or downgraded
         "AND ( SELECT ev2.id
                  FROM {$mepr_db->events} AS ev2
                 WHERE ev2.evt_id=tr.id
                   AND ev2.evt_id_type='transactions'
                   AND ev2.event='subscription-changed'
                 LIMIT 1 ) IS NULL\n" .

         // Let's make sure this is the latest transaction for the subscription
         // in case there is a more recent transaction that expires later
         "AND tr.id = ( SELECT tr2.id
                        FROM {$mepr_db->transactions} AS tr2
                        WHERE tr2.subscription_id = tr.subscription_id
                        ORDER BY tr2.expires_at DESC
                        LIMIT 1 )\n" .

         "{$and_products} " .

       // We're just getting one of these at a time ... we need the oldest one first
       "ORDER BY tr.expires_at
        LIMIT 1\n",

      MeprUtils::db_lifetime(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprSubscription::$active_str,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    $res = $wpdb->get_row($query);

    return $res;
  }

  public function get_next_member_signup() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();

    // Sorry, don't want to incur any temporal paradoxes here
    if( $this->trigger_timing==='before' ) { return false; }

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('txn.product_id');

    // Find transactions where
    // status = complete or confirmed
    // no other complete & unexpired txns for this user
    $query = $wpdb->prepare(
      // Just select the actual transaction id
      "SELECT txn.id FROM {$mepr_db->transactions} AS txn " .

       // Make sure that only transactions that are
       // complete or (confirmed and in a free trial) get picked up
       "WHERE ( txn.status = %s
                OR ( txn.status = %s
                     AND ( SELECT sub.trial
                             FROM {$mepr_db->subscriptions} AS sub
                            WHERE sub.id = txn.subscription_id AND sub.trial_amount = 0.00 ) = 1 ) ) " .

         // We don't send on fallback txn
         " AND txn.txn_type <> %s " .
         // Ensure we grab transactions that are after the trigger period
         "AND DATE_ADD(
                txn.created_at,
                INTERVAL {$this->trigger_length} {$unit}
              ) <= %s " .

         // Give it a 2 day buffer period so we don't send for really old transactions
         "AND DATE_ADD(
                DATE_ADD(
                  txn.created_at,
                  INTERVAL {$this->trigger_length} {$unit}
                ),
                INTERVAL 2 DAY
              ) >= %s " .

         // Make sure this is the *first* complete transaction
         "AND ( SELECT txn2.id
                  FROM {$mepr_db->transactions} AS txn2
                 WHERE txn2.user_id = txn.user_id
                   AND ( txn2.status = %s
                    OR ( txn2.status = %s
                         AND ( SELECT sub.trial
                                 FROM {$mepr_db->subscriptions} AS sub
                                WHERE sub.id = txn2.subscription_id AND sub.trial_amount = 0.00 ) = 1 ) )
                   ".$this->get_query_products('txn2.product_id')."
                   AND txn2.created_at < txn.created_at
                 LIMIT 1
              ) IS NULL " .

         // Don't send this twice yo ... for this user
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=txn.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%s
                 LIMIT 1
              ) IS NULL " .

         "{$and_products} " .

       // Select the oldest transaction
       "ORDER BY txn.created_at ASC LIMIT 1",

      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      MeprTransaction::$fallback_str,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );
    $res = $wpdb->get_var($query);
    return $res;
  }

  public function get_next_abandoned_signup() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();

    // Sorry, don't want to incur any temporal paradoxes here
    if( $this->trigger_timing==='before' ) { return false; }

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('txn.product_id');

    // Find transactions where
    // status = pending
    // no other complete & unexpired membership for this user
    $query = $wpdb->prepare(

      // Just grab the transaction id
      "SELECT txn.id FROM {$mepr_db->transactions} AS txn " .

       // Ensure that we only select 'pending' transactions
       "WHERE txn.status=%s " .

         // Make sure the alotted time has passed
         // before allowing to be selected
         "AND DATE_ADD(
                txn.created_at,
                INTERVAL {$this->trigger_length} {$unit}
              ) <= %s " .

         // Add in the 2 day buffer period
         "AND DATE_ADD(
                DATE_ADD(
                  txn.created_at,
                  INTERVAL {$this->trigger_length} {$unit}
                ),
                INTERVAL 2 DAY
              ) >= %s " .

         // Ensure that there's no completed or confirmed transaction that
         // was created after the pending one ... if they came back and
         // completed their transaction then it's not abandoned ... hahaha
         "AND ( SELECT txn2.id
                  FROM {$mepr_db->transactions} AS txn2
                 WHERE txn2.user_id = txn.user_id
                   AND txn2.product_id = txn.product_id
                   AND txn2.status IN (%s,%s)
                   ".$this->get_query_products('txn2.product_id')."
                   AND txn2.created_at > txn.created_at
                 LIMIT 1
              ) IS NULL " .

         // Don't want to send this reminder twice so make sure there's no
         // reminder that has already been sent for this bro
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=txn.id
                   AND ev.evt_id_type='transactions'
                   AND ev.event=%s
                   AND ev.args=%s
                 LIMIT 1
              ) IS NULL " .

         "{$and_products} " .

       // Get the *oldest* applicable transaction
       "ORDER BY txn.created_at ASC LIMIT 1",

      MeprTransaction::$pending_str,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      MeprTransaction::$complete_str,
      MeprTransaction::$confirmed_str,
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    //echo "User Abandoned Query:\n\n";
    //echo $query . "\n\n";

    $res = $wpdb->get_var($query);
    return $res;
  }

  public function get_next_expired_cc() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();
    $op = ( $this->trigger_timing=='before' ? 'DATE_SUB' : 'DATE_ADD' );

    // We want to get expiring subscriptions
    $not = ( ( $this->trigger_event == 'sub-expires' ) ? ' ' : ' NOT ' );

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('sub.product_id');

    // Expiring Transactions
    $query =
      // Just grab the sub.id for any subscription with an expiring transaction
      "SELECT sub.id FROM {$mepr_db->subscriptions} AS sub " .

       // Make sure we only send out reminders for folks with ACTIVE subscriptions
       "WHERE sub.status = %s " .

         // Subtract or add if the reminder is before or after
         // The concat is just to piece together the date
         // The add_date is because we actually want the first
         // day of the month *after* the expiration month
         // LPAD is just there to ensure the month is a
         // 2 digit zero padded number
         "AND {$op}(
                DATE_ADD(
                  CONCAT(
                    sub.cc_exp_year, '-',
                    LPAD(sub.cc_exp_month, 2, '0'),
                    '-01 00:00:00'
                  ),
                  INTERVAL 1 MONTH
                ),
                INTERVAL {$this->trigger_length} {$unit}
              ) <= %s " .

         // Basically the same thing as we're doing here but let's give it
         // an entire month period to send these reminders ... seeing as
         // there will probably be fewer of these emails and they're pretty
         // dang critical so that people's subscriptions don't lapse.
         "AND DATE_ADD(
                {$op}(
                  DATE_ADD(
                    CONCAT(
                      sub.cc_exp_year, '-',
                      LPAD(sub.cc_exp_month, 2, '0'),
                      '-01 00:00:00'
                    ),
                    INTERVAL 1 MONTH
                  ),
                  INTERVAL {$this->trigger_length} {$unit}
                ),
                INTERVAL 1 MONTH
              ) >= %s " .

         // check that we haven't already sent a reminder for this
         // subscription *and* specific expiration date
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=sub.id
                   AND ev.evt_id_type='subscriptions'
                   AND ev.event=%s
                   AND ev.args=CONCAT(%d, '|', sub.cc_exp_month, '|', sub.cc_exp_year)
                 LIMIT 1
              ) IS NULL " .

         "{$and_products} " .

         // Just ignore subs with no cc_exp date
         "AND sub.cc_exp_month IS NOT NULL
          AND sub.cc_exp_month <> \"\"
          AND sub.cc_exp_year IS NOT NULL
          AND sub.cc_exp_year <> \"\"
         ";

       // Get the *oldest* valid cc expiration first
       "ORDER BY CAST(sub.cc_exp_year AS UNSIGNED) ASC,
                 CAST(sub.cc_exp_month AS UNSIGNED) ASC
        LIMIT 1";

    $query = $wpdb->prepare(
      $query,
      MeprSubscription::$active_str,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->ID
    );

    //echo "{$query}\n";

    $res = $wpdb->get_var($query);

    return $res;
  }

  public function get_next_trial_ends_subs() {
    global $wpdb;

    if( $this->trigger_length < 0 ){
      return false; // bail out.
    }

    $mepr_db = new MeprDb();

    $unit = $this->db_trigger_interval();
    $op = ( $this->trigger_timing =='before' ? 'DATE_SUB' : 'DATE_ADD' );

    //Make sure we're only grabbing from valid product ID's for this reminder yo
    //If $this->products is empty, then we should send for all product_id's
    $and_products = $this->get_query_products('sub.product_id');

    // Make sure we only send out reminders for folks with the following status:
    $subs_statuses = array( MeprSubscription::$active_str, MeprSubscription::$cancelled_str, MeprSubscription::$suspended_str );
    $in_sub_status = implode( "','", $subs_statuses );

    // Expiring Trial Subscriptions
    $query =
      // Just grab the trial_days sub.id for any subscription with an expiring transaction
      "SELECT sub.id FROM {$mepr_db->subscriptions} AS sub " .

       // Calculate trial end date with trial days for comparison
       "WHERE sub.status IN ('{$in_sub_status}') " .

        // Ensure that we're in the 2 day window.
        "AND DATE_ADD(
                {$op}(  DATE_ADD( sub.created_at, INTERVAL trial_days DAY), INTERVAL {$this->trigger_length} {$unit} ),
                INTERVAL 2 DAY
        ) >= %s" .
        "AND {$op}( DATE_ADD( sub.created_at, INTERVAL trial_days DAY), INTERVAL {$this->trigger_length} {$unit} ) <= %s " .

        // Trials not expired yet
        "AND DATE_ADD( sub.created_at, INTERVAL trial_days DAY) >= %s " .

         // check that we haven't already sent a reminder for this
         // subscription *and* specific expiration date
         "AND ( SELECT ev.id
                  FROM {$mepr_db->events} AS ev
                 WHERE ev.evt_id=sub.id
                   AND ev.evt_id_type='subscriptions'
                   AND ev.event=%s
                   AND ev.args=CONCAT(%d, '|', sub.trial_days)
                 LIMIT 1
              ) IS NULL " .

         "{$and_products} " .

         // Just trials subs
         "AND sub.trial = 1 AND sub.trial_days > 0 AND sub.prorated_trial = 0 " .

       // Get the *oldest* valid trial subs first
       "ORDER BY sub.created_at ASC
        LIMIT 1";

    $query = $wpdb->prepare(
      $query,
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      MeprUtils::db_now(),
      "{$this->trigger_timing}-{$this->trigger_event}-reminder",
      $this->rec->ID
    );

    $res = $wpdb->get_var($query);

    return $res;
  }

  // Used for drips and expirations
  //public function get_next_drip( $rule, $timing, $length, $interval, $event='sub-expires' ) {
  //  global $wpdb;
  //
  //  $mepr_db = new MeprDb();
  //
  //  if($reminder->trigger_interval=='days')
  //    $unit = 'DAY';
  //  else if($reminder->trigger_interval=='weeks')
  //    $unit = 'WEEK';
  //  else if($reminder->trigger_interval=='months')
  //    $unit = 'MONTH';
  //  else if($reminder->trigger_interval=='years')
  //    $unit = 'YEAR';
  //
  //  $op = ($reminder->trigger_timing='before')?'DATE_SUB':'DATE_ADD';
  //
  //  // We want to get expiring subscriptions
  //  $not = $expiring ? ' ' : ' NOT ';

  //  $query = "SELECT (SELECT user) as user, " .
  //                  "(SELECT products) as products, " .
  //                  "(Calculate date) as date " .
  //             "FROM {$wpdb->posts} AS p " .
  //            "WHERE p.post_status='publish' " .
  //              "AND p.post_type=%s " .
  //              "AND p.ID=%d";

  //  // Rule:

  //  // drip_enabled
  //  // drip_sequential
  //  // drip_amount
  //  // drip_unit
  //  // drip_after
  //  // drip_after_fixed

  //  // User registers
  //  // Fixed date
  //  // Transaction for specific membership

  //  // Expiring Transactions

  //  $res = $wpdb->get_row($query);
  //
  //  return $res;
  //}
} //End class
