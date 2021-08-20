<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<p>
  <?php echo (empty($first_name)?$username:$first_name); ?>,
  <br/>
  <?php echo sprintf(_x("Your password was successfully reset on %1\$s!", 'ui', 'memberpress'), $mepr_blogname); ?>
</p>
<p>
  <?php echo sprintf(_x("Username: %1\$s", 'ui', 'memberpress'), $username); ?>
  <br/>
  <?php echo _x("Password: *** Successfully Reset ***", 'ui', 'memberpress'); ?>
</p>
<p><?php echo sprintf(_x("You can now login here: %1\$s", 'ui', 'memberpress'), $login_link); ?></p>
