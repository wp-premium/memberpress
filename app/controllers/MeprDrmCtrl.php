<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );}

class MeprDrmCtrl extends MeprBaseCtrl {

  /**
   * Load the hooks for this controller
   */
  public function load_hooks() {
    add_action( 'mepr_license_activated', array( $this, 'drm_license_activated' ) );
    add_action( 'mepr_license_deactivated', array( $this, 'drm_license_deactivated' ) );
    add_action( 'mepr_license_expired', array( $this, 'drm_license_invalid_expired' ) );
    add_action( 'mepr_license_invalidated', array( $this, 'drm_license_invalid_expired' ) );
    add_action( 'wp_ajax_mepr_dismiss_notice_drm', array( $this, 'drm_dismiss_notice' ) );
    add_action( 'wp_ajax_mepr_drm_activate_license', array( $this, 'ajax_drm_activate_license' ) );
    add_action( 'admin_menu', array( $this, 'drm_init' ), 1 );
    add_action( 'admin_init', array( $this, 'drm_throttle' ), 20 );
    add_action( 'admin_footer', array( $this, 'drm_menu_append_alert' ) );
  }

  public function drm_license_activated() {
    delete_option( 'mepr_drm_no_license' );
    delete_option( 'mepr_drm_invalid_license' );

    // delete DRM notices
    $notiications = new MeprNotifications();
    $notiications->dismiss_events( 'mepr-drm' );
  }

  public function drm_license_deactivated() {
    $drm_no_license = get_option( 'mepr_drm_no_license', false );

    if ( ! $drm_no_license ) {
      delete_option( 'mepr_drm_invalid_license' );

      // set no license.
      update_option( 'mepr_drm_no_license', true );

      $drm = new MeprDrmNokey();
      $drm->create_event();
    }
  }

  public function drm_license_invalid_expired() {
    $drm_invalid_license = get_option( 'mepr_drm_invalid_license', false );

    if ( ! $drm_invalid_license ) {
      delete_option( 'mepr_drm_no_license' );

      // set invalid license.
      update_option( 'mepr_drm_invalid_license', true );

      $drm = new MeprDrmInvalid();
      $drm->create_event();
    }
  }

  public static function drm_dismiss_notice() {

    if ( check_ajax_referer( 'mepr_dismiss_notice', false, false ) && isset( $_POST['notice'] ) && is_string( $_POST['notice'] ) ) {
      $notice       = sanitize_key( $_POST['notice'] );
      $secret       = sanitize_key( $_POST['secret'] );
      $secret_parts = explode( '-', $secret );
      $notice_hash  = $secret_parts[0];
      $event_hash   = $secret_parts[1];
      $notice_key   = MeprDrmHelper::prepare_dismissable_notice_key( $notice );

      if ( $notice_hash == sha1( $notice ) ) {
        $event = null;
        if ( sha1( MeprDrmHelper::NO_LICENSE_EVENT ) == $event_hash ) {
          $event = MeprEvent::latest( MeprDrmHelper::NO_LICENSE_EVENT );
        } elseif ( sha1( MeprDrmHelper::INVALID_LICENSE_EVENT ) == $event_hash ) {
          $event = MeprEvent::latest( MeprDrmHelper::INVALID_LICENSE_EVENT );
        }

        if ( $event && is_object( $event ) ) {
          if ( $event->rec->id > 0 ) {
            $event_data                = MeprDrmHelper::parse_event_args( $event->args );
            $event_data[ $notice_key ] = time();
            $event->args               = json_encode( $event_data );
            $event->store();
          }
        }
      }
    }

    wp_send_json_success();
  }


  public function drm_init() {

    if ( MeprDrmHelper::is_valid() ) {
      return; // bail.
    }

    $drm_no_license      = get_option( 'mepr_drm_no_license', false );
    $drm_invalid_license = get_option( 'mepr_drm_invalid_license', false );

    if ( $drm_no_license ) {
      $drm = new MeprDrmNokey();
      $drm->run();
    } elseif ( $drm_invalid_license ) {
      $drm = new MeprDrmInvalid();
      $drm->run();
    }
  }

  public function drm_throttle() {

    if ( wp_doing_ajax() ) {
      return;
    }

    if ( MeprDrmHelper::is_locked() ) {
      $page = isset( $_GET['page'] ) ? $_GET['page'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

      if ( 'memberpress-members' === $page ) {

        $action = isset( $_GET['action'] ) ? $_GET['action'] : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ( 'new' == $action ) {
          wp_die( __( 'Sorry, you are not allowed to access this page.', 'memberpress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        if ( MeprUtils::is_post_request() && 'create' == $action ) {
          wp_die( __( 'Sorry, you are not allowed to access this page.', 'memberpress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
      }
    }
  }

  public function ajax_drm_activate_license() {
    if ( ! MeprUtils::is_post_request() || ! isset( $_POST['key'] ) || ! is_string( $_POST['key'] ) ) {
      wp_send_json_error( sprintf( __( 'An error occurred during activation: %s', 'memberpress' ), __( 'Bad request.', 'memberpress' ) ) );
    }

    if ( ! MeprUtils::is_logged_in_and_an_admin() ) {
      wp_send_json_error( __( 'Sorry, you don\'t have permission to do this.', 'memberpress' ) );
    }

    if ( ! check_ajax_referer( 'mepr_drm_activate_license', false, false ) ) {
      wp_send_json_error( sprintf( __( 'An error occurred during activation: %s', 'memberpress' ), __( 'Security check failed.', 'memberpress' ) ) );
    }

    $mepr_options = MeprOptions::fetch();
    $license_key  = sanitize_text_field( wp_unslash( $_POST['key'] ) );

    try {
      $act = MeprUpdateCtrl::activate_license( $license_key );

      $output = esc_html( $act['message'] );

      wp_send_json_success( $output );
    } catch ( Exception $e ) {
      wp_send_json_error( $e->getMessage() );
    }
  }

  public function drm_menu_append_alert() {

    if ( ! MeprDrmHelper::is_locked() ) {
      return;
    }

    ob_start();
    ?>

  <span class="awaiting-mod">
    <span class="pending-count" id="meprDrmAdminMenuUnreadCount" aria-hidden="true"><?php echo __( '!', 'memberpress' ); ?></span></span>
  </span>

    <?php $output = ob_get_clean(); ?>

  <script>
  jQuery(document).ready(function($) {
    $('li.toplevel_page_memberpress-drm .wp-menu-name').append(`<?php echo $output; ?>`);
  });
  </script>
    <?php
  }

}
