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
        
        this.sources =              [];
        this.did_sources_request =  undefined;

        // Setup a click listener on <wpsstm-tracklist> itself.
        this.addEventListener('click', e => {
        });

    }
    connectedCallback(){
        console.log("TRACK CONNECTED!");
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
        var prefix = " WpsstmTrack #" + this.position;
        wpsstm_debug(msg,prefix);
    }

    render(){
        
        var self = this;

        self.tracklist =            $(self).parents('wpsstm-tracklist').get(0);
        self.position =             Number($(self).attr('data-wpsstm-subtrack-position')); //index in tracklist
        self.track_artist =         $(self).find('[itemprop="byArtist"]').text();
        self.track_title =          $(self).find('[itemprop="name"]').text();
        self.track_album =          $(self).find('[itemprop="inAlbum"]').text();
        self.post_id =              Number($(self).attr('data-wpsstm-track-id'));
        self.subtrack_id =          Number($(self).attr('data-wpsstm-subtrack-id'));
        self.did_sources_request =  $(self).hasClass('track-autosourced');
        self.sources =              [];//reset array

        /*
        populate existing sources
        */

        var source_els = $(self).find('wpsstm-source');

        $.each(source_els, function( index, source ) {
            self.sources.push(source);
        });
      
        $(self).attr('data-wpsstm-sources-count',self.sources.length);
        
        if (!self.sources.length && self.did_sources_request){
            $(self).addClass('track-error');
        }
        
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
        if ( !$(self).find('.wpsstm-sources-count').length ){ //not yet appened
            var sourceCountEl = $('<span class="wpsstm-sources-count">'+self.sources.length+'</span>');
            toggleSourcesEl.append(sourceCountEl);            
        }

        //toggle sources
        toggleSourcesEl.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-sources-list').toggleClass('active');
        });

    }
    
    get_instances(){
        var self = this;
        return $(document).find('wpsstm-track[data-wpsstm-subtrack-id="'+self.subtrack_id+'"]');
    }
    
    maybe_load_sources(){

        var self = this;
        var success = $.Deferred();
        var can_autosource = true; //TOUFIX should be from localized var

        if (self.sources.length > 0){
            
            success.resolve();
            
        }else if ( !can_autosource ){
            
            success.resolve("Autosource is disabled");
            
        } else if ( self.did_sources_request ) {
            success.resolve("already did sources auto request for track #" + self.position);
            
        } else{
            success = self.get_track_sources_request();
        }
        
        //set .can_play property
        success.always(function() {
            self.can_play = (self.sources.length > 0);
        });

        return success.promise();
    }

    get_track_sources_request() {

        var self = this;
        var success = $.Deferred();

        $(self).addClass('track-loading');

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
            $(self).addClass('track-autosourced');
            
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
            $(self).addClass('track-error');
        });

        success.always(function() {
            $(self).removeClass('track-loading');
        });
        
        return success.promise();

    }
    
    reload_track(){
        
        var self = this;
        var success = $.Deferred();

        self.debug("reload_track");
        $(self).addClass('track-loading');
        
        var ajax_data = {
            action:     'wpsstm_track_html',
            track:      self.to_ajax(),   
        };

        var sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        sources_request.done(function(data) {
            if ( data.html ){

                var newTrack = $(data.html);

                //swap content
                self.replaceWith( newTrack.get(0) );
                
                success.resolve();
            }else{
                self.debug("track refresh failed: " + data.message);
                success.reject(data.message);
            }
        });
        
        success.fail(function() {
            $(self).addClass('track-error');
        });

        success.always(function() {
            $(self).removeClass('track-loading');
        });
        
        return success.promise();
    }

    get_source_obj(source_idx){
        var self = this;

        source_idx = Number(source_idx);
        var source_obj = self.sources[source_idx];
        if(typeof source_obj === undefined) return;
        return source_obj;
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
                    $(document).trigger("wpsstmTrackLove", [self,do_love] ); //register custom event - used by lastFM for the track.updateNowPlaying call
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
                $(self).addClass('track-loading');
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
            $(self).removeClass('track-loading');
        });
        
        
        return success.promise();
    }
    
}

window.customElements.define('wpsstm-track', WpsstmTrack);