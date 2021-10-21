<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprLoginCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    MeprHooks::add_shortcode('mepr-logout-link', array($this,'logout_link'));
    MeprHooks::add_shortcode('mepr-login-link', array($this,'logout_link'));
    MeprHooks::add_shortcode('logout_link', array($this,'logout_link')); // DEPRECATED
    MeprHooks::add_shortcode('mepr-login-form', array($this,'render_login_form'));

    // WP Login Customizations
    add_action('wp_logout', array($this,'logout_redirect_override'), 99999);
    add_filter('init', array($this,'override_wp_login_url_init'));
    add_action('init', array($this, 'process_reset_password_form'));
  }

  public function logout_link($atts) {
    $current_post = MeprUtils::get_current_post();
    $mepr_options = MeprOptions::fetch();
    $permalink = !empty($current_post->ID) ? MeprUtils::get_permalink($current_post->ID) : '';
    $arglist = '';

    if(isset($atts) && !empty($atts)) {
      foreach($atts as $att_key => $att_val) {
        $arglist .= " {$att_key}=\"{$att_val}\"";
      }
    }

    ob_start();

    if(MeprUtils::is_user_logged_in()) {
      ?>
      <a href="<?php echo MeprHooks::apply_filters('mepr-logout-url', wp_logout_url($mepr_options->login_page_url("redirect_to=".urlencode($permalink)))); ?>"<?php echo $arglist; ?>><?php _e('Logout', 'memberpress'); ?></a>
      <?php
    }
    else {
      ?>
      <a href="<?php echo $mepr_options->login_page_url("redirect_to=".urlencode($permalink)); ?>"<?php echo $arglist; ?>><?php _e('Login', 'memberpress'); ?></a>
      <?php
    }

    return ob_get_clean();
  }

  // Grabs a string of the login form
  public function render_login_form($atts=array(), $content='', $shortcode=true) {
    global $post;
    $mepr_options = MeprOptions::fetch();

    if(isset($atts['redirect_to']) && !empty($atts['redirect_to'])) {
      // Security fix. Restrict redirect_to param to safe URLs PT#154812459
      $_REQUEST['redirect_to'] = wp_validate_redirect($atts['redirect_to'], apply_filters( 'wp_safe_redirect_fallback', home_url(), 302));
    }

    ob_start();

    //BEGIN TEMP WPML FIX
    if( $shortcode && isset($_REQUEST['action']) &&
        $_REQUEST['action'] != 'mepr_unauthorized' &&
        $_REQUEST['action'] != 'bpnoaccess' && //BuddyPress fix
        !defined('DOING_AJAX') ) { //Don't do this if it's an ajax request. Probably loading up the form shortcode via AJAX
      //Need to check for this POST first
      if($_REQUEST['action'] == 'mepr_process_reset_password_form' && isset($_POST['errors'])) {
        $this->display_reset_password_form_errors($_POST['errors']);
      }
      elseif($_REQUEST['action'] == 'forgot_password') {
        $this->display_forgot_password_form();
      }
      elseif($_REQUEST['action'] == 'reset_password') {
        $this->display_reset_password_form($_REQUEST['mkey'], $_REQUEST['u']);
      }
      else{
        $this->display_login_form(
          $shortcode,
          (isset($atts['use_redirect']) && $atts['use_redirect']=='true')
        );
      }
    }
    //END TEMP WPML FIX
    else {
      if ( ! is_user_logged_in() || ! isset( $atts['show_logged_in'] ) || $atts['show_logged_in'] !== 'false' ) {
        $this->display_login_form(
          $shortcode,
          (isset($atts['use_redirect']) && $atts['use_redirect']=='true')
        );
      }
    }

    return ob_get_clean();
  }

  // Outputs the login form
  public function display_login_form($shortcode=false, $widget_use_redirect_urls = false, $message = '') {
    $current_post = MeprUtils::get_current_post();
    $mepr_options = MeprOptions::fetch();
    $login_page_id = (!empty($mepr_options->login_page_id) && $mepr_options->login_page_id > 0)?$mepr_options->login_page_id:0;
    $is_login_page = (is_page($login_page_id) || $widget_use_redirect_urls);

    // Initially set redirect_to to the default
    $redirect_to = $mepr_options->login_redirect_url;

    // if redirect_to isset then set it to the query param
    if(isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to'])) {
      $redirect_to = urldecode($_REQUEST['redirect_to']);
      // Security fix. Restrict redirect_to param to safe URLs PT#154812459
      $redirect_to = wp_validate_redirect($redirect_to, apply_filters( 'wp_safe_redirect_fallback', home_url(), 302));
    }

    // if we're on a page other than the login page and we're in a shortcode
    if((!isset($_REQUEST['redirect_to']) || empty($_REQUEST['redirect_to'])) &&
       false!==$shortcode && !is_page($login_page_id) && false===$widget_use_redirect_urls) {
      // $redirect_to = MeprUtils::get_permalink($current_post->ID);
      $redirect_to = esc_url($_SERVER['REQUEST_URI']);
    }

    // Check if we've got an unauth page set here
    // Is this even used here??? I don't think so, but leaving it here just in case
    if(isset($_REQUEST['mepr-unauth-page']) && !isset($_REQUEST['redirect_to'])) {
      $redirect_to = MeprUtils::get_permalink($_REQUEST['mepr-unauth-page']);
    }

    $redirect_to = MeprHooks::apply_filters('mepr-login-redirect-url', $redirect_to);

    if($login_page_id) {
      $login_url = $mepr_options->login_page_url();
      $login_delim = MeprAppCtrl::get_param_delimiter_char($login_url);
      $forgot_password_url = "{$login_url}{$login_delim}action=forgot_password";
    }
    else {
      $login_url = home_url('/wp-login.php');
      $forgot_password_url = home_url('/wp-login.php?action=lostpassword');
    }

    if(MeprUtils::is_user_logged_in()) {
      global $user_ID;

      $wp_user = get_user_by('id', $user_ID);

      //Need to override $redirect_to here if a per-membership login redirect URL is set (but do not track a login event)
      $redirect_to = MeprProductsCtrl::track_and_override_login_redirect_mepr($redirect_to, $wp_user, true, false);
      $redirect_to = urlencode($redirect_to);

      MeprView::render('/login/form', get_defined_vars());
      return;
    }

    if(!empty($_REQUEST['mepr_process_login_form']) && !empty($_REQUEST['errors'])) {
      $errors = array_map( 'wp_kses_post', $_REQUEST['errors'] );
      MeprView::render('/shared/errors', get_defined_vars());
    }

    MeprView::render('/login/form', get_defined_vars());
  }

  // Processes the login form
  public function process_login_form() {
    $mepr_options = MeprOptions::fetch();

    $errors = MeprHooks::apply_filters( 'mepr-validate-login',
      MeprUser::validate_login($_POST, array())
    );

    $login = stripcslashes(sanitize_text_field( $_POST['log'] )); //Have to do this for apostrophes in emails, cuz apparently that is a thing.

    if(is_email($login)) {
      $user = get_user_by('email', $login);

      if($user !== false) {
        $login = $user->user_login;
      }
    }

    if(!empty($errors)) {
      do_action('wp_login_failed', $login, $errors[0]);
      $_REQUEST['errors'] = $errors;
      return;
    }

    if(!function_exists('wp_signon')) {
      require_once(ABSPATH . WPINC . '/user.php');
    }

    $wp_user = wp_signon(
      array(
        'user_login' => $login,
        'user_password' => $_POST['pwd'], // Do not need to sanitize here - it causes issues with passwords like test%12test (the %12 is stripped out)
        'remember' => isset($_POST['rememberme'])
      ),
      MeprUtils::is_ssl() //May help with the users getting logged out when going between http and https
    );

    if(is_wp_error($wp_user)) {
      $_REQUEST['errors'] = $wp_user->get_error_messages();
      return;
    }

    if(isset($_POST['redirect_to'])) {
      $redirect_to = wp_sanitize_redirect( urldecode( $_POST['redirect_to'] ) );
      // Security fix. Restrict redirect_to param to safe URLs PT#154812459
      $redirect_to = wp_validate_redirect($redirect_to, apply_filters( 'wp_safe_redirect_fallback', home_url(), 302));
    }
    else {
      $redirect_to = $mepr_options->login_redirect_url;
    }
    $redirect_to = MeprHooks::apply_filters(
      'mepr-process-login-redirect-url',
      $redirect_to,
      $wp_user
    );

    MeprUtils::wp_redirect($redirect_to);
  }

  // Alters the default logout redirect
  public function logout_redirect_override() {
    $mepr_options = MeprOptions::fetch();

    if(isset($mepr_options->logout_redirect_url) && !empty($mepr_options->logout_redirect_url)) {
      MeprUtils::wp_redirect(MeprHooks::apply_filters('mepr-process-logout-redirect-url', $mepr_options->logout_redirect_url));
      exit;
    }
  }

  //This needs to be done in init as before then it seems to cause conflicts with Shield Security plugin
  public function override_wp_login_url_init() {
    add_filter('login_url', array($this, 'override_wp_login_url'), 999999, 2);
  }

  // Override the default wordpress login url
  public function override_wp_login_url($url, $redirect_to) {
    $mepr_options = MeprOptions::fetch();
    $redirect_to = urldecode($redirect_to); // might not be urlencoded, but let's do this just in case before we call urlencode below

    if(is_admin() || !$mepr_options->force_login_page_url || strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
      return $url;
    }

    if(!empty($redirect_to)) {
      $new_login_url = $mepr_options->login_page_url('redirect_to=' . urlencode($redirect_to));
    }
    else {
      $new_login_url = $mepr_options->login_page_url();
    }

    return $new_login_url;
  }

  public function display_forgot_password_form() {
    $mepr_options = MeprOptions::fetch();
    $mepr_blogurl = home_url();

    $mepr_user_or_email = (isset($_REQUEST['user_or_email'])) ? sanitize_text_field(urldecode($_REQUEST['user_or_email'])) : '';

    $process = MeprAppCtrl::get_param('mepr_process_forgot_password_form','');

    if(empty($process)) {
      MeprView::render('/login/forgot_password', get_defined_vars());
    }
    else {
      $this->process_forgot_password_form();
    }
  }

  public function process_forgot_password_form() {
    $mepr_options = MeprOptions::fetch();
    $errors = MeprHooks::apply_filters('mepr-validate-forgot-password', MeprUser::validate_forgot_password($_POST, array()));

    extract($_POST, EXTR_SKIP);

    if(empty($errors)) {
      $is_email = (is_email($mepr_user_or_email) and email_exists($mepr_user_or_email));
      $is_username = username_exists($mepr_user_or_email);
      $user = new MeprUser();

      // If the username & email are identical then let's rely on it as a username first and foremost
      if($is_username) {
        $user->load_user_data_by_login($mepr_user_or_email);
      }
      else if($is_email) {
        $user->load_user_data_by_email($mepr_user_or_email);
      }

      if($user->ID) {
        $user->send_password_notification('reset');

        MeprView::render('/login/forgot_password_requested', get_defined_vars());
      }
      else {
        MeprView::render('/shared/unknown_error', get_defined_vars());
      }
    }
    else {
      MeprView::render('/shared/errors', get_defined_vars());
      MeprView::render('/login/forgot_password', get_defined_vars());
    }
  }

  public function display_reset_password_form($mepr_key, $mepr_screenname) {
    $user = new MeprUser();
    $user->load_user_data_by_login($mepr_screenname);

    if($user->ID && $user->reset_form_key_is_valid($mepr_key)) {
      MeprView::render('/login/reset_password', get_defined_vars());
    }
    else {
      MeprView::render('/shared/unauthorized', get_defined_vars());
    }
  }

  public function display_reset_password_form_errors($errors) {
    if(!empty($errors)) {
      extract($_POST, EXTR_SKIP);
      MeprView::render('/shared/errors', get_defined_vars());
      MeprView::render('/login/reset_password', get_defined_vars());
    }
  }

  public function process_reset_password_form() {
    // Log user out when clicking reset password link
    if(MeprUtils::is_user_logged_in()) {
      if(isset($_GET['action']) && $_GET['action'] == 'reset_password' && isset($_GET['mkey']) && !isset($_GET['loggedout'])) {
        wp_destroy_current_session();
        wp_clear_auth_cookie();
        MeprUtils::wp_redirect($_SERVER['REQUEST_URI'] . '&loggedout=true'); // redirect to same page to flush login cookies
      }
    }

    if(isset($_POST['action']) && $_POST['action'] === 'mepr_process_reset_password_form') {
      $mepr_options = MeprOptions::fetch();

      if(isset($_POST['errors'])) {
        $errors = $_POST['errors'];
      }
      else {
        $errors = $_POST['errors'] = MeprHooks::apply_filters('mepr-validate-reset-password', MeprUser::validate_reset_password($_POST, array()));
      }

      if(empty($errors)) {
        extract($_POST, EXTR_SKIP);
        $user = new MeprUser();
        $user->load_user_data_by_login($mepr_screenname);

        if($user->ID) {
          $user->set_password_and_send_notifications($mepr_key, $mepr_user_password);

          if(MeprHooks::apply_filters('mepr-auto-login', true, null, $user)) {
            if(!MeprUtils::is_user_logged_in()) {
              $wp_user = wp_signon(
                array(
                  'user_login' => $mepr_screenname,
                  'user_password' => $mepr_user_password,
                ),
                MeprUtils::is_ssl()
              );

              if(!is_wp_error($wp_user)) {
                $redirect_to = $mepr_options->login_redirect_url;
                $redirect_to = MeprHooks::apply_filters(
                  'mepr-process-login-redirect-url',
                  $redirect_to,
                  $wp_user
                );

                MeprUtils::wp_redirect($redirect_to);
              }
              else {
                $_POST['errors'] = array($wp_user->get_error_message());
              }
            }
          }
        }
        else {
          $_POST['errors'] = array(__('An Unknown Error Occurred', 'ui', 'memberpress'));
        }
      }
      else {
        $_POST['errors'] = $errors;
      }
    }
  }
}
