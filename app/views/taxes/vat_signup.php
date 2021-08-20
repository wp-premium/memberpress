<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mp-form-row mepr_custom_field mepr_mepr_vat_customer_type mepr_vat_customer_type_row">
  <label><?php _ex('Customer Type:', 'ui', 'memberpress'); ?></label>
  <div class="mepr-radios-field">
    <span class="mepr-radios-field-row">
      <input type="radio" name="mepr_vat_customer_type" id="mepr_vat_customer_type-consumer<?php echo $unique_suffix; ?>" value="consumer" class="mepr-form-radios-input mepr_vat_customer_type-consumer" <?php checked('consumer',$vat_customer_type); ?> />
      <label for="mepr_vat_customer_type-consumer<?php echo $unique_suffix; ?>" class="mepr-form-radios-label"><?php _ex('Consumer', 'ui', 'memberpress'); ?></label>
    </span>
    <span class="mepr-radios-field-row">
      <input type="radio" name="mepr_vat_customer_type" id="mepr_vat_customer_type-business<?php echo $unique_suffix; ?>" value="business" class="mepr-form-radios-input mepr_vat_customer_type-business" <?php checked('business',$vat_customer_type); ?> />
      <label for="mepr_vat_customer_type-business<?php echo $unique_suffix; ?>" class="mepr-form-radios-label"><?php _ex('Business', 'ui', 'memberpress'); ?></label>
    </span>
  </div>
</div>
<div class="mp-form-row mepr_custom_field mepr_vat_number_row">
  <div class="mp-form-label">
    <label for="mepr_vat_number<?php echo $unique_suffix; ?>"><?php _ex('VAT Number:', 'ui', 'memberpress'); ?></label>
    <span class="cc-error"><?php _ex('Invalid VAT Number', 'ui', 'memberpress'); ?></span>
  </div>
  <input type="text" name="mepr_vat_number" id="mepr_vat_number<?php echo $unique_suffix; ?>" class="mepr-form-input valid mepr_vat_number" value="<?php echo $vat_number; ?>" />
</div>

