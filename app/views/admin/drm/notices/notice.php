<?php if ( ! defined( 'ABSPATH' ) ) {die( 'You are not allowed to call this page directly.' );}
$notice = MeprView::get_string( '/admin/drm/notices/' . $drm_info['notice_view'], get_defined_vars() );
if( $notice == '' ){
  return;
}
?>
<div class="notice notice-error is-dismissible mepr-notice-dismiss-24hour" data-notice="<?php echo $drm_info['notice_key']; ?>" data-secret="<?php echo sha1( $drm_info['notice_key'] ); ?>-<?php echo sha1( $drm_info['event_name'] ); ?>">
  <?php
  echo $notice;
  ?>
</div>