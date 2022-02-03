<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 0;"><?php _ex('Your Subscription Payment Failed', 'ui', 'memberpress'); ?></h1>
  <h2 style="margin-top: 0; color: #999; font-weight: normal;"><?php _ex('{$subscr_num} &ndash; {$blog_name}', 'ui', 'memberpress'); ?></h2>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div class="section" style="display: block; margin-bottom: 35px;"><?php _ex('Sorry, your latest subscription payment on {$blog_name} failed. Please click the link below to pay the invoice to keep your subscription active.', 'ui', 'memberpress'); ?></div>
  <div class="section" style="display: block; margin-bottom: 35px; text-align: center;">
    <a style="background-color: #52a7e7; color: #fff; padding: 10px 30px; text-decoration: none; border-radius: 5px; display: inline-block;" href="{$stripe_invoice_url}"><?php _ex('Pay Invoice', 'ui', 'memberpress'); ?></a>
  </div>
  <table style="clear: both;" class="transaction">
    <tr><th style="text-align: left;"><?php _ex('Website:', 'ui', 'memberpress'); ?></th><td>{$blog_name}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Terms:', 'ui', 'memberpress'); ?></th><td>{$subscr_terms}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Subscription:', 'ui', 'memberpress'); ?></th><td>{$subscr_num}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Started:', 'ui', 'memberpress'); ?></th><td>{$subscr_date}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Email:', 'ui', 'memberpress'); ?></th><td>{$user_email}</td></tr>
    <tr><th style="text-align: left;"><?php _ex('Login:', 'ui', 'memberpress'); ?></th><td>{$user_login}</td></tr>
  </table>
</div>
