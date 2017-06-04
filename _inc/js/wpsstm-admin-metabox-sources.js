jQuery(document).ready(function($){
    
    $('.wpsstm-suggest-sources-link').on("click", function(event){
        event.preventDefault();
        
        var link = $(this);
        var sources_section = $(this).closest('.wpsstm-sources-section');
        var sources_wrapper = $(this).closest('.wpsstm-manage-sources-wrapper');
        
        var track = {
            artist: link.closest('[data-wpsstm-track-artist]').attr('data-wpsstm-track-artist'),
            title:  link.closest('[data-wpsstm-track-title]').attr('data-wpsstm-track-title'),
            album:  link.closest('[data-wpsstm-track-album]').attr('data-wpsstm-track-album'),
        }
        
        var ajax_data = {
            action: 'wpsstm_suggest_editable_sources',
            field_name: link.attr('data-wpsstm-autosources-field-name'),
            track:  track
        };
        
        var existing_rows_count = $(sources_wrapper).find('.wpsstm-source').length;

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                link.addClass('loading');
            },
            success: function(data){
                console.log(data);
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
                    
                    sources_section.html($rows);
                }
            },
            complete: function() {
                link.removeClass('loading');
            }
        });
    });

    $(document).on("click", '.wpsstm-source-icon-add', function(){
        event.preventDefault();
        
        var row = $(this).closest('.wpsstm-source');
        //auto source
        if ( row.attr('data-wpsstm-source-origin') == 'auto' ){
            row.removeAttr('data-wpsstm-source-origin');
            row.find('input').prop("disabled", false);
            return;
        }
        
        var wrapper = row.parent();
        var rows_list = wrapper.find('.wpsstm-source');
        var row_blank = rows_list.first();
        
        var empty_row = null;

        rows_list.each(function() {
            var input_url = $(this).find('input.wpsstm-source-url');
            if ( !input_url.val() ){
                empty_row = $(this);
                return;
            }
        });

        if ( empty_row !== null ){
            empty_row.find('input.wpsstm-source-url').focus();
        }else{
            var new_row = row_blank.clone();
            new_row.insertAfter( row_blank );
            var row_blank_input = row_blank.find('input.wpsstm-source-url');
            row_blank.removeAttr('data-wpsstm-source-origin');
            row_blank_input.prop("disabled", false);
            row_blank_input.val(''); //clear form
            row_blank_input.focus();
        }

    });
    
    $(document).on("click", '.wpsstm-source-icon-delete', function(){
        var wrapper = $(this).closest('.wpsstm-manage-sources-wrapper');
        var first_row = wrapper.find('.wpsstm-source').first();
        var row = $(this).closest('.wpsstm-source');
        
        if ( row.is(first_row) ){
            row.find('input.wpsstm-source-url').val('');
        }else{
            row.remove();
        }
        
        
    });
})



