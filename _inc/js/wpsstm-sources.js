class WpsstmTrackSource {
    constructor(source_html,track) {
        
        this.source_el =        $([]);
        this.track =            new WpsstmTrack();
        
        this.index =            null;
        this.post_id =          null;
        this.src =              null;
        this.type =             null;
        this.can_play =         false;
        this.media =            undefined;
        
        //tracklist
        if ( track !== undefined ){
            this.track =        track;
        }
        
        //track
        if ( source_html !== undefined ){
            this.source_el =        $(source_html);
            this.index =            Number(this.source_el.attr('data-wpsstm-source-idx'));
            this.post_id =          Number(this.source_el.attr('data-wpsstm-source-id'));
            this.src =              this.source_el.attr('data-wpsstm-source-src');
            this.type =             this.source_el.attr('data-wpsstm-source-type');
            this.can_play =         ( Boolean(this.type) && Boolean(this.src) );
        }
        
        //this.debug("new WpsstmTrackSource");
        
        if (!this.can_play){
            this.source_el.addClass('source-error');
        }

    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #"+ this.track.tracklist.index +" - WpsstmTrack #" + this.track.index+" - WpsstmTrackSource #" + this.index;
        wpsstm_debug(msg,prefix);
    }

    get_track_el(){
        var self = this;
        return self.track_el.closest('[data-wpsstm-track-idx="'+self.track.index+'"]');
    }

    get_source_instances(ancestor){
        var self = this;
        var selector = '[data-wpsstm-tracklist-idx="'+self.track.tracklist.index+'"] [itemprop="track"][data-wpsstm-track-idx="'+self.track.index+'"] [data-wpsstm-source-idx="'+self.index+'"]';
        
        if (ancestor !== undefined){
            return $(ancestor).find(selector);
        }else{
            return $(selector);
        }
    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var self = this;
        var allowed = ['index','post_id'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
        obj[key] = self[key];
        return obj;
        }, {});
        
        //track
        filtered.track = self.track.to_ajax();

        return filtered;
    }

    init_source(){

        var self = this;
        var success = $.Deferred();

        var new_source = { src: self.src, 'type': self.type };
        
        self.debug("init_source: " + new_source.src);
        
        var audio_el = $('#wpsstm-player-audio');
        var source_instances = self.get_source_instances();

        $(audio_el).mediaelementplayer({
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug:          wpsstmL10n.debug,
                autoStartLoad:  true
            },
            pluginPath: wpsstmPlayer.plugin_path, //'https://cdnjs.com/libraries/mediaelement/'
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(mediaElement, originalNode, player) {

                self.media = mediaElement;
                wpsstm.current_media = self.media;

                $(self.media).on('loadeddata', function() {
                    $(document).trigger( "wpsstmMediaLoaded",[self.media,self] ); //custom event
                    self.can_play = true;

                    self.debug('media - loadeddata');
                    success.resolve();
                });

            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
                var source_instances = self.get_source_instances();
                source_instances.addClass('source-error');
                source_instances.removeClass('source-active');
                success.reject();
            }
        });

        //player
        self.media.pause();
        self.media.setSrc(new_source.src);
        self.media.load();
        
        return success.promise();

    }
    
    play_source(){

        var self = this;
        var success = $.Deferred();

        self.track.set_bottom_audio_el(); //build <audio/> el
        var tracklist_instances = self.track.tracklist.get_tracklist_instances();
        var track_instances = self.track.get_track_instances();
        var source_instances = self.get_source_instances();
        source_instances.addClass('source-loading');

        var promise = self.init_source();

        promise.then(
            function(success_msg){

                $(self.media).on('error', function(error) {

                    self.can_play = false;

                    self.debug('media - error');

                    source_instances.addClass('source-error');
                    source_instances.removeClass('source-active source-loading');

                    success.reject(error);

                });

                $(self.media).on('play', function() {


                    var trackinfo_sources = track_instances.find('[data-wpsstm-source-idx]');
                    $(trackinfo_sources).removeClass('source-playing');
                    
                    track_instances.removeClass('track-error track-loading');

                    tracklist_instances.addClass('tracklist-playing tracklist-has-played');
                    track_instances.addClass('track-playing track-has-played');
                    source_instances.addClass('source-active source-playing source-has-played');

                    self.debug('media - play');

                    self.track.current_source_idx = self.index;
                    success.resolve(self);
                });

                $(self.media).on('pause', function() {
                    self.debug('player - pause');
                    tracklist_instances.removeClass('tracklist-playing');
                    track_instances.removeClass('track-playing');
                    source_instances.removeClass('source-playing');
                });

                $(self.media).on('ended', function() {
                    self.debug('media - ended');
                    tracklist_instances.removeClass('tracklist-playing');
                    track_instances.removeClass('track-playing track-active');
                    source_instances.removeClass('source-playing source-active');

                    //Play next song if any
                    self.track.tracklist.next_track_jump();
                });

                self.media.play();

            },
            function(error_msg){
                success.reject(error_msg);
            }

        )
        
        success.always(function(data, textStatus, jqXHR) {
            source_instances.removeClass('source-loading');
        })
        success.fail(function() {
            source_instances.addClass('source-error');
            source_instances.removeClass('source-active');
        })

        return success.promise();

    }
    
    end_source(){
        var self = this;
        if (!self.media) return;

        self.debug("end_source #" + self.index);
        self.media.pause();
        wpsstm.current_media = undefined;
        
        self.get_source_instances().removeClass('source-playing source-active source-loading');

    }

}

(function($){

    $.fn.extend({
        sourceManager: function(options){
            // OPTIONS
            var defaults = {};
            
            var options =  $.extend(defaults, options);

            // FOR EACH MATCHED ELEMENT
            return this.each(function() {
                var sources_container = $(this);
                var sources_list_el = sources_container.find('.wpsstm-track-sources-list');
                var track_id = $(this).attr('data-wpsstm-track-id');

                //no sources list
                if ( !sources_container.hasClass('wpsstm-track-sources') ){
                    wpsstm_debug("missing class .wpsstm-track-sources-list:",'sourceManager');
                    wpsstm_debug(sources_container,'sourceManager');
                    return false;
                }

                //click expand sources
                sources_container.find('.wpsstm-expand-sources a').click(function(e) {
                    e.preventDefault();
                    $(this).parents('.wpsstm-track-sources').toggleClass('wpsstm-sources-expanded');
                });

                //show more/less (sources actions)
                var actions_lists = sources_container.find('.wpsstm-actions-list');
                //TO FIX wpsstm.showMoreLessActions(actions_lists);
                
                //delete source
                sources_container.find('.wpsstm-source-action-trash a').click(function(e) {
                    e.preventDefault();
                    var source_el = $(this).parents('[data-wpsstm-source-idx]');
                    source_el.trashSources();

                });
                
                // sort track sources
                sources_list_el.sortable({
                    axis: "y",
                    items : "[data-wpsstm-source-id]",
                    handle: '.wpsstm-source-action-move a',
                    update: function(event, ui) {
                        
                        var sourceOrder = sources_list_el.sortable('toArray', {
                            attribute: 'data-wpsstm-source-id'
                        });
                        
                        sources_list_el.addClass('wpsstm-freeze');

                        var reordered = WpsstmTrack.update_sources_order(track_id,sourceOrder);

                        reordered.always(function() {
                            sources_list_el.removeClass('wpsstm-freeze');
                        })

                    }
                });
                
            });
        },
        trashSources: function(options){
            
            // OPTIONS
            var defaults = {};
            var options =  $.extend(defaults, options);
            
            //see https://stackoverflow.com/a/19574266/782013
            var deferredSum = $.Deferred();
            var deferredList = [];

            // FOR EACH MATCHED ELEMENT
            return this.each(function() {

                var deferredSingle = $.Deferred();
                deferredList.push(deferredSingle.promise());
                
                var source_el = $(this);
                var source_id = source_el.attr('data-wpsstm-source-id');
                var source_idx = Number(source_el.attr('data-wpsstm-source-idx'));
                var track_el = source_el.parents('[data-wpsstm-track-idx]');
                var track_id = source_el.attr('data-wpsstm-track-id');
                var track_idx = Number(track_el.attr('data-wpsstm-track-idx'));
                var tracklist_el = track_el.parents('[data-wpsstm-tracklist-idx]');
                var tracklist_idx = Number(tracklist_el.attr('data-wpsstm-tracklist-idx'));
                
                //missing source ID
                if ( !source_id ){
                    deferredSingle.reject("deleteSource: missing 'data-wpsstm-source-id' attr");
                }
                
                //missing track ID
                if ( !track_id ){
                    deferredSingle.reject("deleteSource: missing 'data-wpsstm-track-id' attr");
                }

                var source_instances = $('[data-wpsstm-source-idx][data-wpsstm-track-id="'+track_id+'"][data-wpsstm-source-id="'+source_id+'"]');
                var source_action_links = source_instances.find('.wpsstm-source-action-trash a');

                var ajax_data = {
                    action:         'wpsstm_trash_source',
                    post_id:        source_id
                };

                source_action_links.addClass('action-loading');

                var ajax_request = $.ajax({

                    type:       "post",
                    url:        wpsstmL10n.ajaxurl,
                    data:       ajax_data,
                    dataType:   'json'
                })

                ajax_request.done(function(data){
                    if (data.success === true){

                        //set source 'can_play' to false
                        if ( ( tracklist_obj = wpsstm.tracklists[tracklist_idx] ) && ( track_obj = tracklist_obj.tracks[track_idx] ) && ( source_obj = track_obj.sources[source_idx] ) ){
                            
                            source_obj.can_play = false;

                            //skip current source as it was playibg
                            if ( source_el.hasClass('source-playing') ){
                                source_obj.debug('source was playing, skip it !');
                                source_obj.debug(source_obj);
                                
                                track_obj.play_track(source_idx + 1);
                            }
                        }
                        
                        ///
                        source_instances.remove();
                        deferredSingle.resolve();
                        
                    }else{
                        source_action_links.addClass('action-error');
                        console.log(data);
                        deferredSingle.reject(data.message);
                    }
                });

                ajax_request.fail(function(jqXHR, textStatus, errorThrown) {
                    source_action_links.addClass('action-error');
                    deferredSingle.reject();
                })

                ajax_request.always(function(data, textStatus, jqXHR) {
                    source_action_links.removeClass('action-loading');
                })
                
            });
            
            $.when.apply($, deferredList).done(function() {
               deferredSum.resolve(); 
            });

            return deferredSum.promise();
            

        }
    });

    $(document).on( "wpsstmTrackSourcesDomReady", function( event, track_obj ) {
        var track_instances = track_obj.get_track_instances();
        var sources_container = track_instances.find('.wpsstm-track-sources');
        sources_container.sourceManager();
    });

    $(document).on( "wpsstmTrackSingleSourceDomReady", function( event, source_obj ) {

        var track_instances = source_obj.track.get_track_instances();
        
        //click on source trigger
        track_instances.find('[data-wpsstm-source-idx] .wpsstm-source-title').click(function(e) {
            e.preventDefault();
            source_obj.track.play_track(source_obj.index);
            //toggle tracklist sources
            source_obj.track.track_el.removeClass('wpsstm-sources-expanded');
        });

    });
    
    /*
    metabox
    */
    var track_sources_row = $( ".postbox#wpsstm-metabox-sources .wpsstm-track-sources");
    track_sources_row.sourceManager();
    //new source container
    $( ".postbox#wpsstm-metabox-sources #wpsstm-add-source-url" ).click(function(e) {
        e.preventDefault();
        var container = $(this).parents('.postbox');
        var first_input_block = container.find('#wpsstm-new_track-sources').parent().first();
        var cloned = first_input_block.clone().insertBefore(container);
        cloned.find('input[type="text"]').val("");
        cloned.insertBefore(first_input_block);
    });
    
})(jQuery);


