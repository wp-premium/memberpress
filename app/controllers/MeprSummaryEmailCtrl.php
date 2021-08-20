<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprSummaryEmailCtrl extends MeprBaseCtrl {

  /**
   * Load the hooks for this controller
   */
  public function load_hooks() {
    add_filter('cron_schedules', array($this, 'add_cron_schedule'));
    add_action('mepr_summary_email', array($this, 'send_summary_email'));

    if(!wp_next_scheduled('mepr_summary_email')) {
      try {
        $date = new DateTime('next monday 00:05:00', new DateTimeZone('UTC'));
        wp_schedule_event($date->getTimestamp(), 'mepr_summary_email_interval', 'mepr_summary_email');
      } catch (Exception $e) {
        // Fail silently for now
      }
    }
  }

  /**
   * Add the weekly schedule to WP Cron
   *
   * @param  array $schedules
   * @return array
   */
  public function add_cron_schedule($schedules) {
    $schedules['mepr_summary_email_interval'] = array(
      'interval' => 604800, // weekly
      'display' => __('MemberPress Summary Email', 'memberpress')
    );

    return $schedules;
  }

  /**
   * Send the summary report email
   */
  public function send_summary_email() {
    $mepr_options = MeprOptions::fetch();

    if($mepr_options->disable_summary_email) {
      return;
    }

    try {
      $utc = new DateTimeZone('UTC');
      $tomorrow = new DateTimeImmutable('tomorrow', $utc);
      $report_date = $tomorrow->modify('last monday 00:00:00');
      $last_week_end = $report_date->modify('-1 day');
      $last_week_start = $report_date->modify('-7 days');

      $last_week = $previous_week = array(
        'failed' => 0,
        'pending' => 0,
        'refunded' => 0,
        'completed' => 0,
        'revenue' => 0,
        'refunds' => 0,
        'recurring' => 0,
        'new' => 0
      );

      for ($i = 0; $i < 7; $i++) {
        $ts = $last_week_start->getTimestamp() + MeprUtils::days($i);
        $month = gmdate('n', $ts);
        $day = gmdate('j', $ts);
        $year = gmdate('Y', $ts);
        $revenue = MeprReports::get_revenue($month, $day, $year);
        $recurring = MeprReports::get_recurring_revenue($month, $day, $year);

        $last_week['pending'] += MeprReports::get_transactions_count(MeprTransaction::$pending_str, $day, $month, $year);
        $last_week['failed'] += MeprReports::get_transactions_count(MeprTransaction::$failed_str, $day, $month, $year);
        $last_week['refunded'] += MeprReports::get_transactions_count(MeprTransaction::$refunded_str, $day, $month, $year);
        $last_week['completed'] += MeprReports::get_transactions_count(MeprTransaction::$complete_str, $day, $month, $year);

        $last_week['revenue'] += $revenue;
        $last_week['refunds'] += MeprReports::get_refunds($month, $day, $year);
        $last_week['recurring'] += $recurring;
        $last_week['new'] += $revenue - $recurring;

        $ts = $last_week_start->getTimestamp() - MeprUtils::days($i);
        $month = gmdate('n', $ts);
        $day = gmdate('j', $ts);
        $year = gmdate('Y', $ts);
        $revenue = MeprReports::get_revenue($month, $day, $year);
        $recurring = MeprReports::get_recurring_revenue($month, $day, $year);

        $previous_week['pending'] += MeprReports::get_transactions_count(MeprTransaction::$pending_str, $day, $month, $year);
        $previous_week['failed'] += MeprReports::get_transactions_count(MeprTransaction::$failed_str, $day, $month, $year);
        $previous_week['refunded'] += MeprReports::get_transactions_count(MeprTransaction::$refunded_str, $day, $month, $year);
        $previous_week['completed'] += MeprReports::get_transactions_count(MeprTransaction::$complete_str, $day, $month, $year);

        $previous_week['revenue'] += $revenue;
        $previous_week['refunds'] += MeprReports::get_refunds($month, $day, $year);
        $previous_week['recurring'] += $recurring;
        $previous_week['new'] += $revenue - $recurring;
      }

      if($last_week['revenue'] + $previous_week['revenue'] <= 0.00) {
        // Do not send the email if there has been no revenue for the last two weeks
        return;
      }

      $subject = sprintf(
        __('[MemberPress] Your summary report for the week of %s', 'memberpress'),
        MeprUtils::date('F j', $last_week_start, $utc)
      );

      $site = MeprUtils::blogname();
      $site = empty($site) ? get_bloginfo('url') : $site;
      $ad   = $this->get_ad();

      $message = MeprView::get_string('/admin/reports/summary_email', get_defined_vars());

      $headers = array(
        sprintf('Content-type: text/html; charset=%s', apply_filters('wp_mail_charset', get_bloginfo('charset')))
      );

      MeprUtils::wp_mail_to_admin($subject, $message, $headers);
    } catch (Exception $e) {
      // Fail silently for now
    }
  }

  /**
   * Get a random ad or educational tip to display in the email
   *
   * @return string
   */
  private function get_ad() {
    $url = add_query_arg(array(
      'ad-group' => apply_filters('mepr_summary_email_ad_group', 3),
      'orderby' => 'rand',
    ), 'https://sg-assets.caseproof.com/wp-json/wp/v2/ads');

    $response = wp_remote_get($url);
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if($code == 200 && $body) {
      $ads = json_decode($body, true);

      if(is_array($ads) && isset($ads[0]['rendered_ad'])) {
        return $ads[0]['rendered_ad'];
      }
    }

    return '';
  }

  /**
   * Get the HTML representing the percentage difference between two values
   *
   * @param  int|float $new_value             The new value
   * @param  int|float $previous_value        The previous value
   * @param  bool      $positive_is_favorable Whether a positive change is favorable
   * @return string
   */
  public static function get_change_percent($new_value, $previous_value, $positive_is_favorable = true) {
    if($new_value == $previous_value) {
      $change = 0;
    } elseif($new_value == 0) {
      $change = -100;
    } elseif($previous_value == 0) {
      $change = 100;
    } else {
      $change = (($new_value - $previous_value) / $previous_value) * 100;
    }

    if($change == 0) {
      $color = '#757575';
      $image = '';
    } elseif ($change < 0 && $positive_is_favorable) {
      $color = '#db4437';
      $image = '/down-arrow-red.png';
    } elseif ($change > 0 && !$positive_is_favorable) {
      $color = '#db4437';
      $image = '/up-arrow-red.png';
    } elseif ($change < 0) {
      $color = '#0f9d58';
      $image = '/down-arrow-green.png';
    } else {
      $color = '#0f9d58';
      $image = '/up-arrow-green.png';
    }

    $output = sprintf(
      '<div style="font-family:Helvetica,Arial,sans-serif;font-size:12px;color:%s;">%s%s</div>',
      $color,
      $image ? '<img src="' . esc_url(MEPR_IMAGES_URL . $image) . '" style="vertical-align:baseline;margin-right:1px;">' : '',
      $change == 0 ? '&nbsp;' : '<span>' . number_format_i18n(abs($change), 0) . '%</span>'
    );

    return $output;
  }
}
