var $ = jQuery.noConflict();

//play source
$(document).on('click', '.wpsstm-source-title', function(e) {
    e.preventDefault();
    var source = this.closest('wpsstm-source');
    var track = this.closest('wpsstm-track');
    var sourceIdx = Array.from(source.parentNode.children).indexOf(source);
    
    if (track.queueNode){ //page track, get the queue track
        track = track.queueNode;
    }
    
    var trackIdx = Array.from(track.parentNode.children).indexOf(track);
    
    player = track.closest('wpsstm-player');

    //toggle tracklist sources
    var list = $(track).find('.wpsstm-track-sources-list');
    $( list ).removeClass('active');

    player.play_queue(trackIdx,sourceIdx);

});

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
        var debug = {message:msg,source:this};
        wpsstm_debug(debug);
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

        //delete source
        $(self).on('click', '.wpsstm-source-action-trash a', function(e) {
            e.preventDefault();
            self.trash_source();
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
        var track_instances = source.track.get_instances();
        var source_instances = track_instances.find('wpsstm-source');
        var tracks_container = track_instances.parents('.tracks-container');
        var player = this.closest('wpsstm-player');
        var success = $.Deferred();

        ///
        source_instances.addClass('source-active source-loading');
        //
        player.current_source = source;
        source.debug("play source: " + source.src);
        source.setAttribute('requestSourcePlay',true);

        /*
        register new events
        */
        
        $(player.current_media).off(); //remove old events
        $(document).trigger( "wpsstmSourceInit", [source] );

        $(player.current_media).on('loadeddata', function() {
            $(document).trigger( "wpsstmSourceLoaded",[player,source] ); //custom event
            player.debug('source loaded');
            source.duration = player.current_media.duration;
            player.current_media.play();
        });

        $(player.current_media).on('error', function(error) {
            track_instances.addClass('track-error');
            source.can_play = false;
            source_instances.removeClass('source-active').addClass('source-error');
            success.reject(error);
        });

        $(player.current_media).on('play', function() {
            $(player).addClass('player-playing player-has-played');
            tracks_container.addClass('tracks-container-playing');
            track_instances.removeClass('track-error').addClass('track-playing track-has-played');
            track.setAttribute('trackstatus','playing');
            source_instances.removeClass('source-error').addClass('source-playing source-has-played');
            success.resolve();
        });

        $(player.current_media).on('pause', function() {
            //player.debug('player - pause');

            $(player).removeClass('player-playing');
            tracks_container.removeClass('tracks-container-playing');
            track.setAttribute('trackstatus','paused');
            track_instances.removeClass('track-playing');
            source_instances.removeClass('source-playing');
        });

        $(player.current_media).on('ended', function() {

            player.debug('media - ended');
            
            $(player).removeClass('player-playing');
            tracks_container.removeClass('tracks-container-playing');
            track.removeAttribute('trackstatus');
            source_instances.removeClass('source-playing source-active');

            //Play next song if any
            player.next_track_jump();
        });
        
        success.always(function(data, textStatus, jqXHR) {
            source_instances.removeClass('source-loading');
        })
        success.done(function(v) {
            player.tracksHistory.push(track);
            source.can_play = true;
        })
        success.fail(function() {
            source.can_play = false;
        })

        ////
        player.current_media.setSrc(source.src);
        player.current_media.load();
        
        ////

        return success.promise();

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