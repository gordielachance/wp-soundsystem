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

//TOUFIX expand tracklist to the current track / currently disabled
/*
$(document).on( "wpsstmRequestPlay", function( event, track_obj ) {

    //expand tracklist
    if ( $(track_obj).is(":visible") ) return;

    var tracklist_obj = $(track_obj).parents('wpsstm-tracklist');
    var tracklist_el = track_obj.tracklist;
    var visibleTracksCount = tracklist.find('wpsstm-track:visible').length;
    var newTracksCount = track_obj.position + 1;

    if ( newTracksCount <= visibleTracksCount ) return;

    if ( tracklist_el.options.toggle_tracklist ){
        tracklist_el.showMoreLessTracks({
            childrenToShow:newTracksCount
        });
    }

});
*/

class WpsstmTracklist extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.index =                    undefined;
        this.post_id =                  undefined;
        this.tracklist_request =        undefined;
        this.tracks =                   [];
        this.tracks_count =             undefined;
        this.options =                  [];
        this.can_play =                 undefined;
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
        var prefix = "WpsstmTracklist #" + this.index;
        wpsstm_debug(msg,prefix);
    }
    
    render(){

        var self = this;
        self.tracks =                   []; //unset current tracks

        self.post_id =                  Number( $(self).data('wpsstm-tracklist-id') );
        self.options =                  $(self).data('wpsstm-tracklist-options');

        self.init_tracklist_expiration();

        /*
        Advanced header
        */
        var advancedHeaderContent = $(self).find('.tracklist-advanced-header');
        advancedHeaderContent.hide();
        $(self).find('.tracklist-advanced-header-bt').click(function(e) {
            e.preventDefault();
            $(this).hide();
            advancedHeaderContent.show();
        });

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

        var tracks_html = $(self).find('wpsstm-track');

        if ( tracks_html.length ){
            $.each(tracks_html, function( index, track_html ) {
                self.tracks.push(track_html);
            });
        }

        /* tracks count */
        self.tracks_count = $(self.tracks).length;
        self.can_play =     (self.tracks_count > 0);

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
                var track = self.tracks[startSortIdx];
                var old_position = Number($(track).attr('data-wpsstm-subtrack-position'));
                var new_position = ui.item.index() + 1;


                if (track){
                    //new position
                    track.position = ui.item.index();
                    self.update_subtrack_position(track,new_position);
                }

            }
        });

        //hide a tracklist columns if all datas are the same
        self.checkTracksIdenticalValues();

        console.log("Tracklist #" + self.index + " is ready");
        
        $(self).trigger("wpsstmTracklistReady"); //custom event
    }
    
    get_instances(){
        var self = this;
        return $(document).find('wpsstm-tracklist[data-wpsstm-tracklist-id="'+self.post_id+'"]');
    }

    reload_tracklist(autoplay){
        var self = this;
        
        if (typeof autoplay === 'undefined'){
            autoplay = $(self).hasClass('tracklist-playing');
        }

        self.debug("reload tracklist... autoplay ?" + autoplay);

        var ajax_data = {
            action:     'wpsstm_load_tracklist',
            tracklist:      self.to_ajax(),   
        };

        return $.ajax({
            type:           "post",
            url:            wpsstmL10n.ajaxurl,
            data:           ajax_data,
            dataType:       'json',
            beforeSend:     function() {
                $(self).addClass('tracklist-reloading');
                $(self).trigger("wpsstmTracklistBeforeReload"); //custom event
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    
                    /*
                    If the tracklist WAS playing, keep those classes (used for autoplay).
                    */
                    var newTracklist = $(data.html);

                    if (autoplay===true){
                        newTracklist.find('wpsstm-track:first-child').addClass('track-autoplay');
                    }

                    //swap content
                    self.replaceWith( newTracklist.get(0) );
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
        
    showMoreLessTracks(options){

        var self = this;

        // OPTIONS
        var defaults = {
            childrenShowCount:  true,
            childrenToShow:        3,
            childrenSelector:   'wpsstm-track',
            btMore:             '<li><i class="fa fa-angle-down" aria-hidden="true"></i></li>',
            lessText:           '<li><i class="fa fa-angle-up" aria-hidden="true"></i></li>',
        };

        var options =  $.extend(defaults, options);

        if ( this.tracks_count > 0 ) {
            return $(self).find('.wpsstm-tracks-list').toggleChildren(options);
        }

    }
    /*
    For each track, check if certain cells (image, artist, album...) have the same value - and hide them if yes.
    */
    checkTracksIdenticalValues() {
        
        var self = this;

        if (self.tracks.length <= 1) return;
        
        var track_els = $($(self)).find('wpsstm-track');
        
        var selectors = ['.wpsstm-track-image','[itemprop="byArtist"]','[itemprop="name"]','[itemprop="inAlbum"]'];
        var values_by_selector = [];
        
        function onlyUnique(value, index, self) { 
            return self.indexOf(value) === index;
        }

        $.each( $(selectors), function() {
            var hide_column = undefined;
            var cells = track_els.find(this); //get all track children by selector
            
            var column_datas = cells.map(function() { //get all values for the matching items
                return $(this).html();
            }).get();
            
            var unique_values = column_datas.filter( onlyUnique ); //remove duplicate values

            if (unique_values.length <= 1){
                hide_column = true; //column has a single values; hide this column
            }
            
            if (hide_column){
                cells.addClass('wpsstm-track-unique-value');
            }else{
                cells.removeClass('wpsstm-track-unique-value');
            }
            
        });
        
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
