var $ = jQuery.noConflict();

class WpsstmSource extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.track =            $(this).parents('wpsstm-track').get(0);
        this.index =            Number($(this).attr('data-wpsstm-source-idx'));
        this.post_id =          Number($(this).attr('data-wpsstm-source-id'));
        this.src =              $(this).attr('data-wpsstm-source-src');
        this.type =             $(this).attr('data-wpsstm-source-type');
        this.can_play =         ( Boolean(this.type) && Boolean(this.src) );
        this.duration =         undefined;

        if (!this.can_play){
            $(this).addClass('source-error');
        }

        // Setup a click listener on <wpsstm-tracklist> itself.
        this.addEventListener('click', e => {
        });
    }
    connectedCallback(){
        console.log("SOURCE CONNECTED!");
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
        var prefix = "WpsstmTrack #" + this.track.position+" - WpsstmTrackSource #" + this.index;
        wpsstm_debug(msg,prefix);
    }
    
    get_instances(){
        var self = this;
        return $(document).find('wpsstm-source[data-wpsstm-source-id="'+self.post_id+'"]');
    }
    
    render(){
        var self = this;
        //delete source
        $(self).find('.wpsstm-source-action-trash a').click(function(e) {
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