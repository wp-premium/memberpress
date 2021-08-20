(function($) {
  $(document).ready(function() {
    // Append the shake icon to the menu li
    $('li.toplevel_page_memberpress').append('<span class="mepr-tooltip mepr-shake-tooltip"><span class="dashicons dashicons-warning mepr-shake-icon"></span><span class="mepr-data-title mepr-hidden">' + MeprShake.tooltip_title + '</span><span class="mepr-data-info mepr-hidden">' + MeprShake.tooltip_body + MeprShake.tooltip_button + '</span></span>');

    // Shake on a loop
    (function meprShakeLoop(i) {
      setInterval(function() {
        var $el = $('span.mepr-shake-icon');

        if($el.hasClass('mepr-shake')) {
          $el.removeClass('mepr-shake');
        } else {
          $el.addClass('mepr-shake');
        }
      }, 5000); // Shake every 10 seconds
    })(1000); // Run the loop 1000 times
  });
})(jQuery);
