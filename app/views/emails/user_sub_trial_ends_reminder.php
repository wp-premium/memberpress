<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div id="header" style="width: 680px; padding: 0px; margin: 0 auto; text-align: left;">
  <h1 style="font-size: 30px; margin-bottom: 4px;">{$reminder_name}</h1>
</div>
<div id="body" style="width: 600px; background: white; padding: 40px; margin: 0 auto; text-align: left;">
  <div id="receipt">
    <div class="section" style="display: block; margin-bottom: 24px;">Hi {$user_first_name},</div>
    <div class="section" style="display: block; margin-bottom: 24px;">Just a friendly reminder that your Trial Period for {$product_name} will end on  <strong>{$subscr_trial_end_date}</strong>.</div>
    <div class="section" style="display: block; margin-bottom: 24px;">You will be billed {$subscr_next_billing_amount} automatically. If you'd like to cancel your subscription, you can do so from your <a href="{$account_url}">Account Page</a>.</div>
    <div class="section" style="display: block; margin-bottom: 24px;">Cheers!</div>
    <div class="section" style="display: block; margin-bottom: 24px;">The {$blog_name} Team</div>
  </div>
</div>