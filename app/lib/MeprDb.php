<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprDb {
  private $tables;

  public static function fetch($force = false) {
    static $mepr_db;

    if(!isset($mepr_db) || $force) {
      $mepr_db = new MeprDb();
    }

    return MeprHooks::apply_filters('mepr_fetch_db', $mepr_db);
  }

  public function __construct() {
    global $wpdb;

    // MemberPress tables
    $this->tables = MeprHooks::apply_filters(
      'mepr_db_tables',
      array(
        'events',
        'jobs',
        'members',
        'subscriptions',
        'subscription_meta',
        'tax_rates',
        'tax_rate_locations',
        'transactions',
        'transaction_meta',
        'rule_access_conditions',
      )
    );
  }

  public function do_upgrade() {
    $old_db_version = get_option('mepr_db_version', 0);
    return (version_compare(MEPR_VERSION, $old_db_version, '>'));
  }

  // Upgrades that require migrations should use the db upgrade ui
  // To ensure admins are aware of what's going on
  public function show_upgrade_ui() {
    if($this->do_upgrade()) {
      $from_version = get_option('mepr_db_version', 0);
      return MeprDbMigrations::show_upgrade_ui($from_version, MEPR_VERSION);
    }
    return false;
  }

  public function upgrade_multisite() {
    global $wpdb;

    $bids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    // Must not name instance vars blog_id ... reserved by multisite apparently
    foreach($bids AS $bid) {
      switch_to_blog($bid);

      // Ensure MemberPress is active on the current blog?
      if(is_plugin_active(MEPR_PLUGIN_SLUG)) {
        $this->upgrade();
      }

      restore_current_blog();
    }
  }

  /** Will automatically run once when the plugin is upgraded */
  public function upgrade() {
    global $wpdb;

    $this->before_do_upgrade();

    //This line makes it safe to check this code during admin_init action.
    if($this->do_upgrade()) {

      // Ensure our big queries can run in an upgrade
      //$wpdb->hide_errors(); //Errors will still be logged correctly
      $wpdb->query('SET SQL_BIG_SELECTS=1'); //This may be getting set back to 0 when SET MAX_JOIN_SIZE is executed
      $wpdb->query('SET MAX_JOIN_SIZE=18446744073709551615');
      //$wpdb->query('SET GLOBAL innodb_large_prefix=1'); //Will fail on some installs without proper privileges still

      $old_db_version = get_option('mepr_db_version', 0);
      $this->before_upgrade($old_db_version);

      // This was introduced in WordPress 3.5
      // $char_col = $wpdb->get_charset_collate(); //This doesn't work for most non english setups
      $char_col = "";
      $collation = $wpdb->get_row("SHOW FULL COLUMNS FROM {$wpdb->posts} WHERE field = 'post_content'");

      if(isset($collation->Collation)) {
        $charset = explode('_', $collation->Collation);

        if(is_array($charset) && count($charset) > 1) {
          $charset = $charset[0]; //Get the charset from the collation
          $char_col = "DEFAULT CHARACTER SET {$charset} COLLATE {$collation->Collation}";
        }
      }

      //Fine we'll try it your way this time
      if(empty($char_col)) { $char_col = $wpdb->get_charset_collate(); }

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

      /* Create/Upgrade Board Posts Table */
      $txns =
        "CREATE TABLE {$this->transactions} (
          id bigint(20) NOT NULL auto_increment,
          amount decimal(16,2) NOT NULL,
          total decimal(16,2) DEFAULT 0,
          tax_amount decimal(16,2) DEFAULT 0,
          tax_rate decimal(6,3) DEFAULT 0,
          tax_desc varchar(255) DEFAULT '',
          tax_compound int(1) DEFAULT 0,
          tax_shipping int(1) DEFAULT 1,
          tax_class varchar(255) DEFAULT 'standard',
          user_id bigint(20) NOT NULL,
          product_id bigint(20) NOT NULL,
          coupon_id bigint(20) DEFAULT NULL,
          trans_num varchar(255) DEFAULT NULL,
          status varchar(255) DEFAULT '".MeprTransaction::$pending_str."',
          txn_type varchar(255) DEFAULT '".MeprTransaction::$payment_str."',
          response text DEFAULT NULL,
          gateway varchar(255) DEFAULT '".MeprTransaction::$manual_gateway_str."',
          subscription_id bigint(20) DEFAULT NULL,
          prorated tinyint(1) DEFAULT 0,
          created_at datetime NOT NULL,
          expires_at datetime DEFAULT '".MeprUtils::db_lifetime()."',
          corporate_account_id bigint(20) DEFAULT 0,
          parent_transaction_id bigint(20) DEFAULT 0,
          PRIMARY KEY  (id),
          KEY amount (amount),
          KEY total (total),
          KEY tax_amount (tax_amount),
          KEY tax_rate (tax_rate),
          KEY tax_desc (tax_desc(191)),
          KEY tax_compound (tax_compound),
          KEY tax_shipping (tax_shipping),
          KEY tax_class (tax_class(191)),
          KEY user_id (user_id),
          KEY status (status(191)),
          KEY txn_type (txn_type(191)),
          KEY product_id (product_id),
          KEY coupon_id (coupon_id),
          KEY trans_num (trans_num(191)),
          KEY subscription_id (subscription_id),
          KEY gateway (gateway(191)),
          KEY prorated (prorated),
          KEY created_at (created_at),
          KEY expires_at (expires_at),
          KEY corporate_account_id (corporate_account_id),
          KEY parent_transaction_id (parent_transaction_id)
        ) {$char_col};";

      dbDelta($txns);

      /* Create/Upgrade Transaction Meta Table */
      $txnmeta =
        "CREATE TABLE {$this->transaction_meta} (
          id bigint(20) NOT NULL auto_increment,
          transaction_id bigint(20) DEFAULT 0,
          meta_key varchar(191) DEFAULT NULL,
          meta_value longtext,
          PRIMARY KEY  (id),
          KEY transaction_id (transaction_id),
          KEY meta_key (meta_key)
        ) {$char_col};";

      dbDelta($txnmeta);

      $events =
        "CREATE TABLE {$this->events} (
          id bigint(20) NOT NULL auto_increment,
          event varchar(255) NOT NULL DEFAULT 'login',
          args varchar(255) DEFAULT NULL,
          evt_id bigint(20) NOT NULL,
          evt_id_type varchar(255) NOT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY event_args (args(191)),
          KEY event_event (event(191)),
          KEY event_evt_id (evt_id),
          KEY event_evt_id_type (evt_id_type(191)),
          KEY event_created_at (created_at)
        ) {$char_col};";

      dbDelta($events);

      $jobs =
        "CREATE TABLE {$this->jobs} (
          id bigint(20) NOT NULL auto_increment,
          runtime datetime NOT NULL,
          firstrun datetime NOT NULL,
          lastrun datetime DEFAULT NULL,
          priority bigint(20) DEFAULT 10,
          tries bigint(20) DEFAULT 0,
          class varchar(255) NOT NULL,
          batch varchar(255) DEFAULT NULL,
          args text DEFAULT '',
          reason text DEFAULT '',
          status varchar(255) DEFAULT 'pending',
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY job_runtime (runtime),
          KEY job_firstrun (firstrun),
          KEY job_lastrun (lastrun),
          KEY job_status (status(191)),
          KEY job_priority (priority),
          KEY job_tries (tries),
          KEY job_class (class(191)),
          KEY job_batch (batch(191)),
          KEY job_created_at (created_at)
        ) {$char_col};";

      dbDelta($jobs);

      $tax_rates =
        "CREATE TABLE {$this->tax_rates} (
          id bigint(20) NOT NULL auto_increment,
          tax_country varchar(255) NOT NULL DEFAULT '',
          tax_state varchar(255) NOT NULL DEFAULT '',
          tax_rate varchar(255) NOT NULL DEFAULT '',
          tax_desc varchar(255) NOT NULL DEFAULT '',
          tax_priority bigint(20) NOT NULL DEFAULT 0,
          tax_compound int(1) NOT NULL DEFAULT 0,
          tax_shipping int(1) NOT NULL DEFAULT 1,
          tax_order bigint(20) NOT NULL,
          tax_class varchar(255) NOT NULL DEFAULT 'standard',
          PRIMARY KEY  (id),
          KEY mp_tax_country (tax_country(191)),
          KEY mp_tax_state (tax_state(191)),
          KEY mp_tax_rate (tax_rate(191)),
          KEY mp_tax_desc (tax_desc(191)),
          KEY mp_tax_order (tax_order),
          KEY mp_tax_class (tax_class(191)),
          KEY mp_tax_priority (tax_priority),
          KEY mp_tax_compound (tax_compound),
          KEY mp_tax_shipping (tax_shipping)
        ) {$char_col};";

      dbDelta($tax_rates);

      // This is purely so we can lump multiple cities and zips into the same rate
      $tax_rate_locations =
        "CREATE TABLE {$this->tax_rate_locations} (
          id bigint(20) NOT NULL auto_increment,
          tax_rate_id bigint(20) NOT NULL,
          location_code varchar(200) NOT NULL,
          location_type varchar(40) NOT NULL,
          PRIMARY KEY  (id),
          KEY mp_tax_rate_id (tax_rate_id),
          KEY mp_location_type (location_type),
          KEY mp_location_code (location_code(191)),
          KEY mp_location_type_code (location_type,location_code(191))
        ) {$char_col};";

      dbDelta($tax_rate_locations);

      $subscriptions =
        "CREATE TABLE {$this->subscriptions} (
           id bigint(20) NOT NULL auto_increment,
           user_id bigint(20) NOT NULL,
           product_id bigint(20) NOT NULL,
           coupon_id bigint(20) DEFAULT NULL,
           subscr_id varchar(255) DEFAULT '',
           price decimal(16,2) NOT NULL,
           total decimal(16,2) DEFAULT 0,
           tax_amount decimal(16,2) DEFAULT 0,
           tax_rate decimal(6,3) DEFAULT 0,
           tax_desc varchar(255) DEFAULT '',
           tax_compound int(1) DEFAULT 0,
           tax_shipping int(1) DEFAULT 1,
           tax_class varchar(255) DEFAULT 'standard',
           gateway varchar(255) DEFAULT 'manual',
           response longtext DEFAULT '',
           period int(11) DEFAULT 1,
           period_type varchar(20) DEFAULT 'months',
           limit_cycles tinyint(1) DEFAULT 0,
           limit_cycles_num int(11) DEFAULT 1,
           limit_cycles_action varchar(255) DEFAULT 'lifetime',
           limit_cycles_expires_after int(11) DEFAULT 1,
           limit_cycles_expires_type varchar(255) DEFAULT 'days',
           prorated_trial tinyint(1) DEFAULT 0,
           trial tinyint(1) DEFAULT 0,
           trial_days int(11) DEFAULT 1,
           trial_amount decimal(16,2) DEFAULT 0.00,
           trial_tax_amount decimal(16,2) DEFAULT 0,
           trial_total decimal(16,2) DEFAULT 0,
           status varchar(20) DEFAULT '".MeprSubscription::$pending_str."',
           created_at datetime NOT NULL,
           cc_last4 varchar(10) DEFAULT '4242',
           cc_exp_month varchar(10) DEFAULT '01',
           cc_exp_year varchar(10) DEFAULT '1999',
           token varchar(64) DEFAULT NULL,
           PRIMARY KEY  (id),
           KEY mp_user_id (user_id),
           KEY mp_product_id (product_id),
           KEY mp_coupon_id (coupon_id),
           KEY mp_subscr_id (subscr_id(191)),
           KEY mp_gateway (gateway(191)),
           KEY mp_period (period),
           KEY mp_period_type (period_type),
           KEY mp_limit_cycles (limit_cycles),
           KEY mp_limit_cycles_num (limit_cycles_num),
           KEY mp_limit_cycles_action (limit_cycles_action(191)),
           KEY mp_prorated_trial (prorated_trial),
           KEY mp_trial (trial),
           KEY mp_trial_days (trial_days),
           KEY mp_status (status),
           KEY mp_created_at (created_at),
           KEY mp_cc_last4 (cc_last4),
           KEY mp_cc_exp_month (cc_exp_month),
           KEY mp_cc_exp_year (cc_exp_year),
           KEY mp_token (token)
        ) {$char_col};";

      dbDelta($subscriptions);

      /* Create/Upgrade Subscription Meta Table */
      $submeta =
        "CREATE TABLE {$this->subscription_meta} (
          id bigint(20) NOT NULL auto_increment,
          subscription_id bigint(20) DEFAULT 0,
          meta_key varchar(191) DEFAULT NULL,
          meta_value longtext,
          PRIMARY KEY  (id),
          KEY subscription_id (subscription_id),
          KEY meta_key (meta_key)
        ) {$char_col};";

      dbDelta($submeta);

      $members = "
        CREATE TABLE {$this->members} (
          id bigint(20) NOT NULL auto_increment,
          user_id bigint(20) NOT NULL,
          first_txn_id bigint(20),
          latest_txn_id bigint(20),
          txn_count bigint(20),
          expired_txn_count bigint(20),
          active_txn_count bigint(20),
          sub_count bigint(20),
          pending_sub_count bigint(20),
          active_sub_count bigint(20),
          suspended_sub_count bigint(20),
          cancelled_sub_count bigint(20),
          memberships longtext,
          inactive_memberships longtext,
          last_login_id bigint(20),
          login_count bigint(20),
          total_spent decimal(16,2) DEFAULT 0,
          created_at datetime NOT NULL,
          updated_at datetime NOT NULL,
          trial_txn_count bigint(20),
          PRIMARY KEY  (id),
          UNIQUE KEY mp_user_id (user_id),
          KEY mp_first_txn_id (latest_txn_id),
          KEY mp_latest_txn_id (latest_txn_id),
          KEY mp_txn_count (txn_count),
          KEY mp_expired_txn_count (expired_txn_count),
          KEY mp_active_txn_count (active_txn_count),
          KEY mp_sub_count (sub_count),
          KEY mp_pending_sub_count (pending_sub_count),
          KEY mp_active_sub_count (active_sub_count),
          KEY mp_suspended_sub_count (suspended_sub_count),
          KEY mp_cancelled_sub_count (cancelled_sub_count),
          KEY mp_last_login_id (last_login_id),
          KEY mp_login_count (login_count),
          KEY mp_total_spent (total_spent),
          KEY mp_created_at (created_at),
          KEY mp_updated_at (updated_at),
          KEY mp_trial_txn_count (trial_txn_count)
        ) {$char_col};
      ";

      dbDelta($members);

      $rule_access =
        "CREATE TABLE {$this->rule_access_conditions} (
          id bigint(20) NOT NULL auto_increment,
          rule_id bigint(20) NOT NULL,
          access_type varchar(20) NOT NULL,
          access_operator varchar(20) NOT NULL,
          access_condition varchar(60) NOT NULL,
          PRIMARY KEY  (id),
          KEY mp_access_rule_id (rule_id),
          KEY mp_access_type (access_type),
          KEY mp_access_condition (access_condition)
        ) {$char_col};";

      dbDelta($rule_access);

      try {
        $this->after_upgrade($old_db_version);

        // Update the version in the DB now that we've run the upgrade
        update_option('mepr_db_version', MEPR_VERSION);
      }
      catch(Exception $e) {
        // Do nothing
      }

      // Ensure that the rewrite rules are flushed & in place
      MeprUtils::flush_rewrite_rules();

      // Clear the column info transient in case schemas changed
      delete_transient('_mepr_table_columns_info');
    }
  }

  public function before_upgrade($from_version) {
    global $wpdb;

    // TODO: We should delete this at some point in the future when we're
    // confident that no members are still using version 1.0.6 of MemberPress
    MeprOptions::migrate_to_new_unauth_system();
  }

  public function after_upgrade($from_version) {
    global $wpdb;

    MeprDbMigrations::run($from_version, MEPR_VERSION);

    return true;
  }

  public function column_exists($table, $column) {
    global $wpdb;

    $query = "SELECT * " .
               "FROM information_schema.COLUMNS " .
              "WHERE TABLE_SCHEMA = %s " .
                "AND TABLE_NAME = %s " .
                "AND COLUMN_NAME = %s";

    $query = $wpdb->prepare( $query, DB_NAME, $table, $column );
    $res = $wpdb->get_results( $query );

    return !empty($res);
  }

  public function add_column($table, $column, $type, $index=false) {
    global $wpdb;

    $query = "ALTER TABLE {$table} ADD COLUMN {$column} {$type}";
    if($index) {
      $query .= ", ADD INDEX mp_{$column} ({$column})";
    }

    return $wpdb->query($query);
  }

  public function remove_column($table, $column, $type='COLUMN') {
    global $wpdb;

    $query = "ALTER TABLE {$table} DROP {$type} {$column}";

    return $wpdb->query( $query );
  }

  public function remove_columns($table, $columns) {
    global $wpdb;

    $drop_array = array_map(
      function($column) {
        return "DROP COLUMN {$column}";
      },
      $columns
    );

    $drop_string = join(', ', $drop_array);

    $query = "ALTER TABLE {$table} {$drop_string}";

    return $wpdb->query( $query );
  }

  public function create_record($table, $args, $record_created_at = true) {
    global $wpdb;

    $cols = array();
    $vars = array();
    $values = array();

    $i = 0;

    foreach($args as $key => $value) {
      if ($key == 'id' && empty($value)) {
        // To prevent issue with SQL MODE NO_AUTO_VALUE_ON_ZERO
        // https://github.com/caseproof/memberpress/commit/55d2f9d69a6adc73ced127d226fd6cba6cca0b9c
        continue;
      }

      $cols[$i] = "`$key`";
      if(is_numeric($value) and preg_match('!\.!',$value)) {
        $vars[$i] = '%f';
      }
      else if(is_int($value) or is_bool($value)) {
        $vars[$i] = '%d';
      }
      else {
        $vars[$i] = '%s';
      }

      if(is_bool($value)) {
        $values[$i] = $value ? 1 : 0;
      }
      else if(is_array($value) || is_object($value)) {
        $values[$i] = serialize($value);
      }
      else {
        $values[$i] = $value;
      }

      $i++;
    }

    if($record_created_at) {
      $cols[$i] = 'created_at';
      $vars[$i] = "'".date('c')."'";
    }

    if(empty($cols))
      return false;

    $cols_str = implode(',', $cols);
    $vars_str = implode(',', $vars);

    $query = "INSERT INTO {$table} ({$cols_str}) VALUES ({$vars_str})";
    $query = $wpdb->prepare($query, $values);

    // This will fail to insert the row if NO_AUTO_VALUE_ON_ZERO is set in the database SQL_MODE
    // See notes on this commit: https://github.com/caseproof/memberpress/commit/55d2f9d69a6adc73ced127d226fd6cba6cca0b9c
    $query_results = $wpdb->query($query);

    if($query_results) {
      return $wpdb->insert_id;
    }
    else {
      return false;
    }
  }

  public function update_record($table, $id, $args) {
    global $wpdb;

    if(empty($args) or empty($id))
      return false;

    $set = '';
    $values = array();
    foreach($args as $key => $value) {
      if(empty($set)) {
        $set .= ' SET';
      }
      else {
        $set .= ',';
      }

      $set .= " `{$key}`=";

      if(is_numeric($value) and preg_match('!\.!',$value)) {
        $set .= "%f";
      }
      else if(is_int($value) or is_bool($value)) {
        $set .= "%d";
      }
      else {
        $set .= "%s";
      }

      if(is_bool($value)) {
        $values[] = $value ? 1 : 0;
      }
      else if(is_array($value) || is_object($value)) {
        $values[] = serialize($value);
      }
      else {
        $values[] = $value;
      }
    }

    $values[] = $id;
    $query = "UPDATE {$table}{$set} WHERE id=%d";
    $query = $wpdb->prepare($query, $values);
    $wpdb->query($query);

    return $id;
  }

  public function delete_records($table, $args) {
    global $wpdb;
    extract(MeprDb::get_where_clause_and_values($table,$args));

    $query = "DELETE FROM {$table}{$where}";
    if(!empty($values)) {
      $query = $wpdb->prepare($query, $values);
    }

    return $wpdb->query($query);
  }

  public function record_exists($table, $args=array()) {
    $count = $this->get_count($table, $args);
    return !empty($count);
  }

  public function get_count($table, $args=array()) {
    global $wpdb;
    extract(MeprDb::get_where_clause_and_values($table,$args));

    $query = "SELECT COUNT(*) FROM {$table}{$where}";
    if(!empty($values)) {
      $query = $wpdb->prepare($query, $values);
    }

    return $wpdb->get_var($query);
  }

  public static function get_where_clause_and_values($table,$args) {
    $mepr_db = self::fetch();

    $where = '';
    $values = array();
    foreach($args as $key => $value) {
      $where .= (empty($where) ? ' WHERE' : ' AND');
      $where .= " `{$key}`=";
      $where .= $mepr_db->get_column_prepare_type($table, $key, $value);

      if(is_bool($value)) {
        $values[] = $value ? 1 : 0;
      }
      else {
        $values[] = $value;
      }
    }

    return compact('where', 'values');
  }

  public function get_one_record($table, $args = array(), $return_type=OBJECT) {
    global $wpdb;

    extract(MeprDb::get_where_clause_and_values($table,$args));
    $query = "SELECT * FROM {$table}{$where} LIMIT 1";

    if(!empty($values)) {
      $query = $wpdb->prepare($query, $values);
    }

    return $wpdb->get_row($query, $return_type);
  }

  // Same as get_records but will return an array of MeprBaseModel objects
  public function get_model($model_name, $args=array()) {
    if(!$this->is_model_class($model_name)) { return false; }

    $table = $this->table_name_from_class_name($model_name);
    $results = $this->get_one_record($table, $args, ARRAY_A);

    if(empty($results)) { return false; }

    $r = new ReflectionClass($model_name);
    $model = $r->newInstance();
    $model->load_from_array($results);

    return $model;
  }

  public function get_records($table, $args=array(), $order_by='', $limit='', $return_type=OBJECT) {
    global $wpdb;

    extract(MeprDb::get_where_clause_and_values($table,$args));

    if(!empty($order_by)) { $order_by = " ORDER BY {$order_by}"; }
    if(!empty($limit)) { $limit = " LIMIT {$limit}"; }

    $query = "SELECT * FROM {$table}{$where}{$order_by}{$limit}";
    if(!empty($values)) {
      $query = $wpdb->prepare($query, $values);
    }

    return $wpdb->get_results($query,$return_type);
  }

  // Same as get_records but will return an array of MeprBaseModel objects
  public function get_models($model_name, $args=array(), $order_by='', $limit='') {
    if(!$this->is_model_class($model_name)) { return false; }

    $table = $this->table_name_from_class_name($model_name);
    $results = $this->get_records($table, $args, $order_by, $limit, ARRAY_A);

    if(empty($results)) { return false; }

    $r = new ReflectionClass($model_name);

    $models = array();
    foreach($results as $row) {
      $model = $r->newInstance();
      $model->load_from_array($row);
      $models[] = $model;
    }

    return $models;
  }

  public function get_col($table, $col, $args=array(), $order_by='', $limit='') {
    global $wpdb;

    extract(MeprDb::get_where_clause_and_values($table,$args));

    if(!empty($order_by)) { $order_by = " ORDER BY {$order_by}"; }
    if(!empty($limit)) { $limit = " LIMIT {$limit}"; }

    $query = "SELECT {$table}.{$col} FROM {$table}{$where}{$order_by}{$limit}";
    if(!empty($values)) {
      $query = $wpdb->prepare($query, $values);
    }

    return $wpdb->get_col($query);
  }

  /* Built to work with WordPress' built in WP_List_Table class */
  public static function list_table( $cols,
                                     $from,
                                     $joins=array(),
                                     $args=array(),
                                     $order_by='',
                                     $order='',
                                     $paged='',
                                     $search='',
                                     $search_field='any',
                                     $perpage=10,
                                     $countonly=false,
                                     $queryonly=false ) {
    global $wpdb;

    $joins = MeprHooks::apply_filters('mepr-list-table-joins', $joins);
    $args  = MeprHooks::apply_filters('mepr-list-table-args', $args);

    // Setup selects
    $col_str_array = array();
    foreach($cols as $col => $code) {
      $col_str_array[] = "{$code} AS `{$col}`";
    }

    $col_str = implode(", ", $col_str_array);

    // Setup Joins
    $important_joins = $normal_joins = array();

    if(!empty($joins)) {
      foreach($joins as $join) {
        if(preg_match('/\/\*\sIMPORTANT/', $join)) {
          $important_joins[] = $join;
        }
        else {
          $normal_joins[] = $join;
        }
      }
      $important_join_str = " ".implode(" ", $important_joins);
      $normal_join_str = " ".implode(" ", $normal_joins);
      $join_str = " ".implode(" ", $joins);
    }

    $args_str = implode(' AND ', $args);

    /* -- Ordering parameters -- */
    //Parameters that are going to be used to order the result
    if(!(empty($order_by) && empty($order))) {
      $order_by_str = ' ORDER BY ';
      if(preg_match('/^`[\w\.]+`$/', $order_by)) {
        $order_by_str .= $order_by;
      }
      elseif(preg_match('/^[\w\.]+$/', $order_by)) {
        $order_by_str .= '`' . $order_by . '`';
      }
      else {
        $order_by_str = '';
        $order = '';
      }
      if(preg_match('/^(ASC|DESC)$/i', $order)) {
        $order_by_str .= " {$order}";
      }
    }
    else {
      $order_by_str = '';
    }

    //Page Number
    if(empty($paged) or !is_numeric($paged) or $paged<=0)
      $paged=1;

    $limit = '';
    //adjust the query to take pagination into account
    if(!empty($paged) and !empty($perpage)) {
      $offset=($paged - 1) * $perpage;
      $limit = ' LIMIT '.(int)$offset.','.(int)$perpage;
    }

    // Searching
    $search_str = "";
    $searches = array();
    if(!empty($search)) {
      if($search_field=='any' || empty($search_field)) {
        $terms = explode(' ', $search); //BOOM, much more robust search now

        foreach($terms as $term) {
          foreach($cols as $col => $code) {
            $searches[] = $wpdb->prepare("{$code} LIKE %s", "%{$term}%");
          }
        }

        if(!empty($searches)) {
          $search_str = implode(" OR ", $searches);
        }

        // If we're doing a search we need all the joins in place or we get errors
        $important_join_str = $join_str;
      }
      else {
        $search_join = explode('.', $search_field);
        $search_join_str = $search_join[0];
        if(strpos($important_join_str, "AS {$search_join_str}") === false) {
          //we know we have find the join
          foreach($normal_joins as $join) {
            if(strpos($join, $search_join_str) !== false) {
              //include it in importants
              $important_joins[] = $join;
            }
          }
          $important_join_str = " " . implode(" ", $important_joins);
        }
        $search_str = $searches[] = $wpdb->prepare("{$search_field} LIKE %s", "%{$search}%");
      }
    }

    $conditions = "";

    // Pull Searching into where
    if(!empty($args)) {
      if(!empty($searches))
        $conditions = " WHERE $args_str AND ({$search_str})";
      else
        $conditions = " WHERE $args_str";
    }
    else {
      if(!empty($searches))
        $conditions = " WHERE {$search_str}";
    }

    $query = "SELECT {$col_str} FROM {$from}{$join_str}{$conditions}{$order_by_str}{$limit}";
    $total_query = "SELECT COUNT(*) FROM {$from}{$important_join_str}{$conditions}";

    MeprUtils::debug_log("List Table Query: {$query}");
    MeprUtils::debug_log("List Table Total Query: {$total_query}");

    if($queryonly) {
      return compact('query', 'total_query');
    }
    else {
      // Allows us to run the bazillion JOINS we use on the list tables
      $wpdb->query("SET SQL_BIG_SELECTS=1");

      $st = microtime(true);
      $results = $wpdb->get_results($query);

      $results_exetime = microtime(true) - $st;
      MeprUtils::debug_log("List Table Query Time: {$results_exetime} sec");

      $st = microtime(true);
      $count = $wpdb->get_var($total_query);

      $count_exetime = microtime(true) - $st;
      MeprUtils::debug_log("List Table Total Query Time: {$count_exetime} sec");

      return array('results' => $results, 'count' => $count, 'time' => array('results' => $results_exetime, 'count' => $count_exetime));
    }
  }

  private function table_name_from_class_name($class_name) {
    global $wpdb;

    $name = MeprUtils::snakecase($class_name);
    $name .= 's'; // eyeah at some point we may want a more sophisticated way to pluralize

    return $wpdb->prefix.$name;
  }

  private function is_model_class($model_name) {
    if(!class_exists($model_name)) { return false; }

    $r = new ReflectionClass($model_name);
    $model = $r->newInstance();

    return ($model instanceof MeprBaseModel);
  }

  public function __get($name) {
    if(in_array($name,$this->tables)) {
      global $wpdb;
      return "{$wpdb->prefix}mepr_{$name}";
    }
  }

  public function table_exists($table) {
    global $wpdb;
    $q = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
    $table_res = $wpdb->get_var($q);
    return (strtolower($table_res) == strtolower($table));
  }

  public function get_collation($table) {
    global $wpdb;

    $statuses = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS FROM ' . DB_NAME . ' WHERE Name=%s', $table));

    if(!empty($statuses)) {
      return $statuses->Collation;
    }

    return false;
  }

  public function get_charset($table) {
    global $wpdb;

    $create = $wpdb->get_row("SHOW CREATE TABLE {$table}",ARRAY_A);

    if(!empty($create) &&
       preg_match('!\bCHARSET=([\S]*)\b!', $create['Create Table'], $m) &&
       isset($m[1]) && !empty($m[1])) {
      return $m[1];
    }

    return false;
  }

  public function get_column_info($table) {
    global $wpdb;

    if( false === ( $table_columns_info = get_transient('_mepr_table_columns_info') ) ) {
      $table_columns_info = array();
    }

    if(!isset($table_columns_info[$table])) {
      $q = $wpdb->prepare("
          SELECT *
            FROM INFORMATION_SCHEMA.COLUMNS
           WHERE table_name = %s
        ",
        $table
      );

      $columns_info = $wpdb->get_results($q, ARRAY_A);

      $table_columns_info[$table] = array();
      foreach($columns_info as $c) {
        $table_columns_info[$table][$c['COLUMN_NAME']] = $c;
      }

      set_transient('_mepr_table_columns_info', $table_columns_info);
    }

    return $table_columns_info[$table];
  }

  public function get_column_prepare_type($table, $column, $value=null) {
    $info = $this->get_column_info($table);

    $patterns = (object)array(
      'integer' => '/(bit|binary|bool|boolean|tinyint|smallint|mediumint|integer|int|bigint)/',
      'float' => '/(real|double|float|dec|decimal|numeric)/',
      'string' => '/(datetime|date|timestamp|time|year|tinyblob|blob|mediumblob|longblob|varchar|char|varbinary|tinytext|text|mediumtext|longtext|enum|set)/'
    );

    $prepare_type = '%s';
    if(isset($info[$column])) {
      if(preg_match($patterns->integer, strtolower($info[$column]['DATA_TYPE']))) {
        $prepare_type = '%d';
      }
      else if(preg_match($patterns->float, strtolower($info[$column]['DATA_TYPE']))) {
        $prepare_type = '%f';
      }
    }
    else {
      if(is_numeric($value) and preg_match('!\.!',$value)) {
        $prepare_type = '%f';
      }
      else if(is_int($value) or is_bool($value)) {
        $prepare_type = '%d';
      }
    }

    return $prepare_type;
  }

  /**
   * Get a metadata field for a given meta table
   *
   * Mimics the behavior of 'get_{type}_meta'
   *
   * @param string $table      Tablename of the meta table.
   * @param string $object_col Column containing the foreign id of the associated object
   * @param int    $object_id  Foreign id of the associated object
   * @param string $meta_key   Meta key
   * @param bool   $single     Return a single value or not
   */
  public function get_metadata($table, $object_col, $object_id, $meta_key, $single=false) {
    if(empty($meta_key) || empty($object_id) || !is_numeric($object_id)) {
      return false;
    }

    $meta_value = '';

    if($single) {
      $row = $this->get_one_record(
        $table,
        array(
          $object_col => $object_id,
          'meta_key' => $meta_key
        )
      );

      if(!empty($row)) {
        $meta_value = maybe_unserialize($row->meta_value);
      }
    }
    else {
      $meta_value = $this->get_col(
        $table,
        'meta_value',
        array(
          $object_col => $object_id,
          'meta_key' => $meta_key
        )
      );
      $meta_value = array_map('maybe_unserialize', $meta_value);
    }

    return $meta_value;
  }

  /**
   * Add a metadata field for a given meta table
   *
   * Mimics the behavior of 'add_{type}_meta'
   *
   * @param string $table      Tablename of the meta table.
   * @param string $object_col Column containing the foreign id of the associated object
   * @param int    $object_id  Foreign id of the associated object
   * @param string $meta_key   Meta key
   * @param string $meta_value Meta value. Will be serialized if an object or an array.
   * @param string $unique     Value should be unique for the meta_key/object_id
   * @return int|false The meta ID on success, false on failure.
   */
  public function add_metadata($table, $object_col, $object_id, $meta_key, $meta_value, $unique=false) {
    global $wpdb;

    if(empty($meta_key) || empty($object_id) || !is_numeric($object_id)) {
      return false;
    }

    $object_id = absint($object_id);
    if( $unique && $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM $table WHERE meta_key = %s AND $object_col = %d",
          $meta_key, $object_id ) ) ) {
      return false;
    }

    // expected_slashed ($meta_key)
    $meta_key = wp_unslash($meta_key);
    $meta_value = wp_unslash($meta_value);
    $meta_value = maybe_serialize($meta_value);

    $result = $wpdb->insert( $table, array(
      $object_col => $object_id,
      'meta_key' => $meta_key,
      'meta_value' => $meta_value
    ) );

    if(empty($result)) {
      return false;
    }

    $mid = (int) $wpdb->insert_id;

    return $mid;
  }

  /**
   * Update a metadata field for a given meta table
   *
   * Mimics the behavior of 'update_{type}_meta'
   *
   * @param string $table      Tablename of the meta table.
   * @param string $object_col Column containing the foreign id of the associated object
   * @param int    $object_id  Foreign id of the associated object
   * @param string $meta_key   Meta key
   * @param string $meta_value Meta value. Will be serialized if an object or an array.
   * @param string $prev_value Prev value.
   * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
   */
  public function update_metadata($table, $object_col, $object_id, $meta_key, $meta_value, $prev_value='') {
    global $wpdb;

    if(empty($meta_key) || empty($object_id) || !is_numeric($object_id)) {
      return false;
    }

    // expected_slashed ($meta_key)
    $raw_meta_key = $meta_key;
    $meta_key = wp_unslash($meta_key);
    $passed_value = $meta_value;
    $meta_value = wp_unslash($meta_value);
    $meta_value = maybe_serialize($meta_value);

    $args = array(
      $object_col => $object_id,
      'meta_key' => $meta_key
    );

    $data = array('meta_value' => $meta_value);

    if(!empty($prev_value)) {
      $prev_value = $args['meta_value'] = maybe_serialize($prev_value);
    }

    $meta_count = $this->get_count($table, $args);
    $meta_exists = ($meta_count > 0);

    if($meta_exists) {
      $result = $wpdb->update($table, $data, $args);

      if(empty($result)) {
        return false;
      }
    }
    else {
      return $this->add_metadata($table, $object_col, $object_id, $raw_meta_key, $passed_value);
    }

    return true;
  }

  /**
   * Delete metadata for the specified object.
   *
   * @global wpdb $wpdb WordPress database abstraction object.
   *
   * @param string $table      Tablename of the metadata object we're deleting
   * @param string $object_col Column of the object foreign id
   * @param int    $object_id  ID of the object metadata is for
   * @param string $meta_key   Metadata key
   * @param mixed  $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified, only delete
   *                           metadata entries with this value. Otherwise, delete all entries with the specified meta_key.
   *                           Pass `null, `false`, or an empty string to skip this check. (For backward compatibility,
   *                           it is not possible to pass an empty string to delete those entries with an empty string
   *                           for a value.)
   * @param bool   $delete_all Optional, default is false. If true, delete matching metadata entries for all objects,
   *                           ignoring the specified object_id. Otherwise, only delete matching metadata entries for
   *                           the specified object_id.
   * @return bool True on successful delete, false on failure.
   */
  public function delete_metadata($table, $object_col, $object_id, $meta_key, $meta_value = '', $delete_all = false) {
    global $wpdb;

    if(empty($meta_key) || empty($object_id) || !is_numeric($object_id)) {
      return false;
    }

    $object_id = absint( $object_id );
    if(!$object_id && !$delete_all) {
      return false;
    }

    // expected_slashed ($meta_key)
    $meta_key = wp_unslash($meta_key);
    $meta_value = wp_unslash($meta_value);

    $meta_value = maybe_serialize( $meta_value );

    $query = $wpdb->prepare( "SELECT id FROM {$table} WHERE meta_key = %s", $meta_key );

    if(!$delete_all) {
      $query .= $wpdb->prepare(" AND {$object_col} = %d", $object_id );
    }

    if('' !== $meta_value && null !== $meta_value && false !== $meta_value) {
      $query .= $wpdb->prepare(" AND meta_value = %s", $meta_value );
    }

    $meta_ids = $wpdb->get_col( $query );
    if(!count($meta_ids)) {
      return false;
    }

    if($delete_all) {
      if('' !== $meta_value && null !== $meta_value && false !== $meta_value) {
        $object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ) );
      } else {
        $object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE meta_key = %s", $meta_key ) );
      }
    }

    $query = "DELETE FROM $table WHERE id IN( " . implode( ',', $meta_ids ) . " )";

    $count = $wpdb->query($query);
    return !empty($count);
  }

  /*
   * Conditionally schedule cron jobs for database migration
   * - If inactive_memberships column does not exist before upgrade, then schedule the cron job to populate the column in the members table
   */
  public function before_do_upgrade() {
    global $wpdb;

    if ( $this->table_exists($this->members) && !$this->column_exists( $this->members, 'inactive_memberships' ) && !wp_next_scheduled( 'mepr_migrate_members_table_015' ) ) {
      wp_schedule_event( time() + MeprUtils::minutes(10), 'mepr_migrate_members_table_015_interval', 'mepr_migrate_members_table_015' );
    }
  }
}
