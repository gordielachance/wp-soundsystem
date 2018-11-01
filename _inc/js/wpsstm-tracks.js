(function($){
 
    /*
    Tracklist manager
    */

    //filter playlists input
    $(document).on("keyup", '#wpsstm-playlists-filter input[type="text"]', function(e){
        e.preventDefault();
        var container = $(this).parents('#wpsstm-track-tracklists');
        var newPlaylistEl = $(container).find('#wpsstm-new-playlist-add');
        var value = $(this).val().toLowerCase();
        var tracklist_items = $(container).find('#tracklists-manager .tracklist-row');

        var has_results = false;
        $(tracklist_items).each(function() {
            if ($(this).text().toLowerCase().search(value) > -1) {
                $(this).show();
                has_results = true;
            }
            else {
                $(this).hide();
            }
        });

        if (has_results){
            newPlaylistEl.hide();
        }else{
            newPlaylistEl.show();
        }

    });

    //create new playlist from input
    $(document).on( "click",'#wpsstm-new-playlist-add', function(e){

        e.preventDefault();
        var bt =                        $(this);
        var tracklistSelector =         bt.closest('#wpsstm-track-tracklists');
        var track_id =                  Number($(tracklistSelector).attr('data-wpsstm-track-id'));

        var existingPlaylists_el =      $(tracklistSelector).find('ul');
        var newPlaylistTitle_el =       $(tracklistSelector).find('#wpsstm-playlists-filter input[type="text"]');

        if (!newPlaylistTitle_el.val()){
            $(newPlaylistTitle_el).focus();
        }

        var ajax_data = {
            action:         'wpsstm_subtrack_tracklist_manager_new_playlist',
            playlist_title: newPlaylistTitle_el.val(),
            track_id:       track_id,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(tracklistSelector).addClass('wpsstm-freeze');
                newPlaylistTitle_el.addClass('input-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else if(data.new_html) {

                    //refresh tracklists list
                    existingPlaylists_el.replaceWith( data.new_html );

                    //simulate keyup to keep the original filtering
                    $( '#wpsstm-playlists-filter input[type="text"]' ).trigger("keyup"); 

                    //simulate new playlist checkbox click
                    var playlist_id = data.playlist_id;
                    var checkbox_el = $('input[name="wpsstm_playlist_id"][value="'+playlist_id+'"]');
                    checkbox_el.trigger("click");
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                bt.addClass('action-error');
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                $(tracklistSelector).removeClass('wpsstm-freeze');
                newPlaylistTitle_el.removeClass('input-loading');
            }
        })

    });

    //toggle track in playlist
    $(document).on( "click",'#wpsstm-track-tracklists li input[type="checkbox"]', function(e){

        var checkbox =              $(this);
        var tracklistSelector =     $(this).closest('#wpsstm-track-tracklists');
        var track_id =              Number($(tracklistSelector).attr('data-wpsstm-track-id'));

        var is_checked =            $(checkbox).is(':checked');
        var tracklist_id =          $(this).val();
        var li_el =                 $(checkbox).closest('li');

        var ajax_data = {
            action:         'wpsstm_toggle_playlist_subtrack',
            track_id:       track_id,
            tracklist_id:   tracklist_id,
            track_action:   (is_checked ? 'append' : 'remove'),
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(li_el).addClass('wpsstm-freeze');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    checkbox.prop("checked", !checkbox.prop("checked")); //restore previous state
                }else if(data.success) {
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                checkbox.prop("checked", !checkbox.prop("checked")); //restore previous state
            },
            complete: function() {
                $(li_el).removeClass('wpsstm-freeze');
            }
        })

    });

    $(document).on( "wpsstmTrackDomReady", function( event, track_obj ) {

        var track_instances = track_obj.get_track_instances();

        //play button
        track_instances.find('.wpsstm-track-icon').click(function(e) {
            e.preventDefault();

            if ( wpsstm.current_media && (wpsstm.current_track == track_obj) ){
                if ( track_instances.hasClass('track-playing') ){
                    wpsstm.current_media.pause();
                }else{
                    wpsstm.current_media.play();
                }
                track_instances.toggleClass('track-playing');
            }else{
                track_obj.play_track();
            }

        });
        
        //favorite track
        track_instances.find('#wpsstm-track-action-favorite a').click(function(e) {

            e.preventDefault();

            var link = $(this);
            var el = $(this).parents('.wpsstm-track-action');
            

            var track_ajax = track_obj.to_ajax();

            var ajax_data = {
                action:     'wpsstm_toggle_favorite_track',
                do_love:    true,
                track:      track_ajax
            };

            self.debug(ajax_data);

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
                        var track_instances = track_obj.get_track_instances();
                        
                        //set track ID if track has been created
                        if (!track_ajax.post_id){
                            track_instances.attr("data-wpsstm-track-id", data.track.post_id);
                        }
                        
                        track_instances.addClass('wpsstm-loved-track');
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(thrownError);
                    link.addClass('action-error');
                },
                complete: function() {
                    link.removeClass('action-loading');
                    $(document).trigger("wpsstmTrackLove", [track_obj,true] ); //register custom event - used by lastFM for the track.updateNowPlaying call
                }
            })

        });

        //unfavorite track
        track_instances.find('#wpsstm-track-action-unfavorite a').click(function(e) {

            e.preventDefault();

            var link = $(this);
            
            var track_ajax = track_obj.to_ajax();

            var ajax_data = {
                action:     'wpsstm_toggle_favorite_track',
                do_love:    false,
                track:      track_ajax
            };
            
            self.debug(ajax_data);

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
                    }else{
                        var track_instances = track_obj.get_track_instances();
                        track_instances.removeClass('wpsstm-loved-track');
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(thrownError);
                    link.addClass('action-error');
                },
                complete: function() {
                    link.removeClass('action-loading');
                    $(document).trigger( "wpsstmTrackLove", [track_obj,false] ); //register custom event - used by lastFM for the track.updateNowPlaying call
                }
            })

        });
        
        //remove
        track_instances.find('#wpsstm-track-action-remove a').click(function(e) {
            e.preventDefault();
            track_obj.tracklist.remove_tracklist_track(track_obj);
        });
        
        //delete
        track_instances.find('#wpsstm-track-action-trash a').click(function(e) {
            e.preventDefault();
            track_obj.delete_track();
        });

    });
    
    $(document).on( "wpsstmQueueTrack", function( event, track_obj ) {
        
        //expand tracklist
        var track_el = track_obj.track_el;
        if ( track_el.is(":visible") ) return;

        var tracklist_obj = track_obj.tracklist;
        var visibleTracksCount = tracklist_obj.tracklist_el.find('[itemprop="track"]:visible').length;
        var newTracksCount = track_obj.index + 1;
        
        if ( newTracksCount <= visibleTracksCount ) return;

        if ( tracklist_obj.options.toggle_tracklist ){
            track_obj.tracklist.showMoreLessTracks({
                childrenToShow:newTracksCount
            });
        }
        
    });

})(jQuery);

class WpsstmTrack {
    constructor(track_html,tracklist) {
        
        this.track_el =             $([]);
        this.tracklist =            new WpsstmTracklist();
        
        this.index =                null;
        this.artist =               null;
        this.title =                null;
        this.album =                null;
        this.post_id =              null;
        //this.autosource_time =    null;
        
        this.sources =              [];
        this.current_source_idx =   undefined;
        this.sources_request =      null;
        this.did_sources_request =  false;
        
        //tracklist
        if ( tracklist !== undefined ){
            this.tracklist =            tracklist;
        }
        
        //track
        if ( track_html !== undefined ){
            this.init_html(track_html);
        }
    }

    debug(msg){
        var prefix = " WpsstmTracklist #"+ this.tracklist.index +" - WpsstmTrack #" + this.index;
        wpsstm_debug(msg,prefix);
    }
    
    init_html(track_html){
        
        var self = this;

        if ( track_html === undefined ) return;
        
        self.track_el =             $(track_html);
        self.index =                Number(self.track_el.attr('data-wpsstm-track-idx')); //index in tracklist
        self.artist =               self.track_el.find('[itemprop="byArtist"]').text();
        self.title =                self.track_el.find('[itemprop="name"]').text();
        self.album =                self.track_el.find('[itemprop="inAlbum"]').text();
        self.post_id =              Number(self.track_el.attr('data-wpsstm-track-id'));
        //self.autosource_time =      Number(self.track_el.attr('data-wpsstm-autosource-time'));

        //populate existing sources
        self.populate_html_sources();
        
        $(document).trigger("wpsstmTrackDomReady",[self]); //custom event
        
    }

    get_track_instances(ancestor){
        //TOUFIX var selector = '[data-wpsstm-tracklist-idx="'+this.tracklist.index+'"] [itemprop="track"][data-wpsstm-track-idx="'+this.index+'"]';
        var selector = '[itemprop="track"][data-wpsstm-track-idx="'+this.index+'"]';
        if (ancestor !== undefined){
            return $(ancestor).find(selector);
        }else{
            return $(selector);
        }
    }

    set_bottom_trackinfo(){ //TO FIX SHOULD BE IN PLAYER ?
        var self = this;
        //track infos
        
        var tracklist_el = self.tracklist.tracklist_el;

        //copy attributes from the original playlist 
        var attributes = $(tracklist_el).prop("attributes");
        $.each(attributes, function() {
            wpsstm.bottom_trackinfo_el.attr(this.name, this.value);
        });
        
        //switch type
        wpsstm.bottom_trackinfo_el.removeClass('wpsstm-post-tracklist');
        wpsstm.bottom_trackinfo_el.addClass('wpsstm-player-tracklist');

        var list = $('<ul class="wpsstm-tracks-list" />'); 

        var row = self.track_el.clone(true,true);
        row.removeClass('wpsstm-toggle-sources');

        $(list).append(row);

        wpsstm.bottom_trackinfo_el.html(list);
        wpsstm.bottom_el.show();//show in not done yet
    }
    
    play_first_available_source(source_idx){
        
        var self = this;
        
        //is a track init!
        if ( source_idx === undefined ){
            source_idx = 0;
        }

        var success = $.Deferred();
        
        
        /*
        This function will loop until a promise is resolved
        */
        
        var sources_after = self.sources.slice(source_idx); //including this one
        var sources_before = self.sources.slice(0,source_idx - 1);

        //which one should we play?
        var sources_reordered = sources_after.concat(sources_before);
        var sources_playable = sources_reordered.filter(function (source_obj) {
            return (source_obj.can_play !== false);
        });

        if (!sources_playable.length){
            success.reject("no playable sources to iterate");
        }

        (function iterateSources(index) {

            if (index >= sources_playable.length) {
                
                success.reject();
                return;
            }
            
            var source_obj = sources_playable[index];
            var source_success = source_obj.play_source();
            
            source_success.done(function(v) {
                success.resolve();
            })
            source_success.fail(function() {
                iterateSources(index + 1);
            })


        })(0);
        
        success.fail(function() {
            self.can_play = false;
            var track_instances = self.get_track_instances();
            track_instances.addClass('track-error');
        })
        
        return success.promise();
        
    }

    set_bottom_audio_el(){
        
        var self = this;
        
        var media_wrapper = $('<audio />');
        media_wrapper.attr({
            id:     'wpsstm-player-audio'
        });

        media_wrapper.prop({
            //autoplay:     true,
            //muted:        true
        });

        $( self.sources ).each(function(i, source_attr) {
            //media
            var source_el = $('<source />');
            source_el.attr({
                src:    source_attr.src,
                type:   source_attr.type
            });

            media_wrapper.append(source_el);

        });
        
        $('#wpsstm-player').html(media_wrapper);
    }
    
    maybe_load_sources(){

        var self = this;
        var success = $.Deferred();
        var can_tracklist_autosource = this.tracklist.options.autosource;
        
        if (self.sources.length > 0){
            
            success.resolve();
            
        }else if ( !can_tracklist_autosource ){
            
            success.resolve("Autosource is disabled for this tracklist");
            
        } else if ( self.did_sources_request ) {
            
            success.resolve("already did sources auto request for track #" + self.index);
            
        } else{
            
            success = self.get_track_sources_request();
            
        }
        return success.promise();
    }

    get_track_sources_request() {

        var self = this;
        var deferredObject = $.Deferred();
        
        var track_el    = self.track_el;
        var track_instances = self.get_track_instances();

        self.debug("track sources request");

        $(track_el).find('.wpsstm-track-sources').html('');
        track_instances.addClass('track-loading');

        var ajax_data = {
            action:     'wpsstm_autosources_list',
            track:      self.to_ajax(),   
        };

        self.sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        self.sources_request.done(function(data) {

            //update autosource time
            //self.autosource_time = data.timestamp;
            //track_instances.attr("data-wpsstm-autosource-time", self.autosource_time);
            self.did_sources_request = true;
            
            if ( data.new_html ){
                
                if(data.new_ids){
                    //console.log("new source IDs:");
                    //console.log(data.new_ids);
                }

                //update HTML & repopulate track
                self.debug("repopulate HTML");
                track_instances.replaceWith( data.new_html );
                self.init_html(data.new_html);
                
            }

            if (data.success === true){

                deferredObject.resolve();

            }else{
                self.debug("track sources request failed: " + data.message);
                self.can_play = false;
                track_instances.addClass('track-error');
                deferredObject.reject(data.message);
            }

        });

        self.sources_request.fail(function() {
            track_instances.addClass('track-error');
        })

        self.sources_request.always(function() {
            track_instances.removeClass('track-loading');
        })
        
        return deferredObject.promise();

    }
    
    populate_html_sources(){
        var self =      this;
        
        self.sources =              [];
        self.current_source_idx =   undefined;
        var track_el =  self.track_el; //page track

        var source_els = $(track_el).find('[data-wpsstm-source-idx]');

        //self.debug("found "+source_els.length +" sources");
        
        self.sources = []; //reset array
        $.each(source_els, function( index, source_el ) {
            var source_obj = new WpsstmTrackSource(source_el,self);
            self.sources.push(source_obj);
            $(document).trigger("wpsstmTrackSingleSourceDomReady",[source_obj]); //custom event for single source
        });
                
        var track_instances = self.get_track_instances();
        track_instances.attr('data-wpsstm-sources-count',self.sources.length);
        
        $(document).trigger("wpsstmTrackSourcesDomReady",[self]); //custom event for all sources

    }
    
    get_source_obj(source_idx){
        var self = this;
        
        if(source_idx === undefined){
            source_idx = self.current_source_idx;
        }

        source_idx = Number(source_idx);
        var source_obj = self.sources[source_idx];
        if(typeof source_obj === undefined) return;
        return source_obj;
    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var self = this;
        var allowed = ['index','artist', 'title','album','post_id','duration'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
        obj[key] = self[key];
        return obj;
        }, {});
        return filtered;
    }
    
    delete_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;
        var link = $(track_el).find('#wpsstm-track-action-trash a');

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

    end_track(){
        var self = this;

        var source_obj = self.get_source_obj();
        self.end_current_source();

        var track_instances = self.get_track_instances();
        track_instances.removeClass('track-loading track-active track-playing');
        

    }
    
    end_current_source(){
        var self = this;
        
        var source_obj = self.get_source_obj();
        if (source_obj === undefined) return;
        
        source_obj.end_source();
        self.current_source_idx = undefined;

    }
    
    play_track(source_idx){

        var self = this;
        var success = $.Deferred();
        
        var track_previous = wpsstm.current_track;
        var isDuplicatePlay = ( ( $(self).is($(track_previous))) && (track_previous.current_source_idx == source_idx) ); //if we're trying to play the same track again

        var isTracklistSwitch = true;
        
        if (track_previous){
            track_previous.end_track(); //stop current track
            if (self.tracklist.index === track_previous.tracklist.index){
                isTracklistSwitch = false;
            }
        }

        //fire an event when a playlist starts to play
        if (isTracklistSwitch){
            if (track_previous){
                track_previous.tracklist.debug("close tracklist");
                $(document).trigger("wpsstmCloseTracklist",[track_previous.tracklist]); //custom event
            }
        }

        if (isDuplicatePlay){
            success.resolve("we've already queued this track"); 
        }else{
            
            if (wpsstm.current_media) wpsstm.current_media.pause(); //pause current media

            wpsstm.current_track = self;

            $(document).trigger( "wpsstmQueueTrack",[self] ); //custom event

            var track_instances = self.get_track_instances();
            track_instances.addClass('track-loading track-active');

            self.set_bottom_trackinfo(); //bottom track info

            self.maybe_load_sources().then(
                function(success_msg){

                    var source_play = self.play_first_available_source(source_idx);

                    source_play.done(function(v) { //fetch sources for next tracks
                        self.tracklist.maybe_load_queue_sources();
                        success.resolve();
                    })
                    source_play.fail(function(reason) {
                        success.reject(reason);
                    })

                },
                function(error_msg){
                    success.reject(error_msg);
                }
            );
        }
        
        
        
        success.fail(function(reason) {
            self.debug(reason);
            self.tracklist.next_track_jump();
        });

        return success.promise();

    }
    
    static update_sources_order(track_id,source_ids){
        
        var success = $.Deferred();

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_update_track_sources_order',
            track_id:   track_id,
            source_ids: source_ids
        };
        
        //wpsstm_debug(ajax_data,"update_sources_order");

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
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
        
        return success.promise();
    }
    
}