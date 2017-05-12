(function($){

  $(document).ready(function(){

      /*
      tracklists
      */

      $('.wpsstm-tracklist-table table').shortenTable(3,'tbody tr');
      
      $('a.wpsstm-tracklist-action-share').click(function(e) {
          e.preventDefault();
          var text = $(this).attr('href');
          wpsstm_clipboard_box(text);
      });
      
  });  
})(jQuery);

/*
Displays a box with a text the user can copy.
http://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript
*/
function wpsstm_clipboard_box(text) {
    window.prompt(wpsstmL10n.clipboardtext, text);
}
