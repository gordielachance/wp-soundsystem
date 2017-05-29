(function($){

    $(document).ready(function(){

        /*
        tracklists
        */

        $('.wpsstm-tracklist-table table').shortenTable(3,'tbody tr');

  });  


})(jQuery);

/*
Displays a box with a text the user can copy.
http://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript
*/
function wpsstm_clipboard_box(text) {
    window.prompt(wpsstmL10n.clipboardtext, text);
}
function wpsstm_get_current_user_id(){
    return parseInt(wpsstmL10n.logged_user_id);
}


