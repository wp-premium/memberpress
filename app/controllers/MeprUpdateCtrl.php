<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUpdateCtrl extends MeprBaseCtrl {

  public function load_hooks() {
    add_filter( 'auto_update_plugin', 'MeprUpdateCtrl::automatic_updates', 10, 2 );
    add_filter('pre_set_site_transient_update_plugins', 'MeprUpdateCtrl::queue_update');
    add_filter('plugins_api', 'MeprUpdateCtrl::plugin_info', 11, 3);
    add_action('admin_enqueue_scripts', 'MeprUpdateCtrl::enqueue_scripts');
    add_action('admin_notices', 'MeprUpdateCtrl::activation_warning');
    add_action('admin_notices', 'MeprUpdateCtrl::bf_upgrade_notices');
    //add_action('mepr_display_options', 'MeprUpdateCtrl::queue_button');
    add_action('admin_init', 'MeprUpdateCtrl::activate_from_define');
    add_action('admin_init', 'MeprUpdateCtrl::maybe_activate');
    add_action('wp_ajax_mepr_edge_updates', 'MeprUpdateCtrl::mepr_edge_updates');
    add_action( 'wp_ajax_mepr_dismiss_bf_notice', 'MeprUpdateCtrl::dismiss_bf_notice' );
    //add_action('wp_ajax_mepr_rollback', 'MeprUpdateCtrl::rollback');

    add_action( 'mepr_display_general_options', array( $this,'display_options' ), 99 );
    add_action( 'mepr-process-options', array( $this, 'store_options' ) );

    // Add a custom admin menu item
    add_action('admin_menu', 'MeprUpdateCtrl::admin_menu', 50);
  }

  public static function dismiss_bf_notice() {

    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mepr_dismiss_bf_notice' ) ) {
      die();
    }

    update_option( 'mp_2020_bf_dismissed', true );
    wp_send_json_success( array(), 201 );
  }

  public static function bf_upgrade_notices() {
    if ( ! MeprUtils::is_memberpress_admin_page() ) {
      return;
    }

    if ( ! MeprUtils::is_black_friday_time() || ! empty( get_option( 'mp_2020_bf_dismissed' ) ) ) {
      return;
    }

    $mepr_options = MeprOptions::fetch();

    if(!empty($mepr_options->mothership_license)) {
      $li = get_site_transient('mepr_license_info');

      if(false === $li) {
        MeprUpdateCtrl::manually_queue_update();
        $li = get_site_transient('mepr_license_info');
      }
    }

    $link = 'https://memberpress.com/plans/pricing/';
    $heading = '📢 Black Friday is here!';
    $message = "Get MemberPress TODAY for up to $250 OFF. PLUS enter to win a 27\" iMac w/ Retina 5K display. Don't wait!";
    $button_text = 'Get MemberPress 👈';

    if ( ! empty( $li['license_key']['expires_at'] ) && strtotime( $li['license_key']['expires_at'] ) < time() ) {
      // Expired
      $message = "RENEW MemberPress TODAY for up to $250 OFF. PLUS enter to win a 27\" iMac w/ Retina 5K display. Don't wait!";
      $button_text = 'Renew MemberPress 👈';
    } else {
      // Active
      switch ( $li['product_slug'] ) {
        case 'memberpress-basic':
          $message = "UPGRADE to MemberPress Plus or Pro TODAY & SAVE up to $250 OFF. And enter to win a 27\" iMac w/ Retina 5K display. Don't wait!";
          $button_text = 'Upgrade MemberPress 👈';
          break;

        case 'memberpress-plus':
          $message = "UPGRADE to MemberPress PRO TODAY & SAVE up to $250 OFF. PLUS enter to win a 27\" iMac w/ Retina 5K display. Don't wait!";
          $button_text = 'Upgrade MemberPress 👈';
          break;

        case 'memberpress-pro':
          $message = "Upgrade to a 2 or 3 year MemberPress Pro License TODAY & SAVE up to $250 OFF. PLUS enter to win a 27\" iMac w/ Retina 5K display!";
          $button_text = 'Upgrade MemberPress 👈';
          $heading = '📢 Black Friday is here!';
          break;

        case 'memberpress-pro-5':
          $message = "Sign up for another 2 or 3 year MemberPress Pro License & SAVE up to $250 OFF. PLUS enter to win a 27\" iMac w/ Retina 5K display!";
          $button_text = 'Renew MemberPress 👈';
          $heading = '📢 It\'s Black Friday!';
          break;

        default:
          break;
      }
    }

    MeprView::render('/admin/black-friday', get_defined_vars());
  }

  public static function route() {
    if(strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
      return self::process_form();
    }
    else {
      if( isset($_GET['action']) &&
          $_GET['action'] == 'deactivate' &&
          isset($_GET['_wpnonce']) &&
          wp_verify_nonce($_GET['_wpnonce'], 'memberpress_deactivate') ) {
        return self::deactivate();
      }
      else {
        return self::display_form();
      }
    }
  }

  public static function admin_menu() {
    // Create an official rollback page in the fashion of WordPress' built in upgrader
    if(isset($_GET['page']) && $_GET['page'] == 'mepr-rollback') {
      add_dashboard_page(__('Rollback MemberPress', 'memberpress'), __('Rollback MemberPress', 'memberpress'), 'update_plugins', 'mepr-rollback', 'MeprUpdateCtrl::rollback');
    }
  }

  public function display_options() {
    $mepr_options = MeprOptions::fetch();
    MeprView::render('admin/auto-updates/option', get_defined_vars());
  }

  public function store_options() {
    $mepr_options = MeprOptions::fetch();
    $name = $mepr_options->auto_updates_str;
    $mepr_options->auto_updates = isset( $_POST[$name] ) ? sanitize_text_field( $_POST[$name] ) : false;
    $mepr_options->store(false);
  }

  /**
   * Gets major version
   *
   * @param  string   $version  Version
   *
   * @return string
   */
  public static function get_major_version( $version ) {
      $exploded_version = explode( '.', $version );
      return $exploded_version[0];
  }

  /**
   * Filters the auto update plugin routine to allow MemberPress to be
   * automatically updated.
   *
   * @param bool    $update   Flag to update the plugin or not.
   * @param array   $item     Update data about a specific plugin.
   * @return bool   $update   The new update state.
   */
  /**
   * Notes about autoupdater:
   * This runs on the normal WordPress auto-update sequence:
   * 1. In wp-includes/update.php, wp_version_check() is called by the WordPress update cron (every 8 or 12 hours; can be overriden to be faster/long or turned off by plugins)
   * 2. In wp-includes/update.php, wp_version_check() ends with a action call to do_action( 'wp_maybe_auto_update' ) if cron is running
   * 3. In wp-includes/update.php, wp_maybe_auto_update() hooks into wp_maybe_auto_update action, creates a new WP_Automatic_Updater instance and calls WP_Automatic_Updater->run
   * 4. In wp-admin/includes/class-wp-automatic-updater.php $this->run() checks to make sure we're on the main site if on a network, and also if the autoupdates are disabled (by plugin, by being on a version controlled site, etc )
   * 5. In wp-admin/includes/class-wp-automatic-updater.php $this->run() then checks to see which plugins have new versions (version/update check)
   * 6. In wp-admin/includes/class-wp-automatic-updater.php $this->run() then calls $this->update() for each plugin installed who has an upgrade.
   * 7 In wp-admin/includes/class-wp-automatic-updater.php $this->update() double checks filesystem access and then installs the plugin if able
   *
   * Notes:
   * - This autoupdater only works if WordPress core detects no version control. If you want to test this, do it on a new WP site without any .git folders anywhere.
   * - This autoupdater only works if the file access is able to be written to
   * - This autoupdater only works if a new version has been detected, and will run not the second the update is released, but whenever the cron for wp_version_check is next released. This is generally run every 8-12 hours.
   * - However, that cron can be disabled, the autoupdater can be turned off via constant or filter, version control or file lock can be detected, and other plugins can be installed (incl in functions of theme) that turn off all
   *      all automatic plugin updates.
   * - If you want to test this is working, you have to manually run the wp_version_check cron. Install the WP Crontrol plugin or Core Control plugin, and run the cron manually using it.
   * - Again, because you skimmed over it the first time, if you want to test this manually you need to test this on a new WP install without version control for core, plugins, etc, without file lock, with license key entered (for pro only)
   *        and use the WP Crontrol or Core Control plugin to run wp_version_check
   * - You may have to manually remove an option called "auto_update.lock" from the WP options table
   * - You may need to run wp_version_check multiple times (note though that they must be spaced at least 60 seconds apart)
   * - Because WP's updater asks the OS if the file is writable, make sure you do not have any files/folders for the plugin you are trying to autoupdate open when testing.
   * - You may need to delete the plugin info transient to get it to hard refresh the plugin info.
   */
  public static function automatic_updates( $update, $item ) {

      // If this is multisite and is not on the main site, return early.
      if ( is_multisite() && ! is_main_site() ) {
          return $update;
      }

      // If we don't have everything we need, return early.
      $item = (array) $item;
      if ( ! isset( $item['new_version'] ) || ! isset( $item['slug'] ) ) {
          return $update;
      }

      // If the plugin isn't ours, return early.
      $is_memberpress = 'memberpress' === $item['slug'];
      $is_addon = isset( $item['slug'] ) && 0 === strpos( $item['slug'], 'memberpress-' ); // see updater class
      if ( ! $is_memberpress && ! $is_addon ) {
          return $update;
      }

      $mepr_options = MeprOptions::fetch();

      $automatic_updates = ! empty( $mepr_options->auto_updates ) ? $mepr_options->auto_updates : 'all';
      $current_major     = self::get_major_version( mepr_plugin_info( 'Version' ) );
      $new_major         = self::get_major_version( $item['new_version'] );

      // Major update available
      // If major update are enabled, run the update, else bail
      if ( $current_major < $new_major ) {
        return 'all' === $automatic_updates ? true : $update;
      }

      // Minor update available
      // If minor (or major) updates are enabled, run the update, else bail
      if ( $current_major === $new_major && version_compare( mepr_plugin_info( 'Version' ), $item['new_version'], '<' ) ) {
        return 'all' === $automatic_updates || 'minor' === $automatic_updates ? true : $update;
      }

      return $update;
  }

  public static function rollback() {
    // Ensure the rollback is valid
    check_admin_referer('mepr_rollback_nonce');

    // Permissions check
    if(!current_user_can('update_plugins')) {
      wp_die(__('You don\'t have sufficient permissions to rollback MemberPress.', 'memberpress'));
    }

    $transient = get_site_transient('update_plugins');
    $transient = self::queue_update($transient, true, true);

    $info = get_site_transient('mepr_update_info');

    //Get the necessary class
    include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
    include_once(MEPR_LIB_PATH . '/class-rollback-memberpress-upgrader.php');

    $args = wp_parse_args($_GET, array('page' => 'mepr-rollback'));

    $nonce   = 'upgrade-plugin_' . MEPR_PLUGIN_NAME;
    $url     = 'index.php?page=mepr-rollback';
    $plugin  = MEPR_PLUGIN_NAME;
    $version = $info['curr_version'];

    $upgrader = new WP_Rollback_MemberPress_Upgrader(
      new Plugin_Upgrader_Skin(compact('title','nonce','url','plugin','version'))
    );

    $upgrader->rollback($info);
  }

  public static function rollback_url() {
    $nonce = wp_create_nonce('mepr_rollback_nonce');
    return admin_url("index.php?page=mepr-rollback&_wpnonce={$nonce}");
  }

  public static function display_form($message='', $errors=array()) {
    $mepr_options = MeprOptions::fetch();

    // We just force the queue to update when this page is visited
    // that way we ensure the license info transient is set
    self::manually_queue_update();

    if(!empty($mepr_options->mothership_license) && empty($errors)) {
      $li = get_site_transient( 'mepr_license_info' );
    }

    MeprView::render('/admin/update/ui', get_defined_vars());
  }

  public static function process_form() {
    if(!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'activation_form')) {
      wp_die(_e('Why you creepin\'?', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();

    if(!isset($_POST[$mepr_options->mothership_license_str])) {
      self::display_form();
      return;
    }

    $message = '';
    $errors = array();
    $mepr_options->mothership_license = sanitize_text_field(wp_unslash($_POST[$mepr_options->mothership_license_str]));

    try {
      $act = self::send_mothership_request("/license_keys/jactivate/{$mepr_options->mothership_license}", self::activation_args(true), 'post');
      self::manually_queue_update();
      $mepr_options->store(false);
      $message = $act['message'];
    }
    catch(Exception $e) {
      $errors[] = $e->getMessage();
    }

    self::display_form($message, $errors);
  }

  public static function is_activated() {
    $mepr_options = MeprOptions::fetch();
    $activated = get_option('mepr_activated');
    return (!empty($mepr_options->mothership_license) && !empty($activated));
  }

  public static function activation_args($return_json=false) {
    $args = array(
      'domain' => urlencode(MeprUtils::site_domain()),
      'extra_info' => array(
        //'members' => array(
        //  'paid' => MeprReports::get_paid_active_members_count(),
        //  'free' => MeprReports::get_free_active_members_count(),
        //  'inactive' => MeprReports::get_inactive_members_count(),
        //)
      )
    );

    if($return_json) {
      $args = json_encode($args);
    }

    return $args;
  }

  public static function check_license_activation() {
    $aov = get_option('mepr_activation_override');

    if(!empty($aov)) { return update_option('mepr_activated', true); }

    $mepr_options = MeprOptions::fetch();
    $domain = urlencode(MeprUtils::site_domain());

    $args = compact('domain');

    try {
      $act = self::send_mothership_request("/license_keys/check/{$mepr_options->mothership_license}", $args, 'get');

      if(!empty($act) && is_array($act) && isset($act['status'])) {
        update_option('mepr_activated', ($act['status']=='enabled'));
      }
    }
    catch(Exception $e) {
      // TODO: For now do nothing if the server can't be reached
    }
  }

  public static function maybe_activate() {
    $activated = get_option('mepr_activated');

    if(!$activated) {
      self::check_license_activation();
    }
  }

  public static function activate_from_define() {
    $mepr_options = MeprOptions::fetch();

    if( defined('MEMBERPRESS_LICENSE_KEY') &&
        $mepr_options->mothership_license != MEMBERPRESS_LICENSE_KEY ) {
      $message = '';
      $errors = array();
      $mepr_options->mothership_license = stripslashes(MEMBERPRESS_LICENSE_KEY);
      $domain = urlencode(MeprUtils::site_domain());

      try {
        $args = compact('domain');

        if(!empty($mepr_options->mothership_license)) {
          $act = self::send_mothership_request("/license_keys/deactivate/{$mepr_options->mothership_license}", $args, 'post');
          delete_site_transient('mepr_addons');
        }

        $act = self::send_mothership_request("/license_keys/jactivate/".MEMBERPRESS_LICENSE_KEY, self::activation_args(true), 'post');

        self::manually_queue_update();

        // If we're using defines then we have to do this with defines too
        $mepr_options->edge_updates = false;
        $mepr_options->store(false);

        $message = $act['message'];
        $view = '/admin/errors';
        $callback = function() use($view, $message) {
          return MeprView::render($view, compact('message'));
        };
      }
      catch(Exception $e) {
        $view = '/admin/update/activation_warning';
        $error = $e->getMessage();
        $callback = function() use($view, $error) {
          return MeprView::render($view, compact('error'));
        };
      }

      add_action( 'admin_notices', $callback );
    }
  }

  public static function deactivate() {
    $mepr_options = MeprOptions::fetch();
    $domain       = urlencode(MeprUtils::site_domain());
    $message      = '';

    try {
      $args = compact('domain');
      $act = self::send_mothership_request("/license_keys/deactivate/{$mepr_options->mothership_license}", $args, 'post');

      self::manually_queue_update();

      $mepr_options->deactivate_license();

      $message = $act['message'];
    }
    catch(Exception $e) {
      $mepr_options->deactivate_license();

      $errors[] = $e->getMessage();
    }

    self::display_form($message);
  }

  public static function queue_update($transient, $force=false, $rollback=false) {
    $mepr_options = MeprOptions::fetch();

    $update_info = get_site_transient('mepr_update_info');

    if($force || (false === $update_info)) {
      if(empty($mepr_options->mothership_license)) {
        // Just here to query for the current version
        $args = array();
        if( $mepr_options->edge_updates || ( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) ) {
          $args['edge'] = 'true';
        }

        try {
          $version_info = self::send_mothership_request( "/versions/latest/developer", $args );
          $curr_version = $version_info['version'];
          $download_url = '';
        }
        catch(Exception $e) {
          return $transient;
        }
      }
      else {
        try {
          $domain = urlencode(MeprUtils::site_domain());
          $args = compact('domain');

          if( $mepr_options->edge_updates || ( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) ) {
            $args['edge'] = 'true';
          }

          if($rollback) {
            $args['curr_version'] = MEPR_VERSION;
            $args['rollback'] = 'true';
          }

          $license_info = self::send_mothership_request("/versions/info/{$mepr_options->mothership_license}", $args, 'post');
          $curr_version = $license_info['version'];
          $download_url = $license_info['url'];

          set_site_transient(
            'mepr_license_info',
            $license_info,
            MeprUtils::hours(12)
          );
        }
        catch(Exception $e) {
          try {
            // Just here to query for the current version
            $args = array();
            if( $mepr_options->edge_updates || ( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) ) {
              $args['edge'] = 'true';
            }

            $version_info = self::send_mothership_request("/versions/latest/developer", $args);
            $curr_version = $version_info['version'];
            $download_url = '';
          }
          catch(Exception $e) {
            if(isset($transient->response[MEPR_PLUGIN_SLUG])) {
              unset($transient->response[MEPR_PLUGIN_SLUG]);
            }

            self::check_license_activation();
            return $transient;
          }
        }
      }

      set_site_transient(
        'mepr_update_info',
        compact('curr_version', 'download_url'),
        MeprUtils::hours(12)
      );

      self::addons(false, true);
    }
    else {
      extract( $update_info );
    }

    if(isset($curr_version) && ($rollback || version_compare($curr_version, MEPR_VERSION, '>'))) {
      $transient->response[MEPR_PLUGIN_SLUG] = (object)array(
        'id'          => $curr_version,
        'plugin'      => MEPR_PLUGIN_SLUG,
        'slug'        => 'memberpress',
        'new_version' => $curr_version,
        'url'         => 'http://memberpress.com',
        'package'     => $download_url
      );
    }
    else {
      unset( $transient->response[MEPR_PLUGIN_SLUG] );
    }

    self::check_license_activation();
    return $transient;
  }

  public static function manually_queue_update() {
    $transient = get_site_transient('update_plugins');
    set_site_transient('update_plugins', self::queue_update($transient, true));
  }

  public static function queue_button() {
    ?>
    <a href="<?php echo admin_url('admin.php?page=memberpress-options&action=queue&_wpnonce=' . wp_create_nonce('MeprUpdateCtrl::manually_queue_update')); ?>" class="button"><?php _e('Check for Update', 'memberpress')?></a>
    <?php
  }

  // Return up-to-date addon info for memberpress & its addons
  public static function plugin_info($api, $action, $args) {
    global $wp_version;

    if(!isset($action) || $action != 'plugin_information') {
      return $api;
    }
    elseif(isset($args->slug) && preg_match("#^(affiliate-royale)#", $args->slug)) {
      // If AR is installed we allow it to take care of updates
      if(is_plugin_active('affiliate-royale/affiliate-royale.php')) {
        return $api;
      }
    }
    elseif(isset($args->slug) && !preg_match("#^(memberpress|affiliate-royale)#", $args->slug)) {
      return $api;
    }

    if($args->slug === 'memberpress') {
      $mothership_slug = 'developer';
      $display_name = MEPR_DISPLAY_NAME;
      $description = '
        <h3>The "All-In-One" Membership Plugin for WordPress</h3>
        <p>
          MemberPress will help you build astounding WordPress membership sites, accept credit cards securely, control who sees your content, and sell digital downloads ... all without the difficult setup.
        </p>
        <p>
          MemberPress will help you confidently create, manage and track membership subscriptions and sell digital download products. In addition to these features, MemberPress will allow you manage your members by granting and revoking their access to posts, pages, videos, categories, tags, feeds, communities, digital files and more based on what memberships they belong to.
        </p>
        <p>
          With MemberPress you’ll be able to create powerful and compelling WordPress membership sites that leverage all of the great features of WordPress, WordPress plugins and other 3rd party services including content management, forums, and social communities.
        </p>
      ';
      $faq = 'You can read more about how to use MemberPress by visiting <a href="https://memberpress.com/user-manual/">the user manual</a>.';
      $changelog = 'You can read more about the latest changes to MemberPress by visiting <a href="https://memberpress.com/change-log/">the change log</a>';
    }
    else {
      $mothership_slug = $args->slug;
      $faq = 'You can read more about MemberPress Add-Ons by visiting <a href="https://docs.memberpress.com/category/19-addons">the user manual</a>.';
      $addon_info = self::mepr_addon_info($args->slug);
      if(!empty($addon_info)) {
        $display_name = $addon_info['Name'];
        $description = $addon_info['Description'];
      }
      else {
        $display_name = 'MemberPress Add-On';
        $description = 'MemberPress Add-On';
      }
      if ( in_array( $display_name, array(
        'MemberPress Courses',
        'MemberPress Downloads',
        'MemberPress Developer Tools',
        'MemberPress Corporate Accounts',
        'MemberPress PDF Invoice',
        'MemberPress + BuddyPress Integration'
      ) ) ) {
        $plugin_slug = ($args->slug === 'memberpress-courses') ? 'memberpress-courses' : str_replace('memberpress', '', $addon_info['TextDomain']);
        $changelog = "You can read more about the latest changes to $display_name by visiting <a href=\"https://memberpress.com/add-ons/$plugin_slug/\">the change log</a>";
      }
    }

    $mepr_options = MeprOptions::fetch();

    $mothership_args = array();
    if( $mepr_options->edge_updates || (defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE)) {
      $mothership_args['edge'] = 'true';
    }
    $download_url = '';
    try {
      if(empty($mepr_options->mothership_license)) {
        $version_info = self::send_mothership_request("/versions/latest/{$mothership_slug}", $mothership_args);
      }
      else {
        $mothership_args['domain'] = urlencode(MeprUtils::site_domain());
        $version_info = self::send_mothership_request("/versions/info/{$mothership_slug}/{$mepr_options->mothership_license}", $mothership_args);
        $download_url = $version_info['url'];
      }
    }
    catch(Exception $e) {
      MeprUtils::error_log($e->getMessage());
      $version_info = array('version' => '', 'version_date' => '');
    }
    $plugin_info = array(
      'slug' => $args->slug,
      'name' => $display_name,
      'author' => '<a href="http://blairwilliams.com">Caseproof, LLC</a>',
      'author_profile' => 'http://blairwilliams.com',
      'contributors' => array(
        array('display_name' => 'Caseproof', 'profile' => '', 'avatar' => '')
      ),
      'homepage' => 'https://memberpress.com',
      'version' => $version_info['version'],
      'requires' => '3.8',
      'requires_php' => '5.3',
      'tested' => $wp_version,
      'compatibility' => array($wp_version => array($wp_version => array(100, 0, 0))),
      'last_updated' => $version_info['version_date'],
      'download_link' => $download_url,
      'sections' => array(
        'description' => $description,
        'faq' => $faq,
      ),
      'banners' => array(
        'low'  => MEPR_IMAGES_URL . '/banner-772x250.png',
        'high' => MEPR_IMAGES_URL . '/banner-1544x500.png'
      )
    );

    if(isset($changelog)) {
      $plugin_info['sections']['changelog'] = $changelog;
    }

    return (object)$plugin_info;
  }

  private static function mepr_addon_info($slug) {
    static $curr_plugins;

    if( !isset($curr_plugins) ) {
      if( !function_exists( 'get_plugins' ) ) {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
      }
      $curr_plugins = get_plugins();
      wp_cache_delete('plugins', 'plugins');
    }

    if(isset($curr_plugins[$slug . '/main.php'])) {
      return $curr_plugins[$slug . '/main.php'];
    }
    elseif(isset($curr_plugins[$slug . "/{$slug}.php"])) {
      return $curr_plugins[$slug . "/{$slug}.php"];
    }

    return '';
  }

  public static function send_mothership_request( $endpoint, $args=array(), $method='get', $blocking=true ) {
    $domain = defined('MEPR_MOTHERSHIP_DOMAIN') ? MEPR_MOTHERSHIP_DOMAIN : 'https://mothership.caseproof.com';
    $mepr_options = MeprOptions::fetch();
    $uri = "{$domain}{$endpoint}";

    $arg_array = array(
      'method'    => strtoupper($method),
      'body'      => $args,
      'timeout'   => 15,
      'blocking'  => $blocking,
      'sslverify' => $mepr_options->sslverify,
    );

    $resp = wp_remote_request($uri, $arg_array);

    // If we're not blocking then the response is irrelevant
    // So we'll just return true.
    if($blocking == false) {
      return true;
    }

    if(is_wp_error($resp)) {
      throw new Exception(__('You had an HTTP error connecting to Caseproof\'s Mothership API', 'memberpress'));
    }
    else {
      if(null !== ($json_res = json_decode($resp['body'], true))) {
        if(isset($json_res['error'])) {
          throw new Exception($json_res['error']);
        }
        else {
          return $json_res;
        }
      }
      else {
        throw new Exception(__('Your License Key was invalid', 'memberpress'));
      }
    }

    return false;
  }

  public static function enqueue_scripts($hook) {
    // toplevel_page_memberpress will only be accessible if the plugin is not enabled
    if($hook == 'memberpress_page_memberpress-options' ||
       (!MeprUpdateCtrl::is_activated() && $hook == 'toplevel_page_memberpress')) {
      wp_enqueue_style('mepr-activate-css', MEPR_CSS_URL.'/admin-activate.css', array('mepr-settings-table-css'), MEPR_VERSION);
    }
  }

  public static function activation_warning() {
    $mepr_options = MeprOptions::fetch();

    if(empty($mepr_options->mothership_license) &&
       (!isset($_REQUEST['page']) ||
         !($_REQUEST['page']=='memberpress-options' ||
           (!self::is_activated() && $_REQUEST['page']=='memberpress')))) {
      MeprView::render('/admin/update/activation_warning', get_defined_vars());
    }
  }

  public static function mepr_edge_updates() {
    if(!MeprUtils::is_mepr_admin() || !wp_verify_nonce($_POST['wpnonce'],'wp-edge-updates')) {
      die(json_encode(array('error' => __('You do not have access.', 'memberpress'))));
    }

    if(!isset($_POST['edge'])) {
      die(json_encode(array('error' => __('Edge updates couldn\'t be updated.', 'memberpress'))));
    }

    $mepr_options = MeprOptions::fetch();
    $mepr_options->edge_updates = ($_POST['edge']=='true');
    $mepr_options->store(false);

    // Re-queue updates when this is checked
    self::manually_queue_update();

    die(json_encode(array('state' => ($mepr_options->edge_updates ? 'true' : 'false'))));
  }

  public static function addons($return_object=false, $force=false, $all=false) {
    $mepr_options = MeprOptions::fetch();
    $license = $mepr_options->mothership_license;
    $transient = $all ? 'mepr_all_addons' : 'mepr_addons';

    if($force) {
      delete_site_transient($transient);
    }

    if(($addons = get_site_transient($transient))) {
      $addons = json_decode($addons);
    }
    else {
      $addons = array();

      if(!empty($license)) {
        try {
          $domain = urlencode(MeprUtils::site_domain());
          $args = compact('domain');

          if ($all) {
            $args['all'] = 'true';
          }

          if(defined('MEMBERPRESS_EDGE') && MEMBERPRESS_EDGE) { $args['edge'] = 'true'; }
          $addons = self::send_mothership_request('/versions/addons/'.MEPR_EDITION."/{$license}", $args);
        }
        catch(Exception $e) {
          // fail silently
        }
      }

      $json = json_encode($addons);
      set_site_transient($transient, $json, MeprUtils::hours(12));

      if($return_object) {
        $addons = json_decode($json);
      }
    }

    return $addons;
  }
} //End class
