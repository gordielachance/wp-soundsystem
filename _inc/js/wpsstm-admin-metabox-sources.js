jQuery(document).ready(function($){
    
    $('.wpsstm-sources').shortenTable(1);
    
    $('.wpsstm-source-icon-add').on("click", function(event){
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
            new_row.removeClass('wpsstm-source-new');
            new_row.insertAfter( row_blank );
            var row_blank_input = row_blank.find('input.wpsstm-source-url');
            row_blank.removeAttr('data-wpsstm-source-origin');
            row_blank_input.prop("disabled", false);
            row_blank_input.val(''); //clear form
            row_blank_input.focus();
        }

    });
    
    $('.wpsstm-source-icon-delete').on("click", function(event){
        var row = $(this).closest('.wpsstm-source');
        
        if ( row.hasClass('wpsstm-source-new') ){
            row.find('input.wpsstm-source-url').val('');
        }else{
            row.remove();
        }
        
        
    });
})



