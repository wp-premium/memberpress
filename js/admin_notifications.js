jQuery(document).ready(function($) {

  var viewDismissed = $('#viewDismissed');
  var viewActive = $('#viewActive');
  var dismissedMessages = $('.dismissed-messages');
  var activeMessages = $('.active-messages');

  viewDismissed.on('click', function(event) {
    event.preventDefault();
    dismissedMessages.show();
    activeMessages.hide();
    viewActive.show();
    viewDismissed.hide();
  });
  viewActive.on('click', function(event) {
    event.preventDefault();
    dismissedMessages.hide();
    activeMessages.show();
    viewActive.hide();
    viewDismissed.show();
  });

  $('body').on('click', '.mepr-notice-dismiss', function(event) {

    event.preventDefault();

    var $this = $(this);
    var messageId = $this.data('message-id');
    var message = $('#mepr-notifications-message-' + messageId);
    var countEl = $('#meprNotificationsCount');
    var count = parseInt(countEl.html());
    var mainCountEl = $('#meprAdminHeaderNotificationsCount');
    var adminMenuCount = $('#meprAdminMenuUnreadCount');

    var data = {
      action: 'mepr_notification_dismiss',
      nonce: MeprAdminNotifications.nonce,
      id: messageId,
    };

    $this.prop('disabled', 'disabled');
    message.fadeOut();

    $.post( MeprAdminNotifications.ajax_url, data, function( res ) {

      if ( ! res.success ) {
        MemberPressAdmin.debug( res );
      } else {
        message.prependTo(dismissedMessages);
        message.show();
        count--;

        if ( count < 0 ) {
          count = 0;
          countEl.hide();
          mainCountEl.hide();
          adminMenuCount.closest('.awaiting-mod').remove();
        } else if ( 0 == count ) {
          countEl.hide();
          mainCountEl.hide();
          $('.mepr-notifications-none').show();
          $('.dismiss-all').hide();
          adminMenuCount.closest('.awaiting-mod').remove();
        } else if ( count < 10 ) {
          countEl.addClass('single-digit');
          countEl.html(count);
          mainCountEl.html(count);
          adminMenuCount.html(count);
        } else {
          countEl.html(count);
          mainCountEl.html(count);
          adminMenuCount.html(count);
        }
      }

    } ).fail( function( xhr, textStatus, e ) {

      MemberPressAdmin.debug( xhr.responseText );
      message.show('Message could not be dismissed.');
    } );
  });

  $('body').on('click', '.dismiss-all' ,function(event) {

    event.preventDefault();

    var $this = $(this);
    var countEl = $('#meprNotificationsCount');
    var count = parseInt(countEl.html());
    var mainCountEl = $('#meprAdminHeaderNotificationsCount');
    var adminMenuCount = $('#meprAdminMenuUnreadCount');

    var data = {
      action: 'mepr_notification_dismiss',
      nonce: MeprAdminNotifications.nonce,
      id: 'all',
    };

    $this.prop('disabled', 'disabled');

    $.post( MeprAdminNotifications.ajax_url, data, function( res ) {

      if ( ! res.success ) {
        MemberPressAdmin.debug( res );
      } else {
        countEl.hide();
        mainCountEl.hide();
        adminMenuCount.closest('.awaiting-mod').remove();
        $('.mepr-notifications-none').show();
        $('.dismiss-all').hide();

        $.each($('.active-messages .mepr-notifications-message'), function(i, el) {
          $(el).appendTo(dismissedMessages);
        });
      }

    } ).fail( function( xhr, textStatus, e ) {

      MemberPressAdmin.debug( xhr.responseText );
      message.show('Messages could not be dismissed.');
    } );
  });
});