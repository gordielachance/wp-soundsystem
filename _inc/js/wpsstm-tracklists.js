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
        this.current_track =    undefined;

        this.mediaElement =    undefined;
        this.tracksHistory =    [];
        
        this.$player =          undefined;
        this.$shuffleTracksBt = undefined;
        this.$loopTracksBt =    undefined;
        this.$previousTrackBt = undefined;
        this.$nextTrackBt =     undefined;
        this.isRevertPlay =     false;

        //Setup listeners
        $(this).on('queueLoop',WpsstmTracklist._queueLoopEvent);
        $(this).on('loaded', WpsstmTracklist._loadedEvent);
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

    render(){

        var tracklist =                 this;
        tracklist.post_id =             Number( $(tracklist).data('wpsstm-tracklist-id') );
        tracklist.is_shuffle =          ( localStorage.getItem("wpsstm-shuffle-tracklist") == 'true' );
        tracklist.can_repeat =          ( ( localStorage.getItem("wpsstm-loop-tracklist") == 'true' ) || !localStorage.getItem("wpsstm-loop-tracklist") );
        
        tracklist.$player =             $(tracklist).find('.wpsstm-player');
        tracklist.$shuffleTracksBt =    $(tracklist).find('.wpsstm-shuffle-bt');
        tracklist.$loopTracksBt =       $(tracklist).find('.wpsstm-loop-bt');
        tracklist.$previousTrackBt =    $(tracklist).find('.wpsstm-previous-track-bt');
        tracklist.$nextTrackBt =        $(tracklist).find('.wpsstm-next-track-bt');

        tracklist.init_tracklist_expiration();

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
                var autoplay = ( tracklist.current_track && (tracklist.current_track.status === 'playing') );
                tracklist.reload_tracklist(autoplay);
            });
        }
        
        /*
        Play
        */
        
        //container play icon
        $(tracklist).on('click', '.wpsstm-tracklist-play-bt', function(e) {
            var tracklist = this.closest('wpsstm-tracklist');
            var $tracks = tracklist.get_queue();
            var track = (tracklist.current_track) ? tracklist.current_track : $tracks.get(0);
            if (!track) return;
            
            $(track).find('.wpsstm-track-action-play').trigger('click');

        });
        
        /*
        Confirmation popup is a media is playing and that we leave the page
        //TO FIX TO improve ?
        */

        $(window).bind('beforeunload', function(){

            if (tracklist.mediaElement && !tracklist.mediaElement.paused){
                return wpsstmL10n.leave_page_text;
            }

        });
        
        var $tracks = tracklist.get_queue();
        $tracks.on('skip', function(e) {
            var track = e.target;
            if(!tracklist.isRevertPlay){
                tracklist.next_track_jump();
            }else{
                tracklist.previous_track_jump();
            }
        });
        
        /*
        Player track : scroll to page track
        */
        //Scroll to page track
        $(tracklist).on('click', '.player-track .wpsstm-track-position', function(e) {
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
            
            tracklist.update_player_controls();

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
        
        //TOUFIX URGENT
        //autoreload should only happen one time.  What if we don't have any cache_min ? For now, it will reload infinitly...
        if (wpsstmL10n.ajax_radios && tracklist.isExpired){
            tracklist.reload_tracklist();
        }else{
            $(tracklist).trigger('loaded');
        }
    }
    
    reload_tracklist(autoplay){
        var tracklist = this;
        var success = $.Deferred();
        
        var abord_reload = function(e) {
            if ( (e.key === "Escape") ) { // escape key maps to keycode `27`
                request.abort();
            }
        }

        tracklist.debug("reload tracklist...");
        if (autoplay){
            tracklist.debug("...and autoplay !");
        }
        
        var ajax_data = {
            action:     'wpsstm_reload_tracklist',
            tracklist:      tracklist.to_ajax(),
        };
        
        $(tracklist).addClass('tracklist-reloading');
        
        tracklist.stop_current_link();

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
                If the tracklist WAS playing, keep those classes (used for autoplay).
                */

                if (autoplay){
                    $(newTracklist).find('wpsstm-track:first-child').addClass('track-autoplay');
                }

                /*
                Swap content
                */
                tracklist.replaceWith( newTracklist );
                
                //Keep player intact so we don't mess with the Autoplay Policy
                var oldPlayer = tracklist.$player;
                var newPlayer = newTracklist.$player;
                newPlayer.replaceWith( oldPlayer );
                
                tracklist = newTracklist;

                success.resolve(newTracklist);
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
            success.reject(thrownError);
        })
        .always(function () {
            $(tracklist).removeClass('tracklist-reloading');
            $(document).unbind( "keyup.reloadtracklist", abord_reload );
            $(tracklist).trigger('loaded');
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
                success.reject();
            }else{
                success.resolve();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
            row.addClass('action-error');
            success.reject();
        })
        .always(function() {
            row.removeClass('action-loading wpsstm-freeze');
        })
        
        return success.promise();
    }
    
    get_previous_track(){
        var tracklist = this;
        var tracks_playable = [];

        var $tracks = tracklist.get_queue();
        if ( !$tracks.length ) return;
        
        var current_track_idx = $tracks.index( $(tracklist.current_track) );
        if (current_track_idx == -1) return; //index not found
        
        var tracksArr = $tracks.toArray();

        if (tracklist.can_repeat){
            tracks_playable = tracksArr.slice(current_track_idx + 1); //tracks after this one
        }

        var before = tracksArr.slice(0,current_track_idx); //tracks before this one
        tracks_playable = tracks_playable.concat(before);

        tracks_playable = tracks_playable.reverse();

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return ( track.playable || track.ajax_details );
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

        var $tracks = tracklist.get_queue();
        if ( !$tracks.length ) return;
        
        var current_track_idx = $tracks.index( $(tracklist.current_track) );
        if (current_track_idx == -1) return; //index not found
        
        var tracksArr = $tracks.toArray();

        var tracks_playable = tracksArr.slice(current_track_idx+1); //tracks after this one

        if (tracklist.can_repeat){
            var before = tracksArr.slice(0,current_track_idx); //tracks before this one
            tracks_playable = tracks_playable.concat(before);
        }

        //which one should we play?
        tracks_playable = tracks_playable.filter(function (track) {
            return ( track.playable || track.ajax_details );
        });

        //shuffle ?
        if (tracklist.is_shuffle){
            tracks_playable = wpsstm_shuffle(tracks_playable);
        }

        return tracks_playable[0];
    }
    
    previous_track_jump(){

        var tracklist = this;
        var $tracks = tracklist.get_queue();
        var previousTrack = tracklist.get_previous_track();

        if (previousTrack){
            var success = previousTrack.play_track();
            
            success.done(function(){
                tracklist.isRevertPlay = false;
            })
            
        }else{
            tracklist.debug("no previous track");
        }
    }
    
    next_track_jump(){

        var tracklist =         this;
        var $tracks =           tracklist.get_queue();
        var nextTrack =         tracklist.get_next_track();
        var currentTrackIdx =   $tracks.index( tracklist.current_track );
        var nextTrackIdx =      $tracks.index( nextTrack );

        if (nextTrack){
            nextTrack.play_track();
            
        }else{
            tracklist.debug("no next track");
        }
        
        /*
        We're looping the tracklist, warn the playlist - useful to reload expired tracklists.
        
        */
        if (nextTrackIdx < currentTrackIdx ){
            $(tracklist).trigger('queueLoop');
        }

    }

    update_player_controls(){

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
    
    update_player_track(){
        var tracklist = this;
        var $container = tracklist.$player.find('.player-track');
        var playerTrack = $container.find('wpsstm-track').get(0);
        var currentTrack = tracklist.current_track;
        
        
        //we're trying to load the same track.
        if ( playerTrack && (currentTrack.queueIdx == playerTrack.queueIdx) ){
            return;
        }

        var $clone = $(currentTrack).clone();

        $container.empty().append( $clone );
        
        //once rendered, update clone to match the track values
        $clone.get(0).queueIdx = tracklist.current_track.queueIdx;
    }
    
    get_queue(){
        var tracklist = this;
        return $(tracklist).find('.wpsstm-tracks-list wpsstm-track');
    }
    
    get_playerTrack(){
        if (!this.$player.length) return;
        return this.$player.find('.player-track wpsstm-track').get(0);
    }
    
    static _queueLoopEvent(e){
        var tracklist = e.target;
        tracklist.debug("Tracklist loop");
        //if ( wpsstmL10n.ajax_radios && tracklist.isExpired ){
            tracklist.reload_tracklist(true);
        //}
    }
    
    static _loadedEvent(e){
        var tracklist = e.target;

        tracklist.debug("Tracklist loaded");

        var $tracks = tracklist.get_queue();
        
        /*
        AJAX load details ?
        */
        $tracks.each(function( index ) {
            var track = this;
            if ( track.ajax_details ){
                track.load_details();
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
                tracklist.debug("MediaElementJS ready");
                
                /*
                Autoplay
                */
                var autoplayTrack = $tracks.filter('.track-autoplay').get(0);
                if (autoplayTrack){
                    $(document).one('wpsstmPlayerReady',function(){
                        tracklist.debug("autoplay track");
                        autoplayTrack.play_track();
                    });
                }
                
                $(document).trigger("wpsstmPlayerReady",[tracklist]);
                
                
            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
            }
        });
  
    }
    
    stop_current_link(){
        var tracklist = this;
        if (!tracklist.current_track) return;
        if (!tracklist.current_track.current_link) return;
        tracklist.current_track.current_link.status = '';//stop current link
    }
 
}

window.customElements.define('wpsstm-tracklist', WpsstmTracklist);