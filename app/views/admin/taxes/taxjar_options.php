<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<tr valign="top">
  <th scope="row">
    <label for="mepr_tax_taxjar_enabled"><a href="#" target="_blank"><?php _e('Enable TaxJar', 'memberpress'); ?></a></label>
    <?php MeprAppHelper::info_tooltip( 'mepr-enable-tax-taxjar',
      __('Get US Tax Rates from TaxJar', 'memberpress'),
      __('TaxJar automate your sales tax calculations, reporting, and filings in minutes.<br/><br/>NOTE: This will override any tax rates for the US you\'ve imported via CSV.', 'memberpress'));
    ?>
  </th>
  <td>
    <input type="checkbox" id="mepr_tax_taxjar_enabled" name="mepr_tax_taxjar_enabled" class="mepr-toggle-checkbox" data-box="mepr_tax_taxjar_box" value="mepr_tax_taxjar_enabled" <?php checked( $tax_taxjar_enabled ); ?> />
  </td>
</tr>
<tr valign="top">
  <td colspan="2" class="mepr-sub-box-wrapper">
    <div id="mepr_tax_taxjar_box" class="mepr-sub-box mepr_tax_taxjar_box">
      <div class="mepr-arrow mepr-gray mepr-up mepr-sub-box-arrow"> </div>
      <table class="form-table">
        <tbody>
          <tr valign="top">
            <th scope="row">
              <label for="mepr_tax_taxjar_api_key_live"><?php _e('TaxJar API Key (Live)', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                'mepr-merchant-tax-taxjar-api-key-live',
                __('TaxJar API Key (Live)', 'memberpress'),
                __('Live API key for use in production environments.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="text" id="mepr_tax_taxjar_api_key_live" name="mepr_tax_taxjar_api_key_live" class="regular-text" value="<?php esc_attr_e( get_option( 'mepr_tax_taxjar_api_key_live' ), 'memberpress' ); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="mepr_tax_taxjar_api_key_sandbox"><?php _e('TaxJar API Key (Sandbox)', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                'mepr-merchant-tax-taxjar-api-key-sandbox',
                __('TaxJar API Key (Sandbox)', 'memberpress'),
                __('Sandbox API key for use in testing and staging environments.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="text" id="mepr_tax_taxjar_api_key_sandbox" name="mepr_tax_taxjar_api_key_sandbox" class="regular-text" value="<?php esc_attr_e( get_option( 'mepr_tax_taxjar_api_key_sandbox' ), 'memberpress' ); ?>" />
            </td>
          </tr>
          <tr valign="top">
            <th scope="row">
              <label for="mepr_tax_taxjar_enable_sandbox"><?php _e('Enable TaxJar Sandbox', 'memberpress'); ?></label>
              <?php MeprAppHelper::info_tooltip(
                'mepr-merchant-tax-taxjar-enable-sandbox',
                __('Enable TaxJar Sandbox', 'memberpress'),
                __('Enable a sandbox for use in testing and staging environments.', 'memberpress')
              ); ?>
            </th>
            <td>
              <input type="checkbox" id="mepr_tax_taxjar_enable_sandbox" name="mepr_tax_taxjar_enable_sandbox" value="1" <?php checked( intval( get_option( 'mepr_tax_taxjar_enable_sandbox' ) ), 1 ); ?> />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </td>
</tr>
