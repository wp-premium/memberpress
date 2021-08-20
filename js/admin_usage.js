jQuery(document).ready(function($) {
  var mepr_enable_or_disable_senddata = function(disable,cb) {
    var args = {
      action: disable ? 'mepr_disable_senddata' : 'mepr_enable_senddata',
      toggle_usage_nonce: MeprUsage.toggle_usage_nonce,
    };

    $.post(ajaxurl, args, cb, 'json');
  };

  $('.mepr-enable-senddata').on('mepr-popup-stopped mepr-popup-delayed', function(e) {
    e.preventDefault();
    mepr_enable_or_disable_senddata(false);
  });

  $('.mepr-disable-senddata').on('mepr-popup-stopped mepr-popup-delayed', function(e) {
    e.preventDefault();
    mepr_enable_or_disable_senddata(true);
  });
});
