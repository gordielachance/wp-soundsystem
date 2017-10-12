class WpsstmLastFM {
    constructor(){
        var self = this;
        self.icon_scrobble_el; //player scrobble icon
        self.auth_notice_el;
        self.lastfm_scrobble_along =    parseInt(wpsstmLastFM.lastfm_scrobble_along);
        self.is_user_api_logged =       parseInt(wpsstmLastFM.is_user_api_logged);
        self.has_user_scrobbler =   (    ( localStorage.getItem("wpsstm-scrobble") == 'true' ) && (self.is_user_api_logged) ); //localStorage stores strings
        

        self.auth_notice_el =       null;
        
        if ( ( self.has_user_scrobbler === null ) && (self.is_user_api_logged) ){  //default
            self.has_user_scrobbler = true;
        }

    }
    
    init(){
        
        var self = this;
        
        self.icon_scrobble_el =     $(bottom_el).find('#wpsstm-player-toggle-scrobble')
        self.auth_notice_el =       $(bottom_wrapper_el).find('#wpsstm-bottom-notice-lastfm-auth');

        if (self.has_user_scrobbler){
            $(self.icon_scrobble_el).addClass('active');
        }

        //click toggle scrobbling
        $(self.icon_scrobble_el).find('a').click(function(e) {
            e.preventDefault();
            
            if ( !self.is_user_api_logged ){
                self.lastfm_auth_notice();
                return;
            }

            self.has_user_scrobbler = !self.has_user_scrobbler;
            $(self.icon_scrobble_el).toggleClass('active');

            localStorage.setItem("wpsstm-scrobble", self.has_user_scrobbler);

        });

    }
    
    lastfm_auth_notice(){
        var self = this;
        if ( wpsstm_get_current_user_id() && !self.is_user_api_logged ){
            wpsstm_bottom_notice('lastfm-auth',wpsstmLastFM.lastfm_auth_notice);
        }
    }
        
    /*
    last.fm API - track.updateNowPlaying
    */

    updateNowPlaying(track_obj){
        
        var self = this;

        var ajax_data = {
            action:           'wpsstm_user_update_now_playing_lastfm_track',
            post_id:          track_obj.post_id
        };

        self.debug("lastfm - ajax track.updateNowPlaying: #" + track_obj.post_id);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.icon_scrobble_el).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }
            },
            complete: function() {
                $(self.icon_scrobble_el).removeClass('loading');
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
            post_id:            track_obj.post_id,
            playback_start:     track_obj.playback_start
        };

        self.debug("lastfm - ajax user scrobble: #" + track_obj.post_id + ", start_timestamp: " + track_obj.playback_start);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.icon_scrobble_el).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) { 
                console.log("status: " + textStatus + ", error: " + errorThrown); 
            },
            complete: function() {
                $(self.icon_scrobble_el).removeClass('loading');
            }
        })
    }
    
    community_scrobble(track_obj){
        
        var self = this;

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_community_track',
            post_id:            track_obj.post_id,
            playback_start:     track_obj.playback_start
        };

        self.debug("lastfm - ajax community scrobble: #" + track_obj.post_id + ", start_timestamp: " + track_obj.playback_start);

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
            error: function(XMLHttpRequest, textStatus, errorThrown) { 
                console.log("status: " + textStatus + ", error: " + errorThrown); 
            }
        })
    }

    /*
    last.fm API - track.love
    */

    love_unlove(track_obj,do_love){
        
        var self = this;

        var ajax_data = {
            action:     'wpsstm_lastfm_user_toggle_love_track',
            do_love:    do_love,
            post_id:    track_obj.post_id
        };

        self.debug("lastFM - love/unlove track: #" + track_obj.post_id);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.icon_scrobble_el).addClass('loading');
            },
            success: function(data){ 
                if (data.success === true) {
                }else{
                    console.log(data);
                }
            },
            complete: function() {
                $(self.icon_scrobble_el).removeClass('loading');
            }
        })
    }
        
    debug(msg){
        var prefix = "WpsstmLastFM: ";
        wpsstm_debug(msg,prefix);
    }
    
}

(function($){
    
    $(document).on( "wpsstmPageDomReady", function( event ) {
        wpsstm_lastfm.init();
    });
    
    $(document).on( "wpsstmMediaReady", function( event, media, track ) {

        $(media).on('loadeddata', function() {
            if (wpsstm_lastfm.has_user_scrobbler){
                wpsstm_lastfm.updateNowPlaying(track);
            }
        });
        
        $(media).on('ended', function() {
            if ( media.duration > 30) { //scrobble
                if (wpsstm_lastfm.has_user_scrobbler){
                    wpsstm_lastfm.user_scrobble(track);
                }
                //bot scrobble
                if (wpsstm_lastfm.lastfm_scrobble_along){
                    wpsstm_lastfm.community_scrobble(track);
                }
            }
        });
        
    });


    $(document).on( "wpsstmTrackLove", function( event,track_obj,do_love ) {
        wpsstm_lastfm.love_unlove(track_obj,do_love);
    });
    
})(jQuery);

var wpsstm_lastfm = new WpsstmLastFM();








