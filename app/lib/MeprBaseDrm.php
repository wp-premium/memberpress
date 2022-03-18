<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );}

abstract class MeprBaseDrm {

  public function __construct() {
    $this->init();
  }

  protected $event      = null;
  protected $event_name = '';
  protected $drm_status = '';

  public function is_locked() {
    return MeprDrmHelper::is_locked( $this->drm_status );
  }

  public function is_medium() {
    return MeprDrmHelper::is_medium( $this->drm_status );
  }

  public function is_low() {
    return MeprDrmHelper::is_low( $this->drm_status );
  }

  protected function init() {
    $this->drm_status = '';
  }

  protected function set_status( $status ) {
    $this->drm_status = $status;

    // set global value.
    MeprDrmHelper::set_status( $status );
  }

  public function create_event() {
    $drm  = new MeprDrm( 1 );
    $data = array(
      'id' => 1,
    );

    MeprEvent::record( $this->event_name, $drm, $data );
  }

  protected function update_event( $event, $data ) {
    if ( $event->rec->id == 0 ) {
      return;
    }

    if ( is_array( $data ) || is_object( $data ) ) {
      $event->args = json_encode( $data );
    }
    return $event->store();
  }

  public function run_site_health_drm() {

    $drm_status = MeprDrmHelper::get_status();

    $vars = MeprDrmHelper::get_info( $drm_status, $this->event_name, 'site_health' );
    extract( $vars );

    $result = array(
      'label'       => $heading,
      'status'      => 'critical',
      'badge'       => array(
        'label' => $label,
        'color' => $color,
      ),
      'description' => $message,
      'actions'     => sprintf(
        '<p><a href="%s" target="_blank" rel="noopener">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
        /* translators: Documentation explaining debugging in WordPress. */
        esc_url( $support_link ),
        $help_message,
        /* translators: Accessibility text. */
        __( '(opens in a new tab)', 'memberpress' )
      ),
      'test'        => 'run_site_health_drm',
    );

    return $result;
  }

  public function maybe_create_event() {

    // Check if wp_mepr_events has an entry within the last 30 days, if not, insert.
    $event = MeprEvent::latest_by_elapsed_days( $this->event_name, 30 );

    // make sure we always have an event within last 30 days.
    if ( ! $event ) {
      $this->create_event();
    }
  }

  public function drm_event( $event, $days, $drm_status ) {

    $this->event = $event;

    $event_data    = MeprDrmHelper::parse_event_args( $event->args, true );
    $drm_event_key = MeprDrmHelper::get_status_key( $drm_status );

    // just make sure we run this once.
    if ( ! isset( $event_data[ $drm_event_key ] ) ) {

      // send email
      $this->send_email( $drm_status );

      // create in-plugin notification
      $this->create_inplugin_notification( $drm_status );

      // mark event complete.
      $event_data[ $drm_event_key ] = MeprUtils::mysql_now();

      $this->update_event( $event, $event_data );
    }

    add_action( 'admin_notices', array( $this, 'admin_notices' ), 11 );
    add_filter( 'site_status_tests', array( $this, 'site_health_status' ), 11 );

    if ( MeprDrmHelper::is_locked() ) {

      if( $this->is_mepr_page() ){
        add_action( 'admin_footer', array( $this, 'admin_footer' ), 20 );
      }
      add_action( 'admin_body_class', array( $this, 'admin_body_class' ), 20 );
    }
  }

  private function is_mepr_page() {

    if( ! isset( $_GET['page'] ) ){
      return false;
    }

    if( $_GET['page'] == 'memberpress-members' ){
      return true;
    }

    return false;
  }

  public function admin_body_class( $classes ) {
    $classes .= ' mepr-locked';
    if( $this->is_mepr_page() ){
      $classes .= ' mepr-notice-modal-active';
    }
    return $classes;
  }

  public function admin_footer() {
    MeprView::render( '/admin/drm/modal' );
  }

  public function admin_notices() {

    if ( ! $this->event || ! is_object( $this->event ) ) {
      return;
    }

    if ( MeprDrmHelper::is_locked() && $this->is_mepr_page() ) {
      return;
    }

    $drm_status = MeprDrmHelper::get_status();

    if ( '' !== $drm_status ) {
      $drm_info               = MeprDrmHelper::get_info( $drm_status, $this->event_name, 'admin_notices' );
      $drm_info['notice_key'] = MeprDrmHelper::get_status_key( $drm_status );
      $drm_info['notice_view'] = $drm_info['admin_notice_view'];
      $drm_info['event_name'] = $this->event_name;

      $notice_user_key = MeprDrmHelper::prepare_dismissable_notice_key( $drm_info['notice_key'] );
      $event_data = MeprDrmHelper::parse_event_args( $this->event->args );

      $is_dismissed = MeprDrmHelper::is_dismissed( $event_data, $notice_user_key );
      if ( ! $is_dismissed ) {
        echo'<style>.drm-mepr-activation-warning{display:none;}</style>';
        MeprView::render( '/admin/drm/notices/notice', get_defined_vars() );
      }
    }
  }

  protected function send_email( $drm_status ) {
    $drm_info = MeprDrmHelper::get_info( $drm_status, $this->event_name, 'email' );
    if ( empty( $drm_info['heading'] ) ) {
      return;
    }

    $subject = $drm_info['heading'];

    $message = MeprView::get_string( '/admin/drm/email', get_defined_vars() );

    $headers = array(
      sprintf( 'Content-type: text/html; charset=%s', apply_filters( 'wp_mail_charset', get_bloginfo( 'charset' ) ) ),
    );

    MeprUtils::wp_mail_to_admin( $subject, $message, $headers );
  }

  protected function create_inplugin_notification( $drm_status ) {

    $drm_info = MeprDrmHelper::get_info( $drm_status, $this->event_name, 'inplugin' );
    if ( empty( $drm_info['heading'] ) ) {
      return;
    }

    $notifications = new MeprNotifications();
    $notifications->add(
      array(
        'id'      => 'event_' . time(),
        'title'   => $drm_info['heading'],
        'content' => $drm_info['message'],
        'type'    => 'mepr-drm',
        'segment' => '',
        'saved'   => time(),
        'end'     => '',
        'icon'    => 'https://memberpress.com/wp-content/uploads/notifications/icons/Alert-Icon-150x150.png',
        'buttons' => array(
          'main' => array(
            'text'   => 'Contact Us',
            'url'    => $drm_info['support_link'],
            'target' => '_blank',
          ),
        ),
        'plans'   => array( MEPR_EDITION ),
      )
    );
  }

  abstract function run();
} //End class