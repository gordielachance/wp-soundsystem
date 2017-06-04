jQuery(function($){

    $(document).ready(function(){
        
        var wrapper = $('#wpsstm-tracklist');
        
        var tables_el = wrapper.find('table');
        tables_el.tableToggleColumns();
        
        /* Row actions */
        //edit
        wrapper.find('.row-actions .edit a').on("click", function(event){
            event.preventDefault();
            var row = $(this).parents('tr');
            row.addClass('metabox-table-row-edit');
        });
        //save
        wrapper.find('.row-actions .save a').on("click", function(event){
            event.preventDefault();
            wpsstm_tracklist_row_action(this);
        });
        //remove
        wrapper.find('.row-actions .remove a').on("click", function(event){
            event.preventDefault();
            wpsstm_tracklist_row_action(this);
        });
        //delete
        wrapper.find('.row-actions .delete a').on("click", function(event){
            event.preventDefault();
            wpsstm_tracklist_row_action(this);
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
            wpsstm_tracklist_reorder();

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

    $.fn.tableToggleColumns = function(attr = {}) {

        if( (typeof attr.ignore_columns === typeof undefined) || !attr.ignore_columns ) {
            attr.ignore_columns = [];
        }
        
        if( (typeof attr.unchecked_columns === typeof undefined) || !attr.unchecked_columns ) {
            attr.unchecked_columns = [];
        }
        
        attr.ignore_columns = ['#cb','#trackitem_action'];
        attr.unchecked_columns = ['#trackitem_album','#trackitem_mbid','#trackitem_sources'];

        this.each(function() {

            var $table = $(this);
            var $thead = $(this).find('thead');
            if ($thead.length == 0) return;
            
            var $thead_driver = $thead.clone();
            
            //map columns keys
            $thead_driver.find('tr > *').each(function( key, td ) {
                $(td).attr('data-toggle-column-key',key);
            });
            
            //ignored columns array
            var $ignore_columns = [];
            $(attr.ignore_columns).each(function( key, selector ) {
                var $ignore_column = $thead_driver.find(selector);
                if ($ignore_column.length > 0) $ignore_columns.push($ignore_column);
            });
            
            //unchecked columns array
            var $unchecked_columns = [];
            $(attr.unchecked_columns).each(function( key, selector ) {
                var $unchecked_column = $thead_driver.find(selector);
                if ($unchecked_column.length > 0) $unchecked_columns.push($unchecked_column);
            });
            
            //remove ignore columns
            $($ignore_columns).each(function( key, el ) {
                el.remove();
            });
            
            //remove columns without title
            $thead_driver.find('th').each(function( key, th ) {
                var content = $(th).html();
                if (!content) $(th).remove();
            });

            //add checkboxes
            $thead_driver.find('th').each(function( key, th ) {
                var column_key = Number( $(th).attr('data-toggle-column-key') ) + 1;
                var $driver_checkbox = $('<input type="checkbox" />');
                $driver_checkbox.prop('checked',true);
                $(th).prepend($driver_checkbox);
                
                $driver_checkbox.click(function(e) {
                    var $table_column = $table.find('tr >*:nth-child('+column_key+')');
                    $table_column.toggle();
                });
            });
            
            //unchecked columns
            $($unchecked_columns).each(function( key, th ) {
                var column_key = Number( $(th).attr('data-toggle-column-key') ) + 1;
                var $driver_checkbox = $(th).find('input');
                $driver_checkbox.prop('checked',false);
                var $table_column = $table.find('tr >*:nth-child('+column_key+')');
                $table_column.hide();
            });
            
            
            
            $table.addClass('toggle-columns');
            
            var toggle_driver = $('<table></table>');
            toggle_driver.attr({
                id: 'toggle-columns-driver'
            });
            
            toggle_driver.html($thead_driver);
            toggle_driver.insertBefore( $table );
            
        });


    }; 
    
})

function wpsstm_tracklist_row_action(row_action_link){
    var link = jQuery(row_action_link);
    var row = link.parents('tr');

    var track_id = row.attr('data-wpsstm-track-id');
    var tracklist_id = row.closest('[data-wpsstm-tracklist-id]').attr('data-wpsstm-tracklist-id');
    var track_action = link.attr('data-wpsstm-subtrack-action');

    var track_sources = [];
    row.find('.wpsstm-sources input.wpsstm-source-url:not(:disabled)').each(function() {
        var source_url = $(this).val();
        var source_title = $(this).siblings('.wpsstm-source-title').val();
        if (!source_url) return true; //continue
        var source = {title:source_title,url:source_url};
        track_sources.push(source);
    });
    
    if ( track_sources.length == 0 ) track_sources = null; // we need this or arg will not be received by PHP

    var track = {
        'post_id':          track_id, 
        'artist':           row.find('.trackitem_artist input').val(),
        'title':            row.find('.trackitem_track input').val(),
        'album':            row.find('.trackitem_album input').val(),
        'mbid':             row.find('.trackitem_mbid input').val(),
        'sources':          track_sources,
        'tracklist_id':     tracklist_id,
    };

    var ajax_data = {
        'action':           'wpsstm_tracklist_row_action',
        'track_action':     track_action,
        'track':            track,
        'track_order':      row.find('.trackitem_order input').val(),
        
    };
    
    console.log(ajax_data);

    jQuery.ajax({

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
                if ( data.new_html ){
                    row.html( data.new_html );
                }
                if ( (track_action == 'remove') || (track_action == 'delete') ){
                    row.remove();
                    wpsstm_tracklist_reorder();
                }

            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        },
        complete: function() {
            row.removeClass('metabox-table-row-edit');
            row.removeClass('loading');
        }
    })
}

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
            console.log(data);
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



