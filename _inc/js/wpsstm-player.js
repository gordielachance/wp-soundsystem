var bottom_block;
var bottom_player;
var wpsstm_player;
var wpsstm_current_media;
var wpsstm_countdown_s = wpsstmPlayer.autoredirect; //seconds for the redirection notice
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track
var wpsstm_tracklists = [];

//those are the globals for autoplay and tracks navigation
var wpsstm_current_tracklist_idx;
var wpsstm_track_playing;

(function($){

    $(document).ready(function(){

        bottom_block = $('#wpsstm-bottom');
        bottom_player = bottom_block.find('#wpsstm-bottom-player');
        bt_prev_track = $('#wpsstm-player-extra-previous-track');
        bt_next_track = $('#wpsstm-player-extra-next-track');
        
        bt_prev_track = $('#wpsstm-player-extra-previous-track');
        bt_next_track = $('#wpsstm-player-extra-next-track');

        /* tracklist */

        //init tracklists
        $( ".wpsstm-tracklist" ).wpsstm_init_tracklists();
        
        //define autoplay
        if ( wpsstmPlayer.autoplay ){
            if ( tracklist_obj = wpsstm_set_current_tracklist(0) ){
                tracklist_obj.initialize(0);
            }
        }
        
        bt_prev_track.click(function(e) {
            e.preventDefault();
            wpsstm_tracklists[wpsstm_current_tracklist_idx].play_previous_track();
        });
        
        bt_next_track.click(function(e) {
            e.preventDefault();
            wpsstm_tracklists[wpsstm_current_tracklist_idx].play_next_track();
        });
        
        //source item
        //TO FIX this should be under WpsstmTrackSource
        $( ".wpsstm-player-sources-list li .wpsstm-source-title" ).live( "click", function(e) {

            e.preventDefault();

            var track_el = $(this).closest('[itemprop="track"]');
            var track_idx = Number( track_el.attr('data-wpsstm-track-idx') );
            
            var source_el = $(this).closest('li');
            var source_idx = Number( source_el.attr('data-wpsstm-source-idx') );
            var source = wpsstm_tracklists[wpsstm_current_tracklist_idx].tracks[track_idx].sources[source_idx];
            source.click();

        });

        /*
        page buttons
        */
        
        $( ".wpsstm-play-track" ).live( "click", function(e) {
            e.preventDefault();

            var tracklist_el = $(this).closest('.wpsstm-tracklist');
            var tracklist_idx = $(tracklist_el).attr('data-wpsstm-tracklist-idx');
            
            var track_el = $(this).closest('tr');
            var track_idx = $(track_el).attr('data-wpsstm-track-idx');

            if ( $(track_el).hasClass('active') ){
                if ( $(track_el).hasClass('playing') ){
                    wpsstm_current_media.pause();
                }else{
                    wpsstm_current_media.play();
                }
            }else{
                if ( tracklist_obj = wpsstm_set_current_tracklist(tracklist_idx) ){
                    tracklist_obj.initialize(track_idx);
                }
            }

        });
        
        /*
        Refresh playlist
        */
        $( "a.wpsstm-refresh-playlist" ).live( "click", function(e) {
            e.preventDefault();
            
            var tracklist_el = $(this).closest('.wpsstm-tracklist');
            var tracklist_idx = $(tracklist_el).attr('data-wpsstm-tracklist-idx');
            var tracklist_obj = wpsstm_tracklists[tracklist_idx];

            tracklist_obj.did_tracklist_request = false; //unset did request status

            tracklist_obj.initialize(); //initialize but do not set track to play
            
        });
        
        //user is not logged for action
        $('.wpsstm-requires-auth').click(function(e) {
            if ( !wpsstm_get_current_user_id() ){
                e.preventDefault();
                $('#wpsstm-bottom-notice-wp-auth').addClass('active');
            }

        });
        
        /*
        Player : random
        */
        var shuffle_extra_el = $('#wpsstm-player-random');
        var is_shuffle = localStorage.getItem("wpsstm-random");
        
        if (is_shuffle){
            shuffle_extra_el.addClass('active');
        }
        
        $('#wpsstm-player-random a').click(function(e) {
            e.preventDefault();
            
            var is_active = !shuffle_extra_el.hasClass('active');
            
            if (is_active){
                localStorage.setItem("wpsstm-random", true);
                shuffle_extra_el.addClass('active');
            }else{
                localStorage.removeItem("wpsstm-random");
                 shuffle_extra_el.removeClass('active');
            }
            
            
        });
        

    });

    //Confirmation popup is a media is playing and that we leave the page
    
    $(window).bind('beforeunload', function(){
        if (!wpsstm_current_media.paused){
            return wpsstmPlayer.leave_page_text;
        }
    });
    
    $.fn.wpsstm_init_tracklists = function() {

        this.each(function( i, tracklist_el ) {
            new WpsstmTracklist(tracklist_el);
        });
            
    };
 
})(jQuery);


class WpsstmTracklist {
    constructor(tracklist_el) {

        var self = this;
        self.tracklist_idx = wpsstm_tracklists.length;
        
        console.log("new WpsstmTracklist #" + self.tracklist_idx);
        
        self.tracks =                   new Array();
        self.sources_requests =         [];
        self.seconds_before_refresh =   null;
        self.tracklist_finished =       false;
        self.current_track_idx =        null;
        self.did_tracklist_request =    false;

        wpsstm_tracklists.push(self);
        
        self.sync_html(tracklist_el);

    }
    
    sync_html(new_tracklist_el){
        
        var self = this;
        jQuery(new_tracklist_el).attr('data-wpsstm-tracklist-idx',self.tracklist_idx);

        self.tracklist_id = Number( jQuery(new_tracklist_el).attr('data-tracklist-id') );
        self.expire_time =  Number( jQuery(new_tracklist_el).attr('data-wpsstm-next-refresh') );
        
        self.load_track_objs();
        
    }
    
    update_refresh_timer(){
        var self = this;
        self.seconds_before_refresh = self.seconds_before_refresh - 1;
        
        /*
        Live-update countdown -- TO FIX disabled for now because it might take ressources and we don't really need it
        */

        var tracklist_el = self.get_tracklist_el();
        var tracklist_time_el = jQuery(tracklist_el).find('.wpsstm-tracklist-time');
        
        /*
        var countdown_el = jQuery(tracklist_time_el).find('.wpsstm-live-tracklist-expiry-countdown');
        var time_el = jQuery(countdown_el).find('time');
        var time_hours_el = time_el.find('.wpsstm-tracklist-refresh-hours');
        var time_minutes_el = time_el.find('.wpsstm-tracklist-refresh-minutes');
        var time_seconds_el = time_el.find('.wpsstm-tracklist-refresh-seconds');

        //https://stackoverflow.com/a/8211778/782013
        var current_days = Math.floor((self.seconds_before_refresh % 31536000) / 86400);
        var current_hours = Math.floor(((self.seconds_before_refresh % 31536000) % 86400) / 3600);
        var current_minutes = Math.floor((((self.seconds_before_refresh % 31536000) % 86400) % 3600) / 60);
        var current_seconds = (((self.seconds_before_refresh % 31536000) % 86400) % 3600) % 60;
        
        if (current_days < 0) current_days = 0;
        if (current_hours < 0) current_hours = 0;
        if (current_minutes < 0) current_minutes = 0;
        if (current_seconds < 0) current_seconds = 0;
        
        //two digits
        current_hours =     ("0" + current_hours).slice(-2);
        current_minutes =   ("0" + current_minutes).slice(-2);
        current_seconds =   ("0" + current_seconds).slice(-2);
        
        time_hours_el.text(current_hours);
        time_minutes_el.text(current_minutes);
        time_seconds_el.text(current_seconds);
        
        */
        
        if (self.seconds_before_refresh <= 0){
            $(tracklist_time_el).addClass('can-refresh');
            self.seconds_before_refresh = 0;
            clearInterval(self.refresh_timer);
        }
        
    }

    request_remote_tracklist(){

        console.log("WpsstmTracklist:request_remote_tracklist()");
        
        var self = this;
        var tracklist_el = self.get_tracklist_el();
        var refresh_notice = null;

        var ajax_data = {
            'action':           'wpsstm_load_tracklist',
            'post_id':          this.tracklist_id
        };
        
        var refresh_notice = self.get_refresh_notice();
        var refresh_notice_table = $(refresh_notice).clone();
        refresh_notice_table.find('em').remove();
        refresh_notice_table = jQuery( refresh_notice_table.html() );

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                self.did_tracklist_request = true; //so we can avoid running this function several times

                $(bottom_block).prepend(refresh_notice);

                //replace 'not found' text by refresh notice
                $(tracklist_el).find('tr.no-items td').append( refresh_notice_table );
                $(tracklist_el).addClass('loading');
                

            },
            success: function(data){

                if (data.success === false) {
                    $(tracklist_el).addClass('error');
                    console.log(data);
                }else{
                    var new_tracklist_el = jQuery(data.new_html);
                    $(tracklist_el).replaceWith(new_tracklist_el);
                    self.sync_html(new_tracklist_el);
                    self.initialize();
                }

            },
            fail: function(jqXHR, textStatus, errorThrown) {
                $(tracklist_el).addClass('error');
                console.log("WpsstmTracklist:request_remote_tracklist() failed");
                console.log({jqXHR:jqXHR,textStatus:textStatus,errorThrown:errorThrown});
            },
            complete: function() {
                $(refresh_notice_table).remove();
                refresh_notice.remove();
                $(tracklist_el).removeClass('loading');
            }
        })
    }
    
    get_tracklist_el(){
        var tracklist_el = jQuery('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"]');
        return tracklist_el;
    }
    
    load_track_objs(autoplay = null){
        
        var self = this;
        var tracklist_el = self.get_tracklist_el();
        //console.log("WpsstmTracklist:load_track_objs()");
        //console.log(tracklist_el);

        var tracks_html = jQuery(tracklist_el).find('[itemprop="track"]');

        jQuery.each(tracks_html, function( index, track_html ) {
            var new_track = new WpsstmTrack(track_html,self.tracklist_idx,index);
            self.tracks.push(new_track);
        });

    }
    
    abord_tracks_sources_request() {
        
        var self = this;
        
        if (self.sources_requests.length <= 0) return;
        
        console.log("WpsstmTracklist:abord_tracks_sources_request()");

        for (var i = 0; i < self.sources_requests.length; i++) {
            self.sources_requests[i].abort();
        }

        self.sources_requests.length = 0; //TO FIX better to unset it if possible
    };

    initialize(track_idx){
        
        var self = this;

        if (track_idx!==undefined){ //set next track to play
            self.current_track_idx = track_idx;
        }

        console.log("WpsstmTracklist:initialize() playlist #" + self.tracklist_idx + " track #" + self.current_track_idx);

        self.init_refresh_timer();
        
        if ( self.expire_time && ( self.expire_time <= Math.floor( Date.now() / 1000) ) && !self.did_tracklist_request ){
            self.request_remote_tracklist();
            return; //we be called again later
        }

        var play_track = wpsstm_tracklists[wpsstm_current_tracklist_idx].tracks[self.current_track_idx];
        if(typeof play_track === 'undefined') return; //track does not exists
        
        console.log("autoplay track tracklist #" + wpsstm_current_tracklist_idx + " track #" + self.current_track_idx);
        play_track.play_or_skip();
        
    }
    
    play_previous_track(){
        var self = this;

        var previous_idx = self.current_track_idx - 1;
        
        console.log("WpsstmTracklist:play_previous_track() #" + previous_idx + "in playlist#" + self.tracklist_idx);

        if(typeof self.tracks[previous_idx] === 'undefined'){
            console.log("tracklist start");
        }else{
            self.tracks[previous_idx].play_or_skip();
        }
    }

    play_next_track(){

        var self = this;

        var next_idx = self.current_track_idx + 1;
        
        console.log("WpsstmTracklist:play_next_track() #" + next_idx + "in playlist#" + self.tracklist_idx);

        if(typeof self.tracks[next_idx] === 'undefined'){
            
            console.log("Reached tracklist end");
            self.tracklist_finished = true;

            if ( wpsstmPlayer.autoplay ){
                
                //try to start next playlist if any
                var next_tracklist_idx = self.tracklist_idx + 1;
                var next_tracklist_obj;
                
                next_tracklist_obj = wpsstm_set_current_tracklist(next_tracklist_idx)
                
                //no next playlist, get first one
                if ( !next_tracklist_obj ){
                    next_tracklist_obj = wpsstm_set_current_tracklist(0);
                }

                var track_idx = 0;
                next_tracklist_obj.current_track_idx = track_idx;
                
                console.log("WpsstmTracklist:play_next_track() - set autoplay playlist #" + wpsstm_current_tracklist_idx + " track#" + track_idx);

                if ( next_tracklist_obj.seconds_before_refresh === 0 ){ //if it needs a refresh first
                    next_tracklist_obj.request_remote_tracklist();
                }else{
                    next_tracklist_obj.initialize();
                }

            }

        }else{
            self.tracks[next_idx].play_or_skip();
        }

    }
    
    /*
    timer notice
    */
    
    init_refresh_timer(){
        var self = this;
        
        //expire countdown
        if (!self.expire_time || self.expire_time <= 0) return;
        
        self.seconds_before_refresh = this.expire_time - Math.floor( Date.now() / 1000);
        
        if (!self.seconds_before_refresh || self.seconds_before_refresh <= 0) return;
        
        console.log('init_refresh_timer');
        
        console.log("set timer for " + self.expire_time);
        if (self.refresh_timer){ //stop current timer if any
            clearInterval(self.refresh_timer);
            self.refresh_timer = null;
        }
        
        console.log("this tracklist could refresh in "+ self.seconds_before_refresh +" seconds");

        self.refresh_timer = setInterval ( function(){
                return self.update_refresh_timer();
        }, 1000 );
    }

    get_refresh_notice(){
        
        console.log("get_refresh_notice");
        
        var self = this;
        var notice_el = jQuery('<p />');
        var tracklist = this.get_tracklist_el();
        var tracklist_title = $(tracklist).find('[itemprop="name"]').first().text();
        
        notice_el.attr({
            class:  'wpsstm-bottom-notice active'
        });
        
        var notice_icon_el = jQuery('<i class="fa fa-refresh fa-fw fa-spin"></i>');
        var notice_message_el = jQuery('<span />');
        var playlist_title = jQuery('<em />');
        playlist_title.text("  " +tracklist_title);
        notice_message_el.html(wpsstmPlayer.refreshing_text);
        notice_message_el.append(playlist_title);
        
        notice_el.append(notice_icon_el).append(notice_message_el);

        return notice_el;
    }

}

class WpsstmTrack {
    constructor(track_html,tracklist_idx,track_idx) {

        var self = this;
        self.tracklist_idx = tracklist_idx; //cast to number;
        self.track_idx = track_idx;
        self.artist = jQuery(track_html).find('[itemprop="byArtist"]').text();
        self.title = jQuery(track_html).find('[itemprop="name"]').text();
        self.album = jQuery(track_html).find('[itemprop="inAlbum"]').text();
        self.did_sources_request = false;
        self.can_play = true;
        self.sources = [];
       
        //console.log("new WpsstmTrack #" + this.track_idx + " in tracklist #" + this.tracklist_idx);
        
        jQuery(track_html).attr('data-wpsstm-track-idx',this.track_idx);
        
        //populate existing sources
        self.populate_html_sources();

    }

    get_playlist_obj(){
        console.log(this.tracklist_idx);
        return wpsstm_tracklists[this.tracklist_idx];
    }

    get_track_el(){
        var track_el = jQuery('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"] [itemprop="track"][data-wpsstm-track-idx="'+this.track_idx+'"]');
        return track_el;
    }
    
    /*
    Update the track button after a media event.
    */

    update_button(event){

        var track_el = this.get_track_el();

        switch(event) {
            case 'loadeddata':
            break;
            case 'error':
                track_el.addClass('error');
            break;
            case 'play':
                track_el.addClass('playing');
                track_el.removeClass('error buffering ended');
            break;
            case 'pause':
                track_el.removeClass('playing');
            break;
            case 'ended':
                track_el.removeClass('playing');
                track_el.addClass('has-played');
            break;
        }

    }
    
    /*
    Initialize a track : either play it if it has sources; or get the sources then call this function again (with after_ajax = true)
    */

    play_or_skip(after_ajax = false){

        var self = this;

        var track_el = self.get_track_el();
        var tracklist_obj = wpsstm_tracklists[this.tracklist_idx];
        
        //cannot play this track
        if (!self.can_play) tracklist_obj.play_next_track();

        //is called a second time after tracks sources have been populated.
        if (!after_ajax){

            console.log("WpsstmTrack::play_or_skip() tracklist#" + self.tracklist_idx + ", track#" + self.track_idx);

            //skip the current track if any
            wpsstm_end_current_track();
            
            //set global
            tracklist_obj.current_track_idx = self.track_idx;
            
        }

        //play current track if it has sources

        if ( self.sources.length > 0 ){
            console.log("WpsstmTrack::play_or_skip() - play");
            self.send_to_player();
        }else if (self.did_sources_request){ //no sources and had lookup
            console.log("WpsstmTrack::play_or_skip() - skip");
            self.can_play = false;
            tracklist_obj.play_next_track();
        }else{ //load sources
            self.init_sources_request();
        }
    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    init_sources_request() {
        
        var self = this;
        var tracklist = wpsstm_tracklists[self.tracklist_idx];

        console.log("WpsstmTrack::init_sources_request()");
        console.log(self);

        tracklist.abord_tracks_sources_request(); //abord current requests

        var tracks_to_preload = [];
        var max_items = wpsstm_track_source_requests_limit;
        var rtrack_count = 0;
        var rtrack_in = self.track_idx;
        var rtrack_out = self.track_idx + max_items;

        //TO FIX not working for first track ?
        var tracks_slice = $(tracklist.tracks).slice( rtrack_in, rtrack_out );

        jQuery.each(tracks_slice, function( index, rtrack_obj ) {
            rtrack_count++;
            if ( rtrack_obj.did_sources_request ) return true; //continue
            if (rtrack_count > max_items){
                return false;//break
            }else{
                tracks_to_preload.push(rtrack_obj);
            }
        });

        jQuery(tracks_to_preload).each(function(index, track_to_preload) {
            if ( track_to_preload.sources.length <= 0 ){
                track_to_preload.build_sources_request();
            }
        });
    }
    
    //http://stackoverflow.com/questions/42271167/break-out-of-ajax-loop
    build_sources_request() {
        
        //console.log("WpsstmTrack:build_sources_request() for track#" + this.track_idx);

        var self = this;
        var playlist_obj = self.get_playlist_obj();
        var xhr = this.request_sources();
        playlist_obj.sources_requests.push(xhr);

        xhr.fail(function(jqXHR, textStatus, errorThrown) {
            /*
            if (jqXHR.status === 0 || jqXHR.readyState === 0) { //http://stackoverflow.com/a/5958734/782013
                return;
            }
            */

            console.log("getTrackSourceRequest() failed for track #" + self.track_idx);
            console.log({jqXHR:jqXHR,textStatus:textStatus,errorThrown:errorThrown});
        })
        .done(function(data) {
            //track could have been switched since, so check if this is still the track to play
            
            //Check if the current track is still the active one
            if ( (wpsstm_current_tracklist_idx !==null) && ( wpsstm_current_tracklist_idx === self.tracklist_idx ) ) {
                var tracklist = wpsstm_tracklists[wpsstm_current_tracklist_idx];
                if ( (tracklist.current_track_idx !==null) && ( tracklist.current_track_idx === self.track_idx ) ) {
                    self.play_or_skip(true);
                }
            }

        })
        .then(function(data, textStatus, jqXHR) {})
        .always(function(data, textStatus, jqXHR) {
            //item.statusText = null;
            //playlist_obj.sources_requests.$apply();
        })
    }

    send_to_player(){
        var self = this;
        var tracklist = wpsstm_tracklists[self.tracklist_idx];
        console.log("send_to_player()  tracklist#" + tracklist.tracklist_idx + ", track#" + self.track_idx);

        var track_el    = self.get_track_el();
        
        wpsstm_track_playing = self;
        jQuery(track_el).addClass('active');

        //track infos
        var trackinfo = $(track_el).clone();
        trackinfo.attr('data-wpsstm-tracklist-idx',self.tracklist_idx); //set tracklist ID for the player
        trackinfo.show();
        trackinfo.find('td.trackitem_play_bt').remove();
        $('#wpsstm-player-trackinfo').html(trackinfo);

        //player sources

        var media_wrapper = $('<audio />');
        media_wrapper.attr({
            id:     'wpsstm-player-audio'
        });

        media_wrapper.prop({
            //autoplay:     true,
            //muted:        true
        });

        $( self.sources ).each(function(i, source_attr) {
            //media
            var source_el = $('<source />');
            source_el.attr({
                src:    source_attr.src,
                type:   source_attr.type
            });

            media_wrapper.append(source_el);

        });

        $('#wpsstm-player').html(media_wrapper);

        //display bottom block if not done yet
        bottom_player.show();

        new MediaElementPlayer('wpsstm-player-audio', {
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug: true,
                autoStartLoad: false
            },
            // Do not forget to put a final slash (/)
            pluginPath: 'https://cdnjs.com/libraries/mediaelement/',
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(media, node, player) {
                    console.log("MediaElementPlayer ready");

                    wpsstm_player = player;
                    wpsstm_current_media = media;

                    $(wpsstm_current_media).on('error', function(error) {
                        var current_source = $(wpsstm_current_media).find('audio').attr('src');
                        console.log('player event - source error: '+current_source);
                        self.update_button('loadeddata');
                        self.skip_bad_source(wpsstm_current_media);

                    });

                    $(wpsstm_current_media).on('loadeddata', function() {
                        console.log('player event - loadeddata');
                        self.update_button('loadeddata');
                        $( document ).trigger( "wpsstmPlayerMediaEvent", ['loadeddata',media, node, player,self] ); //register custom event - used by lastFM for the track.updateNowPlaying call

                        wpsstm_player.play();

                    });

                    $(wpsstm_current_media).on('play', function() {
                        if (media.duration <= 0) return; //quick fix because it was fired twice.
                        self.duration = Math.floor(media.duration);
                        self.playback_start = Math.round( $.now() /1000); //seconds - used by lastFM
                        console.log('player event - play');
                        self.update_button('play');
                    });

                    $(wpsstm_current_media).on('pause', function() {
                        console.log('player - pause');
                        self.update_button('pause');
                    });

                    $(wpsstm_current_media).on('ended', function() {
                        console.log('MediaElement.js event - ended');
                        self.update_button('ended');
                        wpsstm_current_media = null;

                        $( document ).trigger( "wpsstmPlayerMediaEvent", ['ended',media, node, player,self] ); //register custom event - used by lastFM for the track.scrobble call

                        //Play next song if any
                        wpsstm_tracklists[self.tracklist_idx].play_next_track();
                    });

            },error(media) {
                // Your action when media had an error loading
                //TO FIX is this required ?
                console.log("player error");
            }
        });

    }
    
    request_sources() {

        var self = this;
        var track_el    = self.get_track_el();
        if (!track_el) return;

        var track = {
            artist: self.artist,
            title:  self.title,
            album:  self.album
        }
        
        //console.log("WpsstmTrack:request_sources(): #" + this.track_idx);
        
        var ajax_data = {
            'action':           'wpsstm_player_get_provider_sources',
            'track':            track
        };
        
        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                self.did_sources_request = true;
                jQuery(track_el).addClass('buffering');
            },
            success: function(data){
                if (data.success === false) {
                    jQuery(track_el).addClass('error');
                    console.log("error getting sources for track#" + self.track_idx);
                    console.log(data);
                    
                    var tracklist_obj = self.get_playlist_obj();
                    tracklist_obj.play_next_track();
                    
                }else{
                    if ( data.new_html ){
                        jQuery(track_el).find('.trackitem_sources').html(data.new_html); //append new sources
                        self.populate_html_sources();
                    }
                }
            },
            complete: function() {
                jQuery(track_el).removeClass('buffering');
            }
        })

    }
    
    populate_html_sources(){
        var self =      this;
        var track_el =  this.get_track_el();

        var new_sources_items = jQuery(track_el).find('.trackitem_sources li');

        //console.log("found "+new_sources_items.length +" sources for track#" + this.track_idx);
        
        var sources = [];
        jQuery.each(new_sources_items, function( index, li_item ) {
            var new_source = new WpsstmTrackSource(li_item,self);
            self.sources.push(new_source);            
        });
        
        jQuery(track_el).attr('data-wpsstm-sources-count',self.sources.length);
        
    }
    
    switch_track_source(idx){
        var self = this;

        var new_player_source = $(wpsstm_current_media).find('audio source').eq(idx);
        var new_player_source_url = new_player_source.attr('src');
        var current_player_source_url = $(wpsstm_current_media).find('audio').attr('src');

        if (current_player_source_url == new_player_source_url) return false;
        
        var new_source_obj = self.sources[idx];
        console.log("WpsstmTrack:switch_track_source():" + new_player_source_url);

        //player
        wpsstm_current_media.pause();
        wpsstm_current_media.setSrc(new_player_source);
        wpsstm_current_media.load();
        wpsstm_current_media.play();

        //trackinfo
        var trackinfo_sources = jQuery(bottom_player).find('#wpsstm-player-sources-wrapper li');
        jQuery(trackinfo_sources).removeClass('wpsstm-active-source');

        var new_source_el = new_source_obj.get_player_source_el();
        jQuery(new_source_el).addClass('wpsstm-active-source');
    }
    
    skip_bad_source(media){
        
        console.log("WpsstmTrack:skip_bad_source()");
        
       //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067
        
        var self = this;
        var current_source_url = $(media).find('audio').attr('src');
        var source_els = $(media).find('source');
        var source_els_clone = $(media).find('source').clone();
        var new_source_idx = -1;

        source_els_clone.each(function(i, val) {

            var source = $(this);
            var source_url = source.attr('src');
            
            if (!source_url) return true; //continue
            if (source.hasClass('wpsstm-bad-source')) return true; //continue;

            if ( source_url == current_source_url ) {
                
                $(source_els_clone).eq(i).remove(); //remove from loop
                $(source_els).eq(i).addClass('wpsstm-bad-source'); //add class to source
                $('#wpsstm-player-sources-wrapper li').eq(i).addClass('wpsstm-bad-source');//add class to trackinfo source
                
                console.log("skip; is current source: "+source_url);
                return true; //continue
            }
            
            new_source_idx = i;
            return false;  //break

        });
        
        if (new_source_idx > -1){
            self.skip_bad_source(new_source_idx);
        }else{
            console.log("WpsstmTrack:skip_bad_source() - No valid sources found - go to next track if possible");
            var track_el = self.get_track_el();
            $(track_el).addClass('error');

            //No more sources - Play next song if any
            var tracklist = self.get_playlist_obj();
            tracklist.play_next_track();
        }

    }
    
    end_track(){
        var self = this;
        var track_el    =   self.get_track_el();

        console.log("WpsstmTrack:end_track() #" + self.track_idx + " in playlist " + self.tracklist_idx);

        $(track_el).removeClass('active');
        $(track_el).addClass('has-played');

        //mediaElement
        if (wpsstm_current_media){
            console.log("there is an active media, abord it");
            wpsstm_current_media.pause();
            self.update_button('ended');

        }
    }
    
}

class WpsstmTrackSource {
    constructor(source_html,track) {

        var self = this;
        self.tracklist_idx = track.tracklist_idx;
        self.track_idx = track.track_idx;
        self.source_idx = track.sources.length;
        jQuery(source_html).attr('data-wpsstm-source-idx',this.source_idx);
        
        self.src =    jQuery(source_html).find('a').attr('href');
        self.type =    jQuery(source_html).attr('data-wpsstm-source-type');
        
        //console.log("new WpsstmTrackSource #" + this.source_idx + " in track #" + track.track_idx + " from tracklist track #" + track.tracklist_idx);

    }
    
    get_playlist_obj(){
        console.log("get_playlist_obj");
        console.log(this.tracklist_idx);
        return wpsstm_tracklists[this.tracklist_idx];
    }
    
    get_track_obj(){
        var playlist_obj = this.get_playlist_obj();
        return playlist_obj.tracks[this.track_idx];
    }

    get_player_source_el(){
        return jQuery(bottom_player).find('[data-wpsstm-source-idx="'+this.source_idx+'"]');
    }
    
    click(){
        var self = this;
        var track_obj = this.get_track_obj();

        var track_sources_count = track_obj.sources.length;
        if ( track_sources_count <= 1 ) return;
        
        console.log("clicked source #" + this.source_idx + " of track #" + this.track_idx + " in playlist #" + self.tracklist_idx);
        console.log(self);
        
        var player_source_el = self.get_player_source_el();
        var ul_el = player_source_el.closest('ul');

        var sources_list = player_source_el.closest('ul');
        var sources_list_wrapper = sources_list.closest('td.trackitem_sources');

        sources_list.closest('ul').append(player_source_el); //move it at the bottom

        if ( !player_source_el.hasClass('wpsstm-active-source') ){ //source switch

            var lis_el = player_source_el.closest('ul').find('li');
            lis_el.removeClass('wpsstm-active-source');
            player_source_el.addClass('wpsstm-active-source');

            track_obj.switch_track_source(self.source_idx);
        }

        ul_el.toggleClass('expanded');
    }

}

function wpsstm_end_current_track(){
    if( !wpsstm_track_playing ) return;
    console.log("WpsstmTracklist:end_current_track() ");
    wpsstm_track_playing.end_track();
    wpsstm_track_playing = null;
}

function wpsstm_set_current_tracklist(tracklist_idx){
    tracklist_idx = Number(tracklist_idx);
    var tracklist_obj = wpsstm_tracklists[tracklist_idx];
    if(typeof tracklist_obj !== 'undefined'){
        console.log("wpsstm_set_current_tracklist() #" +  tracklist_idx);
        wpsstm_current_tracklist_idx = tracklist_idx;
        return tracklist_obj;
    }
    return false;
}

function wpsstm_get_track_obj(tracklist_idx,track_idx){
    tracklist_idx = Number(tracklist_idx);
    var tracklist_obj = wpsstm_tracklists[tracklist_idx];
    if(typeof tracklist_obj === 'undefined') return;
    
    track_idx = Number(track_idx);
    var track_obj = tracklist_obj.tracks[track_idx];
    if(typeof track_obj === 'undefined') return;
    
    return track_obj;
}
