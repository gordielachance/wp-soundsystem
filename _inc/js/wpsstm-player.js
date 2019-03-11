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
        this.debug("CREATE player: #" +id);
        
        ///

        self.trackinfo_el = self.player_el.find('#wpsstm-player-track');
        self.audio_el =     self.player_el.find('#wpsstm-audio-container audio');
        self.shuffle_el =   $('#wpsstm-player-shuffle');
        self.loop_el =      $('#wpsstm-player-loop');

        if (!self.audio_el.length){
            self.debug("no audio element");
            return;
        }
        
        //toggle queue
        self.player_el.find('.wpsstm-player-action-queue a').click(function(e) {
            e.preventDefault();
            $(this).toggleClass('active');
            self.player_el.find('.player-queue').toggleClass('active');
        });
        
        //previous
        self.player_el.find('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            self.previous_track_jump();
        });
        
        //next
        self.player_el.find('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            self.next_track_jump();
        });

        /*
        Scroll to playlist track when clicking the player's track number
        */
        self.player_el.find('.wpsstm-track-position').click(function(e) {
            e.preventDefault();
            
            var track = self.current_track;
            var newTracksCount = track.position + 1;

            //https://stackoverflow.com/a/6677069/782013
            //TOUFIX BROKEN
            $('html, body').animate({
                scrollTop: $(track).offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });
        
        /*
        Track popups for player
        TOUFIX TOUCHECK
        
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
        */
        
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
        
        $(document).trigger( "wpsstmPlayerInit",[self] ); //custom event
        
    }
    
    debug(msg){
        var prefix = "WpsstmPlayer";
        wpsstm_debug(msg,prefix);
    }

    unQueueContainer(tracklist){
        var self = this;

        /*
        Stop current track if it is part of this tracklist
        */
        if ( self.current_track && $(tracklist).find($(self.current_track)).length ){
            self.debug("current track is being unqueued, stop it");
            self.end_track(self.current_track);
        }

        /*
        Keep only tracks that do not belong to the current tracklist
        */
        var cleanedTracks = self.tracks.filter(function( track ) {
            var track_idx = $(self.tracks).index( $(track) );
            return (track_idx === -1);
          })
        
        var newTrackCount = cleanedTracks.length;
        var oldTrackCount = self.tracks.length;
        var removedTrackCount = oldTrackCount - newTrackCount;

        tracklist.debug( 'remove tracks from #' + self.player_el.attr('id') );
        self.debug("unQueued " + removedTrackCount + " tracks, still in queue: " + newTrackCount);
        
        self.tracks = cleanedTracks;
        self.queueUpdateGUI();
    }
    
    queueContainer(tracklist){
        var self = this;
        var newTracks = [];

        $(tracklist.tracks).each(function(index, track_obj) {
            newTracks.push(track_obj);
        });
        
        tracklist.debug( 'append tracks to #' + self.player_el.attr('id') );
        self.debug("Queued tracks: " + newTracks.length );
        
        $.merge(self.tracks,newTracks);
        self.queueUpdateGUI();

        /* autoplay ? */
        if ( newTracks.length && $(tracklist).hasClass('tracklist-autoplay') ){
            $(tracklist).removeClass('tracklist-autoplay');
            var firstTrack = tracklist.tracks[0];
            if(typeof firstTrack !== undefined){
                tracklist.debug("AUTOPLAY!");
                self.play_track(firstTrack);
            }else{
                $(tracklist).removeClass('tracklist-playing');
            }
        }
        
        //play/pause track button
        $(tracklist).on( "click", ".wpsstm-track-play-bt", function(e) {
            e.preventDefault();
            
            var track = $(this).parents('wpsstm-track').get(0);

            //re-click
            if ( self.current_media && (self.current_track == track) ){

                if ( $(track).hasClass('track-playing') ){
                    self.current_media.pause();
                }else{
                    self.current_media.play();
                }

                return;
            }
            
            self.play_track(track);

        });
        
        //play source
        self.player_el.on('click', '.wpsstm-source-title', function(e) {
            e.preventDefault();
            var source = $(this).get(0);
            var track = $(this).parents('wpsstm-track').get(0);
            
            self.play_source(source);
            //toggle tracklist sources
            $(track).removeClass('wpsstm-sources-expanded');
        });
        
    }
    
    queueUpdateGUI(){
        var self = this;
        var queueEl = self.player_el.find('.player-queue');
        
        queueEl.empty();

        $(self.tracks).each(function(index, track) {
            var el = $(track).clone(true,true);
            self.player_el.find('.player-queue').append(el);
        });

        //show in not done yet
        var showPlayer = ( $(self.tracks).length > 0);
        self.player_el.toggle(showPlayer);
    }

    play_track(track){

        var self = this;
        var success = $.Deferred();
        var track_instances = track.get_instances();

        track_instances.addClass('track-active track-loading');

        if ( self.current_track && ( track !== self.current_track ) ){
            self.end_track();
        }
        self.current_track = track;
        self.render_playing_track();
        
        /*
        If this is the first tracklist track, check if tracklist is expired.
        */
        var tracklist = track.tracklist;
        var track_index = $(tracklist).find('wpsstm-track').index( track );
        if ( (track_index === 0) && tracklist.isExpired ){
            tracklist.debug("First track requested but tracklist is expired,reload it!");
            tracklist.reload_tracklist(true);
            return;
        }
        

        ///

        track.maybe_load_sources().then(
            function(success_msg){

                var source_play = self.play_first_available_source(track);

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


        success.done(function(v) {
            
            /*
            preload sources for the X next tracks
            */
            
            
            var max_items = 4; //number of following tracks to preload
            var track_index = $(self.tracks).index( track );
            if (track_index < 0) return; //index not found

            //keep only tracks after this one
            var rtrack_in = track_index + 1;
            var next_tracks = $(self.tracks).slice( rtrack_in );
            
            //remove tracks that have already been autosourced
            var next_tracks = next_tracks.filter(function (track) {
                return (track.did_sources_request !== false);
            });
            
            //reduce to X tracks
            var tracks_slice = next_tracks.slice( 0, max_items );

            $(tracks_slice).each(function(index, track_to_preload) {
                if ( track_to_preload.sources.length > 0 ) return true; //continue;
                track_to_preload.maybe_load_sources();
            });
            
        })

        success.fail(function() {
            track.can_play = false;
            track_instances.addClass('track-error');
            track_instances.removeClass('track-active');
            self.next_track_jump();
        })
        
        success.always(function() {
            track_instances.removeClass('track-loading');
        })

        return success.promise();

    }
    
    play_first_available_source(track){
        
        var self = this;
        var success = $.Deferred();

        var source_idx = 0;

        /*
        This function will loop until a promise is resolved
        */
        
        var sources_after = track.sources.slice(source_idx); //including this one
        var sources_before = track.sources.slice(0,source_idx - 1);

        //which one should we play?
        var sources_reordered = sources_after.concat(sources_before);
        var sources_playable = sources_reordered.filter(function (source) {
            return (source.can_play !== false);
        });

        if (!sources_playable.length){
            success.reject("no playable sources to iterate");
        }else{
            (function iterateSources(index) {

                if (index >= sources_playable.length) {
                    success.reject();
                    return;
                }

                var source = sources_playable[index];
                var sourceplay = self.play_source(source);

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
    
    play_source(source){

        var self = this;
        var success = $.Deferred();
        
        var previous_source = self.current_source;
        var previous_track = self.current_track;
        
        self.current_source = source;
        self.current_track = source.track;
        var tracklist = self.current_track.tracklist;
        
        var tracklist_instances = tracklist.get_instances();
        var track_instances = self.current_track.get_instances();
        var source_instances = source.get_instances();
        
        /*
        handle current (previous) source
        */
        //we're trying to play the same source again
        if ( source === previous_source ){ 
            success.reject("we've already playing this soure");
            return success.promise();
        }
        
        if (previous_source){ //a source is currently playing
            self.end_source(previous_source);
        }

        source.debug("play source: " + source.src);
        source_instances.addClass('source-active source-loading');
        track_instances.addClass('track-active track-loading');
        tracklist_instances.addClass('tracklist-active tracklist-loading');
        
        /*
        display current track
        */
        if ( self.current_track !== previous_track ){
            self.render_playing_track();
        }

        //hide sources if it is expanded //TOUFIX not working
        var toggleEl = $(self.current_track).find('.wpsstm-track-action-toggle-sources a');
        if ( toggleEl.hasClass('.active') ){
            toggleEl.click();
        }

        /*
        register new events
        */
        
        $(self.current_media).off(); //remove old events
        $(document).trigger( "wpsstmSourceInit",[self,source] );

        $(self.current_media).on('loadeddata', function() {
            $(document).trigger( "wpsstmSourceLoaded",[self,source] ); //custom event
            self.debug('source loaded');
            source.duration = self.current_media.duration;
            self.current_media.play();
        });

        $(self.current_media).on('error', function(error) {
            self.debug('media - error');
            success.reject(error);
        });

        $(self.current_media).on('play', function() {
            //self.debug('media - play');
            success.resolve();
            
            source_instances.addClass('source-playing source-has-played');
            tracklist_instances.addClass('tracklist-playing tracklist-has-played');
            track_instances.addClass('track-playing track-has-played');

        });

        $(self.current_media).on('pause', function() {
            //self.debug('player - pause');

            //tracklists
            tracklist_instances.removeClass('tracklist-playing');
            //tracks
            track_instances.removeClass('track-playing');
            //sources
            source_instances.removeClass('source-playing');
        });

        $(self.current_media).on('ended', function() {

            self.debug('media - ended');
            
            //tracklists
            tracklist_instances.removeClass('tracklist-playing');
            //tracks
            track_instances.removeClass('track-active track-playing');
            //sources
            source_instances.removeClass('source-playing source-active');

            //Play next song if any
            self.next_track_jump();
        });
        
        success.always(function(data, textStatus, jqXHR) {
            source_instances.removeClass('source-loading');
            track_instances.removeClass('track-loading');
        })
        success.done(function(v) {
            source.can_play = true;
            tracklist_instances.removeClass('tracklist-loading');
            track_instances.removeClass('track-error track-loading');
            
        })
        success.fail(function() {
            source.can_play = false;
            //sources
            source_instances.removeClass('source-active').addClass('source-error');
            //tracks
            track_instances.removeClass('track-active').addClass('track-error');
        })

        ////
        self.current_media.setSrc(source.src);
        self.current_media.load();
        
        ////

        return success.promise();

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
        
        var tracks_unplayable = tracks_after.filter(function (track_obj) {
            return (track_obj.can_play === false);
        });
        
        
        
        
        var next_track = tracks_playable[0];
        var next_track_idx = ( next_track ) ? $(self.tracks).index( next_track ) : undefined;

        //console.log("get_next_track: current: " + current_track_idx + ", next;" + next_track_idx);

        return next_track;
    }
    
    previous_track_jump(){
        
        var self = this;
        
        var track = self.get_previous_track();

        if (track){
            
            var tracklist = track.tracklist;
            
            /*
            check if we need to reload the tracklist
            */
            if (tracklist.isExpired){
                var current_track_idx = $(tracklist).find('wpsstm-track').index( $(self.current_track) );
                var new_track_idx = $(tracklist).find('wpsstm-track').index( track );

                if (new_track_idx > current_track_idx){
                    tracklist.debug("tracklist backward loop and it is expired, refresh it!");
                    self.end_track();
                    tracklist.reload_tracklist(true);
                    return;
                }
            }


            self.play_track(track);
        }else{
            self.debug("no previous track");
            self.end_track();
        }
    }
    
    next_track_jump(){
        var self = this;

        var track = self.get_next_track();

        if (track){
            
            var tracklist = track.tracklist;
            
            /*
            check if we need to reload the tracklist
            */
            if (tracklist.isExpired){
                var current_track_idx = $(tracklist).find('wpsstm-track').index( $(self.current_track) );
                var new_track_idx = $(tracklist).find('wpsstm-track').index( track );

                if (new_track_idx < current_track_idx){
                    tracklist.debug("tracklist forward loop and it is expired, refresh it!");
                    self.end_track();
                    tracklist.reload_tracklist(true);
                    return;
                }
            }


            self.play_track(track);
        }else{
            self.debug("no next track");
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

    render_playing_track(){

        var self = this;

        if (!self.current_track) return;
        self.current_track.debug("track > player");

        //audio sources
        self.set_player_sources(self.current_track.sources);

        var list = $('<ul class="wpsstm-tracks-list" />'); 

        var row = $(self.current_track).clone(true,true);
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
    
    end_track(track){
        var self = this;

        if (!track) track = self.current_track;
        if (!track) return;
        track.debug("end_track");

        track.get_instances().removeClass('track-loading track-active track-playing');

        self.end_source();
        self.current_track = undefined;

    }
    
    end_source(source){

        var self = this;

        if (!source) source = self.current_source;
        if (!source) return;

        source.debug("end_source");
        
        self.current_media.pause();

        //TOUFIX TO CHECK should be hookend on events ?

        //sources
        $(source).removeClass('source-playing source-active source-loading');
        
        
        self.current_source = undefined;
        
    }

}
