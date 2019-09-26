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
        this.tracklist =            undefined;

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
    }
    attributeChangedCallback(attrName, oldVal, newVal){
        
        var isValueChanged = (newVal !== oldVal);
        if (!isValueChanged) return;
        
        var track = this;
        if (!track.tracklist) return; //ignore player track //URGENT TOUCHECK

        //track.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
            case 'trackstatus':

                if ( !newVal ){
                    $(track).removeClass('track-active track-loading');
                    $(track.tracklist).removeClass('tracklist-playing');

                    track.end_track();
                }

                if (newVal == 'request'){

                    $(track).addClass('track-active track-loading');
                    $(track.tracklist).addClass('tracklist-loading tracklist-active tracklist-has-played');
                    
                    track.tracklist.update_player();
                    
                }
                
                if ( newVal == 'playing' ){

                    $(track).removeClass('track-loading').addClass('track-playing track-has-played');
                    $(track.tracklist).removeClass('tracklist-loading').addClass('tracklist-playing tracklist-has-played');
                }
                
                if ( newVal == 'paused' ){
                    $(track.tracklist).removeClass('tracklist-playing');
                    $(track).removeClass('track-playing');
                }

            break;
                
            case 'data-sources-count':

                if (newVal > 0){
                    track.playable = true;
                }
                
                //links list
                var linksNode = $(track).find('.wpsstm-track-links-list').get(0);

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
                
                var sourceCountEl = $(track).find('.wpsstm-sources-count');
                sourceCountEl.text(newVal);
                
            break;

        }
    }
    
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return ['trackstatus','wpsstm-playable','can-autolink','data-sources-count'];
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
        var isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('wpsstm-playable',true);
        } else {
            this.removeAttribute('wpsstm-playable');
        }
    }
    
    get can_autolink() {
        return this.hasAttribute('can-autolink');
    }

    set can_autolink(value) {
        var isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('can-autolink',true);
        } else {
            this.removeAttribute('can-autolink');
        }
    }
    debug(msg,data){
        
        //msg
        if (typeof msg !== 'object'){
            msg = '[track] ' + msg;
        }
        
        //data
        if (typeof data !== 'object'){
            msg = msg + ' - ' + data;
            data = {};
        }

        data.track = this;

        wpsstm_debug(msg,data);
    }

    render(){
        
        var track =                 this;
        track.tracklist =           $(track).closest('wpsstm-tracklist').get(0);
        track.position =            Number($(track).attr('data-wpsstm-subtrack-position')); //index in tracklist
        track.track_artist =        $(track).find('[itemprop="byArtist"]').text();
        track.track_title =         $(track).find('[itemprop="name"]').text();
        track.track_album =         $(track).find('[itemprop="inAlbum"]').text();
        track.post_id =             Number($(track).attr('data-wpsstm-track-id'));
        track.subtrack_id =         Number($(track).attr('data-wpsstm-subtrack-id'));

        var toggleLinksEl = $(track).find('.wpsstm-track-action-toggle-links');

        toggleLinksEl.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-links-list').toggleClass('active');
        });
        
        if ( track.hasAttribute('ajax-details') ){
            track.load_details();
        }
        
        /*
        Track Links
        */
        
        //create links count
        var toggleLinksEl = $(track).find('.wpsstm-track-action-toggle-links');
        var sourceCountEl = toggleLinksEl.find('.wpsstm-sources-count');
        
        if (!sourceCountEl.length){
            var sourceCountEl = $('<span class="wpsstm-sources-count"></span>');
            toggleLinksEl.append(sourceCountEl);
        }

        var sourceLinks = $(track).find('wpsstm-track-link');
        track.setAttribute('data-sources-count',sourceLinks.length);
        
        /*
        Track Actions
        */

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
        
        //move play button at the beginning of the row
        var playLinkEl = $(track).find('.wpsstm-track-action-play');
        playLinkEl.parents('.wpsstm-track').find('.wpsstm-track-pre').prepend(playLinkEl);
        
        //play/pause
        $(track).on('click','.wpsstm-track-action-play', function(e) {
            e.preventDefault();

            var trackIdx = Array.from(track.parentNode.children).indexOf(track);

            var links = $(track).find('wpsstm-track-link');
            var activeLink = links.filter('.link-active').get(0);
            var linkIdx = links.index( activeLink );
            linkIdx = (linkIdx > 0) ? linkIdx : 0;
            
            track.tracklist.play_queue_track(trackIdx,linkIdx);

        });
        
    }

    load_details() {

        var track = this;

        $(track).addClass('track-details-loading');

        var ajax_data = {
            action:     'wpsstm_get_track_details',
            track:      track.to_ajax(),   
        };

        var ajax = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        })
        .done(function(data) {
            if ( data.success ){
                
                var newTrack = $(data.html)[0];

                //swap nodes
                track.parentNode.insertBefore(newTrack,track);
                track.parentNode.removeChild(track);

            }
        })
        .fail(function() {
            track.debug(ajax_data,"failed loading track details");
        })
        .always(function() {
            $(track).removeClass('track-details-loading');
        });

        return ajax;
    }
    
    track_autolink() {

        var track = this;

        track.addClass('track-links-loading');

        var ajax_data = {
            action:     'wpsstm_get_track_links_autolinked',
            track:      track.to_ajax(),   
        };

        var autolink_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        })
        .done(function(data) {

            if ( data.success ){
                
                var newLinksContainer = $(data.html);
                var newLinks = newLinksContainer.find('wpsstm-track-link');

                track.find('.wpsstm-track-links-list').empty().append(newLinks);
                
            }else{
                track.debug(ajax_data,"autolink failed");
                track.removeAttr("wpsstm-playable");
            }
            
            track.removeAttr("can-autolink");

        })
        .fail(function() {
            track.debug(ajax_data,"autolink ajax request failed");
            track.removeAttr("wpsstm-playable");
        })
        .always(function() {
            track.removeClass('track-links-loading');
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

        if (do_love){
            var link_el = $(track).find('.wpsstm-track-action-favorite');
        }else{
            var link_el = $(track).find('.wpsstm-track-action-unfavorite');
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
                        $(track).addClass('favorited-track');
                    }else{
                        $(track).removeClass('favorited-track');
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
        var link_el = $(track).find('.wpsstm-track-action-dequeue');
        
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
                    track.remove();
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
        var link_el = $(track).find('.wpsstm-track-action-trash');

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
                    track.remove();
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
                $(track).addClass('track-details-loading');
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
            $(track).removeClass('track-details-loading');
        });
        
        
        return success.promise();
    }

    play_track(link_idx){
        var track = this;

        var success = $.Deferred();
        
        //we're trying to play the same link again
        if ( track.status == 'playing' ){
            track.debug("track already playing!");
            success.resolve();
            return success.promise();
        }

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
            track.playable = false;
            track.tracklist.next_track_jump();
        })

        return success.promise();

    }
    
    /*
    preload links for the X next tracks
    */
    next_tracks_autolink(){
        var track = this;
        var tracks = $(track.tracklist).find('wpsstm-track');

        var max_items = 3; //number of following tracks to preload //TOUFIX should be in php options
        var track_index = tracks.index( track );
        if (track_index < 0) return; //index not found

        //keep only tracks after this one
        var rtrack_in = track_index + 1;
        var next_tracks = tracks.slice( rtrack_in );
        
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
        $(track).removeClass('track-playing');

        if (track.tracklist && track.tracklist.current_link){
            track.tracklist.current_media.pause();
            $(track).find('wpsstm-track-link').removeClass('link-playing link-active');
        }

    }
    
}

$(document).on( "wpsstmTrackStart", function( event, track ) {
    
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
    .fail(function() {
        track.debug(ajax_data,"track start request failed");
    })
});

window.customElements.define('wpsstm-track', WpsstmTrack);