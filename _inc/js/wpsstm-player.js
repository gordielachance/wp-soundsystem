class WpsstmPlayer {
    constructor(player_el){
        this.debug("new WpsstmPlayer()");
        this.player_el =                undefined;
        this.trackinfo_el =             undefined;
        this.audio_el =                 undefined;
        this.shuffle_el =               undefined;
        this.loop_el =                  undefined;
        this.current_source =           undefined;
        this.current_track =            undefined;
        this.requested_track =          undefined;
        this.tracks =                   [];
        this.tracklists =               [];
        this.tracks_shuffle_order =     [];
        this.tracklists_shuffle_order = [];
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
        this.tracks_el =                $([]); //should be filled by both tracklist track & player track
        ///
        
        var self = this;

        if ( (player_el === undefined) || !player_el.length ){
            self.debug("no player element");
            return;
            
        }

        self.player_el =    $(player_el);
        self.trackinfo_el = $(player_el).find('#wpsstm-player-track');
        self.audio_el =     $(player_el).find('#wpsstm-audio-container audio');
        self.shuffle_el =   $('#wpsstm-player-shuffle');
        self.loop_el =      $('#wpsstm-player-loop');
        
        self.tracks_el.push(self.trackinfo_el);
        
        if (!self.audio_el.length){
            self.debug("no audio element");
            return;
        }
        
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
                self.init_player();
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
                /*
                var source_instances = self.get_source_instances();
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
    
    init_player(){
        
        var self = this;
        console.log("init_player");
        
        $(document).on( "wpsstmTrackDomReady", function( event, track_obj ) {

            //play button
            track_obj.track_el.find('.wpsstm-track-icon').click(function(e) {
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

                track_obj.playPromise.done(function(v) { //fetch sources for next tracks
                    self.maybe_load_queue_sources();
                })
                
                track_obj.playPromise.fail(function() {
                    track_obj.can_play = false;
                    self.tracks_el.addClass('track-error');
                })
                
                self.play_track(track_obj);
                track_obj.track_el.addClass('track-active track-loading');

                
                $(document).trigger( "wpsstmRequestPlay",track_obj ); //custom event


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

        /*
        Previous track bt
        */

        self.player_el.find('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            self.previous_track_jump();
        });

        /*
        Next track bt
        */
        self.player_el.find('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            self.next_track_jump();
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

        });
        
        /*
        Scroll to playlist track when clicking the player's track number
        */
        self.player_el.on( "click",'[itemprop="track"] .wpsstm-track-position', function(e) {
            e.preventDefault();
            var player_track_el = $(this).parents('[itemprop="track"]');
            var track_idx = Number(player_track_el.attr('data-wpsstm-track-idx'));

            var tracklist_el = player_track_el.closest('[data-wpsstm-tracklist-idx]');
            var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));
            var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;

            var track_obj = self.current_track;

            var track_el = track_obj.track_el;
            var newTracksCount = track_obj.index + 1;

            //https://stackoverflow.com/a/6677069/782013
            $('html, body').animate({
                scrollTop: track_el.offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });
        
        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */
        $(window).bind('beforeunload', function(){

            if (tracklist_obj.targetPlayer.current_media && !tracklist_obj.targetPlayer.current_media.paused){
                return wpsstmPlayer.leave_page_text;
            }

        });

    }
    
    start_player(){
        var self = this;
        /*
        autoplay ?
        */
        
        console.log("start_player");

        self.shuffle_order = wpsstm_shuffle( Object.keys(self.tracks).map(Number) );

        if ( (self.current_media !==undefined) && !self.current_media.onplaying ){ //is not playing //TOUFIX TOCHECK this statement is not working
            var first_track_idx = self.get_maybe_unshuffle_track_idx(0);
            var first_track = self.tracks[first_track_idx];
            
            if ( first_track ){
                console.log("autoplay track#" + first_track.index);
                self.play_track(first_track);
            }
        }
    }
    
    play_track(track_obj){

        var self = this;
        var success = $.Deferred();
        
        self.track_to_player(track_obj);

        track_obj.maybe_load_sources().then(
            function(success_msg){

                var source_play = self.play_first_available_source(track_obj);

                source_play.done(function(v) { //fetch sources for next tracks
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
        
        return success.promise();

    }
    play_first_available_source(track_obj,source_idx){
        
        var self = this;
        
        //is a track init!
        if ( source_idx === undefined ){
            source_idx = 0;
        }

        var success = $.Deferred();
        
        
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
        }

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
        
        return success.promise();
        
    }
    
    play_source(source_obj){

        var self = this;
        var success = $.Deferred();
        
        //tracklists
        var tracklist_instances = self.tracks_el.parents('.wpsstm-tracklist');
        //tracks
        var tracks_instances = self.tracks_el;
        //sources
        self.tracks_el.find('[data-wpsstm-source-idx]').addClass('source-loading');
        
        var source_previous = self.current_source;
        
        var isDuplicatePlay = $(source_obj).is( $(source_previous) ); //if we're trying to play the same track again
        var isTrackListSwitch = ( source_previous && (source_obj.track.tracklist.index === source_previous.track.tracklist.index) );

        //TOUFIX TOUMOUVE ?
        if (isDuplicatePlay){
            success.reject("we've already queued this soure");
        }
        
        if (source_previous){
            self.end_media(); //stop current media
        }

        //fire an event when a playlist starts to play
        if ( isTrackListSwitch ){
                source_previous.track.tracklist.debug("close tracklist");
                $(document).trigger("wpsstmCloseTracklist",[source_previous.track.tracklist]); //custom event
        }

        self.debug("init_source: " + source_obj.src);
        $(self.current_media).off(); //remove old events

        ///
        self.current_source = source_obj;

        self.debug("play_source: ");
        console.log(source_obj);
        
        //register new events

        $(self.current_media).on('loadeddata', function() {
            $(document).trigger( "wpsstmSourceLoaded",[self,source_obj] ); //custom event
            source_obj.can_play = true;
            source_obj.debug('source loaded: ' + source_obj.src);
            source_obj.duration = self.current_media.duration;
            self.current_media.play();
        });

        $(self.current_media).on('error', function(error) {

            source_obj.can_play = false;
            source_obj.debug('media - error');

            //sources
            self.tracks_el.find('[data-wpsstm-source-idx]').addClass('source-error');
            self.tracks_el.find('[data-wpsstm-source-idx]').removeClass('source-active source-loading');

            success.reject(error);

        });

        $(self.current_media).on('play', function() {

            var trackinfo_sources = self.tracks_el.find('[data-wpsstm-source-idx]');
            $(trackinfo_sources).removeClass('source-playing');

            //tracklists
            self.tracks_el.parents('.wpsstm-tracklist').addClass('tracklist-playing tracklist-has-played');
            //tracks
            self.tracks_el.removeClass('track-error track-loading');
            self.tracks_el.addClass('track-playing track-has-played');
            //sources
            self.tracks_el.find('[data-wpsstm-source-idx]').addClass('source-active source-playing source-has-played');

            source_obj.debug('media - play');
            success.resolve();
        });

        $(self.current_media).on('pause', function() {

            source_obj.debug('player - pause');
            
            //tracklists
            self.tracks_el.parents('.wpsstm-tracklist').removeClass('tracklist-playing');
            //tracks
            self.tracks_el.removeClass('track-playing');
            //sources
            self.tracks_el.find('[data-wpsstm-source-idx]').removeClass('source-playing');
        });

        $(self.current_media).on('ended', function() {

            source_obj.debug('media - ended');
            
            //tracklists
            self.tracks_el.parents('.wpsstm-tracklist').removeClass('tracklist-playing');
            //tracks
            self.tracks_el.removeClass('track-playing track-active');
            //sources
            self.tracks_el.find('[data-wpsstm-source-idx]').removeClass('source-playing source-active');

            //Play next song if any
            self.next_track_jump();
        });
        
        success.always(function(data, textStatus, jqXHR) {
            self.tracks_el.find('[data-wpsstm-source-idx]').removeClass('source-loading');
        })
        success.fail(function() {
            self.tracks_el.find('[data-wpsstm-source-idx]').addClass('source-error');
            self.tracks_el.find('[data-wpsstm-source-idx]').removeClass('source-active');
        })

        ////

        self.current_media.setSrc(source_obj.src);
        self.current_media.load();
        
        return success.promise();

    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    maybe_load_queue_sources() {

        var self = this;

        var max_items = 4; //number of following tracks to preload
        var rtrack_in = self.current_source.track.index + 1;
        var rtrack_out = self.current_source.track.index + max_items + 1;

        var tracks_slice = $(self.tracks).slice( rtrack_in, rtrack_out );

        $(tracks_slice).each(function(index, track_to_preload) {
            if ( track_to_preload.sources.length > 0 ) return true; //continue;
            track_to_preload.maybe_load_sources();
        });
    }
    
    previous_track_jump(){

        var self = this;
        
        var current_track_idx = ( self.current_track ) ? self.current_track.index : 0;
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx); //shuffle ?

        var tracks_before = self.get_ordered_tracks().slice(0,current_track_idx).reverse();

        //which one should we play?
        var tracks_playable = tracks_before.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        var track_obj = tracks_playable[0];

        if (track_obj){
            track_obj.play_track();
        }
    }
    
    next_track_jump(){

        var self = this;
        
        var current_track_idx = ( self.current_source ) ? self.current_source.track.index : 0;
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx); // shuffle ?

        var tracks_after = self.get_ordered_tracks().slice(current_track_idx+1);

        //which one should we play?
        var tracks_playable = tracks_after.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        
        var track_obj = tracks_playable[0];

        if (track_obj){
            self.play_track(track_obj);
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
    
    queue_tracklist(tracklist_obj){
        var self = this;
        
        console.log("QUEUE TRACKLIST");
        $(tracklist_obj.tracks).each(function() {
            var track = this;
            self.tracks.push(track);
        });
        
    }
    
    get_maybe_shuffle_tracklist_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var new_idx = self.tracklists_shuffle_order[idx];
        
        self.debug("get_maybe_shuffle_tracklist_idx() : " + idx + "-->" + new_idx);
        return new_idx;
        
    }
    
    get_maybe_unshuffle_tracklist_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var shuffle_order = self.tracklists_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        self.debug("get_maybe_unshuffle_tracklist_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    /*
    Return the tracklists; in the shuffled order if is_shuffle is true.
    */
    get_ordered_tracklists(){
        
        self = this;

        if ( !self.is_shuffle ){
            return self.tracklists;
        }else{
            
            var shuffled_tracklists = [];

            $(self.tracklists_shuffle_order).each(function() {
                var idx = this;
                shuffled_tracklists.push(self.tracklists[idx]);
            });

            return shuffled_tracklists;
        }
        
    }
    
    get_maybe_shuffle_track_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var new_idx = self.tracks_shuffle_order[idx];
        
        self.debug("get_maybe_shuffle_track_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    get_maybe_unshuffle_track_idx(idx){
        var self = this;
        if ( !self.is_shuffle ) return idx;
        var shuffle_order = self.tracks_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        
        self.debug("get_maybe_unshuffle_track_idx : " + idx + "-->" + new_idx);
        return new_idx;
    }

    track_to_player(track_obj){

        var self = this;

        self.debug("source > player");
        
        //audio sources
        self.set_player_sources(track_obj.sources);
        
        /*
        track infos
        */
        var tracklist_el = track_obj.tracklist.tracklist_el;

        //copy attributes from the original playlist 
        var attributes = $(tracklist_el).prop("attributes");
        $.each(attributes, function() {
            self.trackinfo_el.attr(this.name, this.value);
        });
        
        //switch type
        self.trackinfo_el.removeClass('wpsstm-post-tracklist');
        self.trackinfo_el.addClass('wpsstm-player-tracklist');

        var list = $('<ul class="wpsstm-tracks-list" />'); 

        var row = track_obj.track_el.clone(true,true);
        row.find('.wpsstm-track-sources').removeClass('wpsstm-sources-expanded');

        $(list).append(row);

        self.trackinfo_el.html(list);
        self.player_el.show();//show in not done yet
        
        //shortcut for tracks instances
        self.tracks_el = self.player_el.find('[itemprop="track"]').first();
        self.tracks_el.push(track_obj.track_el.get(0));
        
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
    
    end_media(){

        var self = this;
        if (!self.current_source) return;

        self.debug("end_source #" + self.current_source.index);
        self.current_media.pause();

        //TOUFIX should be hookend on events ?
        self.current_source.get_source_instances().removeClass('source-playing source-active source-loading');
        self.tracks_el.removeClass('track-loading track-active track-playing');

    }

}

(function($){
    
    $(document).on( "wpsstmStartTracklist", function( event, tracklist_obj ) {
    
        //track popups for player
        tracklist_obj.targetPlayer.player_el.on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
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

})(jQuery);