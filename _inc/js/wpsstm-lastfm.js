class WpsstmLastFM {
    constructor(){
        this.scrobble_icon =            undefined;
        this.lastfm_scrobble_along =    parseInt(wpsstmLastFM.lastfm_scrobble_along);
        this.scrobbler_enabled =        ( localStorage.getItem("wpsstm-scrobble") == 'true' ); //localStorage stores strings
    }

    enable_scrobbler(do_enable,show_notice){
        
        var self = this;

        if (do_enable){
            
            var ajax_data = {
                action: 'wpsstm_lastfm_enable_scrobbler',
            };

            return $.ajax({

                type: "post",
                url: wpsstmL10n.ajaxurl,
                data:ajax_data,
                dataType: 'json',
                beforeSend: function() {
                    $(self.scrobble_icon).addClass('lastfm-loading');
                },
                success: function(data){
                    if (data.success === false) {
                        do_enable = false;
                        $(self.scrobble_icon).addClass('scrobbler-error');
                        if (data.notice && show_notice){
                            wpsstm_dialog_notice(data.notice);
                        }
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(thrownError);
                    do_enable = false;
                    $(self.scrobble_icon).addClass('scrobbler-error');
                },
                complete: function() {
                    $(self.scrobble_icon).removeClass('lastfm-loading');
                    self.scrobbler_enabled = do_enable;
                    localStorage.setItem("wpsstm-scrobble", do_enable);
                    $(self.scrobble_icon).toggleClass('active',do_enable);
                }
            })
        }else{
            self.scrobbler_enabled = false;
            $(self.scrobble_icon).removeClass('active');
            localStorage.setItem("wpsstm-scrobble", false);
        }
    }
 
    /*
    last.fm API - track.updateNowPlaying
    */

    updateNowPlaying(track_obj){
        
        var self = this;

        var ajax_data = {
            action:             'wpsstm_user_update_now_playing_lastfm_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.scrobble_icon).addClass('lastfm-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    $(self.scrobble_icon).addClass('scrobbler-error');
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                $(self.scrobble_icon).addClass('scrobbler-error');
            },
            complete: function() {
                $(self.scrobble_icon).removeClass('lastfm-loading');
            }
        })
    }

    /*
    last.fm API - track.scrobble
    */

    user_scrobble(track_obj){

        var self = this;

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_user_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.scrobble_icon).addClass('lastfm-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    $(self.scrobble_icon).addClass('scrobbler-error');
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                $(self.scrobble_icon).addClass('scrobbler-error');
            },
            complete: function() {
                $(self.scrobble_icon).removeClass('lastfm-loading');
            }
        })
    }
    
    community_scrobble(track_obj){
        
        var self = this;

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_community_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        //self.debug(ajax_data);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
        })
    }

    /*
    last.fm API - track.love
    */

    love_unlove(track_obj,do_love){
        
        var self = this;

        var ajax_data = {
            action:     'wpsstm_lastfm_user_toggle_love_track',
            track:      track_obj.to_ajax(),
            do_love:    do_love
        };

        self.debug(ajax_data);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.scrobble_icon).addClass('lastfm-loading');
            },
            success: function(data){ 
                if (data.success === true) {
                }else{
                    console.log(data);
                    $(self.scrobble_icon).addClass('scrobbler-error');
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                $(self.scrobble_icon).addClass('scrobbler-error');
            },
            complete: function() {
                $(self.scrobble_icon).removeClass('lastfm-loading');
            }
        })
    }
        
    debug(msg){
        var prefix = "WpsstmLastFM";
        wpsstm_debug(msg,prefix);
    }
    
}

(function($){

    $(document).on( "wpsstmPlayerInit", function( event, player_obj ) {
        
        wpsstm_lastfm.scrobble_icon =     player_obj.player_el.find('.wpsstm-player-action-scrobbler');

        //enable scrobbler at init
        if (wpsstm_lastfm.scrobbler_enabled){
            wpsstm_lastfm.enable_scrobbler(true);
        }

        //click toggle scrobbling
        wpsstm_lastfm.scrobble_icon.find('a').click(function(e) {
            e.preventDefault();
            wpsstm_lastfm.enable_scrobbler(!wpsstm_lastfm.scrobbler_enabled,true);
        });
    });
    
    $(document).on( "wpsstmSourceInit", function( event, player_obj ) {
        
        var nowPlayingTrack = function(){
            var source_obj = player_obj.current_source;
            var track_obj = source_obj.track;
            if (!wpsstm_lastfm.scrobbler_enabled) return;
            
            wpsstm_lastfm.updateNowPlaying(track_obj);
            $(player_obj.current_media).off('play', nowPlayingTrack); //run it only once
        }
        
        var ScrobbleTrack = function() {
            var source_obj = player_obj.current_source;
            var track_obj = source_obj.track;
            if ( source_obj.duration < 30) return;
            
            if (wpsstm_lastfm.scrobbler_enabled){
                wpsstm_lastfm.user_scrobble(track_obj);
            }
            //bot scrobble
            if (wpsstm_lastfm.lastfm_scrobble_along){
                wpsstm_lastfm.community_scrobble(track_obj);
            }
            
            $(player_obj.current_media).off('ended', ScrobbleTrack); //run it only once
        }

        //now playing
        $(player_obj.current_media).on('play', nowPlayingTrack);
        
        //track end
        $(player_obj.current_media).on('ended', ScrobbleTrack);
        
    });

    $(document).on( "wpsstmTrackLove", function( event,track_obj,do_love ) {
        wpsstm_lastfm.love_unlove(track_obj,do_love);
    });
    
})(jQuery);

var wpsstm_lastfm = new WpsstmLastFM();