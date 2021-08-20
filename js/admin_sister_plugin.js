jQuery(function ($) {
  $('.mepr-sister-plugin-step-button button').on('click', function () {
    var $button = $(this),
      $step = $button.closest('.mepr-sister-plugin-step'),
      $plugin = $button.closest('.mepr-sister-plugin'),
      config = $plugin.data('config'),
      originalButtonHtml = $button.html(),
      originalButtonWidth = $button.width(),
      action,
      plugin;

    if (config.active) {
      return;
    } else if (config.installed) {
      action = 'mepr_addon_activate';
      plugin = config.slug;
    } else {
      action = 'mepr_addon_install';
      plugin = config.url;
    }

    $button.prop('disabled', true).html('<i class="mp-icon mp-icon-spinner animate-spin" aria-hidden="true"></i>').width(originalButtonWidth);

    var data = {
      action: action,
      _ajax_nonce: MeprSisterPlugin.nonce,
      plugin: plugin,
      type: 'plugin',
      config: config
    };

    var handleError = function (message) {
      $step.append($('<div class="mepr-sister-plugin-message mepr-sister-plugin-message-error">').text(message));
      $button.html(originalButtonHtml).prop('disabled', false);
    };

    $.ajax({
      type: 'POST',
      url: MeprSisterPlugin.ajax_url,
      dataType: 'json',
      data: data
    })
    .done(function (response) {
      if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
        handleError(MeprSisterPlugin.install_failed);
      } else if (!response.success) {
        if (typeof response.data == 'object' && response.data[0] && response.data[0].code) {
          handleError(MeprSisterPlugin.install_failed);
        } else {
          handleError(response.data);
        }
      } else {
        if (action === 'mepr_addon_install' && !response.data.activated) {
          config.installed = true;

          $step.append($('<div class="mepr-sister-plugin-message mepr-sister-plugin-message-success">').text(response.data.message));

          $button.text(config.activate_button_text).prop('disabled', false);
        } else {
          $button.removeClass('button-primary').addClass('button-secondary').html(MeprSisterPlugin.installed_and_activated);

          $step.removeClass('mepr-sister-plugin-step-current')
            .next().addClass('mepr-sister-plugin-step-current')
            .find('.mepr-sister-plugin-step-button').html(config.next_step_button_html);
        }
      }
    })
    .fail(function () {
      handleError(MeprSisterPlugin.install_failed);
    })
    .always(function () {
      $button.width('auto');

      // Automatically clear any messages after 3 seconds
      setTimeout(function() {
        $step.find('.mepr-sister-plugin-message').remove();
      }, 3000);
    });
  });

  $('.mepr-sister-plugin').each(function () {
    var $plugin = $(this),
      config = $plugin.data('config');

    if(config && config.auto_install) {
      var $button = $plugin.find('.mepr-sister-plugin-auto-installer').trigger('click'),
        $step = $button.closest('.mepr-sister-plugin-step');

      if($step.length && typeof $step[0]['scrollIntoView'] == 'function') {
        $step[0].scrollIntoView();
      }
    }
  });
});
