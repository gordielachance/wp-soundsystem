var $ = jQuery.noConflict();

class WpsstmPlayer extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.shuffle_el =               undefined;
        this.loop_el =                  undefined;
        this.current_source =           undefined;
        this.current_track =            undefined;
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
        this.current_media =            undefined;

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
        var debug = {message:msg,player:this};
        wpsstm_debug(debug);
    }
    
    /*
    Toggle display the player when the queue is updated
    */
    queueWatch(mutationsList){
        
        for(var mutation of mutationsList) {
            if (mutation.type == 'childList') {
                var tracks = $(mutation.target).find('wpsstm-track');
                var player = mutation.target.closest('wpsstm-player');
                var showPlayer = ( tracks.length > 0);
                $(player).toggleClass('active',showPlayer);
            }
        }

    }
    
    render(){

        var self = this;
        this.debug("LOAD player");
        
        ///
        self.shuffle_el =   $('#wpsstm-player-shuffle');
        self.loop_el =      $('#wpsstm-player-loop');
        
        /* Watch for queue changes*/
        var queueNode = $(self).find('.player-queue').get(0);
        var queueUpdated = new MutationObserver(self.queueWatch);
        queueUpdated.observe(queueNode,{childList: true});
        

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
            
            self.render_queue_controls();

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
                $(document).trigger( "wpsstmPlayerInit", [self] ); //custom event
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
            }
        });

    }

    queueTrack(track){
        var self = this;
        var playerQueue = $(self).find('.player-queue').get(0);
        
        //show play BT
        $(track).find('.wpsstm-track-play-bt').show();

        //clone page track & append to queue
        var queueTrack = $(track).clone().get(0);
        queueTrack.pageNode = track;
        playerQueue.append(queueTrack);
        //
        
        //post track
        track.queueNode = queueTrack;
        
        return queueTrack;
        
    }

    get_previous_track_idx(){
        var self = this;
        var tracks_before = [];

        var queue = $(self.current_track.parentNode);
        var tracks = queue.find('wpsstm-track');
        var current_track_idx = tracks.index( $(self.current_track) );
        if (current_track_idx == -1) return; //index not found
        
        if (tracks){
            tracks_before = tracks.slice(0,current_track_idx);
        }

        if (!tracks_before.length){
            if (self.can_repeat){
                tracks_before = tracks;
            }
        }
        
        tracks_before = tracks_before.toArray().reverse();

        //which one should we play?
        var tracks_playable = tracks_before.filter(function (track) {
            return (track.can_play !== false);
        });

        var previous_track = tracks_playable[0];
        var previous_track_idx = ( previous_track ) ? tracks.index( previous_track ) : undefined;
        
        return previous_track_idx;
    }
    
    get_next_track_idx(){
        var self = this;
        var tracks_after = [];

        var queue = $(self.current_track.parentNode);
        var tracks = queue.find('wpsstm-track');
        var current_track_idx = tracks.index( $(self.current_track) );
        if (current_track_idx == -1) return; //index not found

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

        var next_track = tracks_playable[0];
        var next_track_idx = ( next_track ) ? tracks.index( next_track ) : undefined;

        //console.log("get_next_track_idx: current: " + current_track_idx + ", next;" + next_track_idx);

        return next_track_idx;
    }
    
    previous_track_jump(){
        
        var self = this;
        var track_idx = self.get_previous_track_idx();

        if (typeof track_idx !== 'undefined'){
            self.play_queue(track_idx);
        }else{
            self.debug("no previous track");
        }
    }
    
    next_track_jump(){
        var self = this;

        var track_idx = self.get_next_track_idx();

        if (typeof track_idx !== 'undefined'){
            self.play_queue(track_idx);
        }else{
            self.debug("no next track");
        }

    }
    
    play_queue(track_idx){
        var self = this;
        
        var allTracks = $(self).find('.player-queue wpsstm-track');
        var currentTrack = allTracks.filter('.track-active').get(0);
        var requestedTrack = $(self).find('.player-queue wpsstm-track').get(track_idx);

        if (currentTrack){
            currentTrack.removeAttribute('trackstatus');
        }
        
        requestedTrack.debug('request track');
        requestedTrack.setAttribute('trackstatus','request');
        
        var trackPopulated = requestedTrack.maybe_load_sources();
        
        trackPopulated.then(
            function () { //success
                
                /* node has been swapped so fetch the track again*/
                var updatedTrack = $(self).find('.player-queue wpsstm-track').get(track_idx);
                
                //check that it still the same track that is requested
                if (self.current_track !== requestedTrack) return;
                updatedTrack.play_track();
                
                
            }, function (error) { //error
                requestedTrack.debug('unable to play track, skipping...');
                self.next_track_jump();
                
            }
        );


    }
    
    setup_track(track){
        var self = this;
        self.current_track = track;
        self.render_queue_controls();
        track.get_instances().addClass('track-active');
    }

    render_queue_controls(){
        
        var self = this;
        
        /*
        Previous track bt
        */

        var previous_track_idx = self.get_previous_track_idx();
        var hasPreviousTrack = (typeof previous_track_idx !== 'undefined');
        var previousTrackEl = $(self).find('#wpsstm-player-extra-previous-track');

        previousTrackEl.toggleClass('active',hasPreviousTrack);

        /*
        Next track bt
        */
        var next_track_idx = self.get_next_track_idx();
        var hasNextTrack = (typeof next_track_idx !== 'undefined');
        var nextTrackEl = $(self).find('#wpsstm-player-extra-next-track');
        nextTrackEl.toggleClass('active',hasNextTrack);
    }


}

window.customElements.define('wpsstm-player', WpsstmPlayer);
