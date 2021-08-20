<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

$products = MeprCptModel::all('MeprProduct');

if(!empty($products)):
?>
<div class="mepr-coupons-form">
  <table class="form-table">
    <tbody>
      <tr valign="top">
        <th scope="row">
          <label><?php _e('Discount:', 'memberpress'); ?></label>
          <?php
            MeprAppHelper::info_tooltip( 'mepr-coupon-discount',
              __('Coupon Discount', 'memberpress'),
              __('<b>Recurring Memberships</b>: This discount will not apply to paid trials but will apply to all recurring transactions associated with the subscription. That means that 100% discount will give the member lifetime access for free.<br/><br/><b>Lifetime Memberships</b>: This discount will apply directly to the lifetime membership\'s one-time payment.', 'memberpress'));
          ?>
        </th>
        <td>
          <input type="text" size="5" name="<?php echo MeprCoupon::$discount_amount_str; ?>" value="<?php echo $c->discount_amount; ?>" />
          <select name="<?php echo MeprCoupon::$discount_type_str; ?>">
            <option value="percent" <?php echo ($c->discount_type == 'percent')?'selected="selected"':''; ?>><?php _e('%', 'memberpress'); ?></option>
            <option value="dollar" <?php echo ($c->discount_type == 'dollar')?'selected="selected"':''; ?>><?php echo $mepr_options->currency_code; ?></option>
          </select>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row">
          <label><?php _e('Discount Mode:', 'memberpress'); ?></label>
          <?php
            MeprAppHelper::info_tooltip(
              'mepr-coupon-discount-mode',
              __('Discount Mode', 'memberpress'),
              __("<b>Standard:</b> This simply applies the discount to the amount of the charge or subscription.<br/><br/><b>First Payment:</b> This will allow you to set a different discount on the first transaction than the rebill transactions in a recurring subscription. If this value is set for a non-recurring payment then the First Payment discount will take precedence over the coupon's main discount.<br/><br/><b>Trial Override:</b> This will create a custom trial period based on the number of days & trial cost here. This option only works on recurring payments and will prevent any trials associated with the membership from working. The discount set above will still apply to the subscription’s recurring amount.", 'memberpress') );
          ?>
        </th>
        <td>
          <select name="<?php echo MeprCoupon::$discount_mode_str; ?>" class="mepr-toggle-select" data-first-payment-box="mepr_first_payment_box" data-trial-override-box="mepr_trial_override_box">
            <option value="standard" <?php selected($c->discount_mode, 'standard'); ?>><?php _e('Standard', 'memberpress'); ?></option>
            <option value="first-payment" <?php selected($c->discount_mode, 'first-payment'); ?>><?php _e('First Payment', 'memberpress'); ?></option>
            <option value="trial-override" <?php selected($c->discount_mode, 'trial-override'); ?>><?php _e('Trial Period Override', 'memberpress'); ?></option>
          </select>
        </td>
      </tr>
    </tbody>
  </table>
  <div id="mepr_trial_override_box" class="mepr-sub-box mepr_trial_override_box">
    <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label><?php _e('# of Days:', 'memberpress'); ?></label>
            <?php
              MeprAppHelper::info_tooltip(
                'mepr-coupon-trial-days',
                __('Trial Days Price Text', 'memberpress'),
                __('Values here that are multiples of 365 will show as years, multiples of 30 will show as months, multiples of 7 will show as weeks ... otherwise the trial will show up as days.', 'memberpress') );
            ?>
          </th>
          <td>
            <input name="<?php echo MeprCoupon::$trial_days_str; ?>" id="<?php echo MeprCoupon::$trial_days_str; ?>" type="text" size="3" value="<?php echo $c->trial_days; ?>" />
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">
            <label><?php _e('Trial Cost:', 'memberpress'); ?></label>
          </th>
          <td>
            <?php echo $mepr_options->currency_symbol; ?><input name="<?php echo MeprCoupon::$trial_amount_str; ?>" id="<?php echo MeprCoupon::$trial_amount_str; ?>" size="7" type="text" value="<?php echo MeprUtils::format_float($c->trial_amount); ?>" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div id="mepr_first_payment_box" class="mepr-sub-box mepr_first_payment_box">
    <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">
            <label><?php _e('First Payment Discount:', 'memberpress'); ?></label>
            <?php
              MeprAppHelper::info_tooltip(
                'mepr-first-payment-discount',
                __('First Payment Discount', 'memberpress'),
                __("This is the discount that will be applied to the first payment. All additional payments will happen at the standard discount above.", 'memberpress') );
            ?>
          </th>
          <td>
            <input type="text" size="5" name="<?php echo MeprCoupon::$first_payment_discount_amount_str; ?>" value="<?php echo esc_attr($c->first_payment_discount_amount); ?>" />
            <select name="<?php echo MeprCoupon::$first_payment_discount_type_str; ?>">
              <option value="percent" <?php selected($c->first_payment_discount_type,'percent'); ?>><?php _e('%', 'memberpress'); ?></option>
              <option value="dollar" <?php selected($c->first_payment_discount_type,'dollar'); ?>><?php echo $mepr_options->currency_code; ?></option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <table class="form-table">
    <tbody>
      <tr valign="top">
        <th scope="row">
          <label><?php _e('Usage Count:', 'memberpress'); ?></label>
          <?php
            MeprAppHelper::info_tooltip( 'mepr-coupon-usage-amount',
              __('Number of Coupon Uses', 'memberpress'),
              __('This determines the number of times this coupon can be used.<br/><br/>Set to "0" to remove the limit.', 'memberpress')
            );
          ?>
        </th>
        <td>
          <?php $usage_amount = (intval($c->usage_amount) <= 0) ? '∞' : $c->usage_amount; ?>
          <input type="text" maxlength="4" size="4" name="<?php  echo MeprCoupon::$usage_amount_str; ?>" value="<?php echo $usage_amount; ?>" />
        </td>
      </tr>
    </tbody>
  </table>
  <table class="form-table">
    <tbody>
      <tr valign="top">
        <th scope="row">
          <label for="<?php echo MeprCoupon::$use_on_upgrades_str; ?>"><?php _e('Allow on Upgrades and Downgrades:', 'memberpress'); ?></label>
        </th>
        <td>
          <input type="checkbox" name="<?php echo MeprCoupon::$use_on_upgrades_str; ?>" id="<?php echo MeprCoupon::$use_on_upgrades_str; ?>" class="mepr-toggle-checkbox" data-box="mepr_use_on_upgrades_box" <?php checked($c->use_on_upgrades); ?> />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row">
          <label for="<?php echo MeprCoupon::$should_start_str; ?>"><?php _e('Schedule Coupon Start:', 'memberpress'); ?></label>
        </th>
        <td>
          <input type="checkbox" name="<?php echo MeprCoupon::$should_start_str; ?>" id="<?php echo MeprCoupon::$should_start_str; ?>" class="mepr-toggle-checkbox" data-box="mepr_start_coupon_box" <?php checked($c->should_start); ?> />
          <div id="mepr_start_coupon_box" class="mepr-sub-box mepr_start_coupon_box" style="margin-top: 20px;">
            <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
            <table class="form-table">
              <tbody>
                <tr valign="top">
                  <th scope="row">
                    <label><?php _e('Coupon Start Date:', 'memberpress'); ?></label>
                  </th>
                  <td>
                    <span class="description"><small><?php echo MeprUtils::period_type_name('months'); ?></small></span>
                    <select name="<?php echo MeprCoupon::$starts_on_month_str; ?>">
                      <?php MeprCouponsHelper::months_options($c->starts_on); ?>
                    </select>
                    <span class="description"><small><?php echo MeprUtils::period_type_name('days'); ?></small></span>
                    <input type="text" size="2" maxlength="2" name="<?php echo MeprCoupon::$starts_on_day_str; ?>" value="<?php echo MeprUtils::get_date_from_ts($c->starts_on, 'j'); ?>" />
                    <span class="description"><small><?php echo MeprUtils::period_type_name('years'); ?></small></span>
                    <input type="text" size="4" maxlength="4" name="<?php echo MeprCoupon::$starts_on_year_str; ?>" value="<?php echo MeprUtils::get_date_from_ts($c->starts_on, 'Y'); ?>" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row">
          <label for="<?php echo MeprCoupon::$should_expire_str; ?>"><?php _e('Expire Coupon:', 'memberpress'); ?></label>
        </th>
        <td>
          <input type="checkbox" name="<?php echo MeprCoupon::$should_expire_str; ?>" id="<?php echo MeprCoupon::$should_expire_str; ?>" class="mepr-toggle-checkbox" data-box="mepr_expire_coupon_box" <?php checked($c->should_expire); ?> />
          <div id="mepr_expire_coupon_box" class="mepr-sub-box mepr_expire_coupon_box" style="margin-top: 20px;">
            <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
            <table class="form-table">
              <tbody>
                <tr valign="top">
                  <th scope="row">
                    <label><?php _e('Coupon Expiration:', 'memberpress'); ?></label>
                  </th>
                  <td>
                    <span class="description"><small><?php echo MeprUtils::period_type_name('months'); ?></small></span>
                    <select name="<?php echo MeprCoupon::$expires_on_month_str; ?>">
                      <?php MeprCouponsHelper::months_options($c->expires_on); ?>
                    </select>
                    <span class="description"><small><?php echo MeprUtils::period_type_name('days'); ?></small></span>
                    <input type="text" size="2" maxlength="2" name="<?php echo MeprCoupon::$expires_on_day_str; ?>" value="<?php echo MeprUtils::get_date_from_ts($c->expires_on, 'j'); ?>" />
                    <span class="description"><small><?php echo MeprUtils::period_type_name('years'); ?></small></span>
                    <input type="text" size="4" maxlength="4" name="<?php echo MeprCoupon::$expires_on_year_str; ?>" value="<?php echo MeprUtils::get_date_from_ts($c->expires_on, 'Y'); ?>" />
                    Coupon Expires at <strong>Midnight UTC</strong>.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </td>
      </tr>
    </tbody>
  </table>

  <table class="form-table">
    <tbody>
      <tr valign="top">
        <td>
          <?php _e('Apply coupon to the following Memberships:', 'memberpress'); ?><br/>
          <?php MeprCouponsHelper::products_dropdown(MeprCoupon::$valid_products_str, $c->valid_products); ?><br/>
          <span class="description"><?php _e('Hold the Control Key (Command Key on the Mac) in order to select or deselect multiple memberships', 'memberpress'); ?></span>
        </td>
      </tr>
    </tbody>
  </table>
  <!-- The NONCE below prevents post meta from being blanked on move to trash -->
  <input type="hidden" name="<?php echo MeprCoupon::$nonce_str; ?>" value="<?php echo wp_create_nonce(MeprCoupon::$nonce_str.wp_salt()); ?>" />
  <!-- jQuery i18n data -->
  <div id="save-coupon-helper" style="display:none;" data-value="<?php _e('Save Coupon', 'memberpress'); ?>"></div>
  <div id="coupon-message-helper" style="display:none;" data-value="<?php _e('Coupon Saved', 'memberpress'); ?>"></div>
</div>
<?php
else:
?>
  <div id="mepr-coupons-form">
    <strong><?php _e('You cannot create coupons until you have added at least 1 Membership.', 'memberpress'); ?></strong>
    <!-- jQuery i18n data -->
    <div id="save-coupon-helper" style="display:none;" data-value="<?php _e('Save Coupon', 'memberpress'); ?>"></div>
    <div id="coupon-message-helper" style="display:none;" data-value="<?php _e('Coupon Saved', 'memberpress'); ?>"></div>
  </div>
<?php
endif;
