(function ($) {
    $(document).ready(function () {
        $('.mepr-signup-form, #mepr-paypal-payment-form').each(function () {
            new MeprPayPalForm(this);
        });
    });

    function MeprPayPalForm(form) {
        this.form = form;
        this.$form = $(form);
        this.isSpc = this.$form.hasClass('mepr-signup-form');
        this.initPayPalSmartButtons();
    }

    /**
     * Returns the form fields in a pretty key/value hash
     *
     * @return {object}
     */
    MeprPayPalForm.prototype.getFormData = function () {
        var formData = new FormData(this.$form.get(0));
        return Array.from(formData.entries()).reduce(function (obj, item) {
            obj[item[0]] = item[1];
            return obj;
        }, {});
    };

    MeprPayPalForm.prototype.initPayPalSmartButtons = function () {
        var self = this;
        var button_container = this.$form.find('.mepr-paypal-button-container');
        var button_id = button_container.attr('id');
        var ajax_url = button_container.data('ajax-url');
        var webhook_url = button_container.data('webhook-url');
        var successURL = button_container.data('success-url');
        var methodID = button_container.data('method-id');
        var isOneTimePayment = button_container.data('is-one-time') == '1' ? true : false;
        var formData = self.getFormData();

        formData.mepr_payment_method = methodID;

        if (isOneTimePayment) {
            paypal.Buttons({
                createOrder: function (data, actions) {
                    formData = self.getFormData();
                    formData.mepr_coupon_code = self.$form.find('input[name=mepr_coupon_code]').val();
                    return fetch(ajax_url, {
                        method: 'post',
                        body: JSON.stringify(formData),
                        headers: {
                            'content-type': 'application/json'
                        }
                    }).then(function (res) {
                        console.log(res);
                        return res.json();
                    }).then(function (data) {
                        console.log(data);

                        if (typeof data.errors !== 'undefined') {
                            var errors = data.errors;
                            console.log(typeof errors);
                            errors = Object.values(errors);
                            errors = errors.join('.');
                            self.$form.find('.mepr-paypal-card-errors').html(errors);
                            return;
                        }

                        return data.id; // Use the key sent by your server's response, ex. 'id' or 'token'
                    }).catch(function (e) {
                        var errors = e.data.errors;
                        errors = errors.join('.');
                        self.$form.find('.mepr-paypal-card-errors').text(errors);
                    });
                },
                onApprove: function (data, actions) {
                    return fetch(webhook_url, {
                        method: 'post',
                        headers: {
                            'content-type': 'application/json'
                        },
                        body: JSON.stringify({
                            event_type: 'MEMBERPRESS_CAPTURE_ORDER',
                            order_id: data.orderID
                        })
                    }).then(function (res) {
                        return res.json();
                    }).then(function (captureData) {
                        if (captureData.error === 'INSTRUMENT_DECLINED') {
                            return actions.restart();
                        } else {
                            console.log(data);
                            successURL += '?token=' + data.orderID;
                            window.location.href = successURL;
                        }
                    }).catch(function (e) {
                        var errors = data.errors;
                        errors = errors.join('.');
                        self.$form.find('.mepr-paypal-card-errors').text(errors);
                    });
                }
            }).render('#' + button_id);
        } else {
            paypal.Buttons({
                createSubscription: function (data, actions) {
                    formData = self.getFormData();
                    formData.mepr_coupon_code = self.$form.find('input[name=mepr_coupon_code]').val();
                    return fetch(ajax_url, {
                        method: 'post',
                        body: JSON.stringify(formData),
                        headers: {
                            'content-type': 'application/json'
                        }
                    }).then(function (res) {
                        return res.json();
                    }).then(function (data) {
                        if (typeof data.errors !== 'undefined') {
                            var errors = data.errors;
                            console.log(typeof errors);
                            errors = Object.values(errors);
                            errors = errors.join('.');
                            self.$form.find('.mepr-paypal-card-errors').html(errors);
                            return;
                        }
                        console.log(data);
                        return data.id; // Use the key sent by your server's response, ex. 'id' or 'token'
                    }).catch(function (e) {
                        var errors = e.errors;
                        errors = errors.join('.');
                        self.$form.find('.mepr-paypal-card-errors').text(errors);
                    });
                },
                onApprove: function (data, actions) {
                    console.log(data);
                    successURL += '?subscription_id=' + data.subscriptionID + '&token=' + data.orderID;
                    window.location.href = successURL;
                }
            }).render('#' + button_id);
        }
    }
})(jQuery);
