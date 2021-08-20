jQuery(function ($) {
  var $deactivateLink = $('#the-list').find('[data-slug="' + MeprDeactivationSurvey.slug + '"] span.deactivate a'),
    $popup = $('#mepr-deactivation-survey'),
    $form = $popup.find('form'),
    popupOpen = false,
    closePopup = function () {
      $popup.hide();
      popupOpen = false;
      $deactivateLink.focus();
    };

  $deactivateLink.click(function (e) {
    e.preventDefault();
    $popup.show();
    popupOpen = true;
    $form.find('.mepr-deactivation-survey-option-radio').first().focus();
  });

  $form.find('.mepr-deactivation-survey-button-skip').click(function () {
    window.location.href = $deactivateLink.attr('href');
  });

  $popup.find('.mepr-deactivation-survey-popup-close').click(closePopup);

  $form.find('.mepr-deactivation-survey-option-radio').change(function () {
    $form.find('.mepr-deactivation-survey-error').remove();
    $form.find('.mepr-deactivation-survey-option-details').hide();

    $(this).closest('.mepr-deactivation-survey-option').find('.mepr-deactivation-survey-option-details').show();
  });

  $form.submit(function (e) {
    e.preventDefault();

    var $selectedRadio = $form.find('.mepr-deactivation-survey-option-radio:checked'),
      $selectedOption = $selectedRadio.closest('.mepr-deactivation-survey-option');

    $form.find('.mepr-deactivation-survey-error').remove();

    if (!$selectedRadio.length) {
      $form.find('.mepr-deactivation-survey-buttons').prepend($('<div class="mepr-deactivation-survey-error">').text(MeprDeactivationSurvey.pleaseSelectAnOption));
      return;
    }

    var $button = $form.find('.mepr-deactivation-survey-buttons > button');

    $button.width($button.width()).html('<i class="mp-icon mp-icon-spinner animate-spin" aria-hidden="true"></i>');

    var data = {
      code: $selectedRadio.val(),
      reason: $selectedOption.find('.mepr-deactivation-survey-option-label').text().trim(),
      details: $selectedOption.find('.mepr-deactivation-survey-option-details').val(),
      site: MeprDeactivationSurvey.siteUrl,
      plugin: 'MemberPress'
    };

    $.ajax({
      method: 'POST',
      url: MeprDeactivationSurvey.apiUrl,
      data: data
    })
    .always(function () {
      window.location.href = $deactivateLink.attr('href');
    });
  });

  $(document).keyup(function (e) {
    if (popupOpen && e.keyCode === 27) {
      closePopup();
    }
  });
});
