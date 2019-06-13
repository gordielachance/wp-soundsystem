var $ = jQuery.noConflict();

//play/pause track button
$(document).on('click','.wpsstm-track-action-play', function(e) {
    e.preventDefault();
    var track = this.closest('wpsstm-track');
    var player;
    var trackIdx;

    if (track.queueNode){ //page track, get the queue track
        track = track.queueNode;
    }
    
    player = track.closest('wpsstm-player');
    trackIdx = Array.from(track.parentNode.children).indexOf(track);

    var links = $(track).find('wpsstm-track-link');
    var activeLink = links.filter('.link-active').get(0);
    var linkIdx = links.index( activeLink );
    linkIdx = (linkIdx > 0) ? linkIdx : 0;
    
    player.play_queue(trackIdx,linkIdx);

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
        
        this.did_links_request =  undefined;

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
        track.did_links_request =  $(track).hasClass('did-track-autolink');

        var player = track.closest('wpsstm-player');

        /*
        populate existing links
        */
        var trackLinks = $(track).find('wpsstm-track-link');
        var playableTrackLinks = trackLinks.filter('.wpsstm-playable-link');

        if (!playableTrackLinks.length && track.did_links_request){
            $(track).addClass('track-error');
        }

        var toggleLinksEl = $(track).find('.wpsstm-track-action-toggle-links');
        var linkCountEl = toggleLinksEl.find('.wpsstm-links-count');
        if ( !linkCountEl.length ){ //create item
            linkCountEl = $('<span class="wpsstm-links-count"></span>');
            toggleLinksEl.append(linkCountEl);            
        }

        $(track).attr('data-wpsstm-links-count',trackLinks.length);
        linkCountEl.text(trackLinks.length);

        // sort track links
        var links_container = $(this);
        var links_list_el = links_container.find('.wpsstm-track-links-list');

        links_list_el.sortable({
            axis: "y",
            items : "wpsstm-track-link",
            handle: '.wpsstm-track-link-action-move',
            update: function(event, ui) {

                var linkOrder = links_list_el.sortable('toArray', {
                    attribute: 'data-wpsstm-link-id'
                });

                var reordered = track.update_links_order(linkOrder); //TOUFIX bad logic

            }
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
        $(track).on('click','.wpsstm-track-action-favorite,.wpsstm-track-action-unfavorite', function(e) {

            e.preventDefault();
            var do_love = $(this).hasClass('action-favorite');
            
            track.toggle_favorite(do_love);        

        });

        //dequeue
        $(track).on('click','.wpsstm-track-action-dequeue', function(e) {
            e.preventDefault();
            track.dequeue_track();
        });

        //delete
        $(track).on('click','.wpsstm-track-action-trash', function(e) {
            e.preventDefault();
            track.trash_track();
        });

        //links

        var toggleLinksEl = $(track).find('.wpsstm-track-action-toggle-links');

        toggleLinksEl.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-links-list').toggleClass('active');
        });
        
        //move play button at the beginning of the row
        var playLinkEl = $(track).find('.wpsstm-track-action-play');
        playLinkEl.parents('.wpsstm-track').find('.wpsstm-track-pre').prepend(playLinkEl);
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
    
    maybe_load_links(){

        var track = this;
        var success = $.Deferred();

        //TOUFIX urgent !!! var can_autolink = track.tracklist.hasAttribute('data-ajax-autolink');
        var can_autolink = true;
        
        var links = $(track).find('wpsstm-track-link');
        


        if ( (links.length > 0) || ( !can_autolink ) || ( track.did_links_request ) ){
            success.resolve(track);
            
        } else{
            success = track.get_track_links_request();
        }
        
        success.always(function() {
            var links = $(track).find('wpsstm-track-link.wpsstm-playable-link');
            track.can_play = (links.length > 0);    
        });

        return success.promise();
    }

    get_track_links_request() {

        var track = this;
        var track_instances = track.get_instances();
        var success = $.Deferred();

        track_instances.addClass('track-links-loading');

        var ajax_data = {
            action:     'wpsstm_track_autolink',
            track:      track.to_ajax(),   
        };

        var links_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        links_request.done(function(data) {

            var trackUpdated = $.Deferred();
            var trackCreated = (track.post_id != data.track.post_id);
            var hasNewLinks = ( data.link_ids );

            //a track post has been created/updated while autosourcing, refresh it.
            if (trackCreated || hasNewLinks){
                var reloadTrack = track.reload_track();

                reloadTrack.then(
                    function(newTrack){

                        if ( data.success === true ){
                            trackUpdated.resolve(newTrack);
                        }else{
                            track.debug("track refresh request failed: " + data.message);
                            trackUpdated.reject(data.message);
                        }

                    },
                    function(error_msg){
                        trackUpdated.reject(error_msg);
                    }
                );
                
            }else{
                //no need to reload track
                trackUpdated.resolve(track);
            }
            
            trackUpdated.done(function(track) {
                success.resolve(track);
            });
            
            trackUpdated.fail(function(reason) {
                success.reject(reason);
            });

        });

        success.fail(function() {
            track_instances.addClass('track-error');
        });

        success.always(function() {
            track_instances.removeClass('track-links-loading');
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

        var links_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        links_request.done(function(data) {

            if ( data.html ){
                
                /*
                delete old nodes, and add new ones instead
                */

                var newContent = $(data.html);
                var oldStatus = track.getAttribute('trackstatus');

                //important ! Settings this class assure us that the node will have did_links_request = true when it will be inserted in DOM
                $(newContent).addClass('did-track-autolink');

                var newQueueTrack = newContent.get(0);
                var newPageTrack = newContent.clone().get(0);

                var oldQueueNode = track.parentNode;
                var oldPageNode = track.pageNode;

                newQueueTrack.pageNode = newPageTrack;
                newPageTrack.queueNode = newQueueTrack;

                oldQueueNode.replaceChild(newQueueTrack, track); //replace in queue
                oldPageNode.parentNode.replaceChild(newPageTrack, oldPageNode); //replace in page
                
                /* pass the status to the new node*/
                newQueueTrack.setAttribute('trackstatus',oldStatus);
                
                success.resolve(newQueueTrack);
                
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
            var link_el = track_instances.find('.wpsstm-track-action-favorite');
        }else{
            var link_el = track_instances.find('.wpsstm-track-action-unfavorite');
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
        var link_el = track_instances.find('.wpsstm-track-action-dequeue');
        
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
        var link_el = track_instances.find('.wpsstm-track-action-trash');

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

    update_links_order(link_ids){
        
        var track = this;
        var track_instances = track.get_instances();
        var success = $.Deferred();

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_update_track_links_order',
            track_id:   track.post_id,
            link_ids: link_ids
        };
        
        //track.debug(ajax_data,"update_links_order");

        var ajax = jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_instances.addClass('track-reloading');
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
            track_instances.removeClass('track-reloading');
        });
        
        
        return success.promise();
    }

    play_track(link_idx){
        var track = this;
        var player = track.closest('wpsstm-player');

        var success = $.Deferred();
        
        //we're trying to play the same link again
        if ( track.getAttribute("trackstatus") == 'playing' ){
            track.debug("track already playing!");
            success.resolve();
            return success.promise();
        }

        var track_instances = track.get_instances();
        var link_play = track.play_first_available_link(link_idx);

        link_play.done(function(v) {
            success.resolve();
        })
        link_play.fail(function(reason) {
            track.debug(reason);
            success.reject(reason);
        })

        success.done(function(v) {
            track.preload_next_tracks();
        })

        success.fail(function() {
            track.can_play = false;
            track_instances.addClass('track-error');
            player.next_track_jump();
        })

        return success.promise();

    }
    
    /*
    preload links for the X next tracks
    */
    preload_next_tracks(){
        var track = this;
        var player = track.closest('wpsstm-player');
        var tracks = $(player).find('wpsstm-track');


        var max_items = 4; //number of following tracks to preload
        var track_index = tracks.index( track );
        if (track_index < 0) return; //index not found

        //keep only tracks after this one
        var rtrack_in = track_index + 1;
        var next_tracks = tracks.slice( rtrack_in );

        //remove tracks that have already been autolinkd
        var next_tracks = next_tracks.filter(function (track) {
            return (track.did_links_request !== false);
        });

        //reduce to X tracks
        var tracks_slice = next_tracks.slice( 0, max_items );

        $(tracks_slice).each(function(index, track_to_preload) {
            track_to_preload.maybe_load_links();
        });
    }
    
    play_first_available_link(link_idx){

        var track = this;
        var player = track.closest('wpsstm-player');
        var success = $.Deferred();

        link_idx = (typeof link_idx !== 'undefined') ? link_idx : 0;
        var links_playable = [];

        /*
        This function will loop until a promise is resolved
        */
        var links = $(track).find('wpsstm-track-link');

        if (links.length){
            
            var links_after = links.slice(link_idx); //including this one
            var links_before = links.slice(0,link_idx);

            //which one should we play?
            var links_reordered = $.merge(links_after,links_before);
            var links_playable = links_reordered.filter(function (link) {
                return (link.can_play !== false);
            });

        }
        

        if (!links_playable.length){
            success.reject("no playable links to iterate");
        }else{
            (function iterateLinks(index) {

                if (index >= links_playable.length) {
                    success.reject("finished link iteration");
                    return;
                }

                var link = links_playable[index];
                var linkplay = link.play_link();

                linkplay.done(function(v) {
                    success.resolve();
                })
                linkplay.fail(function() {
                    iterateLinks(index + 1);
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

        if (player && player.current_link){
            player.current_media.pause();
            track_instances.find('wpsstm-track-link').removeClass('link-playing link-active');
        }

    }
    
}

window.customElements.define('wpsstm-track', WpsstmTrack);