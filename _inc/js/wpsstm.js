(function($){

    $(document).ready(function(){
        
        /*
        about
        */
        $('#post-46 .entry-content').toggleChildren({
            childrenSelector:'> p'
        });

  });  


})(jQuery);

function wpsstm_get_current_user_id(){
    return parseInt(wpsstmL10n.logged_user_id);
}


