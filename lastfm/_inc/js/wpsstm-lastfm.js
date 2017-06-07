class WpsstmLastFM {
    constructor(){
        var self = this;
        self.icon_scrobble_el; //player scrobble icon
        self.icon_love_el;
        self.auth_notice_el;
        self.ping_timer;
        self.ping;
        self.has_lastfm_bot =       parseInt(wpsstmLastFM.has_lastfm_bot);
        self.is_user_api_logged =   parseInt(wpsstmLastFM.is_user_api_logged);
        self.has_user_scrobbler =   ( ( localStorage.getItem("wpsstm-scrobble") == 'true' ) && (self.is_user_api_logged) ); //localStorage stores strings
        

        self.auth_notice_el =       null;
        
        if ( ( self.has_user_scrobbler === null ) && (self.is_user_api_logged) ){  //default
            alert(self.is_user_api_logged);
            self.has_user_scrobbler = true;
        }

    }
    
    init(){
        
        var self = this;
        
        self.icon_scrobble_el =     $(bottom_el).find('#wpsstm-player-toggle-scrobble')
        self.icon_love_el =         $(bottom_el).find('.wpsstm-lastfm-love-unlove-track-links');
        self.auth_notice_el =       $(bottom_wrapper_el).find('#wpsstm-bottom-notice-lastfm-auth');

        if (self.has_user_scrobbler){
            $(self.icon_scrobble_el).addClass('active');
        }

        //click toggle scrobbling
        $(self.icon_scrobble_el).find('a').click(function(e) {
            e.preventDefault();
            
            if ( !self.is_user_api_logged ){
                self.displayAuthNotices();
                return;
            }

            self.has_user_scrobbler = !self.has_user_scrobbler;
            $(self.icon_scrobble_el).toggleClass('active');

            localStorage.setItem("wpsstm-scrobble", self.has_user_scrobbler);

        });
        
        //click toggle love track
        $(self.icon_love_el).find('a').click(function(e) {
            e.preventDefault();
            
            if ( !self.is_user_api_logged ){
                self.displayAuthNotices();
                return;
            }
            
            var link = $(this);
            var link_wrapper = link.closest('.wpsstm-love-unlove-track-links');
            var do_love = !link_wrapper.hasClass('wpsstm-is-loved');
            
            var tracklist_el = link.closest('[data-wpsstm-tracklist-idx]');
            var tracklist_idx = tracklist_el.attr('data-wpsstm-tracklist-idx');
            
            var track_el = link.closest('[itemprop="track"]');
            var track_idx = track_el.attr('data-wpsstm-track-idx');
            
            var track_obj = wpsstm_page_player.get_tracklist_track_obj(tracklist_idx,track_idx);
            self.love_unlove(track_obj,do_love);
        });

    }
    
    displayAuthNotices(){
        var self = this;
        
        if ( !wpsstm_get_current_user_id() ){
            $('#wpsstm-bottom-notice-wp-auth').addClass('active');
            return;
        }
        if ( !self.is_user_api_logged ){
            $(self.auth_notice_el).addClass('active');
            return;
        }
    }
        
    /*
    last.fm API - track.updateNowPlaying
    */

    updateNowPlaying(track_obj){
        
        var self = this;

        var track = {
            artist: track_obj.artist,
            title:  track_obj.title,
            album:  track_obj.album
        }

        var ajax_data = {
            action:           'wpsstm_user_update_now_playing_lastfm_track',
            track:            track
        };

        self.debug("lastfm - ajax track.updateNowPlaying");

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

        var track = {
            artist:     track_obj.artist,
            title:      track_obj.title,
            album:      track_obj.album,
            duration:   track_obj.duration
        }

        var ajax_data = {
            action:             'wpsstm_user_scrobble_lastfm_track',
            track:              track,
            playback_start:     track_obj.playback_start
        };

        self.debug("lastfm - ajax track.scrobble");

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
    
    bot_scrobble(track_obj){
        
        var self = this;

        var track = {
            artist:     track_obj.artist,
            title:      track_obj.title,
            album:      track_obj.album,
            duration:   track_obj.duration
        }

        var ajax_data = {
            action:             'wpsstm_bot_scrobble_lastfm_track',
            track:              track,
            playback_start:     track_obj.playback_start
        };

        self.debug("lastfm - ajax bot track.scrobble");

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }
            }
        })
    }

    /*
    last.fm API - track.love
    */

    love_unlove(track_obj,do_love){
        
        var self = this;

        var track = {
            artist: track_obj.artist,
            title:  track_obj.title,
            album:  track_obj.album
        }

        var ajax_data = {
            action:     'wpsstm_user_love_unlove_lastfm_track',
            do_love:    do_love,
            track:      track
        };

        self.debug("lastFM - love/unlove track");

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
                    if (do_love){
                        $(self.icon_love_el).addClass('wpsstm-is-loved');
                    }else{
                        $(self.icon_love_el).removeClass('wpsstm-is-loved');
                    }
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
    
    /*
    after 10s: updateNowPlaying()
    after 40s: scrobble
    */
    
    lastFmTrackEvents(){
        var self = this;

        self.debug("lastFmTrackEvents()");

        self.ping_timer = setInterval ( function(){
            
            if(wpsstm_mediaElement.paused) return;
            self.ping++;

            self.debug("track ping: " + self.ping);

            if (self.ping == 1) { //5s
                if (self.has_user_scrobbler){
                    self.updateNowPlaying(wpsstm_currentTrack);
                }
            }

            //stop timer & scrobble
            if (self.ping >= 7) { //35s
                clearInterval(self.ping_timer);
                
                if (self.ping == 7){ //35s
                    if ( wpsstm_mediaElement.duration > 30) { //scrobble
                        if (wpsstm_lastfm.has_user_scrobbler){
                            wpsstm_lastfm.user_scrobble(wpsstm_currentTrack);
                        }
                        //bot scrobble
                        if (wpsstm_lastfm.has_lastfm_bot){
                            wpsstm_lastfm.bot_scrobble(wpsstm_currentTrack);
                        }
                    }
                }
                
            }

            
            
        }, 5000 ); // one ping = 5s
    }
    
}

(function($){
    
    $( document ).on( "wpsstmDomReady", function( event ) {
        wpsstm_lastfm.init();
    });
    
    $( document ).on( "wpsstmMediaReady", function( event ) {

        wpsstm_mediaElement.addEventListener('loadeddata', function() {
            //reinit for each track
            wpsstm_lastfm.ping_timer = null;
            wpsstm_lastfm.ping = 0; 
        });
        
        wpsstm_mediaElement.addEventListener('play', function() {
            if (wpsstm_lastfm.ping_timer === null){
                 wpsstm_lastfm.lastFmTrackEvents();
            }
        });
        
    });


    $(document).on( "wpsstmTrackLove", function( event,track_obj,do_love ) {
        wpsstm_lastfm.love_unlove(track_obj,do_love);
    });
    
})(jQuery);

var wpsstm_lastfm = new WpsstmLastFM();








