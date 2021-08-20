var drawChart = function () {
  var currency_symbol = jQuery('div#mepr-widget-currency-symbol').attr('data-value');

  var args = {
    action: 'mepr_widget_report',
    report_nonce: MeprWidgetData.report_nonce
  };

  //Weekly stats
  jQuery
    .getJSON(ajaxurl, args, function (data) {
      var weeklyChartData = new google.visualization.DataTable(data);
      var weeklyChart = new google.visualization.AreaChart(document.getElementById('mepr-widget-report'));
      weeklyChart.draw(weeklyChartData, {vAxis: {format: currency_symbol}});
    });
};

google.charts.load('current', { packages: ['corechart'] });
google.charts.setOnLoadCallback(drawChart);
