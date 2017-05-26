class WpsstmLastFM {
    constructor(){
        var self = this;
        self.icon_scrobble_el; //player scrobble icon
        self.icon_love_el;
        self.auth_notice_el;
        self.is_scrobbler_active =   ( localStorage.getItem("wpsstm-scrobble") == 'true' ); //localStorage stores strings);
        self.is_api_logged =         parseInt(wpsstmLastFM.is_api_logged);
        self.auth_notice_el =        null;
        
        if ( self.is_scrobbler_active === null ){  //default
            self.is_scrobbler_active = true;
        }

    }
    
    domReady(){
        
        var self = this;
        
        self.icon_scrobble_el =     jQuery('#wpsstm-player-toggle-scrobble')
        self.icon_love_el =         jQuery('#wpsstm-lastfm-love-unlove-track-links');
        self.auth_notice_el =       jQuery('#wpsstm-bottom-notice-lastfm-auth');
        
        if (self.is_scrobbler_active){
            jQuery(self.icon_scrobble_el).addClass('active');
        }

        //click toggle scrobbling
        jQuery(self.icon_scrobble_el).find('a').click(function(e) {
            e.preventDefault();
            if ( !self.is_api_logged ) return;

            self.is_scrobbler_active = !jQuery(self.icon_scrobble_el).hasClass('active');
            jQuery(self.icon_scrobble_el).toggleClass('active');

            localStorage.setItem("wpsstm-scrobble", self.is_scrobbler_active);

        });

        //LAST.FM : user is not logged
        jQuery(self.auth_notice_el).click(function(e) {
            if ( !wpsstm_get_current_user_id() ){
                e.preventDefault();
                jQuery('#wpsstm-bottom-notice-wp-auth').addClass('active');
                return;
            }
            if ( !self.is_api_logged ){
                e.preventDefault();
                jQuery(self.auth_notice_el).addClass('active');
            }
        });
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
                if (data.success === false) {
                    console.log(data);
                }else{
                    if (do_love){
                        $(self.icon_love_el).addClass('wpsstm-is-loved');
                    }else{
                        $(self.icon_love_el).removeClass('wpsstm-is-loved');
                    }

                }
            },
            complete: function() {
                $(self.icon_scrobble_el).removeClass('loading');
            }
        })
    }
        
}

(function($){
    
    $(document).ready(function(){
        
        wpsstm_lastfm.domReady();
        


    });

    $( document ).on( "wpsstmPlayerMediaEvent", function( event,mediaEvent,media,node,player,track_obj ) {

        switch(mediaEvent) {
            case 'loadeddata':
                console.log(wpsstm_lastfm.is_scrobbler_active);
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




