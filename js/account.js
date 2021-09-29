jQuery(document).ready(function ($) {
  $('.mepr-open-resume-confirm, .mepr-open-cancel-confirm').magnificPopup({
    type: 'inline',
    closeBtnInside: false
  });

  $('.mepr-confirm-no').on('click', function(e) {
    $.magnificPopup.close();
  });

  $('.mepr-confirm-yes').on('click', function(e){
    location.href = $(this).data('url');
  });

  $('.mepr-open-upgrade-popup').magnificPopup({
    type: 'inline',
    closeBtnInside: false
  });

  $('.mepr-upgrade-cancel').on('click', function(e) {
    $.magnificPopup.close();
  });

  $('.mepr-upgrade-buy-now').on('click', function(e){
    var id = $(this).data('id');
    var selector = 'select#mepr-upgrade-dropdown-' + id;
    var url = $(selector).val();
    location.href = url;
  });

  $('body').on('click', '.mepr-account-form .mepr-submit', function (e) {
    e.preventDefault();
    var form = $(this).closest('.mepr-account-form');
    var submittedTelInputs = document.querySelectorAll(".mepr-tel-input");
    for (var i = 0; i < submittedTelInputs.length; i++) {
      var iti = window.intlTelInputGlobals.getInstance(submittedTelInputs[i]);
      submittedTelInputs[i].value = iti.getNumber();
    }
    form.submit();
  });

});
