var mepr_setup_clipboard = function(clipboard_class) {
  (function($) {
    if(clipboard_class==null) {
      clipboard_class='.mp-clipboardjs';
    }

    $(clipboard_class).each(function(i, el) {
      var $el = $(el),
        copy_text = MeprClipboard.copy_text,
        copied_text = MeprClipboard.copied_text,
        copy_error_text = MeprClipboard.copy_error_text,
        clipboard = new ClipboardJS(el);

      try {
        var instance = $el
          .tooltipster({
            theme: 'tooltipster-borderless',
            content: copy_text,
            trigger: 'custom',
            triggerClose: {
              mouseleave: true,
              touchleave: true
            },
            triggerOpen: {
              mouseenter: true,
              touchstart: true
            }
          })
          .tooltipster('instance');

        clipboard
          .on('success', function(e) {
            instance
              .content(copied_text)
              .one('after', function(){
                instance.content(copy_text);
              });
          })
          .on('error', function(e) {
            instance
              .content(copy_error_text)
              .one('after', function(){
                instance.content(copy_text);
              });
          });
      } catch (e) {
        // With tooltipster <=3.3.0 an error will be caught here, just display a static tooltip
        $el.tooltipster('destroy').tooltipster({
          content: copy_text
        });
      }
    });
  })(jQuery);
};
