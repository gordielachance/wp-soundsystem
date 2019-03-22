var $ = jQuery.noConflict();

//play/pause track button
$(document).on('click','.wpsstm-track-play-bt', function(e) {
    e.preventDefault();
    var track = this.closest('wpsstm-track');
    var player;
    var trackIdx;

    if (track.queueNode){ //page track, get the queue track
        track = track.queueNode;
    }
    
    player = track.closest('wpsstm-player');
    trackIdx = Array.from(track.parentNode.children).indexOf(track);
    player.play_queue(trackIdx);

});

class WpsstmTrack extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.tracklist =            undefined;
        this.position =             undefined;
        this.track_artist =         undefined;
        this.track_title =          undefined;
        this.track_album =          undefined;
        this.subtrack_id =          undefined;
        this.post_id =              undefined;
        this.can_play =             undefined;
        this.pageNode =             undefined;
        this.queueNode =            undefined;
        
        this.did_sources_request =  undefined;

        // Setup a click listener on <wpsstm-tracklist> itself.
        this.addEventListener('click', e => {
        });

    }
    connectedCallback(){
        //console.log("TRACK CONNECTED!");
        //console.log(this);
        /*
        Called every time the element is inserted into the DOM. Useful for running setup code, such as fetching resources or rendering. Generally, you should try to delay work until this time.
        */
        this.render();
    }

    disconnectedCallback(){
        /*
        Called every time the element is removed from the DOM. Useful for running clean up code.
        */
        //console.log("TRACK DISCONNECTED!");
        var track = this;
        
        if ( track.queueNode ){ //page track
            //track.debug("remove queue node");
            //Track removed from page, remove it from queue too
            track.queueNode.remove();
        }

    }
    attributeChangedCallback(attrName, oldVal, newVal){
        
        var isValueChanged = (newVal !== oldVal);
        if (!isValueChanged) return;
        
        var track = this;
        var trackInstances = track.get_instances();
        var player = track.closest('wpsstm-player');
        
        //track.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
            case 'trackstatus':

                if (!newVal){
                    var track_instances = track.get_instances();
                    track_instances.removeClass('track-active track-loading');
                    track.end_track();
                }

                if (newVal == 'request'){
                    player.setup_track(track); 
                    trackInstances.addClass('track-loading');
                    
                }
                
                if ( newVal == 'playing' ){
                    trackInstances.removeClass('track-loading');
                }

            break;
        }
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return ['trackstatus'];
    }
    
    ///
    ///

    debug(msg){
        var debug = {message:msg,track:this};
        wpsstm_debug(debug);
    }

    render(){
        
        var track = this;

        track.tracklist =            track.closest('wpsstm-tracklist');
        track.position =             Number($(track).attr('data-wpsstm-subtrack-position')); //index in tracklist
        track.track_artist =         $(track).find('[itemprop="byArtist"]').text();
        track.track_title =          $(track).find('[itemprop="name"]').text();
        track.track_album =          $(track).find('[itemprop="inAlbum"]').text();
        track.post_id =              Number($(track).attr('data-wpsstm-track-id'));
        track.subtrack_id =          Number($(track).attr('data-wpsstm-subtrack-id'));
        track.did_sources_request =  $(track).hasClass('track-autosourced');

        var player = track.closest('wpsstm-player');

        /*
        populate existing sources
        */
        var trackSources = $(track).find('wpsstm-source');

        if (!trackSources.length && track.did_sources_request){
            $(track).addClass('track-error');
        }

        var toggleSourcesEl = $(track).find('.wpsstm-track-action-toggle-sources a');
        var sourceCountEl = toggleSourcesEl.find('.wpsstm-sources-count');
        if ( !sourceCountEl.length ){ //create item
            sourceCountEl = $('<span class="wpsstm-sources-count"></span>');
            toggleSourcesEl.append(sourceCountEl);            
        }

        $(track).attr('data-wpsstm-sources-count',trackSources.length);
        sourceCountEl.text(trackSources.length);

        //manage single source
        
        trackSources.each(function() {
            var sources_container = $(this);
            var sources_list_el = sources_container.find('.wpsstm-track-sources-list');

            // sort track sources
            sources_list_el.sortable({
                axis: "y",
                items : "[data-wpsstm-source-id]",
                handle: '.wpsstm-source-action-move a',
                update: function(event, ui) {

                    var sourceOrder = sources_list_el.sortable('toArray', {
                        attribute: 'data-wpsstm-source-id'
                    });

                    var reordered = track.update_sources_order(sourceOrder); //TOUFIX bad logic

                }
            });

        });
        
        
        

        //track popups within iframe
        $(track).on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
            e.preventDefault();

            var content_url = this.href;

            console.log("track popup");
            console.log(content_url);


            var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
            var popup = $('<div></div>').append(loader_el);

            var popup_w = $(window).width();
            var popup_h = $(window).height();

            popup.dialog({
                width:popup_w,
                height:popup_h,
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

        //toggle favorite
        $(track).on('click','.wpsstm-track-action-favorite a,.wpsstm-track-action-unfavorite a', function(e) {

            e.preventDefault();

            var action_el = $(this).parents('.wpsstm-track-action');
            var do_love = action_el.hasClass('action-favorite');
            
            track.toggle_favorite(do_love);        

        });

        //dequeue
        $(track).on('click','.wpsstm-track-action-dequeue a', function(e) {
            e.preventDefault();
            track.dequeue_track();
        });

        //delete
        $(track).on('click','.wpsstm-track-action-trash a', function(e) {
            e.preventDefault();
            track.trash_track();
        });

        //sources

        var toggleSourcesEl = $(track).find('.wpsstm-track-action-toggle-sources a');

        toggleSourcesEl.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-sources-list').toggleClass('active');
        });

    }

    get_instances(){
        var track = this;
        var instances = [];
        
        instances.push(track);
        instances.push(track.pageNode);
        instances.push(track.queueNode);
        
        instances = instances.filter(Boolean); //remove falsy
        
        return $(instances);
    }
    
    maybe_load_sources(){

        var track = this;
        var success = $.Deferred();
        var can_autosource = true; //TOUFIX should be from localized var
        var sources = $(track).find('wpsstm-source');

        if (sources.length > 0){
            success.resolve();
            
        }else if ( !can_autosource ){
            success.resolve("Autosource is disabled");
            
        } else if ( track.did_sources_request ) {
            track.debug("we already did sources requests");
            success.resolve("already did sources auto request for track #" + track.position);
            
        } else{
            success = track.get_track_sources_request();
        }
        
        success.always(function() {
            var sources = $(track).find('wpsstm-source');
            track.can_play = (sources.length > 0);    
        });

        return success.promise();
    }

    get_track_sources_request() {

        var track = this;
        var track_instances = track.get_instances();
        var success = $.Deferred();

        track_instances.addClass('track-sources-loading');

        var ajax_data = {
            action:     'wpsstm_track_autosource',
            track:      track.to_ajax(),   
        };

        var sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        sources_request.done(function(data) {
            
            var reloadTrack = track.reload_track();
            
            //a track post has been created/updated while autosourcing. Refresh it.
            reloadTrack.then(
                function(success_msg){
                    
                    if ( data.success === true ){
                        success.resolve();
                    }else{
                        track.debug("track sources request failed: " + data.message);
                        success.reject(data.message);
                    }
                    
                },
                function(error_msg){
                    success.reject(error_msg);
                }
            );

        });

        success.fail(function() {
            track_instances.addClass('track-error');
        });

        success.always(function() {
            track_instances.removeClass('track-sources-loading');
        });
        
        return success.promise();

    }
    
    reload_track(){
        
        var track = this;
        var success = $.Deferred();
        var track_instances = track.get_instances();

        track_instances.addClass('track-reloading');
        
        var ajax_data = {
            action:     'wpsstm_track_html',
            track:      track.to_ajax(),   
        };

        var sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        sources_request.done(function(data) {
            if ( data.html ){

                /*delete old nodes, and and new ones with pageNode/queueNode properties*/
                
                var newContent = $(data.html);
                
                //important ! Settings this class assure us that the node will have did_sources_request = true when it will be inserted in DOM
                $(newContent).addClass('track-autosourced');

                var newQueueTrack = newContent.get(0);
                var newPageTrack = newContent.clone().get(0);

                var oldQueueNode = track.parentNode;
                var oldPageNode = track.pageNode;

                newQueueTrack.pageNode = newPageTrack;
                newPageTrack.queueNode = newQueueTrack;

                oldQueueNode.replaceChild(newQueueTrack, track); //replace in queue
                oldPageNode.parentNode.replaceChild(newPageTrack, oldPageNode); //replace in page


                success.resolve();
                
            }else{
                track.debug("track refresh failed: " + data.message);
                success.reject(data.message);
            }
        });
        
        success.fail(function() {
            track_instances.addClass('track-error');
        });

        success.always(function() {
            track_instances.removeClass('track-reloading');
        });
        
        return success.promise();
    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var track = this;
        
        var output = {
            position:       track.position,
            subtrack_id:    track.subtrack_id,
            artist:         track.track_artist,
            title:          track.track_title,
            album:          track.track_album,
            duration:       track.duration,
        }

        return output;
    }
    
    toggle_favorite(do_love){
        var track = this;
        var track_instances = track.get_instances();

        if (do_love){
            var link_el = track_instances.find('.wpsstm-track-action-favorite a');
        }else{
            var link_el = track_instances.find('.wpsstm-track-action-unfavorite a');
        }

        var ajax_data = {
            action:     'wpsstm_track_toggle_favorite',
            track:      track.to_ajax(),   
            do_love:    do_love,
        };

        return $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',

            beforeSend: function() {
                link_el.removeClass('action-error').addClass('action-loading');
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
                        track_instances.addClass('favorited-track');
                    }else{
                        track_instances.removeClass('favorited-track');
                    }
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                link_el.addClass('action-error');
            },
            complete: function() {
                link_el.removeClass('action-loading');
            }
        })
    }
    
    dequeue_track(){
        var track = this;
        var track_instances = track.get_instances();
        var link_el = track_instances.find('.wpsstm-track-action-dequeue a');
        
        var ajax_data = {
            action:         'wpsstm_subtrack_dequeue',
            track:          track.to_ajax(),
        };

        $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
            beforeSend: function() {
                link_el.removeClass('action-error').addClass('action-loading');
            },
            success: function(data){
                
                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    track_instances.remove();
                    track.tracklist.refresh_tracks_positions();
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
    
    trash_track(){
        
        var track = this;
        var track_instances = track.get_instances();
        var link_el = track_instances.find('.wpsstm-track-action-trash a');

        var ajax_data = {
            action:     'wpsstm_track_trash',
            track:      track.to_ajax(),
        };

        $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
            beforeSend: function() {
                link_el.removeClass('action-error').addClass('action-loading');
            },
            success: function(data){
                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    track_instances.remove();
                    track.tracklist.refresh_tracks_positions();
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

    static update_sources_order(source_ids){
        
        var track = this;
        var track_instances = track.get_instances();
        var success = $.Deferred();

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_update_track_sources_order',
            track_id:   track.post_id,
            source_ids: source_ids
        };
        
        //track.debug(ajax_data,"update_sources_order");

        var ajax = jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_instances.addClass('track-loading');
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
                success.reject();
            }
        })

        ajax.always(function() {
            track_instances.removeClass('track-loading');
        });
        
        
        return success.promise();
    }

    play_track(source_idx){
        var track = this;
        var player = track.closest('wpsstm-player');

        var success = $.Deferred();
        
        //we're trying to play the same source again
        if ( track.getAttribute("trackstatus") == 'playing' ){
            track.debug("track already playing!");
            success.resolve();
            return success.promise();
        }

        var track_instances = track.get_instances();
        var source_play = track.play_first_available_source(source_idx);

        source_play.done(function(v) {
            success.resolve();
        })
        source_play.fail(function(reason) {
            track.debug(reason);
            success.reject(reason);
        })

        success.done(function(v) {
            
            /*
            preload sources for the X next tracks
            */
            
            var tracks = $(player).find('wpsstm-track');
            
            
            var max_items = 4; //number of following tracks to preload
            var track_index = tracks.index( track );
            if (track_index < 0) return; //index not found

            //keep only tracks after this one
            var rtrack_in = track_index + 1;
            var next_tracks = tracks.slice( rtrack_in );
            
            //remove tracks that have already been autosourced
            var next_tracks = next_tracks.filter(function (track) {
                return (track.did_sources_request !== false);
            });
            
            //reduce to X tracks
            var tracks_slice = next_tracks.slice( 0, max_items );

            $(tracks_slice).each(function(index, track_to_preload) {
                track_to_preload.maybe_load_sources();
            });
            
        })

        success.fail(function() {
            track.can_play = false;
            track_instances.addClass('track-error');
            player.next_track_jump();
        })

        return success.promise();

    }
    
    play_first_available_source(source_idx){

        var track = this;
        var player = track.closest('wpsstm-player');
        var success = $.Deferred();

        source_idx = (typeof source_idx !== 'undefined') ? source_idx : 0;
        var sources_playable = [];

        /*
        This function will loop until a promise is resolved
        */
        var sources = $(track).find('wpsstm-source');

        if (sources.length){
            var sources_after = sources.slice(source_idx); //including this one
            var sources_before = sources.slice(0,source_idx - 1);

            //which one should we play?
            var sources_reordered = $.merge(sources_after,sources_before);
            var sources_playable = sources_reordered.filter(function (source) {
                return (source.can_play !== false);
            });

        }

        if (!sources_playable.length){
            success.reject("no playable sources to iterate");
        }else{
            (function iterateSources(index) {

                if (index >= sources_playable.length) {
                    success.reject("finished source iteration");
                    return;
                }

                var source = sources_playable[index];
                var sourceplay = source.play_source();

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
    
    end_track(){
        var track = this;
        
        track.debug("end_track");
        
        var track_instances = track.get_instances();
        track_instances.removeClass('track-playing');

        var player = track.closest('wpsstm-player');

        if (player && player.current_source){
            player.current_media.pause();
            track_instances.find('wpsstm-source').removeClass('source-playing');
        }

    }
    
}

window.customElements.define('wpsstm-track', WpsstmTrack);