(function($){
    
    //track popups within iframe
    $('body.wpsstm-tracklist-iframe').on('click', 'a.wpsstm-track-popup,li.wpsstm-track-popup>a', function(e) {
        e.preventDefault();

        var content_url = this.href;

        console.log("track popup");
        console.log(content_url);


        var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
        var popup = $('<div></div>').append(loader_el);

        var popup_w = $(window).width();
        var popup_h = $(window).height();

        popup.dialog({
            width:popup_w,
            height:popup_h,
            modal: true,
            dialogClass: 'wpsstm-track-dialog wpsstm-dialog dialog-loading',

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
        var input_el =                  $(tracklistSelector).find('#wpsstm-playlists-filter input[type="text"]');

        if (!input_el.val()){
            $(input_el).focus();
        }

        var ajax_data = {
            action:         'wpsstm_subtrack_tracklist_manager_new_playlist',
            playlist_title: input_el.val(),
            track_id:       track_id,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                input_el.addClass('input-loading');
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
                input_el.removeClass('input-loading');
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
            track_action:   (is_checked ? 'append' : 'unlink'),
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                checkbox.addClass('input-loading');
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
                checkbox.addClass('input-loading');
            }
        })

    });

    $(document).on( "wpsstmTrackDomReady", function( event, track_obj ) {
        
        //toggle favorite
        track_obj.track_el.find('.wpsstm-track-action-toggle-favorite a').click(function(e) {

            e.preventDefault();

            var link = $(this);
            var action_el = link.parents('.wpsstm-track-action');
            var do_love = action_el.hasClass('action-favorite');

            var track_ajax = track_obj.to_ajax();

            var ajax_data = {
                action:     'wpsstm_toggle_favorite_track',
                track:      track_ajax
            };

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
                        if (do_love){
                            action_el.removeClass('action-favorite').addClass('action-unfavorite');
                        }else{
                            action_el.removeClass('action-unfavorite').addClass('action-favorite');
                        }
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(thrownError);
                    link.addClass('action-error');
                },
                complete: function() {
                    link.removeClass('action-loading');
                    $(document).trigger("wpsstmTrackLove", [track_obj,do_love] ); //register custom event - used by lastFM for the track.updateNowPlaying call
                }
            })

        });

        //unlink
        track_obj.track_el.find('.wpsstm-track-action-unlink a').click(function(e) {
            e.preventDefault();
            track_obj.tracklist.unlink_subtrack(track_obj);
        });
        
        //delete
        track_obj.track_el.find('.wpsstm-track-action-trash a').click(function(e) {
            e.preventDefault();
            track_obj.delete_track();
        });
        
        //sources
        var toggleSourcesEl = track_obj.track_el.find('.wpsstm-track-action-toggle-sources a');
        if (!track_obj.sources.length){
            toggleSourcesEl.hide();
        }else{
            var sourceCountEl = $('<span class="wpsstm-sources-count">'+track_obj.sources.length+'</span>');
            toggleSourcesEl.append(sourceCountEl);

            //toggle sources
            toggleSourcesEl.click(function(e) {
                e.preventDefault();

                $(this).toggleClass('active');
                $(this).parents('.wpsstm-track').find('.wpsstm-track-sources-list').toggleClass('active');
            });            
        }

    });
    
    $(document).on( "wpsstmQueueTrack", function( event, track_obj ) {
        
        //expand tracklist
        var track_el = track_obj.track_el;
        if ( track_el.is(":visible") ) return;

        var tracklist_obj = track_obj.tracklist;
        var visibleTracksCount = tracklist_obj.tracklist_el.find('[itemprop="track"]:visible').length;
        var newTracksCount = track_obj.position + 1;
        
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
        
        this.position =             null;
        this.artist =               null;
        this.title =                null;
        this.album =                null;
        this.subtrack_id =          null;
        this.post_id =              null;
        //this.autosource_time =    null;
        this.can_play =             null;
        
        this.sources =              [];
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
        var prefix = " WpsstmTracklist #"+ this.tracklist.position +" - WpsstmTrack #" + this.position;
        wpsstm_debug(msg,prefix);
    }
    
    init_html(track_html){
        
        var self = this;

        if ( track_html === undefined ) return;

        self.track_el =             $(track_html);
        self.position =             Number(self.track_el.attr('data-wpsstm-subtrack-position')); //index in tracklist
        self.artist =               self.track_el.find('[itemprop="byArtist"]').text();
        self.title =                self.track_el.find('[itemprop="name"]').text();
        self.album =                self.track_el.find('[itemprop="inAlbum"]').text();
        self.post_id =              Number(self.track_el.attr('data-wpsstm-track-id'));
        self.subtrack_id =          Number(self.track_el.attr('data-wpsstm-subtrack-id'));
        //self.autosource_time =      Number(self.track_el.attr('data-wpsstm-autosource-time'));

        //populate existing sources
        self.populate_html_sources();

        $(document).trigger("wpsstmTrackDomReady",[self]); //custom event
        
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
            
            success.resolve("already did sources auto request for track #" + self.position);
            
        } else{
            success = self.get_track_sources_request();
        }
        
        //set .can_play property
        success.always(function() {
            self.can_play = (self.sources.length > 0);
        });

        return success.promise();
    }

    get_track_sources_request() {

        var self = this;
        var success = $.Deferred();

        self.track_el.addClass('track-loading');

        var ajax_data = {
            action:     'wpsstm_track_autosource',
            track:      self.to_ajax(),   
        };

        var sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        sources_request.done(function(data) {

            self.did_sources_request = true;
            
            if ( data.success === true ){
                
                self.refresh_track_html().then(
                    function(success_msg){
                        success.resolve();
                    },
                    function(error_msg){
                        success.reject(error_msg);
                    }
                );

            }else{
                self.debug("track sources request failed: " + data.message);
                success.reject(data.message);
            }

        });

        success.fail(function() {
            self.track_el.addClass('track-error');
        });

        success.always(function() {
            self.track_el.removeClass('track-loading');
        });
        
        return success.promise();

    }
    
    refresh_track_html(){
        
        var self = this;
        var success = $.Deferred();

        self.debug("refresh_track_html");
        self.track_el.addClass('track-loading');
        
        var ajax_data = {
            action:     'wpsstm_track_html',
            track:      self.to_ajax(),   
        };

        var sources_request = $.ajax({
            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json',
        });

        sources_request.done(function(data) {
            if ( data.html ){
                var new_node = $(data.html);
                self.track_el.replaceWith( new_node );
                self.init_html( new_node.get(0) );
                success.resolve();
            }else{
                self.debug("track refresh failed: " + data.message);
                success.reject(data.message);
            }
        });
        
        success.fail(function() {
            self.track_el.addClass('track-error');
        });

        success.always(function() {
            self.track_el.removeClass('track-loading');
        });
        
        return success.promise();
    }
    
    populate_html_sources(){
        var self =      this;
        
        self.sources =              [];//reset array
        var source_els = self.track_el.find('[data-wpsstm-source-idx]');

        $.each(source_els, function( index, source_el ) {
            var source_obj = new WpsstmTrackSource(source_el,self);
            self.sources.push(source_obj);
            $(document).trigger("wpsstmTrackSingleSourceDomReady",[source_obj]); //custom event for single source
        });
      
        self.track_el.attr('data-wpsstm-sources-count',self.sources.length);        
        $(document).trigger("wpsstmTrackSourcesDomReady",[self]); //custom event for all sources

    }
    
    get_source_obj(source_idx){
        var self = this;

        source_idx = Number(source_idx);
        var source_obj = self.sources[source_idx];
        if(typeof source_obj === undefined) return;
        return source_obj;
    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var self = this;
        var allowed = ['position','subtrack_id','post_id','artist', 'title','album','duration'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
            obj[key] = self[key];
            return obj;
        }, {});
        
        //tracklist
        filtered.tracklist_id = self.tracklist.post_id;
        
        return filtered;
    }
    
    delete_track(track_obj){
        
        var self = this;
        var track_el = track_obj.track_el;
        var link = $(track_el).find('.wpsstm-track-action-trash a');

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
                    self.refresh_tracks_positions();
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

    static update_sources_order(track_id,source_ids){
        
        var self = this;
        var success = $.Deferred();

        //ajax update order
        var ajax_data = {
            action:     'wpsstm_update_track_sources_order',
            track_id:   track_id,
            source_ids: source_ids
        };
        
        //self.debug(ajax_data,"update_sources_order");

        var ajax = jQuery.ajax({
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

        ajax.always(function() {
            self.track_el.removeClass('track-loading');
        });
        
        
        return success.promise();
    }
    
}