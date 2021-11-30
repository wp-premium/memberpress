<?php
if( ! defined( 'ABSPATH' ) ) { die( 'You are not allowed to call this page directly.' ); }
/*
Integration of Polylang plugin with MemberPress
*/
class MeprPolylangIntegration {
  public function __construct() {
    add_action( 'mepr-signup', array( $this, 'maybe_set_user_locale_at_register' ) );
    add_action( 'mepr-model-initialized', array( $this, 'maybe_get_and_set_user_locale' ) );
  }

  public function maybe_set_user_locale_at_register($txn) {
    if(function_exists('pll_current_language')) {
      $user = $txn->user();
      update_user_meta($user->ID, 'locale', pll_current_language('locale'));
    }
  }

  public function maybe_get_and_set_user_locale($obj) {
    //Make sure we have the right object type
    if(!is_null($obj) && ($obj instanceof MeprTransaction || $obj instanceof MeprSubscription)) {
      //Make sure we have a user
      if(isset($obj->user_id) && $obj->user_id > 0 && !MeprUtils::is_user_logged_in()) {
        //Check that Polylang plugin is installed and activated
        if(function_exists('pll_current_language')) {
          @switch_to_locale(get_user_locale($obj->user_id));
          MeprOptions::fetch(true); // Force refresh MeprOptions singleton
        }
      }
    }
  }
} //End class

new MeprPolylangIntegration;
