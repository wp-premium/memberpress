jQuery(document).ready(function($) {
  if($('#mepruserroles_enabled').is(":checked")) {
    $('div#mepruserroles_enabled_area').show();
  } else {
    $('div#mepruserroles_enabled_area').hide();
  }

  $('#mepruserroles_enabled').click(function() {
    $('div#mepruserroles_enabled_area').slideToggle();
  });
}); //End main document.ready func
