var $ = jQuery.noConflict();

class WpsstmTracklist extends HTMLElement{
    constructor() {
        super(); //required to be first

        this.index =            undefined;
        this.post_id =          undefined;
        this.isExpired =        undefined;
        this.current_track =    undefined;

        this.mediaElement =     undefined;
        this.tracksHistory =    [];

        this.$shuffleTracksBt = undefined;
        this.$loopTracksBt =    undefined;
        this.$previousTrackBt = undefined;
        this.$nextTrackBt =     undefined;

        //Setup listeners
        $(this).on('playerInit',WpsstmTracklist._PlayerInitEvent);
    }
    connectedCallback(){

        var tracklist =                 this;
        tracklist.post_id =             Number( $(tracklist).data('wpsstm-tracklist-id') );
        tracklist.is_shuffle =          ( localStorage.getItem("wpsstm-shuffle-tracklist") == 'true' );
        tracklist.can_repeat =          ( ( localStorage.getItem("wpsstm-loop-tracklist") == 'true' ) || !localStorage.getItem("wpsstm-loop-tracklist") );

        tracklist.$shuffleTracksBt =    $(tracklist).find('.wpsstm-shuffle-bt');
        tracklist.$loopTracksBt =       $(tracklist).find('.wpsstm-loop-bt');
        tracklist.$previousTrackBt =    $(tracklist).find('.wpsstm-previous-track-bt');
        tracklist.$nextTrackBt =        $(tracklist).find('.wpsstm-next-track-bt');

        var tracklistReady = $.Deferred();

        tracklist.init_tracklist_expiration();
        var needsRefresh = (wpsstmL10n.ajax_radios && tracklist.isExpired );

        if (needsRefresh){
            tracklistReady = tracklist.reloadTracklist();
        }else{
            tracklist.renderHeader();
            tracklist.renderQueue();
            tracklistReady.resolve();
        }

        tracklistReady.always(function(data){
            tracklist.renderPlayer();
        });

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
        return ['wpsstm-playable'];
    }

    get tracks(){
        return $(this).find('.wpsstm-tracks-list wpsstm-track').toArray();
    }

    get playerTrack(){
        return $(this).find('.player-track wpsstm-track').get(0);
    }

    set playerTrack(track){
        var tracklist = this;

        //we're trying to load the same track.
        if ( tracklist.playerTrack.queueIdx && (track.queueIdx == tracklist.playerTrack.queueIdx) ){
            return;
        }

        tracklist.current_track = track;

        var $clone = $(track).clone();

        $(tracklist.playerTrack.parentNode).empty().append( $clone );

        //once rendered, update clone to match the track values
        $clone.get(0).queueIdx = tracklist.current_track.queueIdx;

        tracklist.updatePlayerControls();
    }

    get playable() {
        return this.hasAttribute('wpsstm-playable');
    }

    set playable(value) {
        const isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('wpsstm-playable', '');
        } else {
            this.removeAttribute('wpsstm-playable');
        }
    }

    ///
    ///

    debug(data,msg){

        //add prefix
        if (this.post_id){
            var prefix = '[tracklist:'+this.post_id+']';
            if (typeof msg === 'undefined'){
                msg = prefix;
            }else{
                msg = prefix + ' ' + msg;
            }
        }

        wpsstm_debug(data,msg);
    }

    renderHeader(){

        var tracklist = this;

        /*
        Tracklist actions
        */

        //toggle favorite
        $(tracklist).find('.wpsstm-tracklist-action-favorite,.wpsstm-tracklist-action-unfavorite').click(function(e) {
            e.preventDefault();

            var do_love = $(this).hasClass('wpsstm-tracklist-action-favorite');

            tracklist.toggle_favorite_tracklist(do_love);
        });

        /*
        Refresh
        */
        if (wpsstmL10n.ajax_radios){
            $(tracklist).find('.wpsstm-reload-bt').click(function(e) {
                e.preventDefault();
                var autoplay = ( tracklist.current_track && !tracklist.mediaElement.paused );
                tracklist.reloadTracklist(autoplay);
            });
        }

        /*
        Play
        */

        //container play icon
        $(tracklist).find('.wpsstm-tracklist-play-bt').click(function(e) {
            e.preventDefault();
            if (!tracklist.tracks) return;

            var track = (tracklist.current_track) ? tracklist.current_track : tracklist.tracks[0];
            if (!track) return;

            tracklist.playTrack(track);

        });

    }

    renderPlayer(){

        var tracklist = this;

        if ( !tracklist.playerTrack ) return;

        $(tracklist.tracks).on('started', WpsstmTracklist._startedTrackEvent);

        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */

        $(window).bind('beforeunload', function(){

            if ( tracklist.current_track && tracklist.mediaElement && !tracklist.mediaElement.paused ){
                return wpsstmL10n.leave_page_text;
            }

        });

        /*
        Player track : scroll to page track
        */
        //Scroll to page track
        $(tracklist).on('click','.player-track .wpsstm-track-position',function(e) {
            e.preventDefault();

            //https://stackoverflow.com/a/6677069/782013
            $('html, body').animate({
                scrollTop: $(tracklist.current_track).offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });

        /*
        Previous track
        */
        $(tracklist).find('.wpsstm-previous-track-bt').click(function(e) {
            e.preventDefault();
            tracklist.previousTrackJump();
        });

        /*
        Next track
        */
        $(tracklist).find('.wpsstm-next-track-bt').click(function(e) {
            e.preventDefault();
            tracklist.nextTrackJump();
        });

        /*
        Loop button
        */
        if ( tracklist.can_repeat ){
            tracklist.$loopTracksBt.addClass('active');
        }

        tracklist.$loopTracksBt.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !tracklist.can_repeat;
            tracklist.can_repeat = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-loop-tracklist", true);
                tracklist.$loopTracksBt.addClass('active');
            }else{
                localStorage.setItem("wpsstm-loop-tracklist", false);
                tracklist.$loopTracksBt.removeClass('active');
            }

            tracklist.updatePlayerControls();

        });

        /*
        Shuffle button
        */
        if ( tracklist.is_shuffle ){
            tracklist.$shuffleTracksBt.addClass('active');
        }

        tracklist.$shuffleTracksBt.find('a').click(function(e) {
            e.preventDefault();

            var is_active = !tracklist.is_shuffle;
            tracklist.is_shuffle = is_active;

            if (is_active){
                localStorage.setItem("wpsstm-shuffle-tracklist", true);
                tracklist.$shuffleTracksBt.addClass('active');
            }else{
                localStorage.removeItem("wpsstm-shuffle-tracklist");
                tracklist.$shuffleTracksBt.removeClass('active');
            }

        });

        /*
        Init player
        */

        $(tracklist).find('audio').mediaelementplayer({
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug:          wpsstmL10n.debug,
                autoStartLoad:  true
            },
            pluginPath: wpsstmL10n.plugin_path, //'https://cdnjs.com/libraries/mediaelement/'
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(mediaElement, originalNode, MEplayer) {
                tracklist.mediaElement = mediaElement;
                $(tracklist).trigger("playerInit",[mediaElement]);

            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
            }
        });

    }

    renderQueue(){

        var tracklist = this;

        //playable ?
        if ( tracklist.playerTrack ){ //check player is enabled
            var tracks_playable = tracklist.tracks.filter(function (track) {
                return track.playable;
            });

            tracklist.playable = (tracks_playable.length > 0);//has option set & has playable tracks
        }

        /*
        New subtracks
        */

        var tracksList = $(tracklist).find('.wpsstm-tracks-list');
        var newTracksActionsBlock = $(tracklist).find('#wpsstm-new-tracks');
        var newTracksSubmitBt = newTracksActionsBlock.find('#wpsstm-new-tracks-submit');//TOUFIX URGENT
        var addNewTrackRowBt = newTracksActionsBlock.find('#wpsstm-add-new-track-row');

        //add new track row
        addNewTrackRowBt.on( "click", function(e) {
            e.preventDefault();
            var baseRow = newTracksActionsBlock.find('.wpsstm-new-track').first();
            var newRow = baseRow.clone();
            tracksList.append( newRow );
        });

        //remove new track row
        tracksList.on( "click",'.wpsstm-remove-new-track-row', function(e) {
            var row = $(this).parents('.wpsstm-new-track');
            row.remove();
        });

        //submit new track
        tracksList.on( "click",'.wpsstm-save-new-track-row', function(e) {

            e.preventDefault();

            var row = $(this).parents('.wpsstm-new-track');
            var track = new WpsstmTrack();
            track.track_artist = row.find('input[name="wpsstm_track_data[artist]"]').val();
            track.track_title = row.find('input[name="wpsstm_track_data[title]"]').val();
            track.track_album = row.find('input[name="wpsstm_track_data[album]"]').val();

            var ajax_data = {
                action:         'wpsstm_tracklist_new_subtrack',
                track:          track.to_ajax(),
                tracklist_id:   tracklist.post_id
            };

            row.removeClass('action-error').addClass('action-loading wpsstm-freeze');

            var ajax = $.ajax({

                type:       "post",
                url:        wpsstmL10n.ajaxurl,
                data:       ajax_data,
                dataType:   'json',
            })
            .done(function(data){

                if (data.success === false) {
                    console.log(data);
                }

                if (data.html){
                    row.replaceWith($(data.html));
                }
            })
            .fail(function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                row.addClass('action-error');
            })
            .always(function() {
                row.removeClass('action-loading wpsstm-freeze');
            })

        });

        /*
        Subtracks
        */

        //sort subtracks
        var startSortIdx, endSortIdx;
        $(tracklist).find( '.wpsstm-tracks-list' ).sortable({
            axis: "y",
            handle: '.wpsstm-track-action-move',
            start: function(event, ui) {
                startSortIdx = ui.item.index();
            },
            update: function(event, ui) {
                endSortIdx = ui.item.index();
                var track = tracklist.tracks[startSortIdx];
                var old_position = Number($(track).attr('data-wpsstm-subtrack-position'));
                var new_position = ui.item.index() + 1;


                if (track){
                    //new position
                    track.position = ui.item.index();
                    tracklist.update_subtrack_position(track,new_position);
                }

            }
        });

    }

    reloadTracklist(autoplay){
        var tracklist = this;
        var success = $.Deferred();

        tracklist.debug("reload tracklist... - autoplay ? " + (autoplay === true) );

        var abord_reload = function(e) {
            if ( (e.key === "Escape") ) { // escape key maps to keycode `27`
                request.abort();
            }
        }

        tracklist.stopCurrentMedia();

        ///

        var ajax_data = {
            action:     'wpsstm_reload_tracklist',
            tracklist:      tracklist.to_ajax(),
        };

        $(tracklist).addClass('tracklist-reloading');

        $(document).bind( "keyup.reloadtracklist", abord_reload ); //use namespace - https://acdcjunior.github.io/jquery-creating-specific-event-and-removing-it-only.html

        var request = $.ajax({
            type:           "post",
            url:            wpsstmL10n.ajaxurl,
            data:           ajax_data,
            dataType:       'json'
        })
        .done(function(data){
            if (data.success === false) {
                console.log(data);
                success.reject();
            }else{

                var newTracklist = $(data.html).get(0);

                /*
                Swap content, but keep player intact so we don't mess with the Autoplay Policy.
                */

                //swap attributes
                wpsstmSwapNodeAttributes(tracklist,newTracklist);

                //header
                var $oldTracklistHeader =   $(tracklist).find('>section.wpsstm-tracklist-header');
                var $newTracklistHeader =   $(newTracklist).find('>section.wpsstm-tracklist-header');
                $oldTracklistHeader.replaceWith( $newTracklistHeader );

                //queue
                var $oldTracklistQueue =    $(tracklist).find('>section.wpsstm-tracklist-queue');
                var $newTracklistQueue =    $(newTracklist).find('>section.wpsstm-tracklist-queue');
                $oldTracklistQueue.replaceWith( $newTracklistQueue );

                //reset expiration
                tracklist.init_tracklist_expiration();

                success.resolve();
            }
        })
        .done(function() {
            tracklist.current_track = undefined;
            tracklist.current_link = undefined;
            tracklist.renderHeader();
            tracklist.renderQueue();

            var autoplayTrack = (autoplay) ? tracklist.tracks[0] : undefined;

            //wait a few seconds so we're sure that links are initialized.
            //https://stackoverflow.com/questions/58354531/custom-elements-connectedcallback-wait-for-parent-node-to-be-available-bef/58362114#58362114
            if (autoplayTrack){
                tracklist.debug("autoplay track...");
                setTimeout(function(){ tracklist.playTrack(autoplayTrack); }, 1000);
            }

        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
            success.reject(thrownError);
        })
        .always(function() {
            $(tracklist).removeClass('tracklist-reloading');
            $(document).unbind( "keyup.reloadtracklist", abord_reload );
        })

        return success.promise();
    }

    toggle_favorite_tracklist(do_love){
        var tracklist = this;

        if (do_love){
            var link_el = $(tracklist).find('.wpsstm-tracklist-action-favorite');
        }else{
            var link_el = $(tracklist).find('.wpsstm-tracklist-action-unfavorite');
        }

        var ajax_data = {
            action:     'wpsstm_tracklist_toggle_favorite',
            tracklist:  tracklist.to_ajax(),
            do_love:    do_love,
        };

        link_el.removeClass('action-error');
        link_el.addClass('action-loading');

        return $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        })
        .done(function(data){

            if (data.success === false) {
                console.log(data);
                link_el.addClass('action-error');
                if (data.notice){
                    wpsstm_js_notice(data.notice);
                }
            }else{
                if (do_love){
                    $(tracklist).addClass('favorited-tracklist');
                }else{
                    $(tracklist).removeClass('favorited-tracklist');
                }
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            link_el.addClass('action-error');
            console.log(xhr.status);
            console.log(thrownError);
        })
        .always(function() {
            link_el.removeClass('action-loading');
        })
    }

    init_tracklist_expiration(){
        var tracklist = this;

        var now = Math.round( $.now() /1000);
        var remaining_sec = undefined;

        var meta_expiration = $(tracklist).find('meta[itemprop="wpsstmRefreshTimer"]').get(0);
        if (!meta_expiration) return;

        remaining_sec = parseInt( meta_expiration.getAttribute('content') );

        if (remaining_sec > 0){
            tracklist.isExpired = false;

            var expirationTimer = setTimeout(function(){
                tracklist.isExpired = true;
                $(tracklist).addClass('tracklist-expired');
                tracklist.debug("tracklist has expired, stop expiration timer");

            }, remaining_sec * 1000 );

        }else{
            tracklist.isExpired = true;
            $(tracklist).addClass('tracklist-expired');
        }

        if (remaining_sec <= 0){
            tracklist.debug("tracklist has expired "+Math.abs(remaining_sec)+" seconds ago");
        }else{
            tracklist.debug("tracklist will expire in "+remaining_sec+" seconds");
        }

    }

    refresh_tracks_positions(){
        var tracklist = this;
        var all_rows = $(tracklist).find( 'wpsstm-track' );
        jQuery.each( all_rows, function( key, value ) {
            var position = jQuery(this).find('.wpsstm-track-position [itemprop="position"]');
            position.text(key + 1);
        });
    }

    update_subtrack_position(track,new_pos){
        var tracklist = this;
        var link_el = $(track).find('.wpsstm-track-action-move');

        var ajax_data = {
            action:     'wpsstm_update_subtrack_position',
            new_pos:    new_pos,
            track:      track.to_ajax(),
        };

        $(track).addClass('track-loading');
        link_el.removeClass('action-error');
        link_el.addClass('action-loading');

        $.ajax({
            type: "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType: 'json'
        })
        .done(function(data){
            if (data.success === false) {
                link_el.addClass('action-error');
                console.log(data);
            }else{
                tracklist.refresh_tracks_positions();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            link_el.addClass('action-error');
            console.log(xhr.status);
            console.log(thrownError);
        })
        .always(function() {
            $(track).removeClass('track-loading');
            link_el.removeClass('action-loading');
        })

    }

    //reduce object for communication between JS & PHP
    to_ajax(){

        var tracklist = this;
        var allowed = ['post_id'];
        var filtered = Object.keys(tracklist)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) =>{
            obj[key] = tracklist[key];
            return obj;
        }, {});
        return filtered;
    }

    get_previous_track(){
        var tracklist = this;
        var tracks_playable = [];

        if ( !tracklist.tracks ) return;

        var current_track_idx = $(tracklist.tracks).index( $(tracklist.current_track) );
        if (current_track_idx == -1) return; //index not found

        if (tracklist.can_repeat){
            tracks_playable = tracklist.tracks.slice(current_track_idx + 1); //tracks after this one
        }

        var before = tracklist.tracks.slice(0,current_track_idx); //tracks before this one
        tracks_playable = tracks_playable.concat(before);

        tracks_playable = tracks_playable.reverse();

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return track.playable;
        });


        //shuffle ?
        if (tracklist.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        return tracks_playable[0];
    }

    get_next_track(){
        var tracklist = this;
        var tracks_playable = [];

        if ( !tracklist.tracks ) return;

        var current_track_idx = $(tracklist.tracks).index( $(tracklist.current_track) );
        if (current_track_idx == -1) return; //index not found

        var tracks_playable = tracklist.tracks.slice(current_track_idx+1); //tracks after this one

        if (tracklist.can_repeat){
            var before = tracklist.tracks.slice(0,current_track_idx); //tracks before this one
            tracks_playable = tracks_playable.concat(before);
        }

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return track.playable;
        });

        //shuffle ?
        if (tracklist.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        return tracks_playable[0];
    }

    previousTrackJump(){

        var tracklist = this;
        var previousTrack = tracklist.get_previous_track();

        if (!previousTrack){
            tracklist.debug("no previous track");
            return;
        }

        tracklist.playTrack(previousTrack);
    }

    nextTrackJump(){

        var tracklist =         this;
        var nextTrack =         tracklist.get_next_track();

        if (!nextTrack){
            tracklist.debug("no next track");
            return;
        }

        var currentTrackIdx =   $(tracklist.tracks).index( tracklist.current_track );
        var nextTrackIdx =      $(tracklist.tracks).index( nextTrack );
        var isQueueLoop =       (nextTrackIdx < currentTrackIdx );

        if (isQueueLoop && wpsstmL10n.ajax_radios && tracklist.isExpired ){
            tracklist.reloadTracklist(true);
        }else{
            tracklist.playTrack(nextTrack);
        }
        /*
        We're looping the tracklist, warn the playlist - useful to reload expired tracklists.

        */


    }

    updatePlayerControls(){

        var tracklist = this;

        /*
        Previous track bt
        */

        var previousTrack = tracklist.get_previous_track();
        var hasPreviousTrack = (typeof previousTrack !== 'undefined');
        tracklist.$previousTrackBt.toggleClass('active',hasPreviousTrack);

        /*
        Next track bt
        */
        var nextTrack = tracklist.get_next_track();
        var hasNextTrack = (typeof nextTrack !== 'undefined');
        tracklist.$nextTrackBt.toggleClass('active',hasNextTrack);
    }

    playLink(link){

        if (!link) return;

        var tracklist = this;
        var success = $.Deferred();

        var $instances = link.get_instances();
        var track = link.closest('wpsstm-track');

        if( !link.playable ){

            success.reject('cannot play this link');

        }else{

            tracklist.stopCurrentMedia();

            tracklist.playerTrack = track;
            tracklist.current_link = link;

            $(track).trigger('request');

            link.get_instances().addClass('link-active link-loading');
            track.get_instances().addClass('track-active track-loading');
            $(tracklist).addClass('tracklist-loading tracklist-active tracklist-has-played');

            /*
            register new events
            */
            $(tracklist.mediaElement).one('play', function() {
                $(track).trigger('started');
                success.resolve();
            })
            .one('ended', function(error) {
                $(track).trigger('ended');
            })
            .one('error', function(error) {
                success.reject(error);
            })

            tracklist.mediaElement.setSrc(link.src);
            tracklist.mediaElement.load();
        }

        success.fail(function (reason) {
            link.debug(reason);
            tracklist.stopCurrentMedia();
        })

        return success.promise();

    }

    playTrack(track){

        if (!track) return;

        var tracklist = this;
        var success = $.Deferred();

        tracklist.stopCurrentMedia();
        tracklist.playerTrack = track;

        track.get_instances().addClass('track-active track-loading');
        $(tracklist).addClass('tracklist-loading tracklist-active tracklist-has-played');

        /*
        Wait for track to be ready
        */
        var trackready = $.Deferred();

        //should we autolink ?
        var $links = $(track).find('wpsstm-track-link');
        var $sources = $links.filter('[wpsstm-playable]');

        if ( track.can_autolink && !$sources.length ){
            trackready = track.track_autolink()
        }else{
            trackready.resolve();
        }

        /*
        Track is now ready, try to play links
        */

        trackready.always(function(v) {

            /*
            Check this is still the track requested
            */
            if ( tracklist.current_track !== track ){
                track.debug('Track switched, do not play');
                success.resolve();
                return success.promise();
            }


            /*
            Play first available link
            */

            var playableLinks = $(track).find('wpsstm-track-link').filter(function (index) {
                return this.playable;
            });

            if (!playableLinks.length){
                success.reject('No playable links found');
            }else{

                /*
                This function will loop until a promise is resolved
                */

                (function iterateLinks(index) {

                    if (index >= playableLinks.length){
                        success.reject('No playable links found');
                        return;
                    }

                    var link = playableLinks[index];
                    var playLink = tracklist.playLink(link);

                    playLink.done(function(data){
                        success.resolve();
                    })
                    .fail(function (xhr, ajaxOptions, thrownError) {
                        iterateLinks(index + 1);
                    })

                })(0);
            }

        })

        /*
        Success ?
        */

        success.done(function(){
            if (wpsstmL10n.autolink){
                tracklist.nextTracksAutolink(track);
            }
        })
        .fail(function(reason) {
            track.debug(reason);
            track.playable = false;
            tracklist.stopCurrentMedia();
            tracklist.nextTrackJump();
        })

        return success.promise();

    }

    /*
    preload links for the X next tracks
    */
    nextTracksAutolink(track){
        var tracklist = this;
        var max_items = 3; //number of following tracks to preload //TOUFIX should be in php options
        var track_index = $(tracklist.tracks).index( track );
        if (track_index < 0) return; //index not found

        //keep only tracks after this one
        var rtrack_in = track_index + 1;
        var next_tracks = $(tracklist.tracks).slice( rtrack_in );

        //consider only the next X tracks
        var tracks_slice = next_tracks.slice( 0, max_items );

        //keep only tracks that needs to be autolinked
        tracks_slice = tracks_slice.filter(function (index) {
            var playable_links = $(this).find('wpsstm-track-link[wpsstm-playable]');
            return ( this.can_autolink && !playable_links.length );
        });

        /*
        TOUFIX TOUCHECK this preloads the tracks SEQUENCIALLY
        var results = [];
        return tracks_slice.toArray().reduce((promise, track) =>{
            return promise.then((result) =>{
                return track.track_autolink().then(result => results.push(result));
            })
            .catch(console.error);
        }, Promise.resolve());
        */

        $(tracks_slice).each(function(index, track_to_preload) {
            track_to_preload.track_autolink();
        });

    }

    static _PlayerInitEvent(e){

        var tracklist = this;
        tracklist.debug("Player is ready!");

        //load link
        $(tracklist).on('click', 'wpsstm-track-link .wpsstm-track-link-action-play,wpsstm-track-link[wpsstm-playable] .wpsstm-link-title', function(e) {
            e.preventDefault();

            var link = this.closest('wpsstm-track-link');

            if ( tracklist.current_link && (link.post_id === tracklist.current_link.post_id) ){

                //toggle play/pause
                if ( tracklist.mediaElement.paused ){
                    tracklist.mediaElement.play();
                }else{
                    tracklist.mediaElement.pause();
                }

            }else{
                tracklist.playLink(link);
            }

        });

        //load track
        $(tracklist).on('click','wpsstm-track .wpsstm-track-action-play', function(e) {
            e.preventDefault();

            var track = this.closest('wpsstm-track');

            if ( tracklist.current_track && (track.queueIdx === tracklist.current_track.queueIdx) ){

                //toggle play/pause
                if ( tracklist.mediaElement.paused ){
                    tracklist.mediaElement.play();
                }else{
                    tracklist.mediaElement.pause();
                }

            }else{
                tracklist.playTrack(track);
            }

        });

        $(tracklist.mediaElement).on('loadeddata',function(e){
            var mediaElement = this;
            var tracklist = mediaElement.closest('wpsstm-tracklist');
            var track = tracklist.current_track;
            var link = tracklist.current_link;
            mediaElement.play();
        })
        .on('error', function(error) {

            var mediaElement = this;
            var tracklist = mediaElement.closest('wpsstm-tracklist');
            var track = tracklist.current_track;
            var link = tracklist.current_link;

            link.get_instances().toArray().forEach(function(item) {
                item.playable = false;
            });

        })
        .on('play', function() {

            var mediaElement = this;
            var tracklist = mediaElement.closest('wpsstm-tracklist');
            var track = tracklist.current_track;
            var link = tracklist.current_link;

            link.get_instances().toArray().forEach(function(item) {
                item.playable = true;
            });

            $(tracklist).removeClass('tracklist-loading').addClass('tracklist-playing tracklist-has-played');
            track.get_instances().removeClass('track-loading').addClass('track-playing track-has-played');
            link.get_instances().removeClass('link-loading').addClass('link-playing link-has-played');

        })
        .on('pause', function() {

            var mediaElement = this;
            var tracklist = mediaElement.closest('wpsstm-tracklist');
            var track = tracklist.current_track;
            var link = tracklist.current_link;

            $(tracklist).removeClass('tracklist-playing');
            track.get_instances().removeClass('track-playing');
            link.get_instances().removeClass('link-playing');

        })
        .on('ended', function() {

            var mediaElement = this;
            var tracklist = mediaElement.closest('wpsstm-tracklist');
            var track = tracklist.current_track;
            var link = tracklist.current_link;

            tracklist.stopCurrentMedia();
            //Play next song if any
            tracklist.nextTrackJump();
        });

        /*
        Autoplay ?
        */
        var tracklist = this;
        var autoplayTrack = $(tracklist.tracks).filter('.track-autoplay').get(0);

        //wait a few seconds so we're sure that links are initialized.
        //https://stackoverflow.com/questions/58354531/custom-elements-connectedcallback-wait-for-parent-node-to-be-available-bef/58362114#58362114
        if (autoplayTrack){
            tracklist.debug("autoplay track...");
            setTimeout(function(){ tracklist.playTrack(autoplayTrack); }, 1000);
        }

    }

    static _startedTrackEvent(e){

        var track = e.target;
        var tracklist = track.closest('wpsstm-tracklist');

        //add to history
        var lastPlayedTrack = tracklist.tracksHistory[tracklist.tracksHistory.length-1];
        if (lastPlayedTrack !== track){
            tracklist.tracksHistory.push(track); //history
        }

        //local scrobble

        var track = this;

        var ajax_data = {
            action:     'wpsstm_track_start',
            track:      track.to_ajax(),
        };

        var request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            track.debug(ajax_data,"track start request failed");
        })
    }

    stopCurrentMedia(){
        var tracklist = this;
        var mediaElement = tracklist.mediaElement;
        if (!mediaElement) return;

        //stop current media
        mediaElement.pause();
        mediaElement.currentTime = 0;

        $(tracklist).removeClass('tracklist-playing');

        var track =     tracklist.current_track;
        var link =      tracklist.current_link;

        $(track).trigger('stopped');

        if (track){
            track.get_instances().removeClass('track-active track-loading track-playing');
        }

        if (link){
            link.get_instances().removeClass('link-active link-loading link-playing');
        }

    }

}

window.customElements.define('wpsstm-tracklist', WpsstmTracklist);
