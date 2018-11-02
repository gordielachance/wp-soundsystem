class WpsstmLastFM {
    constructor(){
        this.scrobble_icon =            undefined;
        this.lastfm_scrobble_along =    parseInt(wpsstmLastFM.lastfm_scrobble_along);
        this.scrobbler_enabled =        ( localStorage.getItem("wpsstm-scrobble") == 'true' ); //localStorage stores strings
    }
    
    init(){
        
        var self = this;
        
        self.scrobble_icon =     $('.wpsstm-player-action-scrobbler');

        //enable scrobbler at init
        if (self.scrobbler_enabled){
            self.enable_scrobbler(true);
        }

        //click toggle scrobbling
        $('.wpsstm-player-action-scrobbler').find('a').click(function(e) {
            e.preventDefault();
            self.enable_scrobbler(!self.scrobbler_enabled,true);
        });

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
                        console.log(data);
                        self.scrobbler_enabled = false;
                        $(self.scrobble_icon).addClass('scrobbler-error');
                        if (data.notice && show_notice){
                            wpsstm_dialog_notice(data.notice);
                        }
                    }else{
                        self.scrobbler_enabled = true;
                        localStorage.setItem("wpsstm-scrobble", true);
                        $(self.scrobble_icon).addClass('scrobbler-enabled');
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(thrownError);
                    self.scrobbler_enabled = false;
                    $(self.scrobble_icon).addClass('scrobbler-error');
                },
                complete: function() {
                    $(self.scrobble_icon).removeClass('lastfm-loading');
                }
            })
        }else{
            self.scrobbler_enabled = false;
            $(self.scrobble_icon).removeClass('scrobbler-enabled');
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
    
    $(document).on( "PageTracklistsInit", function( event ) {
        wpsstm_lastfm.init();
    });
    
    $(document).on( "wpsstmMediaLoaded", function( event, media,source_obj ) {

        $(media).on('play', function() {
            if (wpsstm_lastfm.scrobbler_enabled){
                wpsstm_lastfm.updateNowPlaying(source_obj.track);
            }
        });
        
        $(media).on('ended', function() {
            if ( media.duration > 30) { //scrobble
                if (wpsstm_lastfm.scrobbler_enabled){
                    wpsstm_lastfm.user_scrobble(source_obj.track);
                }
                //bot scrobble
                if (wpsstm_lastfm.lastfm_scrobble_along){
                    wpsstm_lastfm.community_scrobble(source_obj.track);
                }
            }
        });
        
    });


    $(document).on( "wpsstmTrackLove", function( event,track_obj,do_love ) {
        wpsstm_lastfm.love_unlove(track_obj,do_love);
    });
    
})(jQuery);

var wpsstm_lastfm = new WpsstmLastFM();