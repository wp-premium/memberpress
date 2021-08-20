<br/>
<h3><?php _e( 'Automatic Updates', 'memberpress' ); ?></h3>
<table class="form-table">
  <tbody>
    <tr valign="top">
      <th scope="row">
        <?php _e( 'Enable automatic, background updates', 'memberpress' ); ?>
        <?php MeprAppHelper::info_tooltip(
          'mepr-senddata',
          __('Enable automatic, background updates', 'memberpress'),
          __('Enabling background updates will automatically update MemberPress to the latest version, or the latest minors version.', 'memberpress') ); ?>
      </th>
      <td>
        <p>
          <input type="radio" class="mepr-auto-update-option" name="<?php echo $mepr_options->auto_updates_str; ?>" id="<?php echo $mepr_options->auto_updates_str; ?>_all" value="all" <?php checked( empty( $mepr_options->auto_updates ) || 'all' === $mepr_options->auto_updates ); ?>>
          <label for="<?php echo $mepr_options->auto_updates_str; ?>_all"><?php _e( '<strong>All Updates (recommended)</strong> - Get the latest features, bug fixes, and security updates as they are released.', 'memberpress' ); ?></label>
        </p>
        <p>
          <input type="radio" class="mepr-auto-update-option" name="<?php echo $mepr_options->auto_updates_str; ?>" id="<?php echo $mepr_options->auto_updates_str; ?>_minor" value="minor" <?php checked( $mepr_options->auto_updates, 'minor' ); ?>>
          <label for="<?php echo $mepr_options->auto_updates_str; ?>_minor"><?php _e( '<strong>Minor Updates Only</strong> - Get bug fixes and security updates, but not major features.', 'memberpress' ); ?></label>
        </p>
        <p>
          <input type="radio" class="mepr-auto-update-option" name="<?php echo $mepr_options->auto_updates_str; ?>" id="<?php echo $mepr_options->auto_updates_str; ?>_none" value="none" <?php checked( $mepr_options->auto_updates, 'none' ); ?>>
          <label for="<?php echo $mepr_options->auto_updates_str; ?>_none"><?php _e( '<strong>None</strong> - Manually update everything.', 'memberpress' ); ?></label>
        </p>
        <input type="hidden" id="<?php echo $mepr_options->auto_updates_str; ?>_nonce" value="<?php echo wp_create_nonce('mp-auto-updates'); ?>">
      </td>
    </tr>
  </tbody>
</table>