var $ = jQuery.noConflict();

$( document ).ready(function() {
    
    /* Backend */
    $("#wpsstm-metabox-importer").tabs();
    
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

//load debug
$(document).on('click', '.wpsstm-debug-log-bt', function(e) {
    var bt = $(this);
    var container = bt.parents('#wpsstm-importer');
    var output = container.find('#wpsstm-debug-json');
    var ajax_data = {
        action:         'wpsstm_get_importer_debug',
        tracklist_id:   bt.get(0).getAttribute('data-wpsstm-tracklist-id')
    };
    
    bt.addClass('wpsstm-loading');
    output.text('');

    var request = $.ajax({
        type:       "post",
        url:        wpsstmL10n.ajaxurl,
        data:       ajax_data,
        dataType:   'json',
    })

    request.done(function(data) {

        if ( data.success && data.json ){
            var json = JSON.parse(data.json);
            output.jsonViewer(json,{collapsed: true,rootCollapsable:false});
        }else{
            console.log(data);
        }

    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        wpsstm_debug(errorThrown,"get debug request failed");

    })
    .always(function() {
        bt.removeClass('wpsstm-loading');
    });
});