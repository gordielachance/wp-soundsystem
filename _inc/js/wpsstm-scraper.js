jQuery(document).ready(function($){
    //tabs
    $("#wizard-wrapper.wizard-wrapper-advanced #wpsstm-wizard-tabs").tabs();

    /*
    advanced selectors
    */
    
    //should we show it by default ?
    $('.wpsstm-wizard-step-content .wpsstm-wizard-selector-advanced').each(function() {
        var advanced_block = $(this);
        var inputs_filled = advanced_block.find('input').filter(function () {
            return !!this.value;
        });
        if ( inputs_filled.length > 0 ){
            $(this).addClass('active');
        }
    });
    
    $('.wpsstm-wizard-step-content a.wpsstm-wizard-selector-toggle-advanced').click(function(e) {
        e.preventDefault();
        var selector_row = $(this).parents('tr');
        var advanced_row = selector_row.find('.wpsstm-wizard-selector-advanced');
        advanced_row.toggleClass('active');
    });
    
});

