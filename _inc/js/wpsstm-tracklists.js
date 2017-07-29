(function($){
    
    $(document).ready(function(){
        $('.wpsstm-tracklist').toggleTracklist();    
        $('#wpsstm-subtracks-list table').toggleChildren({
            childrenShowCount:  true,
            childrenMax:        3,
            childrenSelector:   'tbody > *',
            moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
            lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
        });
        
    });
    
    $(document).on( "wpsstmTracklistDomReady", function( event, tracklist_obj ) {
        
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
            tracklist_obj.get_tracklist_request(true); //initialize but do not set track to play
            
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

    //create new playlist
    $(document).on("click", '#wpsstm-new-playlist-add input[type="submit"]', function(e){
        
        e.preventDefault();
        var bt =                        $(this);
        var popupContent =              $(bt).closest('.wpsstm-popup-content');
        var playlistFilterWrapper =     $(popupContent).find('#wpsstm-filter-playlists');
        var existingPlaylists_el =      $(playlistFilterWrapper).find('ul');
        var newPlaylistTitle_el =       $(playlistFilterWrapper).find('#wpsstm-playlists-filter');
        var newPlaylistTitle =          newPlaylistTitle_el.val();
        
        var playlistAddWrapper = $(playlistFilterWrapper).find('#wpsstm-new-playlist-add');
        

        
        if (!newPlaylistTitle){
            $(newPlaylistTitle_el).focus();
        }

        var ajax_data = {
            action:         'wpsstm_create_playlist',
            playlist_title: newPlaylistTitle,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(popupContent).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else if(data.new_html) {
                    
                    $(existingPlaylists_el).remove();
                    $(data.new_html).insertBefore(playlistAddWrapper);
                    $( "#wpsstm-playlists-filter" ).trigger("keyup");
                    $(playlistAddWrapper).toggle();
                }
            },
            complete: function() {
                $(popupContent).removeClass('loading');
            }
        })

    });
    
    //append or remove track from playlist
    //TO FIX put somewhere else ?
    $(document).on("click", '#wpsstm-filter-playlists ul li input[type="checkbox"]', function(e){
        
        var checkbox =      $(this);
        var is_checked =    $(checkbox).is(':checked');
        var playlist_id =   $(this).val();
        var li_el =         $(checkbox).closest('li');
        var popupContent =  $(checkbox).closest('.wpsstm-popup-content');
        
        var popup_section = checkbox.closest('#wpsstm-track-admin-playlists');
        var popup = checkbox.closest('.hentry');

        //get track obj from HTML
        var track_el = popup.find('[itemprop="track"]').first();
        var track_obj = new WpsstmTrack(track_el);

        var ajax_data = {
            action:         (is_checked ? 'wpsstm_add_playlist_track' : 'wpsstm_remove_playlist_track'),
            post_id:        track_obj.post_id,
            playlist_id:    playlist_id,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(li_el).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    checkbox.prop("checked", !checkbox.prop("checked")); //restore previous state
                }else if(data.success) {
                    //TO FIX replace whole track el ?
                    $(track_el).attr('data-wpsstm-track-id',data.track_id); //set returned track ID (useful if track didn't exist before)
                }
            },
            complete: function() {
                $(li_el).removeClass('loading');
            }
        })

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
        self.refresh_timer =            undefined;
        self.expire_sec =               undefined;
        self.tracklist_idx =            tracklist_index;
        self.tracks =                   [];
        self.tracks_shuffle_order =     [];
        self.did_tracklist_request =    true;
        self.tracklist_can_play =       true;
        self.populate_tracklist(tracklist_el);

    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #" + this.tracklist_idx + ": ";
        wpsstm_debug(msg,prefix);
    }
    
    populate_tracklist(tracklist_el){
        
        self.tracklist_el = $(tracklist_el);

        self.debug("populate_tracklist()");

        self.tracklist_el.attr('data-wpsstm-tracklist-idx',self.tracklist_idx);

        self.tracklist_id = Number( self.tracklist_el.attr('data-wpsstm-tracklist-id') );
        var expire_sec_attr =  self.tracklist_el.attr('data-wpsstm-expire-sec');

        if (typeof expire_sec_attr !== typeof undefined && expire_sec_attr !== false) { // For some browsers, `attr` is undefined; for others, `attr` is false.  Check for both.
            
            self.expire_sec = Number(expire_sec_attr);
            
            if ( self.expire_sec <= 0 ){
                self.did_tracklist_request = false; 
            }
        }
        
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

    }


    get_tracklist_request(force = false){
        
        var self = this;
        var deferredTracklist = $.Deferred();
        
        if ( self.did_tracklist_request && !force ){
            
            deferredTracklist.resolve();
            
        }else{
            
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
                console.log(data);
                if (data.success === false) {
                    deferredTracklist.reject();
                }else{
                    var new_tracklist_el = $(data.new_html);
                    self.tracklist_el.replaceWith(new_tracklist_el);
                    self.populate_tracklist( new_tracklist_el );
                    deferredTracklist.resolve();
                }

            });

            self.tracklist_request.fail(function(jqXHR, textStatus, errorThrown) {
                deferredTracklist.reject();
            });  
            
            self.tracklist_request.always(function() {
                self.did_tracklist_request = true; //so we can avoid running this function several times
                
                //remove notice
                var notice_slug = 'refresh-' + self.tracklist_idx;
                $('#wpsstm-bottom-notice-' + notice_slug).remove();

                self.tracklist_el.removeClass('loading');

                //refresh timer
                if (self.expire_sec !== undefined){
                    self.init_refresh_timer();
                }

                self.tracklist_request = undefined;
            });
            
        }
        
        ////
        
        deferredTracklist.fail(function(jqXHR, textStatus, errorThrown) {
            self.tracklist_can_play = false;
            self.tracklist_el.addClass('refresh-error');
            self.tracklist_el.find('#wpsstm-tracklist-action-refresh').addClass('error');
            console.log("get_tracklist_request failed for tracklist #" + self.tracklist_idx);
        });

        return deferredTracklist.promise();

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

    play_previous_track(){
        var self = this;
        
        var current_track_idx = self.get_maybe_unshuffle_track_idx(self.current_track_idx);
        var queue_track_idx = current_track_idx; //get real track index
        var first_track_idx = 0;
        var new_track;

        //try to get previous track
        for (var i = 0; i < self.tracks.length; i++) {

            if (queue_track_idx == first_track_idx){
                self.debug("play_previous_track() : is first track");
                break;
            }
            
            queue_track_idx = Number(queue_track_idx) - 1;
            queue_track_idx = self.get_maybe_shuffle_track_idx(queue_track_idx);
            var check_track = self.get_track_obj(queue_track_idx);

            if (check_track.track_can_play){
                new_track = check_track;
                break;
            }
        }
        
        if (new_track){
            self.debug("play_previous_track() #" + queue_track_idx);
            self.play_tracklist_track(check_track.track_idx);
        }else {
            wpsstm_page_player.play_previous_tracklist();
        }
    }
    
    play_next_track(){
        var self = this;

        var current_track_idx = self.get_maybe_unshuffle_track_idx(self.current_track_idx);
        var queue_track_idx = current_track_idx;
        var last_track_idx = self.tracks.length -1;
        var new_track;

        //try to get next track
        for (var i = 0; i < self.tracks.length; i++) {

            if (queue_track_idx == last_track_idx){
                self.debug("play_next_track() : is tracklist last track");
                break;
            } 
            
            queue_track_idx = Number(queue_track_idx) + 1;

            queue_track_idx = self.get_maybe_shuffle_track_idx(queue_track_idx);
            var check_track = self.get_track_obj(queue_track_idx);

            if ( check_track.track_can_play){
                new_track = check_track;
                break;
            }
        }
        
        if (new_track){
            self.debug("play_next_track() #" + queue_track_idx);
            self.play_tracklist_track(check_track.track_idx);
        }else{
            wpsstm_page_player.play_next_tracklist();
        }
    }
    
    /*
    timer notice
    */
    
    init_refresh_timer(){
        var self = this;

        //expire countdown
        if (self.expire_sec === 0) return;
        if (self.expire_sec <= 0) return;
        
        var ms = self.expire_sec * 1000;

        self.debug("init_refresh_timer()");
        
        if (self.refresh_timer){ //stop current timer if any
            clearTimeout(self.refresh_timer);
            self.refresh_timer = undefined;
        }
        
        self.debug("could refresh in "+ self.expire_sec +" seconds");
        
        setTimeout(function(){
            
            self.expire_sec = 0;
            self.tracklist_el.attr('data-wpsstm-expire-sec',0); //for CSS
            self.did_tracklist_request = false;
            self.debug("refresh timer expired");
            
        }, ms );
        
    }

    play_tracklist_track(track_idx,source_idx){

        var self = this;
        var deferredTracklist = $.Deferred();
        
        //set active playlist
        if ( wpsstm_page_player.current_tracklist_idx !== undefined ){
            if ( wpsstm_page_player.current_tracklist_idx !== self.tracklist_idx ){
                wpsstm_page_player.end_current_tracklist();
            }
        }
        wpsstm_page_player.current_tracklist_idx = self.tracklist_idx;

        //set active track
        if ( self.current_track_idx !== undefined ){
            if ( self.current_track_idx !== track_idx ){
                self.end_current_track();
            }
        }
        
        //no track idx defined, set first track and maybe refresh tracklist
        if ( track_idx === undefined ){
            track_idx = 0;
            deferredTracklist = self.get_tracklist_request();
        }else{
            deferredTracklist.resolve();
        }
        self.current_track_idx = track_idx;

        //tracklist ready
        deferredTracklist.done(function() {
            self.debug("play_tracklist_track #" +  self.current_track_idx + " source #" + source_idx);
            var play_track = self.get_track_obj(self.current_track_idx);

            if (play_track){
                play_track.play_or_skip(source_idx);
            }else{
                self.debug("Track #"+self.current_track_idx+" not found");
                return;
            }
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
            var position = jQuery(this).find('.column-trackitem_position [itemprop="position"]');
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
            action            : 'wpsstm_playlist_delete_track',
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
