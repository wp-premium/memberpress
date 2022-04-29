<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprOptionsHelper {
  public static function wp_pages_dropdown($field_name, $page_id = 0, $auto_page = '', $blank_page = false) {
    $pages = MeprUtils::get_pages();
    $selected_page_id = (isset($_POST[$field_name])?$_POST[$field_name]:$page_id);

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-pages-dropdown">
      <?php if($blank_page): ?>
        <option value=""><?php _e('None', 'memberpress'); ?></option>
      <?php endif ?>
      <?php if(!empty($auto_page)) { ?>
        <option value="__auto_page:<?php echo $auto_page; ?>"><?php _e('- Auto Create New Page -', 'memberpress'); ?>&nbsp;</option>
      <?php } else { ?>
        <option>&nbsp;</option>
      <?php
        }
        foreach($pages as $page) {
          $selected = (((isset($_POST[$field_name]) and $_POST[$field_name] == $page->ID) or (!isset($_POST[$field_name]) and $page_id == $page->ID))?' selected="selected"':'');
          ?>
          <option value="<?php echo $page->ID; ?>" <?php echo $selected; ?>><?php echo $page->post_title; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php

    if($selected_page_id) {
        $permalink = MeprUtils::get_permalink($selected_page_id);
    ?>
&nbsp;<a href="<?php echo admin_url("post.php?post={$selected_page_id}&action=edit"); ?>" target="_blank" class="button"><?php _e('Edit', 'memberpress'); ?></a>
      <a href="<?php echo $permalink; ?>" target="_blank" class="button"><?php _e('View', 'memberpress'); ?></a>
    <?php
    }
  }

  public static function payment_types_dropdown($field_name, $payment_type) {
    $payment_types = array( 'paypal' => __('PayPal', 'memberpress'),
                            'manual' => __('Manual', 'memberpress') );

    $field_value = $_POST[$field_name];

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-payment-types-dropdown">
      <?php
        foreach($payment_types as $curr_type => $curr_label) {
          ?>
          <option value="<?php echo $curr_type; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $curr_type) or (!isset($_POST[$field_name]) and $payment_type == $curr_type))?' selected="selected"':''); ?>><?php echo $curr_label; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function payment_currencies_dropdown($field_name, $payment_currency) {
    $payment_currencies = MeprHooks::apply_filters('mepr-currency-symbols', array('$', 'US$', '£', '€', '¥', ' kr.', 'Kn', 'R$', '฿', '₹', 'zł', ' лв', ' Ft', 'Rp', 'R', '₪', '﷼', 'CHF', ' din.', ' дин.', 'KSh', 'RM', 'Rs', 'руб', '₽', 'NT$', 'Mex$', 'P', 'lei', 'JOD', '₺', 'S/.', '₱', 'د.إ', 'Kč', '₦', '₩', 'ل.د', '₫', 'ƒ', 'GH₵', 'S$', 'K'));
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-payment-currencies-dropdown">
      <?php
        foreach($payment_currencies as $curr_currency) {
          ?>
          <option value="<?php echo $curr_currency; ?>" <?php selected(($payment_currency == $curr_currency)); ?>><?php echo $curr_currency; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function payment_currency_code_dropdown($field_name, $code) {
    $codes = MeprHooks::apply_filters('mepr-currency-codes', array('USD', 'AED', 'AUD', 'AWG', 'BGN', 'BRL', 'BWP', 'CAD', 'CHF', 'CLP', 'CNY', 'COP', 'CVE', 'CZK', 'DKK', 'EUR', 'GBP', 'GHS', 'HKD', 'HRK', 'HUF', 'HUN', 'IDR', 'ILS', 'INR', 'ISK', 'JOD', 'JPY', 'KES', 'KRW', 'LYD', 'MMK', 'MXN', 'MYR', 'NGN', 'NOK', 'NZD', 'PEN', 'PHP', 'PKR', 'PLN', 'RON', 'RSD', 'RUB', 'SAR', 'SEK', 'SGD', 'THB', 'TRY', 'TWN', 'VND', 'ZAR', 'ZMW'));
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-payment-formats-dropdown">
      <?php
        foreach($codes as $curr_code) {
          ?>
          <option value="<?php echo $curr_code; ?>" <?php selected(($code == $curr_code)); ?>><?php echo $curr_code; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function payment_language_code_dropdown($field_name, $code) {
    $codes = MeprHooks::apply_filters('mepr-language-codes', array('US', 'AE', 'AR', 'AU', 'BG', 'BR', 'CH', 'CN', 'CO', 'CZ', 'DE', 'DK', 'EN', 'ES', 'FI', 'FR', 'GB', 'HE', 'HR', 'HU', 'ID', 'IS', 'IT', 'JP', 'KR', 'MS', 'MX', 'NL', 'NO', 'PE', 'PH', 'PL', 'PT', 'RO', 'RU', 'SE', 'SK', 'SR', 'SW', 'TH', 'TN', 'TR', 'TW', 'VI', 'ZA'));
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:null;

    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" class="mepr-dropdown mepr-language-code-dropdown">
      <?php
        foreach($codes as $curr_code) {
          ?>
          <option value="<?php echo $curr_code; ?>" <?php selected(($code == $curr_code)); ?>><?php echo $curr_code; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function gateways_dropdown($field_name, $curr_gateway, $obj_id) {
    $gateways = MeprGatewayFactory::all();
    $field_value = isset($_POST[$field_name])?$_POST[$field_name]:'';

    //Move Stripe Gateway to the top of the list
    if(isset($gateways['MeprStripeGateway'])) {
      $gateways = array_merge(array('MeprPayPalCommerceGateway' => $gateways['MeprPayPalCommerceGateway']), $gateways);
      $gateways = array_merge(array('MeprStripeGateway' => $gateways['MeprStripeGateway']), $gateways);
    }
    ?>
      <select name="<?php echo $field_name; ?>" id="<?php echo $field_name; ?>" data-id="<?php echo $obj_id; ?>" class="mepr-dropdown mepr-gateways-dropdown">
      <?php
        foreach($gateways as $gateway => $gateway_name) {
          if($gateway == 'MeprPayPalProGateway') { continue; } //Don't show PayPal Pro any more to new users

          if($gateway == 'MeprPayPalGateway') { continue; }

          if($gateway == 'MeprPayPalCommerceGateway') { continue; }

          if($gateway == 'MeprStripeGateway') { $gateway_name = __('Stripe (Recommended)', 'memberpress'); }

          if($gateway == 'MeprPayPalCommerceGateway') { $gateway_name = __('PayPal (Recommended)', 'memberpress'); }

          $obj = MeprGatewayFactory::fetch($gateway);

          ?>
          <option value="<?php echo $gateway; ?>" <?php echo (((isset($_POST[$field_name]) and $_POST[$field_name] == $gateway) or (!isset($_POST[$field_name]) and $curr_gateway == $gateway))?' selected="selected"':''); ?>><?php echo $gateway_name; ?>&nbsp;</option>
          <?php
        }
      ?>
      </select>
    <?php
  }

  public static function show_existing_custom_fields() {
    $mepr_options = MeprOptions::fetch();
    $blank_line = array((object)array('field_key'       => '',
                                      'field_name'      => '',
                                      'field_type'      => 'text',
                                      'default_value'   => '',
                                      'show_on_signup'  => false,
                                      'show_in_account' => true,
                                      'required'        => false,
                                      'options'         => array()));

    if(empty($mepr_options->custom_fields))
      $mepr_options->custom_fields = $blank_line;

    foreach($mepr_options->custom_fields as $line) {
      $random_id = rand(1, 100000000);

      if( in_array(
            $line->field_type,
            array('dropdown','multiselect','radios','checkboxes')
          ) ) {
        $hide = '';
      }
      else {
        $hide = 'style="display:none;"';
      }

      MeprView::render('/admin/options/custom_fields_row', get_defined_vars());
    }
  }

  /**
  * Radios for payment methods
  * @param MeprBaseRealGateway[] $payment_methods
  * @return string Radio HTML
  */
  public static function payment_methods_radios($payment_methods) {
    $mepr_options = MeprOptions::fetch();
    $field_name = 'mepr_payment_method';
    $radio_html = '';

    foreach($payment_methods as $payment_method) {
      $first = true;
      $label = self::payment_method_label($payment_method, $first);

      // This will ensure that the first pm is checked by default
      if($first) {
        if(!isset($_POST[$field_name])) {
          $_POST[$field_name] = $payment_method->id;
        }
        $first = false;
      }

      ob_start();
      ?>
      <label class="mepr-payment-option-label payment-option-<?php echo ! empty( $payment_method->key ) ? esc_attr( $payment_method->key ) : esc_attr( str_replace( ' ', '-', strtolower( $payment_method->name ) ) ); ?>">
        <input
          type="radio"
          name="<?php echo $field_name; ?>"
          class="mepr-form-radio"
          value="<?php echo $payment_method->id; ?>"
          data-payment-method-type="<?php echo $payment_method->name; ?>"
          <?php if(isset($_POST[$field_name])): checked($_POST[$field_name], $payment_method->id); endif ?> />
        <?php echo $label; ?>
      </label>
      <?php
      $radio_html .= ob_get_clean();
    }

    return $radio_html;
  }

  /**
  * Payment method descriptions and SPC forms
  * @param MeprBaseRealGateway[] $payment_methods
  * @return string Payment method descirptions and SPC forms HTML
  */
  public static function payment_methods_descriptions($payment_methods) {
    $mepr_options = MeprOptions::fetch();
    $field_name = 'mepr_payment_method';
    $desc_html = '';

    foreach($payment_methods as $payment_method) {
      $first = true;
      $desc = wpautop(esc_html(trim(stripslashes($payment_method->desc))));
      $desc = MeprHooks::apply_filters('mepr_signup_form_payment_description',  $desc,  $payment_method, $first);

      $desc_hidden = ($_POST[$field_name] === $payment_method->id ? '' : ' mepr-hidden');
      ob_start();
      ?>
        <div class="mepr-payment-method <?php echo "{$field_name}-{$payment_method->id}"; ?> mepr-payment-method-<?php echo $payment_method->key; ?>">
          <div class="mepr-payment-method-desc-text mp-pm-desc-<?php echo $payment_method->id; ?> spc <?php echo $desc_hidden; ?>">
            <?php echo wp_unslash($desc); ?>
          </div>
        </div>
      <?php
      $desc = ob_get_clean();

      $desc_html .= $desc;
    }

    return $desc_html;
  }

  /**
  * Payment methods icons
  * @param MeprBaseRealGateway[] $payment_methods
  * @return string Icon HTML
  */
  public static function payment_methods_icons($payment_methods) {
    $mepr_options = MeprOptions::fetch();
    $icons = array();
    $icon_html = '';
    foreach($payment_methods as $payment_method) {
      $first = true;
      $icon = $payment_method->icon;
      $name = $payment_method->name;
      // Ensure icons are unique
      if(in_array($icon, $icons)) {
        continue;
      }
      $icons[] = $icon;

      if($payment_method->use_icon && !empty($icon)) {
        $icon = '<span class="mepr-payment-method-icon"><img src="' . $icon . '" alt="' . $name . '" height="32px" /></span>';
      }
      else {
        $icon = '';
      }

      $icon_html .= MeprHooks::apply_filters('mepr_signup_form_payment_icon', $icon,  $payment_method, $first);
      $first = false;
    }

    return $icon_html;
  }

  /**
  * Label for payment method
  * @param Object $payment_method
  * @param bool $first
  * @return string Label HTML
  */
  private static function payment_method_label($payment_method, $first) {
    $label = esc_html(trim($payment_method->label));

    if($payment_method->use_label && !empty($label)) {
      $label = '<span class="mepr-payment-method-label-text">' . $label . '</span>';
    }
    else {
      $label = '';
    }

    return MeprHooks::apply_filters('mepr_signup_form_payment_label', $label, $payment_method, $first);
  }

  public static function payment_methods_dropdown($field_name, $pms = false) {
    $mepr_options = MeprOptions::fetch();
    $pms = $pms ? $pms : array_keys($mepr_options->integrations);
    $pms = MeprHooks::apply_filters('mepr_options_helper_payment_methods',$pms,$field_name);

    if(count($pms) == 0):
      return false;
    elseif(count($pms) == 1):
      $pm_id = array_shift($pms);
      $obj = $mepr_options->payment_method($pm_id);

      if($obj instanceof MeprBaseRealGateway):
        ?>
          <input type="hidden" name="<?php echo $field_name; ?>" value="<?php echo $obj->id; ?>" />
        <?php
      else:
        return false;
      endif;
    elseif(count($pms) > 1):
    ?><div class="mp-form-row <?php echo esc_attr($field_name); ?>-wrapper"><?php
        $first = true;
        foreach($pms as $pm_id):
          $obj = $mepr_options->payment_method($pm_id);
          $label = esc_html(trim($obj->label));
          $desc = wpautop(trim(stripslashes($obj->desc)));

          // This will ensure that the first pm is checked by default
          if( $first ) {
            $first = false;
            if(!isset($_POST[$field_name])) {
              $_POST[$field_name] = $obj->id;
            }
          }

          if($obj->use_icon && !empty($obj->icon)) {
            $icon = '<span class="mepr-payment-method-icon"><img src="'.$obj->icon.'" alt="'.$label.'" height="32px" /></span>';
          }
          else {
            $icon = '';
          }

          if($obj->use_label && !empty($label)) {
            $label = '<span class="mepr-payment-method-label-text">'.$label.'</span>';
          }
          else {
            $label = '';
          }

          $icon   = MeprHooks::apply_filters('mepr_signup_form_payment_icon',         $icon,  $obj, $first);
          $label  = MeprHooks::apply_filters('mepr_signup_form_payment_label',        $label, $obj, $first);
          $desc   = MeprHooks::apply_filters('mepr_signup_form_payment_description',  $desc,  $obj, $first);

          if($obj->use_desc && !empty($desc)) {
            $desc_hidden = ($_POST[$field_name]==$obj->id ? '' : ' mepr-hidden');
            $desc = '<div class="mepr-payment-method-desc-text mp-pm-desc-' . $obj->id . $desc_hidden . '">'.$desc.'</div>';
          }
          else {
            $desc = '';
          }

          if($obj instanceof MeprBaseRealGateway):
            ?>
            <div class="mp-form-row mepr_payment_method">
              <div class="mepr-payment-method <?php echo "{$field_name}-{$obj->id}"; ?>">
                <div class="mepr-payment-method-label">
                  <label class="mp-checkbox-field">
                    <input
                      type="radio"
                      name="<?php echo $field_name; ?>"
                      class="mepr-form-radio"
                      value="<?php echo $obj->id; ?>"
                      <?php if(isset($_POST[$field_name])): checked($_POST[$field_name], $obj->id); endif ?> />
                    <?php echo $label.$icon; ?>
                  </label>
                  <?php echo $desc; ?>
                </div>
              </div>
            </div>
            <?php
          endif;
        endforeach;
    ?></div><?php
    endif;
  }

  public static function format_plaintext_email($text) {
    // Don't be alarmed, inline styles are required in emails
    return '<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">' . wpautop(make_clickable($text), true) . '</div>';
  }

  public static function display_show_excerpts_dropdown($excerpt_type_str, $excerpt_type, $excerpt_size_str, $excerpt_size, $global=false) {
    ?>
    <div>
      <?php if($global): ?>
        <span><?php _e('Choose the excerpt type:', 'memberpress'); ?></span>
      <?php else: ?>
        <p><strong><?php _e('Excerpts:', 'memberpress'); ?></strong></p>
      <?php endif; ?>

      <?php if(!$global): ?>
        <div class="mepr-sub-pane">
      <?php endif; ?>

        <select id="<?php echo $excerpt_type_str; ?>" name="<?php echo $excerpt_type_str; ?>">
          <?php if(!$global): ?>
            <option value="default"<?php selected('default',$excerpt_type); ?>><?php _e('Default', 'memberpress'); ?></option>
            <option value="hide"<?php selected('hide',$excerpt_type); ?>><?php _e('Hide', 'memberpress'); ?></option>
          <?php endif; ?>
          <option value="more"<?php selected('more',$excerpt_type); ?>><?php _e('More Tag', 'memberpress'); ?></option>
          <option value="excerpt"<?php selected('excerpt',$excerpt_type); ?>><?php _e('Post Excerpt', 'memberpress'); ?></option>
          <option value="custom"<?php selected('custom',$excerpt_type); ?>><?php _e('Custom', 'memberpress'); ?></option>
        </select>

        <div id="<?php echo $excerpt_type_str; ?>-size" class="mepr-hidden">
          <div>&nbsp;</div>
          <i class="mp-icon-level-down-1"></i>
          <?php
            ob_start();
            ?>
            <input type="text" name="<?php echo $excerpt_size_str; ?>" id="<?php echo $excerpt_size_str; ?>" size="5" value="<?php echo $excerpt_size; ?>" />
            <?php
            $excerpt_size_input = ob_get_clean();
          ?>
          <span><?php printf(__('Show the first %s characters of your content', 'memberpress'), $excerpt_size_input); ?></span>
        </div>

      <?php if(!$global): ?>
        </div>
      <?php endif; ?>

    </div>
    <?php
  }

  public static function display_unauth_message_dropdown($message_type_str, $message_type, $message_str, $message) {
    ?>
    <div>
      <p><strong><?php _e('Unauthorized Message:', 'memberpress'); ?></strong></p>
      <div class="mepr-sub-pane">
        <select id="<?php echo $message_type_str; ?>" name="<?php echo $message_type_str; ?>">
          <option value="default"<?php selected('default',$message_type); ?>><?php _e('Default', 'memberpress'); ?></option>
          <option value="hide"<?php selected('hide',$message_type); ?>><?php _e('Hide', 'memberpress'); ?></option>
          <option value="custom"<?php selected('custom',$message_type); ?>><?php _e('Custom', 'memberpress'); ?></option>
        </select>
        <div id="<?php echo $message_type_str; ?>-editor" class="mepr-hidden mepr-sub-pane">
          <br/>
          <p class="description"><?php _e('Enter your custom unauthorized message here:', 'memberpress'); ?></p>
          <?php wp_editor($message, $message_str); ?>
        </div>
      </div>
    </div>
    <?php
  }

  public static function display_unauth_login_dropdown($login_str, $login) {
    ?>
    <div>
      <p><strong><?php _e('Login Form:', 'memberpress'); ?></strong></p>
      <div class="mepr-sub-pane">
        <select id="<?php echo $login_str; ?>" name="<?php echo $login_str; ?>">
          <option value="default"<?php selected('default',$login); ?>><?php _e('Default', 'memberpress'); ?></option>
          <option value="show"<?php selected('show',$login); ?>><?php _e('Show', 'memberpress'); ?></option>
          <option value="hide"<?php selected('hide',$login); ?>><?php _e('Hide', 'memberpress'); ?></option>
        </select>
      </div>
    </div>
    <?php
  }

  public static function admin_privacy_settings_link() {
    global $wp_version;

    if(version_compare($wp_version, '5.3', '<')) {
      return is_multisite() ? network_admin_url('privacy.php') : admin_url('privacy.php');
    }

    return admin_url('options-privacy.php');
  }
} //End class
