var $ = jQuery.noConflict();

class WpsstmPlayer extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.shuffle_el =               undefined;
        this.loop_el =                  undefined;
        this.current_link =             undefined;
        this.current_track =            undefined;
        this.is_shuffle =               ( localStorage.getItem("wpsstm-player-shuffle") == 'true' );
        this.can_repeat =               ( ( localStorage.getItem("wpsstm-player-loop") == 'true' ) || !localStorage.getItem("wpsstm-player-loop") );
        this.current_media =            undefined;
        this.tracksHistory =            [];

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

    render(){

        var player = this;
        this.debug("LOAD player");
        
        ///
        player.shuffle_el =   $('#wpsstm-player-shuffle');
        player.loop_el =      $('#wpsstm-player-loop');

        //previous
        $(player).find('#wpsstm-player-extra-previous-track').click(function(e) {
            e.preventDefault();
            player.previous_track_jump();
        });
        
        //next
        $(player).find('#wpsstm-player-extra-next-track').click(function(e) {
            e.preventDefault();
            player.next_track_jump();
        });

        /*
        Shuffle button
        */
        if ( player.is_shuffle ){
            player.shuffle_el.addClass('active');
        }

        player.shuffle_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !player.is_shuffle;
            player.is_shuffle = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-shuffle", true);
                player.shuffle_el.addClass('active');
            }else{
                localStorage.removeItem("wpsstm-player-shuffle");
                player.shuffle_el.removeClass('active');
            }            

        });

        /*
        Loop button
        */
        if ( player.can_repeat ){
            player.loop_el.addClass('active');
        }

        player.loop_el.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !player.can_repeat;
            player.can_repeat = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-player-loop", true);
                player.loop_el.addClass('active');
            }else{
                localStorage.setItem("wpsstm-player-loop", false);
                player.loop_el.removeClass('active');
            }
            
            player.render_queue_controls();

        });

        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */

        $(window).bind('beforeunload', function(){

            if (player.current_link && !player.current_media.paused){
                return wpsstmPlayer.leave_page_text;
            }

        });

        $(player).find('audio').mediaelementplayer({
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
            success: function(mediaElement, originalNode, MEplayer) {
                player.current_media = mediaElement;
                player.debug("MediaElementJS ready");
                $(document).trigger( "wpsstmPlayerInit", [player] ); //custom event
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
            }
        });

    }
    
    /*
    queueContainerOLD(container){
        var player = this;

        $(container).addClass('tracks-container');
        var tracks = $(container).find('wpsstm-track');
        var icon = $(container).find('.wpsstm-tracklist-play-bt');
        var autoplayTrackIdx;

        tracks.each(function(index, track) {

            if ( $(track).hasClass('track-autoplay') ){
                autoplayTrackIdx = Array.from(track.parentNode.children).indexOf(track); //TOUFIX URGENT
            }
        });
        
        player.debug("Queued tracks: " + tracks.length );
        
        //autoplay
        if (typeof autoplayTrackIdx !== 'undefined'){
            player.debug("autoplay track #" + autoplayTrackIdx);
            player.play_queue(autoplayTrackIdx);
        }

    }
    */

    get_previous_track_idx(){
        var player = this;
        var tracks_playable = [];

        var tracks = $(player).closest('wpsstm-tracklist').find('wpsstm-track');
        var current_track_idx = tracks.index( $(player.current_track) );
        if (current_track_idx == -1) return; //index not found
        
        var tracksArr = tracks.toArray();

        if (tracksArr){

            if (player.can_repeat){
                tracks_playable = tracksArr.slice(current_track_idx + 1); //tracks after this one
            }

            var before = tracksArr.slice(0,current_track_idx); //tracks before this one
            tracks_playable = tracks_playable.concat(before);
            
            tracks_playable = tracks_playable.reverse();
        }

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return ( track.playable || track.ajax_details );
        });

        
        //shuffle ?
        if (player.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        var previous_track = tracks_playable[0];
        var previous_track_idx = ( previous_track ) ? tracks.index( previous_track ) : undefined;
        
        return previous_track_idx;
    }
    
    get_next_track_idx(){
        var player = this;
        var tracks_playable = [];

        var tracks = $(player).closest('wpsstm-tracklist').find('wpsstm-track');
        var current_track_idx = tracks.index( $(player.current_track) );
        if (current_track_idx == -1) return; //index not found
        
        var tracksArr = tracks.toArray();

        if (tracksArr){
            var tracks_playable = tracksArr.slice(current_track_idx+1); //tracks after this one
            
            if (player.can_repeat){
                var before = tracksArr.slice(0,current_track_idx); //tracks before this one
                tracks_playable = tracks_playable.concat(before);
            }
            
        }

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return ( track.playable || track.ajax_details );
        });

        //shuffle ?
        if (player.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        var next_track = tracks_playable[0];
        var next_track_idx = ( next_track ) ? tracks.index( next_track ) : undefined;

        //console.log("get_next_track_idx: current: " + current_track_idx + ", next;" + next_track_idx);

        return next_track_idx;
    }
    
    previous_track_jump(){
        var player = this;
        var tracks = $(player).closest('wpsstm-tracklist').find('wpsstm-track');
        var current_track_idx = tracks.index( $(player.current_track) );
        var track_idx = player.get_previous_track_idx();

        if (typeof track_idx !== 'undefined'){
            player.play_queue(track_idx);
        }else{
            player.debug("no previous track");
            player.current_track.status = '';
        }
    }
    
    next_track_jump(){
        var player = this;
        var tracks = $(player).closest('wpsstm-tracklist').find('wpsstm-track');
        var current_track_idx = tracks.index( $(player.current_track) );
        var track_idx = player.get_next_track_idx();

        if (typeof track_idx !== 'undefined'){
            player.play_queue(track_idx);
        }else{
            player.debug("no next track");
            player.current_track.status = '';
        }
        
        /*
        We're looping the tracklist, warn the playlist - useful to reload expired tracklists.
        
        */
        if (track_idx < current_track_idx){
            var tracklist = $(player).closest('wpsstm-tracklist');
            $(tracklist).trigger('wpsstmTracklistLoop',[player]);
        }

    }
    
    play_queue(track_idx,link_idx){
        var player = this;
        
        track_idx = (typeof track_idx !== 'undefined') ? track_idx : 0;
        link_idx = (typeof link_idx !== 'undefined') ? link_idx : 0;
        var allTracks = $(player).closest('wpsstm-tracklist').find('wpsstm-track');
        var requestedTrack = allTracks.get(track_idx);
        var requestedLink = $(requestedTrack).find('wpsstm-track-link').get(link_idx);

        if (player.current_track){

            //reclick
            if ( ( player.current_track === requestedTrack ) && ( player.current_link === requestedLink) ){

                requestedTrack.debug('reclick');
                var isPlaying = ( requestedTrack.status == 'playing' );
                
                requestedTrack.debug('is playing ? ' + isPlaying);

                if ( isPlaying ){
                    player.current_media.pause();
                }else{
                    player.current_media.play();
                }

                return;
            }
            
            player.current_track.status = '';
        }
        
        /*
        Eventually load track details
        */

        requestedTrack.debug('request track');
        requestedTrack.status = 'request';
        
        var trackready = $.Deferred();

        if ( requestedTrack.playable ){
            var sourceLinks = $(requestedTrack).find('wpsstm-track-link').filter('[wpsstm-playable]');
            
            if(sourceLinks.length){
                trackready.resolve();
            }else{
                if ( requestedTrack.ajax_details ){
                    trackready = requestedTrack.load_details();
                }else{
                    trackready.reject();
                }
            }

        }else{
            trackready.reject();
        }
        
        /*
        Track is now ready (or not, here I come)
        */

        trackready.then(
            function (newTrack) { //success
                //check that it still the same track that is requested
                if (player.current_track !== requestedTrack) return;                
                requestedTrack.play_track(link_idx);

            }, function (error) { //error
                
                requestedTrack.status = '';
                player.next_track_jump();
                return;
                
            }
        );


    }

    render_queue_controls(){
        
        var player = this;
        
        /*
        Previous track bt
        */

        var previous_track_idx = player.get_previous_track_idx();
        var hasPreviousTrack = (typeof previous_track_idx !== 'undefined');
        var previousTrackEl = $(player).find('#wpsstm-player-extra-previous-track');

        previousTrackEl.toggleClass('active',hasPreviousTrack);

        /*
        Next track bt
        */
        var next_track_idx = player.get_next_track_idx();
        var hasNextTrack = (typeof next_track_idx !== 'undefined');
        var nextTrackEl = $(player).find('#wpsstm-player-extra-next-track');
        nextTrackEl.toggleClass('active',hasNextTrack);
    }


}

$(document).on( "wpsstmSourceInit", function( event, link ) {

    var track =                 link.closest('wpsstm-track');
    var scrobble_icon =         $(track.tracklist.player).find('.wpsstm-player-action-scrobbler');
    var scrobbler_enabled =     scrobble_icon.hasClass('active');

    var startTrack = function(){
        $(document).trigger('wpsstmTrackStart',track);
    }

    //start track event, fired only once
    $(track.tracklist.player.current_media).one('play', startTrack);

});

window.customElements.define('wpsstm-player', WpsstmPlayer);
