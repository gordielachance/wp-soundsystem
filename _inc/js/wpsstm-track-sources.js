jQuery(document).ready(function($){

    $(document).on( "wpsstmTrackSourceDomReady", function( event, source_obj ) {

        //click on source trigger
        source_obj.source_el.find('.wpsstm-source-title').click(function(e) {
            e.preventDefault();
            source_obj.play_source();
        });
        
        //delete source
        source_obj.source_el.find('.wpsstm-source-delete-action').click(function(e) {
            e.preventDefault();
            
            var source_el = $(this).parents();
            var promise = source_obj.delete_source();
            
            promise.done(function(data) {
                var source_instances = source_obj.get_source_instances();
                source_instances.remove();
                
                if ( source_el.hasClass('wpsstm-active-source') ){
                    //TO FIX TO DO skip to next source ? what if it is the last one ?
                }
                
            })
            
        });
        

    });
    
    //suggest sources
    $(document).on("click", 'input#wpsstm-suggest-sources-bt', function(e){
        e.preventDefault();

        var bt = $(this);
        var track = bt.closest('[data-wpsstm-track-id]');
        var track_id = track.attr('data-wpsstm-track-id');
        var form = bt.closest('form');
        var sources_wrapper = $(form).find('.wpsstm-sources-edit-list-user');

        var ajax_data = {
            action:     'wpsstm_autosources_form',
            post_id:    track_id //TO FIX we should send a track_obj here (see track_obj.to_ajax())
        };

        var existing_rows_count = $(form).find('.wpsstm-source').length;

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(form).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    
                    bt.remove();
                    
                    if (data.new_html){
                        var $rows = $(data.new_html);
                        $(sources_wrapper).append($rows);
                        $(sources_wrapper).toggleChildren({
                            childrenShowCount:  true,
                            childrenMax:        4,
                            moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                            lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
                        });
                    }

                }
            },
            complete: function() {
                $(form).removeClass('loading');
            }
        });
    });

    //add source
    $(document).on("click", '.wpsstm-source-icon-add', function(){
        event.preventDefault();
        
        var row = $(this).closest('.wpsstm-source');
        //auto source
        if ( Number(row.attr('data-wpsstm-autosource')) == 1 ){
            row.attr('data-wpsstm-autosource', '0');
            row.find('input').prop("disabled", false);
            row.removeClass('wpsstm-source-auto');
            return;
        }
        
        var wrapper = row.parent();
        var rows_list = wrapper.find('.wpsstm-source');
        var row_blank = rows_list.first();
        
        var empty_row = null;

        rows_list.each(function() {
            var input_url = $(this).find('input.wpsstm-editable-source-url');
            if ( !input_url.val() ){
                empty_row = $(this);
                return;
            }
        });

        if ( empty_row !== null ){
            empty_row.find('input.wpsstm-editable-source-url').focus();
        }else{
            var new_row = row_blank.clone();
            new_row.insertAfter( row_blank );
            var row_blank_input = row_blank.find('input.wpsstm-editable-source-url');
            row_blank.attr('data-wpsstm-autosource', '0');
            row_blank_input.prop("disabled", false);
            row_blank_input.val(''); //clear form
            row_blank_input.focus();
        }

    });
    
    //delete source
    $(document).on("click", '.wpsstm-source-icon-delete', function(){
        var wrapper = $(this).closest('.wpsstm-manage-sources-wrapper');
        var first_row = wrapper.find('.wpsstm-source').first();
        var row = $(this).closest('.wpsstm-source');
        
        if ( row.is(first_row) ){
            row.find('input.wpsstm-editable-source-url').val('');
        }else{
            row.remove();
        }
        
        
    });
    
    //submit
    /*
    $(document).on("click", 'input#wpsstm-update-sources-bt', function(e){
        e.preventDefault();
        var bt = $(this);
        console.log(bt);
    });
    */
    
    //toggle expand
    $('.wpsstm-sources-edit-list').toggleChildren();

})

class WpsstmTrackSource {
    constructor(source_html,track) {

        var self =              this;
        self.source_el =        $(source_html);
        self.tracklist_idx =    track.tracklist_idx;
        self.track_idx =        track.track_idx;
        self.post_id =          Number(self.source_el.attr('data-wpsstm-source-id'));
        self.source_idx =       Number(self.source_el.attr('data-wpsstm-source-idx'));
        self.src =              self.source_el.attr('data-wpsstm-source-src');
        self.type =             self.source_el.attr('data-wpsstm-source-type');
        self.source_can_play = true;
        
        //self.debug("new WpsstmTrackSource");

    }
    
    debug(msg){
        var prefix = "WpsstmTrackSource #" + this.source_idx + " in playlist #"+ this.tracklist_idx +"; track #"+ this.track_idx +": ";
        wpsstm_debug(msg,prefix);
    }

    get_track_el(){
        var self = this;
        return self.track_el.closest('[data-wpsstm-track-idx="'+self.track_idx+'"]');
    }

    get_source_instances(ancestor){
        var self = this;
        var selector = '[data-wpsstm-tracklist-idx="'+self.tracklist_idx+'"] [itemprop="track"][data-wpsstm-track-idx="'+self.track_idx+'"] [data-wpsstm-source-idx="'+self.source_idx+'"]';
        
        if (ancestor !== undefined){
            return $(ancestor).find(selector);
        }else{
            return $(selector);
        }
    }

    play_source(){
        var self = this;
        wpsstm_page_player.play_tracklist(self.tracklist_idx,self.track_idx,self.source_idx);
    }
    
    delete_source(){
        
        var self = this;
        var deferredObject = $.Deferred();
        var source_instances = self.get_source_instances();
        
        var ajax_data = {
            action:         'wpsstm_delete_source',
            post_id:        self.post_id
        };
        
        source_instances.addClass('loading');

        var ajax_request = $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })
        
        ajax_request.done(function(data){
            if (data.success === true){
                deferredObject.resolve();
            }else{
                console.log(data);
                deferredObject.reject(data.message);
            }
        });

        ajax_request.fail(function(jqXHR, textStatus, errorThrown) {
            deferredObject.reject();
        })

        ajax_request.always(function(data, textStatus, jqXHR) {
            source_instances.removeClass('loading');
        })
        
        return deferredObject.promise();
        
    }
}


