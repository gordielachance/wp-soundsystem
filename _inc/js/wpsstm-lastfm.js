var $ = jQuery.noConflict();

class WpsstmLastFM {
    constructor(){
        this.scrobble_icon =            undefined;
        this.lastfm_scrobble_along =    parseInt(wpsstmLastFM.lastfm_scrobble_along);
        this.scrobbler_enabled =        parseInt(wpsstmLastFM.lastfm_user_scrobbler);
    }

    enable_scrobbler(do_enable){

        var self = this;

        var ajax_data = {
            action: 'wpsstm_lastfm_toggle_user_scrobbler',
            do_enable: do_enable,
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

                console.log(data);

                if (data.success === false) {
                    do_enable = false;
                    $(self.scrobble_icon).addClass('scrobbler-error');
                    if (data.notice){
                        wpsstm_notice(data.notice);
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
                $(self.scrobble_icon).toggleClass('active',do_enable);
            }
        })
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
        
    debug(msg){
        var prefix = "WpsstmLastFM";
        wpsstm_debug(msg,prefix);
    }
    
}

$(document).on( "wpsstmPlayerInit", function( event,player ) {
    
    var player = this;

    wpsstm_lastfm.scrobble_icon =     $(player).find('.wpsstm-player-action-scrobbler');

    //enable scrobbler at init
    if (wpsstm_lastfm.scrobbler_enabled){
        wpsstm_lastfm.enable_scrobbler(true);
    }

    //click toggle scrobbling
    wpsstm_lastfm.scrobble_icon.find('a').click(function(e) {
        e.preventDefault();
        wpsstm_lastfm.enable_scrobbler(!wpsstm_lastfm.scrobbler_enabled);
    });
});

$(document).on( "wpsstmSourceInit", function( event, source ) {

    var track = source.closest('wpsstm-track');
    var player = source.closest('wpsstm-player');

    var nowPlayingTrack = function(){
        if (!wpsstm_lastfm.scrobbler_enabled) return;

        wpsstm_lastfm.updateNowPlaying(track);
    }

    var ScrobbleTrack = function() {
        if ( source.duration < 30) return;

        if (wpsstm_lastfm.scrobbler_enabled){
            wpsstm_lastfm.user_scrobble(track);
        }
        //bot scrobble
        if (wpsstm_lastfm.lastfm_scrobble_along){
            wpsstm_lastfm.community_scrobble(track);
        }

    }

    //now playing
    $(player.current_media).one('play', nowPlayingTrack);

    //track end
    $(player.current_media).one('ended', ScrobbleTrack);

});


var wpsstm_lastfm = new WpsstmLastFM();