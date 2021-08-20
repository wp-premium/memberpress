jQuery(document).ready(function($) {
  function mepr_process_table_search() {
    var loc = window.location.href;

    loc = loc.replace(/[&\?]search=[^&]*/gi, '');
    loc = loc.replace(/[&\?]search-field=[^&]*/gi, '');
    loc = loc.replace(/[&\?]paged=[^&]*/gi, ''); // Show first page when search button is clicked

    var search = encodeURIComponent($('#cspf-table-search').val());
    var search_field = $('#cspf-table-search-field').val();

    loc = loc + '&search=' + search + '&search-field=' + search_field;

    // Clean up
    if(!/\?/.test(loc) && /&/.test(loc)) {
      loc = loc.replace(/&/,'?'); // not global, just the first
    }

    window.location = loc;
  }

  $("#cspf-table-search").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      mepr_process_table_search();
    }
  });

  $("#cspf-table-search-submit").on('click', function(e) {
    e.preventDefault();
    mepr_process_table_search();
  });

  $(".current-page").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      var loc = window.location.href;
      loc = loc.replace(/&paged=[^&]*/gi, '');

      if($(this).val() != '')
        window.location = loc + '&paged=' + escape($(this).val());
      else
        window.location = loc;
    }
  });

  $("#cspf-table-perpage").change(function(e) {
    var loc = window.location.href;
    loc = loc.replace(/&perpage=[^&]*/gi, '');

    if($(this).val() != '')
      window.location = loc + '&perpage=' + $(this).val();
    else
      window.location = loc;
  });

  $("#mepr_search_filter").click( function() {
    var loc = window.location.href;

    $('.mepr_filter_field').each( function() {
      var arg = $(this).attr('id');
      console.log(arg);
      var re = new RegExp("[&\?]" + arg + "=[^&]*","gi");
      console.log(re);
      loc = loc.replace(re, '');
      loc = loc + '&' + arg + "=" + $(this).val();
    } );

    // Clean up
    if(!/\?/.test(loc) && /&/.test(loc)) {
      loc = loc.replace(/&/,'?'); // not global, just the first
    }

    window.location = loc;
  } );

});
