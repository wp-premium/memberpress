<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-license-active">
  <div><h4><?php esc_html_e('Active License Key Information:', 'memberpress'); ?></h4></div>
  <table>
    <tr>
      <td><?php esc_html_e('License Key:', 'memberpress'); ?></td>
      <td>********-****-****-****-<?php echo esc_html(substr($li['license_key']['license'], -12)); ?></td>
    </tr>
    <tr>
      <td><?php esc_html_e('Status:', 'memberpress'); ?></td>
      <td><b><?php echo esc_html(sprintf(__('Active on %s', 'memberpress'), MeprUtils::site_domain())); ?></b></td>
    </tr>
    <tr>
      <td><?php esc_html_e('Product:', 'memberpress'); ?></td>
      <td><?php echo esc_html($li['product_name']); ?></td>
    </tr>
    <tr>
      <td><?php esc_html_e('Activations:', 'memberpress'); ?></td>
      <td>
        <?php
          printf(
            // translators: %1$s: open b tag, %2$d: activation count, %3$s: max activations, %4$s close b tag
            esc_html__('%1$s%2$d of %3$s%4$s sites have been activated with this license key', 'memberpress'),
            '<b>',
            esc_html($li['activation_count']),
            esc_html(ucwords($li['max_activations'])),
            '</b>'
          );
        ?>
      </td>
    </tr>
  </table>
  <div class="mepr-deactivate-button">
    <button type="button" id="mepr-deactivate-license-key" class="button button-primary"><?php echo esc_html(sprintf(__('Deactivate License Key on %s', 'memberpress'), MeprUtils::site_domain())); ?></button>
  </div>
</div>

<?php MeprView::render('/admin/options/edge_updates', get_defined_vars()); ?>
