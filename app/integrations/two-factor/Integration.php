<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprTwoFactorIntegration {
  public function __construct() {
    add_action('template_redirect', [$this, 'enqueue_twofactor_scripts']);
    add_action('mepr_account_nav_content', [$this, 'add_two_factor_nav_content']);
    add_action('mepr_account_nav', [$this, 'add_two_factor_nav']);
    add_action('mepr_buddypress_integration_setup_menus', [$this, 'add_two_factor_nav_buddypress']);
  }

  public function enqueue_twofactor_scripts() {
    global $post;

    if(MeprUser::is_account_page($post)) {
      if(isset($_GET['action']) && $_GET['action'] == '2fa' && class_exists('Two_Factor_FIDO_U2F_Admin')) {
        Two_Factor_FIDO_U2F_Admin::enqueue_assets('profile.php');
      }
    }
  }

  public function add_two_factor_nav_buddypress($main_slug) {
    if(defined('TWO_FACTOR_DIR')) {
      global $bp;
      bp_core_new_subnav_item(
        array(
          'name' => _x('Two Factor Authentication', 'ui', 'memberpress-buddypress', 'memberpress'),
          'slug' => 'mp-two-factor-auth',
          'parent_url' => $bp->loggedin_user->domain . $main_slug . '/',
          'parent_slug' => $main_slug,
          'screen_function' => array($this, 'bbpress_twofactor_nav'),
          'position' => 20,
          'user_has_access' => bp_is_my_profile(),
          'site_admin_only' => false,
          'item_css_id' => 'mepr-bp-two-factor-auth'
        )
      );
    }
  }

  public function bbpress_twofactor_nav() {
    add_action('bp_template_content', array($this, 'bbpress_twofactor_content'));

    //Enqueue the account page scripts here yo
    $acct_ctrl = new MeprAccountCtrl();
    $acct_ctrl->enqueue_scripts(true);

    bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
  }

  public function add_two_factor_nav() {
    if(defined('TWO_FACTOR_DIR')) { ?>
      <?php
      $mepr_options = MeprOptions::fetch();
      $account_url = $mepr_options->account_page_url();
      $delim = MeprAppCtrl::get_param_delimiter_char($account_url);
      ?>
      <a
        href="<?php echo MeprHooks::apply_filters('mepr-account-nav-2fa-link', $account_url . $delim . 'action=2fa'); ?>"
        id="mepr-account-2fa"><?php echo MeprHooks::apply_filters('mepr-account-nav-2fa-label', _x('Two Factor Authentication', 'ui', 'memberpress')); ?></a>
      <?php
    }
  }

  public function add_two_factor_nav_content($action = null) {
    if ($action !== '2fa') {
      return null;
    }

    if(defined('TWO_FACTOR_DIR')) {
      $user = MeprUtils::get_currentuserinfo();

      if ( ! empty( $_POST ) ) {
        $this->user_two_factor_options_update( $user->ID );
        echo '<p>' . __('Settings Have been saved!', 'memberpress') . '</p>';
      } else {
        echo '<form action="" method="post">';
        $wp_user = get_user_by( 'id', $user->ID );
        $this->user_two_factor_options( $wp_user );
        echo '<input type="submit" value="' . __('SAVE OPTIONS', 'memberpress'). '"/>';
        echo '</form>';
      }
    }
  }

  public function bbpress_twofactor_content() {
    if(defined('TWO_FACTOR_DIR')) {
      $user = MeprUtils::get_currentuserinfo();

      if ( ! empty( $_POST ) ) {
        $this->user_two_factor_options_update( $user->ID );
        echo '<p>' . __('Settings Have been saved!', 'memberpress') . '</p>';
      } else {
        echo '<form action="" method="post">';
        $wp_user = get_user_by( 'id', $user->ID );
        $this->user_two_factor_options( $wp_user );
        echo '<input type="submit" value="' . __('SAVE OPTIONS', 'memberpress'). '"/>';
        echo '</form>';
      }
    }
  }

  /**
   * Update the user meta value.
   *
   * This executes during the `personal_options_update` & `edit_user_profile_update` actions.
   *
   * @since 0.1-dev
   *
   * @param int $user_id User ID.
   */
  public static function user_two_factor_options_update( $user_id ) {
    if ( isset( $_POST['_nonce_user_two_factor_options'] ) ) {
      check_admin_referer( 'user_two_factor_options', '_nonce_user_two_factor_options' );

      if ( ! isset( $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] ) ||
           ! is_array( $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ] ) ) {
        return;
      }

      $providers = self::get_providers();

      $enabled_providers = $_POST[ Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ];

      // Enable only the available providers.
      $enabled_providers = array_intersect( $enabled_providers, array_keys( $providers ) );
      update_user_meta( $user_id, Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY, $enabled_providers );

      // Primary provider must be enabled.
      $new_provider = isset( $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] ) ? $_POST[ Two_Factor_Core::PROVIDER_USER_META_KEY ] : '';
      if ( ! empty( $new_provider ) && in_array( $new_provider, $enabled_providers, true ) ) {
        update_user_meta( $user_id, Two_Factor_Core::PROVIDER_USER_META_KEY, $new_provider );
      }
    }
  }

  /**
   * Add user profile fields.
   *
   * This executes during the `show_user_profile` & `edit_user_profile` actions.
   *
   * @since 0.1-dev
   *
   * @param WP_User $user WP_User object of the logged-in user.
   */
  public static function user_two_factor_options( $user ) {
    $home_url = get_site_url();
    wp_enqueue_style( 'user-edit-2fa', $home_url . '/wp-content/plugins/two-factor/user-edit.css' );

    $enabled_providers = array_keys( Two_Factor_Core::get_available_providers_for_user( $user ) );
    $primary_provider = Two_Factor_Core::get_primary_provider_for_user( $user->ID );

    if ( ! empty( $primary_provider ) && is_object( $primary_provider ) ) {
      $primary_provider_key = get_class( $primary_provider );
    } else {
      $primary_provider_key = null;
    }

    wp_nonce_field( 'user_two_factor_options', '_nonce_user_two_factor_options', false );

    ?>
    <input type="hidden" name="<?php echo esc_attr( Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ); ?>[]" value="<?php /* Dummy input so $_POST value is passed when no providers are enabled. */ ?>" />
    <table class="form-table" id="two-factor-options">
      <tr>
        <th>
          <?php esc_html_e( 'Two-Factor Options', 'two-factor', 'memberpress' ); ?>
        </th>
        <td>
          <table class="two-factor-methods-table">
            <thead>
            <tr>
              <th class="col-enabled" scope="col"><?php esc_html_e( 'Enabled', 'two-factor', 'memberpress' ); ?></th>
              <th class="col-primary" scope="col"><?php esc_html_e( 'Primary', 'two-factor', 'memberpress' ); ?></th>
              <th class="col-name" scope="col"><?php esc_html_e( 'Name', 'two-factor', 'memberpress' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( self::get_providers() as $class => $object ) : ?>
              <tr>
                <th scope="row"><input type="checkbox" name="<?php echo esc_attr( Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY ); ?>[]" value="<?php echo esc_attr( $class ); ?>" <?php checked( in_array( $class, $enabled_providers, true ) ); ?> /></th>
                <th scope="row"><input type="radio" name="<?php echo esc_attr( Two_Factor_Core::PROVIDER_USER_META_KEY ); ?>" value="<?php echo esc_attr( $class ); ?>" <?php checked( $class, $primary_provider_key ); ?> /></th>
                <td>
                  <?php
                  $object->print_label();

                  /**
                   * Fires after user options are shown.
                   *
                   * Use the {@see 'two_factor_user_options_' . $class } hook instead.
                   *
                   * @deprecated 0.7.0
                   *
                   * @param WP_User $user The user.
                   */
                  do_action_deprecated(  'two-factor-user-options-' . $class, array( $user ), '0.7.0', 'two_factor_user_options_' . $class );
                  do_action( 'two_factor_user_options_' . $class, $user );
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </td>
      </tr>
    </table>
    <script type="text/javascript">
      var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php', 'relative' ) ); ?>,
        pagenow = 'customize';
    </script>
    <?php
  }

  /**
   * For each provider, include it and then instantiate it.
   *
   * @since 0.1-dev
   *
   * @return array
   */
  public static function get_providers() {
    $providers = Two_Factor_Core::get_providers();

    if(isset($providers['Two_Factor_FIDO_U2F'])) {
      // Remove this as it causes problem on frontend. The problem? it's using
      // WP_List_Table and this class doesn't fully work on frontpage
      unset($providers['Two_Factor_FIDO_U2F']);
    }

    return $providers;
  }
}

new MeprTwoFactorIntegration;
