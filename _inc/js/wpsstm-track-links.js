var $ = jQuery.noConflict();

class WpsstmLink extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.track =            undefined;
        this.index =            undefined;
        this.post_id =          undefined;
        this.src =              undefined;
        this.type =             undefined;

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
        
        var isValueChanged = (newVal !== oldVal);
        if (!isValueChanged) return;
        
        var source = this;

        //source.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
            case 'linkstatus':

                if ( !newVal ){
                    $(source).removeClass('link-active link-loading link-playing');                    
                }
                
                if (newVal == 'request'){
                    source.debug("request source: " + source.src);
                    $(source).addClass('link-active link-loading');
                }
                
                if ( newVal == 'playing' ){
                    $(source).removeClass('link-loading').addClass('link-playing link-has-played');
                }
                
                if ( newVal == 'paused' ){
                    $(source).removeClass('link-playing');
                }
                
                
        }
        
        
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return ['linkstatus','wpsstm-playable'];
    }
    
    get status() {
        return this.getAttribute('linkstatus');
    }
    
    set status(value) {
        this.setAttribute('linkstatus',value);
    }
    
    get playable() {
        return this.hasAttribute('wpsstm-playable');
    }
    
    set playable(value) {
        const isChecked = Boolean(value);
        if (isChecked) {
            this.setAttribute('wpsstm-playable', '');
        } else {
            this.removeAttribute('wpsstm-playable');
        }
    }
    
    ///
    ///
    
    debug(msg){
        var debug = {message:msg,link:this};
        wpsstm_debug(debug);
    }

    render(){
        var self = this;
        
        self.track =            self.closest('wpsstm-track');

        self.index =            Number($(self).attr('data-wpsstm-link-idx'));
        self.post_id =          Number($(self).attr('data-wpsstm-link-id'));
        self.src =              $(self).attr('data-wpsstm-stream-src');
        self.type =             $(self).attr('data-wpsstm-stream-type');

        //delete link
        $(self).on('click', '.wpsstm-track-link-action-trash', function(e) {
            e.preventDefault();
            self.trash_link();
        });
        
        //play link
        $(self).on('click', '.wpsstm-track-link-action-play,wpsstm-track-link[wpsstm-playable] .wpsstm-link-title', function(e) {
            e.preventDefault();
            var link = this.closest('wpsstm-track-link');
            var linkIdx = Array.from(link.parentNode.children).indexOf(link);

            var track = self.track;

            var trackIdx = Array.from(track.parentNode.children).indexOf(track);

            if(track.tracklist.player){
                //toggle tracklist links
                if ( !$(track).hasClass('track-playing') ){
                    var list = $(track).find('.wpsstm-track-links-list');
                    $( list ).removeClass('active');
                }

                track.tracklist.player.play_queue(trackIdx,linkIdx);
            }

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
        var link = this;
        var action_link = $(link).find('.wpsstm-track-link-action-trash');

        var ajax_data = {
            action:         'wpsstm_trash_link',
            post_id:        link.post_id
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

                link.playable = false;

                //skip current link as it was playibg
                if ( $(link).hasClass('link-playing') ){
                    link.debug('link was playing, skip it !');
                    link.debug(link);
                }

                ///
                $(link).remove();

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
        var track = this.track;
        var success = $.Deferred();
        
        if( !link.playable ){
            success.reject('cannot play this link');
            return success.promise();
        }

        ///
        $(link.track.tracklist.player.current_media).off(); //remove old events
        link.status = 'request';
        link.track.tracklist.player.current_link = link;
        $(document).trigger( "wpsstmSourceInit",[link] ); //custom event

        /*
        register new events
        */

        $(track.tracklist.player.current_media).on('loadeddata', function() {
            track.tracklist.player.debug('source loaded');
            track.tracklist.player.current_media.play();
        });

        $(track.tracklist.player.current_media).on('error', function(error) {
            link.playable = false;
            link.status = '';
            track.status = '';
            success.reject(error);
        });

        $(track.tracklist.player.current_media).on('play', function() {
            link.status = 'playing';
            track.status = 'playing';
            success.resolve();
        });

        $(track.tracklist.player.current_media).on('pause', function() {
            link.status = 'paused';
            track.status = 'paused';
        });

        $(track.tracklist.player.current_media).on('ended', function() {

            track.tracklist.player.debug('media - ended');
            link.status = '';
            
            //Play next song if any
            track.tracklist.player.next_track_jump();
            
            
        });

        success.done(function(v) {
            track.tracklist.player.tracksHistory.push(track);
            link.playable = true;
            //TOUFIX ajax --> +1 track play; user now playing...
        })
        success.fail(function() {
            link.playable = false;
        })

        ////
        track.tracklist.player.current_media.setSrc(link.src);
        track.tracklist.player.current_media.load();
        
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