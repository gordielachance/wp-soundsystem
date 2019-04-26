var $ = jQuery.noConflict();

class WpsstmLink extends HTMLElement{
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
        //console.log("LINK CONNECTED!");
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
        var debug = {message:msg,link:this};
        wpsstm_debug(debug);
    }
    
    get_instances(){
        var self = this;
        return $(document).find('wpsstm-track-link[data-wpsstm-link-id="'+self.post_id+'"]');
    }
    
    render(){
        var self = this;
        
        self.track =            self.closest('wpsstm-track');

        self.index =            Number($(self).attr('data-wpsstm-link-idx'));
        self.post_id =          Number($(self).attr('data-wpsstm-link-id'));
        self.src =              $(self).attr('data-wpsstm-stream-src');
        self.type =             $(self).attr('data-wpsstm-stream-type');
        self.can_play =         $(self).hasClass('wpsstm-playable-link');
        self.duration =         undefined;

        //delete link
        $(self).on('click', '.wpsstm-track-link-action-trash', function(e) {
            e.preventDefault();
            self.trash_link();
        });
        
        //play link
        $(self).on('click', '.wpsstm-track-link-action-play,wpsstm-track-link.wpsstm-playable-link .wpsstm-link-title', function(e) {
            e.preventDefault();
            var link = this.closest('wpsstm-track-link');
            var track = this.closest('wpsstm-track');
            var linkIdx = Array.from(link.parentNode.children).indexOf(link);

            if (track.queueNode){ //page track, get the queue track
                track = track.queueNode;
            }

            var trackIdx = Array.from(track.parentNode.children).indexOf(track);

            var player = track.closest('wpsstm-player');

            //toggle tracklist links
            if ( !$(track).hasClass('track-playing') ){
                var list = $(track).find('.wpsstm-track-links-list');
                $( list ).removeClass('active');
            }

            player.play_queue(trackIdx,linkIdx);

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
    
    trash_link(){
        var self = this;
        var action_link = $(self).find('.wpsstm-track-link-action-trash');

        var ajax_data = {
            action:         'wpsstm_trash_link',
            post_id:        self.post_id
        };

        action_link.addClass('action-loading');

        var ajax_request = $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })

        ajax_request.done(function(data){
            if (data.success === true){

                self.can_play = false;

                //skip current link as it was playibg
                if ( $(self).hasClass('link-playing') ){
                    self.debug('link was playing, skip it !');
                    self.debug(self);
                }

                ///
                $(self).remove();

            }else{
                action_link.addClass('action-error');
                console.log(data);
            }
        });

        ajax_request.fail(function(jqXHR, textStatus, errorThrown) {
            action_link.addClass('action-error');
        })

        ajax_request.always(function(data, textStatus, jqXHR) {
            action_link.removeClass('action-loading');
        })
    }

    play_link(){
        var link = this;
        var track = this.closest('wpsstm-track');
        var track_instances = link.track.get_instances();
        var link_instances = link.get_instances();
        var tracks_container = track_instances.parents('.tracks-container');
        var player = this.closest('wpsstm-player');
        var success = $.Deferred();
        
        if(link.can_play === false){
            success.reject('cannot play this link');
            return success.promise();
        }

        ///
        link_instances.addClass('link-active link-loading');
        //
        
        link.debug("play link: " + link.src);
        link.setAttribute('requestLinkPlay',true);
        player.current_link = link;

        /*
        register new events
        */
        
        $(player.current_media).off(); //remove old events
        $(document).trigger( "wpsstmLinkInit", [link] );

        $(player.current_media).on('loadeddata', function() {
            $(document).trigger( "wpsstmLinkLoaded",[player,link] ); //custom event
            player.debug('link loaded');
            link.duration = player.current_media.duration;
            player.current_media.play();
        });

        $(player.current_media).on('error', function(error) {
            track_instances.addClass('track-error');
            link.can_play = false;
            link_instances.removeClass('link-active').addClass('link-error');
            success.reject(error);
        });

        $(player.current_media).on('play', function() {
            $(player).addClass('player-playing player-has-played');
            tracks_container.addClass('tracks-container-playing tracks-container-has-played');
            track_instances.removeClass('track-error').addClass('track-playing track-has-played');
            track.setAttribute('trackstatus','playing');
            link_instances.removeClass('link-error').addClass('link-playing link-has-played');
            success.resolve();
        });

        $(player.current_media).on('pause', function() {
            //player.debug('player - pause');

            $(player).removeClass('player-playing');
            tracks_container.removeClass('tracks-container-playing');
            track.setAttribute('trackstatus','paused');
            track_instances.removeClass('track-playing');
            link_instances.removeClass('link-playing');
        });

        $(player.current_media).on('ended', function() {

            player.debug('media - ended');
            
            $(player).removeClass('player-playing');
            tracks_container.removeClass('tracks-container-playing');
            track.removeAttribute('trackstatus');
            link_instances.removeClass('link-playing link-active');

            //Play next song if any
            player.next_track_jump();
        });
        
        success.always(function(data, textStatus, jqXHR) {
            link_instances.removeClass('link-loading');
        })
        success.done(function(v) {
            player.tracksHistory.push(track);
            link.can_play = true;
            //TOUFIX ajax --> +1 track play; user now playing...
        })
        success.fail(function() {
            link.can_play = false;
        })

        ////
        player.current_media.setSrc(link.src);
        player.current_media.load();
        
        ////

        return success.promise();

    }

}

window.customElements.define('wpsstm-track-link', WpsstmLink);

/*
metabox
*/
//new link container
$( ".postbox#wpsstm-metabox-track-links #wpsstm-add-link-url" ).click(function(e) {
    e.preventDefault();
    var container = $(this).parents('.postbox');
    var first_input_block = container.find('#wpsstm-new_track-links').parent().first();
    var cloned = first_input_block.clone().insertBefore(container);
    cloned.find('input[type="text"]').val("");
    cloned.insertBefore(first_input_block);
});