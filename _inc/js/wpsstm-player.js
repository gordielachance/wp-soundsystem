var bottom_block;
var bottom_notice_refresh;
var wpsstm_player;
var wpsstm_current_media;
var wpsstm_countdown_s = wpsstmPlayer.autoredirect; //seconds for the redirection notice
var wpsstm_countdown_timer; //redirection timer
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track

var wpsstm_had_tracks_played = false;

var wpsstm_tracklists = [];
var wpsstm_tracks = [];

var wpsstm_current_track_idx = -1;
var wpsstm_current_tracklist_idx = -1;


(function($){

    $(document).ready(function(){

        bottom_block = $('#wpsstm-bottom');
        bt_prev_track = $('#wpsstm-player-nav-previous-track');
        bt_next_track = $('#wpsstm-player-nav-next-track');
        bottom_notice_refresh = $('#wpsstm-bottom-notice-redirection');
        
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
        timer notice
        */

        bottom_notice_refresh.click(function() {
            
            if ( wpsstm_countdown_s == 0 ) return;
            
            if ( $(this).hasClass('active') ){
                clearInterval(wpsstm_countdown_timer);
            }else{
                wpsstm_redirection_countdown();
            }
            
            $(this).toggleClass('active');
            $(this).find('i.fa').toggleClass('fa-spin');
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
            var track_obj   = wpsstm_tracklists[wpsstm_current_tracklist_idx].tracks[wpsstm_current_track_idx];
            var track_el    = track_obj.get_track_el();
            
            $(track_el).addClass('error');

            //No more sources - Play next song if any
            wpsstm_tracklists[wpsstm_current_tracklist_idx].play_next_track();
        }
        
    }



    
})(jQuery);


class WpsstmTracklist {
    constructor(tracklist_el) {
        
        this.tracklist_idx = wpsstm_tracklists.length;
        
        $(tracklist_el).attr('data-wpsstm-tracklist-idx',this.tracklist_idx);
        
        console.log("new WpsstmTracklist #" + this.tracklist_idx);

        //if(typeof tracklist === 'undefined') return false; //tracklist does not exists
        this.tracklist_id =     Number( jQuery(tracklist_el).attr('data-tracklist-id') );
        this.tracks = new Array();
        this.sources_requests = [];
        
        var self =              this;
        
        
        if ( jQuery(tracklist_el).hasClass('wpsstm-ajaxed-tracklist') ){
            self.get_tracklist_page()
        }
        
        wpsstm_tracklists.push(self);

    }
    
    get_tracklist_page(){
        
        console.log("WpsstmTracklist:get_tracklist_page()");
        
        var tracklist_el = this.get_tracklist_el();
        var self = this;
        
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
                jQuery(tracklist_el).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    jQuery(tracklist_el).addClass('error');
                    console.log(data);
                }else{
                    if ( data.new_html ){

                        var new_tracklist_el = jQuery(data.new_html);
                        jQuery(new_tracklist_el).attr('data-wpsstm-tracklist-idx',self.tracklist_idx);
                        jQuery(tracklist_el).replaceWith(new_tracklist_el);
                        self.load_track_objs();
                    }
                }
            },
            complete: function() {
                tracklist_el.removeClass('loading');
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
        if (!tracklist_el) return;
        
        console.log("WpsstmTracklist:load_track_objs()");
        
        var self = this;
        var tracks_html = jQuery(tracklist_el).find('[itemprop="track"]');

        jQuery.each(tracks_html, function( index, track_html ) {
            var new_track = new WpsstmTrack(track_html,self);
        });
    }
    
    abord_tracks_sources_request() {
        
        console.log("WpsstmTracklist:abord_tracks_sources_request()");
        
        var self = this;
        
        for (var i = 0; i < self.sources_requests.length; i++) {
            self.sources_requests.abort();
        }

        self.sources_requests.length = 0; //TO FIX better to unset it if possible
    };
    
    play_previous_track(){
        var self = this;
        var previous_idx = wpsstm_current_track_idx - 1;
        
        console.log("WpsstmTracklist:play_previous_track() #" + previous_idx + "in playlist#" + self.tracklist_idx);

        if(typeof self.tracks[previous_idx] === 'undefined'){
            console.log("tracklist start");
        }else{
            self.tracks[previous_idx].play_or_skip();
        }
    }

    play_next_track(){

        var self = this;
        var next_idx = wpsstm_current_track_idx + 1;
        
        console.log("WpsstmTracklist:play_next_track() #" + next_idx + "in playlist#" + self.tracklist_idx);

        if(typeof self.tracks[next_idx] === 'undefined'){
            console.log("tracklist end");
            wpsstm_redirection_countdown();
        }else{
            self.tracks[next_idx].play_or_skip();
        }

    }
    
    
}

class WpsstmTrack {
    constructor(track_html,tracklist) {

        var self = this;
        self.tracklist_idx = tracklist.tracklist_idx; //cast to number;
        self.track_idx = tracklist.tracks.length;
        self.artist = jQuery(track_html).find('[itemprop="byArtist"]').text();
        self.title = jQuery(track_html).find('[itemprop="name"]').text();
        self.album = jQuery(track_html).find('[itemprop="inAlbum"]').text();
        self.sources = [];
        //console.log("new WpsstmTrack #" + this.track_idx + " in tracklist #" + this.tracklist_idx);
        
        jQuery(track_html).attr('data-wpsstm-track-idx',this.track_idx);
        tracklist.tracks.push(self);

        //autoplay
        if ( ( wpsstmPlayer.autoplay ) && (self.tracklist_idx === 0) && (self.track_idx === 0) ){
            console.log("autoplay first track");
            self.play_or_skip();
        }
        
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
        jQuery(track_el).addClass('active');

        //is called a second time after tracks sources have been populated.  Do not run this code again.
        if (!after_ajax){
            if ( wpsstm_current_track_idx && ( wpsstm_current_track_idx == self.track_idx ) ) return;
            self.init_sources_request();
        }

        console.log("WpsstmTrack::play_or_skip() tracklist#" + self.tracklist_idx + ", track#" + self.track_idx);
        
        //skip the current track if any
        wpsstm_end_current_track();

        //new track
        wpsstm_current_tracklist_idx = self.tracklist_idx;
        wpsstm_current_track_idx = self.track_idx;

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

        tracklist.abord_tracks_sources_request(); //abord current requests

        var tracks_to_preload = [];
        var max_items = wpsstm_track_source_requests_limit;
        var rtrack_count = 0;

        var tracks_slice = $(tracklist.tracks).slice(self.track_idx,self.track_idx+max_items);

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
            if ( track_to_preload.sources.length < 1 ){
                track_to_preload.build_sources_request();
            }
        });
    }
    
    //http://stackoverflow.com/questions/42271167/break-out-of-ajax-loop
    build_sources_request() {

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
            if (wpsstm_current_track_idx == self.track_idx){
                self.play_or_skip(true);
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
        console.log("fill_player()  tracklist#" + self.tracklist_idx + ", track#" + self.track_idx);

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
        bottom_block.show();

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
                        wpsstm_had_tracks_played = true;
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
                    console.log("error getting sources for track#" + track_idx);
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
        
        console.log("new WpsstmTrackSource #" + this.source_idx + " in track #" + track.track_idx + " from tracklist track #" + track.tracklist_idx);
    }
}

function wpsstm_end_current_track(){

    if (wpsstm_current_track_idx == -1) return;

    console.log("wpsstm_end_current_track() #" + wpsstm_current_track_idx);

    var old_track_obj   = wpsstm_tracklists[wpsstm_current_tracklist_idx].tracks[wpsstm_current_track_idx];
    var old_track_el    = old_track_obj.get_track_el();

    $(old_track_el).removeClass('active');
    $(old_track_el).addClass('has-played');

    //mediaElement
    if (wpsstm_current_media){
        console.log("there is an active media, abord it");

        wpsstm_current_media.pause();
        old_track_obj.update_button('ended');

    }

}

function wpsstm_redirection_countdown(){

    // No tracks have been played on the page.  Avoid infinite redirection loop.
    if ( !wpsstm_had_tracks_played ) return;

    if ( bottom_notice_refresh.length == 0) return;

    var redirect_url = null;
    var redirect_link = bottom_notice_refresh.find('a#wpsstm-bottom-notice-link');

    if (redirect_link.length > 0){
        redirect_url = redirect_link.attr('href');
    }

    bottom_notice_refresh.show();

    var container = bottom_notice_refresh.find('strong');
    var message = "";
    var message_end = "";

    // Get reference to container, and set initial content
    container.html(wpsstm_countdown_s + message);

    if ( wpsstm_countdown_s <= 0) return;

    // Get reference to the interval doing the countdown
    wpsstm_countdown_timer = setInterval(function () {
        container.html(wpsstm_countdown_s + message);
        // If seconds remain
        if (--wpsstm_countdown_s) {
            // Update our container's message
            container.html(wpsstm_countdown_s + message);
        // Otherwise
        } else {
            wpsstm_countdown_s = 0;
            // Clear the countdown interval
            clearInterval(wpsstm_countdown_timer);
            // Update our container's message
            container.html(message_end);

            // And fire the callback passing our container as `this`
            console.log("redirect to:" + redirect_url);
            window.location = redirect_url;
        }
    }, 1000); // Run interval every 1000ms (1 second)
}

