<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprUsage {
  public function uuid($regenerate=false) {
    $uuid_key = 'mepr-usage-uuid';
    $uuid = get_option($uuid_key);

    if($regenerate || empty($uuid)) {
      // Definitely not cryptographically secure but
      // close enough to provide an unique id
      $uuid = md5(uniqid().site_url());
      update_option($uuid_key,$uuid);
    }

    return $uuid;
  }

  public function snapshot() {
    global $wpdb, $mepr_update;

    $mepr_options = MeprOptions::fetch();

    $txn_stats = MeprReports::transaction_stats();
    $sub_stats = MeprReports::subscription_stats();

    $last_week = MeprUtils::ts_to_mysql_date(time()-MeprUtils::weeks(1));
    $last_month = MeprUtils::ts_to_mysql_date(time()-MeprUtils::days(30));
    $last_year = MeprUtils::ts_to_mysql_date(time()-MeprUtils::days(365));

    $weekly_txn_stats = MeprReports::transaction_stats($last_week);
    $weekly_sub_stats = MeprReports::subscription_stats($last_week);
    $weekly_refund_stats = MeprReports::refund_event_stats($last_week);
    $weekly_cancel_stats = MeprReports::cancel_event_stats($last_week);

    $monthly_txn_stats = MeprReports::transaction_stats($last_month);
    $monthly_sub_stats = MeprReports::subscription_stats($last_month);
    $monthly_refund_stats = MeprReports::refund_event_stats($last_month);
    $monthly_cancel_stats = MeprReports::cancel_event_stats($last_month);

    $yearly_txn_stats = MeprReports::transaction_stats($last_year);
    $yearly_sub_stats = MeprReports::subscription_stats($last_year);
    $yearly_refund_stats = MeprReports::refund_event_stats($last_year);
    $yearly_cancel_stats = MeprReports::cancel_event_stats($last_year);

    $snap = array(
      'uuid'               => $this->uuid(),
      'mp_version'         => MEPR_VERSION,
      'php_version'        => phpversion(),
      'mysql_version'      => $wpdb->db_version(),
      'os'                 => php_uname('s'),
      'webserver'          => $_SERVER["SERVER_SOFTWARE"],
      'active_license'     => MeprUpdateCtrl::is_activated(),
      'edition'            => MEPR_EDITION,
      'all_users'          => MeprReports::get_total_wp_users_count(),
      'all_members'        => MeprReports::get_total_members_count(),
      'active_members'     => MeprReports::get_active_members_count(),
      'inactive_members'   => MeprReports::get_inactive_members_count(),
      'free_members'       => MeprReports::get_free_active_members_count(),
      'timestamp'          => gmdate('c'),
      'memberships'        => $this->memberships(),
      'plugins'            => $this->plugins(),
      'options'            => $this->options(),
      'gateways'           => $this->gateways(),
      'ltv'                => MeprReports::get_average_lifetime_value(),
      //'mrr'                => '',
      //'arr'                => '',
      'currency'           => $mepr_options->currency_code,
      'is_multisite'       => is_multisite()
    );

    if(!empty($weekly_txn_stats)) {
      $snap['week_revenue'] = $weekly_txn_stats->complete_sum_total;
      $snap['week_transactions'] = $weekly_txn_stats->complete;
    }

    if(!empty($monthly_txn_stats)) {
      $snap['month_revenue'] = $monthly_txn_stats->complete_sum_total;
      $snap['month_transactions'] = $monthly_txn_stats->complete;
    }

    if(!empty($yearly_txn_stats)) {
      $snap['year_revenue'] = $yearly_txn_stats->complete_sum_total;
      $snap['year_transactions'] = $yearly_txn_stats->complete;
    }

    if(!empty($sub_stats)) {
      $snap['subscriptions'] = $sub_stats->active;
    }

    if(!empty($weekly_sub_stats)) {
      $snap['week_subscriptions'] = $weekly_sub_stats->active;
    }

    if(!empty($monthly_sub_stats)) {
      $snap['month_subscriptions'] = $monthly_sub_stats->active;
    }

    if(!empty($yearly_sub_stats)) {
      $snap['year_subscriptions'] = $yearly_sub_stats->active;
    }

    if(!empty($weekly_refund_stats)) {
      $snap['week_refunds'] = $weekly_refund_stats->obj_count;
      $snap['week_refunds_total'] = $weekly_refund_stats->obj_total;
    }

    if(!empty($weekly_cancel_stats)) {
      $snap['week_cancellations'] = $weekly_cancel_stats->obj_count;
      $snap['week_cancellations_total'] = $weekly_cancel_stats->obj_total;
    }

    if(!empty($monthly_refund_stats)) {
      $snap['month_refunds'] = $monthly_refund_stats->obj_count;
      $snap['month_refunds_total'] = $monthly_refund_stats->obj_total;
    }

    if(!empty($monthly_cancel_stats)) {
      $snap['month_cancellations'] = $monthly_cancel_stats->obj_count;
      $snap['month_cancellations_total'] = $monthly_cancel_stats->obj_total;
    }

    if(!empty($yearly_refund_stats)) {
      $snap['year_refunds'] = $yearly_refund_stats->obj_count;
      $snap['year_refunds_total'] = $yearly_refund_stats->obj_total;
    }

    if(!empty($yearly_cancel_stats)) {
      $snap['year_cancellations'] = $yearly_cancel_stats->obj_count;
      $snap['year_cancellations_total'] = $yearly_cancel_stats->obj_total;
    }

    if(!empty($txn_stats)) {
      $snap['transactions'] = $txn_stats->complete;
      $snap['lifetime_processed_total'] = $txn_stats->complete_sum_total + $txn_stats->refunded_sum_total;
      $snap['lifetime_refunds_total'] = $txn_stats->refunded_sum_total;
    }

    return MeprHooks::apply_filters('mepr_usage_snapshot', $snap);
  }

  private function memberships() {
    global $wpdb;
    $mepr_db = MeprDb::fetch();

    $objs = MeprProduct::get_all();

    //$q = $wpdb->prepare("
    //    SELECT COUNT(*)
    //      FROM {$mepr_db->transactions}
    //     WHERE status=%s
    //       AND txn_type=%s
    //       AND created_at >= %s
    //  ",
    //  'complete',
    //  'payment',
    //  MeprUtils::ts_to_mysql_date(time()-MeprUtils::weeks(1))
    //);

    $memberships = array();
    foreach($objs as $obj) {
      //$mq = $q . $wpdb->prepare(" AND product_id=%d", $obj->ID);
      $memberships[] = array(
        'amount'                     => $obj->price,
        'recurring'                  => !$obj->is_one_time_payment(),
        'period_type'                => $obj->period_type,
        'period'                     => $obj->period,
        'trial'                      => $obj->trial,
        'limit_cycles'               => $obj->limit_cycles,
        'tax_exempt'                 => get_option('mepr_calculate_taxes') ? $obj->is_tax_exempt() : null,
        'thank_you_page_enabled'     => (bool) $obj->thank_you_page_enabled,
        'thank_you_page_type'        => $obj->thank_you_page_type,
        'welcome_email_enabled'      => !empty($obj->emails['MeprUserProductWelcomeEmail']['enabled']),
        'customize_payment_methods'  => (bool) $obj->customize_payment_methods,
        'customize_profile_fields'   => (bool) $obj->customize_profile_fields,
        'simultaneous_subscriptions' => (bool) $obj->simultaneous_subscriptions,
        'who_can_purchase'           => $this->who_can_purchase($obj),
        'is_highlighted'             => (bool) $obj->is_highlighted,
        'pricing_title'              => $obj->pricing_title != $obj->post_title,
        'pricing_display'            => $obj->pricing_display,
        'pricing_heading_txt'        => !empty($obj->pricing_heading_txt),
        'pricing_benefits'           => !empty($obj->pricing_benefits[0]),
        'pricing_footer_txt'         => !empty($obj->pricing_footer_txt),
        'pricing_button_txt'         => $obj->pricing_button_txt != __('Sign Up', 'memberpress'),
        'pricing_button_position'    => $obj->pricing_button_position,
        'access_url'                 => !empty($obj->access_url),
        'register_price_action'      => $obj->register_price_action,
        'custom_login_urls_enabled'  => (bool) $obj->custom_login_urls_enabled,
        'custom_login_urls_default'  => !empty($obj->custom_login_urls_default),
        'custom_login_urls'          => is_array($obj->custom_login_urls) && count($obj->custom_login_urls)
        //'weekly_transactions' => $wpdb->get_var($mq),
      );
    }

    return $memberships;
  }

  /**
   * Returns a comma-separated list of user types who can purchase the given product
   *
   * @param  MeprProduct $product
   * @return string
   */
  private function who_can_purchase($product) {
    $who_can_purchase = '';

    if(is_array($product->who_can_purchase)) {
      $user_types = array();

      foreach($product->who_can_purchase as $who) {
        if(isset($who->user_type)) {
          $user_types[] = $who->user_type;
        }
      }

      $who_can_purchase = join(', ', $user_types);
    }

    return $who_can_purchase;
  }

  private function plugins() {
    $plugin_list = get_plugins();
    wp_cache_delete('plugins', 'plugins');

    $plugins = array();
    foreach($plugin_list as $slug => $info) {
      $plugins[] = array(
        'name'        => $info['Name'],
        'slug'        => $slug,
        'version'     => $info['Version'],
        'active'      => is_plugin_active($slug),
        //'plugin_uri'  => $info['PluginURI'],
        //'description' => $info['Description'],
        //'author'      => $info['Author'],
        //'author_uri'  => $info['AuthorURI'],
        //'text_domain' => $info['TextDomain'],
        //'domain_path' => $info['DomainPath'],
        //'network'     => $info['Network'],
        //'title'       => $info['Title'],
        //'author_name' => $info['AuthorName'],
      );
    }

    return $plugins;
  }

  private function options() {
    $mepr_options = MeprOptions::fetch();

    $options = array(
      'redirect_on_unauthorized' => $mepr_options->redirect_on_unauthorized,
      'redirect_method' => $mepr_options->redirect_method,
      'redirect_non_singular' => $mepr_options->redirect_non_singular,
      'unauth_show_excerpts' => $mepr_options->unauth_show_excerpts,
      'unauth_excerpt_type' => $mepr_options->unauth_excerpt_type,
      'unauth_excerpt_size' => $mepr_options->unauth_excerpt_size,
      'unauth_show_login' => $mepr_options->unauth_show_login,
      'disable_wp_admin_bar' => $mepr_options->disable_wp_admin_bar,
      'lock_wp_admin' => $mepr_options->lock_wp_admin,
      'allow_cancel_subs' => $mepr_options->allow_cancel_subs,
      'allow_suspend_subs' => $mepr_options->allow_suspend_subs,
      'enforce_strong_password' => $mepr_options->enforce_strong_password,
      'disable_wp_registration_form' => $mepr_options->disable_wp_registration_form,
      'coupon_field_enabled' => $mepr_options->coupon_field_enabled,
      'username_is_email' => $mepr_options->username_is_email,
      'pro_rated_upgrades' => $mepr_options->pro_rated_upgrades,
      'disable_grace_init_days' => $mepr_options->disable_grace_init_days,
      'disable_checkout_password_fields' => $mepr_options->disable_checkout_password_fields,
      'require_tos' => $mepr_options->require_tos,
      'enable_spc' => $mepr_options->enable_spc,
      'enable_spc_invoice' => $mepr_options->enable_spc_invoice,
      'require_privacy_policy' => $mepr_options->require_privacy_policy,
      'force_login_page_url' => $mepr_options->force_login_page_url,
      'show_fields_logged_in_purchases' => $mepr_options->show_fields_logged_in_purchases,
      'show_fname_lname' => $mepr_options->show_fname_lname,
      'require_fname_lname' => $mepr_options->require_fname_lname,
      'show_address_fields' => $mepr_options->show_address_fields,
      'require_address_fields' => $mepr_options->require_address_fields,
      'custom_field_count' => 0,
      'custom_field_text_count' => 0,
      'custom_field_email_count' => 0,
      'custom_field_url_count' => 0,
      'custom_field_date_count' => 0,
      'custom_field_textarea_count' => 0,
      'custom_field_checkbox_count' => 0,
      'custom_field_dropdown_count' => 0,
      'custom_field_multiselect_count' => 0,
      'custom_field_radios_count' => 0,
      'custom_field_checkboxes_count' => 0,
      'include_email_privacy_link' => $mepr_options->include_email_privacy_link,
      'user_welcome_email_enabled' => !empty($mepr_options->emails['MeprUserWelcomeEmail']['enabled']),
      'user_receipt_email_enabled' => !empty($mepr_options->emails['MeprUserReceiptEmail']['enabled']),
      'user_cancelled_sub_email_enabled' => !empty($mepr_options->emails['MeprUserCancelledSubEmail']['enabled']),
      'user_upgraded_sub_email_enabled' => !empty($mepr_options->emails['MeprUserUpgradedSubEmail']['enabled']),
      'user_downgraded_sub_email_enabled' => !empty($mepr_options->emails['MeprUserDowngradedSubEmail']['enabled']),
      'user_suspended_sub_email_enabled' => !empty($mepr_options->emails['MeprUserSuspendedSubEmail']['enabled']),
      'user_resumed_sub_email_enabled' => !empty($mepr_options->emails['MeprUserResumedSubEmail']['enabled']),
      'user_refunded_txn_email_enabled' => !empty($mepr_options->emails['MeprUserRefundedTxnEmail']['enabled']),
      'user_failed_txn_email_enabled' => !empty($mepr_options->emails['MeprUserFailedTxnEmail']['enabled']),
      'user_cc_expiring_email_enabled' => !empty($mepr_options->emails['MeprUserCcExpiringEmail']['enabled']),
      'admin_signup_email_enabled' => !empty($mepr_options->emails['MeprAdminSignupEmail']['enabled']),
      'admin_new_one_off_email_enabled' => !empty($mepr_options->emails['MeprAdminNewOneOffEmail']['enabled']),
      'admin_new_sub_email_enabled' => !empty($mepr_options->emails['MeprAdminNewSubEmail']['enabled']),
      'admin_receipt_email_enabled' => !empty($mepr_options->emails['MeprAdminReceiptEmail']['enabled']),
      'admin_cancelled_sub_email_enabled' => !empty($mepr_options->emails['MeprAdminCancelledSubEmail']['enabled']),
      'admin_upgraded_sub_email_enabled' => !empty($mepr_options->emails['MeprAdminUpgradedSubEmail']['enabled']),
      'admin_downgraded_sub_email_enabled' => !empty($mepr_options->emails['MeprAdminDowngradedSubEmail']['enabled']),
      'admin_suspended_sub_email_enabled' => !empty($mepr_options->emails['MeprAdminSuspendedSubEmail']['enabled']),
      'admin_resumed_sub_email_enabled' => !empty($mepr_options->emails['MeprAdminResumedSubEmail']['enabled']),
      'admin_refunded_txn_email_enabled' => !empty($mepr_options->emails['MeprAdminRefundedTxnEmail']['enabled']),
      'admin_failed_txn_email_enabled' => !empty($mepr_options->emails['MeprAdminFailedTxnEmail']['enabled']),
      'admin_cc_expiring_email_enabled' => !empty($mepr_options->emails['MeprAdminCcExpiringEmail']['enabled']),
      'disable_global_autoresponder_list' => $mepr_options->disable_global_autoresponder_list,
      'opt_in_checked_by_default' => $mepr_options->opt_in_checked_by_default,
      'language_code' => $mepr_options->language_code,
      'currency_code' => $mepr_options->currency_code,
      'currency_symbol' => $mepr_options->currency_symbol,
      'currency_symbol_after' => $mepr_options->currency_symbol_after,
      'global_styles' => $mepr_options->global_styles,
      'authorize_seo_views' => $mepr_options->authorize_seo_views,
      'seo_unauthorized_noindex' => $mepr_options->seo_unauthorized_noindex,
      'paywall_enabled' => $mepr_options->paywall_enabled,
      'paywall_num_free_views' => $mepr_options->paywall_num_free_views,
      'disable_mod_rewrite' => $mepr_options->disable_mod_rewrite,
      'asynchronous_emails' => (bool) get_option('mp-bkg-email-jobs-enabled'),
      'calculate_taxes' => (bool) get_option('mepr_calculate_taxes'),
      'tax_calc_type' => (string) get_option('mepr_tax_calc_type'),
      'tax_calc_location' => (string) get_option('mepr_tax_calc_location'),
      'tax_default_address' => (string) get_option('mepr_tax_default_address'),
      'tax_avalara_enabled' => (bool) get_option('mepr_tax_avalara_enabled'),
      'tax_taxjar_enabled' => (bool) get_option('mepr_tax_taxjar_enabled'),
      'tax_taxjar_enable_sandbox' => (bool) get_option('mepr_tax_taxjar_enable_sandbox'),
      'vat_enabled' => (bool) get_option('mepr_vat_enabled'),
      'vat_country' => (string) get_option('mepr_vat_country'),
      'vat_tax_businesses' => (bool) get_option('mepr_vat_tax_businesses')
    );

    $custom_fields = $mepr_options->custom_fields;

    if (is_array($custom_fields)) {
      foreach ($custom_fields as $custom_field) {
        $options['custom_field_count']++;

        if (isset($custom_field->field_type, $options["custom_field_{$custom_field->field_type}_count"])) {
          $options["custom_field_{$custom_field->field_type}_count"]++;
        }
      }
    }

    return array($options);
  }

  private function gateways() {
    $mepr_options = MeprOptions::fetch();

    $pms = $mepr_options->payment_methods(false);

    $gateways = array();
    foreach($pms as $pm) {
      $rev_stats = MeprReports::gateway_revenue_stats($pm->id);
      $gateways[] = array(
        'name' => $pm->name,
        'livemode' => !$pm->is_test_mode(),
        'week_revenue'        => $rev_stats->week_revenue,
        'week_refunds_total'  => $rev_stats->week_refunds_total,
        'month_revenue'       => $rev_stats->month_revenue,
        'month_refunds_total' => $rev_stats->month_refunds_total,
        'year_revenue'        => $rev_stats->year_revenue,
        'year_refunds_total'  => $rev_stats->year_refunds_total,
        'lifetime_revenue' => $rev_stats->lifetime_revenue,
        'lifetime_refunds_total' => $rev_stats->lifetime_refunds_total
      );
    }

    return $gateways;
  }

} //End class
