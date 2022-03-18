<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );}

class MeprDrmNokey extends MeprBaseDrm {

  public function __construct() {
    parent::__construct();
    $this->event_name = MeprDrmHelper::NO_LICENSE_EVENT;
    add_action( 'mepr_drm_no_license_event', array( $this, 'drm_event' ), 10, 3 );
  }

  public function run() {
    $event = MeprEvent::latest( $this->event_name );

    if ( $event ) {
      $days = MeprDrmHelper::days_elapsed( $event->created_at );
      if ( $days >= 14 && $days <= 20 ) {
        $this->set_status( MeprDrmHelper::DRM_LOW );
      } elseif ( $days >= 21 && $days <= 29 ) {
        $this->set_status( MeprDrmHelper::DRM_MEDIUM );
      } elseif ( $days >= 30 ) {
        $this->set_status( MeprDrmHelper::DRM_LOCKED );
      }
    }

    // DRM status detected.
    if ( '' !== $this->drm_status ) {
      do_action( 'mepr_drm_no_license_event', $event, $days, $this->drm_status );
    }
  }

  public function site_health_status( $tests ) {

    $drm_status = MeprDrmHelper::get_status();

    if ( $drm_status == '' ) {
      return $tests; // bail.
    }

    $tests['direct']['memberpress_drm_no_key'] = array(
      'label' => __( 'MemberPress - Licence Key Missing', 'memberpress' ),
      'test'  => array( $this, 'run_site_health_drm' ),
    );

    return $tests;
  }
} //End class
