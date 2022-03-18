<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );}

class MeprDrmInvalid extends MeprBaseDrm {

  public function __construct() {
    parent::__construct();
    $this->event_name = MeprDrmHelper::INVALID_LICENSE_EVENT;

    add_action( 'mepr_drm_invalid_license_event', array( $this, 'drm_event' ), 10, 3 );
  }

  public function run() {
    $event = MeprEvent::latest( $this->event_name );

    if ( $event ) {
      $days = MeprDrmHelper::days_elapsed( $event->created_at );
      if ( $days >= 7 && $days <= 20 ) {
        $this->set_status( MeprDrmHelper::DRM_MEDIUM );
      } elseif ( $days >= 21 ) {
        $this->set_status( MeprDrmHelper::DRM_LOCKED );
      }
    }

    // DRM status detected.
    if ( '' !== $this->drm_status ) {
      do_action( 'mepr_drm_invalid_license_event', $event, $days, $this->drm_status );
    }
  }

  public function site_health_status( $tests ) {

    $drm_status = MeprDrmHelper::get_status();

    if ( $drm_status == '' ) {
      return $tests; // bail.
    }

    $tests['direct']['memberpress_drm_invalid_key'] = array(
      'label' => __( 'MemberPress - Invalid License', 'memberpress' ),
      'test'  => array( $this, 'run_site_health_drm' ),
    );

    return $tests;
  }

} //End class
