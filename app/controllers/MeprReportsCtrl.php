<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprReportsCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_action('admin_enqueue_scripts', 'MeprReportsCtrl::enqueue_scripts');
    add_action('wp_ajax_mepr_export_report', 'MeprReportsCtrl::csv');
    add_action('wp_ajax_mepr_pie_report', 'MeprReportsCtrl::load_pie');
    add_action('wp_ajax_mepr_month_report', 'MeprReportsCtrl::load_monthly');
    add_action('wp_ajax_mepr_year_report', 'MeprReportsCtrl::load_yearly');
    add_action('wp_ajax_mepr_widget_report', 'MeprReportsCtrl::load_widget');
  }

  public static function main() {
    $mepr_options = MeprOptions::fetch();

    if( (isset($_GET['month']) || isset($_GET['year']) || isset($_GET['product'])) &&
        !check_admin_referer('mepr_customize_report', 'mepr_reports_nonce') ) {
      MeprView::render("/admin/unauthorized", get_defined_vars());
      return;
    }

    $curr_month = (isset($_GET['month']) && !empty($_GET['month']))?$_GET['month']:gmdate('n');
    $curr_year = (isset($_GET['year']) && !empty($_GET['year']))?$_GET['year']:gmdate('Y');
    $curr_product = (isset($_GET['product']) && !empty($_GET['product']))?$_GET['product']:'all';

    MeprView::render("/admin/reports/main", get_defined_vars());
  }

  public static function enqueue_scripts($hook) {
    $local_data = array(
      'report_nonce' => wp_create_nonce('mepr_reports')
    );

    if($hook == 'memberpress_page_memberpress-reports') {
      wp_enqueue_script('mepr-google-jsapi', 'https://www.gstatic.com/charts/loader.js', array(), MEPR_VERSION);
      wp_enqueue_script('mepr-reports-js', MEPR_JS_URL.'/admin_reports.js', array('jquery', 'mepr-google-jsapi'), MEPR_VERSION, true);
      wp_enqueue_style('mepr-reports-css', MEPR_CSS_URL.'/admin-reports.css', array(), MEPR_VERSION);

      wp_localize_script('mepr-reports-js', 'MeprReportData', $local_data);
    }
  }

  public static function csv() {
    check_ajax_referer('export_report','mepr_reports_nonce');

    if(!MeprUtils::is_mepr_admin()) { MeprUtils::exit_with_status(403,__('Forbidden', 'memberpress')); }

    $export = $_REQUEST['export'];
    $valid_exports = array( 'widget', 'yearly', 'monthly' );
    if( in_array( $export, $valid_exports ) ) {
      call_user_func( "MeprReportsCtrl::export_{$export}" );
    }
  }

  public static function export_widget() {
    $start_date = date_i18n('Y-m-d', time() - MeprUtils::days(6), true);
    $end_date = date_i18n('Y-m-d', time(), true);
    $filename = "memberpress-report-{$start_date}-to-{$end_date}";
    $txns = MeprReports::get_widget_data('transactions');
    $amts = MeprReports::get_widget_data('amounts');
    $results = self::format_for_csv( $txns, $amts );
    MeprUtils::render_csv( $results, $filename );
  }

  public static function load_widget() {
    check_ajax_referer('mepr_reports', 'report_nonce');

    $mepr_options = MeprOptions::fetch();
    $currency_symbol = $mepr_options->currency_symbol;
    $results = MeprReports::get_widget_data();
    $chart_data =
      array( 'cols' =>
        array(
          array("label" => __('Date', 'memberpress'), 'type' => 'string'),
          array("label" => __('Completed', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Pending', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Failed', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Refunded', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip'))
        )
      );

    foreach($results as $r) {
      $tooltip_date = date_i18n('M j, Y', mktime(0, 0, 0, gmdate('n', strtotime($r->date)), gmdate('j', strtotime($r->date)), gmdate('Y', strtotime($r->date))), true);

      $chart_data['rows'][] =
        array( 'c' =>
          array(
            array('v' => date_i18n('M j', mktime(0, 0, 0, gmdate('n', strtotime($r->date)), gmdate('j', strtotime($r->date)), gmdate('Y', strtotime($r->date))), true), 'f' => null),
            array('v' => (int)$r->c, 'f' => null),
            array('v' => $tooltip_date."\n".__('Completed:', 'memberpress').' '.$currency_symbol.(float)$r->c, 'f' => null),
            array('v' => (int)$r->p, 'f' => null),
            array('v' => $tooltip_date."\n".__('Pending:', 'memberpress').' '.$currency_symbol.(float)$r->p, 'f' => null),
            array('v' => (int)$r->f, 'f' => null),
            array('v' => $tooltip_date."\n".__('Failed:', 'memberpress').' '.$currency_symbol.(float)$r->f, 'f' => null),
            array('v' => (int)$r->r, 'f' => null),
            array('v' => $tooltip_date."\n".__('Refunded:', 'memberpress').' '.$currency_symbol.(float)$r->r, 'f' => null)
          )
        );
    }

    echo json_encode($chart_data);
    die();
  }

  public static function load_pie() {
    check_ajax_referer('mepr_reports', 'report_nonce');

    $year = (isset($_REQUEST['year']))?$_REQUEST['year']:false;
    $month = (isset($_REQUEST['month']))?$_REQUEST['month']:false;
    $results = MeprReports::get_pie_data($year, $month);

    $chart_data =
      array( 'cols' =>
        array(
          array("label" => __('Membership', 'memberpress'), 'type' => 'string'),
          array("label" => __('Transactions', 'memberpress'), 'type' => 'number')
        )
      );

    foreach($results as $result) {
      $product = ($result->product)?$result->product:__('Other', 'memberpress');
      $chart_data['rows'][] =
        array( 'c' =>
          array(
            array('v' => $product, 'f' => null),
            array('v' => (int)$result->transactions, 'f' => null)
          )
        );
    }

    echo json_encode($chart_data);
    die();
  }

  public static function export_monthly() {
    self::monthly_table(true);
  }

  public static function load_monthly() {
    check_ajax_referer('mepr_reports', 'report_nonce');
    self::monthly_table();
  }

  public static function monthly_table( $export = false ) {
    $mepr_options = MeprOptions::fetch();
    $type = (isset($_REQUEST['type']) && !empty($_REQUEST['type']))?$_REQUEST['type']:'amounts';
    $currency_symbol = ($type == 'amounts')?$mepr_options->currency_symbol:'';
    $month = (isset($_REQUEST['month']) && !empty($_REQUEST['month']))?$_REQUEST['month']:gmdate('n');
    $year = (isset($_REQUEST['year']) && !empty($_REQUEST['year']))?$_REQUEST['year']:gmdate('Y');
    $product = (isset($_REQUEST['product']) && $_GET['product'] != 'all')?$_REQUEST['product']:'all';
    $q = (isset($_REQUEST['q']) && $_REQUEST['q'] != 'none')?$_REQUEST['q']:array();

    if( $export ) {
      $txns = MeprReports::get_monthly_data('transactions', $month, $year, $product, $q);
      $amts = MeprReports::get_monthly_data('amounts', $month, $year, $product, $q);
      $filename = "memberpress-monthly-{$month}-{$year}-{$type}-for-{$product}";
      $results = self::format_for_csv( $txns, $amts );
      MeprUtils::render_csv( array_values($results), $filename );
    }

    $results = MeprReports::get_monthly_data($type, $month, $year, $product);

    $chart_data =
      array( 'cols' =>
        array(
          array("label" => MeprUtils::period_type_name('days'), 'type' => 'string'),
          array("label" => __('Completed', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Pending', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Failed', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Refunded', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip'))
        )
      );

    foreach($results as $r) {
      $tooltip_date = date_i18n('M j, Y', mktime(0, 0, 0, $month, $r->day, $year), true);

      $chart_data['rows'][] =
        array( 'c' =>
          array(
            array('v' => $r->day, 'f' => null),
            array('v' => (int)$r->c, 'f' => null),
            array('v' => $tooltip_date."\n".__('Completed:', 'memberpress').' '.$currency_symbol.(float)$r->c, 'f' => null),
            array('v' => (int)$r->p, 'f' => null),
            array('v' => $tooltip_date."\n".__('Pending:', 'memberpress').' '.$currency_symbol.(float)$r->p, 'f' => null),
            array('v' => (int)$r->f, 'f' => null),
            array('v' => $tooltip_date."\n".__('Failed:', 'memberpress').' '.$currency_symbol.(float)$r->f, 'f' => null),
            array('v' => (int)$r->r, 'f' => null),
            array('v' => $tooltip_date."\n".__('Refunded:', 'memberpress').' '.$currency_symbol.(float)$r->r, 'f' => null)
          )
        );
    }

    echo json_encode($chart_data);
    die();
  }

  public static function export_yearly() {
    self::yearly_table(true);
  }

  public static function load_yearly() {
    check_ajax_referer('mepr_reports', 'report_nonce');
    self::yearly_table();
  }

  public static function yearly_table($export=false) {
    $mepr_options = MeprOptions::fetch();
    $type = (isset($_REQUEST['type']) && !empty($_REQUEST['type']))?$_REQUEST['type']:'amounts';
    $currency_symbol = ($type == 'amounts')?$mepr_options->currency_symbol:'';
    $year = (isset($_REQUEST['year']) && !empty($_REQUEST['year']))?$_REQUEST['year']:gmdate('Y');
    $product = (isset($_REQUEST['product']) && $_GET['product'] != 'all')?$_REQUEST['product']:'all';
    $q = (isset($_REQUEST['q']) && $_GET['q'] != 'none')?$_REQUEST['q']:'';

    if( $export ) {
      $filename = "memberpress-yearly-{$year}-{$type}-for-{$product}";
      $txns = MeprReports::get_yearly_data('transactions', $year, $product, $q);
      $amts = MeprReports::get_yearly_data('amounts', $year, $product, $q);
      $results = self::format_for_csv( $txns, $amts );
      MeprUtils::render_csv( $results, $filename );
    }

    $results = MeprReports::get_yearly_data($type, $year, $product);

    $chart_data =
      array( 'cols' =>
        array(
          array("label" => MeprUtils::period_type_name('months'), 'type' => 'string'),
          array("label" => __('Completed', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Pending', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Failed', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip')),
          array("label" => __('Refunded', 'memberpress'), 'type' => 'number'),
          array("role" => 'tooltip', 'type' => 'string', 'p' => array('role' => 'tooltip'))
        )
      );

    foreach($results as $r) {
      $tooltip_date = date_i18n('M, Y', mktime(0, 0, 0, $r->month, 15, $year), true);
      $chart_data['rows'][] =
        array( 'c' =>
          array(
            array('v' => MeprUtils::month_names( true, $r->month, true), 'f' => null),
            array('v' => (int)$r->c, 'f' => null),
            array('v' => $tooltip_date."\n".__('Completed:', 'memberpress').' '.$currency_symbol.(float)$r->c, 'f' => null),
            array('v' => (int)$r->p, 'f' => null),
            array('v' => $tooltip_date."\n".__('Pending:', 'memberpress').' '.$currency_symbol.(float)$r->p, 'f' => null),
            array('v' => (int)$r->f, 'f' => null),
            array('v' => $tooltip_date."\n".__('Failed:', 'memberpress').' '.$currency_symbol.(float)$r->f, 'f' => null),
            array('v' => (int)$r->r, 'f' => null),
            array('v' => $tooltip_date."\n".__('Refunded:', 'memberpress').' '.$currency_symbol.(float)$r->r, 'f' => null)
          )
        );
    }

    echo json_encode($chart_data);
    die();
  }

  public static function format_for_csv( $txns, $amts ) {
    $tmap = array( 'date' => 'date',
                   'day' => 'day',
                   'month' => 'month',
                   'p' => 'pending.count',
                   'f' => 'failed.count',
                   'c' => 'complete.count',
                   'r' => 'refunded.count' );

    $amap = array( 'p' => 'pending.amount',
                   'f' => 'failed.amount',
                   't' => 'collected.amount',
                   'r' => 'refunded.amount',
                   'x' => 'tax.amount',
                   'c' => 'complete.amount' );

    $valid_cols = array_keys( $tmap );
    $ta_cols = array_keys( $amap );
    $a_cols = array_diff( $ta_cols, $valid_cols );

    $txns = array_values( $txns );
    $amts = array_values( $amts );

    $csv = array();
    for( $i = 0; $i < count($txns); $i++ ) {
      $csv[$i] = array();

      // Go through the columns that have txn and amt columns
      foreach( $txns[$i] as $label => $value ) {
        if( in_array( $label, $valid_cols ) ) {
          $csv[$i][$tmap[$label]] = $value ? $value : 0;

          if( in_array( $label, $ta_cols ) ) {
            $csv[$i][$amap[$label]] = $amts[$i]->{$label} ? $amts[$i]->{$label} : 0.00;
          }
        }
      }

      // Pickup all the amount only variables
      foreach( $a_cols as $index => $label ) {
        if( in_array( $label, $ta_cols ) ) {
          $csv[$i][$amap[$label]] = $amts[$i]->{$label} ? $amts[$i]->{$label} : 0.00;
        }
      }
    }

    return $csv;
  }
}
