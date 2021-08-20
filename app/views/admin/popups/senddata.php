<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div id="mepr-senddata-popup" class="mfp-hide mepr-popup mepr-auto-open">
  <p><img src="<?php echo MEPR_IMAGES_URL . '/memberpress-logo.svg'; ?>" width="400" height="64" /></p>
  <h2><?php _e('Help Us Improve MemberPress', 'memberpress'); ?></h2>
  <p><?php _e('Did you know that you can do something very easily to help us continue to improve MemberPress?', 'memberpress'); ?></p>
  <p><?php _e('Click "I Agree" below to enable MemberPress to send <em>anonymous</em> usage data back to our developers so you can help us continue to refine this awesome membership plugin.', 'memberpress'); ?></p>

  <p><?php _e('Thanks for your support!', 'memberpress'); ?></p>
  <div>&nbsp;</div>

  <center>
    <button data-popup="senddata" data-href="" class="mepr-stop-popup mepr-enable-senddata button-primary"><?php _e('I Agree', 'memberpress'); ?></button>
    <div>&nbsp;</div>
    <small><a href="" data-popup="senddata" class="mepr-stop-popup mepr-disable-senddata"><?php _e('Nope', 'memberpress'); ?></a></small>
  </center>

</div>
