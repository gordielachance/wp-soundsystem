var $ = jQuery.noConflict();

class WpsstmLink extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.track =            undefined;
        this.index =            undefined;
        this.post_id =          undefined;
        this.src =              undefined;
        this.type =             undefined;

        //Setup listeners
        this.addEventListener('sourceInit', this._sourceInitEvent);
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
        
        var link = this;
        if (!link.track) return; //TOUFIX TOUCHECK when player track is cloned, this function is called and fails because track is not defined yet.  Works for now, but this should be investigated.

        //link.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
            case 'linkstatus':
                
                //mirror status to player link if needed
                var playerTrack = link.track.tracklist.get_playerTrack();
                var playerLink = $(playerTrack).find('wpsstm-track-link[data-wpsstm-link-id="'+link.post_id+'"]').get(0);
                var isPlayerLink = (link === playerLink);
                if ( playerLink && !isPlayerLink ){
                    playerLink.status = newVal;
                }
                

                if ( !newVal ){
                    $(link).removeClass('link-active link-loading link-playing');
                    
                    if (!isPlayerLink){
                        link.track.status = '';
                    }
                    
                    if (link.track.tracklist.current_link == link){
                        link.track.tracklist.current_media.pause();
                    }
                    
                }
                
                if (newVal == 'request'){
                    $(link).addClass('link-active link-loading');
                    
                    if (!isPlayerLink){
                        $(link.track.tracklist.current_media).off(); //remove old events
                        link.track.tracklist.current_link = link;
                        link.dispatchEvent(new CustomEvent('sourceInit'));

                        link.track.status = 'request';
                    }
                }
                
                if ( newVal == 'playing' ){
                    $(link).removeClass('link-loading').addClass('link-playing link-has-played');
                    
                    if (!isPlayerLink){
                        link.track.status = 'playing';
                    }

                }
                
                if ( newVal == 'paused' ){
                    $(link).removeClass('link-playing');
                    
                    if (!isPlayerLink){
                        link.track.status = 'paused';
                    }
                    
                }
                
            break;

        }
        
        
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return ['linkstatus'];
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
    
    debug(data,msg){
        
        //add prefix
        if (this.post_id){
            var prefix = '[link:'+this.post_id+']';
            if (typeof msg === 'undefined'){
                msg = prefix;
            }else{
                msg = prefix + ' ' + msg;
            }
        }
        
        wpsstm_debug(data,msg);
    }

    render(){
        var link =              this;
        link.track =            $(link).closest('wpsstm-track').get(0);

        link.index =            Number($(link).attr('data-wpsstm-link-idx'));
        link.post_id =          Number($(link).attr('data-wpsstm-link-id'));
        link.src =              $(link).attr('data-wpsstm-stream-src');
        link.type =             $(link).attr('data-wpsstm-stream-type');

        //delete link
        $(link).on('click', '.wpsstm-track-link-action-trash', function(e) {
            e.preventDefault();
            link.trash_link();
        });
        
        //play link
        $(link).on('click', '.wpsstm-track-link-action-play,wpsstm-track-link[wpsstm-playable] .wpsstm-link-title', function(e) {
            e.preventDefault();
            var link = $(this).closest('wpsstm-track-link').get(0);
            var track = link.track;

            var linkIdx = Array.from(link.parentNode.children).indexOf(link);

            track.tracklist.play_queue_track(track.queueIdx,linkIdx);

        });

    }

    //reduce object for communication between JS & PHP
    to_ajax(){
        var link = this;
        var allowed = ['index','post_id'];
        var filtered = Object.keys(link)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
        obj[key] = link[key];
        return obj;
        }, {});
        
        //track
        filtered.track = link.track.to_ajax();

        return filtered;
    }
    
    trash_link(){
        var link = this;
        var $instances = link.get_instances();
        var action_links = $instances.find('.wpsstm-track-link-action-trash');

        var ajax_data = {
            action:         'wpsstm_trash_link',
            post_id:        link.post_id
        };

        action_links.addClass('action-loading');

        var ajax_request = $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })

        ajax_request.done(function(data){
            if (data.success === true){

                //skip current link as it was playing
                //TOUFIX URGENT
                if ( $(link).hasClass('link-playing') ){
                    link.debug('link was playing, skip it !');
                    link.debug(link);
                }

                ///
                $instances.remove();

            }else{
                action_links.addClass('action-error');
                console.log(data);
            }
        });

        ajax_request.fail(function(jqXHR, textStatus, errorThrown) {
            action_links.addClass('action-error');
        })

        ajax_request.always(function(data, textStatus, jqXHR) {
            action_links.removeClass('action-loading');
        })
    }

    play_link(){
        var link = this;
        var $instances = this.get_instances();
        var track = this.track;
        var $track_instances = track.get_instances();
        var success = $.Deferred();
        
        if( !link.playable ){
            success.reject('cannot play this link');
            return success.promise();
        }

        ///
        link.status = 'request';

        /*
        register new events
        */

        $(track.tracklist.current_media).on('loadeddata', function() {
            track.tracklist.debug('source loaded',link.src);
            track.tracklist.current_media.play();
        });

        $(track.tracklist.current_media).on('error', function(error) {
            link.status = '';
            success.reject(error);
        });

        $(track.tracklist.current_media).on('play', function() {
            link.status = 'playing';
            success.resolve();
        });

        $(track.tracklist.current_media).on('pause', function() {
            link.status = 'paused';
        });

        $(track.tracklist.current_media).on('ended', function() {

            track.tracklist.debug('media - ended');
            link.status = '';
            
            //Play next song if any
            track.tracklist.next_track_jump();
            
            
        });

        success.done(function(v) {
            track.tracklist.tracksHistory.push(track);
            $instances.attr('wpsstm-playable',true);
            //TOUFIX ajax --> +1 track play; user now playing...
        })
        success.fail(function() {
            $instances.attr('wpsstm-playable',false);
        })

        ////
        track.tracklist.current_media.setSrc(link.src);
        track.tracklist.current_media.load();
        
        ////

        return success.promise();

    }
    
    get_instances(){
        return $('wpsstm-track-link[data-wpsstm-link-id="'+this.post_id+'"]');
    }
    
    _sourceInitEvent(){
        var link = this;
        var track = link.track;

        var startTrack = function(){
            link.track.dispatchEvent(new CustomEvent('start'));
        }

        //start track event, fired only once
        $(link.track.tracklist.current_media).one('play', startTrack);
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