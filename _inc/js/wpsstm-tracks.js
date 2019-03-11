var $ = jQuery.noConflict();
    
//track popups within iframe
$('wpsstm-tracklist').on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
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

class WpsstmTrackCE extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.position =             null;
        this.artist =               null;
        this.title =                null;
        this.album =                null;
        this.subtrack_id =          null;
        this.post_id =              null;
        //this.autosource_time =    null;
        this.can_play =             null;
        
        this.sources =              [];
        this.did_sources_request =  false;

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

        self.position =             Number($(self).attr('data-wpsstm-subtrack-position')); //index in tracklist
        self.artist =               $(self).find('[itemprop="byArtist"]').text();
        self.title =                $(self).find('[itemprop="name"]').text();
        self.album =                $(self).find('[itemprop="inAlbum"]').text();
        self.post_id =              Number($(self).attr('data-wpsstm-track-id'));
        self.subtrack_id =          Number($(self).attr('data-wpsstm-subtrack-id'));
        self.sources =              [];//reset array

        /*
        populate existing sources
        */

        var source_els = $(self).find('wpsstm-source');

        $.each(source_els, function( index, source ) {
            self.sources.push(source);
        });
      
        $(self).attr('data-wpsstm-sources-count',self.sources.length);
        
        //sources manager
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

        //toggle favorite
        $(self).find('.wpsstm-track-action.action-favorite a,.wpsstm-track-action.action-unfavorite a').click(function(e) {

            e.preventDefault();

            var link_el = $(this);
            var action_el = link_el.parents('.wpsstm-track-action');
            var do_love = action_el.hasClass('action-favorite');
            var action_url = link_el.data('wpsstm-ajax-url');
            var ajax_data = {};

            return $.ajax({

                type:       "post",
                url:        action_url,
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
                            wpsstm_dialog_notice(data.notice);
                        }
                    }else{
                        if (do_love){
                            $(self).addClass('favorited-track');
                        }else{
                            $(self).removeClass('favorited-track');
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

        });

        //dequeue
        $(self).find('.wpsstm-track-action-dequeue a').click(function(e) {
            e.preventDefault();
            self.dequeue_track();
        });

        //delete
        $(self).find('.wpsstm-track-action-trash a').click(function(e) {
            e.preventDefault();
            self.delete_track();
        });

        //sources
        var toggleSourcesEl = $(self).find('.wpsstm-track-action-toggle-sources a');
        var sourceCountEl = $('<span class="wpsstm-sources-count">'+self.sources.length+'</span>');
        toggleSourcesEl.append(sourceCountEl);

        //toggle sources
        toggleSourcesEl.click(function(e) {
            e.preventDefault();

            $(this).toggleClass('active');
            $(this).parents('.wpsstm-track').find('.wpsstm-track-sources-list').toggleClass('active');
        });

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
                self.innerHTML = data.html;
                self.render();
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
        var allowed = ['position','subtrack_id','post_id','artist', 'title','album','duration'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
            obj[key] = self[key];
            return obj;
        }, {});

        return filtered;
    }
    
    dequeue_track(){
        
        var self = this;
        var link_el = $(self).find('.wpsstm-track-action-dequeue a');
        var action_url = link_el.data('wpsstm-ajax-url');
        var ajax_data = {};

        $.ajax({
            type: "post",
            url: action_url,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self).addClass('track-loading');
                link_el.removeClass('action-error');
                link_el.addClass('action-loading');
            },
            success: function(data){
                
                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    $(self).remove();
                    self.refresh_tracks_positions();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(self).removeClass('track-loading');
                link_el.removeClass('action-loading');
            }
        })

    }
    
    delete_track(){
        
        var self = this;
        var link_el = $(track_el).find('.wpsstm-track-action-trash a');
        var action_url = link_el.data('wpsstm-ajax-url');

        var ajax_data = {};

        $.ajax({
            type: "post",
            url: action_url,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(self).addClass('track-loading');
            },
            success: function(data){
                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    $(self).remove();
                    self.refresh_tracks_positions();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(self).removeClass('track-loading');
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

window.customElements.define('wpsstm-track', WpsstmTrackCE);