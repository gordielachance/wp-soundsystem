jQuery(document).ready(function($){
    //tabs
    $("#wpsstm-wizard-tabs").tabs();

    //regex
    $('.wpsstm-wizard-step-content a.regex-link').click(function(e) {
        e.preventDefault();
        var selector_row = $(this).parents('tr');
        var regex_row = selector_row.find('.regex-row');
        regex_row.toggleClass('active');
    });
    //default show regex
    $('.wpsstm-wizard-step-content a.regex-link').each(function() {
        var selector_row = $(this).parents('tr');
        var regex_row = selector_row.find('.regex-row');
        var input = regex_row.find('input');
        if ( (input.val() != "") || input.hasClass('required') ){
            regex_row.addClass('active');
        }
    });
    
});

