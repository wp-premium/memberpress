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
            <tr>
              <td valign="top">
                <div style="text-align:center;padding:60px 0;">
                  <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/memberpress-logo.png'); ?>" alt="MemberPress logo" width="350" />
                </div>
                <div style="padding:0 0 30px 0; font-family:Helvetica,Arial,sans-serif;line-height:1.5;">
                  <?php echo $drm_info['message']; ?>
                  <p><?php echo $drm_info['help_message']; ?></p>
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>