<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprCoursesCtrl extends MeprBaseCtrl {

  private $courses_slug = 'memberpress-courses/main.php';

  public function load_hooks() {
    if ( ! is_plugin_active( $this->courses_slug ) ) {
      add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
      add_action( 'wp_ajax_mepr_courses_action', array( $this, 'ajax_courses_action' ) );
      add_action( 'mepr_display_options_tabs', array( $this, 'courses_tab' ), 99 );
      add_action( 'mepr_display_options', array( $this, 'courses_tab_content' ) );
    }
  }

  public static function route() {
    $plugins = get_plugins();
    MeprView::render('/admin/courses/ui', get_defined_vars());
  }

  public function enqueue_scripts($hook) {
    if(preg_match('/_page_memberpress-(courses|options)$/', $hook)) {
      wp_enqueue_style('mepr-sister-plugin-css', MEPR_CSS_URL . '/admin-sister-plugin.css', array(), MEPR_VERSION);
    }
  }

  /**
   * Adds the "Courses" tab to the MemberPress settings page.
   *
   * @return void
   */
  public function courses_tab() {
    ?>
      <a class="nav-tab" id="courses" href="#"><?php _e( 'Courses', 'memberpress' ); ?></a>
    <?php
  }

  /**
   * Renders the "Courses" tab content.
   *
   * @return void
   */
  public function courses_tab_content() {
    ?>
    <div id="courses" class="mepr-options-hidden-pane">
      <?php MeprView::render('/admin/courses/ui', get_defined_vars()); ?>
    </div>
    <?php
  }

  /**
   * Handle actions for MemberPress Courses
   *
   * @return void
   */
  public function ajax_courses_action() {

    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'mepr_courses_action' ) ) {
      die();
    }

    if ( ! current_user_can( 'activate_plugins' ) ) {
      wp_send_json_error( __( 'Sorry, you don\'t have permission to do this.', 'memberpress' ) );
    }

    $type = sanitize_text_field( $_POST['type'] );
    $installed = false;
    $activated = false;
    $message = '';
    $result = 'error';
    switch ( $type ) {
      case 'install-activate' : // Install and activate courses
        $installed = $this->install_courses( true );
        $activated = $installed ? $installed : $activated;
        $result = $installed ? 'success' : 'error';
        $message = $installed ? esc_html__( 'Courses has been installed and activated successfully. Enjoy!', 'memberpress' ) : esc_html__( 'Courses could not be installed. Please check your license settings, or contact MemberPress support for help.', 'memberpress' );
        break;
      case 'activate' : // Just activate (already installed)
        $activated = activate_plugin( $this->courses_slug );
        $result = 'success';
        $message = esc_html__( 'Courses has been activated successfully. Enjoy!', 'memberpress' );
        break;
      default:
        break;
    }

    delete_option( 'mepr_courses_flushed_rewrite_rules' );

    $redirect = '';

    if ( $activated ) {
      $redirect = add_query_arg( array(
        'post_type' => 'mpcs-course',
        'courses_activated' => 'true'
      ), admin_url( 'edit.php' ) );
    }

    wp_send_json_success( array(
      'installed' => $installed,
      'activated' => $activated,
      'result' => $result,
      'message' => $message,
      'redirect' => $redirect
    ) );
  }

  /**
   * Install the MemberPress Courses addon
   *
   * @param boolean $activate Whether to activate after installing
   *
   * @return boolean Whether the plugin was installed
   */
  public function install_courses( $activate = false ) {

    $force = isset($_GET['refresh']) && $_GET['refresh'] == 'true';
    $addons = (array) MeprUpdateCtrl::addons(true, $force, true);
    $courses_addon = ! empty( $addons['memberpress-courses'] ) ? $addons['memberpress-courses'] : array();
    $plugins = get_plugins();
    wp_cache_delete('plugins', 'plugins');

    if ( empty( $courses_addon ) ) {
      return false;
    }

    // Set the current screen to avoid undefined notices
    set_current_screen( "memberpress_page_{$this->courses_slug}" );

    // Prepare variables
    $url = esc_url_raw(
      add_query_arg(
        array(
          'page' => $this->courses_slug,
        ),
        admin_url('admin.php')
      )
    );

    $creds = request_filesystem_credentials( $url, '', false, false, null );

    // Check for file system permissions
    if ( false === $creds ) {
      wp_send_json_error( esc_html( 'File system credentials failed.', 'memberpress' ) );
    }
    if ( ! WP_Filesystem( $creds ) ) {
      wp_send_json_error( esc_html( 'File system credentials failed.', 'memberpress' ) );
    }

    // We do not need any extra credentials if we have gotten this far, so let's install the plugin
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    // Do not allow WordPress to search/download translations, as this will break JS output
    remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

    // Create the plugin upgrader with our custom skin
    $installer = new Plugin_Upgrader( new MeprAddonInstallSkin() );

    $plugin = wp_unslash( $courses_addon->url );
    $installer->install( $plugin );

    // Flush the cache and return the newly installed plugin basename
    wp_cache_flush();

    if ( $installer->plugin_info() && true === $activate ) {
      activate_plugin( $installer->plugin_info() );
    }

    return $installer->plugin_info();
  }

} //End class

