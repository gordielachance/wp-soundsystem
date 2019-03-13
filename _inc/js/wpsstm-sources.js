var $ = jQuery.noConflict();

class WpsstmSource extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.track =            undefined;
        this.index =            undefined;
        this.post_id =          undefined;
        this.src =              undefined;
        this.type =             undefined;
        this.can_play =         undefined;
        this.duration =         undefined;

        // Setup a click listener on <wpsstm-tracklist> itself.
        this.addEventListener('click', e => {
        });
    }
    connectedCallback(){
        //console.log("SOURCE CONNECTED!");
        /*
        Called every time the element is inserted into the DOM. Useful for running setup code, such as fetching resources or rendering. Generally, you should try to delay work until this time.
        */
        this.render();
    }

    disconnectedCallback(){
        /*
        Called every time the element is removed from the DOM. Useful for running clean up code.
        */
    }
    attributeChangedCallback(attrName, oldVal, newVal){
        /*
        Called when an observed attribute has been added, removed, updated, or replaced. Also called for initial values when an element is created by the parser, or upgraded. Note: only attributes listed in the observedAttributes property will receive this callback.
        */
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        //return ['id', 'my-custom-attribute', 'data-something', 'disabled'];
    }
    
    ///
    ///
    
    debug(msg){
        var self = this;
        var track = self.track;
        var prefix = "WpsstmTrack #" + track.position+" - WpsstmTrackSource #" + this.index;
        wpsstm_debug(msg,prefix);
    }
    
    get_instances(){
        var self = this;
        return $(document).find('wpsstm-source[data-wpsstm-source-id="'+self.post_id+'"]');
    }
    
    render(){
        var self = this;
        
        self.track =            self.closest('wpsstm-track');

        self.index =            Number($(self).attr('data-wpsstm-source-idx'));
        self.post_id =          Number($(self).attr('data-wpsstm-source-id'));
        self.src =              $(self).attr('data-wpsstm-source-src');
        self.type =             $(self).attr('data-wpsstm-source-type');
        self.can_play =         ( Boolean(self.type) && Boolean(self.src) );
        self.duration =         undefined;

        if (!this.can_play){
            $(this).addClass('source-error');
        }

        //update track
        var trackSources = $(self.track).find('wpsstm-source');

        if (!trackSources.length && track.did_sources_request){
            $(self.track).addClass('track-error');
        }

        var toggleSourcesEl = $(self.track).find('.wpsstm-track-action-toggle-sources a');
        var sourceCountEl = toggleSourcesEl.find('.wpsstm-sources-count');
        if ( !sourceCountEl.length ){ //create item
            sourceCountEl = $('<span class="wpsstm-sources-count"></span>');
            toggleSourcesEl.append(sourceCountEl);            
        }

        $(self.track).attr('data-wpsstm-sources-count',trackSources.length);
        sourceCountEl.text(trackSources.length);

        //delete source
        $(self).on('click', '.wpsstm-source-action-trash a', function(e) {
            e.preventDefault();
            self.trash_source();
        });
        
        //play source
        $(self).on('click', '.wpsstm-source-title', function(e) {
            e.preventDefault();
            self.play_source();
            //toggle tracklist sources
            $(source.track).removeClass('wpsstm-sources-expanded');
        });
        
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
    
    trash_source(){
        var self = this;
        var source_action_links = $(self).find('.wpsstm-source-action-trash a');

        var ajax_data = {
            action:         'wpsstm_trash_source',
            post_id:        self.post_id
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

                self.can_play = false;

                //skip current source as it was playibg
                if ( $(self).hasClass('source-playing') ){
                    self.debug('source was playing, skip it !');
                    self.debug(self);
                }

                ///
                $(self).remove();

            }else{
                source_action_links.addClass('action-error');
                console.log(data);
            }
        });

        ajax_request.fail(function(jqXHR, textStatus, errorThrown) {
            source_action_links.addClass('action-error');
        })

        ajax_request.always(function(data, textStatus, jqXHR) {
            source_action_links.removeClass('action-loading');
        })
    }
    
    play_source(){
        var source = this;
        var track = this.closest('wpsstm-track');
        var player = this.closest('wpsstm-player');
        var success = $.Deferred();
        
        if (!player){
            success.reject("no player");
            return success.promise();
        }
        
        var playingSources = $(player).find('wpsstm-source.source-active');
        var playingTracks = $(player).find('wpsstm-track.track-active');
        
        //we're trying to play the same source again
        if ( $(source).hasClass('source-active') ){
            success.reject("we've already playing this soure");
            return success.promise();
        }
        
        //another source is playing
        
        if ( playingSources.length ){ //a source is currently playing
            playingSources.removeClass('source-active');
            //previous_source.end_source();
        }

        player.current_source = source;
        player.current_track = track;
        $(source).addClass('source-active');
        $(track).addClass('track-active');
        
        player.render_queue_controls();

        var track_instances = source.track.get_instances();

        var source_instances = track_instances.find('wpsstm-source');
        var tracklist_instances = track_instances.parent();
        
        /*
        handle current (previous) source
        */

        source.debug("play source: " + source.src);
        source_instances.addClass('source-active source-loading');
        track_instances.addClass('track-loading track-active');
        
        tracklist_instances.addClass('tracklist-active tracklist-loading');
        


        //hide sources if it is expanded //TOUFIX not working
        var toggleEl = $(player.current_track).find('.wpsstm-track-action-toggle-sources a');
        if ( toggleEl.hasClass('.active') ){
            toggleEl.click();
        }

        /*
        register new events
        */
        
        $(player.current_media).off(); //remove old events
        $(document).trigger( "wpsstmSourceInit",[player,source] );

        $(player.current_media).on('loadeddata', function() {
            $(document).trigger( "wpsstmSourceLoaded",[player,source] ); //custom event
            player.debug('source loaded');
            source.duration = player.current_media.duration;
            player.current_media.play();
        });

        $(player.current_media).on('error', function(error) {
            player.debug('media - error');
            success.reject(error);
        });

        $(player.current_media).on('play', function() {
            //player.debug('media - play');
            success.resolve();
            
            source_instances.addClass('source-playing source-has-played');
            tracklist_instances.addClass('tracklist-playing tracklist-has-played');
            track_instances.addClass('track-playing track-has-played');

        });

        $(player.current_media).on('pause', function() {
            //player.debug('player - pause');

            //tracklists
            tracklist_instances.removeClass('tracklist-playing');
            //tracks
            track_instances.removeClass('track-playing');
            //sources
            source_instances.removeClass('source-playing');
        });

        $(player.current_media).on('ended', function() {

            player.debug('media - ended');
            
            //tracklists
            tracklist_instances.removeClass('tracklist-playing');
            //tracks
            track_instances.removeClass('track-playing track-active');
            //sources
            source_instances.removeClass('source-playing source-active');

            //Play next song if any
            player.next_track_jump();
        });
        
        success.always(function(data, textStatus, jqXHR) {
            source_instances.removeClass('source-loading');
            track_instances.removeClass('track-loading');
        })
        success.done(function(v) {
            source.can_play = true;
            tracklist_instances.removeClass('tracklist-loading');
            track_instances.removeClass('track-error track-loading');
            
        })
        success.fail(function() {
            source.can_play = false;
            //sources
            source_instances.removeClass('source-active').addClass('source-error');
            //tracks
            track_instances.addClass('track-error');
            track_instances.removeClass('track-active');
        })

        ////
        player.current_media.setSrc(source.src);
        player.current_media.load();
        
        ////

        return success.promise();

    }
    
    end_source(){

        var source = this;
        var player = source.track.player;

        source.debug("end_source");
        
        player.current_media.pause();

        //TOUFIX TO CHECK should be hookend on events ?

        //sources
        source.get_instances().removeClass('source-playing source-active source-loading');
        
        
        player.current_source = undefined;
        
    }

}

window.customElements.define('wpsstm-source', WpsstmSource);

/*
metabox
*/
//new source container
$( ".postbox#wpsstm-metabox-sources #wpsstm-add-source-url" ).click(function(e) {
    e.preventDefault();
    var container = $(this).parents('.postbox');
    var first_input_block = container.find('#wpsstm-new_track-sources').parent().first();
    var cloned = first_input_block.clone().insertBefore(container);
    cloned.find('input[type="text"]').val("");
    cloned.insertBefore(first_input_block);
});