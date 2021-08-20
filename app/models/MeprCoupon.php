<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprCoupon extends MeprCptModel {
  public static $use_on_upgrades_str  = '_mepr_coupons_use_on_upgrades';
  public static $should_start_str     = '_mepr_coupons_should_start';
  public static $should_expire_str    = '_mepr_coupons_should_expire';
  public static $starts_on_str        = '_mepr_coupons_starts_on';
  public static $expires_on_str       = '_mepr_coupons_expires_on';
  public static $usage_count_str      = '_mepr_coupons_usage_count';
  public static $usage_amount_str     = '_mepr_coupons_usage_amount';
  public static $discount_type_str    = '_mepr_coupons_discount_type';
  public static $discount_mode_str    = '_mepr_coupons_discount_mode';
  public static $discount_amount_str  = '_mepr_coupons_discount_amount';
  public static $valid_products_str   = '_mepr_coupons_valid_products';
  public static $trial_days_str       = '_mepr_coupons_trial_days';
  public static $trial_amount_str     = '_mepr_coupons_trial_amount';
  public static $last_run_str         = 'mepr_coupons_expire_last_run';
  public static $nonce_str            = 'mepr_coupons_nonce';
  public static $starts_on_month_str  = 'mepr_coupons_start_month';
  public static $starts_on_day_str    = 'mepr_coupons_start_day';
  public static $starts_on_year_str   = 'mepr_coupons_start_year';
  public static $expires_on_month_str = 'mepr_coupons_ex_month';
  public static $expires_on_day_str   = 'mepr_coupons_ex_day';
  public static $expires_on_year_str  = 'mepr_coupons_ex_year';
  public static $first_payment_discount_type_str    = '_mepr_coupons_first_payment_discount_type';
  public static $first_payment_discount_amount_str  = '_mepr_coupons_first_payment_discount_amount';

  public static $cpt = 'memberpresscoupon';

  public $discount_types;

  /*** Instance Methods ***/
  public function __construct($obj = null) {
    $this->discount_types = array('percent', 'dollar');
    $this->load_cpt(
      $obj,
      self::$cpt,
      array(
        'use_on_upgrades' => false,
        'should_start' => false,
        'should_expire' => false,
        'starts_on' => null,
        'expires_on' => null,
        'usage_count' => 0,
        'usage_amount' => 0,
        'discount_type' => 'percent',
        'discount_amount' => 0.00,
        'first_payment_discount_type' => 'percent',
        'first_payment_discount_amount' => 0.00,
        'valid_products' => array(),
        'discount_mode' => 'standard',
        'trial_days' => 0,
        'trial_amount' => 0.00
      )
    );
  }

  public function validate() {
    $this->validate_is_bool($this->use_on_upgrades, 'use_on_upgrades');
    $this->validate_is_bool($this->should_start, 'should_start');
    $this->validate_is_bool($this->should_expire, 'should_expire');
    if($this->should_start) { $this->validate_is_timestamp($this->starts_on, 'starts_on'); }
    if($this->should_expire) { $this->validate_is_timestamp($this->expires_on, 'expires_on'); }
    $this->validate_is_numeric($this->usage_count, 0, null, 'usage_count');
    $this->validate_is_numeric($this->usage_amount, 0, null, 'usage_amount');
    $this->validate_is_in_array($this->discount_type, $this->discount_types, 'discount_type');
    $this->validate_is_currency($this->discount_amount, 0, null, 'discount_amount');
    $this->validate_is_array($this->valid_products, 'valid_products');

    if($this->discount_mode == 'trial-override') {
      $this->validate_is_numeric($this->trial_days, 0, null, 'trial_days');
      $this->validate_is_currency($this->trial_amount, 0, null, 'trial_amount');
    }
  }

  public function get_formatted_products() {
    $formatted_array = array();

    if(!empty($this->valid_products)) {
      foreach($this->valid_products as $p) {
        $product = get_post($p);

        if($product) {
          $formatted_array[] = $product->post_title;
        }
      }
    }
    else {
      $formatted_array[] = __('None Selected', 'memberpress');
    }

    return $formatted_array;
  }

  public static function get_all_active_coupons() {
    return MeprCptModel::all('MeprCoupon');
  }

  public static function get_one_from_code($code, $ignore_status = false) {
    global $wpdb;

    //Ignore the status here?
    $and_status = "AND post_status = 'publish'";
    if($ignore_status) {
      $and_status = '';
    }

    $q = "SELECT ID
            FROM {$wpdb->posts}
            WHERE post_title = %s
              AND post_type = %s
              {$and_status}";
    $id = $wpdb->get_var($wpdb->prepare($q, $code, self::$cpt));

    if(!$id) {
      return false;
    }
    else {
      return new MeprCoupon($id);
    }
  }

  /**
   * Whether this coupon can be used for upgrades
   *
   * @return boolean
   */
  public function can_use_on_upgrades() {
    return (bool) $this->use_on_upgrades;
  }

  public function is_valid($product_id) {
    //Coupon has reached its usage limit (remember 0 = unlimited)
    if($this->usage_amount > 0 and $this->usage_count >= $this->usage_amount) {
      return false;
    }

    //Coupon has expired
    //This doesn't really need to be here but will be more accurate
    //than waiting every 12 hours for the expiring cron to run
    if($this->should_expire and $this->expires_on <= time()) {
      return false;
    }

    //Coupon hasn't started
    if($this->should_start and $this->starts_on > time()) {
      return false;
    }

    //Coupon code is not valid for this membership
    if(!in_array($product_id, $this->valid_products)) {
      return false;
    }

    // Can't be used on upgrades, so make sure we're not trying to apply to an upgrade
    if ( MeprUtils::is_user_logged_in() && ! $this->can_use_on_upgrades() ) {
      $prd = new MeprProduct( $product_id );
      if ( $prd->is_upgrade_or_downgrade_for( get_current_user_id(), 'upgrade' ) ) {
        return false;
      }
    }

    return apply_filters('mepr_coupon_is_valid', true); // If we made it here, the coupon is good
  }

  //Hmmm...maybe this method should be moved to the Coupon Ctrl instead
  public static function is_valid_coupon_code($code, $product_id) {
    $c = self::get_one_from_code($code);

    //Coupon does not exist or has expired
    if($c === false) {
      return false;
    }

    return $c->is_valid($product_id);
  }

  /**
   * Gets the coupon's amount
   *
   * @return int
   */
  public function get_first_payment_discount_amount( $prd ) {
    return MeprHooks::apply_filters( 'mepr_coupon_get_first_payment_discount_amount', $this->first_payment_discount_amount, $this, $prd );
  }

  /**
   * Gets the coupon's amount
   *
   * @return int
   */
  public function get_discount_amount( $prd ) {
    return MeprHooks::apply_filters( 'mepr_coupon_get_discount_amount', $this->discount_amount, $this, $prd );
  }

  public function apply_discount($price, $is_first_payment=false, $prd = null) {
    if($is_first_payment && $this->discount_mode=='first-payment') {
      $discount_amount = $this->get_first_payment_discount_amount( $prd );
      $discount_type = $this->first_payment_discount_type;
    }
    else {
      $discount_amount = $this->get_discount_amount( $prd );
      $discount_type = $this->discount_type;
    }

    $value = $price;

    if($discount_type == 'percent') {
      $value = ((1 - ($discount_amount / 100)) * $price);
    }
    else {
      $value = ($price - $discount_amount);
    }

    return MeprUtils::format_float(max($value,0)); // must only be precise to 2 points
  }

  /** Applies a trial override where appropriate. $obj must be a MeprProduct or MeprSubscription. */
  public function maybe_apply_trial_override(&$obj) {
    if($this->discount_mode=='trial-override') {
      $obj->trial = true;
      $obj->trial_days = $this->trial_days;
      $obj->trial_amount = $this->trial_amount;
    }
    else if($this->discount_mode=='first-payment') {
      $obj->trial = true;
      $obj->trial_days = (($obj instanceof MeprProduct) ? $obj->days_in_my_period() : $obj->days_in_this_period());

      if ($obj instanceof MeprSubscription) {
        $obj->trial_amount = $this->apply_discount( $obj->product()->price, true, $obj );
      } else {
        $obj->trial_amount = $this->apply_discount( $obj->price, true, $obj );
      }
    }

    // Basically, if the subscription does have a trial period
    // because of a coupon then the trial payment should count as one of the limited cycle payments.
    if( ($this->discount_mode=='trial-override' || $this->discount_mode=='first-payment') &&
        $obj instanceof MeprSubscription &&
        $obj->trial_amount > 0 &&
        $obj->limit_cycles >= 1
    ) {
      $product = $obj->product();
      if(false == $product->trial){
        $obj->limit_cycles_num = $obj->limit_cycles_num - 1;
      }
    }
  }

  public static function expire_old_coupons_and_cleanup_db() {
    global $wpdb;
    $date = time();
    $last_run = get_option(self::$last_run_str, 0); //Prevents all this code from executing on every page load

    if(($date - $last_run) > 43200) { //Runs twice a day just to be sure
      update_option(self::$last_run_str, $date);
      $coupons = self::get_all_active_coupons();

      if(!empty($coupons)) {
        foreach($coupons as $coupon) {
          if($coupon->should_expire && $date > $coupon->expires_on) {
            $coupon->mark_as_expired();
          }
        }
      }

      //While we're in here we should consider deleting auto-draft coupons, waste of db space
      $sq1 = "SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = '".self::$cpt."' AND
                      post_status = 'auto-draft'";
      $sq1_res = $wpdb->get_col($sq1);
      if(!empty($sq1_res)) {
        $post_ids = implode(',', $sq1_res);
        $q1  = "DELETE
                  FROM {$wpdb->postmeta}
                  WHERE post_id IN ({$post_ids})";
        $q2  = "DELETE
                  FROM {$wpdb->posts}
                  WHERE post_type = '".self::$cpt."' AND
                        post_status = 'auto-draft'";
        $wpdb->query($q1);
        $wpdb->query($q2);
      }
    }
  }

  public function mark_as_expired() {
    $post = array('ID' => $this->ID, 'post_status' => 'trash');

    wp_update_post($post);
  }

  public function update_usage_count() {
    global $wpdb;
    $mepr_db = new MeprDb();
    $tcount = 0;

    $sq = "
      SELECT COUNT(DISTINCT subscription_id)
        FROM {$mepr_db->transactions}
       WHERE coupon_id = %d
         AND subscription_id > 0
         AND txn_type IN (%s,%s);
    ";

    $sq = $wpdb->prepare($sq, $this->ID, MeprTransaction::$payment_str, MeprTransaction::$subscription_confirmation_str);

    if($sqcount = $wpdb->get_var($sq)) { $tcount += $sqcount; }

    //Query one-time payments next
    $lq = "
      SELECT COUNT(*)
        FROM {$mepr_db->transactions}
       WHERE coupon_id = %d
         AND (subscription_id <= 0 OR subscription_id IS NULL)
         AND txn_type = %s
    ";

    $lq = $wpdb->prepare($lq, $this->ID, MeprTransaction::$payment_str);

    if($lqcount = $wpdb->get_var($lq)) { $tcount += $lqcount; }

    //Update and store
    $this->usage_count = $tcount;
    $this->store();
  }

  public function store_meta() {
    update_post_meta($this->ID, self::$use_on_upgrades_str, $this->use_on_upgrades);
    update_post_meta($this->ID, self::$should_start_str, $this->should_start);
    update_post_meta($this->ID, self::$should_expire_str, $this->should_expire);
    update_post_meta($this->ID, self::$starts_on_str, $this->starts_on);
    update_post_meta($this->ID, self::$expires_on_str, $this->expires_on);
    update_post_meta($this->ID, self::$usage_count_str, $this->usage_count);
    update_post_meta($this->ID, self::$usage_amount_str, $this->usage_amount);
    update_post_meta($this->ID, self::$discount_type_str, $this->discount_type);
    update_post_meta($this->ID, self::$discount_amount_str, $this->discount_amount);
    update_post_meta($this->ID, self::$valid_products_str, $this->valid_products);
    update_post_meta($this->ID, self::$discount_mode_str, $this->discount_mode);
    update_post_meta($this->ID, self::$trial_days_str, $this->trial_days);
    update_post_meta($this->ID, self::$trial_amount_str, $this->trial_amount);
    update_post_meta($this->ID, self::$first_payment_discount_type_str, $this->first_payment_discount_type);
    update_post_meta($this->ID, self::$first_payment_discount_amount_str, $this->first_payment_discount_amount);
  }

  /**
   * Get the Stripe Coupon ID
   *
   * @param  string       $gateway_id      The gateway ID
   * @param  string       $discount_amount The coupon discount amount
   * @param  bool       $onetime
   * @return string|false
   */
  public function get_stripe_coupon_id($gateway_id, $discount_amount, $onetime) {
    $meta_key = sprintf('_mepr_stripe_coupon_id_%s_%s', $gateway_id, $this->terms_hash($discount_amount));

    if ($onetime) {
      $meta_key = sprintf('_mepr_stripe_onetime_coupon_id_%s_%s', $gateway_id, $this->terms_hash($discount_amount));
    }

    return get_post_meta($this->ID, $meta_key, true);
  }

  /**
   * Set the Stripe Coupon ID
   *
   * @param string $gateway_id      The gateway ID
   * @param string $discount_amount The coupon discount amount
   * @param string $coupon_id       The Stripe Coupon ID
   * @param bool $onetime
   */
  public function set_stripe_coupon_id($gateway_id, $discount_amount, $coupon_id, $onetime) {
    $meta_key = sprintf('_mepr_stripe_coupon_id_%s_%s', $gateway_id, $this->terms_hash($discount_amount));

    if ($onetime) {
      $meta_key = sprintf('_mepr_stripe_onetime_coupon_id_%s_%s', $gateway_id, $this->terms_hash($discount_amount));
    }

    update_post_meta($this->ID, $meta_key, $coupon_id);
  }

  /**
   * Get the hash of the coupon discount terms
   *
   * If this hash changes then a different Stripe Coupon will be created.
   *
   * @param  string $discount_amount The coupon discount amount
   * @return string
   */
  private function terms_hash($discount_amount) {
    $terms = [
      'type' => $this->discount_type,
      'amount' => $discount_amount
    ];

    if($this->discount_type != 'percent') {
      $mepr_options = MeprOptions::fetch();
      $terms['currency'] = $mepr_options->currency_code;
    }

    return md5(serialize($terms));
  }

  /**
   * Delete the Stripe Coupon ID
   *
   * @param string $gateway_id The gateway ID
   * @param string $coupon_id  The Stripe Coupon ID
   */
  public static function delete_stripe_coupon_id($gateway_id, $coupon_id)
  {
    if(!is_string($coupon_id) || $coupon_id === '') {
      return;
    }

    global $wpdb;

    $query = $wpdb->prepare(
      "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value = %s",
      $wpdb->esc_like('_mepr_stripe_coupon_id_' . $gateway_id) . '%',
      $coupon_id
    );

    $meta_ids = $wpdb->get_col($query);

    if(is_array($meta_ids) && count($meta_ids)) {
      foreach($meta_ids as $meta_id) {
        delete_metadata_by_mid('post', $meta_id);
      }
    }
  }
} //End class
