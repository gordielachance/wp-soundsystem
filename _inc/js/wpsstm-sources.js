class WpsstmTrackSource {
    constructor(source_html,track) {
        
        this.source_el =        $([]);
        this.track =            (track !== undefined) ? track : new WpsstmTrack();
        
        this.index =            undefined;
        this.post_id =          undefined;
        this.src =              undefined;
        this.type =             undefined;
        this.can_play =         undefined;
        this.duration =         undefined;
        
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
        var source_action_links = self.source_el.find('.wpsstm-source-action-trash a');

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

                source_obj.can_play = false;

                //skip current source as it was playibg
                if ( self.source_el.hasClass('source-playing') ){
                    source_obj.debug('source was playing, skip it !');
                    source_obj.debug(source_obj);
                }

                ///
                self.source_el.remove();

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

(function($){

    $(document).on( "wpsstmTrackSourcesDomReady", function( event, track_obj ) {

        //sources manager
        track_obj.track_el.find('.wpsstm-track-sources').each(function() {
            var sources_container = $(this);
            var sources_list_el = sources_container.find('.wpsstm-track-sources-list');

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

                    var reordered = WpsstmTrack.update_sources_order(track_obj.post_id,sourceOrder); //TOUFIX bad logic

                    reordered.always(function() {
                        sources_list_el.removeClass('wpsstm-freeze');
                    })

                }
            });

        });

    });

    $(document).on( "wpsstmTrackSingleSourceDomReady", function( event, source_obj ) {

        //delete source
        source_obj.source_el.find('.wpsstm-source-action-trash a').click(function(e) {
            e.preventDefault();
            source_obj.trash_source();
        });

    });
    
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
    
})(jQuery);


