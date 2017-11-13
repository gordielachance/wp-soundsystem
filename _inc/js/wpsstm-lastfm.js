class WpsstmLastFM {
    constructor(){
        this.scrobble_icon =            undefined;
        this.auth_notice_el =           undefined;
        this.lastfm_scrobble_along =    parseInt(wpsstmLastFM.lastfm_scrobble_along);
        this.is_user_api_logged =       parseInt(wpsstmLastFM.is_user_api_logged);
        this.has_user_scrobbler =   (    ( localStorage.getItem("wpsstm-scrobble") == 'true' ) && (this.is_user_api_logged) ); //localStorage stores strings
        

        this.auth_notice_el =       null;
        
        if ( ( this.has_user_scrobbler === null ) && (this.is_user_api_logged) ){  //default
            this.has_user_scrobbler = true;
        }
    }
    
    init(){
        
        var self = this;
        
        self.scrobble_icon =     $('#wpsstm-player-action-scrobbler');
        self.auth_notice_el =       wpsstm.bottom_wrapper_el.find('#wpsstm-bottom-notice-lastfm-auth');

        if (self.has_user_scrobbler){
            $(self.scrobble_icon).addClass('wpsstm-enabled');
        }

        //click toggle scrobbling
        $('#wpsstm-player-action-scrobbler').find('a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ){
                wpsstm_wp_auth_notice();
            }else if( !self.is_user_api_logged ){
                wpsstm_bottom_notice('lastfm-auth',wpsstmLastFM.lastfm_auth_notice);
            }

            if ( !self.is_user_api_logged ){
                self.has_user_scrobbler = false;
                self.lastfm_auth_notice();
            }else{            
                self.has_user_scrobbler = !self.has_user_scrobbler;
            }

            $(self.scrobble_icon).toggleClass('wpsstm-enabled',self.has_user_scrobbler);

            localStorage.setItem("wpsstm-scrobble", self.has_user_scrobbler);

        });

    }
 
    /*
    last.fm API - track.updateNowPlaying
    */

    updateNowPlaying(track_obj){
        
        var self = this;

        var ajax_data = {
            action:             'wpsstm_user_update_now_playing_lastfm_track',
            track:              track_obj.to_ajax(),
            playback_start:     track_obj.playback_start
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.scrobble_icon).addClass('wpsstm-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }
            },
            complete: function() {
                $(self.scrobble_icon).removeClass('wpsstm-loading');
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
            playback_start:     track_obj.playback_start
        };

        self.debug(ajax_data);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self.scrobble_icon).addClass('wpsstm-loading');
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
                $(self.scrobble_icon).removeClass('wpsstm-loading');
            }
        })
    }
    
    community_scrobble(track_obj){
        
        var self = this;

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_community_track',
            track:              track_obj.to_ajax(),
            playback_start:     track_obj.playback_start
        };

        self.debug(ajax_data);

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
                $(self.scrobble_icon).addClass('wpsstm-loading');
            },
            success: function(data){ 
                if (data.success === true) {
                }else{
                    console.log(data);
                }
            },
            complete: function() {
                $(self.scrobble_icon).removeClass('wpsstm-loading');
            }
        })
    }
        
    debug(msg){
        var prefix = "WpsstmLastFM: ";
        wpsstm_debug(msg,prefix);
    }
    
}

(function($){
    
    $(document).on( "PageTracklistsInit", function( event ) {
        wpsstm_lastfm.init();
    });
    
    $(document).on( "wpsstmSourceMediaLoaded", function( event, media, track ) {

        $(media).on('play', function() {
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








