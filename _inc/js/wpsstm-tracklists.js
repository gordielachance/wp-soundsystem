(function($){

    $(document).on( "wpsstmTracklistDomReady", function( event, tracklist_obj ) {
        
        //toggle expand tracks
        tracklist_obj.tracklist_el.toggleTracklist();
        
        // sort rows
        tracklist_obj.tracklist_el.find( '.wpsstm-tracklist-entries' ).sortable({
            handle: '.wpsstm-reposition-track',

            update: function(event, ui) {
                tracklist_obj.update_playlist_track_position(ui);
            }
        });
        
        /*
        Tracklist actions
        */
        
        //refresh
        tracklist_obj.tracklist_el.find("#wpsstm-tracklist-action-refresh a").click(function(e) {
            e.preventDefault();
            //unset request status
            tracklist_obj.debug("clicked 'refresh' link");
            tracklist_obj.get_tracklist_request(); //initialize but do not set track to play
            
        });

        //favorite
        tracklist_obj.tracklist_el.find('#wpsstm-tracklist-action-favorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = tracklist_wrapper.attr('data-wpsstm-tracklist-id');

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
            var tracklist_id = tracklist_wrapper.attr('data-wpsstm-tracklist-id');

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
            var track_idx = track_el.attr('data-wpsstm-track-idx');
            var track_obj = tracklist_obj.get_track_obj(track_idx);
            
            tracklist_obj.remove_playlist_track(track_obj);
        });
        
        //delete
        tracklist_obj.tracklist_el.find('#wpsstm-track-action-delete a').click(function(e) {
            e.preventDefault();
            
            //get track
            var track_el = $(this).closest('[itemprop="track"]');
            var track_idx = track_el.attr('data-wpsstm-track-idx');
            var track_obj = tracklist_obj.get_track_obj(track_idx);
            
            tracklist_obj.delete_playlist_track(track_obj);
        });
        
        tracklist_obj.tracklist_el.toggleTracklist();
    });

    $.fn.extend({ 
        toggleTracklist: function(options){
            // OPTIONS
            var defaults = {
                childrenShowCount:  true,
                childrenMax:        3,
                childrenSelector:   '.wpsstm-tracklist-entries > *',
                moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
            };
            var options =  $.extend(defaults, options);
            
            $(this).each(function() {
                if ( $(this).attr("data-tracks-count") > 0 ) {
                    return $(this).toggleChildren(options);
                }
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
        self.autoplay =                 undefined;
        self.autosource =               undefined;
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
        self.autoplay = ( Number( self.tracklist_el.attr('data-wpsstm-autoplay') ) === 1);
        self.autosource = ( Number( self.tracklist_el.attr('data-wpsstm-autosource') ) === 1);
        self.expire_time = Number( self.tracklist_el.attr('data-wpsstm-expire-time') );
        
        
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
                'post_id':          self.tracklist_id
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
    
    can_play_tracklist(){
        
        var hasPlayable = $.Deferred();
        
        var unplayableTracks = [];
        
        $.each(self.tracks, function( index, track_obj ) {
            
            var unplayableTrack = $.Deferred();
            
            // If a request fails, count that as a resolution so it will keep
            // waiting for other possible successes. If a request succeeds,
            // treat it as a rejection so Promise.all immediately bails out.
            
            track_obj.can_play_track().then(
                val => unplayableTrack.reject(track_obj.track_idx),
                err => unplayableTrack.resolve(track_obj.track_idx),
            );
            
            unplayableTracks.push(unplayableTrack);

        });

        Promise.all(unplayableTracks).then(
            val => {
                hasPlayable.reject(val);
            },
            err => {
                console.log("can_play_tracklist() #" + self.tracklist_idx + ": yes, at least track #"+err+" can be played");
                hasPlayable.resolve(err);
            }
        );
        
        return hasPlayable.promise();
        
    }
/*
    can_play_tracklistTEST(){
        
        var all_promises;
        
        $.each(self.tracks, function( index, track ) {
            var promise = track.can_play_track();
            all_promises.push(promise);
        });
        
        //https://stackoverflow.com/questions/37234191/resolve-es6-promise-with-first-success
        
        return Promise.all(all_promises.map(p => {
            // If a request fails, count that as a resolution so it will keep
            // waiting for other possible successes. If a request succeeds,
            // treat it as a rejection so Promise.all immediately bails out.
            return p.then(
                val => Promise.reject(val),
                err => Promise.resolve(err)
            );
            )).then(
                // If '.all' resolved, we've just got an array of errors.
                errors => Promise.reject(errors),
                // If '.all' rejected, we've got the result we wanted.
                val => Promise.resolve(val)
            );
    }
*/
    
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
        
            self.can_play_tracklist().then(function(value) {
                
                var current_track_idx = ( self.current_track_idx === 'undefined') ? 0 : self.current_track_idx;
                current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx);
                var last_track_idx = self.tracks.length -1;
                var previous_track_idx = ( current_track_idx <= 0) ? 0 : current_track_idx - 1;
                var new_track_idx;

                if (current_track_idx == 0){ //this is the first tracklist track

                    if ( !wpsstm_page_player.can_repeat ){
                        self.debug("previous_tracklist_jump() : Reached the first track and repeat is disabled; abord previous_track_jump()");
                        return false;
                    }else{
                        wpsstm_page_player.previous_tracklist_jump();
                        return;
                    }

                }

                var tracks = $(self.tracks).get();
                var tracks_before = tracks.slice(0,current_track_idx).reverse();
                var tracks_after = tracks.slice(current_track_idx+1).reverse(); 

                var tracks_reordered = tracks_before.concat(tracks_after);
                var debug = [];


                $(tracks_reordered).each(function( i, track_obj ) {
                    debug.push(track_obj.track_idx);
                });
                console.log("previous tracks:");
                console.log(debug);

                $(tracks_reordered).each(function( i, track_obj ) {
                    console.log(track_obj);
                    if ( track_obj.can_play_track() ){
                        new_track_idx = track_obj.track_idx;
                        return false; //break
                    }
                });

                if (new_track_idx !== 'undefined'){
                    return self.play_subtrack(new_track_idx);
                }else{
                    console.log('Tracklist #' + tracklist_obj.tracklist_idx + ": cannot jump previous track");
                    //wpsstm_page_player.previous_tracklist_jump();
                }
                
            }, function(reason) {
                
                self.debug("previous_tracklist_jump() : No playable tracks");
                return;
                
            });

        
    }
    
    next_track_jump(){
        
        var self = this;
        
        self.can_play_tracklist().then(function(value) {

            var current_track_idx = ( self.current_track_idx === 'undefined') ? 0 : self.current_track_idx;
            current_track_idx = self.get_maybe_unshuffle_track_idx(current_track_idx);
            var last_track_idx = self.tracks.length -1;
            var next_track_idx = ( current_track_idx >= last_track_idx) ? 0 : current_track_idx + 1;
            var new_track_idx;

            if (current_track_idx == last_track_idx){ //this is the last track

                if ( !wpsstm_page_player.can_repeat ){
                    self.debug("previous_tracklist_jump() : Reached the last track and repeat is disabled; abord next_track_jump()");
                    return false;
                }else{
                    wpsstm_page_player.next_tracklist_jump();
                    return;
                }

            }

            console.log("next_track_jump - current:" + current_track_idx + ", next:" + next_track_idx);

            var tracks = $(self.tracks).get();

            //if we loop inside the playlist rather than the page, add the previous tracks too
            var tracks_before = tracks.slice(0,current_track_idx);
            var tracks_after = tracks.slice(current_track_idx+1); //do not including this one

            var tracks_reordered = tracks_after.concat(tracks_before);
            var debug = [];

            /*
            $(tracks_reordered).each(function( i, track_obj ) {
                debug.push(track_obj.track_idx);
            });
            console.log("next tracks:");
            console.log(debug);
            */

            $(tracks_reordered).each(function( i, track_obj ) {
                if ( track_obj.can_play_track() ){
                    new_track_idx = track_obj.track_idx;
                    return false; //break
                }
            });

            if (new_track_idx !== 'undefined'){
                return self.play_subtrack(new_track_idx);
            }else{
                console.log('Tracklist #' + tracklist_obj.tracklist_idx + ": cannot jump next track");
                wpsstm_page_player.next_tracklist_jump();
            }

        }, function(reason) {

            self.debug("next_track_jump() : No playable tracks");
            return;

        });
        
        
        
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
        if ( (track_idx === 0) && self.is_expired ){ //TO FIX + ajax enabled ?
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
            
            subtrack.can_play_track().then(function(value) {
                
                return subtrack.play_source(source_idx);
                
            }, function(reason) {
                
                self.next_track_jump();
                
            });

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
        var track_idx = track_el.attr('data-wpsstm-track-idx');
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
    
    remove_playlist_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;

        var ajax_data = {
            action            : 'wpsstm_playlist_remove_track',
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
    
}
