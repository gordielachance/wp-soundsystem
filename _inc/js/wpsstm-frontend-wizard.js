var $ = jQuery.noConflict();

$( document ).ready(function() {

    var shortcut_titles = $('#wpsstm-wizard-shortcuts li');
    var clickable_shortcuts = shortcut_titles.filter(function( index ) {
        return $( ".wpsstm-bang-desc", this ).length === 1;
    });
    
    console.log(shortcut_titles);
    console.log(clickable_shortcuts);
    
    clickable_shortcuts.each(function() {
        var title_el = $(this).find('label');
        var desc_el = $(this).find('.wpsstm-bang-desc');
        title_el.addClass('wpsstm-can-click');
        desc_el.hide();
    });
    
    
    clickable_shortcuts.on( "click", function(e) {
        var desc_el = $(this).find('.wpsstm-bang-desc');
        desc_el.toggle();
    });

});
