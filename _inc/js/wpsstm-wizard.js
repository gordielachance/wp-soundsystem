jQuery(document).ready(function($){
    
    /*
    helpers
    */
    
    //user stations
    var user_stations_input = $('#wpsstm-helper-lastfm-user-stations').find('input');

    user_stations_input.change(function() {
        
        var username = $(this).val();
        var wrapper = $(this).parents('.wpsstm-helper');

        wrapper.toggleClass('wpsstm-helper-ready',username);
        
        var recommandations_el = $('#wpsstm-helper-lastfm-user-stations-recommendations a');
        var library_el = $('#wpsstm-helper-lastfm-user-stations-library a');
        var mix_el = $('#wpsstm-helper-lastfm-user-stations-mix a');
        
        recommandations_el.attr('data-wpsstm-wizard-preview','lastfm:user:'+username+':station:recommended');
        library_el.attr('data-wpsstm-wizard-preview','lastfm:user:'+username+':station:library');
        mix_el.attr('data-wpsstm-wizard-preview','lastfm:user:'+username+':station:mix');
    });
    user_stations_input.trigger('change');// at init
    

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

