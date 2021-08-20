<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo esc_attr(apply_filters('wp_mail_charset', get_bloginfo('charset'))); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body style="margin:0;padding:0;">
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
        <td align="center" valign="top">
          <table border="0" cellspacing="0" cellpadding="0" width="600">
            <?php do_action('mepr-weekly-summary-email-inner-table-top-tr'); ?>
            <tr>
              <td valign="top">
                <div style="text-align:center;padding:60px 0;">
                  <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/memberpress-logo.png'); ?>" alt="MemberPress logo" width="350" />
                </div>
                <div style="padding:0 0 30px 0;">
                  <p style="font-family:Helvetica,Arial,sans-serif;line-height:1.5;">
                    <?php
                      printf(
                        // translators: %1$s: the site title, %2$s: the week date range
                        esc_html__('Here\'s the summary report for %1$s for the week of %2$s. Enjoy!', 'memberpress'),
                        esc_html($site),
                        sprintf(
                          '<b>%s - %s</b>',
                          esc_html(MeprUtils::date('l, F j', $last_week_start, $utc)),
                          esc_html(MeprUtils::date('l, F j', $last_week_end, $utc))
                        )
                      );
                    ?>
                  </p>
                </div>
                <div style="padding:0 0 30px 0;">
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                    <tr>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Amount Collected', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo MeprAppHelper::format_currency(($last_week['revenue'] + $last_week['refunds']), true, false); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent(($last_week['revenue'] + $last_week['refunds']), ($previous_week['revenue'] + $previous_week['refunds'])); ?>
                        </div>
                      </td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Amount Refunded', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo MeprAppHelper::format_currency($last_week['refunds'], true, false); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['refunds'], $previous_week['refunds'], false); ?>
                        </div>
                      </td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Total Income', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo MeprAppHelper::format_currency($last_week['revenue'], true, false); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['revenue'], $previous_week['revenue']); ?>
                        </div>
                      </td>
                    </tr>
                  </table>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td width="16.66666666%">&nbsp;</td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Recurring Income', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo MeprAppHelper::format_currency($last_week['recurring'], true, false); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['recurring'], $previous_week['recurring']); ?>
                        </div>
                      </td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('New Income', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo MeprAppHelper::format_currency($last_week['new'], true, false); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['new'], $previous_week['new']); ?>
                        </div>
                      </td>
                      <td width="16.66666666%">&nbsp;</td>
                    </tr>
                  </table>
                </div>
                <div style="padding:0 0 30px 0;">
                  <p style="font-family:Helvetica,Arial,sans-serif;"><?php esc_html_e("We've also included a breakdown of your transactions below.", 'memberpress'); ?></p>
                </div>
                <div style="padding:0 0 30px 0;">
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                    <tr>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Refunded Transactions', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo esc_html($last_week['refunded']); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['refunded'], $previous_week['refunded'], false); ?>
                        </div>
                      </td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Pending Transactions', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo esc_html($last_week['pending']); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['pending'], $previous_week['pending'], false); ?>
                        </div>
                      </td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Failed Transactions', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo esc_html($last_week['failed']); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['failed'], $previous_week['failed'], false); ?>
                        </div>
                      </td>
                    </tr>
                  </table>
                  <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                    <tr>
                      <td valign="top" align="center" width="33.33333333%">&nbsp;</td>
                      <td valign="top" align="center" width="33.33333333%">
                        <div style="border:1px solid #ccc;text-align:center;margin:0 5px;padding:10px;">
                          <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;"><?php esc_html_e('Completed Transactions', 'memberpress'); ?></div>
                          <div style="font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:21px;padding:10px 0;"><?php echo esc_html($last_week['completed']); ?></div>
                          <?php echo MeprSummaryEmailCtrl::get_change_percent($last_week['completed'], $previous_week['completed']); ?>
                        </div>
                      </td>
                      <td valign="top" align="center" width="33.33333333%">&nbsp;</td>
                    </tr>
                  </table>
                </div>
                <?php if($ad) : ?>
                  <div style="padding:0 0 30px 0;">
                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                      <tr>
                        <td valign="top" align="center">
                          <?php echo $ad; ?>
                        </td>
                      </tr>
                    </table>
                  </div>
                <?php endif; ?>
                <div style="padding:0 0 30px 0;">
                  <p style="font-family:Helvetica,Arial,sans-serif;font-size:13px;">
                    <?php
                      // translators: %1$s: open link tag, %2$s: close link tag
                      printf(
                        esc_html__('P.S. Want to unsubscribe from these emails? %sClick here to access the MemberPress settings%s where you can disable the Weekly Summary Email.', 'memberpress'),
                        sprintf('<a href="%s" target="_blank">', esc_url(admin_url('admin.php?page=memberpress-options#mepr-general'))),
                        '</a>'
                      );
                    ?>
                  </p>
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
