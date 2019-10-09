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
                
            break;

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
        $(link.track.tracklist.current_media).off(); //remove old events
        link.status = 'request';
        link.track.tracklist.current_link = link;
        $(document).trigger( "wpsstmSourceInit",[link] ); //custom event

        /*
        register new events
        */

        $(track.tracklist.current_media).on('loadeddata', function() {
            track.tracklist.debug('source loaded',link.src);
            track.tracklist.current_media.play();
        });

        $(track.tracklist.current_media).on('error', function(error) {
            link.playable = false;
            link.status = '';
            track.status = '';
            success.reject(error);
        });

        $(track.tracklist.current_media).on('play', function() {
            link.status = 'playing';
            track.status = 'playing';
            success.resolve();
        });

        $(track.tracklist.current_media).on('pause', function() {
            link.status = 'paused';
            track.status = 'paused';
        });

        $(track.tracklist.current_media).on('ended', function() {

            track.tracklist.debug('media - ended');
            link.status = '';
            
            //Play next song if any
            track.tracklist.next_track_jump();
            
            
        });

        success.done(function(v) {
            track.tracklist.tracksHistory.push(track);
            link.playable = true;
            //TOUFIX ajax --> +1 track play; user now playing...
        })
        success.fail(function() {
            link.playable = false;
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