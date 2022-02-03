(function ($) {
  $(document).ready(function() {
    //Trigger a click on stripe checkout automatically
    var done = false; //Prevent double submit (for some reason)
    if(!done) {
      $("button.stripe-button-el").trigger("click");
      done = true;
    }

    $('.mepr-signup-form, #mepr-stripe-payment-form').each(function () {
      new MeprStripeForm(this);
    });
  });

  /**
   * The MemberPress Stripe form class
   *
   * @constructor
   * @param {HTMLFormElement} form
   */
  function MeprStripeForm (form) {
    this.form = form;
    this.$form = $(form);
    this.isSpc = this.$form.hasClass('mepr-signup-form');
    this.paymentMethods = [];
    this.selectedPaymentMethod = null;
    this.paymentRequest = null;
    this.submitting = false;
    this.processingPaymentRequestButton = false;
    this.paymentRequestPaymentMethodId = '';

    this.initPaymentMethods();
    this.initPaymentRequestButtons();
    this.$form.on('submit', $.proxy(this.handleSubmit, this));
    this.$form.on('meprAfterPriceStringUpdated', $.proxy(this.updateStripePaymentRequestPrice, this));
  }

  MeprStripeForm.prototype.updateStripePaymentRequestPrice = function () {
    if (this.isSpc) {
      var stripeAmount = this.$form.find('input[name=mepr_stripe_txn_amount]').
          attr('value');
    } else {
      var stripeAmount = $('.mp_invoice input[name=mepr_stripe_txn_amount]').attr('value');
    }
    stripeAmount = parseInt(stripeAmount);
    if (this.paymentRequest !== null) {
      this.paymentRequest.update({
        total: {
          label: 'Total',
          amount: stripeAmount,
        },
      });
    }
  }

  MeprStripeForm.prototype.initPaymentRequestButtons = function () {
    var self = this;

    self.$form.find('.mepr-stripe-payment-request-element').each(function () {
      var paymentRequestElement = $(this);
      var countryCode = paymentRequestElement.data('locale-code');
      var stripe = Stripe(paymentRequestElement.data('stripe-public-key'), { locale: countryCode });

      if (self.isSpc) {
        var stripeAmount = self.$form.find(
            'input[name=mepr_stripe_txn_amount]').attr('value');
      } else {
        var stripeAmount = $('.mp_invoice input[name=mepr_stripe_txn_amount]').attr('value');
      }

      stripeAmount = parseInt(stripeAmount);
      try {
        self.paymentRequest = stripe.paymentRequest({
          country: countryCode,
          currency: paymentRequestElement.data('currency-code').toLowerCase(),
          total: {
            label: paymentRequestElement.data('total-text'),
            amount: stripeAmount, // Placeholder, will be updated later
          },
          requestPayerName: true,
          requestPayerEmail: true,
        });
      } catch (e) {
        $('.mepr-stripe-payment-request-wrapper').hide();
        return;
      }

      var elements = stripe.elements();
      var prButton = elements.create('paymentRequestButton', {
        paymentRequest: self.paymentRequest,
      });

      prButton.on('click', function(event) {
        self.$form.find('input[name="card-name"]').removeAttr('required');

        self.$form.find('.mepr-form-input:visible').each(function (i, obj) {
          window.mepr_validate_input(obj, true);
        });

        if (self.$form.find('.invalid:visible, .cc-error:visible').length > 0) {
          self.$form.find('.validation').addClass('failed');
          event.preventDefault();
        } else {
          self.$form.find('.validation').addClass('passed');
        }

        self.$form.find('input[name="card-name"]').attr('required', true);
      });

      // Check the availability of the Payment Request API first.
      self.paymentRequest.canMakePayment().then(function(result) {
        if (result) {
          prButton.mount(paymentRequestElement.get(0));
        } else {
          $('.mepr-stripe-payment-request-wrapper').hide();
        }
      });

      self.paymentRequest.on('paymentmethod', function (ev) {
        self.processingPaymentRequestButton = true;
        self.paymentRequestPaymentMethodId = ev.paymentMethod.id;
        self.$form.find('input[name="card-name"]').removeAttr('required');
        self.$form.find('.mepr-submit').trigger('click');
        ev.complete('success');
      });
    });
  }

  /**
   * Initialize Stripe elements
   */
  MeprStripeForm.prototype.initPaymentMethods = function () {
    var self = this;
    self.$form.find('.mepr-stripe-card-element').each(function () {
      var $cardElement = $(this);
      var $cardErrors = $cardElement.closest('.mp-form-row').find('.mepr-stripe-card-errors'),
          stripe = Stripe($cardElement.data('stripe-public-key'), { locale: $cardElement.data('locale-code').toLowerCase() }),
          elements = stripe.elements(),
          card = elements.create('card', {
            style: MeprStripeGateway.style,
            hidePostalCode: MeprStripeGateway.hide_postal_code === '1' ? true : false
          }),
          paymentMethodId = $cardElement.data('payment-method-id'),
          wrapperSelector = self.isSpc ? '.mepr-payment-method' : '.mp_payment_form_wrapper',
          $wrapper = $cardElement.closest(wrapperSelector);

      card.mount($cardElement[0]);

      card.addEventListener('change', function (event) {
        $cardErrors.text(event.error ? event.error.message : '');
      });

      self.paymentMethods.push({
        id: paymentMethodId,
        stripe: stripe,
        card: card,
        $cardErrors: $cardErrors,
        $wrapper: $wrapper,
        subscriptionId: null
      });
    });
  };

  /**
   * Handle the payment form submission
   *
   * @param {jQuery.Event} e
   */
  MeprStripeForm.prototype.handleSubmit = function (e) {
    var self = this;

    e.preventDefault();

    if (self.submitting) {
      return;
    }

    self.submitting = true;

    if (self.$form.find('.mepr-payment-methods-wrapper').is(':hidden')) {
      self.form.submit();
      return;
    }

    self.$form.find('.mepr-submit').prop('disabled', true);
    self.$form.find('.mepr-loading-gif').show();

    self.selectedPaymentMethod = self.getSelectedPaymentMethod();
    var isStripeCheckoutPageMode = self.$form.find('input[name=mepr_stripe_checkout_page_mode]').val();

    if (self.selectedPaymentMethod) {
      var $recaptcha = self.$form.find('[name="g-recaptcha-response"]'),
          extraData = {};

      if ($recaptcha.length) {
        extraData['g-recaptcha-response'] = $recaptcha.val();
      }

      if (self.processingPaymentRequestButton) {
        extraData.payment_method_id = self.paymentRequestPaymentMethodId;
        self.confirmPayment(extraData);
      } else {
        var cardData = {
          billing_details: self.getBillingDetails(self.selectedPaymentMethod)
        };

        self.selectedPaymentMethod.stripe.createPaymentMethod('card', self.selectedPaymentMethod.card, cardData).then(function (result) {
          if (result.error) {
            self.handlePaymentError(result.error.message);
          } else {
            extraData.payment_method_id = result.paymentMethod.id;
            self.confirmPayment(extraData);
          }
        });
      }
    } else {
      if (!self.isSpc && isStripeCheckoutPageMode == '1') {
        self.redirectToStripeCheckout(e);
        return;
      }

      const paymentMethodId = self.$form.find('input[name="mepr_payment_method"]:checked').data('payment-method-type');
      if (
          isStripeCheckoutPageMode == '1' && (
              paymentMethodId == 'Stripe' &&
              self.$form.find('[name=mepr_stripe_is_checkout]').val() == '1'
          )
      ) {
        self.redirectToStripeCheckout(e);
        return;
      }
      self.form.submit();
    }
  };

  /**
   * Get the currently selected payment method data
   *
   * @return {object|null}
   */
  MeprStripeForm.prototype.getSelectedPaymentMethod = function () {
    if (this.isSpc) {
      var paymentMethodId = this.$form.find('input[name="mepr_payment_method"]:checked').val();

      for (var i = 0; i < this.paymentMethods.length; i++) {
        if (this.paymentMethods[i].id === paymentMethodId) {
          return this.paymentMethods[i];
        }
      }

      return null;
    } else {
      return this.paymentMethods.length ? this.paymentMethods[0] : null;
    }
  };

  /**
   * Returns the form fields in a pretty key/value hash
   *
   * @return {object}
   */
  MeprStripeForm.prototype.getFormData = function () {
    var formData = new FormData( this.$form.get(0) );
    return Array.from(formData.entries()).reduce(function(obj, item) {
      obj[item[0]] = item[1];
      return obj;
    }, {});
  };

  /**
   * Get the billing details object to pass to Stripe
   *
   * @param  {object} selectedPaymentMethod
   * @return {object}
   */
  MeprStripeForm.prototype.getBillingDetails = function (selectedPaymentMethod) {
    var self = this,
        name = selectedPaymentMethod.$wrapper.find('input[name="card-name"]').val(),
        keys = {
          line1: 'mepr-address-one',
          line2: 'mepr-address-two',
          city: 'mepr-address-city',
          country: 'mepr-address-country',
          state: 'mepr-address-state',
          postal_code: 'mepr-address-zip'
        },
        address = {},
        addressFieldsPresent = false,
        details = {
          address: {}
        };

    if (typeof name == 'string' && name.length) {
      details.name = name;
    }

    $.each(keys, function (key, value) {
      var $field = self.$form.find('input[name="' + value + '"], select[name="' + value + '"]');

      if ($field.length) {
        var val = $field.val();

        if (typeof val == 'string' && val.length) {
          address[key] = val;
        }

        addressFieldsPresent = true;
      }
    });

    if (addressFieldsPresent) {
      details.address = address;
    } else {
      $.each(keys, function (key, value) {
        var cardAddressKey = value.replace('mepr-', 'card-'),
            $field = selectedPaymentMethod.$wrapper.find('input[name="' + cardAddressKey + '"]');

        if ($field.length) {
          var val = $field.val();

          if (typeof val == 'string' && val.length) {
            details.address[key] = val;
          }
        }
      });
    }

    return details;
  };

  /**
   * Allow the form to be submitted again
   */
  MeprStripeForm.prototype.allowResubmission = function () {
    this.submitting = false;
    this.processingPaymentRequestButton = false;
    this.paymentRequestPaymentMethodId = '';
    this.$form.find('.mepr-submit').prop('disabled', false);
    this.$form.find('.mepr-loading-gif').hide();
    this.$form.find('.mepr-form-has-errors').show();
    this.$form.find('.mepr-validation-error, .mepr-top-error').remove();
    this.$form.find('input[name="card-name"]').attr('required', true);
  };

  /**
   * Handle form validation errors
   *
   * @param {array} errors The validation errors array
   */
  MeprStripeForm.prototype.handleValidationErrors = function (errors) {
    // Allow the form to be submitted again
    this.allowResubmission();

    var topErrors = [];

    for (var key in errors) {
      if (errors.hasOwnProperty(key)) {
        var $field = this.$form.find('[name="' + key + '"]').first(),
            $label = $field.closest('.mp-form-row').find('.mp-form-label');

        if ($.isNumeric(key) || !$label.length) {
          topErrors.push(errors[key]);
        } else {
          $label.append($('<span class="mepr-validation-error">').html(errors[key]));
        }

        console.log(errors[key]);
      }
    }

    if (topErrors.length) {
      var $list = $('<ul>'),
          $wrap = $('<div class="mepr-top-error mepr_error">');

      for (var i = 0; i < topErrors.length; i++) {
        $list.append($('<li>').html(MeprStripeGateway.top_error.replace('%s', topErrors[i])));
      }

      $wrap.append($list).prependTo(this.$form);
    }
  };

  /**
   * Handle an error with the payment
   *
   * @param {string} error The error message to display
   */
  MeprStripeForm.prototype.handlePaymentError = function (error) {
    // Allow the form to be submitted again
    this.allowResubmission();

    // Inform the user if there was an error
    this.selectedPaymentMethod.$cardErrors.html(error);
    console.log(error);
  };

  /**
   * Handle the response from our Ajax endpoint
   *
   * @param {object} response
   * @param {string} textStatus
   * @param {object} jqXHR
   */
  MeprStripeForm.prototype.handleServerResponse = function (response, textStatus, jqXHR) {
    if (response === null || typeof response != 'object') {
      this.handlePaymentError(MeprStripeGateway.invalid_response_error);
      this.debugCheckoutError({
        status: jqXHR.status,
        status_text: jqXHR.statusText,
        response_text: jqXHR.responseText,
        text_status: textStatus,
        error_thrown: 'Response was null or not an object'
      });
    } else {
      if (response.transaction_id) {
        this.$form.find('input[name="mepr_transaction_id"]').val(response.transaction_id);
      }

      if (response.subscription_id) {
        this.selectedPaymentMethod.subscriptionId = response.subscription_id;
      }

      if (response.errors) {
        this.handleValidationErrors(response.errors);
      } else if (response.error) {
        this.handlePaymentError(response.error);

        if (response.destroy_payment_method) {
          this.selectedPaymentMethod.card.destroy();
        }
      } else if (response.requires_action) {
        this.handleAction(response);
      } else if (!this.$form.hasClass('mepr-payment-submitted')) {
        this.$form.addClass('mepr-payment-submitted');
        this.form.submit();
      }
    }
  };

  /**
   * Displays the card action dialog to the user, and confirms the payment if successful
   *
   * @param {object} response
   */
  MeprStripeForm.prototype.handleAction = function (response) {
    var self = this,
        stripe = this.selectedPaymentMethod.stripe,
        card = this.selectedPaymentMethod.card,
        data;

    if (response.action === 'confirmCardSetup') {
      data = {
        payment_method: {
          card: card,
          billing_details: self.getBillingDetails(this.selectedPaymentMethod)
        }
      };

      if (self.processingPaymentRequestButton) {
        stripe.confirmCardSetup(response.client_secret).then(function (result) {
          if (result.error) {
            self.handlePaymentError(result.error.message);
          } else {
            self.confirmPayment();
          }
        });
      } else {
        stripe.confirmCardSetup(response.client_secret, data).then(function (result) {
          if (result.error) {
            self.handlePaymentError(result.error.message);
          } else {
            self.confirmPayment();
          }
        });
      }
    } else if (response.action === 'confirmCardPayment') {
      data = {
        payment_method: {
          card: card,
          billing_details: self.getBillingDetails(this.selectedPaymentMethod)
        }
      };

      if (self.processingPaymentRequestButton) {
        stripe.confirmCardPayment(response.client_secret).then(function (result) {
          if (result.error) {
            self.handlePaymentError(result.error.message);
          } else {
            self.confirmPayment();
          }
        });
      } else {
        stripe.confirmCardPayment(response.client_secret, data).then(function (result) {
          if (result.error) {
            self.handlePaymentError(result.error.message);
          } else {
            self.confirmPayment();
          }
        });
      }
    } else {
      stripe.handleCardAction(response.client_secret).then(function (result) {
        if (result.error) {
          self.handlePaymentError(result.error.message);
        } else {
          self.confirmPayment({
            payment_intent_id: result.paymentIntent.id
          });
        }
      });
    }
  };

  /**
   * Create stripe checkout page session then redirect user o checkout.stripe.com
   *
   * @param e
   */
  MeprStripeForm.prototype.redirectToStripeCheckout = function(e) {
    var self = this,
        data = self.getFormData();
    $.extend(data, {
      action: 'mepr_stripe_create_checkout_session',
      mepr_current_url: document.location.href
    });

    // We don't want to hit our routes for processing the signup or payment forms
    delete data.mepr_process_signup_form;
    delete data.mepr_process_payment_form;

    var formData = new FormData();
    for (let key in data) {
      formData.append(key, data[key]);
    }
    $.ajax({
      type: 'POST',
      url: MeprStripeGateway.ajax_url,
      data: formData,
      dataType: 'json',
      cache: false,
      processData: false,
      contentType: false,
      headers: {
        'cache-control': 'no-cache'
      }
    })
    .done(function(result) {
      if (typeof result.errors !== 'undefined') {
        self.$form.find('.mepr-stripe-checkout-errors').html('');
        for (var i = 0; i < Object.entries(result.errors).length; i++) {
          self.$form.find('.mepr-stripe-checkout-errors').
              append('<p>' + Object.entries(result.errors)[i][1] + '</p>');
        }
        self.allowResubmission();
      }

      if (typeof result.error !== 'undefined') {
        self.$form.find('.mepr-stripe-checkout-errors').html(result.error);
        self.allowResubmission();
      }

      if (typeof result.errors !== 'undefined' || typeof result.error !== 'undefined') {
        return;
      }

      var stripe = Stripe(result.public_key);
      return stripe.redirectToCheckout({ sessionId: result.id });
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      if (jqXHR.status === 0) {
        // Don't send a debug email for errors with status 0
        self.handlePaymentError(MeprStripeGateway.error_please_try_again);
      } else {
        self.handlePaymentError(MeprStripeGateway.ajax_error);
        self.debugCheckoutError({
          status: jqXHR.status,
          status_text: jqXHR.statusText,
          response_text: jqXHR.responseText,
          text_status: textStatus,
          error_thrown: '' + errorThrown
        });
      }
    });
  };

  /**
   * Confirm the payment with our Ajax endpoint
   *
   * @param {object} [extraData] Additional data to send with the request
   */
  MeprStripeForm.prototype.confirmPayment = function (extraData) {
    var self = this,
        data = self.getFormData();

    $.extend(data, extraData || {}, {
      action: 'mepr_stripe_confirm_payment',
      mepr_current_url: document.location.href
    });

    if (self.selectedPaymentMethod.subscriptionId) {
      data.subscription_id = self.selectedPaymentMethod.subscriptionId;
    }

    // We don't want to hit our routes for processing the signup or payment forms
    delete data.mepr_process_signup_form;
    delete data.mepr_process_payment_form;

    var formData = new FormData();
    for (let key in data) {
      formData.append(key, data[key]);
    }

    $.ajax({
      type: 'POST',
      url: MeprStripeGateway.ajax_url,
      data: formData,
      dataType: 'json',
      cache: false,
      processData: false,
      contentType: false,
      headers: {
        'cache-control': 'no-cache'
      }
    })
    .done($.proxy(self.handleServerResponse, self))
    .fail(function (jqXHR, textStatus, errorThrown) {
      if (jqXHR.status === 0) {
        // Don't send a debug email for errors with status 0
        self.handlePaymentError(MeprStripeGateway.error_please_try_again);
      } else {
        self.handlePaymentError(MeprStripeGateway.ajax_error);
        self.debugCheckoutError({
          status: jqXHR.status,
          status_text: jqXHR.statusText,
          response_text: jqXHR.responseText,
          text_status: textStatus,
          error_thrown: '' + errorThrown
        });
      }
    });
  };

  /**
   * Debug a critical checkout error
   *
   * @param {object} data
   */
  MeprStripeForm.prototype.debugCheckoutError = function (data) {
    data.transaction_id = this.$form.find('input[name="mepr_transaction_id"]').val();
    data.customer_email = this.$form.find('input[name="user_email"]').val();

    $.ajax({
      type: 'POST',
      url: MeprStripeGateway.ajax_url,
      dataType: 'json',
      data: {
        action: 'mepr_stripe_debug_checkout_error',
        data: JSON.stringify(data)
      }
    });
  };
})(jQuery);
