<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mepr-checkout-form spc">
  <?php MeprView::render("/checkout/{$payment_method->settings->gateway}/payment_gateway_fields", get_defined_vars()); ?>
  <div class="mp-form-row">
    <div class="mp-form-label">
      <label><?php _ex('Credit Card Number', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Invalid Credit Card Number', 'ui', 'memberpress'); ?></span>
    </div>
    <input type="tel" class="mepr-form-input card-number cc-number validation" pattern="\d*" autocomplete="cc-number" required>
    <input type="hidden" class="mepr-cc-num" name="mepr_cc_num"/>
  </div>
  <input type="hidden" name="mepr-cc-type" class="cc-type" value="" />
  <div class="mp-form-row">
    <div class="mp-form-label">
      <label><?php _ex('Expiration', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Invalid Expiration', 'ui', 'memberpress'); ?></span>
    </div>
    <input type="tel" class="mepr-form-input cc-exp validation" pattern="\d*" autocomplete="cc-exp" placeholder="<?php _ex('mm/yy', 'ui', 'memberpress'); ?>" required>
    <input type="hidden" class="cc-exp-month" name="mepr_cc_exp_month"/>
    <input type="hidden" class="cc-exp-year" name="mepr_cc_exp_year"/>
  </div>
  <div class="mp-form-row">
    <div class="mp-form-label">
      <label><?php _ex('CVC', 'ui', 'memberpress'); ?></label>
      <span class="cc-error"><?php _ex('Invalid CVC Code', 'ui', 'memberpress'); ?></span>
    </div>
    <input type="tel" name="mepr_cvv_code" class="mepr-form-input card-cvc cc-cvc validation" pattern="\d*" autocomplete="off" required />
  </div>
</div>
<noscript><p class="mepr_nojs"><?php _e('Javascript is disabled in your browser. You will not be able to complete your purchase until you either enable JavaScript in your browser, or switch to a browser that supports it.', 'memberpress'); ?></p></noscript>
