var $ = jQuery.noConflict();

//tracklist modals
$('body.wpsstm-popup').on('click', 'a.wpsstm-tracklist-popup,li.wpsstm-tracklist-popup>a', function(e) {
    e.preventDefault();

    var content_url = this.href;

    console.log("tracklist popup");
    console.log(content_url);


    var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
    var popup = $('<div></div>').append(loader_el);

    var popup_w = $(window).width() *.75;
    var popup_h = $(window).height() *.75;

    popup.dialog({
        width:popup_w,
        height:popup_h,
        modal: true,
        dialogClass: 'wpsstm-tracklist-dialog wpsstm-dialog dialog-loading',

        open: function(ev, ui){
            $('body').addClass('wpsstm-popup-overlay');
            var dialog = $(this).closest('.ui-dialog');
            var dialog_content = dialog.find('.ui-dialog-content');
            var iframe = $('<iframe src="'+content_url+'"></iframe>');
            dialog_content.append(iframe);
            iframe.load(function(){
                dialog.removeClass('dialog-loading');
            });
        },
        close: function(ev, ui){
            $('body').removeClass('wpsstm-popup-overlay');
        }

    });

});

class WpsstmTracklist extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.index =            undefined;
        this.post_id =          undefined;
        this.isExpired =        undefined;
        this.current_link =     undefined;
        this.current_track =    undefined;

        this.current_media =    undefined;
        this.tracksHistory =    [];
        
        this.$player =          undefined;
        this.$shuffleTracksBt = undefined;
        this.$loopTracksBt =    undefined;
        this.$previousTrackBt = undefined;
        this.$nextTrackBt =     undefined;

        // Setup a click listener on <wpsstm-tracklist> itself.
        //TOUFIX TOUCHECK WHYFOR ? URGENT
        this.addEventListener('click', e => {
        });
    }
    connectedCallback(){
        //console.log("TRACKLIST CONNECTED!");
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
        return ['wpsstm-playable'];
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
    
    debug(msg){
        var debug = {message:msg,tracklist:this};
        wpsstm_debug(debug);
    }

    render(){

        var tracklist =                 this;
        tracklist.post_id =             Number( $(tracklist).data('wpsstm-tracklist-id') );
        tracklist.is_shuffle =          ( localStorage.getItem("wpsstm-shuffle-tracklist") == 'true' );
        tracklist.can_repeat =          ( ( localStorage.getItem("wpsstm-loop-tracklist") == 'true' ) || !localStorage.getItem("wpsstm-loop-tracklist") );
        
        tracklist.$player =             $(tracklist).find('wpsstm-player');
        tracklist.$shuffleTracksBt =    $(tracklist).find('.wpsstm-shuffle-bt');
        tracklist.$loopTracksBt =       $(tracklist).find('.wpsstm-loop-bt');
        tracklist.$previousTrackBt =    $(tracklist).find('.wpsstm-previous-track-bt');
        tracklist.$nextTrackBt =        $(tracklist).find('.wpsstm-next-track-bt');

        tracklist.init_tracklist_expiration();
        
        /*
        Player
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
                tracklist.current_media = mediaElement;
                tracklist.debug("MediaElementJS ready");
                $(document).trigger( "wpsstmPlayerInit", [tracklist] ); //custom event
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
            }
        });

        /*
        New subtracks
        */

        var queue_tracks_form = $(tracklist).find('#wpsstm-queue-tracks');
        var queue_tracks_submit = queue_tracks_form.find('#wpsstm-queue-tracks-submit');
        var queue_more_tracks = queue_tracks_form.find('#wpsstm-queue-more-tracks');

        //add new track row
        queue_more_tracks.on( "click", function(e) {
            e.preventDefault();
            var last_row = queue_tracks_form.find('.wpsstm-new-track').last();

            var new_row = last_row.clone();
            new_row.find('input').val('');
            new_row.removeClass('wpsstm-new-track-ready');
            new_row.insertAfter( last_row );
        });
        
        //remove new track row
        queue_tracks_form.on( "click",'.wpsstm-remove-new-track-row', function(e) {
            var row = $(this).parents('.wpsstm-new-track');
            row.remove();
        });
        
        
        //submit tracks
        queue_tracks_submit.click(function(e) {
            
            e.preventDefault();
            
            var isExpanded = queue_tracks_form.hasClass('expanded');
            
            if (!isExpanded){
                queue_tracks_form.addClass('expanded');
            }else{
                
                var rows = queue_tracks_form.find('.wpsstm-new-track');
                var doReload = false;
                var ajaxCalls = [];
                
                queue_tracks_form.addClass('wpsstm-freeze');

                rows.each(function( index ) {
                    var row = $(this);
                    var track = new WpsstmTrack();
                    track.track_artist = row.find('input[name="wpsstm_track_data[artist]"]').val();
                    track.track_title = row.find('input[name="wpsstm_track_data[title]"]').val();
                    track.track_album = row.find('input[name="wpsstm_track_data[album]"]').val();
                    
                    var ajax = tracklist.new_subtrack(track,row).done(function() { //at least one track added, we'll need to reload the tracklist
                        doReload = true;
                        row.remove();
                    });

                    ajaxCalls.push(ajax);
                    
                });

                //TOUFIX BROKEN
                //should be fired when all promises have returned a response, no matter if it succeeded or not.
                $.when.apply($, ajaxCalls).always(function(){
                    queue_tracks_form.removeClass('wpsstm-freeze');
                    if (doReload){
                        tracklist.reload_tracklist();
                    }
                })
            }

        });

        /*
        Refresh
        */
        if (wpsstmL10n.ajax_radios){
            var refresh_bt = $(tracklist).find(".wpsstm-reload-bt");
            refresh_bt.click(function(e) {
                e.preventDefault();
                tracklist.debug("clicked 'refresh' bt");
                tracklist.reload_tracklist();
            });

            $(tracklist).on( "wpsstmTracklistLoop", function( event ) {
                tracklist.debug("tracklist loop");
                if (tracklist.isExpired){
                    tracklist.reload_tracklist(true);
                }
            });
        }
        
        /*
        Play
        */
        
        //container play icon
        $(tracklist).on('click', '.wpsstm-tracklist-play-bt', function(e) { //TOUFIX URGENT

            var $tracks = tracklist.get_queue();
            var activeTrack = $tracks.filter('.track-active').get(0);
            var trackIdx = $tracks.index( activeTrack );
            trackIdx = (trackIdx > 0) ? trackIdx : 0;

            tracklist.play_queue_track(trackIdx);
        });
        
        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */

        $(window).bind('beforeunload', function(){

            if (tracklist.current_link && !tracklist.current_media.paused){
                return wpsstmL10n.leave_page_text;
            }

        });
        
        /*
        Previous track
        */
        $(tracklist).find('.wpsstm-previous-track-bt').click(function(e) {
            e.preventDefault();
            tracklist.previous_track_jump();
        });
        
        /*
        Next track
        */
        $(tracklist).find('.wpsstm-next-track-bt').click(function(e) {
            e.preventDefault();
            tracklist.next_track_jump();
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
            
            tracklist.update_player();

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
        Tracklist actions
        */

        //toggle favorite
        $(tracklist).find('.wpsstm-tracklist-action-favorite,.wpsstm-tracklist-action-unfavorite').click(function(e) {
            e.preventDefault();
            
            var do_love = $(this).hasClass('wpsstm-tracklist-action-favorite');

            tracklist.toggle_favorite_tracklist(do_love);
        });


        /*
        Subtracks
        */

        var $tracks = tracklist.get_queue();

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
                var track = $tracks.get(startSortIdx);
                var old_position = Number($(track).attr('data-wpsstm-subtrack-position'));
                var new_position = ui.item.index() + 1;


                if (track){
                    //new position
                    track.position = ui.item.index();
                    tracklist.update_subtrack_position(track,new_position);
                }

            }
        });

        tracklist.debug("Tracklist ready");
        
        $(document).trigger("wpsstmTracklistReady",[tracklist]); //custom event
    }

    reload_tracklist(autoplay){
        var tracklist = this;
        
        //stop player
        //TOUFIX URGENT
        
        tracklist.debug("reload tracklist... autoplay ?" + autoplay);
        
        var ajax_data = {
            action:     'wpsstm_reload_tracklist',
            tracklist:      tracklist.to_ajax(),
        };
        
        var abord_reload = function(e) {
             if ( (e.key === "Escape") ) { // escape key maps to keycode `27`
                xhr.abort();
            }
        }

        var xhr = $.ajax({
            type:           "post",
            url:            wpsstmL10n.ajaxurl,
            data:           ajax_data,
            dataType:       'json',
            beforeSend:     function() {
                $(tracklist).addClass('tracklist-reloading');
                $(document).bind( "keyup.reloadtracklist", abord_reload ); //use namespace - https://acdcjunior.github.io/jquery-creating-specific-event-and-removing-it-only.html
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    
                    /*
                    If the tracklist WAS playing, keep those classes (used for autoplay).
                    */
                    var newTracklist = $(data.html).get(0);

                    if (autoplay){
                        $(newTracklist).find('wpsstm-track:first-child').addClass('track-autoplay');
                    }

                    //swap content
                    tracklist.replaceWith( newTracklist );
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(tracklist).removeClass('tracklist-reloading');
                $(document).unbind( "keyup.reloadtracklist", abord_reload );
            }
        })
        
        return xhr;
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

        return $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
            beforeSend: function() {
                link_el.removeClass('action-error');
                link_el.addClass('action-loading');
            },
            success: function(data){

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
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                link_el.removeClass('action-loading');
            }
        })
    }
    
    init_tracklist_expiration(){
        var tracklist = this;

        var now = Math.round( $.now() /1000);
        var remaining_sec = undefined;

        var meta_expiration = $(tracklist).find('meta[itemprop="wpsstmRefreshTimer"]');
        if (!meta_expiration.length) return;
            
        remaining_sec = parseInt( meta_expiration.attr('content') );

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
        
        if (remaining_sec < 0){
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

        $.ajax({
            type: "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(track).addClass('track-loading');
                link_el.removeClass('action-error');
                link_el.addClass('action-loading');
            },
            success: function(data){

                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    tracklist.refresh_tracks_positions();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(track).removeClass('track-loading');
                link_el.removeClass('action-loading');
            }
        })

    }

    //reduce object for communication between JS & PHP
    to_ajax(){

        var tracklist = this;
        var allowed = ['post_id'];
        var filtered = Object.keys(tracklist)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
            obj[key] = tracklist[key];
            return obj;
        }, {});
        return filtered;
    }
    
    new_subtrack(track,row){
        
        var tracklist = this;
        var success = $.Deferred();

        var ajax_data = {
            action:         'wpsstm_tracklist_new_subtrack',
            track:          track.to_ajax(),
            tracklist_id:   tracklist.post_id
        };

        var ajax = $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',

            beforeSend: function() {
                row.removeClass('action-error').addClass('action-loading wpsstm-freeze');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    success.reject();
                }else{
                    success.resolve();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                row.addClass('action-error');
                success.reject();
            },
            complete: function() {
                row.removeClass('action-loading wpsstm-freeze');
            }
        })
        
        return success.promise();
    }
    
    get_previous_track_idx(){
        var tracklist = this;
        var tracks_playable = [];

        var $tracks = tracklist.get_queue();
        var activeTrack = $tracks.filter('.track-active').get(0);
        var current_track_idx = $tracks.index( $(activeTrack) );
        if (current_track_idx == -1) return; //index not found
        
        var tracksArr = $tracks.toArray();

        if (tracksArr){

            if (tracklist.can_repeat){
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
        if (tracklist.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        var previous_track = tracks_playable[0];
        var previous_track_idx = ( previous_track ) ? $tracks.index( previous_track ) : undefined;
        
        return previous_track_idx;
    }
    
    get_next_track_idx(){
        var tracklist = this;
        var tracks_playable = [];

        var $tracks = tracklist.get_queue();
        var activeTrack = $tracks.filter('.track-active').get(0);
        var current_track_idx = $tracks.index( $(activeTrack) );
        if (current_track_idx == -1) return; //index not found
        
        var tracksArr = $tracks.toArray();

        if (tracksArr){
            var tracks_playable = tracksArr.slice(current_track_idx+1); //tracks after this one
            
            if (tracklist.can_repeat){
                var before = tracksArr.slice(0,current_track_idx); //tracks before this one
                tracks_playable = tracks_playable.concat(before);
            }
            
        }

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return ( track.playable || track.ajax_details );
        });

        //shuffle ?
        if (tracklist.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        var next_track = tracks_playable[0];
        var next_track_idx = ( next_track ) ? $tracks.index( next_track ) : undefined;

        //console.log("get_next_track_idx: current: " + current_track_idx + ", next;" + next_track_idx);

        return next_track_idx;
    }
    
    previous_track_jump(){
        var tracklist = this;
        var $tracks = tracklist.get_queue();
        var current_track_idx = $tracks.index( $(tracklist.current_track) );
        var track_idx = tracklist.get_previous_track_idx();

        if (typeof track_idx !== 'undefined'){
            tracklist.play_queue_track(track_idx);
        }else{
            tracklist.debug("no previous track");
            tracklist.current_track.status = '';
        }
    }
    
    next_track_jump(){
        var tracklist = this;
        var $tracks = tracklist.get_queue();
        var current_track_idx = $tracks.index( $(tracklist.current_track) );
        var track_idx = tracklist.get_next_track_idx();

        if (typeof track_idx !== 'undefined'){
            tracklist.play_queue_track(track_idx);
        }else{
            tracklist.debug("no next track");
            tracklist.current_track.status = '';
        }
        
        /*
        We're looping the tracklist, warn the playlist - useful to reload expired tracklists.
        
        */
        if (track_idx < current_track_idx){
            $(tracklist).trigger('wpsstmTracklistLoop');
        }

    }
    
    play_queue_track(track_idx,link_idx){
        var tracklist = this;
        
        track_idx = (typeof track_idx !== 'undefined') ? track_idx : 0;
        link_idx = (typeof link_idx !== 'undefined') ? link_idx : 0;
        var $tracks = tracklist.get_queue();
        var requestedTrack = $tracks.get(track_idx);
        var requestedLink = $(requestedTrack).find('wpsstm-track-link').get(link_idx);
        
        var currentIdx = Array.from($tracks).indexOf(tracklist.current_track);
        
        console.log(
            {
                requested_idx:track_idx,
                current_idx:currentIdx,
                requested:requestedTrack,
                current:tracklist.current_track
            }
        )

        if (tracklist.current_track){

            //reclick
            if ( ( tracklist.current_track === requestedTrack ) && ( tracklist.current_link === requestedLink) ){

                requestedTrack.debug('reclick');
                var isPlaying = ( requestedTrack.status == 'playing' );
                
                requestedTrack.debug('is playing ? ' + isPlaying);

                if ( isPlaying ){
                    tracklist.current_media.pause();
                }else{
                    tracklist.current_media.play();
                }

                return;
            }
            
            tracklist.current_track.status = '';
        }

        tracklist.current_track = requestedTrack;
        requestedTrack.status = 'request';
        
        /*
        Autolink ?
        */
        
        var trackready = $.Deferred();
        trackready.resolve();//URGENT
        /*

        if ( requestedTrack.playable ){
            var sourceLinks = $(requestedTrack).find('wpsstm-track-link').filter('[wpsstm-playable]');

            if(sourceLinks.length){
                trackready.resolve();
            }else{
                if ( requestedTrack.ajax_details ){
                    trackready = requestedTrack.track_autolink();
                }else{
                    trackready.reject();
                }
            }

        }else{
            trackready.reject();
        }
        */
        
        /*
        Track is now ready (or not, here I come)
        */

        trackready.then(
            function (value) { //success
                //check that it still the same track that is requested
                if (tracklist.current_track !== requestedTrack) return;
                requestedTrack.play_track(link_idx);

            }, function (error) { //error
                requestedTrack.status = '';
                tracklist.next_track_jump();
                return;
                
            }
        );


    }

    update_player(){
        
        var tracklist = this;
        
        /*
        Current Track
        */

        var playerTrackContainer = tracklist.$player.find('.player-track');
        var $currenTrack = $(tracklist.current_track);
        var $clone = $currenTrack.clone();
        playerTrackContainer.empty().append( $clone );
        
        //Scroll to page track
        $clone.on('click', '.wpsstm-track-position', function(e) {
            e.preventDefault();

            //https://stackoverflow.com/a/6677069/782013
            $('html, body').animate({
                scrollTop: $currenTrack.offset().top - ( $(window).height() / 3) //not at the very top
            }, 500);

        });
        
        
        /*
        Previous track bt
        */

        var previous_track_idx = tracklist.get_previous_track_idx();
        var hasPreviousTrack = (typeof previous_track_idx !== 'undefined');
        tracklist.$previousTrackBt.toggleClass('active',hasPreviousTrack);

        /*
        Next track bt
        */
        var next_track_idx = tracklist.get_next_track_idx();
        var hasNextTrack = (typeof next_track_idx !== 'undefined');
        tracklist.$nextTrackBt.toggleClass('active',hasNextTrack);
    }
    
    get_queue(){
        var tracklist = this;
        return $(tracklist).find('.wpsstm-tracks-list wpsstm-track');
    }
 
}

window.customElements.define('wpsstm-tracklist', WpsstmTracklist);

//TOUFIX MOUVE ELSEWHERE ? URGENT
$(document).on( "wpsstmSourceInit", function( event, link ) {

    var track =                 link.closest('wpsstm-track');
    var scrobble_icon =         $(track.tracklist).find('.wpsstm-player-action-scrobbler');
    var scrobbler_enabled =     scrobble_icon.hasClass('active');

    var startTrack = function(){
        $(document).trigger('wpsstmTrackStart',track);
    }

    //start track event, fired only once
    $(track.tracklist.current_media).one('play', startTrack);

});
