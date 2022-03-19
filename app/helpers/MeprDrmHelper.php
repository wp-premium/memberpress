<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );}

class MeprDrmHelper {

  const NO_LICENSE_EVENT      = 'no-license';
  const INVALID_LICENSE_EVENT = 'invalid-license';

  const DRM_LOW    = 'low';
  const DRM_MEDIUM = 'medium';
  const DRM_LOCKED = 'locked';

  private static $drm_status = '';
  private static $drm_links  = null;
  private static $fallback_links = array(
    'account' => 'https://memberpress.com/account/',
    'support' => 'https://memberpress.com/support/',
    'pricing' => 'https://memberpress.com/pricing/',
  );

  public static function set_status( $status ) {
    self::$drm_status = $status;
  }

  public static function get_status() {
    return self::$drm_status;
  }

  public static function has_key() {
    $mepr_options = MeprOptions::fetch();
    $key          = '';
    if ( isset( $mepr_options->mothership_license ) ) {
      $key = $mepr_options->mothership_license;
    }
    return ! empty( $key );
  }

  public static function is_aov() {
    $aov = get_option( 'mepr_activation_override' );

    if ( ! empty( $aov ) ) {
      return true; // Valid license.
    }

    return false;
  }

  public static function is_valid() {

    if ( self::is_aov() || MeprUpdateCtrl::is_activated() ) {
      return true; // Valid license
    }

    if ( ! self::has_key() ) {
      return false;
    }

    $license = get_site_transient( 'mepr_license_info' );

    if ( ! isset( $license['license_key'] ) ) {
      return false;  // invalid license.
    }

    if ( 'enabled' != $license['license_key']['status'] ) {
      return false; // invalid license.
    }

    // Expiry is not set. It is unlimited.
    if ( is_null( $license['license_key']['expires_at'] ) ) {
      return true; // valid license.
    }

    $expiry_stamp = strtotime( $license['license_key']['expires_at'] );

    // License has a valid expiry date and it is in future?
    if ( $expiry_stamp && $expiry_stamp >= strtotime( 'Y-m-d' ) ) {
      return true; // valid license.
    }

    return false; // invalid license.
  }

  public static function days_elapsed( $created_at ) {

    $timestamp = strtotime( $created_at );

    if ( false === $timestamp ) {
      return 0; // invalid timestamp.
    }

    $start_date = new DateTime( date( 'Y-m-d' ) );
    $end_date   = new DateTime( date( 'Y-m-d', $timestamp ) );
    $difference = $end_date->diff( $start_date );

    return absint( $difference->format( '%a' ) );
  }


  protected static function maybe_drm_status( $drm_status = '' ) {
    if ( empty( $drm_status ) ) {
      $drm_status = self::$drm_status;
    }

    return $drm_status;
  }

  public static function is_locked( $drm_status = '' ) {
    return ( self::DRM_LOCKED === self::maybe_drm_status( $drm_status ) );
  }

  public static function is_medium( $drm_status = '' ) {
    return ( self::DRM_MEDIUM === self::maybe_drm_status( $drm_status ) );
  }

  public static function is_low( $drm_status = '' ) {
    return ( self::DRM_LOW === self::maybe_drm_status( $drm_status ) );
  }

  public static function get_info( $drm_status, $event_name, $purpose ) {

    $out = array();
    switch ( $event_name ) {
      case self::NO_LICENSE_EVENT:
        $out = self::drm_info_no_license( $drm_status, $purpose );
        break;
      case self::INVALID_LICENSE_EVENT:
        $out = self::drm_info_invalid_license( $drm_status, $purpose );
        break;
      default:
    }

    return $out;
  }

  public static function get_status_key( $drm_status ) {

    $out = '';
    switch ( $drm_status ) {
      case self::DRM_LOW:
        $out = 'dl';
        break;
      case self::DRM_MEDIUM:
        $out = 'dm';
        break;
      case self::DRM_LOCKED:
        $out = 'dll';
        break;
      default:
    }

    return $out;
  }

  protected static function get_drm_links() {

    if ( self::$drm_links === null ) {
      self::$drm_links = array(
        self::DRM_LOW    => array(
          'email'   => array(
            'home'    => 'https://memberpress.com/drmlow/email',
            'account' => 'https://memberpress.com/drmlow/email/acct',
            'support' => 'https://memberpress.com/drmlow/email/support',
            'pricing' => 'https://memberpress.com/drmlow/email/pricing',
          ),
          'general' => array(
            'home'    => 'https://memberpress.com/drmlow/ipm',
            'account' => 'https://memberpress.com/drmlow/ipm/account',
            'support' => 'https://memberpress.com/drmlow/ipm/support',
            'pricing' => 'https://memberpress.com/drmlow/ipm/pricing',
          ),
        ),
        self::DRM_MEDIUM => array(
          'email'   => array(
            'home'    => 'https://memberpress.com/drmmed/email',
            'account' => 'https://memberpress.com/drmmed/email/acct',
            'support' => 'https://memberpress.com/drmmed/email/support',
            'pricing' => 'https://memberpress.com/drmmed/email/pricing',
          ),
          'general' => array(
            'home'    => 'https://memberpress.com/drmmed/ipm',
            'account' => 'https://memberpress.com/drmmed/ipm/account',
            'support' => 'https://memberpress.com/drmmed/ipm/support',
            'pricing' => 'https://memberpress.com/drmmed/ipm/pricing',
          ),
        ),
        self::DRM_LOCKED => array(
          'email'   => array(
            'home'    => 'https://memberpress.com/drmlock/email',
            'account' => 'https://memberpress.com/drmlock/email/acct',
            'support' => 'https://memberpress.com/drmlock/email/support',
            'pricing' => 'https://memberpress.com/drmlock/email/pricing',
          ),
          'general' => array(
            'home'    => 'https://memberpress.com/drmlock/ipm',
            'account' => 'https://memberpress.com/drmlock/ipm/account',
            'support' => 'https://memberpress.com/drmlock/ipm/support',
            'pricing' => 'https://memberpress.com/drmlock/ipm/pricing',
          ),
        ),
      );
    }

    return self::$drm_links;
  }

  public static function get_drm_link( $drm_status, $purpose, $type ) {
    $drm_links = self::get_drm_links();
    if ( isset( $drm_links[ $drm_status ] ) ) {
      if ( ! isset( $drm_links[ $drm_status ][ $purpose ] ) ) {
        $purpose = 'general';
      }

      if (  isset( $drm_links[ $drm_status ][ $purpose ] ) ) {
        $data = $drm_links[ $drm_status ][ $purpose ];
        if ( isset( $data[ $type ] ) ) {
          return $data[ $type ];
        }
      }
    }

    // fallback links.
    if ( isset( self::$fallback_links[$type] ) ) {
      return self::$fallback_links[$type];
    }

    return '';
  }

  protected static function drm_info_no_license( $drm_status, $purpose ) {

    $account_link = self::get_drm_link( $drm_status, $purpose, 'account' );
    $support_link = self::get_drm_link( $drm_status, $purpose, 'support' );
    $pricing_link = self::get_drm_link( $drm_status, $purpose, 'pricing' );

    switch ( $drm_status ) {
      case self::DRM_LOW:
        $admin_notice_view = 'low_warning';
        $heading           = __( 'MemberPress: Did You Forget Something?', 'memberpress' );
        $color             = 'orange';
        $simple_message    = __( 'Oops! It looks like your MemberPress license key is missing. Here\'s how to fix the problem fast and easy:', 'memberpress' );
        $help_message      = __( 'We’re here if you need any help.', 'memberpress' );
        $label             = __( 'Alert', 'memberpress' );
        $activation_link   = admin_url( 'admin.php?page=memberpress-options#mepr-license' );
        $message           = sprintf(
          '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
          $simple_message,
          __( 'Grab your key from your <a href="' . esc_url( $account_link ) . '">Account Page</a>.', 'memberpress' ),
          __( '<a href="' . esc_url( $activation_link ) . '">Click here</a> to enter and activate it.', 'memberpress' ),
          __( 'That’s it!', 'memberpress' )
        );
        break;
      case self::DRM_MEDIUM:
        $admin_notice_view = 'medium_warning';
        $heading           = __( 'MemberPress: WARNING! Your Business is at Risk', 'memberpress' );
        $color             = 'orange';
        $simple_message    = __( 'To continue using MemberPress without interruption, you need to enter your  license key right away. Here’s how:', 'memberpress' );
        $help_message      = __( 'Let us know if you need assistance.', 'memberpress' );
        $label             = __( 'Critical', 'memberpress' );
        $activation_link   = admin_url( 'admin.php?page=memberpress-options#mepr-license' );
        $message           = sprintf(
          '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
          $simple_message,
          __( 'Grab your key from your <a href="' . esc_url( $account_link ) . '">Account Page</a>.', 'memberpress' ),
          __( '<a href="' . esc_url( $activation_link ) . '">Click here</a> to enter and activate it.', 'memberpress' ),
          __( 'That’s it!', 'memberpress' )
        );
        break;
      case self::DRM_LOCKED:
        $admin_notice_view = 'locked_warning';
        $heading           = __( 'ALERT! MemberPress Backend is Deactivated', 'memberpress' );
        $color             = 'red';
        $simple_message    = __( 'Because your license key is inactive, you can no longer manage MemberPress on the backend (e.g., you can\'t do things like issue customer refunds or add new members). Fortunately, this problem is easy to fix!', 'memberpress' );
        $help_message      = __( 'We\'re here to help you get things up and running. Let us know if you need assistance.', 'memberpress' );
        $label             = __( 'Critical', 'memberpress' );
        $activation_link   = admin_url( 'admin.php?page=memberpress-members' );
        $message           = sprintf(
          '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
          $simple_message,
          __( 'Grab your key from your <a href="' . esc_url( $account_link ) . '">Account Page</a>.', 'memberpress' ),
          __( '<a href="' . esc_url( $activation_link ) . '">Click here</a> to enter and activate it.', 'memberpress' ),
          __( 'That’s it!', 'memberpress' )
        );
        break;
      default:
        $heading           = '';
        $color             = '';
        $message           = '';
        $help_message      = '';
        $label             = '';
        $activation_link   = '';
        $admin_notice_view = '';
        $simple_message    = '';
    }

    return compact( 'heading', 'color', 'message', 'simple_message', 'help_message', 'label', 'activation_link', 'account_link', 'support_link', 'pricing_link', 'admin_notice_view' );
  }

  protected static function drm_info_invalid_license( $drm_status, $purpose ) {

    $account_link = self::get_drm_link( $drm_status, $purpose, 'account' );
    $support_link = self::get_drm_link( $drm_status, $purpose, 'support' );
    $pricing_link = self::get_drm_link( $drm_status, $purpose, 'pricing' );

    switch ( $drm_status ) {
      case self::DRM_MEDIUM:
        $admin_notice_view = 'medium_warning';
        $heading           = __( 'MemberPress: WARNING! Your Business is at Risk', 'memberpress' );
        $color             = 'orange';
        $simple_message    = __( 'Your MemberPress license key is expired, but is required to continue using MemberPress. Fortunately, it’s easy to renew your license key. Just do the following:', 'memberpress' );
        $help_message      = __( 'Let us know if you need assistance.', 'memberpress' );
        $label             = __( 'Critical', 'memberpress' );
        $activation_link   = admin_url( 'admin.php?page=memberpress-options#mepr-license' );
        $message           = sprintf(
          '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
          $simple_message,
          __( 'Go to MemberPress.com and make your selection. <a href="' . esc_url( $pricing_link ) . '">Pricing</a>.', 'memberpress' ),
          __( '<a href="' . esc_url( $activation_link ) . '">Click here</a> to enter and activate your new license key.', 'memberpress' ),
          __( 'That’s it!', 'memberpress' )
        );
        break;
      case self::DRM_LOCKED:
        $admin_notice_view = 'locked_warning';
        $label             = __( 'Critical', 'memberpress' );
        $heading           = __( 'ALERT! MemberPress Backend is Deactivated', 'memberpress' );
        $color             = 'red';
        $simple_message    = __( 'Without an active license key, MemberPress cannot be managed on the backend. Your frontend will remain intact, but you can’t: Issue customer refunds, Add new members, Manage memberships. Fortunately, this problem is easy to fix by doing the following: ', 'memberpress' );
        $activation_link   = admin_url( 'admin.php?page=memberpress-members' );
        $message           = sprintf(
          '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li></ul>',
          $simple_message,
          __( 'Go to MemberPress.com and make your selection. <a href="' . esc_url( $pricing_link ) . '">Pricing</a>.', 'memberpress' ),
          __( '<a href="' . esc_url( $activation_link ) . '">Click here</a> to enter and activate your new license key.', 'memberpress' ),
          __( 'That’s it!', 'memberpress' )
        );
        $help_message      = __( 'We’re here to help you get things back up and running. Let us know if you need assistance.', 'memberpress' );
        break;
      default:
        $heading           = '';
        $color             = '';
        $message           = '';
        $help_message      = '';
        $label             = '';
        $activation_link   = '';
        $admin_notice_view = '';
        $simple_message    = '';
    }

    return compact( 'heading', 'color', 'message', 'simple_message', 'help_message', 'label', 'activation_link', 'account_link', 'support_link', 'admin_notice_view', 'pricing_link' );
  }

  public static function parse_event_args( $args ) {
    return json_decode( $args, true );
  }

  public static function prepare_dismissable_notice_key( $notice ) {
    $notice = sanitize_key( $notice );
    return "{$notice}_u" . get_current_user_id();
  }

  public static function is_dismissed( $event_data, $notice_key ) {
    if ( isset( $event_data[ $notice_key ] ) ) {
      $diff = (int) abs( time() - $event_data[ $notice_key ] );
      if ( $diff <= ( HOUR_IN_SECONDS * 24 ) ) {
        return true;
      }
    }

    return false;
  }
} //End class
