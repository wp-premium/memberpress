<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<?php
  if(!isset($editions)) {
    $editions = MeprUtils::is_incorrect_edition_installed();
  }

  if(is_array($editions)) {
    printf(
      '<div class="notice notice-warning inline"><p>%1$s<img id="mepr-install-license-edition-loading" class="mepr-hidden" src="%2$s" alt="%3$s" /></p></div>',
      sprintf(
        /* translators: %1$s: the license edition, %2$s: the installed edition, %3$s: open link tag, %4$s: close link tag */
        esc_html__('This License Key is for %1$s, but %2$s is installed. %3$sClick here%4$s to install the correct edition for the license (%1$s).', 'memberpress'),
        '<strong>' . esc_html($editions['license']['name']) . '</strong>',
        '<strong>' . esc_html($editions['installed']['name']) . '</strong>',
        '<a id="mepr-install-license-edition" href="#">',
        '</a>'
      ),
      esc_url(MEPR_IMAGES_URL . '/square-loader.gif'),
      esc_html__('Loading...', 'memberpress')
    );
  }
?>
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
