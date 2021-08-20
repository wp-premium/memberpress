<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprCouponsCtrl extends MeprCptCtrl {
  public function load_hooks() {
    add_filter('bulk_actions-edit-memberpresscoupon', 'MeprCouponsCtrl::disable_bulk');
    add_filter('post_row_actions', 'MeprCouponsCtrl::disable_row', 10, 2);
    add_action('admin_enqueue_scripts', 'MeprCouponsCtrl::admin_enqueue_scripts');
    add_action('manage_posts_custom_column', 'MeprCouponsCtrl::custom_columns', 10, 2);
    add_filter('manage_edit-memberpresscoupon_columns', 'MeprCouponsCtrl::columns');
    add_action('admin_init', 'MeprCoupon::expire_old_coupons_and_cleanup_db');
    add_action('save_post', 'MeprCouponsCtrl::save_postdata');
    add_action('wp_insert_post_data', 'MeprCouponsCtrl::sanitize_coupon_title', 99, 2);
    add_filter('default_title', 'MeprCouponsCtrl::get_page_title_code');
    add_action('mepr-txn-store', 'MeprCouponsCtrl::update_coupon_usage_count');
    add_action('mepr-subscr-store', 'MeprCouponsCtrl::update_coupon_usage_count');

    // Cleanup list view
    add_filter('views_edit-'.MeprCoupon::$cpt, 'MeprAppCtrl::cleanup_list_view' );

    //Ajax coupon validation
    add_action('wp_ajax_mepr_validate_coupon', 'MeprCouponsCtrl::validate_coupon_ajax');
    add_action('wp_ajax_nopriv_mepr_validate_coupon', 'MeprCouponsCtrl::validate_coupon_ajax');
  }

  public function register_post_type() {
    register_post_type( MeprCoupon::$cpt, array(
        'labels' => array(
          'name' => __('Coupons', 'memberpress'),
          'singular_name' => __('Coupon', 'memberpress'),
          'add_new_item' => __('Add New Coupon', 'memberpress'),
          'edit_item' => __('Edit Coupon', 'memberpress'),
          'new_item' => __('New Coupon', 'memberpress'),
          'view_item' => __('View Coupon', 'memberpress'),
          'search_items' => __('Search Coupons', 'memberpress'),
          'not_found' => __('No Coupons found', 'memberpress'),
          'not_found_in_trash' => __('No Coupons found in Trash', 'memberpress'),
          'parent_item_colon' => __('Parent Coupon:', 'memberpress')
        ),
        'public' => false,
        'show_ui' => true, //MeprUpdateCtrl::is_activated(),
        'show_in_menu' => 'memberpress',
        'capability_type' => 'page',
        'hierarchical' => false,
        'register_meta_box_cb' => 'MeprCouponsCtrl::add_meta_boxes',
        'rewrite' => false,
        'supports' => array('title')
      )
    );
  }

  public static function columns($columns) {
    $columns = array(
      "cb" => '<input type="checkbox" />',
      "title" => __('Code', 'memberpress'),
      "coupon-description" => __('Description', 'memberpress'),
      "date" => __('Created', 'memberpress'),
      "coupon-discount" => __('Discount', 'memberpress'),
      "coupon-dm" => __('Mode', 'memberpress'),
      "coupon-starts" => __('Starts', 'memberpress'),
      "coupon-expires" => __('Expires', 'memberpress'),
      "coupon-count" => __('Usage Count', 'memberpress'),
      "coupon-products" => __('Applies To', 'memberpress')
    );

    return $columns;
  }

  public static function custom_columns($column, $coupon_id) {
    $mepr_options = MeprOptions::fetch();
    $coupon = new MeprCoupon($coupon_id);

    if($coupon->ID !== null) {
      switch($column) {
        case 'coupon-description':
          echo strip_tags($coupon->post_content);
          break;
        case 'coupon-discount':
          if($coupon->discount_mode=='first-payment') {
            echo $coupon->first_payment_discount_amount; //Update this to show proper currency symbol later
            echo ($coupon->first_payment_discount_type == 'percent')?__('%', 'memberpress'):$mepr_options->currency_code;
            echo ' → ';
          }

          echo $coupon->discount_amount; //Update this to show proper currency symbol later
          echo ($coupon->discount_type == 'percent')?__('%', 'memberpress'):$mepr_options->currency_code;
          break;
        case 'coupon-starts':
          if($coupon->post_status != 'trash') {
            if($coupon->should_start) {
              echo MeprUtils::get_date_from_ts($coupon->starts_on);
            }
            else {
              _e('Immediately', 'memberpress');
            }
          }
          else {
            _e('Expired', 'memberpress'); //They've moved this to trash so show it as expired
          }
          break;
        case 'coupon-expires':
          if($coupon->post_status != 'trash') {
            if($coupon->should_expire) {
              echo MeprUtils::get_date_from_ts($coupon->expires_on);
            }
            else {
              _e('Never', 'memberpress');
            }
          }
          else {
            _e('Expired', 'memberpress'); //They've moved this to trash so show it as expired
          }
          break;
        case 'coupon-count':
          echo '<a href="'.admin_url('admin.php?page=memberpress-trans&coupon_id=' . $coupon->ID).'">';
          if($coupon->usage_amount) {
            echo (int)$coupon->usage_count.' / '.$coupon->usage_amount;
          }
          else {
            echo (int)$coupon->usage_count.' / '.__('Unlimited', 'memberpress');
          }
          echo '</a>';
          break;
        case 'coupon-dm':
          if($coupon->discount_mode=='trial-override') {
            printf(__('Trial: %1$s days for %2$s','memberpress'), $coupon->trial_days, MeprAppHelper::format_currency($coupon->trial_amount));
          }
          else if($coupon->discount_mode=='first-payment') {
            _e('First Payment','memberpress');
          }
          else {
            _e('None','memberpress');
          }
          break;
        case 'coupon-products':
          echo implode(', ', $coupon->get_formatted_products());
      }
    }
  }

  public static function add_meta_boxes() {
    global $post_id;
    $c = new MeprCoupon($post_id);

    add_meta_box("memberpress-coupon-meta", __("Coupon Options", 'memberpress'), "MeprCouponsCtrl::coupon_meta_box", MeprCoupon::$cpt, "normal", "high");
    add_meta_box("memberpress-coupon-description", __("Description", 'memberpress'), "MeprCouponsCtrl::coupon_description_box", MeprCoupon::$cpt, "normal", "high");

    MeprHooks::do_action('mepr-coupon-meta-boxes', $c);
  }

  public static function coupon_meta_box() {
    global $post_id;
    $mepr_options = MeprOptions::fetch();
    $c = new MeprCoupon($post_id);

    MeprView::render('/admin/coupons/form', get_defined_vars());
  }

  public static function coupon_description_box() {
    global $post_id;
    $c = new MeprCoupon($post_id);

    ?>
    <textarea name="content" id="excerpt"><?php echo $c->post_content; ?></textarea>
    <?php
  }

  public static function save_postdata($post_id) {
    $post = get_post($post_id);

    if(!wp_verify_nonce((isset($_POST[MeprCoupon::$nonce_str]))?$_POST[MeprCoupon::$nonce_str]:'', MeprCoupon::$nonce_str.wp_salt())) {
      return $post_id; //Nonce prevents meta data from being wiped on move to trash
    }

    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; }

    if(defined('DOING_AJAX')) { return; }

    if(!empty($post) && $post->post_type == MeprCoupon::$cpt) {
      $coupon = new MeprCoupon($post_id);

      if(isset($_POST[MeprCoupon::$should_start_str])) {
        $coupon->should_start = true;
        $month = isset($_POST[MeprCoupon::$starts_on_month_str])?$_POST[MeprCoupon::$starts_on_month_str]:1;
        $day = isset($_POST[MeprCoupon::$starts_on_day_str])?$_POST[MeprCoupon::$starts_on_day_str]:1;
        $year = isset($_POST[MeprCoupon::$starts_on_year_str])?$_POST[MeprCoupon::$starts_on_year_str]:1970;
        $coupon->starts_on = MeprUtils::make_ts_date($month, $day, $year);
      }
      else {
        $coupon->should_start = false;
        $coupon->starts_on = 0;
      }

      if(isset($_POST[MeprCoupon::$should_expire_str])) {
        $coupon->should_expire = true;
        $month = isset($_POST[MeprCoupon::$expires_on_month_str])?$_POST[MeprCoupon::$expires_on_month_str]:1;
        $day = isset($_POST[MeprCoupon::$expires_on_day_str])?$_POST[MeprCoupon::$expires_on_day_str]:1;
        $year = isset($_POST[MeprCoupon::$expires_on_year_str])?$_POST[MeprCoupon::$expires_on_year_str]:1970;
        $coupon->expires_on = MeprUtils::make_ts_date($month, $day, $year); //23:59:59 of the chosen day
      }
      else {
        $coupon->should_expire = false;
        $coupon->expires_on = 0;
      }

      if( isset($_POST[MeprCoupon::$usage_amount_str]) and is_numeric($_POST[MeprCoupon::$usage_amount_str]) ) {
        $coupon->usage_amount = sanitize_text_field($_POST[MeprCoupon::$usage_amount_str]);
      }
      else {
        $coupon->usage_amount = 0;
      }

      $coupon->discount_type = isset($_POST[MeprCoupon::$discount_type_str])?sanitize_text_field($_POST[MeprCoupon::$discount_type_str]):'percent';
      $coupon->discount_amount = isset($_POST[MeprCoupon::$discount_amount_str])?(float)sanitize_text_field($_POST[MeprCoupon::$discount_amount_str]):0;

      if($coupon->discount_type == 'percent' && $coupon->discount_amount > 100) {
        $coupon->discount_amount = 100; //Make sure percent is never > 100
      }

      $coupon->first_payment_discount_type = isset($_POST[MeprCoupon::$first_payment_discount_type_str])?sanitize_text_field($_POST[MeprCoupon::$first_payment_discount_type_str]):'percent';
      $coupon->first_payment_discount_amount = isset($_POST[MeprCoupon::$first_payment_discount_amount_str])?(float)sanitize_text_field($_POST[MeprCoupon::$first_payment_discount_amount_str]):0;

      if($coupon->first_payment_discount_type == 'percent' && $coupon->first_payment_discount_amount > 100) {
        $coupon->first_payment_discount_amount = 100; //Make sure percent is never > 100
      }

      $coupon->use_on_upgrades = isset( $_POST[MeprCoupon::$use_on_upgrades_str] );

      $coupon->valid_products = isset($_POST[MeprCoupon::$valid_products_str])?$_POST[MeprCoupon::$valid_products_str]:array();
      $coupon->discount_mode = sanitize_text_field($_POST[MeprCoupon::$discount_mode_str]);
      $coupon->trial_days = isset($_POST[MeprCoupon::$trial_days_str])?(int)sanitize_text_field($_POST[MeprCoupon::$trial_days_str]):0;
      $coupon->trial_amount = isset($_POST[MeprCoupon::$trial_amount_str]) ? MeprUtils::format_float( $_POST[MeprCoupon::$trial_amount_str] ) : 0.00;
      $coupon->store_meta();

      MeprHooks::do_action('mepr-coupon-save-meta', $coupon);
    }
  }

  public static function sanitize_coupon_title($data, $postarr) {
    global $wpdb;

    if($data['post_type'] == MeprCoupon::$cpt) {
      //Get rid of invalid chars
      $data['post_title'] = preg_replace(array('/ +/', '/[^A-Za-z0-9_-]/'), array('-', ''), $data['post_title']);

      //Begin duplicate titles handling
      $q1 = "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND ID <> %d LIMIT 1";
      $q2 = $wpdb->prepare($q1, $data['post_title'], MeprCoupon::$cpt, $postarr['ID']);
      $count = 0;

      if(is_admin()) {
        while( ($id = $wpdb->get_var($q2)) ) {
          ++$count; //Want to increment before running the query, so when we exit the loop $data['post_title'] . "-{$count}" is stil valid
          $q2 = $wpdb->prepare($q1, $data['post_title'] . "-{$count}", MeprCoupon::$cpt, $postarr['ID']);
        }
      }

      if($count > 0) {
        $data['post_title'] .= "-{$count}";
      }
      //End duplicate titles handling
    }

    return $data;
  }

  public static function disable_row($actions, $post) {
    global $current_screen;

    if(!isset($current_screen->post_type) || $current_screen->post_type != MeprCoupon::$cpt) { return $actions; }

    unset($actions['inline hide-if-no-js']); //Hides quick-edit
    unset($actions['delete']); //Hides permanantely delete

    return $actions;
  }

  public static function disable_bulk($actions) {
    unset($actions['delete']); //disables permanent delete bulk action
    unset($actions['edit']); //disables bulk edit

    return $actions;
  }

  public static function admin_enqueue_scripts($hook) {
    global $current_screen;

    $l10n = array('mepr_no_products_message' => __('Please select at least one Membership before saving.', 'memberpress'));

    if($current_screen->post_type == MeprCoupon::$cpt) {
      wp_register_style( 'mepr-settings-table-css', MEPR_CSS_URL.'/settings_table.css', array(), MEPR_VERSION );
      wp_enqueue_style('mepr-coupons-css', MEPR_CSS_URL.'/admin-coupons.css', array('mepr-settings-table-css'), MEPR_VERSION);

      wp_register_script('mepr-settings-table-js', MEPR_JS_URL.'/settings_table.js', array('jquery'), MEPR_VERSION);
      wp_dequeue_script('autosave'); //Disable auto-saving
      wp_enqueue_script('mepr-coupons-js', MEPR_JS_URL.'/admin_coupons.js', array('jquery','mepr-settings-table-js'), MEPR_VERSION);
      wp_localize_script('mepr-coupons-js', 'MeprCoupon', $l10n);

      do_action('mepr-coupon-admin-enqueue-script', $hook);
    }
  }

  public static function get_page_title_code($title) {
    global $current_screen;

    if(empty($title) && isset($current_screen->post_type) && $current_screen->post_type == MeprCoupon::$cpt) {
      return MeprUtils::random_string(10,false,true);
    }
    else {
      return $title;
    }
  }

  public static function validate_coupon_ajax($code = null, $product_id = null) {
    check_ajax_referer('mepr_coupons', 'coupon_nonce');

    if(empty($code) || empty($product_id)) {
      if(!isset($_POST['code']) || empty($_POST['code']) || !isset($_POST['prd_id']) || empty($_POST['prd_id'])) {
        echo 'false';
        die();
      }
      else {
        $code = wp_unslash($_POST['code']);
        $product_id = wp_unslash($_POST['prd_id']);
      }
    }

    $output = MeprCoupon::is_valid_coupon_code($code, $product_id);

    if($output) {
      echo 'true';
    }
    else {
      echo 'false';
    }

    die();
  }

  public static function update_coupon_usage_count($object) {
    if(!isset($object->coupon_id) || empty($object->coupon_id)) { return; }

    $coupon = new MeprCoupon($object->coupon_id);

    if($coupon->ID) {
      $coupon->update_usage_count();
    }
  }
} //End class
