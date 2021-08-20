jQuery(function ($) {
  var $addonsContainer = $('#mepr-addons-container');

  if ($addonsContainer.length) {
    if (window.List) {
      var list = new List($addonsContainer[0], {
        valueNames: ['mepr-addon-name'],
        listClass: 'mepr-addons'
      });

      $('#mepr-addons-search').on('keyup', function () {
        list.search($(this).val());
      })
      .on('input', function () {
        // Used to detect click on HTML5 clear button
        if ($(this).val() === '') {
          list.search('');
        }
      });
    }

    if ($.fn.matchHeight) {
      $('.mepr-addon .mepr-addon-details').matchHeight({
        byRow: false
      });
    }

    var icons = {
      activate: '<i class="mp-icon mp-icon-toggle-on mp-flip-horizontal" aria-hidden="true"></i>',
      deactivate: '<i class="mp-icon mp-icon-toggle-on" aria-hidden="true"></i>',
      install: '<i class="mp-icon mp-icon-cloud-download" aria-hidden="true"></i>',
      spinner: '<i class="mp-icon mp-icon-spinner animate-spin" aria-hidden="true"></i>',
    };

    $(document).on('click', '.mepr-addon-action button', function () {
      var $button = $(this),
        $addon = $button.closest('.mepr-addon'),
        originalButtonHtml = $button.html(),
        originalButtonWidth = $button.width(),
        type = $button.data('type'),
        action,
        statusClass,
        statusText,
        buttonHtml,
        successText;

      if ($addon.hasClass('mepr-addon-status-active')) {
        action = 'mepr_addon_deactivate';
        statusClass = 'mepr-addon-status-inactive';
        statusText = MeprAddons.inactive;
        buttonHtml = icons.activate + MeprAddons.activate;
      } else if ($addon.hasClass('mepr-addon-status-inactive')) {
        action = 'mepr_addon_activate';
        statusClass = 'mepr-addon-status-active';
        statusText = MeprAddons.active;
        buttonHtml = icons.deactivate + MeprAddons.deactivate;
      } else if ($addon.hasClass('mepr-addon-status-download')) {
        action = 'mepr_addon_install';
        statusClass = 'mepr-addon-status-active';
        statusText = MeprAddons.active;
        buttonHtml = icons.deactivate + MeprAddons.deactivate;
      } else {
        return;
      }

      $button.prop('disabled', true).html(icons.spinner).addClass('mepr-loading').width(originalButtonWidth);

      var data = {
        action: action,
        _ajax_nonce: MeprAddons.nonce,
        plugin: $button.data('plugin'),
        type: type
      };

      var handleError = function (message) {
        $addon.find('.mepr-addon-actions').append($('<div class="mepr-addon-message mepr-addon-message-error">').text(message));
        $button.html(originalButtonHtml);
      };

      $.ajax({
        type: 'POST',
        url: MeprAddons.ajax_url,
        dataType: 'json',
        data: data
      })
      .done(function (response) {
        if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
          handleError(type === 'plugin' ? MeprAddons.plugin_install_failed : MeprAddons.install_failed);
        } else if (!response.success) {
          if (typeof response.data == 'object' && response.data[0] && response.data[0].code) {
            handleError(type === 'plugin' ? MeprAddons.plugin_install_failed : MeprAddons.install_failed);
          } else {
            handleError(response.data);
          }
        } else {
          if (action === 'mepr_addon_install') {
            $button.data('plugin', response.data.basename);
            successText = response.data.message;

            if (!response.data.activated) {
              statusClass = 'mepr-addon-status-inactive';
              statusText = MeprAddons.inactive;
              buttonHtml = icons.activate + MeprAddons.activate;
            }
          } else {
            successText = response.data;
          }

          $addon.find('.mepr-addon-actions').append($('<div class="mepr-addon-message mepr-addon-message-success">').text(successText));

          $addon.removeClass('mepr-addon-status-active mepr-addon-status-inactive mepr-addon-status-download')
                .addClass(statusClass);

          $addon.find('.mepr-addon-status-label').text(statusText);

          $button.html(buttonHtml);
        }
      })
      .fail(function () {
        handleError(type === 'plugin' ? MeprAddons.plugin_install_failed : MeprAddons.install_failed);
      })
      .always(function () {
        $button.prop('disabled', false).removeClass('mepr-loading').width('auto');

        // Automatically clear add-on messages after 3 seconds
        setTimeout(function() {
          $addon.find('.mepr-addon-message').remove();
        }, 3000);
      });
    });
  }
});
