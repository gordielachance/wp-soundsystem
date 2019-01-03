var $ = jQuery.noConflict();

$( document ).ready(function() {
    /*
    advanced selectors
    */

    //should we show it by default ?
    $('.wpsstm-wizard-selector-advanced').each(function() {
        var advanced_block = $(this);
        var inputs_filled = advanced_block.find('input').filter(function () {
            return !!this.value;
        });
        if ( inputs_filled.length > 0 ){
            $(this).addClass('active');
        }
    });

    $('a.wpsstm-wizard-selector-toggle-advanced').click(function(e) {
        e.preventDefault();
        var selector_row = $(this).parents('.wpsstm-wizard-row');
        var advanced_row = selector_row.find('.wpsstm-wizard-selector-advanced');
        advanced_row.toggleClass('active');
    });
});
