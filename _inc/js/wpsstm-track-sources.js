jQuery(document).ready(function($){
    
    $(document).on( "wpsstmTrackSourcesDomReady", function( event, track_obj ) {
        var track_el = track_obj.track_el;
        
        var sources_links = $(track_el).filter('.active').find('.wpsstm-track-sources-list a');

        //click on source link
        sources_links.click(function(e) {
            e.preventDefault();
            
            alert("toto");
            
            var source_el = $(this).closest('li');
            var source_idx = Number( source_el.attr('data-wpsstm-source-idx') );
            var source_obj = track_obj.get_track_source(source_idx);
            source_obj.select_player_source();
        });

        /*
        Single Track Playlists manager popup
        */

    });
    
    //suggest sources
    $(document).on("click", 'input#wpsstm-suggest-sources-bt', function(e){
        e.preventDefault();
        
        var bt = $(this);
        var sources_auto_edit_list = bt.closest('#wpsstm-sources-edit-list-auto');
        var popup_section = bt.closest('#tab-content-sources');
        var popup = bt.closest('.hentry');

        //get track obj from HTML
        var track_el = popup.find('[itemprop="track"]').first();
        var track_obj = new WpsstmTrack(track_el);

        var track = track_obj.build_request_obj();

        var ajax_data = {
            action:     'wpsstm_suggest_editable_sources',
            field_name: bt.attr('data-wpsstm-autosources-field-name'), //TO FIX has been removed
            post_id:    track_obj.post_id
        };
        
        var existing_rows_count = $(popup_section).find('.wpsstm-source').length;

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                popup_section.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    var $rows = $(data.new_html);
                    
                    //increment input names
                    $.each($rows, function( row_i, row ) {
                        var inputs = $(row).find('input');
                        $.each(inputs, function( i, input ) {
                            var input_name = $(input).attr('name');
                            var new_input_index = existing_rows_count + row_i;
                            var new_input_name = input_name.replace(/\[[\d+]\]/, "[" + new_input_index + "]");
                            $(input).attr('name',new_input_name);
                            
                        });
                    });
                    
                    sources_auto_edit_list.html($rows);
                    sources_auto_edit_list.toggleChildren({
                        childrenShowCount:  true,
                        childrenMax:        3,
                        moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                        lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
                    });
                }
            },
            complete: function() {
                popup_section.removeClass('loading');
            }
        });
    });

    //add source
    $(document).on("click", '.wpsstm-source-icon-add', function(){
        event.preventDefault();
        
        var row = $(this).closest('.wpsstm-source');
        //auto source
        if ( row.attr('data-wpsstm-source-origin') == 'auto' ){
            row.removeAttr('data-wpsstm-source-origin');
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
            row_blank.removeAttr('data-wpsstm-source-origin');
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
    $('.wpsstm-sources-edit-list').toggleTracklist();

})

class WpsstmTrackSource {
    constructor(source_html,track) {

        var self = this;
        self.tracklist_idx = track.tracklist_idx;
        self.track_idx = track.track_idx;
        self.source_idx = track.sources.length;
        $(source_html).attr('data-wpsstm-source-idx',this.source_idx);
        
        self.src =    $(source_html).attr('data-wpsstm-source-src');
        self.type =    $(source_html).attr('data-wpsstm-source-type');
        self.can_play_source = true;
        
        //self.debug("new WpsstmTrackSource");

    }
    
    debug(msg){
        var prefix = "WpsstmTrackSource #" + this.source_idx + " in playlist #"+ this.tracklist_idx +"; track #"+ this.track_idx +": ";
        wpsstm_debug(msg,prefix);
    }

    get_source_li_el(ancestor){
        
        var self = this;
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(self.tracklist_idx,self.track_idx);
        var track_el = track_obj.get_track_instances(ancestor);
        return $(track_el).find('[data-wpsstm-source-idx="'+self.source_idx+'"]');
    }
    
    /*
    get_player_source_el(){
        return $(bottom_el).find('audio source').eq(this.source_idx).get(0);
    }
    */

    select_player_source(){
        var self = this;
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(self.tracklist_idx,self.track_idx);

        var track_sources_count = track_obj.sources.length;
        if ( track_sources_count <= 1 ) return;
        
        self.debug("select_player_source()");

        var player_source_el = self.get_source_li_el(bottom_el);
        var ul_el = player_source_el.closest('ul');

        var sources_list = player_source_el.closest('ul');
        var sources_list_wrapper = sources_list.closest('td.trackitem_sources');

        if ( !player_source_el.hasClass('wpsstm-active-source') ){ //source switch

            var lis_el = player_source_el.closest('ul').find('li');
            lis_el.removeClass('wpsstm-active-source');
            player_source_el.addClass('wpsstm-active-source');

            track_obj.set_track_source(self.source_idx);
        }

    }

}


