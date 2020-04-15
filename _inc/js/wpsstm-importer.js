var $ = jQuery.noConflict();

$( document ).ready(function() {

    var $feedUrlHelpContent = $("#wpsstm-importers");
    var $feedUrlHelpHandle = $("#feed-url-help");
    $feedUrlHelpContent.hide();

    $feedUrlHelpHandle.click(function(e) {
      e.preventDefault();
      $feedUrlHelpContent.toggle();
    });

    /*
    advanced selectors
    */

    //should we show it by default ?
    $('.wpsstm-importer-selector-advanced').each(function() {
        var advanced_block = $(this);
        var inputs_filled = advanced_block.find('input').filter(function (index) {
            return !!this.value;
        });
        if ( inputs_filled.length > 0 ){
            $(this).addClass('active');
        }
    });

    $('a.wpsstm-importer-selector-toggle-advanced').click(function(e) {
        e.preventDefault();
        var selector_row = $(this).parents('.wpsstm-importer-row');
        var advanced_row = selector_row.find('.wpsstm-importer-selector-advanced');
        advanced_row.toggleClass('active');
    });

});
