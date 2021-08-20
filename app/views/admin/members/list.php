<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="wrap">
  <h2><?php _e('Members', 'memberpress'); ?> <a href="<?php echo admin_url('admin.php?page=memberpress-members&action=new'); ?>" class="add-new-h2"><?php _e('Add New', 'memberpress'); ?></a></h2>

  <?php if(!isset($errors)) { $errors = ''; } ?>
  <?php MeprView::render('/admin/errors', compact('errors','message')); ?>

  <?php $list_table->display(); ?>
</div>
