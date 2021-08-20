<?php if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); } ?>
<html>
  <body>
    <p><?php echo sprintf(_x("Hi %s,", 'ui', 'memberpress'), $locals['first_name']); ?></p>
    <p><?php echo sprintf(_x("You can create a new password for %1\$s on %2\$s at %3\$s by clicking on the following link:", 'ui', 'memberpress'), $locals['user_login'], $locals['mepr_blogname'], $locals['mepr_blogurl']); ?></p>
    <p><a href="<?php echo $locals['reset_password_link']; ?>"><?php echo $locals['reset_password_link']; ?></a></p>
  </body>
</html>
