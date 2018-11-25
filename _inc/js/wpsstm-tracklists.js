(function($){

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
    
    $(document).on("wpsstmTracklistInit", function( event, tracklist_obj ) {
        //style new subtracks row
        var new_subtrack_row = tracklist_obj.tracklist_el.find('.wpsstm-new-subtrack');
        new_subtrack_row.addClass('wpsstm-new-subtrack-simple');
        var new_subtrack_bt = new_subtrack_row.find('button');
        new_subtrack_bt.click(function(e) {
            if ( new_subtrack_bt.parents('.wpsstm-new-subtrack').hasClass('wpsstm-new-subtrack-simple') ){
                e.preventDefault();
                new_subtrack_row.removeClass('wpsstm-new-subtrack-simple');
            }
        });

    });

    $(document).on( "wpsstmTracklistLoaded", function( event, tracklist_obj ) {
        
        /*
        Tracklist actions
        */

        //refresh
        var refresh_bts = tracklist_obj.tracklist_el.find(".wpsstm-tracklist-action-refresh a,a.wpsstm-refresh-tracklist");
        refresh_bts.click(function(e) {
            e.preventDefault();
            tracklist_obj.debug("clicked 'refresh' bt")
            var reloaded = self.reload_tracklist(tracklist_obj);
        });

        //favorite
        var favorite_bt = tracklist_obj.tracklist_el.find('.wpsstm-tracklist-action-favorite a');
        favorite_bt.click(function(e) {
            e.preventDefault();

            var link = $(this);
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = Number(tracklist_wrapper.attr('data-wpsstm-tracklist-id'));

            var ajax_data = {
                action:         'wpsstm_toggle_favorite_tracklist',
                post_id:        tracklist_id,
                do_love:        true,
            };

            tracklist_obj.debug("favorite tracklist:" + tracklist_id);

            return $.ajax({
                type:       "post",
                url:        wpsstmL10n.ajaxurl,
                data:       ajax_data,
                dataType:   'json',
                beforeSend: function() {
                    link.addClass('action-loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                        link.addClass('action-error');
                        if (data.notice){
                            wpsstm_dialog_notice(data.notice);
                        }
                    }else{
                        tracklist_obj.tracklist_el.addClass('wpsstm-loved-tracklist');
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    link.addClass('action-error');
                    console.log(xhr.status);
                    console.log(thrownError);
                },
                complete: function() {
                    link.removeClass('action-loading');
                }
            })
        });
        
        //unfavorite
        var unfavorite_bt = tracklist_obj.tracklist_el.find('.wpsstm-tracklist-action-unfavorite a');
        unfavorite_bt.click(function(e) {
            e.preventDefault();

            var link = $(this);
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = Number(tracklist_wrapper.attr('data-wpsstm-tracklist-id'));

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_toggle_favorite_tracklist',
                post_id:        tracklist_id,
                do_love:        false,
            };

            tracklist_obj.debug("unfavorite tracklist:" + tracklist_id);

            return $.ajax({
                type:       "post",
                url:        wpsstmL10n.ajaxurl,
                data:       ajax_data,
                dataType:   'json',
                beforeSend: function() {
                    link.addClass('action-loading');
                },
                success: function(data){
                    if (data.success === false) {
                        link.addClass('action-error');
                        console.log(data);
                    }else{
                        tracklist_obj.tracklist_el.removeClass('wpsstm-loved-tracklist');
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    link.addClass('action-error');
                    console.log(xhr.status);
                    console.log(thrownError);
                },
                complete: function() {
                    link.removeClass('action-loading');
                }
            })
        });

        /*
        Tracks
        */


        // sort tracks
        tracklist_obj.tracklist_el.find( '.wpsstm-tracks-list' ).sortable({
            axis: "y",
            handle: '.wpsstm-track-action-move',
            update: function(event, ui) {
                console.log('update: '+ui.item.index())
                //get track
                var track_el = $(ui.item);
                var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
                var track_obj = tracklist_obj.get_track_obj(track_idx);

                //new position
                track_obj.index = ui.item.index();
                tracklist_obj.update_track_index(track_obj);
                
            }
        });
        
        //hide a tracklist columns if all datas are the same
        tracklist_obj.checkTracksIdenticalValues();

    });
    
    
    //TOUFIX expand tracklist to the current track / currently disabled
    /*
    $(document).on( "wpsstmRequestPlay", function( event, track_obj ) {

        var tracklist_obj = track_obj.tracklist;
        
        //expand tracklist
        var track_el = track_obj.track_el;
        if ( track_el.is(":visible") ) return;

        var visibleTracksCount = tracklist_obj.tracklist_el.find('[itemprop="track"]:visible').length;
        var newTracksCount = track_obj.index + 1;
        
        if ( newTracksCount <= visibleTracksCount ) return;

        if ( tracklist_obj.options.toggle_tracklist ){
            tracklist_obj.showMoreLessTracks({
                childrenToShow:newTracksCount
            });
        }
        
    });
    */
    
    

})(jQuery);

class WpsstmTracklist {
    constructor(tracklist_html,index) {
        
        this.index =                    ( index === undefined) ? 0 : index;
        this.post_id =                  undefined;
        this.tracklist_el =             $([]);
        this.tracklist_request =        undefined;
        this.tracks =                   undefined;
        this.tracks_count =             undefined;
        this.options =                  [];
        this.can_play =                 undefined;
        this.isExpired =                undefined;

        this.populate_html(tracklist_html);
    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #" + this.index;
        wpsstm_debug(msg,prefix);
    }
    
    populate_html(tracklist_html){

        this.tracklist_el =             $(tracklist_html);
        if (this.tracklist_el.length == 0) return;

        this.post_id =                  Number( this.tracklist_el.data('wpsstm-tracklist-id') );
        this.options =                  this.tracklist_el.data('wpsstm-tracklist-options');

        this.init_tracklist_expiration();
        this.load_tracklist_tracks();

        console.log("init tracklist #" + this.index);
        $(document).trigger("wpsstmTracklistInit",[this]); //custom event
    }
    
    load_tracklist_tracks(){
        
        var self = this;

        var tracks_html = self.tracklist_el.find('[itemprop="track"]');

        self.tracks = [];

        if ( !tracks_html.length) return;

        $.each(tracks_html, function( index, track_html ) {
            var new_track = new WpsstmTrack(track_html,self);
            self.tracks.push(new_track);
        });

        /* tracks count */
        self.tracks_count = $(self.tracks).length;
        self.can_play =     (self.tracks_count > 0);

        $(document).trigger("wpsstmTracklistLoaded",[self]); //custom event
    }
    
    init_tracklist_expiration(){
        var self = this;
        
        var now = Math.round( $.now() /1000);
        self.isExpired = false;
        var remaining_sec = null;
        
        var meta_expiration = self.tracklist_el.find('meta[itemprop="wpsstmRefreshTimer"]');
        if (meta_expiration.length){
            remaining_sec = meta_expiration.attr('content');
        }

        if (!remaining_sec) return;
        
        if (remaining_sec > 0){

            var expirationTimer = setTimeout(function(){
                self.isExpired = true;
                self.tracklist_el.addClass('tracklist-expired');
                self.debug("tracklist has expired, stop expiration timer");

            }, remaining_sec * 1000 );

        }
        
        if (remaining_sec < 0){
            self.debug("tracklist has expired "+Math.abs(remaining_sec)+" seconds ago");
        }else{
            self.debug("tracklist will expire in "+remaining_sec+" seconds");
        }

    }

    get_track_obj(track_idx){
        var self = this;
        
        track_idx = Number(track_idx);
        var track_obj = self.tracks[track_idx];
        if(typeof track_obj !== undefined) return track_obj;

        return false;
    }

    update_tracks_order(){
        var self = this;
        var all_rows = self.tracklist_el.find( '[itemprop="track"]' );
        jQuery.each( all_rows, function( key, value ) {
            var position = jQuery(this).find('.wpsstm-track-position [itemprop="position"]');
            position.text(key + 1);
        });
    }

    update_track_index(track_obj){
        var self = this;
        var link = track_obj.track_el.find('.wpsstm-track-action-move a');

        //ajax update order
        var ajax_data = {
            action            : 'wpsstm_set_track_position',
            tracklist_id      : self.post_id,
            track:              track_obj.to_ajax()
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                link.addClass('action-loading');
            },
            success: function(data){
                if (data.success === false) {
                    link.addClass('action-error');
                    console.log(data);
                }else{
                    self.update_tracks_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                link.removeClass('action-loading');
            }
        })

    }
    
    remove_subtrack(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;
        var link = track_el.find('.wpsstm-track-action-remove a');

        var ajax_data = {
            action            : 'wpsstm_remove_from_tracklist',
            tracklist_id      : self.post_id,
            track_id          : track_obj.post_id
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_el.addClass('track-loading');
            },
            success: function(data){
                if (data.success === false) {
                    link.addClass('action-error');
                    console.log(data);
                }else{
                    $(track_el).remove();
                    self.update_tracks_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                link.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_el.removeClass('track-loading');
            }
        })

    }
    
    showMoreLessTracks(options){

        var self = this;

        // OPTIONS
        var defaults = {
            childrenShowCount:  true,
            childrenToShow:        3,
            childrenSelector:   '[itemprop="track"]',
            btMore:             '<li><i class="fa fa-angle-down" aria-hidden="true"></i></li>',
            lessText:           '<li><i class="fa fa-angle-up" aria-hidden="true"></i></li>',
        };

        var options =  $.extend(defaults, options);

        if ( this.tracks_count > 0 ) {
            return $(this.tracklist_el).find('.wpsstm-tracks-list').toggleChildren(options);
        }

    }
    /*
    For each track, check if certain cells (image, artist, album...) have the same value - and hide them if yes.
    */
    checkTracksIdenticalValues() {
        
        var self = this;

        var all_tracks = $(self.tracklist_el).find('.wpsstm-tracks-list > *');
        if (all_tracks.length < 2) return;
        var selectors = ['.wpsstm-track-image','[itemprop="byArtist"]','[itemprop="name"]','[itemprop="inAlbum"]'];
        var values_by_selector = [];
        
        function onlyUnique(value, index, self) { 
            return self.indexOf(value) === index;
        }

        $.each( $(selectors), function() {
            var hide_column = undefined;
            var cells = all_tracks.find(this); //get all track children by selector
            
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
    
}
