jQuery(document).ready(function($){
    //tabs
    $("#wpsstm-wizard-tabs").tabs();

    //regex
    $('.wpsstm-wizard-step-content a.wpsstm-wizard-selector-toggle-advanced').click(function(e) {
        e.preventDefault();
        var selector_row = $(this).parents('tr');
        var advanced_row = selector_row.find('.wpsstm-wizard-selector-advanced');
        advanced_row.toggleClass('active');
    });
    //default show regex
    $('.wpsstm-wizard-step-content a.wpsstm-wizard-selector-toggle-advanced').each(function() {
        var selector_row = $(this).parents('tr');
        var advanced_row = selector_row.find('.wpsstm-wizard-selector-advanced');
        var input = advanced_row.find('input');
        if ( (input.val() != "") || input.hasClass('required') ){
            advanced_row.addClass('active');
        }
    });
    
});

