<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="wrap">
  <h2><?php _e('Members', 'memberpress'); ?> <a href="<?php echo admin_url('admin.php?page=memberpress-members&action=new'); ?>" class="add-new-h2"><?php _e('Add New', 'memberpress'); ?></a></h2>

  <?php if(!isset($errors)) { $errors = ''; } ?>
  <?php MeprView::render('/admin/errors', compact('errors','message')); ?>

  <?php
    $migrating = get_transient('mepr_members_migrate_start');
    if(isset($migrating) && $migrating) { ?>
      <div class="notice notice-warning">
        <p>Your Members data is currently being migrated so that active and inactive memberships will now appear in separate columns.</p>
        <p>This message will disappear when it is completed. If you see this message for more than a few days, please contact MemberPress support.</p>
      </div>
  <?php } ?>

  <?php $list_table->display(); ?>
</div>
