jQuery(document).ready(function($) {
  // Login form shortcode
  if($('#_mepr_manual_login_form').is(":checked")) {
    $('div#mepr-shortcode-login-page-area').show();
  } else {
    $('div#mepr-shortcode-login-page-area').hide();
  }

  $('#_mepr_manual_login_form').on('click' , function() {
    $('div#mepr-shortcode-login-page-area').slideToggle();
  });

  // Unauthorized stuff
  var unauth_tgl_ids = {
    excerpt: {
      src: '_mepr_unauth_excerpt_type',
      target: '_mepr_unauth_excerpt_type-size'
    },
    message: {
      src: '_mepr_unauthorized_message_type',
      target: '_mepr_unauthorized_message_type-editor'
    }
  };

  var unauth_tgl = function(src,target) {
    if($('#'+src).val()=='custom')
      $('#'+target).slideDown();
    else
      $('#'+target).slideUp();
  };

  unauth_tgl(unauth_tgl_ids.excerpt.src,unauth_tgl_ids.excerpt.target);
  $('#'+unauth_tgl_ids.excerpt.src).on('change', function() {
    unauth_tgl(unauth_tgl_ids.excerpt.src,unauth_tgl_ids.excerpt.target);
  });

  unauth_tgl(unauth_tgl_ids.message.src,unauth_tgl_ids.message.target);
  $('#'+unauth_tgl_ids.message.src).on('change', function() {
    unauth_tgl(unauth_tgl_ids.message.src,unauth_tgl_ids.message.target);
  });

  $('table.wp-list-table tr').on('mouseenter',
    function(e) {
      $(this).find('.mepr-row-actions').css('visibility','visible');
    }
  );
  $('table.wp-list-table tr').on('mouseleave',
    function(e) {
      $(this).find('.mepr-row-actions').css('visibility','hidden');
    }
  );

  $( '.mepr-auto-trim' ).on('blur', function(e) {
    var value = $(this).val();
    $(this).val( value.trim() );
  });

  $('.mepr-slide-toggle').on('click', function(e) {
    e.preventDefault();
    $($(this).attr('data-target')).slideToggle();
  });

  //Change mouse pointer over li items
  $('body').on('mouseenter', '.mepr-sortable li', function() {
    $(this).addClass('mepr-hover');
  });
  $('body').on('mouseleave', '.mepr-sortable li', function() {
    $(this).removeClass('mepr-hover');
  });

  $('.mepr-admin-notice.mepr-auto-open').each( function() {
    var _this = this;

    $.magnificPopup.open({
      items: {
        src: _this,
        type: 'inline'
      }
    });
  });

  var mepr_stop_addon_notices = function(addon, cb) {
    var args = {
      action: 'mepr_addon_stop_notice',
      addon: addon,
    };

    $.post(ajaxurl, args, cb, 'json');
  };

  $('.mepr-addon-stop-notices').on('click', function() {
    var _this = this;
    mepr_stop_addon_notices($(this).parent().data('addon'), function(response) {
      if(typeof response.error === 'undefined') {
        $.magnificPopup.close();
      }
      else {
        alert(response.error);
        $.magnificPopup.close();
      }
    });
  });

  $('.mepr-addon-activate, .mepr-addon-install').on('click', function() {
    var _this = this;
    mepr_stop_addon_notices($(this).parent().data('addon'), function(response) {
      if(typeof response.error === 'undefined') {
        location.href = $(_this).data('href');
      }
      else {
        alert(response.error);
        $.magnificPopup.close();
      }
    });
  });

  $('.mepr-confirm').on('click', function(e) {
    var confirm_message = $(this).data('message');

    if(!confirm(confirm_message)) {
      e.preventDefault();
    }
  });

  $('body').on('click', '#mepr_stripe_connect_upgrade_notice button.notice-dismiss', function(e){
    Cookies.set('mepr_stripe_connect_upgrade_dismissed', '1', { expires: 1, path: '/' });
  });

  $('body').on('click', '#mepr_paypal_connect_upgrade_notice button.notice-dismiss', function(e){
    Cookies.set('mepr_paypal_connect_upgrade_dismissed', '1', { expires: 1, path: '/' });
  });

  $('body').on('click', '.mepr-notice-dismiss-permanently button.notice-dismiss', function () {
    $.ajax({
      url: MeprAdminShared.ajax_url,
      method: 'POST',
      data: {
        action: 'mepr_dismiss_notice',
        _ajax_nonce: MeprAdminShared.dismiss_notice_nonce,
        notice: $(this).closest('.notice').data('notice')
      }
    })
  });

  $('#mepAdminHeaderNotifications').on('click', function(e) {
    e.preventDefault();
    $('#mepr-notifications').toggleClass('visible');
  });
  $('#meprNotificationsClose').on('click', function(e) {
    e.preventDefault();
    $('#mepr-notifications').removeClass('visible');
  });

  $('body').on('click', '.mepr-notice-dismiss-24hour button.notice-dismiss', function (e) {

    var notice = $(this).closest('.notice');

    $.ajax({
      url: MeprAdminShared.ajax_url,
      method: 'POST',
      data: {
        action: 'mepr_dismiss_notice_drm',
        _ajax_nonce: MeprAdminShared.dismiss_notice_nonce,
        notice: notice.data('notice'),
        secret: notice.data('secret')
      }
    })
  });
});
