<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-account-login">
  <h3><?php esc_html_e('MemberPress.com Account Login', 'memberpress'); ?></h3>

  <?php if ( $site_uuid && $account_email && $secret ) : ?>

    <div class="mepr-account-connected">

      <h3><?php esc_html_e( 'Connected to MemberPress.com', 'memberpress' ); ?></h3>

      <table>
        <tr>
          <th><?php esc_html_e( 'Account Email', 'memberpress' ); ?></th>
          <td><?php echo esc_html($account_email); ?></td>
        </tr>
        <tr>
          <th><?php esc_html_e( 'Site ID', 'memberpress' ); ?></th>
          <td><?php echo esc_html($site_uuid); ?></td>
        </tr>
      </table>

      <?php

        $disconnect_url = add_query_arg(array(
          'mepr-disconnect' => 'true',
          'nonce' => wp_create_nonce( 'mepr-disconnect' )
        ));

      ?>

      <p>
        <a href="<?php echo esc_url($disconnect_url); ?>" class="button-primary mepr-confirm" data-message="<?php esc_attr_e('Are you sure? This action will disconnect any of your Stripe payment methods, block webhooks from being processed, and prevent you from charging Credit Cards with and being notified of automatic rebills from Stripe.', 'memberpress'); ?>"><?php esc_html_e( 'Disconnect from MemberPress.com', 'memberpress' ); ?></a>
      </p>

    </div>

  <?php else : ?>

    <div class="mepr-account-not-connected">

      <p class="description"><?php esc_html_e( 'Connect your site to MemberPress.com to enable MemberPress Cloud Services!', 'memberpress' ); ?></p>

      <p>
        <a href="<?php echo esc_url(MeprAuthenticatorCtrl::get_auth_connect_url()); ?>" class="button-primary"><?php esc_html_e( 'Connect to MemberPress.com', 'memberpress' ); ?></a>
      </p>

    </div>

  <?php endif; ?>

  <?php MeprHooks::do_action('mepr_account_login_page'); ?>

</div>
