<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprGoogleCaptchaIntegration {
  public function __construct() {
    add_action('plugins_loaded', array($this, 'load_hooks'));
  }

  public function load_hooks() {
    if(!function_exists('gglcptch_is_recaptcha_required')) {
      return;
    }

    add_filter('gglcptch_add_custom_form', array($this, 'add_options'));
    add_filter('mepr-validate-signup', array($this, 'remove_authenticate_action'));
    add_filter('mepr-validate-login', array($this, 'remove_authenticate_action'));
    add_filter('mepr-validate-forgot-password', array($this, 'remove_allow_password_reset_action'));
    add_filter('mepr-validate-reset-password', array($this, 'remove_authenticate_action'));

    if(gglcptch_is_recaptcha_required('memberpress_checkout')) {
      add_action('mepr-checkout-before-submit', array($this, 'add_recaptcha'));
      add_filter('mepr-validate-signup', array($this, 'verify_recaptcha'));
    }

    if(gglcptch_is_recaptcha_required('memberpress_login')) {
      add_action('mepr-login-form-before-submit', array($this, 'add_recaptcha'));
      add_filter('mepr-validate-login', array($this, 'verify_recaptcha'));
    }

    if(gglcptch_is_recaptcha_required('memberpress_forgot_password')) {
      add_action('mepr-forgot-password-form', array($this, 'add_recaptcha'));
      add_filter('mepr-validate-forgot-password', array($this, 'verify_recaptcha'));
    }
  }

  public function add_options($forms) {
    $forms['memberpress_checkout'] = array('form_name' => __('MemberPress checkout form', 'memberpress'));
    $forms['memberpress_login'] = array('form_name' => __('MemberPress login form', 'memberpress'));
    $forms['memberpress_forgot_password'] = array('form_name' => __('MemberPress forgot password form', 'memberpress'));

    return $forms;
  }

  public function add_recaptcha() {
    ?>
    <div class="mp-form-row mepr-google-captcha">
      <?php echo do_shortcode('[bws_google_captcha]'); ?>
    </div>
    <?php
  }

  public function verify_recaptcha($errors) {
    $is_valid = apply_filters('gglcptch_verify_recaptcha', true);

    if(!$is_valid) {
      $errors[] = __('Captcha verification failed', 'memberpress');
    }

    return $errors;
  }

  public function remove_authenticate_action($errors) {
    // We need to remove this action or the reCAPTCHA is checked twice
    remove_action('authenticate', 'gglcptch_login_check', 21);

    return $errors;
  }

  public function remove_allow_password_reset_action($errors) {
    // We need to remove this action or the reCAPTCHA is checked twice
    remove_action('allow_password_reset', 'gglcptch_lostpassword_check');

    return $errors;
  }
}

new MeprGoogleCaptchaIntegration;
