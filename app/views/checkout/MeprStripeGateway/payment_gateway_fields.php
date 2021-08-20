<?php

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if($mepr_options->show_address_fields) {
  foreach($mepr_options->address_fields as $address_field) {
    printf(
      '<input type="hidden" name="%s" value="%s" />',
      esc_attr(str_replace('mepr-', 'card-', $address_field->field_key)),
      esc_attr(get_user_meta($user->ID, $address_field->field_key, true))
    );
  }
}
