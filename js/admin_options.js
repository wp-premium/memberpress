jQuery(document).ready(function($) {
  $('.mepr-paypal-onboarding-button').tooltipster({
    maxWidth: 300
  });

  $('[data-mepr-disconnect-paypal]').on('click', function () {
    let methodId = $(this).data('method-id');
    let sandbox = $(this).data('paypal-sandbox');
    let disconnectConfirmMessage = $(this).data('disconnect-confirm-msg');

    if (sandbox == true) {
      if (window.confirm(disconnectConfirmMessage)) {
        window.location.href = ajaxurl + '?action=mepr_paypal_connect_disconnect&sandbox=1&method-id=' + methodId;
      }
    } else {
      if (window.confirm(disconnectConfirmMessage)) {
        window.location.href = ajaxurl + '?action=mepr_paypal_connect_disconnect&method-id=' + methodId;
      }
    }
  });

  $('[data-mepr-upgrade-paypal]').on('click', function () {
    let methodId = $(this).data('method-id');
    let disconnectConfirmMessage = $(this).data('disconnect-confirm-msg');

    if (window.confirm(disconnectConfirmMessage)) {
      window.location.href = ajaxurl + '?action=mepr_paypal_connect_upgrade_standard_gateway&method-id=' + methodId;
    }
  });

  //Set the correct tab to display
  var hash = location.hash.replace('#','');

  if(hash == '') {
    hash = 'license';
  }
  else {
    hash = hash.replace('mepr-','');
  }

  show_chosen_tab(hash);

  setTimeout( dismiss_notices, 5000 );

  function show_chosen_tab(chosen) {
    var hash = '#mepr-' + chosen;

    //Adjust tab's style
    $('a.nav-tab-active').removeClass('nav-tab-active');
    $('a#' + chosen).addClass('nav-tab-active');

    //Adjust pane's style
    $('div.mepr-options-hidden-pane').hide();
    $('div#' + chosen).show();

    //Set action to the proper tab
    $('#mepr_options_form').attr('action', hash);
    $('.nav-tab-wrapper').trigger('mepr-show-nav-tab',[chosen]);
    window.location.hash = hash;
  }

  function dismiss_notices() {
    var notices = $('.mepr-removable-notice');
    $.each(notices, function(index, el) {
      el.remove();
    });
  }

  $('a.nav-tab').click(function() {
    var chosen = $(this).attr('id');

    show_chosen_tab(chosen);

    dismiss_notices();

    return false;
  });

  // Payment configuration options
  $('div#integration').on('click', '#mepr-add-integration', function() {
    show_integration_form();
    return false;
  });

  function show_integration_form() {
    var data = {
      action: 'mepr_gateway_form',
      option_nonce: MeprOptions.option_nonce
    };
    $.post(ajaxurl, data, function(response) {
      if( response.error === undefined ) {
        $(response.form).hide().appendTo('#integrations-list').slideDown('fast');
        $("select.mepr-gateways-dropdown").val("MeprStripeGateway").change();
        mepr_setup_clipboard();
      }
      else {
        alert('Error');
      }
    }, 'json');
  }

  $('div#integration').on('click', '.mepr-integration-delete a', function() {
    if(confirm(MeprOptions.confirmPMDelete)) {
      $(this).parent().parent().slideUp('fast', function() {
        $('<input>').attr({
          type: 'hidden',
          name: 'mepr_deleted_payment_methods[]',
          value: $(this).data('id')
        }).appendTo('#mepr_options_form');
        $(this).remove();
      });
    }
    return false;
  });

  $('div#integration').on('change', 'select.mepr-gateways-dropdown', function() {
    var data_id = $(this).attr('data-id');
    var gateway = $(this).val();
    var data = {
      action: 'mepr_gateway_form',
      option_nonce: MeprOptions.option_nonce,
      g: gateway
    };
    $.post(ajaxurl, data, function(response) {
      if( response.error === undefined ) {
        $('#mepr-integration-'+data_id).replaceWith(response.form);
        mepr_setup_clipboard();
        if( gateway === 'MeprStripeGateway' ) {
          $('#mepr-stripe-live-keys-'+response.id).slideDown('fast');
        }

        mepr_toggle_boxes();
        $('#mepr-integration-'+response.id+ ' .mepr-paypal-onboarding-button').hide();
        $('#mepr-integration-'+response.id+ ' .mepr-paypal-save-option').show();
      }
      else {
        alert('Error');
      }
    }, 'json');
    return false;
  });

  $('input.mepr-stripe-testmode').each( function() {
    var integration = $(this).data('integration');

    if( $(this).is(':checked') ) {
      $('#mepr-stripe-test-keys-'+integration).show();
    }
    else {
      $('#mepr-stripe-live-keys-'+integration).show();
    }
  });

  $('div#integration').on('change', 'input.mepr-stripe-testmode', function() {
    var integration = $(this).data('integration');
    if( $(this).is(':checked') ) {
      $('#mepr-stripe-live-keys-'+integration).hide();
      $('#mepr-stripe-test-keys-'+integration).show();
    }
    else {
      $('#mepr-stripe-live-keys-'+integration).show();
      $('#mepr-stripe-test-keys-'+integration).hide();
    }
  });

  //Custom Fields JS
  function get_new_line()
  {
    var random_id = Math.floor(Math.random() * 100000001); //easiest way to do this
    return  '<li class="mepr-custom-field postbox"> \
              <label>' + MeprOptions.nameLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + random_id + '][name]" /> \
               \
              <label>' + MeprOptions.typeLabel + '</label> \
              <select name="mepr-custom-fields[' + random_id + '][type]" class="mepr-custom-fields-select" data-value="' + random_id + '"> \
                <option value="text">' + MeprOptions.textOption + '</option> \
                <option value="email">' + MeprOptions.emailOption + '</option> \
                <option value="url">' + MeprOptions.urlOption + '</option> \
                <option value="tel">' + MeprOptions.phoneOption + '</option> \
                <option value="date">' + MeprOptions.dateOption + '</option> \
                <option value="textarea">' + MeprOptions.textareaOption + '</option> \
                <option value="checkbox">' + MeprOptions.checkboxOption + '</option> \
                <option value="dropdown">' + MeprOptions.dropdownOption + '</option> \
                <option value="multiselect">' + MeprOptions.multiselectOption + '</option> \
                <option value="radios">' + MeprOptions.radiosOption + '</option> \
                <option value="checkboxes">' + MeprOptions.checkboxesOption + '</option> \
                <option value="file">' + MeprOptions.fileuploadOption + '</option> \
              </select> \
               \
              <label for="mepr-custom-fields[' + random_id + '][default]">' + MeprOptions.defaultLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + random_id + '][default]" /> \
               \
              <input type="checkbox" name="mepr-custom-fields[' + random_id + '][signup]" id="mepr-custom-fields-signup-' + random_id + '" /> \
              <label for="mepr-custom-fields-signup-' + random_id + '">' + MeprOptions.signupLabel + '</label> \
               \
              <input type="checkbox" name="mepr-custom-fields[' + random_id + '][show_in_account]" id="mepr-custom-fields-account-' + random_id + '" checked/> \
              <label for="mepr-custom-fields-account-' + random_id + '">' + MeprOptions.accountLabel + '</label> \
               \
              <input type="checkbox" name="mepr-custom-fields[' + random_id + '][required]" id="mepr-custom-fields-required-' + random_id + '" /> \
              <label for="mepr-custom-fields-required-' + random_id + '">' + MeprOptions.requiredLabel + '</label> \
              <input type="hidden" name="mepr-custom-fields-index[]" value="' + random_id + '" /> \
               \
              <a href="" class="mepr-custom-field-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a> \
              <div id="dropdown-hidden-options-' + random_id + '" style="display:none;"></div> \
              \
              <input type="hidden" name="mepr-custom-fields[' + random_id + '][slug]" value="mepr_none" />\
            </li>';
  }

  function get_initial_dropdown_options(my_id)
  {
    return '<ul class="custom_options_list"> \
              <li> \
                <label>' + MeprOptions.optionNameLabel + '</label> \
                <input type="text" name="mepr-custom-fields[' + my_id + '][option][]" /> \
                 \
                <label>' + MeprOptions.optionValueLabel + '</label> \
                <input type="text" name="mepr-custom-fields[' + my_id + '][value][]" /> \
                 \
                <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a> \
              </li> \
              <a href="" id="mepr-add-new-option" title="' + MeprOptions.addOptionLabel + '" data-value="' + my_id + '"><i class="mp-icon mp-icon-plus-circled mp-16"></i></a> \
            </ul>';
  }

  function get_new_option_line(my_id)
  {
    return '<li> \
              <label>' + MeprOptions.optionNameLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + my_id + '][option][]" /> \
               \
              <label>' + MeprOptions.optionValueLabel + '</label> \
              <input type="text" name="mepr-custom-fields[' + my_id + '][value][]" /> \
               \
              <a href="" class="mepr-option-remove"><i class="mp-icon mp-icon-cancel-circled mp-16"></i></a> \
            </li>';
  }

  $('a#mepr-add-new-custom-field').click(function() {
    $(this).before(get_new_line());
    return false;
  });

  $('body').on('click', 'a#mepr-add-new-option', function() {
    var my_id = $(this).attr('data-value');
    $(this).before(get_new_option_line(my_id));
    return false;
  });

  $('body').on('click', 'a.mepr-custom-field-remove', function() {
    $(this).parent().remove();
    return false;
  });
  $('body').on('click', 'a.mepr-option-remove', function() {
    $(this).parent().remove();
    return false;
  });

  $('body').on('change', 'select.mepr-custom-fields-select', function() {
    var my_id = $(this).data('value');
    var type = $(this).val();

    if( $.inArray(type,['dropdown','multiselect','radios','checkboxes']) > -1 ) {
      $('div#dropdown-hidden-options-' + my_id).html(get_initial_dropdown_options(my_id));
      $('div#dropdown-hidden-options-' + my_id).show();
    } else {
      $('div#dropdown-hidden-options-' + my_id).html('');
      $('div#dropdown-hidden-options-' + my_id).hide();
    }

    return false;
  });

  $('body').on('change', 'select.mepr-custom-fields-select', function() {
    var my_id = $(this).data('value');
    var type = $(this).val();

    if( 'file' == type ) {
      $('input[name="mepr-custom-fields['+my_id+'][default]"]').hide();
      $('label[for="mepr-custom-fields['+my_id+'][default]"]').hide();

    } else {
      $('input[name="mepr-custom-fields['+my_id+'][default]"]').show();
      $('label[for="mepr-custom-fields['+my_id+'][default]"]').show();
    }

    return false;
  });

  $("select.mepr-custom-fields-select").each(function(){
    var my_id = $(this).data('value');
    var type = $(this).val();

    if( 'file' == type ) {
      $('input[name="mepr-custom-fields['+my_id+'][default]"]').hide();
      $('label[for="mepr-custom-fields['+my_id+'][default]"]').hide();

    }
  });


    //Terms of Service JS stuff
    if($('#mepr-require-tos').is(":checked")) {
      $('div#mepr_tos_hidden').show();
  } else {
    $('div#mepr_tos_hidden').hide();
  }
  $('#mepr-require-tos').click(function() {
    $('div#mepr_tos_hidden').slideToggle('fast');
  });

  //Privacy Policy JS stuff
  if($('#mepr-require-privacy-policy').is(":checked")) {
    $('div#mepr_privacy_hidden').show();
  } else {
    $('div#mepr_privacy_hidden').hide();
  }
  $('#mepr-require-privacy-policy').click(function() {
    $('div#mepr_privacy_hidden').slideToggle('fast');
  });

  //Unauthorized stuff
  if($('#mepr-redirect-on-unauthorized').is(':checked')) {
    $('#mepr-unauthorized-redirect').slideDown();
  } else {
    $('#mepr-unauthorized-redirect').slideUp();
  }

  $('#mepr-redirect-on-unauthorized').click(function() {
    if($('#mepr-redirect-on-unauthorized').is(':checked')) {
      $('#mepr-unauthorized-redirect').slideDown();
    } else {
      $('#mepr-unauthorized-redirect').slideUp();
    }
  });

  //Unauthorized excerpts type
  var toggle_excerpt_type = function() {
    if($('#mepr-unauth-show-excerpts').is(':checked')) {
      $('#mepr-unauthorized-show-excerpts-type').slideDown();
    } else {
      $('#mepr-unauthorized-show-excerpts-type').slideUp();
    }
  };
  toggle_excerpt_type();
  $('#mepr-unauth-show-excerpts').click(toggle_excerpt_type);

  //Unauthorized excerpt size
  var toggle_excerpt_size = function() {
    if($('#mepr-unauth-excerpt-type').val()=='custom') {
      $('#mepr-unauth-excerpt-type-size').slideDown();
    } else {
      $('#mepr-unauth-excerpt-type-size').slideUp();
    }
  };

  toggle_excerpt_size();
  $('#mepr-unauth-excerpt-type').change(toggle_excerpt_size);

  //Unauthorized message toggle
  $('.mp-toggle-unauthorized-message').click( function(e) {
    e.preventDefault();
    $('.mp-unauthorized-message').slideToggle();
  });

  mepr_setup_clipboard();

  //Make who can purchase list sortable
  $(function() {
    $('ol#custom_profile_fields').sortable();
  });

  //Hide/Show SEO Unauthorized Noindex stuff
  if($('#mepr-authorize-seo-views').is(":checked")) {
    $('div#mepr-seo-noindex-area').hide();
  } else {
    $('div#mepr-seo-noindex-area').show();
  }
  $('#mepr-authorize-seo-views').click(function() {
    $('div#mepr-seo-noindex-area').slideToggle('fast');
  });

  //Hide/Show PayWall Stuff
  if($('#mepr-paywall-enabled').is(":checked")) {
    $('div#mepr-paywall-options-area').show();
  } else {
    $('div#mepr-paywall-options-area').hide();
  }
  $('#mepr-paywall-enabled').click(function() {
    $('div#mepr-paywall-options-area').slideToggle('fast');
  });

  //PAYPAL STANDARD STUFF SHNIZZLE
  $('.advanced_mode_checkbox').each(function() {
    if($(this).is(':checked')) {
      var id = $(this).attr('data-value');
      $('.advanced_mode_row-' + id).show();
    }
  });

  $('body').on('click', '.advanced_mode_checkbox', function(e) {
    // e.preventDefault(); //Don't do this on checkbox's -- they will never uncheck apparently
    var id = $(this).attr('data-value');
    $('.advanced_mode_row-' + id).toggle();
  });

  if($('#mepr_calculate_taxes').is(':checked')) {
    $('#address-tax-info').show();
    $('input#mepr-show-address-fields').prop('checked', true);
    $('input#mepr-require-address-fields').prop('checked', true);
    $('input#mepr-show-address-fields').prop('disabled', true);
    $('input#mepr-require-address-fields').prop('disabled', true);
  }

  if($('#mepr-require-address-fields').is(':checked')) {
    $('#mepr-show-address-fields').prop('checked', true);
    $('#mepr-show-address-fields').prop('disabled', true);
  }

  if($('#mepr-require-fname-lname').is(':checked')) {
    $('#mepr-show-fname-lname').prop('checked', true);
    $('#mepr-show-fname-lname').prop('disabled', true);
  }

  $('.mepr-custom-field').each(function() {
    var field_id = $(this).find('input[name="mepr-custom-fields-index[]"]').val();

    if(!field_id) {
      return;
    }

    if($('#mepr-custom-fields-required-' + field_id).is(':checked')) {
      $('#mepr-custom-fields-signup-' + field_id).prop('checked', true);
      $('#mepr-custom-fields-signup-' + field_id).prop('disabled', true);
    }
  });

  $('#mepr-require-address-fields').on('click', function() {
    if($('#mepr-require-address-fields').is(':checked')) {
      $('#mepr-show-address-fields').prop('checked', true);
      $('#mepr-show-address-fields').prop('disabled', true);
    } else {
      $('#mepr-show-address-fields').prop('disabled', false);
    }
  });

  $('#mepr-require-fname-lname').on('click', function() {
    if($('#mepr-require-fname-lname').is(':checked')) {
      $('#mepr-show-fname-lname').prop('checked', true);
      $('#mepr-show-fname-lname').prop('disabled', true);
    } else {
      $('#mepr-show-fname-lname').prop('disabled', false);
    }
  });

  $('#custom_profile_fields').on('change', function(e) {
    // If this is a text, select field, or the "Show in Account" checkbox, then let's bail.
    if($(e.target).is('input[type="text"]') || $(e.target).is('select') || e.target.id.indexOf('account') >= 0) {
      return;
    }

    var field_id = e.target.id.split('-').pop();

    if(!field_id) {
      return;
    }

    if(e.target.id.indexOf('required') >= 0 && $(e.target).is('input[type="checkbox"]') && $(e.target).is(':checked')) {
      $('#mepr-custom-fields-signup-' + field_id).prop('checked', true);
      $('#mepr-custom-fields-signup-' + field_id).prop('disabled', true);
    } else {
      $('#mepr-custom-fields-signup-' + field_id).prop('disabled', false);
    }
  });

  $('body').on('click', '#mepr_calculate_taxes', function(e) {
    if($('#mepr_calculate_taxes').is(':checked')) {
      $('#address-tax-info').show();
      $('input#mepr-show-address-fields').prop('checked', true);
      $('input#mepr-require-address-fields').prop('checked', true);
      $('input#mepr-show-address-fields').prop('disabled', true);
      $('input#mepr-require-address-fields').prop('disabled', true);
    }
    else {
      $('#address-tax-info').hide();

      if($('input#mepr-require-address-fields').is(':checked')) {
        $('input#mepr-require-address-fields').prop('disabled', false);
        $('input#mepr-show-address-fields').prop('disabled', true);
      } else {
        $('input#mepr-show-address-fields').prop('disabled', false);
        $('input#mepr-require-address-fields').prop('disabled', false);
      }
    }
  });

  $('body').on('click', '.mepr-tax-rate-remove', function(e) {
    e.preventDefault();
    if(confirm(MeprOptions.taxRateRemoveStr)) {
      var id = $(this).data('id');
      var ajax_data = {
        id: id,
        action: 'mepr_remove_tax_rate',
        tax_nonce: MeprOptions.tax_nonce
      }
      $.post(ajaxurl, ajax_data)
        .done(function(data, stat) {
          var msg = JSON.parse(data);
          alert(msg.message);
          $('#mepr_tax_rate_row_'+id).slideUp({
            complete: function() {
              $('#mepr_tax_rate_row_'+id).remove();
            }
          });
        })
        .fail(function(data, stat, statname) {
          var msg = JSON.parse(data.responseText);
          alert('ERROR: ' + msg.error);
        }, 'json');
    }
  });

  // Create payment method before Stripe connect redirect
  $('body').on('click', '.mepr-stripe-connect-new', function(e) {
    e.preventDefault();
    var pmid = $(this).data('id');

    var form_data = $('#mepr-integration-'+pmid+' input, #mepr-integration-'+pmid+' select').serialize();

    var href = $(this).data('href');
    var nonce = $(this).data('nonce');

    $.post( ajaxurl, {
        'action': 'mepr_create_new_payment_method',
        'security': nonce,
        'form_data':  form_data
      },
      function(response) {
        window.location.href = href;
      }
    );
  });

  $('body').on('click', '.mepr_stripe_disconnect_button', function(e) {
    var proceed = confirm( $(this).data('disconnect-msg') );
    if ( false === proceed ) {
      e.preventDefault();
    }
  });

  var $licenseContainer = $('#mepr-license-container'),
    loadingHtml = '<i class="mp-icon mp-icon-spinner animate-spin" aria-hidden="true"></i>',
    activating = false,
    licenseError = function (message) {
      $licenseContainer.prepend(
        $('<div class="notice notice-error">').append(
          $('<p>').html(message)
        )
      );
    };

  $('body').on('click', '#mepr-activate-license-key', function () {
    var $button = $(this),
      buttonWidth = $button.width(),
      buttonHtml = $button.html(),
      key = $('#mepr-license-key').val();

    if (activating || !key) {
      return;
    }

    activating = true;
    $button.width(buttonWidth).html(loadingHtml);
    $licenseContainer.find('> .notice').remove();

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'mepr_activate_license',
        _ajax_nonce: MeprOptions.activate_license_nonce,
        key: key
      }
    })
    .done(function (response) {
      if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
        licenseError(MeprOptions.activation_error.replace('%s', MeprOptions.invalid_response));
      } else if (!response.success) {
        licenseError(response.data);
      } else {
        $('.mepr-notice-dismiss-24hour').hide();
        if(response.data === true) {
          window.location.reload();
        } else {
          $licenseContainer.html(response.data);
        }
      }
    })
    .fail(function () {
      licenseError(MeprOptions.activation_error.replace('%s', MeprOptions.ajax_error));
    })
    .always(function () {
      activating = false;
      $button.html(buttonHtml).width('auto');
    });
  });

  $('body').on('keypress', '#mepr-license-key', function (e) {
    if(e.which === 13) {
      e.preventDefault();
      $('#mepr-activate-license-key').trigger('click');
    }
  });

  var deactivating = false;

  $('body').on('click', '#mepr-deactivate-license-key', function () {
    var $button = $(this),
      buttonWidth = $button.width(),
      buttonHtml = $button.html();

    if (deactivating || !confirm(MeprOptions.deactivate_confirm)) {
      return;
    }

    deactivating = true;
    $button.width(buttonWidth).html(loadingHtml);
    $licenseContainer.find('> .notice').remove();

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'mepr_deactivate_license',
        _ajax_nonce: MeprOptions.deactivate_license_nonce
      }
    })
    .done(function (response) {
      if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
        licenseError(MeprOptions.deactivation_error.replace('%s', MeprOptions.invalid_response));
      } else if (!response.success) {
        licenseError(response.data);
      } else {
        $licenseContainer.html(response.data);
      }
    })
    .fail(function () {
      licenseError(MeprOptions.deactivation_error.replace('%s', MeprOptions.ajax_error));
    })
    .always(function () {
      deactivating = false;
      $button.html(buttonHtml).width('auto');
    });
  });

  $('body').on('click', '#mepr-edge-updates', function(e) {
    e.preventDefault();
    var wpnonce = $(this).attr('data-nonce');

    $('#mepr-edge-updates-wrap .mepr_loader').show();
    $(this).prop('disabled',true);

    var data = {
      action: 'mepr_edge_updates',
      edge: $(this).is(':checked'),
      wpnonce: wpnonce
    };

    var bigthis = this;

    $.post(ajaxurl, data, function(obj) {
      $('#mepr-edge-updates-wrap .mepr_loader').hide();
      $(bigthis).prop('disabled',false);

      if('error' in obj)
        alert(obj.error);
      else {
        $(bigthis).prop('checked',(obj.state=='true'));
      }
    }, 'json');
  });

  var show_charge_business_customer_option = function () {
    var seleted_tax_type = $('select[name=mepr_tax_calc_type]').val();
    var eu_tax = $('input[name=mepr_vat_tax_businesses]').prop('checked');
    if (seleted_tax_type === 'inclusive' && eu_tax === false) {
      $('#mepr_charge_business_customer_net_price_section').show();
    } else {
      $('#mepr_charge_business_customer_net_price_section').hide();
    }
  };

  show_charge_business_customer_option();

  $('select[name=mepr_tax_calc_type]').change(show_charge_business_customer_option);
  $('input[name=mepr_vat_tax_businesses]').change(show_charge_business_customer_option);

  var $detected_ip_address = $('#mepr-detected-ip-address');

  $('input[name="mepr-anti-card-testing-ip-method"]').on('change', function () {
    $detected_ip_address.text('...');

    $.ajax({
      url: ajaxurl,
      method: 'GET',
      dataType: 'json',
      data: {
        action: 'mepr_anti_card_testing_get_ip',
        method: $(this).val()
      }
    })
    .done(function (response) {
      if(response && typeof response == 'object' && response.success) {
        $detected_ip_address.text(response.data);
      }
    });
  });

  $('body').on('click', '#mepr-install-license-edition', function (e) {
    e.preventDefault();

    $('#mepr-install-license-edition-loading').show();

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'mepr_install_license_edition',
        _ajax_nonce: MeprOptions.install_license_edition_nonce
      }
    })
    .done(function (response) {
      if(response && typeof response.success === 'boolean') {
        alert(response.data);

        if(response.success) {
          window.location.reload();
        }
      }
      else {
        alert(MeprOptions.invalid_response);
      }
    })
    .fail(function () {
      alert(MeprOptions.ajax_error);
    })
    .always(function () {
      $('#mepr-install-license-edition-loading').hide();
    });
  });

  $('body').on('click', '#mepr-activate-new-license', function (e) {
    e.preventDefault();

    var license_key = $(this).data('license-key');

    setTimeout(function () {
      $('#mepr-license-key').val(license_key);
      $('#mepr-activate-license-key').trigger('click');
    }, 250);
  });
});
