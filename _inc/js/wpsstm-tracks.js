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
        $(track_el).find('.track-play-bt').click(function(e) {
            e.preventDefault();

            if ( wpsstm_page_player.current_media && $(track_el).hasClass('track-active') ){
                if ( $(track_el).hasClass('track-playing') ){
                    wpsstm_page_player.current_media.pause();
                }else{
                    wpsstm_page_player.current_media.play();
                }
                $(track_el).toggleClass('track-playing');
            }else{
                track_obj.tracklist.play_subtrack(track_obj.index);
            }

        });
        
        //favorite track
        $(track_el).find('#wpsstm-track-action-favorite a').click(function(e) {

            e.preventDefault();
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);

            var tracklist_el = link.closest('[data-wpsstm-tracklist-idx]');
            var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));

            var track_el = link.closest('[itemprop="track"]');
            var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
            
            var tracklist_obj =  wpsstm_page_player.get_page_tracklist(tracklist_idx);
            var track_obj = tracklist_obj.get_track_obj(track_idx);
            var track_ajax = track_obj.to_ajax();

            var ajax_data = {
                action:     'wpsstm_love_unlove_track',
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
                    link.addClass('loading');
                },
                success: function(data){
                    console.log(data);
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var track_instances = track_obj.get_track_instances();
                        
                        //set track ID if track has been created
                        if (!track_ajax.post_id){
                            track_instances.attr("data-wpsstm-track-id", data.track.post_id);
                        }
                        
                        track_instances.find('#wpsstm-track-action-favorite').removeClass('wpsstm-toggle-favorite-active');
                        track_instances.find('#wpsstm-track-action-unfavorite').addClass('wpsstm-toggle-favorite-active');
                    }
                },
                complete: function() {
                    link.removeClass('loading');
                    $(document).trigger( "wpsstmTrackLove", [track_obj,true] ); //register custom event - used by lastFM for the track.updateNowPlaying call
                }
            })

        });

        //unfavorite track
        $(track_el).find('#wpsstm-track-action-unfavorite a').click(function(e) {

            e.preventDefault();
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);

            var tracklist_el = link.closest('[data-wpsstm-tracklist-idx]');
            var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));

            var track_el = link.closest('[itemprop="track"]');
            var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
            
            var tracklist_obj =  wpsstm_page_player.get_page_tracklist(tracklist_idx);
            var track_obj = tracklist_obj.get_track_obj(track_idx);
            var track_ajax = track_obj.to_ajax();

            var ajax_data = {
                action:     'wpsstm_love_unlove_track',
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
                    link.addClass('loading');
                },
                success: function(data){
                    console.log(data);
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var track_instances = track_obj.get_track_instances();
                        track_instances.find('#wpsstm-track-action-favorite').addClass('wpsstm-toggle-favorite-active');
                        track_instances.find('#wpsstm-track-action-unfavorite').removeClass('wpsstm-toggle-favorite-active');
                    }
                },
                complete: function() {
                    link.removeClass('loading');
                    $(document).trigger( "wpsstmTrackLove", [track_obj,false] ); //register custom event - used by lastFM for the track.updateNowPlaying call
                }
            })

        });

    });
    
    $(document).on( "wpsstmRequestTrack", function( event, track_obj ) {
        
        //expand tracklist
        var track_el = track_obj.track_el;
        if ( track_el.is(":visible") ) return;

        var tracklist_el = track_obj.tracklist.tracklist_el;
        var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;
        var newTracksCount = track_obj.index + 1;
        
        if ( newTracksCount <= visibleTracksCount ) return;

        if ( tracklist_el.is('[wpsstm-toggle-tracklist]') ){
            track_obj.tracklist.toggleTracklist({
                childrenMax:newTracksCount
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
        this.playback_start =       null; //seconds - used by lastFM

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
        
        var track_instances = self.get_track_instances(); //repopulate instances - they might have changed since
        track_instances.addClass('track-loading');

        promise.fail(function() {
            deferredObject.reject();
        })

        promise.done(function(v) {
            deferredObject.resolve();
        })

        promise.always(function(data, textStatus, jqXHR) {
            track_instances = self.get_track_instances(); //repopulate instances - they might have changed since
            track_instances.removeClass('track-loading');
        })

        return deferredObject.promise();
    }
    

    

    
    set_bottom_trackinfo(){
        var self = this;
        //track infos
        
        var tracklist_el = self.tracklist.tracklist_el;

        //copy attributes from the original playlist 
        var attributes = $(tracklist_el).prop("attributes");
        $.each(attributes, function() {
            $(bottom_trackinfo_el).attr(this.name, this.value);
        });
        $(bottom_trackinfo_el).removeClass('tracklist-table');
        
        var list = $('<table class="wpsstm-tracklist-entries"></table>');

        var row = self.track_el.clone(true,true);
        row.show(); //be sure it will be displayed
        
        $(list).append(row);

        $(bottom_trackinfo_el).html(list);
        
        $(bottom_el).show();//show in not done yet
    }

    play_track_source(source_idx){

        var self = this;
        var success = $.Deferred();
        
        //is a track init!
        if ( source_idx === undefined ){
            source_idx = 0;
        }
        
         self.debug("play_track_source");
        
        var source_obj = self.get_source_obj(source_idx);
        self.set_bottom_audio_el(); //build <audio/> el
        var track_instances = self.get_track_instances();
        
        if(!source_obj){
            self.debug("source does not exists");
            success.reject("source does not exists");
        }else{
            
            //TO FIX check if same source playing already ?
            //if (self.current_source_idx === source_obj.index){
            //}
            
           
            
            source_obj.init_source().then(
                function(success_msg){

                    $(source_obj.media).on('play', function() {

                        source_obj.debug('media - play');

                        if (!self.playback_start){
                            self.playback_start = Math.round( $.now() /1000);
                        }

                        track_instances.addClass('track-playing track-has-played');
                        track_instances.removeClass('track-error track-loading');
                        
                        wpsstm_page_player.current_tracklist_idx = source_obj.track.tracklist.index;
                        source_obj.track.tracklist.current_track_idx = source_obj.track.index;
                        source_obj.track.current_source_idx = source_obj.index;
                        
                        console.log(wpsstm_page_player);
                        
                        success.resolve(source_obj);
                    });

                    $(source_obj.media).on('pause', function() {
                        source_obj.debug('player - pause');
                        track_instances.removeClass('track-playing');
                    });

                    $(source_obj.media).on('ended', function() {
                        source_obj.debug('MediaElement.js event - ended');
                        
                        track_instances.removeClass('track-playing track-active');

                        //Play next song if any
                        self.tracklist.next_track_jump();
                    });
                    
                    source_obj.media.play();
                    
                },

                function(error_msg){
                    success.reject("unable to fetch track sources");
                }

            )
            
            

            
        }

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
        var deferredObject = $.Deferred();
        if (self.sources.length > 0){
            deferredObject.resolve();
        }else{
            var promise = self.get_track_sources_request();
            promise.fail(function() {
                deferredObject.reject();
            })
            promise.done(function(v) {
                deferredObject.resolve(v);
            })
        }
        return deferredObject.promise();
    }

    get_track_sources_request() {

        var self = this;
        var deferredObject = $.Deferred();
        
        self.debug("get_track_sources_request");
        
        if ( self.did_sources_request ) {
            
            deferredObject.resolve("already did sources auto request for track #" + self.index);
            
        } else{
        
            var track_el    = self.track_el;
            var track_instances = self.get_track_instances();
            $(track_el).find('.wpsstm-track-sources').html('');

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

        $(track_el).attr('data-wpsstm-sources-count',self.sources.length);
        
        $(document).trigger("wpsstmTrackSourcesDomReady",[self]); //custom event for all sources

    }
    
    get_source_obj(source_idx){
        var self = this;
        
        if(typeof source_idx === 'undefined'){
            source_idx = self.current_source_idx;
        }

        source_idx = Number(source_idx);
        var source_obj = self.sources[source_idx];
        if(typeof source_obj === 'undefined') return;
        return source_obj;
    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var self = this;
        var allowed = ['index','artist', 'title','album','post_id'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
        obj[key] = self[key];
        return obj;
        }, {});
        return filtered;
    }
    
    update_source_index(source_obj){
        var self = this;

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_track_update_source_index',
            source:     source_obj.to_ajax()
        };

        jQuery.ajax({
            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                self.track_el.addClass('track-loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    //self.update_sources_order();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                self.track_el.removeClass('track-loading');
            }
        })

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
        
        wpsstm_page_player.current_media = self.media;

    }
    
}