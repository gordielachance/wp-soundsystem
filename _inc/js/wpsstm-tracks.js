var $ = jQuery.noConflict();

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
        var player = track.closest('wpsstm-player');
        
        //track.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
            case 'trackstatus':

                if (!newVal){
                    track.end_track();
                }

                if (newVal == 'request'){
                    player.setup_track(track);
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
        
        var self = this;

        self.tracklist =            self.closest('wpsstm-tracklist');
        self.position =             Number($(self).attr('data-wpsstm-subtrack-position')); //index in tracklist
        self.track_artist =         $(self).find('[itemprop="byArtist"]').text();
        self.track_title =          $(self).find('[itemprop="name"]').text();
        self.track_album =          $(self).find('[itemprop="inAlbum"]').text();
        self.post_id =              Number($(self).attr('data-wpsstm-track-id'));
        self.subtrack_id =          Number($(self).attr('data-wpsstm-subtrack-id'));
        self.did_sources_request =  $(self).hasClass('track-autosourced');

        var player = self.closest('wpsstm-player');

        /*
        populate existing sources
        */

        //manage single source
        $(self).find('.wpsstm-track-sources').each(function() {
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

                    var reordered = self.update_sources_order(sourceOrder); //TOUFIX bad logic

                }
            });

        });

        //track popups within iframe
        $(self).on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
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
        $(self).on('click','.wpsstm-track-action-favorite a,.wpsstm-track-action-unfavorite a', function(e) {

            e.preventDefault();

            var action_el = $(this).parents('.wpsstm-track-action');
            var do_love = action_el.hasClass('action-favorite');
            
            self.toggle_favorite(do_love);        

        });

        //dequeue
        $(self).on('click','.wpsstm-track-action-dequeue a', function(e) {
            e.preventDefault();
            self.dequeue_track();
        });

        //delete
        $(self).on('click','.wpsstm-track-action-trash a', function(e) {
            e.preventDefault();
            self.trash_track();
        });

        //sources

        var toggleSourcesEl = $(self).find('.wpsstm-track-action-toggle-sources a');

        toggleSourcesEl.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-sources-list').toggleClass('active');
        });

    }

    getQueueTrack(){
        var playerTrack;
        var self = this;

        if (this.pageNode){ //is a queue track
            playerTrack = this;
        }else{ //is a page track
            playerTrack = this.queueNode;
        }
        return playerTrack;
    }
    
    renderPageTrack(){
        var self = this;
        
        //play/pause track button
        var bt = $(self).find(".wpsstm-track-play-bt");
        
        bt.click(function(e) {
            e.preventDefault();
            $(self.queueNode).find(".wpsstm-track-play-bt").trigger('click');
        });
        
    }
    
    renderQueueTrack(){
        
        var self = this;

        //play/pause track button
        var bt = $(self).find(".wpsstm-track-play-bt");
        bt.click(function(e) {
            
            e.preventDefault();
            var player = self.closest('wpsstm-player');

            if ( player.current_source && (player.current_track == self) ){
                
                self.debug("reclick");

                if ( self.getAttribute("trackstatus") == 'playing' ){
                    player.current_media.pause();
                }else{
                    player.current_media.play();
                }
            }else{
                var trackIdx = Array.from(self.parentNode.children).indexOf(self);
                player.play_queue(trackIdx);
            }

        });

    }

    get_instances(){
        var self = this;
        var instances = [];
        
        instances.push(self);
        instances.push(self.pageNode);
        instances.push(self.queueNode);
        
        instances = instances.filter(Boolean); //remove falsy
        
        return $(instances);
    }
    
    maybe_load_sources(){

        var self = this;
        var success = $.Deferred();
        var can_autosource = true; //TOUFIX should be from localized var
        var sources = $(self).find('wpsstm-source');

        if (sources.length > 0){
            
            success.resolve();
            
        }else if ( !can_autosource ){
            
            success.resolve("Autosource is disabled");
            
        } else if ( self.did_sources_request ) {
            success.resolve("already did sources auto request for track #" + self.position);
            
        } else{
            success = self.get_track_sources_request();
        }
        
        success.always(function() {
            var sources = $(self).find('wpsstm-source');
            self.can_play = (sources.length > 0);    
        });

        return success.promise();
    }

    get_track_sources_request() {

        var self = this;
        var track_instances = self.get_instances();
        var success = $.Deferred();

        track_instances.addClass('track-loading');

        var ajax_data = {
            action:     'wpsstm_track_autosource',
            track:      self.to_ajax(),   
        };

        var sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        sources_request.done(function(data) {

            self.did_sources_request = true;
            track_instances.addClass('track-autosourced');
            
            if ( data.success === true ){
                self.reload_track().then(
                    function(success_msg){
                        success.resolve();
                    },
                    function(error_msg){
                        success.reject(error_msg);
                    }
                );
            }else{
                self.debug("track sources request failed: " + data.message);
                success.reject(data.message);
            }

        });

        success.fail(function() {
            track_instances.addClass('track-error');
        });

        success.always(function() {
            track_instances.removeClass('track-loading');
        });
        
        return success.promise();

    }
    
    reload_track(){
        
        var track = this;
        var success = $.Deferred();
        var track_instances = track.get_instances();

        track_instances.addClass('track-loading');
        
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

                var newQueueTrack = newContent.get(0);
                var newPageTrack = newContent.clone().get(0);

                var oldQueueNode = track.parentNode;
                var oldPageNode = track.pageNode;

                oldQueueNode.replaceChild(newQueueTrack, track); //replace in queue
                oldPageNode.parentNode.replaceChild(newPageTrack, oldPageNode); //replace in page
                
                //link (!once nodes have been inserted)
                newQueueTrack.pageNode = newPageTrack;
                newPageTrack.queueNode = newQueueTrack;
                
                //init - TOUFIX we should have a better way to handle this
                newQueueTrack.renderQueueTrack();
                newPageTrack.renderPageTrack();

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
            track_instances.removeClass('track-loading');
        });
        
        return success.promise();
    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var self = this;
        
        var output = {
            position:       self.position,
            subtrack_id:    self.subtrack_id,
            artist:         self.track_artist,
            title:          self.track_title,
            album:          self.track_album,
            duration:       self.duration,
        }

        return output;
    }
    
    toggle_favorite(do_love){
        var self = this;
        var track_instances = self.get_instances();

        if (do_love){
            var link_el = track_instances.find('.wpsstm-track-action-favorite a');
        }else{
            var link_el = track_instances.find('.wpsstm-track-action-unfavorite a');
        }

        var ajax_data = {
            action:     'wpsstm_track_toggle_favorite',
            track:      self.to_ajax(),   
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
                        wpsstm_dialog_notice(data.notice);
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
        var self = this;
        var track_instances = self.get_instances();
        var link_el = track_instances.find('.wpsstm-track-action-dequeue a');
        
        var ajax_data = {
            action:         'wpsstm_subtrack_dequeue',
            track:          self.to_ajax(),
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
                    self.tracklist.refresh_tracks_positions();
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
        
        var self = this;
        var track_instances = self.get_instances();
        var link_el = track_instances.find('.wpsstm-track-action-trash a');

        var ajax_data = {
            action:     'wpsstm_track_trash',
            track:      self.to_ajax(),
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
                    self.tracklist.refresh_tracks_positions();
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
        
        var self = this;
        var track_instances = self.get_instances();
        var success = $.Deferred();

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_update_track_sources_order',
            track_id:   self.post_id,
            source_ids: source_ids
        };
        
        //self.debug(ajax_data,"update_sources_order");

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
        
        player.setup_track(track);

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
            console.log(reason);
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
            track_instances.removeClass('track-active');
            player.next_track_jump();
        })
        
        success.always(function() {
            track_instances.removeClass('track-loading');
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
        track_instances.removeClass('track-playing track-active');

        var player = track.closest('wpsstm-player');
        var allSources = $(track).find('wpsstm-source');

        var currentSource = allSources.filter('.source-active').get(0);

        if (currentSource){
            player.current_media.pause();
            track_instances.find('wpsstm-source').removeClass('source-playing source-active');
        }

    }
    
}

window.customElements.define('wpsstm-track', WpsstmTrack);