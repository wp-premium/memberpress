<?php
if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

$mepr_options = MeprOptions::fetch();

if(!$mepr_options->setup_complete) {
  if(!is_numeric($mepr_options->thankyou_page_id) || $mepr_options->thankyou_page_id == 0) {
    $mepr_options->thankyou_page_id = MeprAppHelper::auto_add_page(__('Thank You', 'memberpress'), esc_html__('Your subscription has been set up successfully.', 'memberpress'));
  }

  if(!is_numeric($mepr_options->account_page_id) || $mepr_options->account_page_id == 0) {
    $mepr_options->account_page_id = MeprAppHelper::auto_add_page(__('Account', 'memberpress'));
  }

  if(!is_numeric($mepr_options->login_page_id) || $mepr_options->login_page_id == 0) {
    $mepr_options->login_page_id = MeprAppHelper::auto_add_page(__('Login', 'memberpress'));
  }

  $mepr_options->setup_complete = 1;
  $mepr_options->activated_timestamp = time();
  $mepr_options->store(false);
}

MeprUtils::flush_rewrite_rules();
