class WpsstmLastFM {
    constructor(){
        var self = this;
        self.icon_scrobble_el; //player scrobble icon
        self.icon_love_el;
        self.auth_notice_el;
        self.is_scrobbler_active =   ( localStorage.getItem("wpsstm-scrobble") == 'true' ); //localStorage stores strings
        self.is_api_logged =         parseInt(wpsstmLastFM.is_api_logged);
        self.auth_notice_el =        null;
        
        if ( self.is_scrobbler_active === null ){  //default
            self.is_scrobbler_active = true;
        }

    }
    
    init(){
        
        var self = this;
        
        self.icon_scrobble_el =     $(bottom_player_el).find('#wpsstm-player-toggle-scrobble')
        self.icon_love_el =         $(bottom_player_el).find('.wpsstm-lastfm-love-unlove-track-links');
        self.auth_notice_el =       $(bottom_block_el).find('#wpsstm-bottom-notice-lastfm-auth');

        if (self.is_scrobbler_active){
            $(self.icon_scrobble_el).addClass('active');
        }

        //click toggle scrobbling
        $(self.icon_scrobble_el).find('a').click(function(e) {
            e.preventDefault();
            
            if ( !self.is_api_logged ){
                self.displayAuthNotices();
                return;
            }

            self.is_scrobbler_active = !self.is_scrobbler_active;
            $(self.icon_scrobble_el).toggleClass('active');

            localStorage.setItem("wpsstm-scrobble", self.is_scrobbler_active);

        });
        
        //click toggle love track
        $(self.icon_love_el).find('a').click(function(e) {
            e.preventDefault();
            
            if ( !self.is_api_logged ){
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
        if ( !wpsstm_get_current_user_id() ){
            $('#wpsstm-bottom-notice-wp-auth').addClass('active');
            return;
        }
        if ( !self.is_api_logged ){
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
            action:           'wpsstm_lastfm_update_now_playing_track',
            track:            track
        };

        console.log("lastfm - ajax track.updateNowPlaying");

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

    scrobble(track_obj,media){
        
        var self = this;

        if ( media.duration <= 30) return;

        var track = {
            artist:     track_obj.artist,
            title:      track_obj.title,
            album:      track_obj.album,
            duration:   track_obj.duration
        }

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_track',
            track:              track,
            playback_start:     track_obj.playback_start
        };

        console.log("lastfm - ajax track.scrobble");

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
            action:     'wpsstm_lastfm_love_unlove_track',
            do_love:    do_love,
            track:      track
        };

        console.log("lastFM - love/unlove track");

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
        
}

(function($){
    
    $( document ).on( "wpsstmDomReady", function( event ) {
        wpsstm_lastfm.init();
    });

    $( document ).on( "wpsstmPlayerMediaEvent", function( event,mediaEvent,media,node,player,track_obj ) {

        switch(mediaEvent) {
            case 'loadeddata':
                if (wpsstm_lastfm.is_scrobbler_active){
                    wpsstm_lastfm.updateNowPlaying(track_obj);
                }
            break;
            case 'ended':
                if (wpsstm_lastfm.is_scrobbler_active){
                    wpsstm_lastfm.scrobble(track_obj,media);
                }
            break;
        }
    });

    $(document).on( "wpsstmTrackLove", function( event,track_obj,do_love ) {
        wpsstm_lastfm.love_unlove(track_obj,do_love);
    });
    
})(jQuery);

var wpsstm_lastfm = new WpsstmLastFM();








