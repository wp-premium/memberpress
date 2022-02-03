//Load the larger zxcvbn.js file asynchronously
(function(){var a;a=function(){var a,b;b=document.createElement("script");b.src=MeprZXCVBN.script_url;b.type="text/javascript";b.async=!0;a=document.getElementsByTagName("script")[0];return a.parentNode.insertBefore(b,a)};null!=window.attachEvent?window.attachEvent("onload",a):window.addEventListener("load",a,!1)}).call(this);

function mepr_score_it($el) {
  //console.log(zxcvbn($el.val()).score);
  var score = zxcvbn($el.val()).score;

  if($el.val().length == 0) {
    $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-nopass');
    $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.indicator + '<br/><small>' + MeprZXCVBN.required + '</small>');
    $el.closest('form').find('.mp-pass-strength').val('0');
  } else {
    switch(score) {
      case 0:
        $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-weakpass');
        $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.very_weak + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.closest('form').find('.mp-pass-strength').val('0');
        break;
      case 1:
        $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-weakpass');
        $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.weak + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.closest('form').find('.mp-pass-strength').val('1');
        break;
      case 2:
        $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-mediumpass');
        $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.medium + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.closest('form').find('.mp-pass-strength').val('2');
        break;
      case 3:
        $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-strongpass');
        $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.strong + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.closest('form').find('.mp-pass-strength').val('3');
        break;
      case 4:
        $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-strongpass');
        $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.very_strong + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.closest('form').find('.mp-pass-strength').val('4');
        break;
      default:
        $el.closest('form').find('.mp-password-strength-display').attr('class', 'mp-password-strength-display mp-nopass');
        $el.closest('form').find('.mp-password-strength-display').html(MeprZXCVBN.indicator + '<br/><small>' + MeprZXCVBN.required + '</small>');
        $el.closest('form').find('.mp-pass-strength').val('0');
        break;
    }
  }
}

jQuery(function($) {
  //Signup forms
  function mepr_check() {
    if(typeof zxcvbn !== 'undefined') {
      if($('.mepr-password').length && $('.mepr-password').val().length > 0) {
        mepr_score_it($('.mepr-password'));
      }
    } else {
      setTimeout(mepr_check, 100);
    }
  }
  mepr_check();

  var selectors = '.mepr-password, .mepr-new-password, .mepr-forgot-password';

  document.body.addEventListener('keyup', function (e) {
    if(e.target && e.target.matches && e.target.matches(selectors)) {
      mepr_score_it($(e.target));
    }
  });

  document.body.addEventListener('paste', function (e) {
    if(e.target && e.target.matches && e.target.matches(selectors)) {
      e.preventDefault();
      e.target.value = (e.clipboardData || window.clipboardData).getData('text');
      mepr_score_it($(e.target));
    }
  });
});
