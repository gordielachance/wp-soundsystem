var $ = jQuery.noConflict();

//tracklist modals
$('body.wpsstm-iframe').on('click', 'a.wpsstm-tracklist-popup,li.wpsstm-tracklist-popup>a', function(e) {
    e.preventDefault();

    var content_url = this.href;

    console.log("tracklist popup");
    console.log(content_url);


    var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
    var popup = $('<div></div>').append(loader_el);

    popup_w = $(window).width();
    popup_h = $(window).height();

    popup.dialog({
        width:popup_w,
        height:popup_h,
        modal: true,
        dialogClass: 'wpsstm-tracklist-dialog wpsstm-dialog dialog-loading',

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

class WpsstmTracklist extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.index =                    undefined;
        this.post_id =                  undefined;
        this.tracklist_request =        undefined;
        this.options =                  [];
        this.isExpired =                undefined;

        // Setup a click listener on <wpsstm-tracklist> itself.
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
        //return ['id', 'my-custom-attribute', 'data-something', 'disabled'];
    }
    
    ///
    ///
    
    debug(msg){
        var debug = {message:msg,tracklist:this};
        wpsstm_debug(debug);
    }
    
    /*
    Watch the first track.  If it is requested, maybe reload tracklist.
    */
    
    firstTrackWatch(mutationsList) {

        for(var mutation of mutationsList) {
            
            if (mutation.type == 'attributes') {

                var attrName = mutation.attributeName;
                var attrValue = mutation.target.getAttribute(attrName);

                switch (attrName) {
                    case 'class':
                        
                        var track = mutation.target;
                        if ( $(track).hasClass('track-active') ){ //TOUFIX maybe we need something more consistant here

                            var tracklist = track.closest('wpsstm-tracklist');

                            if (tracklist.isExpired){
                                tracklist.debug("TRACK#1 requested but tracklist is outdated, refresh!");
                                tracklist.reload_tracklist(true);
                            }
                        }

                    break;
                }
                
                
                
            }
        }
    }
    
    /*
    Watch for tracklist queue update.
    */
    
    pageQueueWatch(mutationsList){

        for(var mutation of mutationsList) {
            if (mutation.type == 'childList') {
                var queue = mutation.target;
                var tracklist = mutation.target.closest('wpsstm-tracklist');
                var firstPageTrack = $(tracklist).find('wpsstm-track').first().get(0);
                var firstTrackObserver = new MutationObserver(tracklist.firstTrackWatch);
                firstTrackObserver.observe(firstPageTrack,{attributes: true});
            }
        }
    }

    render(){

        var self = this;

        self.post_id =                  Number( $(self).data('wpsstm-tracklist-id') );
        self.options =                  $(self).data('wpsstm-tracklist-options');

        self.init_tracklist_expiration();

        /*
        New subtracks
        */

        var new_subtrack_el = $(self).find('.wpsstm-new-subtrack');
        new_subtrack_el.addClass('wpsstm-new-subtrack-simple');
        var new_subtrack_bt = new_subtrack_el.find('button');
        
        new_subtrack_bt.click(function(e) {
            
             e.preventDefault();
            
            var isExpanded = !new_subtrack_bt.parents('.wpsstm-new-subtrack').hasClass('wpsstm-new-subtrack-simple');
            
            if (!isExpanded){
                new_subtrack_el.removeClass('wpsstm-new-subtrack-simple');
            }else{
                var track = new WpsstmTrack();
                track.track_artist = new_subtrack_el.find('input[name="wpsstm_track_data[artist]"]').val();
                track.track_title = new_subtrack_el.find('input[name="wpsstm_track_data[title]"]').val();
                track.track_album = new_subtrack_el.find('input[name="wpsstm_track_data[album]"]').val();
                
                self.new_subtrack(track);
                
            }

        });

        /*
        Refresh BT
        */
        var refresh_bt = $(self).find(".wpsstm-tracklist-action-refresh a");
        refresh_bt.click(function(e) {
            e.preventDefault();
            self.debug("clicked 'refresh' bt");
            self.reload_tracklist();
        });
        
        /* Observe first track to know if we need to update the tracklist*/
        
        //in page, at init
        var firstPageTrack = $(self).find('wpsstm-track').first().get(0);
        if (firstPageTrack){
            var firstTrackObserver = new MutationObserver(self.firstTrackWatch);
            firstTrackObserver.observe(firstPageTrack,{attributes: true});
        }

        //in page, at queue update
        var queue = $(self).find('.wpsstm-tracks-list').get(0);
        if (queue){
            var firstTrackObserver = new MutationObserver(self.pageQueueWatch);
            firstTrackObserver.observe(queue,{childList: true});
        }

        /*
        Tracklist actions
        */

        //toggle favorite
        $(self).find('.wpsstm-tracklist-action-favorite a,.wpsstm-tracklist-action-unfavorite a').click(function(e) {
            e.preventDefault();
            
            var action_el = $(this).parents('.wpsstm-tracklist-action');
            var do_love = action_el.hasClass('wpsstm-tracklist-action-favorite');

            self.toggle_favorite_tracklist(do_love);
        });


        /*
        Subtracks
        */

        var tracks = $(self).find('wpsstm-track');

        //sort subtracks
        var startSortIdx, endSortIdx;
        $(self).find( '.wpsstm-tracks-list' ).sortable({
            axis: "y",
            handle: '.wpsstm-track-action-move',
            start: function(event, ui) { 
                startSortIdx = ui.item.index();
            },
            update: function(event, ui) {
                endSortIdx = ui.item.index();
                var track = tracks.get(startSortIdx);
                var old_position = Number($(track).attr('data-wpsstm-subtrack-position'));
                var new_position = ui.item.index() + 1;


                if (track){
                    //new position
                    track.position = ui.item.index();
                    self.update_subtrack_position(track,new_position);
                }

            }
        });

        self.debug("Tracklist ready");
        
        $(document).trigger("wpsstmTracklistReady",[self]); //custom event
    }
    
    get_instances(){
        var self = this;
        return $(document).find('wpsstm-tracklist[data-wpsstm-tracklist-id="'+self.post_id+'"]');
    }

    reload_tracklist(autoplay){
        var self = this;
        
        //stop player
        var activeTrack = $(self).find('wpsstm-track.track-active').get(0);
        
        if (activeTrack){
            activeTrack.removeAttribute('trackstatus');
            if (typeof autoplay === 'undefined'){
                autoplay = true;
            }
        }

        self.debug("reload tracklist... autoplay ?" + autoplay);

        var ajax_data = {
            action:     'wpsstm_reload_tracklist',
            tracklist:      self.to_ajax(),   
        };

        return $.ajax({
            type:           "post",
            url:            wpsstmL10n.ajaxurl,
            data:           ajax_data,
            dataType:       'json',
            beforeSend:     function() {
                $(self).addClass('tracklist-reloading');
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
                    self.replaceWith( newTracklist );
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(self).removeClass('tracklist-reloading');
            }
        })
    }
    
    toggle_favorite_tracklist(do_love){
        var self = this;
        
        if (do_love){
            var link_el = $(self).find('.wpsstm-tracklist-action-favorite a');
        }else{
            var link_el = $(self).find('.wpsstm-tracklist-action-unfavorite a');
        }

        var ajax_data = {
            action:     'wpsstm_tracklist_toggle_favorite',
            tracklist:  self.to_ajax(),   
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
                        wpsstm_dialog_notice(data.notice);
                    }
                }else{
                    if (do_love){
                        $(self).addClass('favorited-tracklist');
                    }else{
                        $(self).removeClass('favorited-tracklist');
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
        var self = this;

        var now = Math.round( $.now() /1000);
        var remaining_sec = null;
        
        var meta_expiration = $(self).find('meta[itemprop="wpsstmRefreshTimer"]');
        if (meta_expiration.length){
            remaining_sec = meta_expiration.attr('content');
        }

        if (!remaining_sec) return;
        
        if (remaining_sec > 0){
            self.isExpired = false;
            var expirationTimer = setTimeout(function(){
                self.isExpired = true;
                $(self).addClass('tracklist-expired');
                self.debug("tracklist has expired, stop expiration timer");

            }, remaining_sec * 1000 );

        }else{
            self.isExpired = true;
        }
        
        if (remaining_sec < 0){
            self.debug("tracklist has expired "+Math.abs(remaining_sec)+" seconds ago");
        }else{
            self.debug("tracklist will expire in "+remaining_sec+" seconds");
        }

    }

    refresh_tracks_positions(){
        var self = this;
        var all_rows = $(self).find( 'wpsstm-track' );
        jQuery.each( all_rows, function( key, value ) {
            var position = jQuery(this).find('.wpsstm-track-position [itemprop="position"]');
            position.text(key + 1);
        });
    }

    update_subtrack_position(track,new_pos){
        var self = this;
        var track_instances = track.get_instances();
        var link_el = track_instances.find('.wpsstm-track-action-move a');

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
                track_instances.addClass('track-loading');
                link_el.removeClass('action-error');
                link_el.addClass('action-loading');
            },
            success: function(data){

                if (data.success === false) {
                    link_el.addClass('action-error');
                    console.log(data);
                }else{
                    self.refresh_tracks_positions();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link_el.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_instances.removeClass('track-loading');
                link_el.removeClass('action-loading');
            }
        })

    }

    //reduce object for communication between JS & PHP
    to_ajax(){

        var self = this;
        var allowed = ['post_id'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
            obj[key] = self[key];
            return obj;
        }, {});
        return filtered;
    }
    
    new_subtrack(track){
        
        var self = this;
        var container_el = $(self).find('.wpsstm-new-subtrack');
        var tracklist = container_el.parents('wpsstm-tracklist').get(0);

        var ajax_data = {
            action:         'wpsstm_tracklist_new_subtrack',
            track:          track.to_ajax(),
            tracklist_id:   self.post_id
        };

        return $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',

            beforeSend: function() {
                container_el.removeClass('action-error').addClass('action-loading');
            },
            success: function(data){

                if (data.success === false) {
                    console.log(data);
                }else{
                    tracklist.reload_tracklist();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                container_el.addClass('action-error');
            },
            complete: function() {
                container_el.removeClass('action-loading');
            }
        })
    }
    
    
}

window.customElements.define('wpsstm-tracklist', WpsstmTracklist);
