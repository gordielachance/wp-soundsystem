var $ = jQuery.noConflict();

$( document ).ready(function() {

    var shortcut_titles = $('#wpsstm-wizard-shortcuts li label');
    var clickable_shortcuts = shortcut_titles.filter(function( index ) {
        var container = $(this).parents('li');
        return $( ".wpsstm-bang-desc", container ).length === 1;
    });
    
    console.log(shortcut_titles);
    console.log(clickable_shortcuts);
    
    clickable_shortcuts.each(function() {
        var container = $(this).parents('li');
        var desc_el = $(this).find('.wpsstm-bang-desc');
        $(this).addClass('wpsstm-can-click');
    });
    
    
    clickable_shortcuts.on( "click", function(e) {
        var container = $(this).parents('li');
        var desc_el = container.find('.wpsstm-bang-desc');
        desc_el.toggleClass('expanded');
    });

});
