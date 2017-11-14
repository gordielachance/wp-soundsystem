jQuery(document).ready(function($){

    //wizard URL fill
    $('[data-wpsstm-wizard-preview]').click(function(e) {
        e.preventDefault();
        var input_el = $('#wpsstm-wizard-input');
        var new_value = $(this).attr('data-wpsstm-wizard-preview');
        input_el.val(new_value);
        
        $('html, body').animate({
            scrollTop: input_el.offset().top - ( $(window).height() / 3) //not at the very top
        }, 500);

    });
    
    //tabs
    $("#wizard-wrapper #wpsstm-advanced-wizard-sections").tabs();

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

