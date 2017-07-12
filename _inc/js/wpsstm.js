(function($){

    $(document).ready(function(){
        //user is not logged for action
        $(document).on( "click",'.wpsstm-requires-auth', function(e) {
            if ( !wpsstm_get_current_user_id() ){
                e.preventDefault();
                wpsstm_wp_auth_notice();
            }
        });

    });  


})(jQuery);

function wpsstm_get_current_user_id(){
    return parseInt(wpsstmL10n.logged_user_id);
}

function wpsstm_bottom_notice(slug,msg,can_close = true){
    var notice_id =  'wpsstm-bottom-notice-' + slug;
        
    var existing_notice_el = $('#' + notice_id);
    if (existing_notice_el.length > 0) return;
   
    var notice = $('<div class="wpsstm-notice wpsstm-bottom-notice"></div>');
    var notice_msg = $('<p></p>');
    notice_msg.html(msg);
    notice.attr({
        id:     notice_id
    })

    if(can_close){
        var close_bt = $('<i class="wpsstm-close-notice fa fa-times" aria-hidden="true"></i>');
        notice.append(close_bt);
        
        close_bt.click(function(event){
            event.preventDefault();
            notice.remove();
        });
        
    }
    
    notice.append(notice_msg);
    $( "#wpsstm-bottom-wrapper" ).prepend( notice );
}

function wpsstm_wp_auth_notice(){
    var self = this;
    wpsstm_bottom_notice('wp-auth',wpsstmL10n.wp_auth_notice);
}