<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}
/*
Integration of User Roles into MemberPress
*/
class MeprUserRoles  {
  public function __construct() {
    add_action('mepr-txn-store',                array($this, 'process_status_changes'));
    add_action('mepr-txn-expired',              array($this, 'process_status_changes'), 10, 2);
    add_action('mepr-account-is-active',        array($this, 'process_status_changes'));
    add_action('mepr_post_delete_transaction',  array($this, 'process_destroy_txn'), 10, 3);
    add_action('mepr-product-advanced-metabox', array($this, 'display_product_override'));
    add_action('mepr-product-save-meta',        array($this, 'save_product_override'));
    add_action('profile_update',                array($this, 'process_profile_update'), 10, 2);

    // Enqueue scripts
    add_action('mepr-membership-admin-enqueue-script', array($this, 'admin_enqueue_product_scripts'));
  }

  public function admin_enqueue_product_scripts($hook) {
    wp_enqueue_script( 'mp-userroles-product-js', plugin_dir_url( __FILE__ ) . 'userroles_product.js', array('jquery'), 'MEPR_VERSION', true );
  }

  /**
  * This will ensure that the roles persist after the profile is updated
  * @see add_action('profile_update')
  */
  public function process_profile_update($user_id, $old_user_data) {
    // Restrict to admin user profile updates
    if(is_admin()) {
      $wp_user = get_user_by('id', $user_id);

      if(!$wp_user) { return; }

      $this->set_users_roles($wp_user);
    }
  }

  public function process_destroy_txn($id, $user, $result) {
    $txn = new MeprTransaction(); // Temp txn object to pass to process_status_changes
    $txn->user_id = $user->ID;
    $this->process_status_changes($txn);
  }

  public function process_status_changes($obj, $sub_status = false) {
    if($obj instanceof MeprTransaction && $sub_status !== false && $sub_status == MeprSubscription::$active_str) {
      return; //This is an expiring transaction which is part of an active subscription, so don't remove the user's roles
    }

    $wp_user = get_user_by('id', $obj->user_id);

    if(!$wp_user) { return; }

    $this->set_users_roles($wp_user);
  }

  public function set_users_roles($wp_user) {
    //Okay we're going to be a bit tricky here
    //1 What we need to do is run through all Memberships and get a list of ALL Roles attached to ALL Memberships
    //2 Then we need to get the Roles this user should have according to their active memberships
    //3 Then we need to get an array_diff of the two, and remove the one's that are different
    //4 Then we'll re-add the Roles the user should have (from step 2 above)
    //Along the way we also need to keep track of Roles the user may have which aren't associated with Memberships - and make sure they stay in place
    //And lastly - make sure the user doesn't have an empty $wp_user->roles - if they somehow do - we'll just set the default role

    $roles_user_should_have = $this->get_users_active_roles($wp_user);
    $roles_to_remove        = $this->get_roles_to_remove($wp_user);

    //Remove the Roles they shouldn't have
    $roles_to_remove = MeprHooks::apply_filters('mepr-userroles-remove-roles', $roles_to_remove, $wp_user);
    if(!empty($roles_to_remove)) {
      $this->remove_roles($wp_user, $roles_to_remove);
    }

    //Add the Roles they should have
    $roles_user_should_have = MeprHooks::apply_filters('mepr-userroles-add-roles', $roles_user_should_have, $wp_user);
    if(!empty($roles_user_should_have)) {
      $this->add_roles($wp_user, $roles_user_should_have);
    }

    //Reset the user caches
    clean_user_cache($wp_user);

    //Check if the user now has no Roles - if so - we'll reset them to the default WP Role
    //Need to do this after cleaning user caches to ensure we get the proper $wp_user->roles
    $wp_user = get_user_by('id', $wp_user->ID);

    if($wp_user !== false && empty($wp_user->roles)) {
      $this->reset_role($wp_user);
    }
  }

  public function get_all_roles_from_all_memberships() {
    global $wpdb;

    $products = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mepruserroles_enabled' AND meta_value = 1");

    return $this->get_roles_from_products_array($products);
  }

  public function get_users_active_roles($wp_user) {
    $mp_user  = new MeprUser($wp_user->ID);
    $products = $mp_user->active_product_subscriptions('ids', true);

    return $this->get_roles_from_products_array($products);
  }

  public function get_roles_to_remove($wp_user) {
    $inactive_memberships = array();

    $mepr_user = new MeprUser($wp_user->ID);
    $memberships = $mepr_user->active_product_subscriptions('ids', true, false);

    if(!empty($memberships)) {
      foreach($memberships as $membership) {
        if(!($mepr_user->is_already_subscribed_to($membership))) {
          $inactive_memberships[] = $membership;
        }
      }
    }

    return $this->get_roles_from_products_array($inactive_memberships);
  }

  public function get_roles_from_products_array($products) {
    //No products?
    if(empty($products)) { return array(); }

    $roles = array();

    foreach($products as $id) {
      $prd_roles = get_post_meta($id, '_mepruserroles_roles', true);

      if(!empty($prd_roles) && is_array($prd_roles)) {
        $roles = array_merge($roles, $prd_roles);
      }
    }

    return $roles;
  }

  //Be very careful here - this will WIPE OUT all user's roles and reset it to only one Role
  //Probably should ONLY call this if the $user->roles is empty
  public function reset_role($wp_user) {
    $wp_user->set_role(get_option('default_role'));

    //Reset the user caches
    clean_user_cache($wp_user);
  }

  public function add_roles($wp_user, $roles) {
    if(!empty($roles)) {
      foreach($roles as $role) {
        $wp_user->add_role($role);
      }
    }

    //Reset the user caches
    clean_user_cache($wp_user);
  }

  public function remove_roles($wp_user, $roles, $reset = true) {
    if(!empty($roles)) {
      foreach($roles as $role) {
        $wp_user->remove_role($role);
      }
    }

    //Reset the user caches
    clean_user_cache($wp_user);
  }

  public function get_all_roles() {
    $formatted_roles = $this->get_formatted_roles_from_array(get_editable_roles());

    //bbPress Roles (they don't store these in the DB for some reason)
    if(function_exists('bbp_get_dynamic_roles')) {
      $formatted_roles = array_merge($formatted_roles, $this->get_formatted_roles_from_array(bbp_get_dynamic_roles()));
    }

    return $formatted_roles;
  }

  public function get_formatted_roles_from_array($roles) {
    if(empty($roles)) { return array(); }

    $formatted_roles = array();

    foreach($roles as $role => $details) {
      $temp               = array();
      $temp['slug']       = esc_attr($role);
      $temp['name']       = translate_user_role($details['name']);
      $formatted_roles[]  = $temp;
    }

    return $formatted_roles;
  }

  public function display_product_override($product) {
    $enabled    = (bool)get_post_meta($product->ID, '_mepruserroles_enabled', true);
    $roles      = get_post_meta($product->ID, '_mepruserroles_roles', true);
    $all_roles  = $this->get_all_roles();

    if(empty($roles)) { $roles = array(); }

    ?>
    <div id="mepr-userroles" class="mepr-product-adv-item">
      <input type="checkbox" name="mepruserroles_enabled" id="mepruserroles_enabled" <?php checked($enabled); ?> />
      <label for="mepruserroles_enabled"><?php _e('User Roles for this Membership', 'memberpress-userroles', 'memberpress'); ?></label>

      <?php MeprAppHelper::info_tooltip('mepruserroles-list-override',
                                        __('Enable Membership User Roles', 'memberpress-userroles', 'memberpress'),
                                        __('These Roles will be added to and removed from the user automatically based on their current subscription status to this Membership level. When they are not active on any Memberships, their Role will go back to the Role you have set in your WordPress -> Settings -> General page.', 'memberpress-userroles', 'memberpress'));
      ?>

      <div id="mepruserroles_enabled_area" class="mepr-hidden product-options-panel">
        <select name="mepruserroles_product_roles[]" id="mepruserroles_product_roles" class="mepr-text-input form-field" multiple="multiple" size="5" style="width:99%;">
          <?php foreach($all_roles as $role): ?>
            <option value="<?php echo $role['slug']; ?>" <?php selected(in_array($role['slug'], $roles)); ?>><?php echo $role['name']; ?></option>
          <?php endforeach; ?>
        </select>
        <br/>
        <small><?php _e('Hold the Control Key (Command Key on the Mac) in order to select or deselect multiple roles', 'memberpress-userroles', 'memberpress'); ?></small>
      </div>
    </div>
    <?php
  }

  public function save_product_override($product) {
    if( isset($_POST['mepruserroles_enabled']) && isset($_POST['mepruserroles_product_roles']) ) {
      update_post_meta($product->ID, '_mepruserroles_enabled', true);
      update_post_meta($product->ID, '_mepruserroles_roles', $_POST['mepruserroles_product_roles']);
    }
    else {
      update_post_meta($product->ID, '_mepruserroles_enabled', false);
      update_post_meta($product->ID, '_mepruserroles_roles', array());
    }
  }
} //END CLASS
