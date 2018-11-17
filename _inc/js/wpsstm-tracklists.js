(function($){
    
    $( document ).ready(function() {
      var playlists = $(".wpsstm-tracklist");
        $.each(playlists, function( index, playlist_html ) {
            var tracklist = new WpsstmTracklist(playlist_html);
        });
    });
    
    $('iframe').load(function(e){
        
        console.log("IFRAME LOADED");
        
        var iframe = $(this).get(0);
        
        $(this).parents('.wpsstm-iframe-container').removeClass('wpsstm-iframe-loading');
        
        //var content = $(iframe.contentWindow.document.body);
        //var tracklist_el = $(content).find( ".wpsstm-tracklist" ).get(0);
    });
    
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

        wpsstm.tracklists.push(tracklist_obj);
        wpsstm.tracklists_shuffle_order = wpsstm_shuffle( Object.keys(wpsstm.tracklists).map(Number) );
        
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
        
        /*
        autoplay ?
        */

        if ( (wpsstm.current_media !==undefined) && !wpsstm.current_media.onplaying ){ //is not playing //TOUFIX TOCHECK this statement is not working
            //which one could we autoplay?
            var tracklists_autoplay = wpsstm.get_ordered_tracklists().filter(function (tracklist_obj) {
                //TOUFIX we should check the autoplay property of the HTML5 element instead of this one.
                return tracklist_obj.options.autoplay;
            });
            var play_tracklist = tracklists_autoplay[0];
            if ( play_tracklist ){
                console.log("autoplay tracklist #" + tracklist_obj.index);
                play_tracklist.start_tracklist();
            }
        }

    });

    $(document).on("wpsstmStartTracklist", function( event, tracklist_obj ) {
        
        if ( tracklist_obj.is_expired ){
            tracklist_obj.debug("cache expired, refresh tracklist");
            tracklist_obj.reload_tracklist();
        }
    });

    $(document).on( "wpsstmTracklistLoaded", function( event, tracklist_obj ) {
        
        /*
        Tracklist actions
        */

        //refresh
        var refresh_bts = tracklist_obj.tracklist_el.find(".wpsstm-tracklist-action-refresh a,a.wpsstm-refresh-tracklist");
        refresh_bts.click(function(e) {
            tracklist_obj.tracklist_el.find('.wpsstm-iframe-container').addClass('wpsstm-iframe-loading');
            tracklist_obj.debug("clicked 'refresh' link");
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
                        var tracklist_instances = tracklist_obj.get_tracklist_instances();
                        tracklist_instances.addClass('wpsstm-loved-tracklist');
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
                        var tracklist_instances = tracklist_obj.get_tracklist_instances()
                        tracklist_instances.removeClass('wpsstm-loved-tracklist');
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
        
        //show more/less (tracks)
        //TOUFIX
        /*
        if ( showSubtracksCount = tracklist_obj.options.toggle_tracklist ){
            tracklist_obj.showMoreLessTracks({
                childrenToShow:showSubtracksCount
            });
        }
        
        
        //show more/less (tracklist/tracks/sources actions)
        var actions_lists = tracklist_obj.tracklist_el.find('.wpsstm-actions-list');
        wpsstm.showMoreLessActions(actions_lists);
        
        */
    });
    
    

})(jQuery);

class WpsstmTracklist {
    constructor(tracklist_html) {
        
        this.index =                    undefined;
        this.post_id =                  undefined;
        this.tracklist_el =             $([]);
        this.iframe_el =                $([]);
        this.tracklist_request =        undefined;
        this.is_expired =               undefined;
        this.expiration_time =          undefined;
        this.tracks =                   undefined;
        this.tracks_count =             undefined;
        this.tracks_shuffle_order =     undefined;
        this.options =                  wpsstmPlayer.default_tracklist_options;
        this.can_play =                 undefined;

        this.populate_html(tracklist_html);
    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #" + this.index;
        wpsstm_debug(msg,prefix);
    }
    
    populate_html(tracklist_html){

        this.tracklist_el =             $(tracklist_html);
        if (this.tracklist_el.length == 0) return;
        
        //TOUFIX TO IMPROVE we should locate the parent element from within the frame, what if we have several .wpsstm-iframe-container ?
        this.iframe_el =                $(parent.document).find('.wpsstm-iframe-container iframe');

        this.index =                    Number( this.tracklist_el.data('wpsstm-tracklist-idx') );
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
        self.tracks_shuffle_order = [];

        if ( !tracks_html.length) return;

        $.each(tracks_html, function( index, track_html ) {
            var new_track = new WpsstmTrack(track_html,self);
            self.tracks.push(new_track);
        });

        self.tracks_shuffle_order = wpsstm_shuffle( Object.keys(self.tracks).map(Number) );

        /* tracks count */
        self.tracks_count = $(self.tracks).length;
        self.can_play =     (self.tracks_count < 0) ? undefined : true; //if -1:not yet populated

        $(document).trigger("wpsstmTracklistLoaded",[self]); //custom event
    }
    
    init_tracklist_expiration(){
        var self = this;
        
        var now = Math.round( $.now() /1000);
        var expiration_time = null;
        var is_expired = false;
        var remaining_sec = null;
        
        var meta_expiration = self.tracklist_el.find('meta[itemprop="wpsstmExpiration"]');
        
        if (meta_expiration.length){
            var value = meta_expiration.attr('content');
            expiration_time = Number(value);

            is_expired = now > expiration_time;
            remaining_sec = expiration_time - now;
            
            var readable_date = new Date();
            readable_date.setTime(expiration_time*1000);
            var readable_date_str = readable_date.toUTCString();
            
            ///
            self.expiration_time = expiration_time;
            self.is_expired = is_expired;
            ///
            
            if (is_expired){
                console.log("this tracklist has expired on: " + readable_date_str);
            }else{
                if (remaining_sec > 0){

                    setTimeout(function(){
                        self.is_expired = true;
                        self.tracklist_el.addClass('tracklist-expired');
                        self.debug("tracklist has expired, stop expiration timer");

                    }, remaining_sec * 1000 );
                }
                console.log("tracklist will expire on: " + readable_date_str + " (in "+remaining_sec+" seconds)");
            }
        }

    }

    reload_tracklist(){
        console.log("reload iframe");
        var iframe = this.iframe_el;
        var container = iframe.parents('.wpsstm-iframe-container');
        container.addClass('wpsstm-iframe-loading');
        iframe.get(0).contentWindow.location.reload(true);
    }

    get_track_obj(track_idx){
        var self = this;
        
        if(typeof track_idx === undefined){
            if (wpsstm.current_track){
                return wpsstm.current_track;
            }
        }else{
            track_idx = Number(track_idx);
            var track_obj = self.tracks[track_idx];
            if(typeof track_obj !== undefined) return track_obj;
        }


        return false;
    }
    
    get_tracklist_instances(){
        var tracklist_el = $('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.index+'"]');
        return tracklist_el;
    }

    get_maybe_shuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm.is_shuffle ) return idx;
        var new_idx = self.tracks_shuffle_order[idx];
        
        self.debug("get_maybe_shuffle_track_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    get_maybe_unshuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm.is_shuffle ) return idx;
        var shuffle_order = self.tracks_shuffle_order;
        var new_idx = shuffle_order.indexOf(idx);
        
        self.debug("get_maybe_unshuffle_track_idx : " + idx + "-->" + new_idx);
        return new_idx;
    }

    abord_tracks_sources_request() {
        
        var self = this;
        
        $.each(self.tracks, function( index, track ) {
            if (track.sources_request){
                track.sources_request.abort();
            }
        });

    };
    
    /*
    Return the tracks; in the shuffled order if is_shuffle is true.
    */
    get_ordered_tracks(){
        
        self = this;

        if ( !wpsstm.is_shuffle ){
            return self.tracks;
        }else{
            
            var shuffled_tracks = [];

            $(self.tracks_shuffle_order).each(function() {
                var idx = this;
                shuffled_tracks.push(self.tracks[idx]);
            });

            return shuffled_tracks;
        }
        
    }

    previous_track_jump(){

        var self = this;
        
        var current_track_idx = ( wpsstm.current_track ) ? wpsstm.current_track.index : 0;
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx); //shuffle ?

        var tracks_before = self.get_ordered_tracks().slice(0,current_track_idx).reverse();

        //which one should we play?
        var tracks_playable = tracks_before.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        var track_obj = tracks_playable[0];

        if (track_obj){
            track_obj.play_track();
        }else{
            
            self.debug("previous_track_jump: can repeat ? " + wpsstm.can_repeat);
            
            if (wpsstm.can_repeat){
                wpsstm.previous_tracklist_jump();
            }
        }
    }
    
    next_track_jump(){

        var self = this;
        
        var current_track_idx = ( wpsstm.current_track ) ? wpsstm.current_track.index : 0;
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx); // shuffle ?

        var tracks_after = self.get_ordered_tracks().slice(current_track_idx+1);

        //which one should we play?
        var tracks_playable = tracks_after.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        
        var track_obj = tracks_playable[0];

        if (track_obj){
            track_obj.play_track();
        }else{
            
            self.debug("next_track_jump: can repeat ? " + wpsstm.can_repeat);
            
            if (wpsstm.can_repeat){
                
                if (self.is_expired){
                    self.debug("next_track_jump: tracklist is expired, unset 'can_play'");
                    self.can_play = undefined; //will allow to refresh tracklist when calling maybe_refresh
                }

                $(document).trigger("wpsstmStopTracklist",[self]); //custom event
                
                wpsstm.next_tracklist_jump();
            }
        }


    }

    start_tracklist(){
        var self = this;

        var current_track = wpsstm.current_track;
        if (current_track){
            if ( self.index === current_track.tracklist.index ){
                self.debug("loop tracklist");
                $(document).trigger("wpsstmLoopTracklist",[self]); //custom event
            }
        }

        var track_idx = 0;
        track_idx = self.get_maybe_shuffle_track_idx(track_idx); //shuffle ?

        var track_obj = self.tracks[track_idx];

        if (track_obj){
            track_obj.play_track();
        }
        
        self.debug("start tracklist");
        
        $(document).trigger("wpsstmStartTracklist",[self]); //custom event

        

    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    maybe_load_queue_sources() {

        var self = this;

        var max_items = 4; //number of following tracks to preload
        var rtrack_in = wpsstm.current_track.index + 1;
        var rtrack_out = wpsstm.current_track.index + max_items + 1;

        var tracks_slice = $(self.tracks).slice( rtrack_in, rtrack_out );

        $(tracks_slice).each(function(index, track_to_preload) {
            if ( track_to_preload.sources.length > 0 ) return true; //continue;
            track_to_preload.maybe_load_sources();
        });
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
    
    remove_tracklist_track(track_obj){
        
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
