(function($){

    $(document).on( "wpsstmTracklistDomReady", function( event, tracklist_obj ) {

        // sort rows
        tracklist_obj.tracklist_el.find( '.wpsstm-tracklist-entries' ).sortable({
            handle: '.wpsstm-reposition-track',

            update: function(event, ui) {
                tracklist_obj.update_playlist_track_position(ui);
            }
        });
        
        // hide column when data is empty or identical
        if ( tracklist_obj.options.hide_empty_columns ){
            tracklist_obj.hideEmptyColumns();
        }
        
        
        /*
        Tracklist actions
        */
        
        //refresh
        tracklist_obj.tracklist_el.find("#wpsstm-tracklist-action-refresh a").click(function(e) {
            e.preventDefault();
            //unset request status
            tracklist_obj.debug("clicked 'refresh' link");
            tracklist_obj.get_tracklist_request();

        });


        //favorite
        tracklist_obj.tracklist_el.find('#wpsstm-tracklist-action-favorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = Number(tracklist_wrapper.attr('data-wpsstm-tracklist-id'));

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_love_unlove_tracklist',
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
                    link.addClass('loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var tracklist_instances = tracklist_obj.get_tracklist_instances()
                        tracklist_instances.find('#wpsstm-tracklist-action-favorite').removeClass('wpsstm-toggle-favorite-active');
                        tracklist_instances.find('#wpsstm-tracklist-action-unfavorite').addClass('wpsstm-toggle-favorite-active');
                    }
                },
                complete: function() {
                    link.removeClass('loading');
                }
            })
        });
        
        //unfavorite
        tracklist_obj.tracklist_el.find('#wpsstm-tracklist-action-unfavorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = Number(tracklist_wrapper.attr('data-wpsstm-tracklist-id'));

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_love_unlove_tracklist',
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
                    link.addClass('loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var tracklist_instances = tracklist_obj.get_tracklist_instances()
                        tracklist_instances.find('#wpsstm-tracklist-action-unfavorite').removeClass('wpsstm-toggle-favorite-active');
                        tracklist_instances.find('#wpsstm-tracklist-action-favorite').addClass('wpsstm-toggle-favorite-active');
                    }
                },
                complete: function() {
                    link.removeClass('loading');
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

        self =                          this;
        self.tracklist_el =             undefined;
        self.tracklist_id =             undefined;
        self.current_track_idx =        undefined;
        self.tracklist_request =        undefined;
        self.is_expired =               undefined;
        self.expire_time =              undefined;
        self.options =                  {};
        self.tracklist_idx =            tracklist_index;
        self.tracks =                   [];
        self.tracks_shuffle_order =     [];
        self.populate_tracklist(tracklist_el);

    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #" + this.tracklist_idx + ": ";
        wpsstm_debug(msg,prefix);
    }
    
    populate_tracklist(tracklist_el){
        
        self.tracklist_el = $(tracklist_el);

        self.tracklist_el.attr('data-wpsstm-tracklist-idx',self.tracklist_idx);

        self.tracklist_id = Number( self.tracklist_el.attr('data-wpsstm-tracklist-id') );
        self.expire_time = Number( self.tracklist_el.attr('data-wpsstm-expire-time') );
        
        self.options = $.parseJSON( self.tracklist_el.attr('data-wpsstm-tracklist-options') );

        var now = Math.round( $.now() /1000);
        self.is_expired = (self.expire_time && (now > self.expire_time) );
        
        /*
        if ( self.expire_time ){
            self.debug("populate_tracklist() - is_expired: " + self.is_expired + ", now: " + now + " VS expire: " + self.expire_time);
        }else{
            self.debug("populate_tracklist()");
        }
        */

        var tracks_html = self.tracklist_el.find('[itemprop="track"]');
        
        self.tracks = [];
        self.tracks_shuffle_order = [];
        
        if ( tracks_html.length > 0 ){
            $.each(tracks_html, function( index, track_html ) {
                var new_track = new WpsstmTrack(track_html,self.tracklist_idx,index);
                self.tracks.push(new_track);
                self.tracks_shuffle_order.push(index);
            });

            self.tracks_shuffle_order = wpsstm_shuffle(self.tracks_shuffle_order);

        }

        $(document).trigger("wpsstmTracklistDomReady",[self]); //custom event
        
        if (self.expire_time){
            self.init_refresh_timer();
        }

    }

    get_tracklist_request(){
        
        var self = this;
        var deferredTracklist = $.Deferred();
 
        if (!self.tracklist_request){

            self.debug("get_tracklist_request");

            var ajax_data = {
                'action':           'wpsstm_refresh_tracklist',
                'post_id':          self.tracklist_id,
                'options':          self.options,
            };
            
            self.tracklist_request = $.ajax({

                type: "post",
                url: wpsstmL10n.ajaxurl,
                data:ajax_data,
                dataType: 'json'
            });

            self.tracklist_el.addClass('loading');

            //bottom notice
            var notice_slug = 'refresh-' + self.tracklist_idx;
            var tracklist_title = self.tracklist_el.find('[itemprop="name"]').first().text();
            var refresh_notice_bottom = $('<i class="fa fa-circle-o-notch fa-fw fa-spin"></i> '+wpsstmPlayer.refreshing_text+' <em>'+tracklist_title+'</em>');
            wpsstm_bottom_notice(notice_slug,refresh_notice_bottom,false);

        }else{ 
            //already requesting
        }

        self.tracklist_request.done(function(data) {
            if (data.success === false) {
                deferredTracklist.reject("get_tracklist_request() did NOT succeed for tracklist#" + self.tracklist_idx);
            }else{
                var new_tracklist_el = $(data.new_html);
                self.tracklist_el.replaceWith(new_tracklist_el);
                self.populate_tracklist( new_tracklist_el );
                deferredTracklist.resolve("get_tracklist_request() did succeed for tracklist#" + self.tracklist_idx);
            }


        });

        self.tracklist_request.fail(function(jqXHR, textStatus, errorThrown) {
            deferredTracklist.reject("get_tracklist_request() FAILED for tracklist#" + self.tracklist_idx);
        });  

        self.tracklist_request.always(function() {

            //remove notice
            var notice_slug = 'refresh-' + self.tracklist_idx;
            $('#wpsstm-bottom-notice-' + notice_slug).remove();

            self.tracklist_el.removeClass('loading');
            self.tracklist_request = undefined;
        });

        ////

        deferredTracklist.fail(function(jqXHR, textStatus, errorThrown) {
            self.tracklist_el.addClass('refresh-error');
            self.tracklist_el.find('#wpsstm-tracklist-action-refresh').addClass('error');
            console.log("get_tracklist_request failed for tracklist #" + self.tracklist_idx);
        });

        return deferredTracklist.promise();

    }
    
    get_first_playable_track(tracks){
        
        var self = this;
        var hasPlayable = $.Deferred();

        if (typeof tracks === 'undefined'){
            tracks = self.tracks;
        }
        
        if (tracks.length === 0) hasPlayable.reject('empty tracks input');
        
        /*
        self.debug("get_first_playable_track() in tracklist #" + self.tracklist_idx + " for tracks :");
        var idx_list = tracks.map(function(a) {return a.track_idx;});
        self.debug(idx_list);
        */
        
        /*
        This function will loop until a promise is resolved
        */

        (function iterateTrack(index) {

            if (index >= tracks.length) {
                hasPlayable.reject("unable to find a playable track");
                return;
            }
            
            var track_obj = tracks[index];
            
            track_obj.can_play_track().then(
                function(success_msg) {
                    hasPlayable.resolve(track_obj);
                },
                function(error_msg){
                    iterateTrack(index + 1);
                }
            );
            
        })(0);

        return hasPlayable.promise();
        
    }

    get_track_obj(track_idx){
        var self = this;
        
        if(typeof track_idx === 'undefined'){
            track_idx = self.current_track_idx;
        }

        track_idx = Number(track_idx);
        var track_obj = self.tracks[track_idx];
        if(typeof track_obj === 'undefined') return;
        return track_obj;
    }
    
    get_tracklist_instances(){
        var tracklist_el = $('.wpsstm-tracklist[data-wpsstm-tracklist-idx="'+this.tracklist_idx+'"]');
        return tracklist_el;
    }

    get_maybe_shuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm_page_player.is_shuffle ) return idx;
        var new_idx = self.tracks_shuffle_order[idx];
        
        self.debug("get_maybe_shuffle_track_idx() : " + idx + "-->" + new_idx);
        return new_idx;
    }
    
    get_maybe_unshuffle_track_idx(idx){
        var self = this;
        if ( !wpsstm_page_player.is_shuffle ) return idx;
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
        
        var current_track_idx = ( self.current_track_idx === 'undefined') ? 0 : self.current_track_idx;
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx);
        
        
        //which is the first track of tracklist ?
        self.get_first_playable_track().then(
            function(track_obj) {
                
                var first_track_idx = track_obj.track_idx;
                
                //current track is first track
                if ( current_track_idx === first_track_idx ){
                    if ( wpsstm_page_player.can_repeat ){
                        wpsstm_page_player.previous_tracklist_jump();
                        return;
                    }else{
                        self.debug("previous_track_jump() for tracklist #"+self.tracklist_idx+" is the first track, and can_repeat is disabled.");
                        return;
                    }
                }

                var tracks = $(self.tracks).get();
                var tracks_before = tracks.slice(0,current_track_idx).reverse();
                var tracks_after = [];

                if ( wpsstm_page_player.can_repeat ){
                    tracks_after = tracks.slice(current_track_idx+1).reverse(); 
                }

                var tracks_reordered = tracks_before.concat(tracks_after);

                //find first playable
                self.get_first_playable_track(tracks_reordered).then(
                    function(track_obj) {
                        self.play_subtrack(track_obj.track_idx);
                    }, function(error) {
                        console.log("previous_track_jump() : unable to find any playable track for tracklist #" + self.tracklist_idx);
                        wpsstm_page_player.previous_tracklist_jump();
                    }
                );
                
                
                
            }, function(error) {
                console.log("previous_track_jump() : unable to identify first track for tracklist #" + self.tracklist_idx);
                return;
            }
        );
        

        
    }
    
    next_track_jump(){

        var self = this;
        
        var current_track_idx = ( self.current_track_idx === 'undefined') ? 0 : self.current_track_idx;
        current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx);
        
        //which is the last track of tracklist ?
        var tracks_reverted = $(self.tracks).get().reverse();
        
        self.get_first_playable_track(tracks_reverted).then(
            function(track_obj) {
                var last_track_idx = track_obj.track_idx;
                
                //current track is last track
                if ( current_track_idx === last_track_idx ){
                    if ( wpsstm_page_player.can_repeat ){
                        wpsstm_page_player.next_tracklist_jump();
                        return;
                    }else{
                        self.debug("next_track_jump() for tracklist #"+self.tracklist_idx+" is the last track, and can_repeat is disabled.");
                        return;
                    }
                }

                var tracks = $(self.tracks).get();
                var tracks_after = tracks.slice(current_track_idx+1); 
                var tracks_before = [];

                if ( wpsstm_page_player.can_repeat ){
                    tracks_before = tracks.slice(0,current_track_idx);
                }

                var tracks_reordered = tracks_after.concat(tracks_before);

                //find first playable
                self.get_first_playable_track(tracks_reordered).then(
                    function(track_obj) {
                        self.play_subtrack(track_obj.track_idx);
                    }, function(error) {
                        console.log("next_track_jump() : unable to find any playable track for tracklist #" + self.tracklist_idx);
                        wpsstm_page_player.next_tracklist_jump();
                    }
                );
                
                
            }, function(error) {
                console.log("next_track_jump() : unable to identify last track for tracklist #" + self.tracklist_idx);
                return;
            }
        );

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
            self.tracklist_el.addClass('wpsstm-expired-tracklist'); //for CSS
            self.debug("tracklist has expired");
            
        }, remaining_ms );
        
    }

    play_subtrack(track_idx,source_idx){

        var self = this;
        var upToDateTracklist = $.Deferred();
        
        track_idx = ( track_idx !== undefined ) ? track_idx : 0;

        //maybe refresh tracklist if this is the first track
        if ( (track_idx === 0) && self.is_expired ){
            upToDateTracklist = self.get_tracklist_request();
        }else{
            upToDateTracklist.resolve();
        }

        //tracklist ready
        upToDateTracklist.done(function() {
            
            //maybe stop current tracklist
            if ( wpsstm_page_player.current_tracklist_idx !== undefined ){
                if ( wpsstm_page_player.current_tracklist_idx !== self.tracklist_idx ){
                    wpsstm_page_player.end_current_tracklist();
                }
            }
            //set this tracklist as the active one
            wpsstm_page_player.current_tracklist_idx = self.tracklist_idx;
            
            //populate request track
            var subtrack = self.get_track_obj(track_idx);

            if (!subtrack){
                self.debug("Track #"+self.current_track_idx+" does not exists");
                return false;
            }
            
            //set this track as the active one
            self.current_track_idx = track_idx;
            
            var debug_msg = "play_subtrack() #" + self.current_track_idx;
            if(typeof source_idx !== 'undefined') debug_msg += " source #" + source_idx;
            self.debug(debug_msg);
            
            subtrack.can_play_track().then(
                function(msg) {
                    return subtrack.play_source(source_idx);
                },
                function(error) {
                    self.debug(error);
                    self.next_track_jump();
                }
            );

        });

    }
    
    end_current_track(){
        var self = this;
        var current_track = self.get_track_obj();
        
        if (current_track){

            self.debug("end_current_track #" + current_track.track_idx);

            //mediaElement
            if (wpsstm_mediaElement){
                self.debug("there is an active media, stop it");
                wpsstm_mediaElement.pause();
                wpsstm_mediaElement.currentTime = 0;
                current_track.updateTrackClasses('ended');
            }
            
            if (current_track.sources_request){
                current_track.sources_request.abort();
            }
            
            current_track.current_track_idx = undefined;
            current_track.current_source_idx = undefined;
        }
    }
    
    update_rows_order(){
        var self = this;
        var all_rows = self.tracklist_el.find( '[itemprop="track"]' );
        jQuery.each( all_rows, function( key, value ) {
            var position = jQuery(this).find('.wpsstm-track-position [itemprop="position"]');
            position.text(key + 1);
        });
    }

    update_playlist_track_position(ui){
        var self = this;
        var rows_container = self.tracklist_el.find( '[itemprop="track"]' );
        var new_order = [];
        
        //get track
        var track_el = $(ui.item);
        var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(self.tracklist_idx,track_idx);

        var new_position = $( rows_container ).index( track_el );

        //ajax update order

        var ajax_data = {
            action            : 'wpsstm_playlist_update_track_position',
            tracklist_id      : self.tracklist_id,
            track_id          : track_obj.post_id,
            position          : new_position
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                self.tracklist_el.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    self.update_rows_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                self.tracklist_el.removeClass('loading');
            }
        })

    }
    
    remove_tracklist_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;

        var ajax_data = {
            action            : 'wpsstm_remove_tracklist_track',
            tracklist_id      : self.tracklist_id,
            track_id          : track_obj.post_id
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_el.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    $(track_el).remove();
                    self.update_rows_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_el.removeClass('loading');
            }
        })

    }
    
    delete_playlist_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;

        var ajax_data = {
            action            : 'wpsstm_playlist_trash_track',
            track_id          : track_obj.post_id
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_el.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    $(track_el).remove();
                    self.update_rows_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_el.removeClass('loading');
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
            return $(this.tracklist_el).toggleChildren(options);
        }


    }
    
    hideEmptyColumns() {
        
        var self = this;

        /*
        per column, make an array of every cell value
        */
        var all_rows = $(self.tracklist_el).find('tr');
        var header_row = all_rows.filter('.wpsstm-tracklist-entries-header');
        
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
