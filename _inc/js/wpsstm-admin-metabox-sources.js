jQuery(document).ready(function($){
    $('.wpsstm-source-icon-add').live("click", function(event){
        event.preventDefault();
        
        var wrapper = $(this).closest('.wpsstm-source').parent();
        var rows_list = wrapper.find('.wpsstm-source');
        var row_blank = rows_list.first();
        
        var empty_row = null;

        rows_list.each(function() {
            var input = $(this).find('input');
            if ( !input.val() ){
                empty_row = $(this);
                return;
            }
        });

        if ( empty_row !== null ){
            empty_row.find('input').focus();
        }else{
            var new_row = row_blank.clone();
            new_row.removeClass('wpsstm-source-blank');
            new_row.insertAfter( row_blank );
            row_blank.find('input').val(''); //clear form
            row_blank.find('input').focus();
        }

    });
    
    $('.wpsstm-source-icon-delete').live("click", function(event){
        var row = $(this).closest('.wpsstm-source');
        
        if ( row.hasClass('wpsstm-source-blank') ){
            row.find('input').val('');
        }else{
            row.remove();
        }
        
        
    });
})



