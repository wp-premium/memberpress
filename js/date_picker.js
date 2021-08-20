jQuery(document).ready(function($) {
  //I guess these can be tweaked as time goes, but for now these seem like reasonable targets
  var currentYear = new Date().getFullYear();
  var pastYears = currentYear - 100;
  var futureYears = currentYear + 50;

  var dateFormat = 'yy-mm-dd';
  var timeFormat = 'HH:mm:ss';
  var showTime   = true;
  var translations = {};
  var options = {};

  //Front End needs to display cleaner
  if(typeof MeprDatePicker != "undefined") {
    if(MeprDatePicker.hasOwnProperty('dateFormat')){
      dateFormat = String(MeprDatePicker.dateFormat);
    }
    timeFormat = MeprDatePicker.timeFormat;
    showTime = Boolean(MeprDatePicker.showTime);
    translations = MeprDatePicker.translations;
  }

  if(typeof translations !== 'undefined' && translations != null) {
      options = translations;
  }

  options['dateFormat'] = dateFormat;
  options['timeFormat'] = timeFormat;
  options['yearRange'] = pastYears + ":" + futureYears;
  options['changeMonth'] = true;
  options['changeYear'] = true;
  options['showTime'] = showTime;
  options['onSelect'] = function (date, inst) {
      $(this).trigger('mepr-date-picker-selected', [date, inst]);
    };
  options['onChangeMonthYear'] = function (month, year, inst) {
      $(this).trigger('mepr-date-picker-changed', [month, year, inst]);
    };
  options['onClose'] = function (date, inst) {
      $(this).val(date.trim()); //Trim off white-space if any
      $(this).trigger('mepr-date-picker-closed', [date, inst]);
    };

  $('.mepr-date-picker').datetimepicker( options );
});
