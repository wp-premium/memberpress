<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mepr-page-title"><?php _e('MemberPress.com Account Login', 'memberpress'); ?></div>

<?php

  $account_email = get_option( 'mepr_authenticator_account_email' );
  $secret = get_option( 'mepr_authenticator_secret_token' );
  $site_uuid = get_option( 'mepr_authenticator_site_uuid' );

?>

<?php if ( $site_uuid && $account_email && $secret ) : ?>

  <div class="mepr-account-connected">

    <h3><?php _e( 'Connected to MemberPress.com', 'memberpress' ); ?></h3>

    <table>
      <tr>
        <th><?php _e( 'Account Email', 'memberpress' ); ?></th>
        <td><?php echo $account_email; ?></td>
      </tr>
      <tr>
        <th><?php _e( 'Site ID', 'memberpress' ); ?></th>
        <td><?php echo $site_uuid; ?></td>
      </tr>
    </table>

    <?php

      $disconnect_params = array(
        'nonce' => wp_create_nonce( 'mepr-connect' )
      );

      $disconnect_url = add_query_arg(array(
        'mepr-disconnect' => 'true',
        'nonce' => wp_create_nonce( 'mepr-disconnect' )
      ));

    ?>

    <p>
      <a href="<?php echo $disconnect_url; ?>" class="button-primary mepr-confirm" data-message="<?php _e('Are you sure? This action will disconnect any of your Stripe payment methods, block webhooks from being processed, and prevent you from charging Credit Cards with and being notified of automatic rebills from Stripe.', 'memberpress'); ?>"><?php _e( 'Disconnect from MemberPress.com', 'memberpress' ); ?></a>
    </p>

  </div>

<?php else : ?>

  <div class="mepr-account-not-connected">

    <p class="description"><?php _e( 'Connect your site to MemberPress.com to enable MemberPress Cloud Services!', 'memberpress' ); ?></p>

    <p>
      <a href="<?php echo MeprAuthenticatorCtrl::get_auth_connect_url(); ?>" class="button-primary"><?php _e( 'Connect to MemberPress.com', 'memberpress' ); ?></a>
    </p>

  </div>

<?php endif; ?>

<?php MeprHooks::do_action('mepr_account_login_page'); ?>
