var $ = jQuery.noConflict();

class WpsstmLastFM {
    constructor(){
        this.lastfm_scrobble_along =    ( parseInt(wpsstmLastFM.lastfm_scrobble_along) === 1 );
        this.lastfm_scrobble_user =     ( parseInt(wpsstmLastFM.lastfm_scrobble_user) === 1 );
        $('wpsstm-tracklist').on('playerInit',this._initPlayerEvent);
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
            dataType: 'json'
        })
        .done(function(data){
            if (data.success === false) {
                console.log(data);
                success.reject();
            }else{
                success.resolve();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            success.reject(thrownError);
        })

        success.done(function () {
            this.lastfm_scrobble_user = do_enable;
        })
        .fail(function(reason) {
            console.log(reason);
        })


        return success.promise();
    }

    /*
    last.fm API - track.updateNowPlaying
    */

    updateNowPlaying(track_obj){

        var self = this;
        var success = $.Deferred();

        track_obj.debug("[Last.fm] update user NOW PLAYING track");

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
        })
        .done(function(data){
            if (data.success === false) {
                console.log(data);
                success.reject();
            }else{
                success.resolve();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            success.reject(thrownError);
        })

        success.fail(function(reason) {
            console.log(reason);
        })

        return success.promise();
    }

    /*
    last.fm API - track.scrobble
    */

    user_scrobble(track_obj){

        var self = this;
        var success = $.Deferred();

        track_obj.debug("[Last.fm] scrobble USER track");

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
        })
        .done(function(data){
            if (data.success === false) {
                console.log(data);
                success.reject();
            }else{
                success.resolve();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            success.reject(thrownError);
        })

        success.fail(function(reaseon) {
             console.log(reason);
        })

        return success.promise();
    }

    bot_scrobble(track_obj){

        var self = this;
        var success = $.Deferred();

        track_obj.debug("[Last.fm] scrobble BOT track");

        var ajax_data = {
            action:             'wpsstm_lastfm_scrobble_bot_track',
            track:              track_obj.to_ajax(),
            playback_start:     Math.round( $.now() /1000), //time in sec
        };

        var ajax = $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
        })
        .done(function(data){
            if (data.success === false) {
                console.log(data);
                success.reject();
            }else{
                success.resolve();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            success.reject(thrownError);
        })

        success.fail(function (reason) {
            console.log(reason);
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

    _initPlayerEvent(e){
        var tracklist =             this;
        var $scrobbleIcon =         $(tracklist).find('.wpsstm-player-action-scrobbler');

        //click toggle scrobbling
        $scrobbleIcon.click(function(e) {
            e.preventDefault();

            $scrobbleIcon.addClass('lastfm-loading');

            var do_enable = !wpsstm_lastfm.lastfm_scrobble_user;
            var ajax_toggle = wpsstm_lastfm.enable_scrobbler(do_enable);

            ajax_toggle.done(function() {
                $scrobbleIcon.toggleClass('active',do_enable);
            })
            .done(function() {
                $scrobbleIcon.removeClass('scrobbler-error');
            })
            .fail(function() {
                $scrobbleIcon.addClass('scrobbler-error');
            })
            .always(function() {
                $scrobbleIcon.removeClass('lastfm-loading');
            });

        });

        $scrobbleIcon.toggleClass('active',wpsstm_lastfm.lastfm_scrobble_user);
        $(tracklist.tracks).on('started',WpsstmLastFM._nowPlayingTrackEvent);
        $(tracklist.tracks).on('ended',WpsstmLastFM._scrobbleTrackEvent);

    }

    static _nowPlayingTrackEvent(e){

        var track =                 this;
        var tracklist =             track.closest('wpsstm-tracklist');
        var $scrobbleIcon =         $(tracklist).find('.wpsstm-player-action-scrobbler');
        var scrobbler_enabled =     $scrobbleIcon.hasClass('active');

        if (scrobbler_enabled){

            $scrobbleIcon.addClass('lastfm-loading');
            wpsstm_lastfm.updateNowPlaying(track)
            .done(function() {
                $scrobbleIcon.removeClass('scrobbler-error');
            })
            .fail(function(reason) {
                console.log(reason);
                $scrobbleIcon.addClass('scrobbler-error');
            })
            .always(function() {
                $scrobbleIcon.removeClass('lastfm-loading');
            });

        }

    }

    static _scrobbleTrackEvent(e){
        var track =                 this;
        var tracklist =             track.closest('wpsstm-tracklist');
        var $scrobbleIcon =         $(tracklist).find('.wpsstm-player-action-scrobbler');
        var scrobbler_enabled =     $scrobbleIcon.hasClass('active');

        var duration = tracklist.mediaElement.duration;
        if ( duration < 30) return;

        //bot scrobble
        if (wpsstm_lastfm.lastfm_scrobble_along){
            wpsstm_lastfm.bot_scrobble(track);
        }

        //user scrobble
        if (scrobbler_enabled){
            $scrobbleIcon.addClass('lastfm-loading');

            wpsstm_lastfm.user_scrobble(track)
            .done(function() {
                $scrobbleIcon.removeClass('scrobbler-error');
            })
            .fail(function(reason) {
                console.log(reason);
                $scrobbleIcon.addClass('scrobbler-error');
            })
            .always(function() {
                $scrobbleIcon.removeClass('lastfm-loading');
            });
        }


    }

}

var wpsstm_lastfm = new WpsstmLastFM();
