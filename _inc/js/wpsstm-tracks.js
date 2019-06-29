var $ = jQuery.noConflict();

class WpsstmTrack extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.position =             undefined;
        this.track_artist =         undefined;
        this.track_title =          undefined;
        this.track_album =          undefined;
        this.subtrack_id =          undefined;
        this.post_id =              undefined;
        this.pageNode =             undefined;
        this.queueNode =            undefined;
        this.player =               undefined;

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
        var tracksContainer = trackInstances.closest('.tracks-container');

        //track.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
            case 'trackstatus':

                if ( !newVal ){
                    trackInstances.removeClass('track-active track-loading');
                    $(track.player).removeClass('player-playing');

                    track.end_track();
                }

                if (newVal == 'request'){
                    track.player.current_track = track;
                    track.player.render_queue_controls();
                    trackInstances.addClass('track-active track-loading');
                    $(tracksContainer).addClass('tracks-container-loading tracks-container-has-played');
                }
                
                if ( newVal == 'playing' ){
                    trackInstances.removeClass('track-loading').addClass('track-playing track-has-played');
                    
                    $(track.player).addClass('player-playing player-has-played');
                    $(tracksContainer).removeClass('tracks-container-loading').addClass('tracks-container-playing tracks-container-has-played');
                }
                
                if ( newVal == 'paused' ){
                    $(track.player).removeClass('player-playing');
                    $(tracksContainer).removeClass('tracks-container-playing');
                    trackInstances.removeClass('track-playing');
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
        return ['trackstatus','wpsstm-playable','didautolink'];
    }
    
    get status() {
        return this.getAttribute('trackstatus');
    }
    
    set status(value) {
        this.setAttribute('trackstatus',value);
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
    
    get didautolink() {
        return this.hasAttribute('didautolink');
    }

    set didautolink(value) {
        const isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('didautolink', '');
        } else {
            this.removeAttribute('didautolink');
        }
    }
    
    ///
    listenLinks(){
        
        var track = this;
        
        /*
        Toggle display the track when the queue is updated
        */

        return new MutationObserver(function(mutationsList){
            for(var mutation of mutationsList) {
                
                if (mutation.type == 'childList') {
                    track.initLinks();
                }
            }
        });
        
    }
    
    initLinks(){
        var track = this;
        var sourceLinks = $(track).find('wpsstm-track-link').filter('[wpsstm-playable]');
        
        if (!sourceLinks.length && track.didautolink){
            track.playable = false;
        }else{
            track.playable = true;
        }

        var toggleLinksEl = $(track).find('.wpsstm-track-action-toggle-links');

        //link count
        var linkCountEl = toggleLinksEl.find('.wpsstm-links-count');
        if ( !linkCountEl.length ){ //create item
            linkCountEl = $('<span class="wpsstm-links-count"></span>');
            toggleLinksEl.append(linkCountEl);            
        }

        track.setAttribute('data-wpsstm-links-count',sourceLinks.length);
        linkCountEl.text( sourceLinks.length );
    }

    debug(msg){
        var debug = {message:msg,track:this};
        wpsstm_debug(debug);
    }

    render(){
        
        var track = this;

        track.position =            Number($(track).attr('data-wpsstm-subtrack-position')); //index in tracklist
        track.track_artist =        $(track).find('[itemprop="byArtist"]').text();
        track.track_title =         $(track).find('[itemprop="name"]').text();
        track.track_album =         $(track).find('[itemprop="inAlbum"]').text();
        track.post_id =             Number($(track).attr('data-wpsstm-track-id'));
        track.subtrack_id =         Number($(track).attr('data-wpsstm-subtrack-id'));
        
        /*
        Track Links
        */
        
        var linksNode = $(track).find('.wpsstm-track-links-list').get(0);
        track.initLinks();

        // Watch for links update
        var linksUpdated = track.listenLinks();
        linksUpdated.observe(linksNode,{childList: true});

        // sort links
        $(linksNode).sortable({
            axis: "y",
            items : "wpsstm-track-link",
            handle: '.wpsstm-track-link-action-move',
            update: function(event, ui) {

                var linkOrder = $(linksNode).sortable('toArray', {
                    attribute: 'data-wpsstm-link-id'
                });

                var reordered = track.update_links_order(linkOrder); //TOUFIX bad logic

            }
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
    
    track_autolink() {

        var track = this;
        var track_instances = track.get_instances();

        track_instances.addClass('track-links-loading');

        var ajax_data = {
            action:     'wpsstm_track_autolink',
            track:      track.to_ajax(),   
        };

        var autolink_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        autolink_request.done(function(data) {
            
            track_instances.prop('didautolink', true); //use prop and not attr here - https://stackoverflow.com/a/12940759/782013

            if ( data.success ){
                
                var newLinksContainer = $(data.html);
                var newLinks = newLinksContainer.find('wpsstm-track-link');

                track_instances.find('.wpsstm-track-links-list').empty().append(newLinks);
                
            }
            
        });
        
        autolink_request.always(function (dataOrjqXHR, textStatus, jqXHRorErrorThrown) {
            track_instances.removeClass('track-links-loading');
        });

        return autolink_request;
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
                    track.closest('wpsstm-tracklist').refresh_tracks_positions();
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
                    track.closest('wpsstm-tracklist').refresh_tracks_positions();
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
                track_instances.addClass('track-links-loading');
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
            track_instances.removeClass('track-links-loading');
        });
        
        
        return success.promise();
    }

    play_track(link_idx){
        var track = this;
        var track_instances = track.get_instances();

        var success = $.Deferred();
        
        //we're trying to play the same link again
        if ( track.status == 'playing' ){
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
            if (wpsstmL10n.autolink){
                track.next_tracks_autolink();
            }
        })

        success.fail(function() {
            track_instances.playable = false;
            track.player.next_track_jump();
        })

        return success.promise();

    }
    
    /*
    preload links for the X next tracks
    */
    next_tracks_autolink(){
        var track = this;
        var tracks = $(track.player).find('wpsstm-track');

        var max_items = 3; //number of following tracks to preload //TOUFIX should be in php options
        var track_index = tracks.index( track );
        if (track_index < 0) return; //index not found

        //keep only tracks after this one
        var rtrack_in = track_index + 1;
        var next_tracks = tracks.slice( rtrack_in );
        
        //consider only the next X tracks
        var tracks_slice = next_tracks.slice( 0, max_items );

        //remove those that already been autolinked
        tracks_slice = tracks_slice.filter(function (index) {
            return !this.didautolink;
        });
        
        /*
        TOUFIX TOUCHECK this preloads the tracks SEQUENCIALLY
        var results = [];

        return tracks_slice.toArray().reduce((promise, track) => {
            return promise.then((result) => {
                return track.track_autolink().then(result => results.push(result));
            })
            .catch(console.error);
        }, Promise.resolve());
        */
        
        $(tracks_slice).each(function(index, track_to_preload) {	
            track_to_preload.track_autolink();	
        });
        

    }
    
    play_first_available_link(link_idx){

        var track = this;
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

            var links_playable = links_reordered.filter(function (index) {
                return this.playable;
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

        if (track.player && track.player.current_link){
            track.player.current_media.pause();
            track_instances.find('wpsstm-track-link').removeClass('link-playing link-active');
        }

    }
    
}

window.customElements.define('wpsstm-track', WpsstmTrack);