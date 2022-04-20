(function($) {
  $(document).ready(function() {
    $('body').on('click', '.mepr-signup-form .have-coupon-link', function(e) {
      e.preventDefault();
      $(this).hide();
      $('div.mepr_coupon_'+$(this).data("prdid")).show();
    });

    $('body').on('keydown', '.mepr-signup-form .mepr-coupon-code', function(e) {
      if(e.which === 13) {
        e.preventDefault();
        $(this).trigger('blur');
      }
    });

    // Store the default price string
    $('.mepr_price_cell').each(function () {
      $(this).data('default-price-string', $(this).text());
    });

    var meprValidateInput = function (obj, submitting) {
      $(obj).removeClass('invalid');

      var form = $(obj).closest('.mepr-signup-form');

      if ($(obj).attr('required') !== undefined) {
        var notBlank = true;

        if(!$(obj).is(':visible')) {
          // Pass validation on fields that are not visible
        }
        else if ($(obj).is('input') || $(obj).is('select') || $(obj).is('textarea')) {
          notBlank = mpValidateNotBlank($(obj).val());
        }
        else if ($(obj).hasClass('mepr-checkbox-field')) {
          notBlank = $(obj).find('input').is(':checked');
        }
        else if ($(obj).hasClass('mepr-radios-field') || $(obj).hasClass('mepr-checkboxes-field')) {
          var input_vals = [];
          $.each($(obj).find('input'), function (i, obj) {
            if($(obj).is(':checked')) {
              input_vals.push(true);
            }
          });

          notBlank = mpValidateNotBlank(input_vals);
        }

        mpToggleFieldValidation($(obj), notBlank);
      }

      // Validate actual email only if it's not empty otherwise let the required/un-required logic hold
      if ($(obj).attr('type')==='email' && $(obj).val().length > 0) {
        var validEmail = mpValidateEmail($(obj).val());
        mpToggleFieldValidation($(obj), validEmail);
      }

      // Validate the URL by using the browser validation functions
      if ($(obj).attr('type')==='url' && $(obj).val().length > 0) {
        var validURL = $(obj).is(':valid');
        mpToggleFieldValidation($(obj), validURL);
      }

      if ($(obj).hasClass('mepr-password-confirm')) {
        var confirmMatch = $(obj).val() === form.find('.mepr-password').val();
        mpToggleFieldValidation($(obj), confirmMatch);
      }

      if($(obj).hasClass('mepr-coupon-code') && !submitting) {
        var price_string = form.find('div.mepr_price_cell');

        if($(obj).val().match(/(\s|\S)/)) {
          $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').fadeIn();

          var data = {
            action: 'mepr_validate_coupon',
            code: $(obj).val(),
            prd_id: $(obj).data("prdid"),
            coupon_nonce: MeprSignup.coupon_nonce
          };

          $.post(MeprI18n.ajaxurl, data, function(res) {
            $(obj).prev('.mp-form-label').find('.mepr-coupon-loader').hide();
            res = res.trim();
            mpToggleFieldValidation($(obj), (res.toString() == 'true'));

            if(res.toString() == 'true') {

              // Let's update price string
              meprUpdatePriceTerms(form);
            }
            else {
              form.find('.mepr-payment-methods-wrapper:hidden').show();
              form.find('input[name="mepr_payment_methods_hidden"]').remove();
              price_string.text(price_string.data('default-price-string'));

              // Invalid Coupon - update SPC Invoice
              meprUpdatePriceTerms(form);
            }

          });
        }
        else if($(obj).val().trim() === '') {
          if (form.find('.mepr-payment-methods-wrapper').is(':hidden')) {
            // Looks like we need to restore the payment methods
            form.find('.mepr-payment-methods-wrapper').show();
            form.find('input[name="mepr_payment_methods_hidden"]').remove();
          }

          price_string.text(price_string.data('default-price-string'));
          // Enpty Coupon - update SPC Invoice
          meprUpdatePriceTerms(form);
        }
      }

      $(obj).trigger('mepr-validate-input');
    };

    window.mepr_validate_input = meprValidateInput;

    var meprUpdatePriceTerms = function (form) {
      var price_string = form.find('div.mepr_price_cell');

      let settings = {
        url: MeprI18n.ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          code: form.find('input[name="mepr_coupon_code"]').val(),
          prd_id: form.find('input[name="mepr_product_id"]').val(),
          mepr_address_one: form.find('input[name="mepr-address-one"]').val(),
          mepr_address_two: form.find('input[name="mepr-address-two"]').val(),
          mepr_address_city: form.find('input[name="mepr-address-city"]').val(),
          mepr_address_state: form.find('select[name="mepr-address-state"]').is(':visible') ? form.find('select[name="mepr-address-state"]').val() : form.find('input[name="mepr-address-state"]').val(),
          mepr_address_country: form.find('select[name="mepr-address-country"]').val(),
          mepr_address_zip: form.find('input[name="mepr-address-zip"]').val(),
          mepr_vat_number: form.find('input[name="mepr_vat_number"]').val(),
          mepr_vat_customer_type: form.find('input[name="mepr_vat_customer_type"]:checked').val(),
          coupon_nonce: MeprSignup.coupon_nonce,
          mpca_corporate_account_id: form.find('input[name="mpca_corporate_account_id"]').val()
        }
      }

      if(form.find('input[name="mpgft-signup-gift-checkbox"]').length > 0){
        settings.data.mpgft_gift_checkbox = $('input[name="mpgft-signup-gift-checkbox"]').is(':checked');
      }

      // Let's update terms
      settings.data.action = 'mepr_update_price_string';
      $.ajax(settings)
      .done(function (response) {
        if (response && typeof response == 'object' && response.status === 'success') {
          form.trigger('meprPriceStringUpdated', [response]);
          if(price_string.length) {
            var scroll_top = price_string.offset().top;
            price_string.html(response.price_string);
          }

          if(response.payment_required) {
            form.find('.mepr-payment-methods-wrapper').show();
            form.find('input[name="mepr_payment_methods_hidden"]').remove();
          } else {
            form.find('.mepr-payment-methods-wrapper').hide();
            form.append('<input type="hidden" name="mepr_payment_methods_hidden" value="1">');

            // Clear validation errors on fields now hidden
            form.find('.mepr-payment-methods-wrapper .mepr-form-input').each(function () {
              meprValidateInput(this);
            });
          }

          form.trigger('meprAfterPriceStringUpdated', [response]);
        }
      });

      // Let's update SPC Invoice
      if (MeprSignup.spc_enabled == '1'){
        settings.data.action = 'mepr_update_spc_invoice_table';

        // Show SPC invoice table loader
        form.find('.mepr-invoice-loader').fadeIn();

        $.ajax(settings)
        .done(function (response) {
          if (response && typeof response == 'object' && response.status === 'success') {
            $(form).find('.mepr-transaction-invoice-wrapper > div').replaceWith(response.invoice);
          }
          $(form).find('.mepr-invoice-loader').hide();
          $(form).find(".mepr-transaction-invoice-wrapper .mp_invoice").css({ opacity: 1 });
        });
      }
    }


    $('body').on('focus', '.mepr-form .mepr-form-input', function (e) {
      $(this).prev('.mp-form-label').find('.cc-error').hide();
      $(this).removeClass('invalid');
    });

    $('body').on('blur', '.mepr-form .mepr-form-input', function (e) {
      //Don't validate date fields here, wait til the push the submit button
      if(!$(this).hasClass('mepr-date-picker')) {
        meprValidateInput(this);
      }
    });

    // Specific to validating with the datepicker ui text field
    $('body').on('mepr-date-picker-closed', '.mepr-form .mepr-form-input.mepr-date-picker', function (e, date, inst) {
      meprValidateInput(this);
    });

    $('body').on('click', '.mepr-signup-form .mepr-submit', function (e) {
      // We want to control if/when the form is submitted
      e.preventDefault();

      var form = $(this).closest('.mepr-signup-form');
      var button = $(this);

      $.each(form.find('.mepr-form-input:visible'), function(i,obj) {
        meprValidateInput(obj, true);
      });

      if (0 < form.find('.invalid:visible').length) {
        form.find('.validation').addClass('failed');
      }
      else {
        var submittedTelInputs = document.querySelectorAll(".mepr-tel-input");
        for (var i = 0; i < submittedTelInputs.length; i++) {
          var iti = window.intlTelInputGlobals.getInstance(submittedTelInputs[i]);
          submittedTelInputs[i].value = iti.getNumber();
        }
        form.find('.validation').addClass('passed');
        this.disabled = true;
        form.find('.mepr-loading-gif').show();
        $(this).trigger('mepr-register-submit');
        form.submit();
      }
    });

    $('body').on('click', '.mepr-signup-form div[class^=mepr-payment-method] input.mepr-form-radio', function () {
      var form = $(this).closest('.mepr-signup-form');

      // Reset the transaction ID to prevent any issues after switching payment methods
      form.find('input[name="mepr_transaction_id"]').val('');

      var pmid = '.mp-pm-desc-' + $(this).val();
      var pmid_exists = (form.find(pmid).length > 0);

      form.find('.mepr-payment-method-desc-text').addClass('mepr-close');

      if(pmid_exists) {
        form.find(pmid).removeClass('mepr-close');
      }

      //If nothing has the mepr-close class, we still need to show this one's description
      var mepr_close_exists = (form.find('.mepr-payment-method-desc-text.mepr-close').length > 0);

      if(mepr_close_exists) {
        form.find('.mepr-payment-method-desc-text.mepr-close').slideUp({
          duration: 200,
          complete: function() {
            // Clear validation errors on fields now hidden
            form.find('.mepr-payment-methods-wrapper .mepr-form-input').each(function () {
              meprValidateInput(this);
            });

            if(pmid_exists) {
              form.find(pmid).slideDown(200);
            }
          }
        });
      } else {
        // Clear validation errors on fields now hidden
        form.find('.mepr-payment-methods-wrapper .mepr-form-input').each(function () {
          meprValidateInput(this);
        });

        if(pmid_exists) {
          form.find(pmid).slideDown(200);
        }
      }
    });

    // Update price string & invoice when certain inputs change value
    $("body").on("change",
      ".mepr-form .mepr-form-input, .mepr-form .mepr-form-radios-input, .mepr-form .mepr-select-field",
      function (e) {

        if($(this).attr('name') == 'mepr-address-zip' ||
          $(this).attr('name') == 'mepr-address-city' ||
          $(this).attr('name') == 'mepr-address-country' ||
          $(this).attr('name') == 'mepr-address-one' ||
          $(this).attr('name') == 'mepr-address-state' ||
          $(this).attr('name') == 'mepr_vat_customer_type' ||
          $(this).attr('name') == 'mepr_vat_number' ||
          $(this).attr('name') == 'mpgft-signup-gift-checkbox'
        ) {
          let form = $(this).closest(".mepr-signup-form");
          meprUpdatePriceTerms(form);
        }
      }
    );

    // Update price string & invoice when geolocation occurs
    $('body').on('mepr-geolocated', '.mepr-form .mepr-countries-dropdown', function () {
      meprUpdatePriceTerms($(this).closest('.mepr-signup-form'));
    });


    $(".mepr-replace-file").each(function(){
      $(this).closest('div').find('.mepr-file-uploader').hide();
    });
    $('body').on('click', '.mepr-replace-file', function (e) {
      $(this).closest('div').find('.mepr-file-uploader').toggle();
    });


  });
})(jQuery);
