<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprDbMigrations {
  private $migrations;
  public static function run($from_version, $to_version) {
    global $wpdb;

    $mig = new MeprDbMigrations();
    $migration_versions = $mig->get_migration_versions($from_version, $to_version);

    if(empty($migration_versions)) { return; }

    foreach($migration_versions as $migration_version) {
      $config = $mig->get_migration($migration_version);
      foreach($config['migrations'] as $callbacks) {
        // Store current migration config in the database so the
        // progress AJAX endpoint can see what's going on
        set_transient('mepr_current_migration', $callbacks, MeprUtils::hours(4));
        call_user_func(array($mig, $callbacks['migration']));
        delete_transient('mepr_current_migration');
      }
    }
  }

  public static function show_upgrade_ui($from_version, $to_version) {
    $mig = new MeprDbMigrations();
    $migration_versions = $mig->get_migration_versions($from_version, $to_version);

    if(empty($migration_versions)) { return; }

    foreach($migration_versions as $migration_version) {
      $config = $mig->get_migration($migration_version);
      if( isset($config['show_ui']) &&
          $config['show_ui'] !== false &&
          call_user_func(array($mig, $config['show_ui'])) ) {
        return true;
      }
    }

    return false;
  }

  public function __construct() {
    // ensure migration versions are sequential when adding new migration callbacks
    $this->migrations = array(
      '1.3.0' => array(
        'show_ui' => 'show_ui_001_002',
        'migrations' => array(
          array(
            'migration' => 'create_and_migrate_subscriptions_table_001',
            'check'     => 'check_create_and_migrate_subscriptions_table_001',
            'message'   => __('Updating Subscriptions', 'memberpress'),
          ),
          array(
            'migration' => 'create_and_migrate_members_table_002',
            'check'     => 'check_create_and_migrate_members_table_002',
            'message'   => __('Optimizing Member Data', 'memberpress'),
          ),
        ),
      ),
      '1.3.9' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'add_trial_txn_count_column_to_members_table_003',
            'check'     => false,
            'message'   => false,
          ),
          array(
            'migration' => 'sub_post_meta_to_table_token_004',
            'check'     => false,
            'message'   => false,
          ),
        ),
      ),
      '1.3.11' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'fix_all_the_expires_006',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.3.19' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'migrate_access_rules_007',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.3.33' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'fix_txn_counts_for_sub_accounts_008',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.3.36' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'remove_ip_addr_gdpr_009',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.3.43b5' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'refactor_coupon_trial_010',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.4.6a3' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'refactor_coupon_first_payment_011',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.4.6a5' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'usage_reset_012',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.8.0' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'existing_coupons_enable_use_on_upgrades_013',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
      '1.8.9' => array(
        'show_ui' => false,
        'migrations' => array(
          array(
            'migration' => 'leap_year_extra_day_014',
            'check'     => false,
            'message'   => false,
          )
        )
      ),
    );
  }

  public function get_migration_versions($from_version, $to_version) {
    $mig_versions = array_keys($this->migrations);

    $versions = array();
    foreach($mig_versions as $mig_version) {
      if(version_compare($from_version, $mig_version, '<')) {
         //version_compare($to_version, $mig_version, '>='))
        $versions[] = $mig_version;
      }
    }

    return $versions;
  }

  public function get_migration($version) {
    return $this->migrations[$version];
  }

/** SHOW UI **/
  public function show_ui_001_002() {
    global $wpdb;
    $mepr_db = new MeprDb();

    $q = "
      SELECT COUNT(*)
        FROM {$wpdb->posts}
       WHERE post_type='mepr-subscriptions'
    ";

    if($mepr_db->table_exists($mepr_db->subscriptions)) {
      $q .= "
        AND ID NOT IN (
          SELECT id
            FROM {$mepr_db->subscriptions}
        )
      ";
    }

    $subs_left = $wpdb->get_var($q);

    $q = "
      SELECT COUNT(*)
        FROM {$wpdb->users}
    ";

    if($mepr_db->table_exists($mepr_db->members)) {
      $q .= "
        WHERE ID NOT IN (
         SELECT user_id
           FROM {$mepr_db->members}
        )
      ";
    }

    $members_left = $wpdb->get_var($q);

    $already_migrating = get_transient('mepr_migrating');

    return (
      !empty($already_migrating) ||
      ($subs_left >= 100) ||
      ($members_left >= 100)
    );
  }

/** CHECKS **/
  public function check_create_and_migrate_subscriptions_table_001() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s", 'mepr-subscriptions');
    $total = $wpdb->get_var($q); //Need to account for 0's below

    $q = "SELECT COUNT(*) FROM {$mepr_db->subscriptions}";
    $completed = $wpdb->get_var($q);

    $progress = 100;
    if($total > 0) {
      $progress = (int)(($completed / $total) * 100);
      $progress = min($progress, 100);
    }

    return compact('completed','total','progress');
  }

  public function check_create_and_migrate_members_table_002() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $q = "SELECT COUNT(*) FROM {$wpdb->users}";
    $total = $wpdb->get_var($q); //Should never get a 0 here

    $q = "SELECT COUNT(*) FROM {$mepr_db->members}";
    $completed = $wpdb->get_var($q);

    $progress = (int)(($completed / $total) * 100);
    $progress = min($progress, 100);

    return compact('completed','total','progress');
  }

/** MIGRATIONS **/
  public function create_and_migrate_subscriptions_table_001() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    MeprSubscription::upgrade_table(null,true);

    $max_sub_id = $wpdb->get_var("SELECT max(ID) FROM {$wpdb->posts} WHERE post_type='mepr-subscriptions'");

    if(!empty($max_sub_id)) {
      $max_sub_id = (int)$max_sub_id + 1; // Just in case
      $wpdb->query("ALTER TABLE {$mepr_db->subscriptions} AUTO_INCREMENT={$max_sub_id}");
    }
  }

  public function create_and_migrate_members_table_002() {
    MeprUser::update_all_member_data(true);
  }

  public function add_trial_txn_count_column_to_members_table_003() {
    MeprUser::update_all_member_data(false, '', array('txn_count', 'active_txn_count', 'expired_txn_count', 'trial_txn_count'));
  }

  public function sub_post_meta_to_table_token_004() {
    global $wpdb;

    $mepr_db = MeprDb::fetch();

    $q = $wpdb->prepare("
        SELECT *
          FROM {$wpdb->postmeta}
         WHERE meta_key IN (%s,%s,%s,%s)
      ",
      '_mepr_authnet_order_invoice', //Use actual string here, becasue Authorize.net Class doens't exist in business edition and what if we change the class names in the future?
      '_mepr_paypal_token',
      '_mepr_paypal_pro_token',
      '_mepr_stripe_plan_id'
    );

    $tokens = $wpdb->get_results($q);

    foreach($tokens as $token) {
      $q = $wpdb->prepare("
          UPDATE {$mepr_db->subscriptions}
             SET token=%s
           WHERE id=%d
        ",
        $token->meta_value,
        $token->post_id
      );

      $wpdb->query($q);
    }
  }

  public function fix_all_the_expires_006() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    //Gimme all the transactions since 2017-07-15 with trials
    $query = $wpdb->prepare("
      SELECT t.id
      FROM {$mepr_db->transactions} t
      JOIN {$mepr_db->subscriptions} s
        ON s.id = t.subscription_id
      WHERE s.trial_days > 0
        AND t.status = %s
        AND t.created_at > '2017-07-15'
    ",
    MeprTransaction::$complete_str
    );

    $transactions = $wpdb->get_results($query);
    foreach($transactions as $transaction_id) {
      $transaction = new MeprTransaction($transaction_id->id);
      $subscription = $transaction->subscription();
      //Get the expiratoin with the bug fix
      $txn_created_at = strtotime($transaction->created_at);
      $expected_expiration = $subscription->get_expires_at($txn_created_at);
      $expires_at = MeprUtils::ts_to_mysql_date($expected_expiration);
      //Do we actually need to fix anything?
      if($expires_at != $transaction->expires_at) {
        //We're just going to do this via SQL to skip hooks
        MeprUtils::debug_log("Found transaction {$transaction->id} to update from {$transaction->expires_at} to {$expires_at}");
        $wpdb->update($mepr_db->transactions, array('expires_at' => $expires_at), array('id' => $transaction->id));
      }
    }
  }

  public function migrate_access_rules_007() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $post_rules = get_posts(
      array(
        'post_type' => 'memberpressrule',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'trash')
      )
    );

    $rules_count = sizeof($post_rules);
    MeprUtils::debug_log("Found {$rules_count} rules to migrate!");

    foreach ($post_rules as $post) {
      // No longer a mepr_access attribute on the rule
      // model so we do it the old fashioned way here
      $access_rules = get_post_meta($post->ID, '_mepr_rules_access');

      foreach ($access_rules as $ids) {
        if(!is_array($ids)) { $ids = array($ids); }
        $ids = array_unique($ids);

        foreach ($ids as $id) {
          MeprUtils::debug_log("Adding Rule Access POST:{$post->ID} => MEMBERSHIP:{$id}");
          $rule_access_condition = new MeprRuleAccessCondition();
          $rule_access_condition->rule_id = $post->ID;
          $rule_access_condition->access_type = 'membership';
          $rule_access_condition->access_operator = 'is';
          $rule_access_condition->access_condition = $id;
          $rule_access_condition->store();
        }
      }
    }

    MeprUtils::debug_log('All done migrating access rules');
  }

  public function fix_txn_counts_for_sub_accounts_008() {
    global $wpdb;
    $mepr_db = new MeprDb();
    $update_columns = array('txn_count', 'active_txn_count', 'expired_txn_count');

    $query = $wpdb->prepare(
      "SELECT DISTINCT(m.user_id) FROM {$mepr_db->members} m
         JOIN {$mepr_db->transactions} t
           ON t.user_id = m.user_id
          AND t.txn_type = %s",
      MeprTransaction::$sub_account_str
    );
    $results = $wpdb->get_col($query);
    $count = sizeOf($results);
    MeprUtils::debug_log("Found {$count} members to update");

    foreach ($results as $user_id) {
      $user = new MeprUser($user_id);
      $user->update_member_data($update_columns);
    }
  }

  public function remove_ip_addr_gdpr_009() {
    global $wpdb;
    $db = new MeprDb;

    if($db->column_exists($db->events, 'ip'))
      $db->remove_column($db->events, 'ip');
    if($db->column_exists($db->subscriptions, 'ip_addr') && $db->column_exists($db->subscriptions, 'response'))
      $db->remove_columns($db->subscriptions, array('ip_addr', 'response'));
    if($db->column_exists($db->transactions, 'ip_addr') && $db->column_exists($db->transactions, 'response'))
      $db->remove_columns($db->transactions, array('ip_addr', 'response'));

    $wpdb->delete($wpdb->prefix . 'usermeta', array('meta_key' => 'user_ip'));
  }


  public function refactor_coupon_trial_010() {
    $coupons = MeprCoupon::get_all_active_coupons();
    MeprUtils::debug_log('Migrating Coupon Trials');

    if(empty($coupons)) { return; }

    foreach($coupons as $c) {
      $trial = get_post_meta($c->ID, '_mepr_coupons_trial', true);
      if($trial !== '') { // Empty string indicates not found
        if($trial) {
          update_post_meta($c->ID, MeprCoupon::$discount_mode_str, 'trial-override');
        }

        error_log('Migrating Coupon: Deleting trial post_meta');
        delete_post_meta($c->ID, '_mepr_coupons_trial');
      }
    }
  }

  public function refactor_coupon_first_payment_011() {
    MeprUtils::debug_log('Migrating First Payment Discount details');

    $already_ran = get_option('mepr_db_migration_011_ran');

    if($already_ran) {
      MeprUtils::debug_log('Migrating First Payment Discount details already ran ... aborting migration 011');
      return;
    }

    $posts = get_posts(array('numberposts' => -1, 'post_type' => MeprCoupon::$cpt, 'post_status' => array('publish', 'trash')));

    if(empty($posts) || is_wp_error($posts)) {
      MeprUtils::debug_log('Migrating First Payment Discount failed ... aborting migration 011');
      return;
    }

    foreach($posts as $p) {
      $c = new MeprCoupon($p->ID);

      if($c->discount_mode=='first-payment') {
        MeprUtils::debug_log('Migrating Coupon (first-payment): ' . $c->post_title);
        if($c->discount_amount > 0 && empty($c->first_payment_discount_amount)) { // Prevent duplicate runs
          $c->first_payment_discount_amount = $c->discount_amount;
          $c->first_payment_discount_type = $c->discount_type;
          $c->discount_amount = 0;
          $c->discount_type = 'percent';
          $c->store();
        }
      }
    }

    update_option('mepr_db_migration_011_ran',time());
  }

  public function usage_reset_012() {
    delete_option('mepr_disable_senddata');
  }

  /**
   * Introducing an option for explicitly allowing coupons to be used on upgrades.
   * Since all existing coupons up to this point work for upgrades, we don't want to change the behavior for these coupons.
   * Therefore, we'll enable the new option for existing coupons so that they continue to work for upgrades.
   * Moving forward, users will need to enable the new option for any new coupon to work on upgrades.
   *
   * @since 1.7.3
   */
  public function existing_coupons_enable_use_on_upgrades_013() {

    MeprUtils::debug_log('Migrating Coupons to use on upgrades');

    // Check to see if this migration has already run
    if ( get_option( 'mepr_db_migration_013_ran' ) ) {
      MeprUtils::debug_log( 'Migrating Coupons to use on upgrades already ran ... aborting migration 013' );
      return;
    }

    // All the coupons
    $coupons = get_posts(
      array(
        'numberposts' => -1,
        'post_type' => MeprCoupon::$cpt,
        'post_status' => array('publish', 'trash')
      )
    );

    if ( empty( $coupons ) || is_wp_error( $coupons ) ) {
      MeprUtils::debug_log( 'Migrating Coupons to use on upgrades failed ... aborting migration 013' );
      return;
    }

    foreach ( $coupons as $coupon ) {

      $c = new MeprCoupon( $coupon->ID );

      MeprUtils::debug_log( 'Migrating Coupon to use on upgrades: ' . $c->post_title );
      $c->use_on_upgrades = true;
      $c->store();
    }

    // Flag that this migration has run
    update_option( 'mepr_db_migration_013_ran', time() );
  }

  /**
   * Due to leap year bugs, yearly subscription transactions have an incorrect expires_at date. We'll add an extra day
   * to transactions between 01 Mar 2019 and 31 Dec 2019, and remove a day from transactions between 01 Mar 2020 and
   * 31 Dec 2020.
   */
  public function leap_year_extra_day_014() {
    global $wpdb;
    $mepr_db = new MeprDb();

    // For transactions between 01 Mar 2019 and 31 Dec 2019 that expire in 365 days, set them to expire in 366 days
    // to account for the leap day
    $wpdb->query(
      "UPDATE {$mepr_db->transactions}
      SET expires_at = DATE_ADD(expires_at, INTERVAL 1 DAY)
      WHERE DATE(expires_at) = DATE(DATE_ADD(created_at, INTERVAL 365 DAY))
      AND created_at >= '2019-03-01 00:00:00'
      AND created_at <= '2019-12-31 23:59:59'
      AND expires_at != '0000-00-00 00:00:00'
      AND txn_type = 'payment'
      AND status = 'complete'
    ");

    // For transactions between 01 Mar 2020 and 31 Dec 2020 that expire in 366 days, set them to expire in 365 days
    // since there is no leap day in the period
    $wpdb->query(
      "UPDATE {$mepr_db->transactions}
      SET expires_at = DATE_SUB(expires_at, INTERVAL 1 DAY)
      WHERE DATE(expires_at) = DATE(DATE_ADD(created_at, INTERVAL 366 DAY))
      AND created_at >= '2020-03-01 00:00:00'
      AND created_at <= '2020-12-31 23:59:59'
      AND expires_at != '0000-00-00 00:00:00'
      AND txn_type = 'payment'
      AND status = 'complete'
    ");
  }

  /*
   * This script populates both the membership and inactive_membership columns in the
   * members table now that the inactive_membership column has been added.
   *
   * The memberships column is update because a previous "fix" populated it with both
   * active and inactive memberships.
   *
   * Runs in batches on a cron job to reduce load on customer sites.
   */
  public static function populate_inactive_memberships_col_015() {
    //scheduled in
    global $wpdb;
    $mepr_db = new MeprDb();

    //Large member base may take days to update. So setting thet start date
    //And only updating ones that haven't been updated since then
    //Store as transient so it can be accesses/won't change between cron job executions
    $started = get_transient('mepr_members_migrate_start');
    if(!isset($started) || !$started) {
      $started = MeprUtils::ts_to_mysql_date(time());
      set_transient('mepr_members_migrate_start', $started);
    }

    //Get the next 100 user ids that have not been updated since the migration started
    //Note: If the member data was already updated by some other process since the migration started
    //that is okay, it will have the correct data and will be skipped here
    $batch_query = "SELECT user_id FROM " . $mepr_db->members . " WHERE updated_at < %s LIMIT 25";
    $batch_query = $wpdb->prepare($batch_query, $started);

    $batch_ids = $wpdb->get_col($batch_query);

    if (empty($batch_ids)) {
      //Nothing left to update so remove transient and cancel cron job
      delete_transient('mepr_members_migrate_start');

      $timestamp = wp_next_scheduled( 'mepr_migrate_members_table_015' );
      wp_unschedule_event( $timestamp, 'mepr_migrate_members_table_015' );
      wp_clear_scheduled_hook('mepr_migrate_members_table_015');
    } else {
      //Loop through all the ids
      foreach($batch_ids as $uid) {
        $u = new MeprUser();

        // We just set the ID here to avoid looking up the ID and
        // it's the only thing we care about in updat_member_data
        $u->ID = $uid;
        $u->update_member_data(array('memberships', 'inactive_memberships'));
      }
    }
  }
}
