<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
/** @var MeprProduct $product */
$group = $product->group();

if($group !== false && $group instanceof MeprGroup && $group->is_upgrade_path) {
  $product_in_upgrade_path = true;
} else {
  $product_in_upgrade_path = false;
}

?>
<div class="product-options-panel">
  <?php if(!$product_in_upgrade_path) { ?>
  <div id="mepr-simultaneous-purchases">
    <input type="checkbox" name="<?php echo MeprProduct::$simultaneous_subscriptions_str; ?>" id="<?php echo MeprProduct::$simultaneous_subscriptions_str; ?>" <?php checked($product->simultaneous_subscriptions); ?> />
    <label for="<?php echo MeprProduct::$simultaneous_subscriptions_str; ?>"><?php _e('Allow users to create multiple, active subscriptions to this membership', 'memberpress'); ?></label>
  </div>
  <br/>
  <?php } ?>
  <div id="mepr-who-can-purchase">
    <label><?php _e('Who can purchase this Membership', 'memberpress'); ?></label>
    <ol id="who-can-purchase-list" class="mepr-sortable">
      <?php MeprProductsHelper::get_who_can_purchase_items($product); ?>
    </ol>
    <a href="" class="add-new-who" title="Add Rule"><i class="mp-icon mp-icon-plus-circled mp-24"></i></a>
    <div id="who_can_purchase_hidden_row">
      <?php MeprProductsHelper::get_blank_who_can_purchase_row($product); ?>
    </div>
    <div id="cannot_purchase_message">
      <label><?php _e('No permissions message', 'memberpress'); ?></label>
      <?php wp_editor(stripslashes($product->cannot_purchase_message), 'meprcannotpurchasemessage'); ?>
    </div>
  </div>

  <?php MeprHooks::do_action('mepr_products_permissions_tab', $product); ?>
</div>
