(function($){

    $(document).ready(function(){
        
        //filter playlists
        $(document).on("keyup", '#wpsstm-playlists-filter', function(e){
            e.preventDefault();
            var playlistFilterWrapper = $(this).closest('#wpsstm-filter-playlists');
            var playlistAddWrapper = $(playlistFilterWrapper).find('#wpsstm-new-playlist-add');
            var value = $(this).val().toLowerCase();
            var li_items = playlistFilterWrapper.find('ul li');

            var has_results = false;
            $(li_items).each(function() {
                if ($(this).text().toLowerCase().search(value) > -1) {
                    $(this).show();
                    has_results = true;
                }
                else {
                    $(this).hide();
                }
            });

            if (has_results){
                playlistAddWrapper.hide();
            }else{
                playlistAddWrapper.show();
            }

        });
        
    });
    
    $(document).on( "wpsstmTrackDomReady", function( event, track_obj ) {
        var track_el = track_obj.track_el;

        //play button
        $(track_el).find('.wpsstm-track-icon').click(function(e) {
            e.preventDefault();

            if ( wpsstm.current_media && $(track_el).hasClass('track-active') ){
                if ( $(track_el).hasClass('track-playing') ){
                    wpsstm.current_media.pause();
                }else{
                    wpsstm.current_media.play();
                }
                $(track_el).toggleClass('track-playing');
            }else{
                track_obj.play_track();
            }

        });
        
        //favorite track
        $(track_el).find('#wpsstm-track-action-favorite a').click(function(e) {

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
        $(track_el).find('#wpsstm-track-action-unfavorite a').click(function(e) {

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
        $(track_el).find('#wpsstm-track-action-remove a').click(function(e) {
            e.preventDefault();
            track_obj.tracklist.remove_tracklist_track(track_obj);
        });
        
        //delete
        $(track_el).find('#wpsstm-track-action-delete a').click(function(e) {
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
    constructor(track_html,tracklist,track_idx) {

        this.tracklist =            tracklist;
        this.track_el =             $(track_html);
        
        this.index =                Number(this.track_el.attr('data-wpsstm-track-idx')); //index in tracklist
        this.artist =               this.track_el.find('[itemprop="byArtist"]').text();
        this.title =                this.track_el.find('[itemprop="name"]').text();
        this.album =                this.track_el.find('[itemprop="inAlbum"]').text();
        this.post_id =              Number(this.track_el.attr('data-wpsstm-track-id'));
        this.sources_request =      null;
        this.did_sources_request =  false;
        this.sources =              [];
        this.current_source_idx =   undefined;

        //populate existing sources
        this.populate_html_sources();
        
        $(document).trigger("wpsstmTrackDomReady",[this]); //custom event

    }

    debug(msg){
        var prefix = " WpsstmTracklist #"+ this.tracklist.index +" - WpsstmTrack #" + this.index + ": ";
        wpsstm_debug(msg,prefix);
    }

    get_track_instances(ancestor){
        var selector = '[data-wpsstm-tracklist-idx="'+this.tracklist.index+'"] [itemprop="track"][data-wpsstm-track-idx="'+this.index+'"]';
        if (ancestor !== undefined){
            return $(ancestor).find(selector);
        }else{
            return $(selector);
        }
    }

    
    get_sources_auto(){

        var self = this;

        var deferredObject = $.Deferred();

        var promise = self.maybe_load_sources();

        promise.fail(function() {
            deferredObject.reject();
        })

        promise.done(function(v) {
            deferredObject.resolve();
        })

        return deferredObject.promise();
    }

    set_bottom_trackinfo(){ //TO FIX SHOULD BE IN PLAYER
        var self = this;
        //track infos
        
        var tracklist_el = self.tracklist.tracklist_el;

        //copy attributes from the original playlist 
        var attributes = $(tracklist_el).prop("attributes");
        $.each(attributes, function() {
            wpsstm.bottom_trackinfo_el.attr(this.name, this.value);
        });

        var list = $('<table class="wpsstm-tracks-list"></table>'); 

        var row = self.track_el.clone(true,true);
        row.show(); //be sure it will be displayed

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
        if (self.sources.length > 0){
            success.resolve();
        }else{
            success = self.get_track_sources_request();
        }
        return success.promise();
    }

    get_track_sources_request() {

        var self = this;
        var deferredObject = $.Deferred();
        
        var track_el    = self.track_el;
        var track_instances = self.get_track_instances();

        if ( self.did_sources_request ) {
            
            deferredObject.resolve("already did sources auto request for track #" + self.index);
            
        } else{
            
            self.debug("get_track_sources_request");
        

            $(track_el).find('.wpsstm-track-sources').html('');
            track_instances.addClass('track-loading');

            var ajax_data = {
                action:     'wpsstm_autosources_list',
                track:      self.to_ajax(),   
            };
            
            //self.debug(ajax_data);

            self.sources_request = $.ajax({
                type:       "post",
                url:        wpsstmL10n.ajaxurl,
                data:       ajax_data,
                dataType:   'json',
            });

            self.sources_request.done(function(data) {

                self.did_sources_request = true;

                if (data.success === true){
                    if ( data.new_html ){
                        self.can_play = true;
                        $(track_instances).find('.wpsstm-track-sources').html(data.new_html); //append new sources
                        self.populate_html_sources();
                    }

                    deferredObject.resolve();

                }else{
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
            
        }
        
        return deferredObject.promise();

    }
    
    populate_html_sources(){
        var self =      this;
        var track_el =  self.track_el; //page track

        var new_sources_items = $(track_el).find('[data-wpsstm-source-idx]');

        //self.debug("found "+new_sources_items.length +" sources");
        
        self.sources = [];
        $.each(new_sources_items, function( index, source_link ) {
            var source_obj = new WpsstmTrackSource(source_link,self);
            self.sources.push(source_obj);
            $(document).trigger("wpsstmTrackSingleSourceDomReady",[source_obj]); //custom event for single source
        });

                
        var track_instances = self.get_track_instances();
        track_instances.attr('data-wpsstm-sources-count',self.sources.length);
        
        $(document).trigger("wpsstmTrackSourcesDomReady",[self]); //custom event for all sources

    }
    
    get_source_obj(source_idx){
        var self = this;
        
        if(typeof source_idx === undefined){
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
        var link = $(track_el).find('#wpsstm-track-action-delete a');

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
    
    update_source_index(source_obj){
        var self = this;
        var link = source_obj.source_el.find('#wpsstm-source-action-move a');

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_set_source_position',
            source:     source_obj.to_ajax()
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                link.track_el.addClass('action-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                    link.addClass('action-error');
                }else{
                    //self.update_sources_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
                link.addClass('action-error');
            },
            complete: function() {
                link.removeClass('action-loading');
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
        var source = self.get_source_obj();
        
        if (source === undefined) return;

        self.debug("end_current_source");
        
        if (self.media){
            self.media.pause();
            self.media.currentTime = 0;
        }
        
        wpsstm.current_media = self.media; //TO FIX why for?
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

                    source_play.done(function(v) {
                        //fetch sources for next tracks
                        if ( self.tracklist.tracklist_el.hasClass('tracklist-autosource') ) {
                            self.tracklist.get_next_tracks_sources_auto();
                        }
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
    
}