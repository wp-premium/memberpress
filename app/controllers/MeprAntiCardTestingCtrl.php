<?php

class MeprAntiCardTestingCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('mepr_display_general_options', array($this, 'display_options'));
    add_action('mepr_stripe_payment_failed', array($this, 'record_payment_failure'));
    add_action('mepr_stripe_before_confirm_payment', array($this, 'maybe_block_checkout_ajax'));
    add_action('wp_ajax_mepr_anti_card_testing_get_ip', array($this, 'get_detected_ip_ajax'));
  }

  public function display_options() {
    $mepr_options = MeprOptions::fetch();
    ?>
    <h3><?php esc_html_e('Card Testing Protection', 'memberpress'); ?></h3>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label for="<?php echo esc_attr($mepr_options->anti_card_testing_enabled_str); ?>"><?php esc_html_e('Enable Card Testing Protection', 'memberpress'); ?></label>
            <?php
              MeprAppHelper::info_tooltip(
                $mepr_options->anti_card_testing_enabled_str,
                __('Enable Card Testing Protection', 'memberpress'),
                sprintf(
                  // translators: %1$s: br tag
                  __('Card testing is a type of fraudulent activity where someone tries to determine if stolen card information can be used to make purchases, by repeatedly attempting a purchase with different card numbers until one succeeds.%1$s%1$sBy enabling this protection, MemberPress will permanently block any further payment attempts by any user that has had 5 failed payments in a 2 hour window.', 'memberpress'),
                  '<br>'
                )
              );
            ?>
          </th>
          <td>
            <input type="checkbox" name="<?php echo esc_attr($mepr_options->anti_card_testing_enabled_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_enabled_str); ?>" <?php checked($mepr_options->anti_card_testing_enabled); ?> class="mepr-toggle-checkbox" data-box="mepr-anti-card-testing-box" />
          </td>
        </tr>
      </tbody>
    </table>
    <div class="mepr-sub-box-white mepr-hidden mepr-anti-card-testing-box">
      <div class="mepr-arrow mepr-white mepr-up mepr-sub-box-arrow"> </div>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <?php esc_html_e( 'How To Get Visitor IP?', 'memberpress' ); ?>
              <?php
                MeprAppHelper::info_tooltip(
                  $mepr_options->anti_card_testing_ip_method_str,
                  __('How To Get Visitor IP?', 'memberpress'),
                  sprintf(
                    // translators: %1$s: br tag, %2$s: open link tag, %3$s: close link tag
                    __('Which method should MemberPress use to retrieve the visitor\'s IP address?%1$s%1$sIt\'s important to use a method that is compatible with your site. The REMOTE_ADDR method is the most secure but may not be correct if your site is using a front-end proxy.%1$s%1$sCompare the displayed detected IP address with what is displayed on %2$sthis site%3$s to find the correct method for your site.', 'memberpress'),
                    '<br>',
                    '<a href="https://whatismyipaddress.com/" target="_blank">',
                    '</a>'
                  )
                );
              ?>
            </th>
            <td>
              <p>
                <input type="radio" name="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_default" value="" <?php checked($mepr_options->anti_card_testing_ip_method, ''); ?>>
                <label for="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_default">
                  <?php
                    printf(
                      // translators: %1$s: open strong tag, %2$s: close strong tag
                      esc_html__('%1$sDefault%2$s - Compatible with most sites, but not as secure as the methods below.', 'memberpress'),
                      '<strong>',
                      '</strong>'
                    );
                  ?>
                </label>
              </p>
              <p>
                <input type="radio" name="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_remote_addr" value="REMOTE_ADDR" <?php checked($mepr_options->anti_card_testing_ip_method, 'REMOTE_ADDR'); ?>>
                <label for="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_remote_addr">
                  <?php
                    printf(
                      // translators: %1$s: open strong tag, %2$s: close strong tag
                      esc_html__('%1$sUse PHP\'s built-in REMOTE_ADDR%2$s - The most secure method if this is compatible with your site.', 'memberpress'),
                      '<strong>',
                      '</strong>'
                    );
                  ?>
                </label>
              </p>
              <p>
                <input type="radio" name="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_x_forwarded_for" value="HTTP_X_FORWARDED_FOR" <?php checked($mepr_options->anti_card_testing_ip_method, 'HTTP_X_FORWARDED_FOR'); ?>>
                <label for="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_x_forwarded_for">
                  <?php
                    printf(
                      // translators: %1$s: open strong tag, %2$s: close strong tag
                      esc_html__('%1$sUse the X-Forwarded-For HTTP header%2$s - Only use this if you\'re using a front-end proxy or spoofing may result.', 'memberpress'),
                      '<strong>',
                      '</strong>'
                    );
                  ?>
                </label>
              </p>
              <p>
                <input type="radio" name="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_x_real_ip" value="HTTP_X_REAL_IP" <?php checked($mepr_options->anti_card_testing_ip_method, 'HTTP_X_REAL_IP'); ?>>
                <label for="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_x_real_ip">
                  <?php
                    printf(
                      // translators: %1$s: open strong tag, %2$s: close strong tag
                      esc_html__('%1$sUse the X-Real-IP HTTP header%2$s - Only use this if you\'re using a front-end proxy or spoofing may result.', 'memberpress'),
                      '<strong>',
                      '</strong>'
                    );
                  ?>
                </label>
              </p>
              <p>
                <input type="radio" name="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_cf_connecting_ip" value="HTTP_CF_CONNECTING_IP" <?php checked($mepr_options->anti_card_testing_ip_method, 'HTTP_CF_CONNECTING_IP'); ?>>
                <label for="<?php echo esc_attr($mepr_options->anti_card_testing_ip_method_str); ?>_cf_connecting_ip">
                  <?php
                    printf(
                      // translators: %1$s: open strong tag, %2$s: close strong tag
                      esc_html__('%1$sUse the CF-Connecting-IP HTTP header%2$s - Only use this if you\'re using Cloudflare.', 'memberpress'),
                      '<strong>',
                      '</strong>'
                    );
                  ?>
                </label>
              </p>
              <p class="mepr-detected-ip-address-p"><?php esc_html_e('Detected IP address using the selected method:', 'memberpress'); ?><code id="mepr-detected-ip-address"><?php echo esc_html(self::get_ip()); ?></code></p>
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="<?php echo esc_attr($mepr_options->anti_card_testing_blocked_str); ?>"><?php esc_html_e('Blocked IP Addresses', 'memberpress'); ?></label>
              <?php
                MeprAppHelper::info_tooltip(
                  $mepr_options->anti_card_testing_blocked_str,
                  __('Blocked IP Addresses', 'memberpress'),
                  sprintf(
                    // translators: %1$s: br tag
                    __('The IP addresses listed here are currently banned from making purchases.%1$s%1$sYou can add a new IP address (one per line) to block it, or remove an IP address to unblock it.', 'memberpress'),
                    '<br>'
                  )
                );
              ?>
            </th>
            <td>
              <textarea name="<?php echo esc_attr($mepr_options->anti_card_testing_blocked_str); ?>" id="<?php echo esc_attr($mepr_options->anti_card_testing_blocked_str); ?>"><?php echo esc_textarea(join("\n", $mepr_options->anti_card_testing_blocked)); ?></textarea>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php
  }

  /**
   * Get the visitor's IP address
   *
   * @param string|null $method The IP retrieval method to use or null to use the saved method
   * @return string
   */
  public static function get_ip($method = null) {
    $mepr_options = MeprOptions::fetch();
    $connection_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    $ips = [];

    if(is_null($method)) {
      $method = $mepr_options->anti_card_testing_ip_method;
    }

    if(empty($method)) {
      $headers = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP'
      );

      foreach($headers as $header) {
        if(isset($_SERVER[$header])) {
          $ips[] = $_SERVER[$header];
        }
      }
    }
    elseif(isset($_SERVER[$method])) {
      $ips[] = $_SERVER[$method];
    }

    $ips[] = $connection_ip;

    $ip = self::get_client_ip_from_ips($ips);

    if(is_null($ip)) {
      $ip = $connection_ip;
    }

    return apply_filters('mepr_anti_card_testing_ip', $ip);
  }

  /**
   * Get the first valid public IP from the given array
   *
   * @param array $ips The array of IP addresses to check
   * @return string|null
   */
  private static function get_client_ip_from_ips($ips) {
    foreach($ips as $ip) {
      $skip_to_next = false;

      foreach(array(',', ' ', "\t") as $char) {
        if(strpos($ip, $char) !== false) {
          $parts = explode($char, $ip);
          $parts = array_reverse($parts);

          foreach($parts as $part) {
            $part = trim($part);

            if(self::is_valid_ip_address($part) && !self::is_private_ip_address($part)) {
              return $part;
            }
          }

          $skip_to_next = true;
          break;
        }
      }

      if($skip_to_next) {
        continue; // this one had a delimiter and we didn't find anything
      }

      if(self::is_valid_ip_address($ip) && !self::is_private_ip_address($ip)) {
        return $ip;
      }
    }

    return null;
  }

  /**
   * Is the given IP address valid?
   *
   * @param string $ip
   * @return bool
   */
  private static function is_valid_ip_address($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
  }

  /**
   * Is the given IP address private?
   *
   * @param string $ip
   * @return bool
   */
  private static function is_private_ip_address($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false
      && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
  }

  public function record_payment_failure() {
    $mepr_options = MeprOptions::fetch();

    if(!$mepr_options->anti_card_testing_enabled) {
      return;
    }

    $ip = self::get_ip();

    if($ip && !self::is_private_ip_address($ip)) {
      $failed = (int) get_transient("mepr_failed_payments_$ip");
      set_transient("mepr_failed_payments_$ip", $failed + 1, MeprHooks::apply_filters('mepr_card_testing_timeframe', 2 * HOUR_IN_SECONDS));
    }
  }

  public function maybe_block_checkout_ajax() {
    $mepr_options = MeprOptions::fetch();

    if(!$mepr_options->anti_card_testing_enabled) {
      return;
    }

    $ip = self::get_ip();

    if($ip && !self::is_private_ip_address($ip)) {
      $mepr_options = MeprOptions::fetch();
      $failed = (int) get_transient("mepr_failed_payments_$ip");
      $blocked_ips = $mepr_options->anti_card_testing_blocked;

      if(!is_array($blocked_ips)) {
        $blocked_ips = array();
      }

      // If there have been 5 or more failed payments, add to permanently banned IPs
      if($failed >= MeprHooks::apply_filters('mepr_card_testing_failure_limit', 5) && !in_array($ip, $blocked_ips, true)) {
        $blocked_ips[] = $ip;
        $mepr_options->anti_card_testing_blocked = $blocked_ips;
        $mepr_options->store(false);
      }

      // Display an error and block the payment if this IP is permanently banned
      if(in_array($ip, $blocked_ips, true)) {
        wp_send_json(array(
          'error' => __('We are not able to complete your purchase at this time. Please contact us for more information.', 'memberpress'),
          'destroy_payment_method' => true
        ));
      }
    }
  }

  public function get_detected_ip_ajax() {
    if(!MeprUtils::is_logged_in_and_an_admin() || !isset($_GET['method']) || !is_string($_GET['method'])) {
      wp_send_json_error();
    }

    $valid_methods = array('', 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP');
    $method = in_array($_GET['method'], $valid_methods, true) ? $_GET['method'] : '';

    wp_send_json_success(self::get_ip($method));
  }
}
