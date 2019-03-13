var $ = jQuery.noConflict();

class WpsstmPlayer extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.tracks =                   [];
        this.trackinfo_el =             undefined;
        this.shuffle_el =               undefined;
        this.loop_el =                  undefined;
        this.current_source =           undefined;
        this.current_track =            undefined;
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );

        // Setup a click listener on <wpsstm-tracklist> itself.
        this.addEventListener('click', e => {
        });
    }
    connectedCallback(){
        //console.log("PLAYER CONNECTED!");
        /*
        Called every time the element is inserted into the DOM. Useful for running setup code, such as fetching resources or rendering. Generally, you should try to delay work until this time.
        */
        this.render();
    }

    disconnectedCallback(){
        /*
        Called every time the element is removed from the DOM. Useful for running clean up code.
        */
    }
    attributeChangedCallback(attrName, oldVal, newVal){
        /*
        Called when an observed attribute has been added, removed, updated, or replaced. Also called for initial values when an element is created by the parser, or upgraded. Note: only attributes listed in the observedAttributes property will receive this callback.
        */
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        //return ['id', 'my-custom-attribute', 'data-something', 'disabled'];
    }
    
    ///
    ///
    
    debug(msg){
        var prefix = "WpsstmPlayer";
        wpsstm_debug(msg,prefix);
    }
    
    render(){

        var self = this;
        this.debug("LOAD player: #" +$(self).attr('id'));
        
        ///

        self.trackinfo_el = $(self).find('#wpsstm-player-track');
        self.shuffle_el =   $('#wpsstm-player-shuffle');
        self.loop_el =      $('#wpsstm-player-loop');

        //toggle queue
        $(self).find('.wpsstm-player-action-queue a').click(function(e) {
            e.preventDefault();
            $(this).toggleClass('active');
            $(self).find('.player-queue').toggleClass('active');
        });
        
        //previous
        $(self).find('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            self.previous_track_jump();
        });
        
        //next
        $(self).find('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            self.next_track_jump();
        });

        /*
        Scroll to playlist track when clicking the player's track number
        */
        $(self).find('.wpsstm-track-position').click(function(e) {
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
        
        $(self).on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
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
            var prevTrackEl = $(self).find('#wpsstm-player-extra-previous-track');
            prevTrackEl.toggleClass('active',has_prev_track);
            
            //update next track bt
            var next_track = self.get_next_track();
            var has_next_track = (next_track!==undefined);
            var nextTrackEl = $(self).find('#wpsstm-player-extra-next-track');
            nextTrackEl.toggleClass('active',has_next_track);

        });

        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */

        $(window).bind('beforeunload', function(){

            if (self.current_source && !self.current_media.paused){
                return wpsstmPlayer.leave_page_text;
            }

        });

        $(self).find('audio').mediaelementplayer({
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

    unQueueContainer(tracklist){
        var self = this;

        /*
        Stop current track if it is part of this tracklist
        */
        if ( self.current_track && $(tracklist).find($(self.current_track)).length ){
            self.debug("current track is being unqueued, stop it");
            self.current_track.end_track();
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

        tracklist.debug( 'remove tracks from #' + $(self).attr('id') );
        self.debug("unQueued " + removedTrackCount + " tracks, still in queue: " + newTrackCount);
        
        self.tracks = cleanedTracks;
        self.queueUpdateGUI();
    }
    
    queueContainer(tracklist){
        var self = this;
        var newTracks = [];
        

        $(tracklist.tracks).each(function(index, track) {
            //set player
            track.player = self;
            newTracks.push(track);
        });
        
        tracklist.debug( 'append tracks to #' + $(self).attr('id') );
        self.debug("Queued tracks: " + newTracks.length );
        
        $.merge(self.tracks,newTracks);
        self.queueUpdateGUI();

        /* autoplay ? */
        function autoPlayFN(){
            if ( newTracks.length && $(tracklist).hasClass('tracklist-autoplay') ){
                $(tracklist).removeClass('tracklist-autoplay');
                var firstTrack = $(tracklist).find('wpsstm-track').get(0);

                if(firstTrack){
                    firstTrack.play_track();
                }else{
                    $(tracklist).removeClass('tracklist-autoplay');
                }
            }
        }
        
        /*
        TOUFIX 
        When a tracklist is refreshed and has autoplay enabled, a bug occurs:
        In play_source, the source track is not defined.
        It works with a little timeout but this should be investigated.
        */
        
        setTimeout(autoPlayFN, 250);

        
        //play/pause track button
        $(tracklist).on( "click", ".wpsstm-track-play-bt", function(e) {
            e.preventDefault();
            
            var track = this.closest('wpsstm-track');

            //re-click
            if ( self.current_media && (self.current_track == track) ){

                if ( $(track).hasClass('track-playing') ){
                    self.current_media.pause();
                }else{
                    self.current_media.play();
                }

                return;
            }
            
            track.play_track();

        });

    }
    
    queueUpdateGUI(){
        var self = this;
        var queueEl = $(self).find('.player-queue');
        
        queueEl.empty();

        $(self.tracks).each(function(index, track) {
            var el = $(track).clone(true,true);
            $(self).find('.player-queue').append(el);
        });

        //show in not done yet
        var showPlayer = ( $(self.tracks).length > 0);
        $(self).toggle(showPlayer);
    }
    get_previous_track(){
        var self = this;
        var tracks_before = [];

        var current_track_idx = $(self.tracks).index( $(self.current_track) );
        if (current_track_idx == -1) return; //index not found

        var tracks = $(self).find('.player-queue wpsstm-track');
        
        if (tracks){
            tracks_before = tracks.slice(0,current_track_idx);
            tracks_before = tracks_before.reverse();
        }

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
        var tracks_after = [];

        var current_track_idx = $(self.tracks).index( $(self.current_track) );
        if (current_track_idx == -1) return; //index not found

        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx); // shuffle ?

        var tracks = $(self).find('.player-queue wpsstm-track');
        
        if (tracks){
            var tracks_after = tracks.slice(current_track_idx+1);
        }

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
            
            if (tracklist){
                /*
                check if we need to reload the tracklist
                */
                if (new_track_idx > current_track_idx){
                    if (tracklist.isExpired){
                        var current_track_idx = $(tracklist).find('wpsstm-track').index( $(self.current_track) );
                        var new_track_idx = $(tracklist).find('wpsstm-track').index( track );


                        tracklist.debug("tracklist backward loop and it is expired, refresh it!");
                        self.current_track.end_track();
                        tracklist.reload_tracklist(true);
                        return;

                    }
                }
            }

            track.play_track();
        }else{
            self.debug("no previous track");
            self.current_track.end_track();
        }
    }
    
    next_track_jump(){
        var self = this;

        var track = self.get_next_track();

        if (track){
            
            var tracklist = track.tracklist;
            
            if (tracklist){
                /*
                check if we need to reload the tracklist
                */
                if (new_track_idx < current_track_idx){
                    if (tracklist.isExpired){
                        var current_track_idx = $(tracklist).find('wpsstm-track').index( $(self.current_track) );
                        var new_track_idx = $(tracklist).find('wpsstm-track').index( track );


                            tracklist.debug("tracklist forward loop and it is expired, refresh it!");
                            self.current_track.end_track();
                            tracklist.reload_tracklist(true);
                            return;

                    }
                }
            }

            track.play_track();
        }else{
            self.debug("no next track");
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
        var sources = $(self.current_track).find('wpsstm-source');
        self.set_player_sources(sources);

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
        var previousTrackEl = $(self).find('#wpsstm-player-extra-previous-track');

        previousTrackEl.toggleClass('active',has_previous_track);

        /*
        Next track bt
        */
        var next_track = self.get_next_track();
        var has_next_track = (next_track!==undefined);
        var nextTrackEl = $(self).find('#wpsstm-player-extra-next-track');
        nextTrackEl.toggleClass('active',has_next_track);

    }
    
    set_player_sources(sources){
        
        var self = this;

        var old_sources = $(self).find('audio').find('source');
        
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
        $(self).find('audio').append(new_sources);
    }

}

window.customElements.define('wpsstm-player', WpsstmPlayer);
