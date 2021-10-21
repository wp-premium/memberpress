<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

/** This is just a front-end Controller adapter for MeprNotifications.
  */
class MeprNotificationsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    $notifications = new MeprNotifications();
    $notifications->init(); // loads hooks

    add_action('admin_enqueue_scripts', function () {
      if(MeprUtils::is_memberpress_admin_page()) {
        do_action('mepr_overview_enqueue');
      }
    });

    //add_action('admin_notices', function() {
    //  if(MeprUtils::is_memberpress_admin_page()) {
    //    do_action('mepr_admin_overview_before_table');
    //  }
    //});
  }
}

