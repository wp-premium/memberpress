<?php

class MeprDeactivationSurveyCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    if(apply_filters('mepr_deactivation_survey_skip', $this->is_dev_url())) {
      return;
    }

    add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    add_action('admin_footer', array($this, 'popup'));
  }

  protected function is_dev_url() {
    $url          = network_site_url( '/' );
    $is_local_url = false;

    // Trim it up
    $url = strtolower( trim( $url ) );

    // Need to get the host...so let's add the scheme so we can use parse_url
    if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
      $url = 'http://' . $url;
    }
    $url_parts = parse_url( $url );
    $host      = ! empty( $url_parts['host'] ) ? $url_parts['host'] : false;
    if ( ! empty( $url ) && ! empty( $host ) ) {
      if ( false !== ip2long( $host ) ) {
        if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
          $is_local_url = true;
        }
      } else if ( 'localhost' === $host ) {
        $is_local_url = true;
      }

      $tlds_to_check = array( '.dev', '.local', ':8888' );
      foreach ( $tlds_to_check as $tld ) {
        if ( false !== strpos( $host, $tld ) ) {
          $is_local_url = true;
          continue;
        }

      }
      if ( substr_count( $host, '.' ) > 1 ) {
        $subdomains_to_check =  array( 'dev.', '*.staging.', 'beta.', 'test.' );
        foreach ( $subdomains_to_check as $subdomain ) {
          $subdomain = str_replace( '.', '(.)', $subdomain );
          $subdomain = str_replace( array( '*', '(.)' ), '(.*)', $subdomain );
          if ( preg_match( '/^(' . $subdomain . ')/', $host ) ) {
            $is_local_url = true;
            continue;
          }
        }
      }
    }

    return $is_local_url;
  }

  public function enqueue() {
    if(!$this->is_plugin_page()) {
      return;
    }

    wp_enqueue_style('mepr-deactivation-survey', MEPR_CSS_URL . '/admin-deactivation-survey.css', array(), MEPR_VERSION);
    wp_enqueue_script('mepr-deactivation-survey', MEPR_JS_URL . '/admin_deactivation_survey.js', array('jquery'), MEPR_VERSION, true);

    wp_localize_script('mepr-deactivation-survey', 'MeprDeactivationSurvey', array(
      'slug' => MEPR_PLUGIN_NAME,
      'pleaseSelectAnOption' => __('Please select an option', 'memberpress'),
      'siteUrl' => site_url(),
      'apiUrl' => 'https://hooks.zapier.com/hooks/catch/43914/otu86c9/silent/'
    ));
  }

  public function popup() {
    if(!$this->is_plugin_page()) {
      return;
    }

    $plugin = MEPR_PLUGIN_NAME;

    $options = array(
      1 => array(
        'label'   => __('I no longer need the plugin', 'memberpress'),
      ),
      2 => array(
        'label'   => __('I\'m switching to a different plugin', 'memberpress'),
        'details' => __('Please share which plugin', 'memberpress'),
      ),
      3 => array(
        'label'   => __('I couldn\'t get the plugin to work', 'memberpress'),
      ),
      4 => array(
        'label'   => __('It\'s a temporary deactivation', 'memberpress'),
      ),
      5 => array(
        'label'   => __('Other', 'memberpress'),
        'details' => __('Please share the reason', 'memberpress'),
      )
    );

    MeprView::render('/admin/popups/deactivation_survey', compact('plugin', 'options'));
  }

  protected function is_plugin_page() {
    return in_array(MeprUtils::get_current_screen_id(), array('plugins', 'plugins-network'));
  }
}
