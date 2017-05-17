(function($){

    $(document).ready(function(){

        //LAST.FM : user is not logged
        $('.wpsstm-track-action-lastfm').click(function(e) {
            if ( !wpsstm_is_lastfm_api_logged() ) return;
            e.preventDefault();
            $('#wpsstm-bottom-notice-lastfm-auth').show();
        });
        
        //LAST.FM : love / unlove track
        $('.wpsstm-love-track,.wpsstm-unlove-track').click(function(e) {
            if ( !wpsstm_is_lastfm_api_logged() ) return;
            //if (lastm_auth_notice.length > 0) return;
            e.preventDefault();
            
            var link = $(this);
            var link_wrapper = link.closest('.wpsstm-love-unlove-links');
            var track_obj = wpsstm_page_tracks[wpsstm_current_track_idx];
            var track_el = $(track_obj.row);

            var track = {
                artist: track_obj.artist,
                title:  track_obj.title,
                album:  track_obj.album
            }
            
            var love = link.hasClass('wpsstm-love-track'); //is love or unlove ?

            var ajax_data = {
                action:           'wpsstm_love_unlove_track',
                love:             love,
                track:            track
            };
            
            console.log("love/unlove track:");
            console.log(ajax_data);

            return $.ajax({

                type: "post",
                url: wpsstmL10n.ajaxurl,
                data:ajax_data,
                dataType: 'json',
                beforeSend: function() {
                    link_wrapper.addClass('loading');
                },
                success: function(data){
                    console.log(data);
                    if (data.success === false) {
                    }else{
                        if (love){
                            link_wrapper.addClass('wpsstm-is-loved');
                        }else{
                            link_wrapper.removeClass('wpsstm-is-loved');
                        }
                        
                    }
                },
                complete: function() {
                    link_wrapper.removeClass('loading');
                }
            })
            
            
        });
        
        //LAST.FM : update track playing
        
        

    });
    
    function wpsstm_is_lastfm_api_logged(){
        return parseInt(wpsstmLastFM.is_api_logged);
    }

    
})(jQuery);
