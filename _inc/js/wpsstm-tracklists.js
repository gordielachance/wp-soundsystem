(function($){
    
    $(document).on("wpsstmStartTracklist", function( event, tracklist_obj ) {
        
        if ( tracklist_obj.is_expired ){
            tracklist_obj.debug("cache expired, refresh tracklist");
            var promise = tracklist_obj.get_tracklist_request();
        }
    });

    $(document).on( "wpsstmTracklistRefreshed", function( event, tracklist_obj ) {

        // sort tracks
        tracklist_obj.tracklist_el.find( '.wpsstm-tracks-list tbody' ).sortable({
            axis: "y",
            handle: '#wpsstm-track-action-move',
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
        
        if ( tracklist_obj.tracklist_el.hasClass("wpsstm-hide-empty-columns" ) ){
            tracklist_obj.hideEmptyColumns();
        }
        
        
        /*
        Tracklist actions
        */
        
        //refresh
        tracklist_obj.tracklist_el.filter('.tracklist-ajaxed').find("#wpsstm-tracklist-action-refresh a").click(function(e) {
            e.preventDefault();
            tracklist_obj.can_play = undefined; //will force refresh
            tracklist_obj.debug("clicked 'refresh' link");
            tracklist_obj.get_tracklist_request();

        });


        //favorite
        tracklist_obj.tracklist_el.find('#wpsstm-tracklist-action-favorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var action = $(this).parents('.wpsstm-tracklist-action');
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = Number(tracklist_wrapper.attr('data-wpsstm-tracklist-id'));

            if (!tracklist_id) return;

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
                    action.addClass('wpsstm-loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var tracklist_instances = tracklist_obj.get_tracklist_instances()
                        tracklist_instances.addClass('wpsstm-loved-tracklist');
                    }
                },
                complete: function() {
                    action.removeClass('wpsstm-loading');
                }
            })
        });
        
        //unfavorite
        tracklist_obj.tracklist_el.find('#wpsstm-tracklist-action-unfavorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var action = $(this).parents('.wpsstm-tracklist-action');
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
                    action.addClass('wpsstm-loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var tracklist_instances = tracklist_obj.get_tracklist_instances()
                        tracklist_instances.removeClass('wpsstm-loved-tracklist');
                    }
                },
                complete: function() {
                    action.removeClass('wpsstm-loading');
                }
            })
        });
        
        //switch status
        tracklist_obj.tracklist_el.find("#wpsstm-tracklist-action-status-switch a").click(function(e) {
            e.preventDefault();
            $(this).closest('li').toggleClass('expanded');
            
        });
        
        //remove
        tracklist_obj.tracklist_el.find('#wpsstm-track-action-remove a').click(function(e) {
            e.preventDefault();
            
            //get track
            var track_el = $(this).closest('[itemprop="track"]');
            var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
            var track_obj = tracklist_obj.get_track_obj(track_idx);
            
            tracklist_obj.remove_tracklist_track(track_obj);
        });
        
        //delete
        tracklist_obj.tracklist_el.find('#wpsstm-track-action-delete a').click(function(e) {
            e.preventDefault();
            
            //get track
            var track_el = $(this).closest('[itemprop="track"]');
            var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
            var track_obj = tracklist_obj.get_track_obj(track_idx);
            
            tracklist_obj.delete_playlist_track(track_obj);
        });
        
        //toggle expand tracks at init
        if ( showSubtracksCount = tracklist_obj.options.toggle_tracklist ){
            tracklist_obj.toggleTracklist({
                childrenMax:showSubtracksCount
            });
        }

    });

    
})(jQuery);

class WpsstmTracklist {
    constructor(tracklist_el,tracklist_index) {
        this.index =                    tracklist_index; //index in page
        this.tracklist_el =             undefined;
        this.post_id =                  undefined;
        this.tracklist_request =        undefined;
        this.is_expired =               undefined;
        this.expire_time =              undefined;
        this.options =                  {};
        this.tracks =                   [];
        this.tracks_shuffle_order =     [];
        this.populate_tracklist(tracklist_el);
        this.can_play =                 undefined;
    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #" + this.index + ": ";
        wpsstm_debug(msg,prefix);
    }

    maybe_refresh(){
        
        self = this;

        var initCheck = $.Deferred();
        
        if (typeof self.can_play !== 'undefined'){
            initCheck.resolve("we already have refreshed this playlist");
        }else{

            var upToDateTracklist = $.Deferred();

            upToDateTracklist = self.get_tracklist_request();
            upToDateTracklist.done(function(message) {
                initCheck.resolve(message);
            });

            upToDateTracklist.fail(function(jqXHR, textStatus, errorThrown) {
                self.debug("init refresh did NOT succeed");
                initCheck.reject();
            });


        }

        return initCheck.promise();

        
    }

    populate_tracklist(tracklist_el){
        
        var self = this;
        
        self.tracklist_el = $(tracklist_el);

        self.tracklist_el.attr('data-wpsstm-tracklist-idx',self.index);

        self.post_id = Number( self.tracklist_el.attr('data-wpsstm-tracklist-id') );
        
        self.options = $.parseJSON( self.tracklist_el.attr('data-wpsstm-tracklist-options') );
        
        /*
        expiration
        */
        var expire_time_attr = self.tracklist_el.attr('data-wpsstm-expire-time');

        if (typeof expire_time_attr !== typeof undefined && expire_time_attr !== false) { //value exists
            self.expire_time = Number(expire_time_attr);
            var now = Math.round( $.now() /1000);
            self.is_expired = now > self.expire_time;
            self.tracklist_el.toggleClass('tracklist-expired',self.is_expired);
        }

        var tracks_html = self.tracklist_el.find('[itemprop="track"]');
        
        self.tracks = [];
        self.tracks_shuffle_order = [];
        
        if ( tracks_html.length > 0 ){
            $.each(tracks_html, function( index, track_html ) {
                var new_track = new WpsstmTrack(track_html,self,index);
                self.tracks.push(new_track);
                self.tracks_shuffle_order.push(index);
            });

            self.tracks_shuffle_order = wpsstm_shuffle(self.tracks_shuffle_order);

        }

        $(document).trigger("wpsstmTracklistRefreshed",[self]); //custom event
        
        if (self.expire_time){
            self.init_refresh_timer();
        }

    }

    get_tracklist_request(){

        var self = this;

        //already requested
        if (self.tracklist_request) return self.tracklist_request.promise();
        
        self.tracklist_request = $.Deferred();
        
        //not ajaxed
        if ( !self.tracklist_el.hasClass('tracklist-ajaxed') ){
            self.tracklist_request.resolve("tracklist is not ajaxed");
            return self.tracklist_request.promise();
        }

        self.debug("get_tracklist_request");

        var tracklist_instances = self.get_tracklist_instances();
        tracklist_instances.addClass('tracklist-loading tracklist-refresh');

        var ajax_data = {
            'action':           'wpsstm_refresh_tracklist',
            'post_id':          self.post_id,
            'options':          self.options,
        };

        self.tracklist_request = $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json'
        });


        self.tracklist_request.done(function(data) {
            if (data.success === false) {
                self.tracklist_el.addClass('tracklist-error');
                self.debug("get_tracklist_request did NOT succeed: no data");
                self.can_play = false;
            }else{
                var new_tracklist_el = $(data.new_html);
                self.tracklist_el.replaceWith(new_tracklist_el);
                self.populate_tracklist( new_tracklist_el );
                self.can_play = true;
                self.debug("get_tracklist_request did succeed");
            }
        });

        self.tracklist_request.fail(function(jqXHR, textStatus, errorThrown) {
            self.can_play = false;
            self.tracklist_el.addClass('tracklist-error');
            self.tracklist_el.find('#wpsstm-tracklist-action-refresh').addClass('error');
            self.debug("get_tracklist_request did NOT succeed");
        });  

        self.tracklist_request.always(function() {
            tracklist_instances.removeClass('tracklist-loading tracklist-refresh');
            self.tracklist_request = undefined;
        });

        return self.tracklist_request.promise();

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

    previous_track_jump(){

        var self = this;
        
        var track_idx = ( wpsstm.current_track ) ? wpsstm.current_track.index : 0;
        track_idx = self.get_maybe_unshuffle_track_idx(track_idx);
        var first_track_idx = self.get_maybe_unshuffle_track_idx(0);

        var tracks = $(self.tracks).get();
        var tracks_before = tracks.slice(0,track_idx).reverse();

        //which one should we play?
        var tracks_playable = tracks_before.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        var track_obj = tracks_playable[0];

        if (!track_obj){
            self.debug("previous_track_jump: no previous track found, jumping to previous tracklist");
            wpsstm.previous_tracklist_jump();
            return;
        }
       
        if ( track_obj.index === track_idx ){ //is loop
            self.debug("next_track_jump: is looping");
            if ( !wpsstm.can_repeat ){
                self.debug("next_track_jump: can_repeat is disabled.");
                return;
            }
        }
        
        track_obj.play_track();
        
    }
    
    next_track_jump(){

        var self = this;

        var track_idx = ( wpsstm.current_track ) ? wpsstm.current_track.index : 0;
        track_idx = self.get_maybe_unshuffle_track_idx(track_idx);
        var last_track = self.tracks[self.tracks.length-1];

        var tracks = $(self.tracks).get();
        var tracks_after = tracks.slice(track_idx+1); 
        var tracks_before = [];

        //which one should we play?
        var tracks_playable = tracks_after.filter(function (track_obj) {
            return (track_obj.can_play !== false);
        });
        var track_obj = tracks_playable[0];

        if (!track_obj){
            self.debug("next_track_jump: is last track");
            
            if (self.is_expired){
                self.debug("(tracklist will refresh if it is started again)");
                self.can_play = undefined; //will force to refresh tracklist
            }
            
            
            $(document).trigger("wpsstmStopTracklist",[self]); //custom event
            wpsstm.next_tracklist_jump();
        }else{
            track_obj.play_track();
        }


    }

    
    /*
    timer notice
    */
    
    init_refresh_timer(){
        
        var self = this;
        var now = Math.round( $.now() /1000);
        var remaining_sec = self.expire_time - now;
        var remaining_ms = remaining_sec * 1000;
        
        if (remaining_sec <= 0) return;

        self.debug("init_refresh_timer() - could refresh in "+ remaining_sec +" seconds");

        setTimeout(function(){
            self.is_expired = true;
            self.tracklist_el.addClass('tracklist-expired');
            self.debug("tracklist has expired");
            
        }, remaining_ms );
        
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
        
        self.debug("start tracklist");
        $(document).trigger("wpsstmStartTracklist",[self]); //custom event
        
        self.maybe_refresh().then(
                function(success_msg){
 
                    var track_obj = self.tracks[0];
                    track_obj.play_track();

                },
                function(error_msg){
                    success.reject(error_msg);
                }
            );

    }
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    get_next_tracks_sources_auto() {

        var self = this;

        var max_items = 4; //number of following tracks to preload
        var rtrack_in = wpsstm.current_track.index + 1;
        var rtrack_out = wpsstm.current_track.index + max_items + 1;

        var tracks_slice = $(self.tracks).slice( rtrack_in, rtrack_out );

        $(tracks_slice).each(function(index, track_to_preload) {
            if ( track_to_preload.sources.length > 0 ) return true; //continue;
            track_to_preload.get_sources_auto();
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
        var handle_el = track_obj.track_el.find('#wpsstm-track-action-move');

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
                handle_el.addClass('wpsstm-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    self.update_tracks_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                handle_el.removeClass('wpsstm-loading');
            }
        })

    }
    
    remove_tracklist_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;

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
                    console.log(data);
                }else{
                    $(track_el).remove();
                    self.update_tracks_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_el.removeClass('track-loading');
            }
        })

    }
    
    delete_playlist_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;

        var ajax_data = {
            action:     'wpsstm_trash_track',
            track:      track_obj.to_ajax(),
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
                    console.log(data);
                }else{
                    $(track_el).remove();
                    self.update_tracks_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_el.removeClass('track-loading');
            }
        })

    }
    
    toggleTracklist(options){

        var self = this;

        // OPTIONS
        var defaults = {
            childrenShowCount:  true,
            childrenMax:        3,
            childrenSelector:   '[itemprop="track"]',
            moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
            lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
        };

        var options =  $.extend(defaults, options);

        if ( Number($(this.tracklist_el).attr("data-tracks-count")) > 0 ) {
            return $(this.tracklist_el).find('.wpsstm-tracks-list').toggleChildren(options);
        }


    }
    
    hideEmptyColumns() {
        
        var self = this;

        /*
        per column, make an array of every cell value
        */
        var all_rows = $(self.tracklist_el).find('tr');
        var header_row = all_rows.filter('.wpsstm-tracks-list-header');
        
        //get the IDX of the columns we should check
        var toggable_header_cells = $(header_row).find('th.wpsstm-toggle-same-value');
        
        $.each( toggable_header_cells, function( i,e ) {
            
            var column_idx = $( this ).index() + 1;
            var column = all_rows.find('>*:nth-child('+column_idx+')');
            var column_head = column.filter('th');
            var column_body = column.filter('td');
            var hide_column = false;
            
            //collect tracks data for this column
            var column_datas = [];
            
            $.each( column_body, function() {
                var value = $(this).html();
                value = $.trim(value);
                column_datas.push(value);
            });

            //check status
            function onlyUnique(value, index, self) { 
                return self.indexOf(value) === index;
            }
            
            //exceptions
            if (column_datas.length == 1){ //there is only a single row displayed
                var value = $(column_datas).get(0);
                if (!value) hide_column = true; //the is not empty; hide this column
            }else{
                var unique_values = column_datas.filter( onlyUnique );
                if (unique_values.length <= 1){
                    hide_column = true; //column has a single values; hide this column
                }
            }
            
            if (hide_column){
                column.addClass('wpsstm-column-identical-value');
            }else{
                column.removeClass('wpsstm-column-identical-value');
            }

        });
        
    }
    

    
}
