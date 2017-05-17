(function($){

    $(document).ready(function(){
        
        //LAST.FM : toggle scrobbling
        $('#wpsstm-player-toggle-scrobble a').click(function(e) {
            e.preventDefault();
            
            var link = $(this);
            var link_wrapper = $('#wpsstm-player-toggle-scrobble');
            var do_scrobble = !link_wrapper.hasClass('active');

            var ajax_data = {
                action:             'wpsstm_toggle_scrobbling',
                do_scrobble:        do_scrobble,
            };
            
            console.log("toggle_scrobbling:" + do_scrobble);

            return $.ajax({

                type: "post",
                url: wpsstmL10n.ajaxurl,
                data:ajax_data,
                dataType: 'json',
                beforeSend: function() {
                    link_wrapper.addClass('loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        if (do_scrobble){
                            link_wrapper.addClass('active');
                        }else{
                            link_wrapper.removeClass('active');
                        }
                        
                    }
                },
                complete: function() {
                    link_wrapper.removeClass('loading');
                }
            })
            
            
        });

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
                    if (data.success === false) {
                        console.log(data);
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
    
    $( document ).on( "wpsstmPlayerMediaEvent", function( event,mediaEvent,media,node,player,track_obj ) {

        switch(mediaEvent) {
            case 'loadeddataAA':
                
                /*
                last.fm API - track.updateNowPlaying
                */

                var track = {
                    artist: track_obj.artist,
                    title:  track_obj.title,
                    album:  track_obj.album
                }

                var ajax_data = {
                    action:           'wpsstm_lastfm_update_now_playing_track',
                    track:            track
                };

                console.log("lastfm - ajax track.updateNowPlaying:");
                console.log(ajax_data);

                return $.ajax({

                    type: "post",
                    url: wpsstmL10n.ajaxurl,
                    data:ajax_data,
                    dataType: 'json',
                    beforeSend: function() {
                    },
                    success: function(data){
                        if (data.success === false) {
                            console.log(data);
                        }
                    },
                    complete: function() {
                    }
                })
                
                
            break;
            case 'ended':
                
                /*
                last.fm API - track.scrobble
                */
                
                if ( media.duration > 30) {
                    var track = {
                        artist: track_obj.artist,
                        title:  track_obj.title,
                        album:  track_obj.album
                    }

                    var ajax_data = {
                        action:           'wpsstm_lastfm_scrobble_track',
                        track:            track
                    };

                    console.log("lastfm - ajax track.scrobble:");
                    console.log(ajax_data);
                    
                    return $.ajax({

                        type: "post",
                        url: wpsstmL10n.ajaxurl,
                        data:ajax_data,
                        dataType: 'json',
                        beforeSend: function() {
                        },
                        success: function(data){
                            if (data.success === false) {
                                console.log(data);
                            }
                        },
                        complete: function() {
                        }
                    })
                
                }
                

            break;
        }
    });
    
    function wpsstm_is_lastfm_api_logged(){
        return parseInt(wpsstmLastFM.is_api_logged);
    }


    
})(jQuery);
