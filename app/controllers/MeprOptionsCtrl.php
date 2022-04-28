<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprOptionsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('wp_ajax_mepr_activate_license', 'MeprOptionsCtrl::ajax_activate_license');
    add_action('wp_ajax_mepr_deactivate_license', 'MeprOptionsCtrl::ajax_deactivate_license');
    add_action('wp_ajax_mepr_install_license_edition', 'MeprOptionsCtrl::ajax_install_license_edition');
    add_action('wp_ajax_mepr_gateway_form', 'MeprOptionsCtrl::gateway_form');
    add_action('admin_enqueue_scripts', 'MeprOptionsCtrl::enqueue_scripts');
    add_action('admin_print_footer_scripts', 'MeprOptionsCtrl::enqueue_footer_scripts');
    add_action('admin_notices', 'MeprOptionsCtrl::maybe_show_stripe_checkout_warning');
  }

  public static function maybe_show_stripe_checkout_warning() {
    if (MeprUtils::is_get_request() && isset($_GET['page']) && $_GET['page'] == 'memberpress-options') {
      $mepr_options = MeprOptions::fetch();

      foreach ($mepr_options->integrations as $integration) {
        if (isset($integration['gateway']) && $integration['gateway'] == 'MeprStripeGateway' && isset($integration['use_stripe_checkout'])) {
          MeprView::render('/admin/stripe_checkout_deprecated');
          break;
        }
      }
    }
  }

  public static function route() {
    $action = (isset($_REQUEST['action'])?$_REQUEST['action']:'');

    if(MeprUtils::is_post_request() && $action == 'process-form') {
      check_admin_referer('mepr_update_options', 'mepr_options_nonce');
      return self::process_form();
    }
    else if($action == 'queue' and isset($_REQUEST['_wpnonce']) and
            wp_verify_nonce($_REQUEST['_wpnonce'], 'MeprUpdateCtrl::manually_queue_update')) {
      MeprUpdateCtrl::manually_queue_update();
    }
    else if($action==='upgrade') { // Manually upgrade the database
      $mepr_app = new MeprAppCtrl();
      try {
        delete_transient('mepr_migration_error');
        $mepr_app->install();
        $message = __('Database Was Upgraded', 'memberpress');
        return self::display_form(array(),$message);
      }
      catch(MeprDbMigrationException $e) {
        return self::display_form(array($e->getMessage()),'');
      }
    }
    else if($action==='clear_tax_rates') {
      check_admin_referer('clear_tax_rates', 'mepr_taxes_nonce');
      MeprTaxRate::destroy_all();
      $message = __('Tax rates have been cleared', 'memberpress');
      return self::display_form(array(),$message);
    }
    else {
      return self::display_form();
    }
  }

  public static function display_form($errors=array(), $message='') {
    $mepr_options = MeprOptions::fetch();

    if(MeprUtils::is_logged_in_and_an_admin()) {
      if(!empty($mepr_options->mothership_license)) {
        $li = get_site_transient('mepr_license_info');

        if($li === false) {
          MeprUpdateCtrl::manually_queue_update();
          $li = get_site_transient('mepr_license_info');
        }
      }

      MeprView::render('/admin/options/form', get_defined_vars());
    }
  }

  public static function process_form() {
    $mepr_options = MeprOptions::fetch();

    if(MeprUtils::is_logged_in_and_an_admin()) {
      $errors = MeprHooks::apply_filters('mepr-validate-options', $mepr_options->validate($_POST, array()));

      if(count($errors) <= 0) {
        MeprHooks::do_action('mepr-process-options', $_POST);
        $settings = MeprHooks::apply_filters( 'mepr-saved-options', $_POST );

        $mepr_options->update($settings);
        $mepr_options->store();

        // Ensure that the rewrite rules are flushed & in place
        MeprUtils::flush_rewrite_rules(); //Don't call this before running ->update() - it borks stuff

        $message = __('Options saved.', 'memberpress');
      }

      if(!empty($mepr_options->mothership_license)) {
        $li = get_site_transient('mepr_license_info');

        if($li === false) {
          MeprUpdateCtrl::manually_queue_update();
          $li = get_site_transient('mepr_license_info');
        }
      }

      MeprView::render('/admin/options/form', get_defined_vars());
    }
  }

  public static function enqueue_footer_scripts() {
    global $hook_suffix;
    if ( $hook_suffix == 'memberpress_page_memberpress-options' ) {
      ?>
      <script id="paypal-sdk-js" src="https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"></script>
      <?php
    }
  }

  public static function enqueue_scripts($hook) {
    if($hook == 'memberpress_page_memberpress-options') {
      $mepr_options = MeprOptions::fetch();

      wp_register_style('mepr-clipboardtip', MEPR_CSS_URL . '/tooltipster.bundle.min.css', array(), MEPR_VERSION );
      wp_register_style('mepr-clipboardtip-borderless', MEPR_CSS_URL . '/tooltipster-sideTip-borderless.min.css', array('mepr-clipboardtip'), MEPR_VERSION );
      wp_enqueue_style('mp-options', MEPR_CSS_URL.'/admin-options.css', array('mepr-settings-table-css','mepr-clipboardtip','mepr-clipboardtip-borderless'), MEPR_VERSION);
      wp_enqueue_style('mp-emails', MEPR_CSS_URL.'/admin-emails.css', array('mp-options'), MEPR_VERSION);

      $js_helpers = array(
        'nameLabel'         => __('Name:', 'memberpress'),
        'typeLabel'         => __('Type:', 'memberpress'),
        'defaultLabel'      => __('Default Value(s):', 'memberpress'),
        'signupLabel'       => __('Show at Signup', 'memberpress'),
        'accountLabel'      => __('Show in Account', 'memberpress'),
        'requiredLabel'     => __('Required', 'memberpress'),
        'textOption'        => __('Text', 'memberpress'),
        'textareaOption'    => __('Textarea', 'memberpress'),
        'checkboxOption'    => __('Checkbox', 'memberpress'),
        'dropdownOption'    => __('Dropdown', 'memberpress'),
        'multiselectOption' => __('Multi-Select', 'memberpress'),
        'emailOption'       => __('Email', 'memberpress'),
        'urlOption'         => __('URL', 'memberpress'),
        'phoneOption'       => __('Phone', 'memberpress'),
        'radiosOption'      => __('Radio Buttons', 'memberpress'),
        'checkboxesOption'  => __('Checkboxes', 'memberpress'),
        'fileuploadOption'  => __('File Upload', 'memberpress'),
        'dateOption'        => __('Date', 'memberpress'),
        'optionNameLabel'   => __('Option Name:', 'memberpress'),
        'optionValueLabel'  => __('Option Value:', 'memberpress'),
        'addOptionLabel'    => __('Add Option', 'memberpress'),
        'show_fname_lname_id'    => "#{$mepr_options->show_fname_lname_str}",
        'require_fname_lname_id' => "#{$mepr_options->require_fname_lname_str}",
        'jsUrl'             => MEPR_JS_URL,
        'taxRateRemoveStr'  => __('Are you sure you want to delete this Tax Rate?', 'memberpress'),
        'confirmPMDelete'   => __('WARNING: Do not remove this Payment Method if you have active subscriptions using it. Doing so will prevent you from being notified of recurring payments for those subscriptions, which means your members will lose access to their paid content. Are you sure you want to delete this Payment Method?', 'memberpress'),
        'wpnonce'           => wp_create_nonce(MEPR_PLUGIN_SLUG),
        'option_nonce'      => wp_create_nonce('mepr_gateway_form_nonce'),
        'tax_nonce'         => wp_create_nonce('mepr_taxes'),
        'activate_license_nonce' => wp_create_nonce('mepr_activate_license'),
        'activation_error'  => __('An error occurred during activation: %s', 'memberpress'),
        'invalid_response'        => __('Invalid response.', 'memberpress'),
        'ajax_error'        => __('Ajax error.', 'memberpress'),
        'deactivate_license_nonce' => wp_create_nonce('mepr_deactivate_license'),
        'deactivate_confirm' => sprintf(__('Are you sure? MemberPress will not be functional on %s if this License Key is deactivated.', 'memberpress'), MeprUtils::site_domain()),
        'deactivation_error'  => __('An error occurred during deactivation: %s', 'memberpress'),
        'install_license_edition_nonce' => wp_create_nonce('mepr_install_license_edition'),
      );

      wp_register_script('memberpress-i18n', MEPR_JS_URL.'/i18n.js', array('jquery'), MEPR_VERSION);
      wp_enqueue_script('alpinejs', 'https://unpkg.com/alpinejs@3.9.3/dist/cdn.min.js', array(), MEPR_VERSION, true);
      wp_localize_script('memberpress-i18n', 'MeprI18n', array('states' => MeprUtils::states()));

      wp_register_script( 'mepr-clipboard-js', MEPR_JS_URL . '/clipboard.min.js', array(), MEPR_VERSION );
      wp_register_script( 'mepr-tooltipster', MEPR_JS_URL . '/tooltipster.bundle.min.js', array('jquery'), MEPR_VERSION );
      wp_register_script( 'mepr-copy-to-clipboard', MEPR_JS_URL . '/copy_to_clipboard.js', array('mepr-clipboard-js','mepr-tooltipster'), MEPR_VERSION );
      wp_localize_script( 'mepr-copy-to-clipboard', 'MeprClipboard', array(
        'copy_text' => __('Copy to Clipboard', 'memberpress'),
        'copied_text' => __('Copied!', 'memberpress'),
        'copy_error_text' => __('Oops, Copy Failed!', 'memberpress'),
      ));

      wp_enqueue_script('mepr-options-js', MEPR_JS_URL.'/admin_options.js',
        array(
          'jquery',
          'mepr-copy-to-clipboard',
          'mepr-settings-table-js',
          'mepr-admin-shared-js',
          'jquery-ui-sortable',
          'memberpress-i18n'
        ),
        MEPR_VERSION
      );
      wp_localize_script('mepr-options-js', 'MeprOptions', $js_helpers);

      $email_locals = array(
        'set_email_defaults_nonce' => wp_create_nonce('set_email_defaults'),
        'send_test_email_nonce' => wp_create_nonce('send_test_email'),
      );
      wp_enqueue_script('mepr-emails-js', MEPR_JS_URL.'/admin_emails.js', array('mepr-options-js'), MEPR_VERSION);
      wp_localize_script('mepr-emails-js', 'MeprEmail', $email_locals);
      MeprHooks::do_action('mepr-options-admin-enqueue-script', $hook);
    }
  }

  public static function gateway_form() {
    check_ajax_referer('mepr_gateway_form_nonce', 'option_nonce');

    if(!is_admin()) {
      die(json_encode(array('error'=>__('Unauthorized', 'memberpress'))));
    }

    $mepr_options = MeprOptions::fetch();

    if(!isset($_POST['g']) or empty($_POST['g'])) {
      $gateways = array_keys(MeprGatewayFactory::all());

      if(empty($gateways)) {
        die(json_encode(array('error'=>__('No gateways were found', 'memberpress'))));
      }

      // Artificially set the gateway to the first available
      $gateway = $gateways[0];
    }
    else {
      $gateway = $_POST['g'];
    }

    try {
      $obj = MeprGatewayFactory::fetch($gateway);
    }
    catch(Exception $e) {
      die($e->getMessage());
    }

    ob_start();
    MeprView::render("/admin/options/gateway", get_defined_vars());
    $form = ob_get_clean();

    die( json_encode( array( 'form' => $form, 'id' => $obj->id ) ) );
  }

  public static function ajax_activate_license() {
    if(!MeprUtils::is_post_request() || !isset($_POST['key']) || !is_string($_POST['key'])) {
      wp_send_json_error(sprintf(__('An error occurred during activation: %s', 'memberpress'), __('Bad request.', 'memberpress')));
    }

    if(!MeprUtils::is_logged_in_and_an_admin()) {
      wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    if(!check_ajax_referer('mepr_activate_license', false, false)) {
      wp_send_json_error(sprintf(__('An error occurred during activation: %s', 'memberpress'), __('Security check failed.', 'memberpress')));
    }

    $mepr_options = MeprOptions::fetch();
    $license_key = sanitize_text_field(wp_unslash($_POST['key']));

    try {
      $act = MeprUpdateCtrl::activate_license($license_key);
      $li = get_site_transient('mepr_license_info');
      $output = sprintf('<div class="notice notice-success inline"><p>%s</p></div>', esc_html($act['message']));

      if(is_array($li)) {
        $editions = MeprUtils::is_incorrect_edition_installed();
        $automatic_updates = !empty($mepr_options->auto_updates) ? $mepr_options->auto_updates : 'all';

        if(is_array($editions) && $editions['license']['index'] > $editions['installed']['index'] && $automatic_updates != 'none') {
          // The installed plugin is a lower edition, try to upgrade to the higher license edition
          if(!empty($li['url']) && MeprUtils::is_url($li['url'])) {
            $result = self::install_plugin_silently($li['url'], array('overwrite_package' => true));

            if($result === true) {
              do_action('mepr_plugin_edition_changed');
              wp_send_json_success(true);
            }
          }
        }

        $output .= MeprView::get_string('/admin/options/active_license', get_defined_vars());
      }
      else {
        $output .= sprintf('<div class="notice notice-warning"><p>%s</p></div>', esc_html__('The license information is not available, try refreshing the page.', 'memberpress'));
      }

      wp_send_json_success($output);
    }
    catch(Exception $e) {
      try {
        $expires = MeprUpdateCtrl::send_mothership_request("/license_keys/expires_at/$license_key");

        if(isset($expires['expires_at'])) {
          $expires_at = strtotime($expires['expires_at']);

          if($expires_at && $expires_at < time()) {
            $licenses = MeprUpdateCtrl::send_mothership_request("/license_keys/list_keys/$license_key");

            if(!empty($licenses) && is_array($licenses)) {
              $highest_edition_index = -1;
              $highest_license = null;

              foreach($licenses as $license) {
                $edition = MeprUtils::get_edition($license['product_slug']);

                if(is_array($edition) && $edition['index'] > $highest_edition_index) {
                  $highest_edition_index = $edition['index'];
                  $highest_license = $license;
                }
              }

              if(is_array($highest_license)) {
                wp_send_json_error(
                  sprintf(
                    /* translators: %1$s: the product name, %2$s: open link tag, %3$s: close link tag */
                    esc_html__('This License Key has expired, but you have an active license for %1$s, %2$sclick here%3$s to activate using this license instead.', 'memberpress'),
                    '<strong>' . esc_html($highest_license['product_name']) . '</strong>',
                    sprintf('<a href="#" id="mepr-activate-new-license" data-license-key="%s">', esc_attr($highest_license['license_key'])),
                    '</a>'
                  )
                );
              }
            }
          }
        }
      }
      catch(Exception $ignore) {
        // Nothing we can do, let it fail.
      }

      wp_send_json_error($e->getMessage());
    }
  }

  public static function ajax_deactivate_license() {
    if(!MeprUtils::is_post_request()) {
      wp_send_json_error(sprintf(__('An error occurred during deactivation: %s', 'memberpress'), __('Bad request.', 'memberpress')));
    }

    if(!MeprUtils::is_logged_in_and_an_admin()) {
      wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    if(!check_ajax_referer('mepr_deactivate_license', false, false)) {
      wp_send_json_error(sprintf(__('An error occurred during deactivation: %s', 'memberpress'), __('Security check failed.', 'memberpress')));
    }

    $mepr_options = MeprOptions::fetch();
    $act = MeprUpdateCtrl::deactivate_license();

    $output = sprintf('<div class="notice notice-success"><p>%s</p></div>', esc_html($act['message']));
    $output .= MeprView::get_string('/admin/options/inactive_license', get_defined_vars());

    wp_send_json_success($output);
  }

  public static function install_plugin_silently($url, $args) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $skin = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);

    if(!$skin->request_filesystem_credentials(false, WP_PLUGIN_DIR)) {
      return new WP_Error('no_filesystem_access', __('Failed to get filesystem access', 'memberpress'));
    }

    return $upgrader->install($url, $args);
  }

  public static function ajax_install_license_edition() {
    if(!MeprUtils::is_post_request()) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('update_plugins')) {
      wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    if(!check_ajax_referer('mepr_install_license_edition', false, false)) {
      wp_send_json_error(__('Security check failed.', 'memberpress'));
    }

    $li = get_site_transient('mepr_license_info');

    if(!empty($li) && is_array($li) && !empty($li['url']) && MeprUtils::is_url($li['url'])) {
      $result = self::install_plugin_silently($li['url'], array('overwrite_package' => true));

      if($result instanceof WP_Error) {
        wp_send_json_error($result->get_error_message());
      }
      elseif($result === true) {
        do_action('mepr_plugin_edition_changed');
        wp_send_json_success(__('The correct edition of MemberPress has been installed successfully.', 'memberpress'));
      }
      else {
        wp_send_json_error(__('Failed to install the correct edition of MemberPress, please download it from memberpress.com and install it manually.', 'memberpress'));
      }
    }

    wp_send_json_error(__('License data not found', 'memberpress'));
  }
} //End class
