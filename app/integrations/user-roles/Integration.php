<?php

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

// Deactivate the standalone plugin
if ( is_plugin_active( 'memberpress-userroles/main.php' ) ) {
  deactivate_plugins( 'memberpress-userroles/main.php', true );
}

// Load Addon
require_once 'MeprUserRoles.php';
new MeprUserRoles;