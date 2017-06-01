jQuery(document).ready(function($){
    
    $('.wpsstm-sources').shortenTable(1);
    
    $('.wpsstm-source-icon-add').live("click", function(event){
        event.preventDefault();
        
        var row = $(this).closest('.wpsstm-source');
        
        //auto source
        if ( row.hasClass('wpsstm-source-auto') ){
            row.removeClass('wpsstm-source-auto');
            row.find('input').prop("disabled", false);
            return;
        }
        
        var wrapper = row.parent();
        var rows_list = wrapper.find('.wpsstm-source');
        var row_blank = rows_list.first();
        
        var empty_row = null;

        rows_list.each(function() {
            var input_url = $(this).find('input:nth-of-type(2)');
            if ( !input_url.val() ){
                empty_row = $(this);
                return;
            }
        });

        if ( empty_row !== null ){
            empty_row.find('input:nth-of-type(2)').focus();
        }else{
            var new_row = row_blank.clone();
            new_row.removeClass('wpsstm-source-new ');
            new_row.insertAfter( row_blank );
            var row_blank_input = row_blank.find('input');
            row_blank.removeClass('wpsstm-source-auto');
            row_blank_input.prop("disabled", false);
            row_blank_input.val(''); //clear form
            row_blank_input.focus();
        }

    });
    
    $('.wpsstm-source-icon-delete').live("click", function(event){
        var row = $(this).closest('.wpsstm-source');
        
        if ( row.hasClass('wpsstm-source-new') ){
            row.find('input').val('');
        }else{
            row.remove();
        }
        
        
    });
})



