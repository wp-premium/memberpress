<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<p class="description">
  <?php
    printf(
      // translators: %1$s: link to memberpress.com, %2$s: link to memberpress.com/login
      esc_html__('You must have a License Key to enable automatic updates for MemberPress. If you don\'t have a License please go to %1$s to get one. If you do have a license you can login at %2$s to manage your licenses and site activations.', 'memberpress'),
      '<a href="https://memberpress.com">MemberPress.com</a>',
      '<a href="https://memberpress.com/login">MemberPress.com/login</a>'
    );
  ?>
</p>

<table class="form-table">
  <tr class="form-field">
    <th valign="top"><?php esc_html_e('License Key:', 'memberpress'); ?></th>
    <td>
      <input type="text" id="mepr-license-key" value="<?php echo esc_attr($mepr_options->mothership_license); ?>" />
      <button type="button" id="mepr-activate-license-key" class="button button-primary"><?php esc_html_e('Activate License Key', 'memberpress'); ?></button>
    </td>
  </tr>
</table>
