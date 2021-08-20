(function($) {
  const mepr_ssl_geoip_services = {
    caseproof: {
      url:    'https://cspf-locate.herokuapp.com?callback=?',
      cindex: 'country_code',
      sindex: 'region_code',
      used:   false,
      type:   'jsonp', // Eventually move to CORS to prevent blocking
    }
  }

  class GeoLocation {
    constructor() {
      this.country = '';
      this.state = '';
    }

    // Calls the geoip service to get the location.
    // Updates dropdowns and mepr-geo-country hidden field
    locate() {
      let source_key = 'caseproof';
      let source = mepr_ssl_geoip_services[source_key];

      $.ajax({
        url: source.url,
        method: 'GET',
        timeout: 2000,
        dataType: source.type,
      })
      .done (function(data) {
        if(data[source.cindex] !== undefined) this.country = data[source.cindex];
        if(data[source.sindex] !== undefined) this.state   = data[source.sindex];

        // Method resides in i18njs
        mepr_set_locate_inputs(this.country, this.state);
      });

      return false;
    }
  }

  $(document).ready(function($) {
    new GeoLocation().locate();

    var mepr_show_vat_number = function(form) {
      if ($.inArray(form.find('select[name="mepr-address-country"]').val(),MpVat.countries) >= 0) {
        form.find('.mepr_vat_customer_type_row').slideDown();
        if (form.find('.mepr_vat_customer_type-consumer').is(':checked')) {
          form.find('.mepr_vat_number_row').slideUp();
        }
        else if (form.find('.mepr_vat_customer_type-business').is(':checked')) {
          form.find('.mepr_vat_number_row').slideDown();
        }

        var tmpDate = new Date();

        if (
          form.find('select[name="mepr-address-country"]').val() !== 'GB'
          && MpVat.vat_country === 'GB'
          && tmpDate.getFullYear() > 2020
        ) {
          form.find('.mepr_vat_customer_type_row').slideUp();
          form.find('.mepr_vat_number_row').slideUp();
        }
      }
      else {
        form.find('.mepr_vat_customer_type_row').slideUp();
        form.find('.mepr_vat_number_row').slideUp();
      }
    };

    $.each($('.mepr-countries-dropdown'), function(i,obj) {
      var form = $(obj).closest('.mepr-form');
      mepr_show_vat_number(form);
    });

    $('.mepr_vat_customer_type-consumer, .mepr_vat_customer_type-business').on('click', function(e) {
      var form = $(this).closest('.mepr-form');
      mepr_show_vat_number(form);
    });

    $('.mepr-countries-dropdown').on('change mepr-geolocated', function(e) {
      var form = $(this).closest('.mepr-form');
      mepr_show_vat_number(form);
    });

    $('.mepr-form input[name="mepr_vat_number"]').on('mepr-validate-input', function (e) {
      var form = $(this).closest('.mepr-form');

      var country = form.find('select[name="mepr-address-country"]').val();
      var is_business = form.find('.mepr_vat_customer_type-business').is(':checked');
      var vat_number = form.find('input[name="mepr_vat_number"]').val();

      vat_number = vat_number.replace(/[-.●]/g,'');

      var invalid = ($.inArray(country,MpVat.countries) >= 0 &&
                     is_business && vat_number.length > 0 &&
                     !vat_number.match(new RegExp('^'+MpVat.rates[country].fmt+'$'), 'i'));

      mpToggleFieldValidation($(this), !invalid);
    });
  });
})(jQuery);
