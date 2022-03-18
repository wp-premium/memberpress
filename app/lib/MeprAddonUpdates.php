<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprAddonUpdates {
  public $memberpress_active, $slug, $main_file, $options_key, $title, $desc, $path;

  public function __construct($slug, $main_file, $options_key='', $title='', $desc='') {
    $this->slug = $slug;
    $this->main_file = $main_file;
    $this->options_key = $options_key;
    $this->title = $title;
    $this->desc = $desc;
    $this->path = WP_PLUGIN_DIR.'/'.$slug;

    $this->load_language();

    add_filter('pre_set_site_transient_update_plugins', array( $this, 'queue_update' ));
    add_action("in_plugin_update_message-$main_file", array($this, 'check_incorrect_edition'));
    add_action('mepr_plugin_edition_changed', array($this, 'clear_update_transient'));
    add_action('mepr_license_activated_before_queue_update', array($this, 'clear_update_transient'));
    add_action('mepr_license_deactivated_before_queue_update', array($this, 'clear_update_transient'));

    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    $this->memberpress_active = is_plugin_active('memberpress/memberpress.php');
  }

  public function site_domain() {
    return preg_replace('#^https?://(www\.)?([^\?\/]*)#','$2',home_url());
  }

  public function queue_update($transient, $first_time_install=false) {
    if(!$first_time_install && empty($transient->checked)) { return $transient; }

    $addons_ctrl = MeprCtrlFactory::fetch('addons');
    $plugin_info = $addons_ctrl->curr_plugin_info($this->main_file);
    $installed_version = (!$first_time_install && isset($plugin_info['Version'])) ? $plugin_info['Version'] : '0.0.0';
    $update_info = get_site_transient('mepr_update_info_' . $this->slug);

    if(!is_array($update_info)) {
      $license = $this->license();

      if(empty($license)) {
        // Just here to query for the current version
        $args = array();
        if( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) { $args['edge'] = 'true'; }

        try {
          $version_info = $this->send_mothership_request( "/versions/latest/".$this->slug, $args );
          $curr_version = $version_info['version'];
          $download_url = '';
        }
        catch(Exception $e) {
          if(isset($transient->response[$this->main_file])) {
            unset($transient->response[$this->main_file]);
          }

          return $transient;
        }
      }
      else {
        try {
          $domain = urlencode($this->site_domain());
          $args = compact('domain');

          if( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) { $args['edge'] = 'true'; }
          $license_info = $this->send_mothership_request("/versions/info/".$this->slug."/{$license}", $args);
          $curr_version = $license_info['version'];
          $download_url = $license_info['url'];

          if(MeprUtils::is_incorrect_edition_installed()) {
            $download_url = '';
          }
        }
        catch(Exception $e) {
          try {
            // Just here to query for the current version
            $args = array();
            if( defined( "MEMBERPRESS_EDGE" ) && MEMBERPRESS_EDGE ) {
              $args['edge'] = 'true';
            }

            $version_info = $this->send_mothership_request("/versions/latest/".$this->slug, $args);
            $curr_version = $version_info['version'];
            $download_url = '';
          }
          catch(Exception $e) {
            if(isset($transient->response[$this->main_file])) {
              unset($transient->response[$this->main_file]);
            }

            return $transient;
          }
        }
      }

      set_site_transient(
        'mepr_update_info_' . $this->slug,
        compact('curr_version', 'download_url'),
        MeprUtils::hours(12)
      );
    }
    else {
      $curr_version = isset($update_info['curr_version']) ? $update_info['curr_version'] : $installed_version;
      $download_url = isset($update_info['download_url']) ? $update_info['download_url'] : '';
    }

    if(isset($curr_version) && version_compare($curr_version, $installed_version, '>')) {
      $transient->response[$this->main_file] = (object)array(
        'id'          => $curr_version,
        'slug'        => $this->slug,
        'plugin'      => $this->main_file,
        'new_version' => $curr_version,
        'url'         => 'https://memberpress.com/',
        'package'     => $download_url
      );
    }
    else {
      unset( $transient->response[$this->main_file] );
    }

    return $transient;
  }

  public function send_mothership_request( $endpoint,
                                           $args=array(),
                                           $method='get',
                                           $domain='https://mothership.caseproof.com',
                                           $blocking=true ) {
    $mepr_options = MeprOptions::fetch();
    $uri = $domain.$endpoint;

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
    if($blocking == false) { return true; }

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
        throw new Exception(__( 'Your License Key was invalid', 'memberpress'));
      }
    }

    return false;
  }

  public function manually_queue_update($first_time_install=false) {
    $transient = get_site_transient('update_plugins');
    set_site_transient('update_plugins', $this->queue_update($transient, $first_time_install));
  }

  private function license() {
    if( $this->memberpress_active ) {
      $mepr_options = MeprOptions::fetch();
      return $mepr_options->mothership_license;
    }
    else if(!empty($this->options_key)) {
      return get_option($this->options_key);
    }

    return false;
  }

  public function load_language() {
    $paths = array();
    $paths[] = str_replace(WP_PLUGIN_DIR, '', $this->path.'/i18n');

    //Have to use WP_PLUGIN_DIR because load_plugin_textdomain doesn't accept abs paths
    if(!file_exists(WP_PLUGIN_DIR . '/' . 'mepr-i18n')) {
      @mkdir(WP_PLUGIN_DIR . '/' . 'mepr-i18n');

      if(file_exists(WP_PLUGIN_DIR . '/' . 'mepr-i18n')) {
        $paths[] = '/mepr-i18n';
      }
    }
    else {
      $paths[] = '/mepr-i18n';
    }

    // MeprHooks isn't going to always be defined here so just use the normal apply_filters
    $paths = apply_filters("mepr_{$this->slug}_textdomain_paths", $paths);

    foreach($paths as $path) {
      load_plugin_textdomain($this->slug, false, $path);
    }
  }

  public function check_incorrect_edition() {
    if(MeprUtils::is_incorrect_edition_installed()) {
      printf(
        /* translators: %1$s: open link tag, %2$s: close link tag */
        ' <strong>' . esc_html__('To restore automatic updates, %1$sinstall the correct edition%2$s of MemberPress.', 'memberpress') . '</strong>',
        sprintf('<a href="%s">', esc_url(admin_url('admin.php?page=memberpress-options#mepr-license'))),
        '</a>'
      );
    }
  }

  public function clear_update_transient() {
    delete_site_transient('mepr_update_info_' . $this->slug);
  }
} //End class
