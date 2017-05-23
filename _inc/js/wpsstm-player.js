var bottom_block;
var bottom_player;
var redirect_page_notice;
var wpsstm_player;
var wpsstm_current_media;
var wpsstm_countdown_s = wpsstmPlayer.autoredirect; //seconds for the redirection notice
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track

var wpsstm_tracklists = [];
var wpsstm_current_tracklist_idx = null;

(function($){

    $(document).ready(function(){

        bottom_block = $('#wpsstm-bottom');
        bottom_player = bottom_block.find('#wpsstm-bottom-player');
        bt_prev_track = $('#wpsstm-player-nav-previous-track');
        bt_next_track = $('#wpsstm-player-nav-next-track');
        redirect_page_notice = $('#wpsstm-bottom-notice-redirection');
        
        bt_prev_track = $('#wpsstm-player-nav-previous-track');
        bt_next_track = $('#wpsstm-player-nav-next-track');
        
        /* tracklist */

        //init tracklists
        $( ".wpsstm-tracklist" ).wpsstm_init_tracklists();
        
        bt_prev_track.click(function(e) {
            e.preventDefault();
            wpsstm_tracklists[wpsstm_current_tracklist_idx].play_previous_track();
        });
        
        bt_next_track.click(function(e) {
            e.preventDefault();
            wpsstm_tracklists[wpsstm_current_tracklist_idx].play_next_track();
        });

        //source item
        $( ".wpsstm-player-sources-list li .wpsstm-source-title" ).live( "click", function(e) {
            e.preventDefault();

            var track_el = $(this).closest('[itemprop="track"]');
            var track_sources_count = track_el.attr('data-wpsstm-sources-count');
            if ( track_sources_count < 2 ) return;
            
            var sources_list = $(this).closest('ul');
            var sources_list_wrapper = sources_list.closest('td.trackitem_sources');
            var li_el = $(this).closest('li');
            sources_list.closest('ul').append(li_el); //move it at the bottom

            if ( !li_el.hasClass('wpsstm-active-source') ){ //source switch
                
                var lis = li_el.closest('ul').find('li');
                lis.removeClass('wpsstm-active-source');
                li_el.addClass('wpsstm-active-source');
                
                var idx = li_el.attr('data-wpsstm-source-idx');
                wpsstm_switch_track_source(idx);
            }
            
            sources_list_wrapper.toggleClass('expanded');
            
            
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
                var track_obj = wpsstm_tracklists[tracklist_idx].tracks[track_idx];
                track_obj.play_or_skip();
            }

        });

    });

    //Confirmation popup is a media is playing and that we leave the page
    
    $(window).bind('beforeunload', function(){
        if (!wpsstm_current_media.paused){
            return wpsstmPlayer.leave_page_text;
        }
    });

    function wpsstm_switch_track_source(idx){
        var new_source = $(wpsstm_current_media).find('audio source').eq(idx);
        
        console.log("wpsstm_switch_track_source() #" + idx);
        console.log(new_source.get(0));
        
        
        var player_url = $(wpsstm_current_media).find('audio').attr('src');
        var new_source_url = new_source.attr('src');

        if (player_url == new_source_url) return false;

        //player
        wpsstm_current_media.pause();
        wpsstm_current_media.setSrc(new_source);
        wpsstm_current_media.load();
        wpsstm_current_media.play();

        //trackinfo
        var trackinfo_sources = $('#wpsstm-player-sources-wrapper li');
        var trackinfo_new_source = trackinfo_sources.eq(idx);
        trackinfo_sources.removeClass('wpsstm-active-source');

        trackinfo_new_source.addClass('wpsstm-active-source');
    }

    function wpsstm_skip_bad_source(media){
        console.log("try to get next source or next media");
        
       //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067
        
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
            wpsstm_switch_track_source(new_source_idx);
        }else{
            
            //No valid source found
            console.log("coucou2");
            var tracklist_obj = wpsstm_tracklists[wpsstm_current_tracklist_idx];
            var track_obj   = tracklist_obj.tracks[current_track_idx];
            var track_el    = track_obj.get_track_el();
            
            $(track_el).addClass('error');

            //No more sources - Play next song if any
            wpsstm_tracklists[wpsstm_current_tracklist_idx].play_next_track();
        }
        
    }



    
})(jQuery);


class WpsstmTracklist {
    constructor(tracklist_el) {
        
        var self = this;
        self.tracklist_idx = wpsstm_tracklists.length;
        wpsstm_tracklists.push(self);
        
        self.refresh_notice = null; //do not put in init_html
        self.init_html(tracklist_el);
        
        if ( self.expire_time && ( self.expire_time <= Math.floor( Date.now() / 1000) ) ){
            self.request_remote_tracklist();
        }
    }
    
    init_html(new_tracklist_el){
        
        var self = this;
        jQuery(new_tracklist_el).attr('data-wpsstm-tracklist-idx',self.tracklist_idx);

        self.tracklist_id =             Number( jQuery(new_tracklist_el).attr('data-tracklist-id') );
        self.tracks =                   new Array();
        self.sources_requests =         [];
        self.had_tracks_played =        false;
        self.expire_time =              Number( jQuery(new_tracklist_el).attr('data-wpsstm-next-refresh') );
        self.seconds_before_refresh =   null;
        self.tracklist_finished =       false;
        self.current_track_idx =        null;

        wpsstm_current_tracklist_idx = self.tracklist_idx;
        
        console.log(self);

        console.log("WpsstmTracklist:init()");
        
        self.init_refresh_timer();
        self.load_track_objs();
        
        
    }
    
    update_refresh_timer(){
        var self = this;
        self.seconds_before_refresh = self.seconds_before_refresh - 1;

        var tracklist_el = self.get_tracklist_el();
        var time_el = jQuery(tracklist_el).find('.wpsstm-tracklist-refresh-time');
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
        
        if (self.seconds_before_refresh <= 0){
            self.seconds_before_refresh = 0;
            clearInterval(self.refresh_timer);
            
            if (self.tracklist_finished){
                console.log("WpsstmTracklist:update_refresh_timer() : RELOAD TRACKLIST");
                self.request_remote_tracklist();
            }
            
            return;
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

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                refresh_notice = self.get_refresh_notice();
                $(bottom_block).prepend(refresh_notice);
                jQuery(tracklist_el).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    jQuery(tracklist_el).addClass('error');
                    console.log(data);
                }else{
                    var new_tracklist_el = jQuery(data.new_html);
                    jQuery(tracklist_el).replaceWith(new_tracklist_el);
                    self.init_html(new_tracklist_el);
                }
            },
            fail: function(jqXHR, textStatus, errorThrown) {
                console.log("WpsstmTracklist:request_remote_tracklist() failed");
                console.log({jqXHR:jqXHR,textStatus:textStatus,errorThrown:errorThrown});
            },
            complete: function() {
                refresh_notice.remove();
                $(tracklist_el).removeClass('loading');
            }
        })
    }
    
    get_tracklist_el(){
        var tracklist_el = jQuery('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"]');
        return tracklist_el;
    }
    
    load_track_objs(){
        
        var self = this;
        var tracklist_el = self.get_tracklist_el();
        console.log("WpsstmTracklist:load_track_objs()");
        console.log(self);

        var tracks_html = jQuery(tracklist_el).find('[itemprop="track"]');

        jQuery.each(tracks_html, function( index, track_html ) {
            var new_track = new WpsstmTrack(track_html,self.tracklist_idx,index);
            self.tracks.push(new_track);
            
            //autoplay
            if ( ( wpsstmPlayer.autoplay ) && (new_track.tracklist_idx === 0) && (new_track.track_idx === 0) ){
                console.log("autoplay first track");
                new_track.play_or_skip();
            }
            
        });
    }
    
    abord_tracks_sources_request() {
        
        var self = this;
        
        if (self.sources_requests.length <= 0) return;
        
        console.log("WpsstmTracklist:abord_tracks_sources_request()");

        for (var i = 0; i < self.sources_requests.length; i++) {
            self.sources_requests.abort();
        }

        self.sources_requests.length = 0; //TO FIX better to unset it if possible
    };
    
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
            if ( self.seconds_before_refresh == 0 ){
                console.log("WpsstmTracklist:play_next_track() : RELOAD TRACKLIST");
                self.request_remote_tracklist();
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
        if (!self.expire_time) return;
        
        console.log('init_refresh_timer');
        
        console.log("set timer for " + self.expire_time);
        if (self.refresh_timer){ //stop current timer if any
            clearInterval(self.refresh_timer);
            self.refresh_timer = null;
        }
        self.seconds_before_refresh = this.expire_time - Math.floor( Date.now() / 1000);
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
            id:     'wpsstm-bottom-notice-redirection',
            class:  'wpsstm-bottom-notice active'
        });
        
        var notice_icon_el = jQuery('<i class="fa fa-refresh fa-fw fa-spin"></i>');
        var notice_message_el = jQuery('<strong />');
        notice_message_el.text(tracklist_title);
        
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
        self.sources = [];
        //console.log("new WpsstmTrack #" + this.track_idx + " in tracklist #" + this.tracklist_idx);
        
        jQuery(track_html).attr('data-wpsstm-track-idx',this.track_idx);
        
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
        var tracklist = wpsstm_tracklists[this.tracklist_idx];
        jQuery(track_el).addClass('active');

        //is called a second time after tracks sources have been populated.
        if (!after_ajax){
            self.init_sources_request();
        }

        console.log("WpsstmTrack::play_or_skip() tracklist#" + self.tracklist_idx + ", track#" + self.track_idx);
        
        //skip the current track if any
        wpsstm_end_current_track();

        //set global
        tracklist.current_track_idx = self.track_idx;

        //play current track if it has sources

        if ( self.sources.length > 0 ){            
            console.log("WpsstmTrack::play_or_skip() - play");
            self.fill_player();
        }else if (self.did_lookup){ //no sources and had lookup
            console.log("WpsstmTrack::play_or_skip() - skip");
            self.play_next_track();
        }
    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    init_sources_request() {
        
        var self = this;
        var tracklist = wpsstm_tracklists[self.tracklist_idx];
        console.log("WpsstmTrack::init_sources_request()");
        console.log(tracklist);

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
            if ( rtrack_obj.did_lookup ) return true; //continue
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
        var xhr = this.request_sources();
        wpsstm_source_requests.push(xhr);

        xhr.fail(function(jqXHR, textStatus, errorThrown) {
            /*
            if (jqXHR.status === 0 || jqXHR.readyState === 0) { //http://stackoverflow.com/a/5958734/782013
                return;
            }
            */

            console.log("getTrackSourceRequest() failed for track #"+track_idx);
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
            //wpsstm_source_requests.$apply();
        })
    }

    fill_player(){
        var self = this;
        var tracklist = wpsstm_tracklists[this.tracklist_idx];
        console.log("fill_player()  tracklist#" + tracklist.tracklist_idx + ", track#" + self.track_idx);

        var track_el    = self.get_track_el();

        //track infos
        var trackinfo = $(track_el).clone();
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
                        wpsstm_skip_bad_source(wpsstm_current_media);

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
                        tracklist.had_tracks_played = true;
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
        
        console.log("WpsstmTrack:request_sources(): #" + this.track_idx);
        
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
                jQuery(track_el).addClass('buffering');
            },
            success: function(data){
                if (data.success === false) {
                    jQuery(track_el).addClass('error');
                    console.log("error getting sources for track#" + self.track_idx);
                    console.log(data);
                }else{
                    if ( data.new_html ){
                        var sources_list_html = $(data.new_html);
                        self.load_track_sources(sources_list_html);
                    }
                }
            },
            complete: function() {
                self.did_lookup = true;
                jQuery(track_el).addClass('did-source-lookup');
                jQuery(track_el).removeClass('buffering');
            }
        })

    }
    
    load_track_sources(sources_list_html){
        var self =      this;
        var track_el =  this.get_track_el();
        
        console.log("WpsstmTrack:load_track_sources()");
        
        jQuery(track_el).find('.trackitem_sources').html(sources_list_html); //append new sources
        
        var new_sources_items = sources_list_html.find('li');
        console.log("found "+new_sources_items.length +" sources for track#" + this.track_idx);
        
        var sources = [];
        jQuery.each(new_sources_items, function( index, source_html ) {
            var new_source = new WpsstmTrackSource(source_html,self);
            self.sources.push(new_source);
        });
        
        jQuery(track_el).attr('data-wpsstm-sources-count',self.sources.length);
        
    }
    
}

class WpsstmTrackSource {
    constructor(source_html,track) {

        this.source_idx = track.sources.length;
        jQuery(source_html).attr('data-wpsstm-source-idx',this.source_idx);
        
        this.src =    jQuery(source_html).find('a').attr('href');
        this.type =    jQuery(source_html).attr('data-wpsstm-source-type');
        
        //console.log("new WpsstmTrackSource #" + this.source_idx + " in track #" + track.track_idx + " from tracklist track #" + track.tracklist_idx);
    }
}

function wpsstm_end_current_track(){
    
    console.log("wpsstm_end_current_track");
    
    if( wpsstm_current_tracklist_idx === null ) return; //TO FIX

    var playlist_obj = wpsstm_tracklists[wpsstm_current_tracklist_idx];
    
    console.log(wpsstm_current_tracklist_idx);
    
    if( playlist_obj.current_track_idx === null ) return; // TO FIX

    var track_obj =     playlist_obj.tracks[playlist_obj.current_track_idx];
    var track_el    =   track_obj.get_track_el();

    console.log("TO FIX wpsstm_end_current_track() #" + track_obj.track_idx + " in playlist " + track_obj.tracklist_idx);

    $(track_el).removeClass('active');
    $(track_el).addClass('has-played');

    //mediaElement
    if (wpsstm_current_media){
        console.log("there is an active media, abord it");
        wpsstm_current_media.pause();
        track_obj.update_button('ended');

    }

}



