var $ = jQuery.noConflict();

class WpsstmLastFM {
    constructor(){
        this.lastfm_scrobble_along =    parseInt(wpsstmLastFM.lastfm_scrobble_along);
        $(document).on('wpsstmPlayerReady',this._initPlayerEvent);
        $(document).on('wpsstmTrackStart',this._nowPlayingTrackEvent);
    }

    enable_scrobbler(do_enable){

        var self = this;
        var success = $.Deferred();

        var ajax_data = {
            action: 'wpsstm_lastfm_toggle_user_scrobbler',
            do_enable: do_enable,
        };

        var ajax = $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    success.reject();
                }else{
                    success.resolve();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },

        })
        
        return success.promise();
    }
 
    /*
    last.fm API - track.updateNowPlaying
    */

    updateNowPlaying(track_obj){
        
        var self = this;
        var success = $.Deferred();

        var ajax_data = {
            action:             'wpsstm_user_update_now_playing_lastfm_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        var ajax = $.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    success.reject();
                }else{
                    success.resolve();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
        })
        
        return success.promise();
    }

    /*
    last.fm API - track.scrobble
    */

    user_scrobble(track_obj){

        var self = this;
        var success = $.Deferred();

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_user_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        var ajax = $.ajax({

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
                    success.reject();
                }else{
                    success.resolve();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
        })
        
        return success.promise();
    }
    
    bot_scrobble(track_obj){
        
        var self = this;
        var success = $.Deferred();

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_bot_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        self.debug(ajax_data);

        var ajax = $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            success: function(data){
                console.log(data);
                if (data.success === false) {
                    console.log(data);
                    success.reject();
                }else{
                    success.resolve();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
        })
        
        return success.promise();
    }
        
    debug(data,msg){

        var prefix = '[lastfm]';
        if (typeof msg === 'undefined'){
            msg = prefix;
        }else{
            msg = prefix + ' ' + msg;
        }

        wpsstm_debug(data,msg);
    }
    
    _initPlayerEvent(e, tracklist){
        var scrobble_icon =         $(tracklist).find('.wpsstm-player-action-scrobbler');

        //click toggle scrobbling
        scrobble_icon.click(function(e) {
            e.preventDefault();

            scrobble_icon.addClass('lastfm-loading');

            var do_enable = !scrobble_icon.hasClass('active');
            var ajax_toggle = wpsstm_lastfm.enable_scrobbler(do_enable);

            ajax_toggle.done(function() {
                scrobble_icon.toggleClass('active',do_enable);
            })
            .fail(function() {
                scrobble_icon.addClass('scrobbler-error');
            })
            .always(function() {
                scrobble_icon.removeClass('lastfm-loading');
            });

        });
    }
    
    _nowPlayingTrackEvent(e, track){
        var scrobble_icon =         $(track.tracklist).find('.wpsstm-player-action-scrobbler');
        var scrobbler_enabled =     scrobble_icon.hasClass('active');

        var nowPlayingTrack = function(){
            if (!scrobbler_enabled) return;

            scrobble_icon.addClass('lastfm-loading');

            var ajax = wpsstm_lastfm.updateNowPlaying(track);

            ajax.fail(function() {
                scrobble_icon.addClass('scrobbler-error');
            })
            .always(function() {
                scrobble_icon.removeClass('lastfm-loading');
            });

        }

        var ScrobbleTrack = function() {
            var duration = track.tracklist.current_media.duration;
            if ( duration < 30) return;

            if (scrobbler_enabled){

                var ajax =  wpsstm_lastfm.user_scrobble(track);

                ajax.fail(function() {
                    scrobble_icon.addClass('scrobbler-error');
                })
                .always(function() {
                    scrobble_icon.removeClass('lastfm-loading');
                });

            }

            //bot scrobble
            if (wpsstm_lastfm.lastfm_scrobble_along){
                wpsstm_lastfm.bot_scrobble(track);
            }

        }

        //now playing
        $(track.tracklist.current_media).one('play', nowPlayingTrack);

        //track end
        $(track.tracklist.current_media).one('ended', ScrobbleTrack);
    }
    
}

var wpsstm_lastfm = new WpsstmLastFM();