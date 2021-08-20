(function ($) {
  $(document).ready(function() {
    //Trigger a click on stripe checkout automatically
    var done = false; //Prevent double submit (for some reason)
    if(!done) {
      $("button.stripe-button-el").trigger("click");
      done = true;
    }

    var stripe = Stripe(MeprStripeAccountForm.public_key);
    var elements = stripe.elements();
    var card = elements.create('card', { style: MeprStripeAccountForm.style });
    card.mount('#card-element');
    card.addEventListener('change', function(event) {
      var displayError = document.getElementById('card-errors');
      if (event.error) {
        displayError.textContent = event.error.message;
      } else {
        displayError.textContent = '';
      }
    });

    var stripePaymentForm = $('#mepr-stripe-payment-form');
    stripePaymentForm.on('submit', function(e) {
      e.preventDefault();
      stripePaymentForm.find('.mepr-submit').disabled = true;
      stripePaymentForm.find('.mepr-loading-gif').show();

      var cardData = {
        billing_details: getBillingDetails()
      };

      stripe.createPaymentMethod('card', card, cardData).then(function(result) {
        if (result.error) {
          handlePaymentError(result.error.message);
        } else {
          confirmPayment({
            payment_method_id: result.paymentMethod.id
          });
        }
      });

      return false; // submit from callback
    });

    /**
     * Returns the form fields in a pretty key/value hash
     *
     * @param  {jQuery} form
     * @return {object}
     */
    function getFormData(form) {
      return form.serializeArray().reduce(function(obj, item) {
        obj[item.name] = item.value;
        return obj;
      }, {});
    }

    /**
     * Get the billing details object to pass to Stripe
     *
     * @return {object}
     */
    function getBillingDetails() {
      var formData = getFormData(stripePaymentForm),
        keys = {
          line1: 'card-address-one',
          line2: 'card-address-two',
          city: 'card-address-city',
          country: 'card-address-country',
          state: 'card-address-state',
          postal_code: 'card-address-zip'
        },
        details = {
          address: {}
        };

      if (formData.hasOwnProperty('card-name') && typeof formData['card-name'] == 'string' && formData['card-name'].length) {
        details.name = formData['card-name'];
      }

      $.each(keys, function (key, value) {
        if (formData.hasOwnProperty(value) && typeof formData[value] == 'string' && formData[value].length) {
          details.address[key] = formData[value];
        }
      });

      return details;
    }

    /**
     * Handle an error with the payment
     *
     * @param {string} message The error message to display
     */
    function handlePaymentError(message) {
      console.log(message);
      // re-enable the submit button
      stripePaymentForm.find('.mepr-submit').prop('disabled', false);
      stripePaymentForm.find('.mepr-loading-gif').hide();
      stripePaymentForm.find('.mepr-form-has-errors').show();

      // Inform the user if there was an error
      var errorElement = document.getElementById('card-errors');
      errorElement.textContent = message;
    }

    /**
     * Handle the response from our Ajax endpoint after creating the SetupIntent
     *
     * @param {object} response
     */
    function handleServerResponse(response) {
      if (response === null || typeof response != 'object') {
        handlePaymentError(MeprStripeAccountForm.invalid_response_error)
      } else {
        if (response.error) {
          handlePaymentError(response.error);
        } else if (response.requires_action) {
          handleAction(response);
        } else if (!stripePaymentForm.hasClass('mepr-payment-submitted')) {
          if (response.is_payment) {
            stripePaymentForm.append('<input type="hidden" name="mepr_stripe_update_is_payment" value="1">');
          }

          stripePaymentForm.addClass('mepr-payment-submitted');
          stripePaymentForm[0].submit();
        }
      }
    }

    /**
     * Displays the card action dialog to the user
     *
     * @param {object} response
     */
    function handleAction(response) {
      var data = {
        payment_method: {
          card: card,
          billing_details: getBillingDetails()
        }
      };

      if (response.action === 'confirmCardPayment') {
        stripe.confirmCardPayment(response.client_secret, data).then(function (result) {
          if (result.error) {
            handlePaymentError(result.error.message);
          } else {
            confirmPayment({
              payment_intent_id: result.paymentIntent.id
            });
          }
        });
      } else {
        stripe.confirmCardSetup(response.client_secret, data).then(function (result) {
          if (result.error) {
            handlePaymentError(result.error.message);
          } else {
            confirmPayment({
              setup_intent_id: result.setupIntent.id
            });
          }
        });
      }
    }

    /**
     * Confirm the payment with our Ajax endpoint
     *
     * @param {object} extraData Additional data to send with the request
     */
    function confirmPayment(extraData) {
      var data = getFormData(stripePaymentForm);

      $.extend(data, extraData || {}, {
        action: 'mepr_stripe_update_payment_method',
        subscription_id: stripePaymentForm.data('sub-id')
      });

      $.ajax({
        type: 'POST',
        url: MeprStripeAccountForm.ajax_url,
        dataType: 'json',
        data: data
      })
      .done(handleServerResponse)
      .fail(function () {
        handlePaymentError(MeprStripeAccountForm.ajax_error);
      });
    }
  });
})(jQuery);
