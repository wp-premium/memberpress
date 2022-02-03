<div class="notice is-dismissible" id="mepr_bf_admin_notice" style="border-left-color: #00cee6;">
  <h3><?php echo $heading; ?></h3>
  <p><?php echo $message; ?></p>
  <p>
    <a href="<?php echo $link; ?>" class="button button-primary" target="_blank"><?php echo $button_text; ?></a>
    <a href="#" class="button button-secondary" id="mepr_bf_notice_dismiss" target="_blank"><?php esc_html_e( 'Dismiss', 'memberpress' ); ?></a>
  </p>
</div>
<script>
  jQuery(document).ready(function($) {
    $('body').on('click', '#mepr_bf_admin_notice button.notice-dismiss, #mepr_bf_notice_dismiss', function(event) {
      event.preventDefault();
      $.ajax({
        url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
        type: 'POST',
        data: {
          action: 'mepr_dismiss_gm_notice',
          nonce: "<?php echo wp_create_nonce( 'mepr_dismiss_gm_notice' ); ?>"
        },
      })
      .done(function() {
        $('#mepr_bf_admin_notice').slideUp();
        $('.memberpress-menu-pulse.green').hide();
      });
    });
  });
</script>