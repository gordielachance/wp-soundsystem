var $ = jQuery.noConflict();

class WpsstmPlayer {
    constructor(id){
        
        this.player_el =                $('#'+id);
        this.trackinfo_el =             undefined;
        this.audio_el =                 undefined;
        this.shuffle_el =               undefined;
        this.loop_el =                  undefined;
        this.current_source =           undefined;
        this.current_track =            undefined;
        this.tracks =                   [];
        this.tracks_shuffle_order =     [];
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );

        ///
        
        var self = this;

        if ( !self.player_el.length ) return;
        this.debug("new Wpsstm player(): #" +id);
        
        ///

        self.trackinfo_el = self.player_el.find('#wpsstm-player-track');
        self.audio_el =     self.player_el.find('#wpsstm-audio-container audio');
        self.shuffle_el =   $('#wpsstm-player-shuffle');
        self.loop_el =      $('#wpsstm-player-loop');

        if (!self.audio_el.length){
            self.debug("no audio element");
            return;
        }
        
        $(document).trigger( "wpsstmPlayerInit",[self] ); //custom event
        
        $(document).on( "wpsstmTracklistInit", function( event, tracklist_obj ) {

            //track popups for player
            self.player_el.on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
                e.preventDefault();

                var content_url = this.href;

                console.log("track popup");
                console.log(content_url);


                var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
                var popup = $('<div></div>').append(loader_el);

                popup.dialog({
                    width:800,
                    height:500,
                    modal: true,
                    dialogClass: 'wpsstm-track-dialog wpsstm-dialog dialog-loading',

                    open: function(ev, ui){
                        var dialog = $(this).closest('.ui-dialog');
                        var dialog_content = dialog.find('.ui-dialog-content');
                        var iframe = $('<iframe src="'+content_url+'"></iframe>');
                        dialog_content.append(iframe);
                        iframe.load(function(){
                            dialog.removeClass('dialog-loading');
                        });
                    },
                    close: function(ev, ui){
                    }

                });

            });
        });
        
        $(document).on( "wpsstmPlayerTrackReady", function( event, track_obj ) {

            //play button
            track_obj.track_el.find('.wpsstm-track-play-bt').click(function(e) {
                e.preventDefault();
                
                //re-click
                if ( self.current_media && (self.current_track == track_obj) ){
                    
                    if ( track_obj.track_el.hasClass('track-playing') ){
                        self.current_media.pause();
                    }else{
                        self.current_media.play();
                    }
                    
                    return;
                }

                self.play_track(track_obj);
                
            });
        });
        
        $(document).on( "wpsstmTrackSingleSourceDomReady", function( event, source_obj ) {

            //play source
            source_obj.source_el.find('.wpsstm-source-title').click(function(e) {
                e.preventDefault();
                self.play_source(source_obj);
                //toggle tracklist sources
                source_obj.track.track_el.removeClass('wpsstm-sources-expanded');
            });

        });

        $(document).on("wpsstmStartTracklist", function( event, tracklist_obj ) {
            if ( tracklist_obj.isExpired ){
                tracklist_obj.debug("cache expired, refresh tracklist");

                self.end_track();
                self.current_track = undefined; //unset current player track so the player won't try to go to the next track
                var reloaded = self.reload_tracklist(tracklist_obj);

                reloaded.done(function(v) {
                    self.debug("restart tracklist");

                    var tracklist_tracks = self.tracks.filter(function (track_obj) {
                        return (track_obj.tracklist.index === tracklist_obj.index);
                    });
                    
                    //play first track of this tracklist
                    var first_track = tracklist_tracks[0];
                    if (first_track){
                        self.play_track(first_track);
                    }
                    
                })
                
            }
        });
        
        self.player_el.find('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            self.previous_track_jump();
        });
        
        self.player_el.find('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            self.next_track_jump();
        });

        /*
        Scroll to playlist track when clicking the player's track number
        */
        self.player_el.find('.wpsstm-track-position').click(function(e) {
            e.preventDefault();
            
            var track_obj = self.current_track;

            var track_el = track_obj.track_el;
            var newTracksCount = track_obj.position + 1;

            //https://stackoverflow.com/a/6677069/782013
            //TOUFIX BROKEN
            $('html, body').animate({
                scrollTop: track_el.offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });
        
        /*
        Shuffle button
        */
        if ( self.is_shuffle ){
            self.shuffle_el.addClass('active');
        }

        self.shuffle_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !self.is_shuffle;
            self.is_shuffle = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-shuffle", true);
                self.shuffle_el.addClass('active');
            }else{
                localStorage.removeItem("wpsstm-player-shuffle");
                self.shuffle_el.removeClass('active');
            }            

        });

        /*
        Loop button
        */
        if ( self.can_repeat ){
            self.loop_el.addClass('active');
        }

        self.loop_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !self.can_repeat;
            self.can_repeat = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-loop", true);
                self.loop_el.addClass('active');
            }else{
                localStorage.setItem("wpsstm-player-loop", false);
                self.loop_el.removeClass('active');
            }
            
            //update previous track bt
            var prev_track = self.get_previous_track();
            var has_prev_track = (prev_track!==undefined);
            var prevTrackEl = self.player_el.find('#wpsstm-player-extra-previous-track');
            prevTrackEl.toggleClass('active',has_prev_track);
            
            //update next track bt
            var next_track = self.get_next_track();
            var has_next_track = (next_track!==undefined);
            var nextTrackEl = self.player_el.find('#wpsstm-player-extra-next-track');
            nextTrackEl.toggleClass('active',has_next_track);

        });

        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */
        $(window).bind('beforeunload', function(){

            if (self.current_media && !self.current_media.paused){
                return wpsstmPlayer.leave_page_text;
            }

        });
        
        self.audio_el.mediaelementplayer({
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug:          wpsstmL10n.debug,
                autoStartLoad:  true
            },
            pluginPath: wpsstmPlayer.plugin_path, //'https://cdnjs.com/libraries/mediaelement/'
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(mediaElement, originalNode, player) {
                self.current_media = mediaElement;
                self.debug("MediaElementJS ready");
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
                /*
                source_instances.addClass('source-error');
                source_instances.removeClass('source-active');
                */
            }
        });
    }
    
    debug(msg){
        var prefix = "WpsstmPlayer";
        wpsstm_debug(msg,prefix);
    }
    
    reload_tracklist(tracklist_obj){
        var self = this;
        var success = $.Deferred();
        
        self.debug("reload tracklist #" + tracklist_obj.index);
        var iframe = $(parent.document).find('iframe.wpsstm-tracklist-iframe').get(tracklist_obj.index);
        var container = $(iframe).parents('.wpsstm-iframe-container');
        container.addClass('wpsstm-iframe-loading');
        var old_tracks_count = self.tracks.length;
        
        //select old (tracklist) tracks - to remove
        var subtracks = self.tracks.filter(function (track_obj) {
            return (track_obj.tracklist.index === tracklist_obj.index);
        });

        //get index of first (tracklist) track within player tracks
        var first_track_idx = $(self.tracks).index( $(subtracks[0]) );

        //build new player queue
        var tracks_before = self.tracks.slice(0,first_track_idx);
        var tracks_after = self.tracks.slice(first_track_idx + subtracks.length);

        iframe.contentWindow.location.reload(true);

        /*
        reload tracklist at refresh (fires only once)
        */
        $(iframe).one( "load", function() {
            
            $(iframe).parents('.wpsstm-iframe-container').removeClass('wpsstm-iframe-loading');
            var content = $(iframe.contentWindow.document.body);
            var playlist_html = $(content).find( ".wpsstm-tracklist" ).get(0);
            tracklist_obj.populate_html(playlist_html);
            
            /*
            update player tracks
            */

            var new_queue = tracks_before.concat(tracklist_obj.tracks,tracks_after);

            self.debug('was '+old_tracks_count+' tracks, removed tracks:' + subtracks.length +', new tracks:'+tracklist_obj.tracks.length+', new total:' + new_queue.length);
            
            self.append_tracks(new_queue,true);
            
            success.resolve();
            
        });

        success.done(function(v) {
            self.debug("reload successful");
        })
        
        return success.promise();
        
    }

    play_track(track_obj){

        var self = this;
        var success = $.Deferred();
        
        if (track_obj === undefined){
            success.reject('track is undefined');
            return success.promise();
        }
        
        //check if this is the first (playable) tracklist track in queue
        var tracklist_tracks = self.tracks.filter(function (all_tracks_obj) {
            return (all_tracks_obj.can_play !== false) && (all_tracks_obj.tracklist.index === track_obj.tracklist.index);
        });
        var first_tracklist_track = tracklist_tracks[0];

        if ( $(track_obj).is( $(first_tracklist_track) ) ){
            track_obj.tracklist.debug("wpsstmStartTracklist");
            $(document).trigger( "wpsstmStartTracklist",[track_obj.tracklist] );
        }
        
        track_obj.track_el.addClass('track-active track-loading');

        /*
        set current track
        */
        if ( !$(track_obj).is( $(self.current_track) ) ){
            self.end_track();
            self.current_track = track_obj;
            self.track_to_player();
        }
        
        ///

        track_obj.maybe_load_sources().then(
            function(success_msg){

                var source_play = self.play_first_available_source(track_obj);

                source_play.done(function(v) {
                    success.resolve();
                })
                source_play.fail(function(reason) {
                    success.reject(reason);
                })

            },
            function(error_msg){
                success.reject(error_msg);
            }
        );


        success.done(function(v) { //fetch sources for next tracks
            self.maybe_load_queue_sources();
        })

        success.fail(function() {
            track_obj.can_play = false;
            track_obj.track_el.addClass('track-error');
            track_obj.track_el.removeClass('track-active');
            self.next_track_jump();
        })
        
        success.always(function() {
            track_obj.track_el.removeClass('track-loading');
        })

        return success.promise();

    }
    
    play_first_available_source(track_obj,source_idx){
        
        var self = this;
        var success = $.Deferred();

        source_idx = ( source_idx !== undefined )  ? source_idx : 0;

        /*
        This function will loop until a promise is resolved
        */
        
        var sources_after = track_obj.sources.slice(source_idx); //including this one
        var sources_before = track_obj.sources.slice(0,source_idx - 1);

        //which one should we play?
        var sources_reordered = sources_after.concat(sources_before);
        var sources_playable = sources_reordered.filter(function (source_obj) {
            return (source_obj.can_play !== false);
        });

        if (!sources_playable.length){
            success.reject("no playable sources to iterate");
        }else{
            (function iterateSources(index) {

                if (index >= sources_playable.length) {
                    success.reject();
                    return;
                }

                var source_obj = sources_playable[index];
                var sourceplay = self.play_source(source_obj);

                sourceplay.done(function(v) {
                    success.resolve();
                })
                sourceplay.fail(function() {
                    iterateSources(index + 1);
                })


            })(0);
        }
        
        return success.promise();
        
    }
    
    play_source(source_obj){

        var self = this;
        var success = $.Deferred();
        
        var previous_source = self.current_source;
        self.current_source = source_obj;
        
        var previous_track = self.current_track;
        var track_obj = source_obj.track;
        self.current_track = track_obj;
        
        var track_el = $([]);
        track_el.push(track_obj.track_el.get(0) );
        track_el.push( self.trackinfo_el.find('.wpsstm-track').get(0) );
        var tracklist_el = track_el.parents('.wpsstm-tracklist');
        var source_el = track_el.find('[data-wpsstm-source-idx='+source_obj.index+']');
        
        /*
        handle current (previous) source
        */
        //we're trying to play the same source again
        if ( $(source_obj).is( $(previous_source) ) ){ 
            success.reject("we've already playing this soure");
            return success.promise();
        }
        
        if (previous_source){ //a source is currently playing
            self.end_source(previous_source);
        }

        source_obj.debug("play source: " + source_obj.src);
        source_el.addClass('source-active source-loading');
        track_el.addClass('track-active track-loading');
        tracklist_el.addClass('tracklist-active tracklist-loading');
        
        /*
        display current track
        */
        if ( !$(track_obj).is( $(previous_track) ) ){
            self.track_to_player();
        }

        //hide sources if it is expanded //TOUFIX not working
        var toggleEl = track_obj.track_el.find('.wpsstm-track-action-toggle-sources a');
        if ( toggleEl.hasClass('.active') ){
            toggleEl.click();
        }

        /*
        register new events
        */
        
        $(self.current_media).off(); //remove old events
        $(document).trigger( "wpsstmSourceInit",[self,source_obj] );

        $(self.current_media).on('loadeddata', function() {
            $(document).trigger( "wpsstmSourceLoaded",[self,source_obj] ); //custom event
            self.debug('source loaded');
            source_obj.duration = self.current_media.duration;
            self.current_media.play();
        });

        $(self.current_media).on('error', function(error) {
            self.debug('media - error');
            success.reject(error);
        });

        $(self.current_media).on('play', function() {
            //self.debug('media - play');
            success.resolve();
            
            source_el.addClass('source-playing source-has-played');
            tracklist_el.addClass('tracklist-playing tracklist-has-played');
            track_el.addClass('track-playing track-has-played');

        });

        $(self.current_media).on('pause', function() {
            //self.debug('player - pause');

            //tracklists
            tracklist_el.removeClass('tracklist-playing');
            //tracks
            track_el.removeClass('track-playing');
            //sources
            source_el.removeClass('source-playing');
        });

        $(self.current_media).on('ended', function() {

            self.debug('media - ended');
            
            //tracklists
            tracklist_el.removeClass('tracklist-playing');
            //tracks
            track_el.removeClass('track-active track-playing');
            //sources
            source_el.removeClass('source-playing source-active');

            //Play next song if any
            self.next_track_jump();
        });
        
        success.always(function(data, textStatus, jqXHR) {
            source_el.removeClass('source-loading');
            track_el.removeClass('track-loading');
        })
        success.done(function(v) {
            source_obj.can_play = true;
            tracklist_el.removeClass('tracklist-loading');
            track_el.removeClass('track-error track-loading');
            
        })
        success.fail(function() {
            source_obj.can_play = false;
            //sources
            source_el.removeClass('source-active');
            source_el.addClass('source-error');
            //tracks
            track_el.removeClass('track-active');
            track_el.addClass('track-error');
        })

        ////
        self.current_media.setSrc(source_obj.src);
        self.current_media.load();
        
        ////

        return success.promise();

    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    maybe_load_queue_sources() {

        var self = this;

        var max_items = 4; //number of following tracks to preload
        var rtrack_in = self.current_track.index + 1;
        var rtrack_out = self.current_track.index + max_items + 1;

        var tracks_slice = $(self.tracks).slice( rtrack_in, rtrack_out );

        $(tracks_slice).each(function(index, track_to_preload) {
            if ( track_to_preload.sources.length > 0 ) return true; //continue;
            track_to_preload.maybe_load_sources();
        });
    }
    
    get_previous_track(){
        var self = this;

        var current_track_idx = $(self.tracks).index( $(self.current_track) );
        if (current_track_idx == -1) return; //index not found

        var tracks = self.get_ordered_tracks();
        var tracks_before = tracks.slice(0,current_track_idx).reverse();
        
        if (!tracks_before.length){
            if (self.can_repeat){
                tracks_before = tracks;
            }
        }

        //which one should we play?
        var tracks_playable = tracks_before.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        
        var previous_track = tracks_playable[0];
        var previous_track_idx = ( previous_track ) ? $(self.tracks).index( previous_track ) : undefined;
        
        return previous_track;
    }
    
    previous_track_jump(){
        
        var self = this;
        
        var track_obj = self.get_previous_track();

        if (track_obj){
            self.play_track(track_obj);
        }
    }
    
    get_next_track(){
        var self = this;

        var current_track_idx = $(self.tracks).index( $(self.current_track) );
        if (current_track_idx == -1) return; //index not found
        
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx); // shuffle ?

        var tracks = self.get_ordered_tracks();
        var tracks_after = tracks.slice(current_track_idx+1);
        
        if (!tracks_after.length){
            if (self.can_repeat){
                tracks_after = tracks;
            }
        }

        //which one should we play?
        var tracks_playable = tracks_after.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        
        var next_track = tracks_playable[0];
        var next_track_idx = ( next_track ) ? $(self.tracks).index( next_track ) : undefined;

        //console.log("get_next_track: current: " + current_track_idx + ", next;" + next_track_idx);

        return next_track;
    }
    
    next_track_jump(){
        var self = this;

        var track_obj = self.get_next_track();

        if (track_obj){
            self.play_track(track_obj);
        }else{
            console.log("no next track");
            self.end_track();
        }

    }

    /*
    Return the tracks; in the shuffled order if is_shuffle is true.
    */
    get_ordered_tracks(){
        
        self = this;

        if ( !this.is_shuffle ){
            return self.tracks;
        }else{
            
            var shuffled_tracks = [];

            $(self.tracks_shuffle_order).each(function() {
                var idx = this;
                shuffled_tracks.push(self.tracks[idx]);
            });

            return shuffled_tracks;
        }
        
    }
    
    append_tracks(new_tracks,reset){
        var self = this;
        
        if (reset === undefined){
            self.tracks = self.tracks.concat(new_tracks); 
        }else{
            self.tracks = new_tracks;
        }

        //self.debug('added tracks: ' + new_tracks.length +', total:' + self.tracks.length);
        
        //set the shuffle order
        self.tracks_shuffle_order = wpsstm_shuffle( Object.keys(self.tracks).map(Number) );
 
    }
    
    autoplay(){
        var self = this;
        var has_autoplay = self.audio_el.get(0).autoplay;
        var is_media_playing = ( (self.current_media !==undefined) && self.current_media.onplaying );
        
        if (has_autoplay && !is_media_playing){
            var first_track_idx = self.get_maybe_unshuffle_track_idx(0);
            var first_track = self.tracks[first_track_idx];
            self.play_track(first_track);
        }
    }

    get_maybe_shuffle_track_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var new_idx = self.tracks_shuffle_order[idx];
        
        //self.debug("get_maybe_shuffle_track_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    get_maybe_unshuffle_track_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var shuffle_order = self.tracks_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        
        //self.debug("get_maybe_unshuffle_track_idx : " + idx + "-->" + new_idx);
        return new_idx;
    }

    track_to_player(){

        var self = this;

        self.current_track.debug("track > player");

        //audio sources
        self.set_player_sources(self.current_track.sources);
        
        /*
        track infos
        */
        var tracklist_el = self.current_track.tracklist.tracklist_el;

        //copy attributes from the original playlist 
        var attributes = $(tracklist_el).prop("attributes");
        $.each(attributes, function() {
            self.trackinfo_el.attr(this.name, this.value);
        });
        
        //switch type
        self.trackinfo_el.removeClass('wpsstm-post-tracklist');
        self.trackinfo_el.addClass('wpsstm-player-tracklist');

        var list = $('<ul class="wpsstm-tracks-list" />'); 

        var row = self.current_track.track_el.clone(true,true);
        row.find('.wpsstm-track-sources').removeClass('wpsstm-sources-expanded');

        $(list).append(row);

        self.trackinfo_el.html(list);
        
        ///
        
        /*
        Previous track bt
        */

        var previous_track = self.get_previous_track();
        var has_previous_track = (previous_track!==undefined);
        var previousTrackEl = self.player_el.find('#wpsstm-player-extra-previous-track');

        previousTrackEl.toggleClass('active',has_previous_track);

        /*
        Next track bt
        */
        var next_track = self.get_next_track();
        var has_next_track = (next_track!==undefined);
        var nextTrackEl = self.player_el.find('#wpsstm-player-extra-next-track');
        nextTrackEl.toggleClass('active',has_next_track);
        
        ///
        
        self.player_el.show();//show in not done yet

    }
    
    set_player_sources(sources){
        
        var self = this;

        var old_sources = self.audio_el.find('source');
        
        //remove old sources
        old_sources.each(function(i) {
            $(this).remove();
        });
        
        if ( sources === undefined) return;
        
        //append new sources
        var new_sources = [];
        $( sources ).each(function(i, source_attr) {
            //create source element
            var source_el = $('<source />');
            source_el.attr({
                src:    source_attr.src,
                type:   source_attr.type
            });
            new_sources.push(source_el);
        });
        self.audio_el.append(new_sources);
    }
    
    end_track(track_obj){
        var self = this;

        if (!track_obj) track_obj = self.current_track;
        if (!track_obj) return;
        track_obj.debug("end_track");
        

        var track_instances = track_obj.track_el;
        
        track_instances.removeClass('track-loading track-active track-playing');
        self.player_el.find('[itemprop="track"]').removeClass('track-loading track-active track-playing');

        self.end_source();
        
        self.current_track = undefined;

    }
    
    end_source(source_obj){

        var self = this;

        if (!source_obj) source_obj = self.current_source;
        if (!source_obj) return;

        source_obj.debug("end_source");
        
        self.current_media.pause();

        //TOUFIX TO CHECK should be hookend on events ?
        //tracklists
        var tracklist_instances = source_obj.track.track_el.parents('.wpsstm-tracklist');

        //sources
        var sources_instances = source_obj.track.track_el.find('[data-wpsstm-source-idx='+source_obj.index+']');

        sources_instances.removeClass('source-playing source-active source-loading');
        
        
        self.current_source = undefined;
        
    }

}

$( document ).ready(function() {

    var iframes = $('iframe.wpsstm-tracklist-iframe');

    /*
    wait for all iframes before initializing player
    */

    var iframesLoaded = $.Deferred();
    var loadedIFramesCount = 0;

    iframes.one( "load", function() {

        ++loadedIFramesCount;//increment
        var iframe_el = $(this).get(0);

        var content = $(iframe_el.contentWindow.document.body);

        //all frames are loaded
        if ( loadedIFramesCount == iframes.length ){
            iframesLoaded.resolve();
        }

    });

    /*
    init player
    */
    var bottomPlayer = new WpsstmPlayer('wpsstm-bottom-player');
    iframesLoaded.done(function(v) {
        bottomPlayer.debug('all iframes have been loaded, init player');

        //sort tracklists by tracklist index
        function compare_tracklist_idx(a,b) {
            if (a.index > b.index) return 1;
            if (b.index > a.index) return -1;
            return 0;
        }

        wpsstm.tracklists.sort(compare_tracklist_idx);

        var allTracks = [];
        $(wpsstm.tracklists).each(function(index,tracklist) {
            allTracks = allTracks.concat(tracklist.tracks);
        });

        bottomPlayer.append_tracks(allTracks);
        bottomPlayer.autoplay();

    });

});
