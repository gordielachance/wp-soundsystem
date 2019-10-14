var $ = jQuery.noConflict();

class WpsstmTrack extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.queueIdx =             undefined;
        this.position =             undefined;
        this.track_artist =         undefined;
        this.track_title =          undefined;
        this.track_album =          undefined;
        this.subtrack_id =          undefined;
        this.post_id =              undefined;
        this.ajax_details =         undefined;
        
        //Setup listeners
        $(this).on('start', WpsstmTrack._startTrackEvent);
        
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

        //track.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {

            case 'data-links-count':
                
                var $container = $(track).find('.wpsstm-track-links-list');
                var $links = $container.find('wpsstm-track-link');
                var $sources = $links.filter('[wpsstm-playable]');
                track.playable = ( ($sources.length > 0) || track.can_autolink );

                // sort links
                $container.sortable({
                    axis: "y",
                    items : "wpsstm-track-link",
                    handle: '.wpsstm-track-link-action-move',
                    update: function(event, ui) {

                        var linkOrder = $container.sortable('toArray', {
                            attribute: 'data-wpsstm-link-id'
                        });

                        var reordered = track.update_links_order(linkOrder); //TOUFIX bad logic

                    }
                });
                
                var $linkCount = $(track).find('.wpsstm-link-count');
                $linkCount.text(newVal);
                
            break;
        }
    }
    
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return ['data-links-count'];
    }

    get playable() {
        return this.hasAttribute('wpsstm-playable');
    }
    
    set playable(value) {
        var isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('wpsstm-playable','');
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
            this.setAttribute('can-autolink','');
        } else {
            this.removeAttribute('can-autolink');
        }
    }

    debug(data,msg){
        
        //add prefix
        if (this.post_id){
            var prefix = '[subtrack:'+this.subtrack_id+']';
            if (typeof msg === 'undefined'){
                msg = prefix;
            }else{
                msg = prefix + ' ' + msg;
            }
        }
        
        wpsstm_debug(data,msg);
    }

    render(){
        
        var track =                 this;
        track.queueIdx =            Array.from(track.parentNode.children).indexOf(track);
        track.position =            Number($(track).attr('data-wpsstm-subtrack-position')); //index in tracklist
        track.track_artist =        $(track).find('[itemprop="byArtist"]').text();
        track.track_title =         $(track).find('[itemprop="name"]').text();
        track.track_album =         $(track).find('[itemprop="inAlbum"]').text();
        track.post_id =             Number($(track).attr('data-wpsstm-track-id'));
        track.subtrack_id =         Number($(track).attr('data-wpsstm-subtrack-id'));
        track.can_autolink =        ( wpsstmL10n.ajax_autolink && track.hasAttribute('can-autolink') );

        var $toggleLinks = $(track).find('.wpsstm-track-action-toggle-links');

        $toggleLinks.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-links-list').toggleClass('active');
        });

        /*
        Track Links
        */
        
        //create links count
        var $linkCount = $toggleLinks.find('.wpsstm-link-count');
        
        if (!$linkCount.length){
            var $linkCount = $('<span class="wpsstm-link-count"></span>');
            $toggleLinks.append($linkCount);
        }

        var $links = $(track).find('wpsstm-track-link');
        track.setAttribute('data-links-count',$links.length);

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

    }
    
    track_autolink() {

        var track = this;
        var $instances = track.get_instances();
        var success = $.Deferred();

        $instances.addClass('track-links-loading');

        var ajax_data = {
            action:     'wpsstm_get_track_links_autolinked',
            track:      track.to_ajax(),   
        };

        var request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        })
        .done(function(data) {
            
            $instances.toArray().forEach(function(item) {
                item.can_autolink = false;
            });

            if ( data.success ){
                
                var $links = $(data.html).find('wpsstm-track-link');
                $instances.find('.wpsstm-track-links-list').empty().append($links);
                $instances.attr('data-links-count',$links.length);
                
                success.resolve();
                
            }else{
                success.reject(data.error_code);
            }

        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            success.reject(errorThrown);
        })

        
        success.fail(function(reason) {
            track.debug(reason,"autolink failed");

            $instances.toArray().forEach(function(item) {
                item.playable = false;
            });

        })
        .always(function() {
            $instances.removeClass('track-links-loading');
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
        var $instances = track.get_instances();

        if (do_love){
            var $links = $instances.find('.wpsstm-track-action-favorite');
        }else{
            var $links = $instances.find('.wpsstm-track-action-unfavorite');
        }

        var ajax_data = {
            action:     'wpsstm_track_toggle_favorite',
            track:      track.to_ajax(),   
            do_love:    do_love,
        };
        
        $links.removeClass('action-error').addClass('action-loading');

        return $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })
        .done(function(data){

                if (data.success === false) {
                    console.log(data);
                    $links.addClass('action-error');
                    if (data.notice){
                        wpsstm_js_notice(data.notice);
                    }
                }else{
                    if (do_love){
                        $instances.addClass('favorited-track');
                    }else{
                        $instances.removeClass('favorited-track');
                    }
                }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                $links.addClass('action-error');
        })
        .always(function() {
                $links.removeClass('action-loading');
        })
    }
    
    dequeue_track(){
        var track = this;
        var $instances = track.get_instances();
        var $links = $instances.find('.wpsstm-track-action-dequeue');
        
        var ajax_data = {
            action:         'wpsstm_subtrack_dequeue',
            track:          track.to_ajax(),
        };
        
        $links.removeClass('action-error').addClass('action-loading');

        $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })
        .done(function(data){

                if (data.success === false) {
                    $links.addClass('action-error');
                    console.log(data);
                }else{
                    $instances.remove();
                    track.tracklist.refresh_tracks_positions();
                }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
                $links.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
        })
        .always(function() {
            $links.removeClass('action-loading');
        })

    }
    
    trash_track(){
        
        var track = this;
        var $instances = track.get_instances();
        var $links = $instances.find('.wpsstm-track-action-trash');

        var ajax_data = {
            action:     'wpsstm_track_trash',
            track:      track.to_ajax(),
        };
        
        $links.removeClass('action-error').addClass('action-loading');

        $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })
        .done(function(data){
            if (data.success === false) {
                $links.addClass('action-error');
                console.log(data);
            }else{
                $instances.remove();
                track.tracklist.refresh_tracks_positions();
            }
        })
        .fail(function (xhr, ajaxOptions, thrownError) {
            $links.addClass('action-error');
            console.log(xhr.status);
            console.log(thrownError);
        })
        .always(function() {
            $links.removeClass('action-loading');
        })

    }

    update_links_order(link_ids){
        
        var track = this;
        var $instances = track.get_instances();
        var success = $.Deferred();

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_update_track_links_order',
            track_id:   track.post_id,
            link_ids: link_ids
        };
        
        //track.debug(ajax_data,"update_links_order");

        $instances.addClass('track-details-loading');
        
        var ajax = jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json'
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
            success.reject();
        })
        .always(function() {
            $instances.removeClass('track-details-loading');
        });
        
        
        return success.promise();
    }
    
    get_instances(){
        return $('wpsstm-track[data-wpsstm-track-id="'+this.post_id+'"]');
    }

    static _startTrackEvent(e){
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
    
}

window.customElements.define('wpsstm-track', WpsstmTrack);