<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAppCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('manage_posts_custom_column', 'MeprAppCtrl::custom_columns', 100, 2);
    add_action('manage_pages_custom_column', 'MeprAppCtrl::custom_columns', 100, 2);
    add_action('registered_post_type', 'MeprAppCtrl::setup_columns', 10, 2);
    add_filter('the_content', 'MeprAppCtrl::page_route', 100);
    add_action('wp_enqueue_scripts', 'MeprAppCtrl::load_scripts', 1);
    add_action('admin_enqueue_scripts', 'MeprAppCtrl::load_admin_scripts', 1);
    add_action('init', 'MeprAppCtrl::parse_standalone_request', 10);
    add_action('wp_dashboard_setup', 'MeprAppCtrl::add_dashboard_widgets');
    add_action('widgets_init', 'MeprAppCtrl::add_sidebar_widgets');
    add_action('custom_menu_order', 'MeprAppCtrl::admin_menu_order');
    add_action('menu_order', 'MeprAppCtrl::admin_menu_order');
    add_action('menu_order', 'MeprAppCtrl::admin_submenu_order');
    add_action('widgets_init', 'MeprLoginWidget::register_widget');
    add_action('widgets_init', 'MeprSubscriptionsWidget::register_widget');
    add_action('add_meta_boxes', 'MeprAppCtrl::add_meta_boxes', 10, 2);
    add_action('save_post', 'MeprAppCtrl::save_meta_boxes');
    add_action('admin_notices', 'MeprAppCtrl::protected_notice');
    add_action('admin_notices', 'MeprAppCtrl::php_min_version_check');
    add_action('admin_notices', 'MeprAppCtrl::maybe_show_get_started_notice');
    add_action('wp_ajax_mepr_dismiss_notice', 'MeprAppCtrl::dismiss_notice');
    add_action('wp_ajax_mepr_todays_date', 'MeprAppCtrl::todays_date');
    add_action('wp_ajax_mepr_close_about_notice', 'MeprAppCtrl::close_about_notice');
    add_action('admin_init', 'MeprAppCtrl::append_mp_privacy_policy');
    add_filter('embed_oembed_html', 'MeprAppCtrl::wrap_oembed_html', 99);
    add_action('in_admin_header', 'MeprAppCtrl::mp_admin_header', 0);

    // add_action('wp_ajax_mepr_load_css', 'MeprAppCtrl::load_css');
    // add_action('wp_ajax_nopriv_mepr_load_css', 'MeprAppCtrl::load_css');
    add_action('plugins_loaded', 'MeprAppCtrl::load_css');

    //Load language - must be done after plugins are loaded to work with PolyLang/WPML
    add_action('plugins_loaded', 'MeprAppCtrl::load_language');

    add_filter('months_dropdown_results', array($this, 'cleanup_list_table_month_dropdown'), 10, 2);

    // Integrate with WP Debugging plugin - https://github.com/afragen/wp-debugging/issues/6
    add_filter('wp_debugging_add_constants', 'MeprAppCtrl::integrate_wp_debugging');

    //show MemberPress as active menu item when support admin page is selected
    add_filter( 'mp_hidden_submenu', 'MeprAppCtrl::highlight_parent_menu');

    register_deactivation_hook(__FILE__, 'MeprAppCtrl::deactivate');
  }

  public static function mp_admin_header() {
    global $current_screen;

    if(MeprUtils::is_memberpress_admin_page()) {
      ?>
      <div id="mp-admin-header">
        <img class="mp-logo" src="<?php echo MEPR_IMAGES_URL . '/memberpress-logo-color.svg'; ?>" />
        <a class="mp-support-button button button-primary" href="<?php echo admin_url('admin.php?page=memberpress-support'); ?>"><?php _e('Support', 'memberpress')?></a>
      </div>
      <?php
    }
  }

  //Fix for Elementor page builder and our static the_content caching
  //Elementor runs the_content filter on each video embed, our the_content static caching
  //caused the same video to load for all instances of a video on a page as a result
  public static function wrap_oembed_html($cached_html) {
    $length = rand(1, 100); //Random length, this is the key to all of this
    $class = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
    return '<span class="' . $class . '">' . $cached_html . '</span>';
  }

  public static function add_meta_boxes($post_type, $post) {
    $mepr_options = MeprOptions::fetch();

    if(!isset($post->ID) || $post->ID == $mepr_options->login_page_id) { return; }

    $screens = array_merge( array_keys(get_post_types(array("public" => true, "_builtin" => false))),
                            array('post', 'page') );

    // This meta box shouldn't appear on the new/edit membership screen
    $pos = array_search(MeprProduct::$cpt, $screens);
    if(isset($screens[$pos])) { unset($screens[$pos]); }

    $rules = MeprRule::get_rules($post);

    foreach($screens as $screen) {
      if( MeprGroup::$cpt == $screen ) {
        add_meta_box( 'mepr_unauthorized_message',
                      __('MemberPress Unauthorized Access on the Group Pricing Page', 'memberpress'),
                      'MeprAppCtrl::unauthorized_meta_box',
                      $screen );
        if(!empty($rules)) {
          add_meta_box( 'mepr_rules',
                        __('This Group Pricing Page is Protected', 'memberpress'),
                        'MeprAppCtrl::rules_meta_box',
                        $screen, 'normal', 'high' );
        }
      }
      elseif( in_array( $screen, array( 'post', 'page' ) ) ) {
        add_meta_box( 'mepr_unauthorized_message',
                      __('MemberPress Unauthorized Access', 'memberpress'),
                      'MeprAppCtrl::unauthorized_meta_box',
                      $screen );
        if(!empty($rules)) {
          $obj = get_post_type_object( $screen );
          add_meta_box( 'mepr_rules',
                        sprintf( __('This %s is Protected', 'memberpress'),
                                 $obj->labels->singular_name ),
                        'MeprAppCtrl::rules_meta_box',
                        $screen, 'normal', 'high' );
        }
      }
      else {
        $obj = get_post_type_object( $screen );
        add_meta_box( 'mepr_unauthorized_message',
                      sprintf( __('MemberPress Unauthorized Access to this %s', 'memberpress'),
                               $obj->labels->singular_name ),
                      'MeprAppCtrl::unauthorized_meta_box',
                      $screen );
        if(!empty($rules)) {
          add_meta_box( 'mepr_rules',
                        sprintf( __('This %s is Protected', 'memberpress'),
                                 $obj->labels->singular_name ),
                        'MeprAppCtrl::rules_meta_box',
                        $screen, 'normal', 'high' );
        }
      }
    }
  }

  public static function custom_columns($column, $post_id) {
    $post = get_post($post_id);
    if( $column=="mepr-access" ) {
      $access_list = MeprRule::get_access_list($post);
      if(empty($access_list)) {
        ?><div class="mepr-active"><?php _e("Public", 'memberpress'); ?></div><?php
      }
      else {
        $display_access_list = array();
        foreach( $access_list as $access_key => $access_values ) {
          if($access_key == 'membership') {
            foreach($access_values as $product_id) {
              $product = new MeprProduct($product_id);
              if(!is_null($product->ID))  {
                $display_access_list[] = stripslashes($product->post_title);
              }
            }
          }
          else {
            $display_access_list = array_merge($display_access_list, $access_values);
          }
        }
        ?>
        <div class="mepr-inactive">
          <?php echo implode(', ', $display_access_list); ?>
        </div>
        <?php
      }
    }
  }

  public static function setup_columns($post_type, $args) {
    add_filter("manage_posts_columns", 'MeprAppCtrl::columns');
    add_filter("manage_pages_columns", 'MeprAppCtrl::columns');
    add_filter("manage_edit-{$post_type}_columns", 'MeprAppCtrl::columns');
  }

  public static function columns($columns) {
    global $post_type, $post;

    $except = array('attachment', 'memberpressproduct');
    $except = MeprHooks::apply_filters('mepr-hide-cpt-access-column', $except);

    if(isset($_GET['post_type']) || (isset($post_type) && !empty($post_type)) || (isset($post->post_type) && !empty($post->post_type))) {
      if(!empty($_GET['post_type'])) {
        $cpt = get_post_type_object($_GET['post_type']);
      }
      elseif(!empty($post_type)) {
        $cpt = get_post_type_object($post_type);
      }
      elseif(!empty($post->post_type)) { // Try individual post last
        $cpt = get_post_type_object($post->post_type);
      }
      else {
        return $columns; // Just give up trying
      }

      if(in_array($cpt->name, $except) || !$cpt->public) {
        return $columns;
      }
    }

    $ak = array_keys($columns);

    MeprUtils::array_splice_assoc($columns, $ak[2], $ak[2],
                                  array("mepr-access" => __("Access", 'memberpress')));

    return $columns;
  }

  public static function rules_meta_box() {
    global $post;

    $rules = MeprRule::get_rules($post);
    $access_list = MeprRule::get_access_list($post);
    $product_ids = (isset($access_list['membership']) && !empty($access_list['membership'])) ? $access_list['membership'] : array();
    $members = (isset($access_list['member']) && !empty($access_list['member'])) ? $access_list['member'] : array();

    MeprView::render('/admin/rules/rules_meta_box', get_defined_vars());
  }

  public static function protected_notice() {
    global $post, $pagenow;

    $public_post_types = MeprRule::public_post_types();

    if( 'post.php' != $pagenow or !isset($_REQUEST['action']) or
        $_REQUEST['action']!='edit' or !in_array($post->post_type,$public_post_types) )
    { return; }

    $rules = MeprRule::get_rules($post);
    $rule_count = count($rules);

    $message = '<strong>' .
               sprintf( _n( 'This Content is Protected by %s MemberPress Access Rule',
                            'This Content is Protected by %s MemberPress Access Rules',
                            $rule_count , 'memberpress'), $rule_count ) .
               '</strong>' .
               ' &ndash; <a href="#mepr_post_rules">' . __('Click here to view', 'memberpress') . '</a>';

    if(!empty($rules)) {
      MeprView::render('/admin/errors', get_defined_vars());
    }
  }

  public static function php_min_version_check() {
    $current_php_version = phpversion();
    if (version_compare($current_php_version, MEPR_MIN_PHP_VERSION, '<')) {
      $message = __('<strong>MemberPress: Your PHP version (%s) is out of date!</strong> ' .
                    'This version has reached official End Of Life and as such may expose your site to security vulnerabilities. ' .
                    'Please contact your web hosting provider to update to %s or newer', 'memberpress');
      ?>
     <div class="notice notice-warning is-dismissible">
         <p><?php printf($message, $current_php_version, MEPR_MIN_PHP_VERSION); ?></p>
     </div>
     <?php
    }
  }

  public static function maybe_show_get_started_notice() {
    $mepr_options = MeprOptions::fetch();

    // Only show to users who have access, and those who haven't already dismissed it
    if(!MeprUtils::is_mepr_admin() || get_user_meta(get_current_user_id(), 'mepr_dismiss_notice_get_started')) {
      return;
    }

    $has_payment_method = count($mepr_options->integrations) > 0;
    $has_product = MeprProduct::count() > 0;
    $has_rule = MeprRule::count() > 0;

    // Don't show if a payment method, membership and rule already exist
    if($has_payment_method && $has_product && $has_rule) {
      return;
    }

    MeprView::render('/admin/get_started', compact('has_payment_method', 'has_product', 'has_rule'));
  }

  public static function dismiss_notice() {
    if(check_ajax_referer('mepr_dismiss_notice', false, false) && isset($_POST['notice']) && is_string($_POST['notice'])) {
      $notice = sanitize_key($_POST['notice']);
      update_user_meta(get_current_user_id(), "mepr_dismiss_notice_{$notice}", true);
    }

    wp_send_json_success();
  }

  public static function unauthorized_meta_box()
  {
    global $post;

    $mepr_options = MeprOptions::fetch();

    $_wpnonce = wp_create_nonce('mepr_unauthorized');

    if(!($unauthorized_message_type = get_post_meta($post->ID, '_mepr_unauthorized_message_type', true)))
      $unauthorized_message_type = 'default';

    if(!($unauthorized_message = get_post_meta($post->ID, '_mepr_unauthorized_message', true)))
      $unauthorized_message = '';

    $unauth_excerpt_type = get_post_meta($post->ID, '_mepr_unauth_excerpt_type', true);

    // Backwards compatibility here people
    if($unauthorized_message_type=='excerpt') {
      $unauthorized_message_type = 'hide';
      if(empty($unauth_excerpt_type)) {
        $unauth_excerpt_type = 'show';
      }
    }

    if(empty($unauth_excerpt_type)) {
      $unauth_excerpt_type = 'default';
    }

    $unauth_excerpt_size = get_post_meta($post->ID, '_mepr_unauth_excerpt_size', true);

    if($unauth_excerpt_size === '' or !is_numeric($unauth_excerpt_size)) {
      $unauth_excerpt_size = 100;
    }

    $unauth_login = get_post_meta($post->ID, '_mepr_unauth_login', true);

    if($unauth_login=='') {
      // backwards compatibility
      $hide_login = get_post_meta($post->ID, '_mepr_hide_login_form', true);
      $unauth_login = (empty($hide_login)?'default':'show');
    }

    MeprView::render('/admin/unauthorized_meta_box', get_defined_vars());
  }

  public static function save_meta_boxes($post_id) {
    //Verify the Nonce First
    if(!isset($_REQUEST['mepr_custom_unauthorized_nonce']) || !wp_verify_nonce($_REQUEST['mepr_custom_unauthorized_nonce'], 'mepr_unauthorized')) {
      return $post_id;
    }

    if((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || defined('DOING_AJAX')) {
      return $post_id;
    }

    // First we need to check if the current user is authorized to do this action.
    if('page' == $_POST['post_type']) {
      if(!current_user_can('edit_page', $post_id)) { return; }
    }
    else {
      if(!current_user_can('edit_post', $post_id)) { return; }
    }

    //if saving in a custom table, get post_ID
    $post_ID = $_REQUEST['post_ID'];

    update_post_meta( $post_ID, '_mepr_unauthorized_message_type', $_REQUEST['_mepr_unauthorized_message_type'] );
    update_post_meta( $post_ID, '_mepr_unauthorized_message',      $_REQUEST['_mepr_unauthorized_message'] );
    update_post_meta( $post_ID, '_mepr_unauth_login',              $_REQUEST['_mepr_unauth_login'] );
    update_post_meta( $post_ID, '_mepr_unauth_excerpt_type',       $_REQUEST['_mepr_unauth_excerpt_type'] );
    update_post_meta( $post_ID, '_mepr_unauth_excerpt_size',       $_REQUEST['_mepr_unauth_excerpt_size'] );
  }

  public static function highlight_parent_menu($submenu) {
    $screen = get_current_screen();

    if($screen->id === 'memberpress-support') {
        $submenu = 'memberpress';
    }
    return $submenu;
  }

  public static function setup_menus() {
    add_action('admin_menu', 'MeprAppCtrl::menu');
  }

  public static function toplevel_menu_route() {
    ?>
    <script>
      window.location.href="<?php echo admin_url("admin.php?page=memberpress-options"); ?>";
    </script>
    <?php
  }

  public static function menu() {
    global $submenu;
    $capability = MeprUtils::get_mepr_admin_capability();

    self::admin_separator();

    $mbr_ctrl = new MeprMembersCtrl();
    $txn_ctrl = new MeprTransactionsCtrl();
    $sub_ctrl = new MeprSubscriptionsCtrl();
    $menu_title = 'MemberPress';

    if ( MeprUtils::is_black_friday_time() && empty( get_option( 'mp_2020_bf_dismissed' ) ) ) {
      $menu_title .= '<span class="memberpress-menu-pulse green"></span>';
    }

    add_menu_page('MemberPress', $menu_title, $capability, 'memberpress', 'MeprAppCtrl::toplevel_menu_route', MEPR_IMAGES_URL."/memberpress-16@2x.png", 775677);

    add_submenu_page('memberpress', __('Members', 'memberpress'), __('Members', 'memberpress'), $capability, 'memberpress-members', array($mbr_ctrl,'listing'));

    if(!get_option('mepr_disable_affiliates_menu_item')) {
      if(defined('ESAF_VERSION')) {
        add_submenu_page('memberpress', __('Affiliates', 'memberpress'), __('Affiliates', 'memberpress'), $capability, admin_url('admin.php?page=easy-affiliate'));
      }
      else {
        add_submenu_page('memberpress', __('Affiliates', 'memberpress'), __('Affiliates', 'memberpress'), $capability, 'memberpress-affiliates', 'MeprAddonsCtrl::affiliates');
      }
    }

    add_submenu_page('memberpress', __('Subscriptions', 'memberpress'), __('Subscriptions', 'memberpress'), $capability, 'memberpress-subscriptions', array( $sub_ctrl, 'listing' ));
    // Specifically for subscriptions listing
    add_submenu_page(null, __('Subscriptions', 'memberpress'), __('Subscriptions', 'memberpress'), $capability, 'memberpress-lifetimes', array( $sub_ctrl, 'listing' ));
    add_submenu_page('memberpress', __('Transactions', 'memberpress'), __('Transactions', 'memberpress'), $capability, 'memberpress-trans', array( $txn_ctrl, 'listing' ));
    add_submenu_page('memberpress', __('Reports', 'memberpress'), __('Reports', 'memberpress'), $capability, 'memberpress-reports', 'MeprReportsCtrl::main');
    add_dashboard_page(__('MemberPress', 'memberpress'), __('MemberPress', 'memberpress'), $capability, 'memberpress-reports', 'MeprReportsCtrl::main');
    add_submenu_page('memberpress', __('Settings', 'memberpress'), __('Settings', 'memberpress'), $capability, 'memberpress-options', 'MeprOptionsCtrl::route');
    add_submenu_page('memberpress', __('Account Login', 'memberpress'), __('Account Login', 'memberpress'), $capability, 'memberpress-account-login', 'MeprAccountLoginCtrl::route');
    add_submenu_page('memberpress', __('Add-ons', 'memberpress'), '<span style="color:#8CBD5A;">' . __('Add-ons', 'memberpress') . '</span>', $capability, 'memberpress-addons', 'MeprAddonsCtrl::route');

    if ( ! is_plugin_active( 'memberpress-courses/main.php' ) ) {
      $menu_title = __('Courses', 'memberpress');
      $menu_title .= sprintf( '<span style="background-color: #ed5a4c; color: #fff; font-weight: bold; display: inline-block; margin-left: 5px; padding: 2px 6px 3px; border-radius: 100px; font-size: 10px;">%s</span>', __('NEW', 'memberpress') );
      add_submenu_page('memberpress', __('MemberPress Courses', 'memberpress'), $menu_title, $capability, 'memberpress-courses', 'MeprCoursesCtrl::route');
    }

    if(!get_option('mepr_disable_smtp_menu_item')) {
      if(function_exists('wp_mail_smtp')) {
        $submenu['memberpress'][998] = array(__('SMTP', 'memberpress'), $capability, admin_url('admin.php?page=wp-mail-smtp'));
      }
      else {
        add_submenu_page('memberpress', __('SMTP', 'memberpress'), __('SMTP', 'memberpress'), $capability, 'memberpress-smtp', 'MeprAddonsCtrl::smtp');
      }
    }

    if(!get_option('mepr_disable_analytics_menu_item')) {
      if(class_exists('MonsterInsights_eCommerce')) {
        $submenu['memberpress'][999] = array(__('Analytics', 'memberpress'), $capability, admin_url('admin.php?page=monsterinsights_reports#/ecommerce'));
      }
      else {
        add_submenu_page('memberpress', __('Analytics', 'memberpress'), __('Analytics', 'memberpress'), $capability, 'memberpress-analytics', 'MeprAddonsCtrl::analytics');
      }
    }

    add_submenu_page(null, __('Support', 'memberpress'), __('Support', 'memberpress'), $capability, 'memberpress-support', 'MeprAppCtrl::render_admin_support');

    MeprHooks::do_action('mepr_menu');
  }

  /**
   * Add a separator to the WordPress admin menus
   */
  public static function admin_separator()
  {
    global $menu;

    // Prevent duplicate separators when no core menu items exist
    if(!MeprUtils::is_mepr_admin()) { return; }

    $menu[] = array('', 'read', 'separator-memberpress', '', 'wp-menu-separator memberpress');
  }

  /**
   * Move our custom separator above our admin menu
   *
   * @param array $menu_order Menu Order
   * @return array Modified menu order
   */
  public static function admin_menu_order($menu_order)
  {
    if(!$menu_order)
      return true;

    if(!is_array($menu_order))
      return $menu_order;

    // Initialize our custom order array
    $new_menu_order = array();

    // Menu values
    $second_sep   = 'separator2';
    $custom_menus = array('separator-memberpress', 'memberpress');

    // Loop through menu order and do some rearranging
    foreach($menu_order as $item)
    {
      // Position MemberPress menus above appearance
      if($second_sep == $item)
      {
        // Add our custom menus
        foreach($custom_menus as $custom_menu)
          if(array_search($custom_menu, $menu_order))
            $new_menu_order[] = $custom_menu;

        // Add the appearance separator
        $new_menu_order[] = $second_sep;

      // Skip our menu items down below
      }
      elseif(!in_array($item, $custom_menus))
        $new_menu_order[] = $item;
    }

    // Return our custom order
    return $new_menu_order;
  }

  //Organize the CPT's in our submenu
  public static function admin_submenu_order($menu_order)
  {
    global $submenu;

    static $run = false;

    //no sense in running this everytime the hook gets called
    if($run) { return $menu_order; }

    //just return if there's no memberpress menu available for the current screen
    if(!isset($submenu['memberpress'])) { return $menu_order; }

    $run = true;
    $new_order = array();
    $i = 5;

    foreach($submenu['memberpress'] as $sub)
    {
      if($sub[0] == __('Memberships', 'memberpress'))
        $new_order[0] = $sub;
      elseif($sub[0] == __('Groups', 'memberpress'))
        $new_order[1] = $sub;
      elseif($sub[0] == __('Rules', 'memberpress'))
        $new_order[2] = $sub;
      elseif($sub[0] == __('Coupons', 'memberpress'))
        $new_order[3] = $sub;
      elseif( 0 === strpos( $sub[0], __('Courses', 'memberpress') ) )
        $new_order[4] = $sub;
      else
        $new_order[$i++] = $sub;
    }

    ksort($new_order);

    $submenu['memberpress'] = $new_order;

    return $menu_order;
  }

  // Routes for wordpress pages -- we're just replacing content here folks.
  public static function page_route($content) {
    $current_post = MeprUtils::get_current_post();

    //This isn't a post? Just return the content then
    if($current_post === false) { return $content; }

    //WARNING the_content CAN be run more than once per page load
    //so this static var prevents stuff from happening twice
    //like cancelling a subscr or resuming etc...
    static $already_run = array();
    static $new_content = array();
    static $content_length = array();

    //Init this posts static values
    if(!isset($new_content[$current_post->ID]) || empty($new_content[$current_post->ID])) {
      $already_run[$current_post->ID] = false;
      $new_content[$current_post->ID] = '';
      $content_length[$current_post->ID] = -1;
    }

    if($already_run[$current_post->ID] && strlen($content) == $content_length[$current_post->ID]) {
      return $new_content[$current_post->ID];
    }

    $content_length[$current_post->ID] = strlen($content);
    $already_run[$current_post->ID] = true;

    $mepr_options = MeprOptions::fetch();

    switch($current_post->ID) {
      case $mepr_options->account_page_id:
        if(!MeprUser::manually_place_account_form($current_post)) {
          try {
            $account_ctrl = MeprCtrlFactory::fetch('account');
            $content = $account_ctrl->display_account_form($content);
          }
          catch(Exception $e) {
            ob_start();
            ?>
            <div class="mepr_error"><?php _e('We can\'t display your account form right now. Please come back soon and try again.', 'memberpress'); ?></div>
            <?php
            $content = ob_get_clean();
          }
        }
        break;
      case $mepr_options->login_page_id:
        ob_start();

        $action = self::get_param('action');
        $manual_login_form = get_post_meta($current_post->ID, '_mepr_manual_login_form', true);

        try {
          $login_ctrl = MeprCtrlFactory::fetch('login');

          if($action and $action == 'forgot_password') {
            $login_ctrl->display_forgot_password_form();
          }
          else if($action and $action == 'mepr_process_forgot_password') {
            $login_ctrl->process_forgot_password_form();
          }
          else if($action and $action == 'reset_password') {
            $login_ctrl->display_reset_password_form(self::get_param('mkey',''),self::get_param('u',''));
          }
          else if($action and $action === 'mepr_process_reset_password_form' && isset($_POST['errors'])) {
            $login_ctrl->display_reset_password_form_errors($_POST['errors']);
          }
          else if(!$manual_login_form || ($manual_login_form && $action == 'mepr_unauthorized')) {
            $message = '';

            if($action and $action == 'mepr_unauthorized') {
              $resource = isset($_REQUEST['redirect_to']) ? esc_url(urldecode($_REQUEST['redirect_to'])) : __('the requested resource.','memberpress');
              $unauth_message = wpautop(MeprHooks::apply_filters('mepr-unauthorized-message', do_shortcode($mepr_options->unauthorized_message), $current_post));

              //Maybe override the message if a page id is set
              if(isset($_GET['mepr-unauth-page'])) {
                $unauth_post = get_post((int)$_GET['mepr-unauth-page']);
                $unauth = MeprRule::get_unauth_settings_for($unauth_post);
                $unauth_message = $unauth->message;
              }

              $message = '<p id="mepr-unauthorized-for-resource">' . __('Unauthorized for', 'memberpress') . ': <span id="mepr-unauthorized-resource-url">' . $resource . '</span></p>' . $unauth_message;
            }

            $login_ctrl->display_login_form(false, false, $message);
          }
        }
        catch(Exception $e) {
          $login_actions = array(
            'forgot_password',
            'mepr_process_forgot_password',
            'reset_password',
            'mepr_process_reset_password_form',
            'mepr_unauthorized'
          );

          if($action && in_array($action,$login_actions)) {
            ?>
            <div class="mepr_error"><?php _e('There was a problem with our system. Please come back soon and try again.', 'memberpress'); ?></div>
            <?php
          }
        }

        //Some crazy trickery here to prevent from having to completely rewrite a lot of crap
        //This is a fix for https://github.com/Caseproof/memberpress/issues/609
        if(!$manual_login_form || ($action && $action == 'bpnoaccess')) { //BuddyPress fix
          $content .= ob_get_clean();
        }
        elseif($action) {
          $match_str = '#' . preg_quote('<!-- mp-login-form-start -->') . '.*' . preg_quote('<!-- mp-login-form-end -->') . '#s';
          //preg_quote below helps fix an issue with the math captcha add-on when using a shortcode for login
          $content = stripslashes(preg_replace($match_str, ob_get_clean(), preg_quote($content)));
        }
        else { //do nothing really
          ob_end_clean();
        }
        break;
      case $mepr_options->thankyou_page_id:
        $message = MeprProductsCtrl::maybe_get_thank_you_page_message();

        // If a custom message is set, only show that message
        if($message != '') { $content = $message; }
        break;
    }

    // See above notes
    $new_content[$current_post->ID] = $content;
    return $new_content[$current_post->ID];
  }

  public static function load_scripts() {
    global $post;

    $mepr_options = MeprOptions::fetch();

    $is_product_page = ( false !== ( $prd = MeprProduct::is_product_page($post) ) );
    $is_group_page   = ( false !== ( $grp = MeprGroup::is_group_page($post) ) );
    $is_login_page   = MeprUser::is_login_page($post);
    $is_account_page = MeprUser::is_account_page($post);
    $global_styles   = $mepr_options->global_styles;

    MeprHooks::do_action('mepr_enqueue_scripts', $is_product_page, $is_group_page, $is_account_page);

    // Yeah we enqueue this globally all the time so the login form will work on any page
    wp_enqueue_style('mp-theme', MEPR_CSS_URL . '/ui/theme.css', null, MEPR_VERSION);

    if($global_styles || $is_account_page) {
      wp_enqueue_style('mp-account-css', MEPR_CSS_URL.'/ui/account.css', null, MEPR_VERSION);
    }

    if($global_styles || $is_login_page || has_shortcode(get_the_content(null, false, $post) , 'mepr-login-form') || is_active_widget(false, false, 'mepr_login_widget')) {
      wp_enqueue_style( 'dashicons' );
      wp_enqueue_style( 'mp-login-css', MEPR_CSS_URL.'/ui/login.css', null, MEPR_VERSION);

      wp_register_script('mepr-login-js', MEPR_JS_URL.'/login.js', array('jquery', 'underscore', 'wp-i18n'), MEPR_VERSION);

      wp_enqueue_script('mepr-login-i18n');
      wp_enqueue_script('mepr-login-js');
    }

    if($global_styles || $is_product_page || $is_account_page) {
      $wp_scripts = new WP_Scripts();
      $ui = $wp_scripts->query('jquery-ui-core');
      $url = "//ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.css";
      wp_enqueue_style('mepr-jquery-ui-smoothness', $url);
      wp_enqueue_style('jquery-ui-timepicker-addon', MEPR_CSS_URL.'/jquery-ui-timepicker-addon.css', array('mepr-jquery-ui-smoothness'));

      $prereqs = MeprHooks::apply_filters('mepr-signup-styles', array());
      wp_enqueue_style('mp-signup',  MEPR_CSS_URL.'/signup.css', $prereqs, MEPR_VERSION);

      wp_register_script('mepr-timepicker-js', MEPR_JS_URL.'/jquery-ui-timepicker-addon.js', array('jquery-ui-datepicker'));
      wp_register_script('mp-datepicker', MEPR_JS_URL.'/date_picker.js', array('mepr-timepicker-js'), MEPR_VERSION);

      $date_picker_frontend = array('translations' => self::get_datepicker_strings(), 'timeFormat' => (is_admin())?'HH:mm:ss':'', 'dateFormat' => MeprUtils::datepicker_format(get_option('date_format')), 'showTime' => (is_admin())?true:false);
      wp_localize_script('mp-datepicker', 'MeprDatePicker', $date_picker_frontend);

      wp_register_script('jquery.payment', MEPR_JS_URL.'/jquery.payment.js');
      wp_register_script('mp-validate', MEPR_JS_URL.'/validate.js');
      wp_register_script('mp-i18n', MEPR_JS_URL.'/i18n.js');

      $i18n = array('states' => MeprUtils::states(), 'ajaxurl' => admin_url('admin-ajax.php'));
      $i18n['please_select_state'] = __('-- Select State --', 'memberpress');
      wp_localize_script('mp-i18n', 'MeprI18n', $i18n);

      $prereqs = MeprHooks::apply_filters(
        'mepr-signup-scripts',
        array('jquery','jquery.payment','mp-validate','mp-i18n','mp-datepicker'),
        $is_product_page,
        $is_account_page
      );

      wp_enqueue_script('mp-signup', MEPR_JS_URL.'/signup.js', $prereqs, MEPR_VERSION);

      $local_data = array(
        'coupon_nonce' => wp_create_nonce('mepr_coupons'),
        'spc_enabled'  => $mepr_options->enable_spc,
        'spc_invoice'  => $mepr_options->enable_spc_invoice
      );

      wp_localize_script('mp-signup', 'MeprSignup', $local_data);

      //For Show hide password
      wp_enqueue_style( 'dashicons' );
      wp_enqueue_style( 'mp-login-css', MEPR_CSS_URL.'/ui/login.css', null, MEPR_VERSION);

      wp_register_script('mepr-login-js', MEPR_JS_URL.'/login.js', array('jquery', 'underscore', 'wp-i18n'), MEPR_VERSION);

      wp_enqueue_script('mepr-login-i18n');
      wp_enqueue_script('mepr-login-js');
    }

    if($global_styles || $is_group_page) {
      wp_enqueue_style('mp-plans-css', MEPR_CSS_URL . '/plans.min.css', array(), MEPR_VERSION);
    }
  }

  public static function get_datepicker_strings() {
    return array(
      'closeText' => _x( 'Done', 'ui', 'memberpress' ),
      'currentText' => _x( 'Today', 'ui', 'memberpress' ),
      'monthNamesShort' => [ _x( 'Jan', 'ui', 'memberpress' ), _x( 'Feb', 'ui', 'memberpress' ), _x( 'Mar', 'ui', 'memberpress' ), _x( 'Apr', 'ui', 'memberpress' ), _x( 'May', 'ui', 'memberpress' ), _x( 'Jun', 'ui', 'memberpress' ),
      _x( 'Jul', 'ui', 'memberpress' ), _x( 'Aug', 'ui', 'memberpress' ), _x( 'Sep', 'ui', 'memberpress' ), _x( 'Oct', 'ui', 'memberpress' ), _x( 'Nov', 'ui', 'memberpress' ), _x( 'Dec', 'ui', 'memberpress' ) ],
      'dayNamesMin' => [ _x( 'Su', 'ui', 'memberpress' ),_x( 'Mo', 'ui', 'memberpress' ),_x( 'Tu', 'ui', 'memberpress' ),_x( 'We', 'ui', 'memberpress' ),_x( 'Th', 'ui', 'memberpress' ),_x( 'Fr', 'ui', 'memberpress' ),_x( 'Sa', 'ui', 'memberpress' ) ]
    );
  }

  public static function load_admin_scripts($hook)
  {
    global $wp_version;

    $popup_ctrl = new MeprPopupCtrl();
    wp_enqueue_style('jquery-magnific-popup', $popup_ctrl->popup_css);

    wp_register_style( 'mepr-settings-table-css',
                        MEPR_CSS_URL.'/settings_table.css',
                        array(), MEPR_VERSION );
    wp_enqueue_style( 'mepr-admin-shared-css',
                      MEPR_CSS_URL.'/admin-shared.css',
                      array('wp-pointer','jquery-magnific-popup','mepr-settings-table-css'), MEPR_VERSION );
    wp_enqueue_style( 'mepr-fontello-animation',
                      MEPR_VENDOR_LIB_URL.'/fontello/css/animation.css',
                      array(), MEPR_VERSION );
    wp_enqueue_style( 'mepr-fontello-memberpress',
                      MEPR_VENDOR_LIB_URL.'/fontello/css/memberpress.css',
                      array('mepr-fontello-animation'), MEPR_VERSION );

    // If we're in 3.8 now then use a font for the admin image
    if( version_compare( $wp_version, '3.8', '>=' ) ) {
      wp_enqueue_style( 'mepr-menu-styles', MEPR_CSS_URL.'/menu-styles.css',
                        array('mepr-fontello-memberpress'), MEPR_VERSION );
    }

    wp_register_script('jquery-magnific-popup', $popup_ctrl->popup_js, array('jquery'));
    wp_enqueue_script('mepr-tooltip', MEPR_JS_URL.'/tooltip.js', array('jquery','wp-pointer','jquery-magnific-popup'), MEPR_VERSION);
    wp_localize_script('mepr-tooltip', 'MeprTooltip', array( 'show_about_notice' => self::show_about_notice(),
                                                             'about_notice' => self::about_notice() ));
    wp_register_script('mepr-settings-table-js', MEPR_JS_URL.'/settings_table.js', array('jquery'), MEPR_VERSION);
    wp_register_script('mepr-cookie-js', MEPR_JS_URL.'/js.cookie.min.js', array(), '2.2.1');
    wp_enqueue_script('mepr-admin-shared-js', MEPR_JS_URL.'/admin_shared.js', array('jquery', 'jquery-magnific-popup', 'mepr-settings-table-js', 'mepr-cookie-js'), MEPR_VERSION);
    wp_localize_script('mepr-admin-shared-js', 'MeprAdminShared', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'dismiss_notice_nonce' => wp_create_nonce('mepr_dismiss_notice')
    ));

    //Widget in the dashboard stuff
    if($hook == 'index.php') {
      $local_data = array(
        'report_nonce' => wp_create_nonce('mepr_reports')
      );
      wp_enqueue_script('mepr-google-jsapi', 'https://www.gstatic.com/charts/loader.js', array(), MEPR_VERSION);
      wp_enqueue_script('mepr-widgets-js', MEPR_JS_URL.'/admin_widgets.js', array('jquery', 'mepr-google-jsapi'), MEPR_VERSION, true);
      wp_localize_script('mepr-widgets-js', 'MeprWidgetData', $local_data);
      wp_enqueue_style('mepr-widgets-css', MEPR_CSS_URL.'/admin-widgets.css', array(), MEPR_VERSION);
    }
  }

  // The tight way to process standalone requests dogg...
  public static function parse_standalone_request() {
    global $user_ID;

    $plugin     = (isset($_REQUEST['plugin']))?$_REQUEST['plugin']:'';
    $action     = (isset($_REQUEST['action']))?$_REQUEST['action']:'';
    $controller = (isset($_REQUEST['controller']))?$_REQUEST['controller']:'';

    $request_uri = $_SERVER['REQUEST_URI'];

    // Pretty Mepr Notifier ... prevents POST vars from being mangled
    $notify_url_pattern = MeprUtils::gateway_notify_url_regex_pattern();
    if(MeprUtils::match_uri($notify_url_pattern,$request_uri,$m)) {
      $plugin = 'mepr';
      $_REQUEST['pmt'] = $m[1];
      $action = $m[2];
    }

    try {
      if(MeprUtils::is_post_request() && isset($_POST['mepr_process_signup_form'])) {
        if( MeprUtils::is_user_logged_in() &&
            isset($_POST['logged_in_purchase']) &&
            $_POST['logged_in_purchase'] == 1 ) {
          check_admin_referer( 'logged_in_purchase', 'mepr_checkout_nonce' );
        }

        $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
        $checkout_ctrl->process_signup_form();
      }
      else if(isset($_POST) && isset($_POST['mepr_process_payment_form'])) {
        $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
        $checkout_ctrl->process_payment_form();
      }
      else if($action==='checkout' && isset($_REQUEST['txn'])) {
        $_REQUEST['txn'] = MeprUtils::base36_decode($_REQUEST['txn']);

        //Back button fix
        $txn = new MeprTransaction((int)$_REQUEST['txn']);
        if(strpos($txn->trans_num, 'mp-txn-') === false || $txn->status != MeprTransaction::$pending_str) {
          $prd = new MeprProduct($txn->product_id);
          MeprUtils::wp_redirect($prd->url());
        }

        $checkout_ctrl = MeprCtrlFactory::fetch('checkout');
        $checkout_ctrl->display_payment_page();
      }
      else if(isset($_POST) && isset($_POST['mepr_process_login_form'])) {
        $login_ctrl = MeprCtrlFactory::fetch('login');
        $login_ctrl->process_login_form();
      }
      else if( MeprUtils::is_post_request() && $plugin=='mepr' && $action=='updatepassword' &&
               isset($_POST['mepr-new-password']) && isset($_POST['mepr-confirm-password']) ) {
        check_admin_referer( 'update_password', 'mepr_account_nonce' );
        $account_ctrl = MeprCtrlFactory::fetch('account');
        $account_ctrl->save_new_password($user_ID, $_POST['mepr-new-password'], $_POST['mepr-confirm-password']);
      }
      else if( !empty($plugin) && $plugin == 'mepr' && !empty($controller) && !empty($action) ) {
        self::standalone_route($controller, $action);
        exit;
      }
      else if(!empty($plugin) && $plugin == 'mepr' && isset($_REQUEST['pmt']) &&
              !empty($_REQUEST['pmt']) && !empty($action)) {
        $mepr_options = MeprOptions::fetch();
        $obj = MeprHooks::apply_filters('mepr_gateway_notifier_obj', $mepr_options->payment_method($_REQUEST['pmt']), $action, $_REQUEST['pmt']);
        if( $obj && ( $obj instanceof MeprBaseRealGateway ) ) {
          $notifiers = $obj->notifiers();
          if( isset($notifiers[$action]) ) {
            call_user_func(array($obj,$notifiers[$action]));
            exit;
          }
        }
      }
    }
    catch(Exception $e) {
      ?>
      <div class="mepr_error"><?php printf(__('There was a problem with our system: %s. Please come back soon and try again.', 'memberpress'), $e->getMessage()); ?></div>
      <?php
      exit;
    }
  }

  // Routes for standalone / ajax requests
  public static function standalone_route($controller, $action) {
    if($controller == 'coupons') {
      if($action == 'validate') {
        MeprCouponsCtrl::validate_coupon_ajax(MeprAppCtrl::get_param('mepr_coupon_code'), MeprAppCtrl::get_param('mpid'));
      }
    }
  }

  public static function load_language() {
    /*
    * Allow add-ons and such to load .po/mo files from outside directories using this filter hook
    * WordPress will merge transalations if the textdomain is the same from multiple locations
    * so we should be good to do it this way
    */
    $paths = array();
    $paths[] = str_replace(WP_PLUGIN_DIR, '', MEPR_I18N_PATH);

    //Have to use WP_PLUGIN_DIR because load_plugin_textdomain doesn't accept abs paths
    if(!file_exists(WP_PLUGIN_DIR . '/' . 'mepr-i18n')) {
      @mkdir(WP_PLUGIN_DIR . '/' . 'mepr-i18n');

      if(file_exists(WP_PLUGIN_DIR . '/' . 'mepr-i18n'))
        $paths[] = '/mepr-i18n';
    }
    else {
      $paths[] = '/mepr-i18n';
    }

    $paths = MeprHooks::apply_filters('mepr-textdomain-paths', $paths);

    foreach($paths as $path) {
      load_plugin_textdomain('memberpress', false, $path);
    }

    //Force a refresh of the $mepr_options so those strings can be marked as translatable in WPML/Polylang type plugins
    MeprOptions::fetch(true);
  }

  // Utility function to grab the parameter whether it's a get or post
  public static function get_param($param, $default = '') {
    return (isset($_REQUEST[$param])?$_REQUEST[$param]:$default);
  }

  public static function get_param_delimiter_char($link)
  {
    return ((preg_match("#\?#",$link))?'&':'?');
  }

  public static function add_dashboard_widgets()
  {
    if(!MeprUtils::is_mepr_admin())
      return;

    wp_add_dashboard_widget('mepr_weekly_stats_widget', 'MemberPress Weekly Stats', 'MeprAppCtrl::weekly_stats_widget');

    // Globalize the metaboxes array, this holds all the widgets for wp-admin
    global $wp_meta_boxes;

    // Get the regular dashboard widgets array
    // (which has our new widget already but at the end)
    $normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

    // Backup and delete our new dashbaord widget from the end of the array
    $mepr_weekly_stats_widget_backup = array('mepr_weekly_stats_widget' => $normal_dashboard['mepr_weekly_stats_widget']);
    unset($normal_dashboard['mepr_weekly_stats_widget']);

    // Merge the two arrays together so our widget is at the beginning
    $sorted_dashboard = array_merge($mepr_weekly_stats_widget_backup, $normal_dashboard);

    // Save the sorted array back into the original metaboxes
    $wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
  }

  public static function add_sidebar_widgets() {
    try {
      $account_ctrl = MeprCtrlFactory::fetch('account');
      wp_register_sidebar_widget( 'mepr-account-links', __('MemberPress Account Links', 'memberpress'), array($account_ctrl,'account_links_widget') );
      //control func below doesn't do anything, but without it a bunch of debug notices are logged when in the theme customizer
      wp_register_widget_control( 'mepr-account-links', __('MemberPress Account Links', 'memberpress'), function($args=array(), $params=array()){} );
    }
    catch(Exception $e) {
      // Silently fail if the account controller is absent
    }
  }

  public static function weekly_stats_widget() {
    $mepr_options = MeprOptions::fetch();
    $failed_transactions = $pending_transactions = $refunded_transactions = $completed_transactions = $revenue = $refunds = 0;
    $time = time();
    for($i = 0; $i < 7; $i++) {
      $ts = $time - MeprUtils::days($i);
      $month = gmdate('n', $ts);
      $day = gmdate('j', $ts);
      $year = gmdate('Y', $ts);

      $pending_transactions += MeprReports::get_transactions_count(MeprTransaction::$pending_str, $day, $month, $year);
      $failed_transactions += MeprReports::get_transactions_count(MeprTransaction::$failed_str, $day, $month, $year);
      $refunded_transactions += MeprReports::get_transactions_count(MeprTransaction::$refunded_str, $day, $month, $year);
      $completed_transactions += MeprReports::get_transactions_count(MeprTransaction::$complete_str, $day, $month, $year);

      $revenue += MeprReports::get_revenue($month, $day, $year);
      $refunds += MeprReports::get_refunds($month, $day, $year);
    }

    MeprView::render('/admin/widgets/admin_stats_widget', get_defined_vars());
  }

  public static function todays_date() {
    if(isset($_REQUEST['datetime'])) {
      echo date_i18n('Y-m-d H:i:s', time(), true);
    }
    else {
      echo date_i18n('Y-m-d', time(), true);
    }

    die;
  }

  public static function show_about_notice() {
    $last_shown_notice = get_option('mepr_about_notice_version');
    $version_str = preg_replace('/\./','-',MEPR_VERSION);
    return ( $last_shown_notice != MEPR_VERSION &&
             file_exists( MeprView::file("/admin/about/{$version_str}") ) );
  }

  public static function about_notice() {
    $version_str  = preg_replace('/\./','-',MEPR_VERSION);
    $version_file = MeprView::file("/admin/about/{$version_str}");
    if( file_exists( $version_file ) ) {
      ob_start();
      require_once($version_file);
      return ob_get_clean();
    }

    return '';
  }

  public static function close_about_notice() {
    update_option('mepr_about_notice_version',MEPR_VERSION);
  }

  public static function cleanup_list_view($views) {
    if(isset($views['draft'])) { unset($views['draft']); }
    if(isset($views['publish'])) { unset($views['publish']); }
    return $views;
  }

  public function cleanup_list_table_month_dropdown( $months, $post_type ) {
    $ours = array( MeprProduct::$cpt, MeprRule::$cpt, MeprGroup::$cpt, MeprCoupon::$cpt );
    if( in_array( $post_type, $ours ) ) { $months = array(); }
    return $months;
  }

  // TODO: We want to eliminate this when we get css compilation / compression in place
  public static function load_css() {
    //IF WE MOVE BACK TO admin-ajax.php method, then this conditional needs to go
    if( !isset($_GET['plugin']) ||
        $_GET['plugin'] != 'mepr' ||
        !isset($_GET['action']) ||
        $_GET['action'] != 'mepr_load_css' ) {
      return;
    }

    header('Content-Type: text/css');
    header('Cache-Control: max-age=2629000, public'); //1 month
    header('Expires: '.gmdate('D, d M Y H:i:s', (int)(time() + 2629000)).' GMT'); //1 month?

    $css = '';

    if(isset($_REQUEST['t']) && $_REQUEST['t']=='price_table') {
      $csskey = 'mp-css-' . md5(MEPR_VERSION);
      $css_files = get_transient($csskey);

      //$css_files = false;
      if(!$css_files) {
        $css_files = array();

        // Enqueue plan templates
        $css_files = array_merge($css_files, MeprGroup::group_theme_templates(true));

        // Enqueue plans
        $css_files = array_merge($css_files, MeprGroup::group_themes(true));

        set_transient($csskey, $css_files, DAY_IN_SECONDS);
      }
    }

    if(isset($css_files) && !empty($css_files)) {
      $csskey = 'mp-load-css-' . md5(MEPR_VERSION) . '-' . md5(implode(',',$css_files));
      $css = get_transient($csskey);

      if(!$css) {
        ob_start();

        foreach($css_files as $f) {
          if(file_exists($f)) { echo file_get_contents($f); }
        }

        $css = MeprUtils::compress_css(ob_get_clean());
        set_transient($csskey, $css, DAY_IN_SECONDS);
      }
    }

    exit($css);
  }

  public static function append_mp_privacy_policy() {
    if(!function_exists('wp_add_privacy_policy_content')) { return; }

    ob_start();
    MeprView::render('/admin/privacy/privacy_policy', get_defined_vars());
    $privacy_policy = ob_get_clean();

    wp_add_privacy_policy_content('MemberPress', $privacy_policy);
  }

  public static function integrate_wp_debugging($user_defined_constants) {
    if(!defined('WP_MEPR_DEBUG') || WP_MEPR_DEBUG === false) {
      // if raw is true, then value will be converted to boolean
      $user_defined_constants['WP_MEPR_DEBUG'] = array('value' => 'true', 'raw' => true);
    }

    return $user_defined_constants;
  }

  public static function render_admin_support() {
    MeprView::render('admin/support/view');
  }
} //End class
