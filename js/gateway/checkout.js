jQuery(document).ready(function($) {
  $('body').on('mepr-checkout-submit', function(e, payment_form) {
    payment_form.get(0).submit();
  });
  $('input.cc-number').on('change blur', function (e) {
    var num = $(this).val().replace(/ /g, '');
    $(this).next('input.mepr-cc-num').val( num );
  });
  $('input.cc-exp').on('change blur', function (e) {
    var exp = $(this).payment('cardExpiryVal');
    $( 'input.cc-exp-month' ).val( exp.month );
    $( 'input.cc-exp-year' ).val( exp.year );
  });
});
