jQuery(document).ready(function($) {
  jQuery('.mepr-resend-welcome-email').click( function() {
    jQuery('.mepr-resend-welcome-email-loader').show();

    var data = {
      action: 'mepr_resend_welcome_email',
      uid: jQuery(this).data('uid'),
      nonce: jQuery(this).data('nonce')
    };

    jQuery.post(ajaxurl, data, function(response) {
      jQuery('.mepr-resend-welcome-email-loader').hide();
      jQuery('.mepr-resend-welcome-email-message').text(response);
    });

    return false;
  });

  $('body').on('click', '#submit', function (e) {
    var form = $(this).closest('form');
    var submittedTelInputs = document.querySelectorAll(".mepr-tel-input");
    for (var i = 0; i < submittedTelInputs.length; i++) {
      var iti = window.intlTelInputGlobals.getInstance(submittedTelInputs[i]);
      submittedTelInputs[i].value = iti.getNumber();
    }
  });

  // Dynamically set enctype and the encoding of admin profile form
  $( "form#your-profile" )
  .attr( "enctype", "multipart/form-data" );

  $(".mepr-replace-file").each(function(){
    $(this).closest('td').find('.mepr-file-uploader').hide();
  });

  $('body').on('click', '.mepr-replace-file', function (e) {
    $(this).closest('td').find('.mepr-file-uploader').toggle();
  });
});
