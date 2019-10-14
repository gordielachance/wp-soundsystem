var $ = jQuery.noConflict();

class WpsstmLink extends HTMLElement{
    constructor() {
        super(); //required to be first
        
        this.index =            undefined;
        this.post_id =          undefined;
        this.src =              undefined;
        this.type =             undefined;

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

        //link.debug(`Attribute ${attrName} changed from ${oldVal} to ${newVal}`);

        switch (attrName) {
        }
        
        
    }
    adoptedCallback(){
        /*
        The custom element has been moved into a new document (e.g. someone called document.adoptNode(el)).
        */
    }
    
    static get observedAttributes() {
        return [];
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
        link.index =            Number($(link).attr('data-wpsstm-link-idx'));
        link.post_id =          Number($(link).attr('data-wpsstm-link-id'));
        link.src =              $(link).attr('data-wpsstm-stream-src');
        link.type =             $(link).attr('data-wpsstm-stream-type');

        //delete link
        $(link).on('click', '.wpsstm-track-link-action-trash', function(e) {
            e.preventDefault();
            link.trash_link();
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