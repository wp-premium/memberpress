(function($) {
  __ = wp.i18n.__;

  function mpResetToggle( button, show ) {
    $(button)
      .attr({
        'aria-label': show ? __( 'Show password' ) : __( 'Hide password' )
      })
      .find( '.text' )
        .text( show ? __( 'Show' ) : __( 'Hide' ) )
      .end()
      .find( '.dashicons' )
        .removeClass( show ? 'dashicons-hidden' : 'dashicons-visibility' )
        .addClass( show ? 'dashicons-visibility' : 'dashicons-hidden' );
  }

  $(document).ready( function() {
    $('button.mp-hide-pw').each(function(index, button) {
      $(button).show().on( 'click', function () {
        pass = $(button).prev();
        if ( 'password' === $(pass).attr( 'type' ) ) {
          $(pass).attr( 'type', 'text' );
          mpResetToggle( button, false );
        } else {
          pass.attr( 'type', 'password' );
          mpResetToggle( button, true );
        }
      });
    });
  });
})(jQuery);
