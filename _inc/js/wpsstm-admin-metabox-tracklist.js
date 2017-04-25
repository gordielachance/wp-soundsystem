jQuery(function($){

    $(document).ready(function(){
        
        var wrapper = $('#wpsstm-tracklist');
        
        /* Row actions */
        //edit
        wrapper.find('.row-actions .edit a').live("click", function(event){
            event.preventDefault();
            var row = $(this).parents('tr');
            row.addClass('metabox-table-row-edit');
        });
        //save
        wrapper.find('.row-actions .save a').live("click", function(event){
            event.preventDefault();
            
            //get URI args
            var uri = new URI( $(this).attr('href') );
            var uri_args = uri.search(true);

            var row = $(this).parents('tr');
            
            var ajax_data = {
                'action':   'wpsstm_tracklist_save_row',
                'order':    row.find('.trackitem_order input').val(),
                'artist':   row.find('.trackitem_artist input').val(),
                'track':    row.find('.trackitem_track input').val(),
                'album':    row.find('.trackitem_album input').val(),
                'mbid':     row.find('.trackitem_mbid input').val(),
                'uri_args': uri_args
            };
            
            $.ajax({

                type: "post",
                url: wpsstmL10n.ajaxurl,
                data:ajax_data,
                dataType: 'json',
                beforeSend: function() {
                    row.addClass('loading');
                },
                success: function(data){
                    console.log(data);
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        row.html( data.output );
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status);
                    console.log(thrownError);
                },
                complete: function() {
                    row.removeClass('metabox-table-row-edit'); //remove edit class
                    row.removeClass('loading');
                }
            })

            
        });
        //delete
        wrapper.find('.row-actions .delete a').live("click", function(event){
            event.preventDefault();
            var row = $(this).parents('tr');
            row.remove();
            reorder_tracks();
        });

        wrapper.find('.metabox-table-row-new').hide();

        //add new row
        wrapper.find(".add-tracklist-track").click(function(event){

            event.preventDefault();
            
            var the_list = wrapper.find('#the-list');
            var rows_list = the_list.find("tr:not(.no-items)"); //all list items
            var row_blank = the_list.find("tr:first-child"); //item to clone
            
            var row_filled_last = row_blank;
            var rows_filled = rows_list.not(row_blank); //existing tracks
            
            if (rows_filled.length){
                row_filled_last = rows_filled.last(); //last existing track
                
                //check last row is filled
                if (row_filled_last.length > 0) {
                    var first_row_artist_input = row_filled_last.find('.column-trackitem_artist input');
                    var first_row_track_input = row_filled_last.find('.column-trackitem_track input');
                    //artist focus
                    if( first_row_artist_input.val().length === 0 ) {
                        first_row_artist_input.focus();
                        return;
                    }
                    //track focus
                    if( first_row_track_input.val().length === 0 ) {
                        first_row_track_input.focus();
                        return;
                    }
                }
                
            }

            

            /*
            clone blank row, insert, focus
            */
            var new_row = row_blank.clone();
            
            new_row.find('input[type="text"]').val(''); //clear form

            //increment input name prefixes
            new_row.html(function(index,html){
                var pattern = 'wpsstm[tracklist][tracks][0]';
                var replaceby = 'wpsstm[tracklist][tracks]['+rows_list.length+']';
                return html.split(pattern).join(replaceby);
            });
 
            //add line
            new_row.insertAfter( row_filled_last );
            new_row.show();
            
            //focus input
            new_row.find('input').first().focus();
            
            //check checkbox & set 'Save' action
            new_row.find('.check-column input[type="checkbox"]').prop('checked', true);
            $('#post-bkmarks-bulk-action-selector-top').val("save");
            
            //reorder
            reorder_tracks();

        });

        // sort rows
        $( wrapper ).find( '#the-list' ).sortable({
            handle: '.metabox-table-row-draghandle',

            update: function(event, ui) {
                wpsstm_tracklist_reorder();
                wpsstm_tracklist_order_update();
            }
        });
    })
})

function wpsstm_tracklist_reorder(){
    var wrapper = jQuery('#wpsstm-subtracks-list');
    var all_rows = wrapper.find( '#the-list tr' );
    jQuery.each( all_rows, function( key, value ) {
      var order_input = jQuery(this).find('.column-trackitem_order input');
        order_input.val(key);
    });
}

function wpsstm_tracklist_order_update(){
    var wrapper = jQuery('#wpsstm-subtracks-list');
    var all_rows = wrapper.find( '#the-list tr' );
    var new_order = [];
    var subtrack_parent_id = 0; //TO FIX
    
    jQuery.each( all_rows, function( key, value ) {
        
        var subtrack_id = jQuery(this).find('input[type="hidden"]').val();
        if (subtrack_id != 0) {
            new_order.push(subtrack_id);
        }
    });
    
    //ajax update order
    var table = wrapper.find('table.wp-list-table');
    
    var ajax_data = {
        'action'            : 'wpsstm_tracklist_update_order',
        'post_id'           : wrapper.attr('data-wpsstm-tracklist-id'),
        'subtracks_order'   : new_order
    };

    
    jQuery.ajax({
        type: "post",
        url: wpsstmL10n.ajaxurl,
        data:ajax_data,
        dataType: 'json',
        beforeSend: function() {
            table.addClass('loading');
        },
        success: function(data){
            if (data.success === false) {
                console.log(data);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        },
        complete: function() {
            table.removeClass('loading');
        }
    })

}



