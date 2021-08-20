<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<?php
$reset_error = isset($_REQUEST['error']) ? $_REQUEST['error'] : "";

if(!empty($reset_error)) {
  $errors[] = $reset_error;
 ?>
  <h3><?php _ex('Password could not be reset.', 'ui', 'memberpress'); ?></h3>
  <?php MeprView::render('/shared/errors', get_defined_vars()); ?>
  <div><?php _ex('Please contact us for further assistance.', 'ui', 'memberpress'); ?></div>
<?php
} else {
?>
<div class="mp_wrapper mepr_password_reset_requested">
  <h3><?php _ex('Successfully requested password reset', 'ui', 'memberpress'); ?></h3>
  <p><?php _ex('Please click on the confirmation link that was just sent to your email address.', 'ui', 'memberpress'); ?></p>
</div>
<?php } ?>
