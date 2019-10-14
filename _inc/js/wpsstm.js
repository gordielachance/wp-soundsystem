//https://developers.google.com/web/fundamentals/web-components/customelements

var $ = jQuery.noConflict();

//json viewer
$( ".wpsstm-json-input" ).wpsstmJsonViewer();

//datas metabox
$('.wpsstm-data-metabox').each(function( index ) {
    var lookup_bt = $(this).find('a.wpsstm-data-id-lookup-bt');
    var input_id = $(this).find('input[type="text"].wpsstm-data-id');

    input_id.change(function() {
        var hasVal = ( input_id.val().trim() );
        lookup_bt.toggleClass( "wpsstm-freeze", hasVal );
    });
});

//artist autocomplete
$('.wpsstm-artist-autocomplete').each(function() {
    var input_el = $(this);
    input_el.autocomplete({
        source: function( request, response ) {
            
            input_el.addClass('input-loading');
            
            $.ajax({
                type: "post",
                dataType: "json",
                url: wpsstmL10n.ajaxurl,
                data: {
                    action:             'wpsstm_search_artists',
                    search:              request.term + '*', //wildcard!
                }
            })
            .done(function( ajax ) {
                if(ajax.success){
                    var artists = ajax.data.artists;
                    response($.map(artists, function(artist){
                        return artist.name;
                    }));
                }else{
                    console.log(ajax);
                }
            })
            .fail(function(XMLHttpRequest, textStatus, errorThrown) { 
                console.log("status: " + textStatus + ", error: " + errorThrown); 
            })
            .always(function() {
                input_el.removeClass('input-loading');
            })
        },
        delay: 500,
        minLength: 2,
    });
});

//notices

$(document).on('click', 'a.wpsstm-close-notice', function(e) {
    var notice = this.closest('.wpsstm-block-notice');
    notice.remove();
});

//tracklist modals
$('body.wpsstm-popup').on('click', 'a.wpsstm-tracklist-popup,li.wpsstm-tracklist-popup>a', function(e) {
    e.preventDefault();

    var content_url = this.href;

    console.log("tracklist popup");
    console.log(content_url);


    var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
    var popup = $('<div></div>').append(loader_el);

    var popup_w = $(window).width() *.75;
    var popup_h = $(window).height() *.75;

    popup.dialog({
        width:popup_w,
        height:popup_h,
        modal: true,
        dialogClass: 'wpsstm-tracklist-dialog wpsstm-dialog dialog-loading',

        open: function(ev, ui){
            $('body').addClass('wpsstm-popup-overlay');
            var dialog = $(this).closest('.ui-dialog');
            var dialog_content = dialog.find('.ui-dialog-content');
            var iframe = $('<iframe src="'+content_url+'"></iframe>');
            dialog_content.append(iframe);
            iframe.load(function(){
                dialog.removeClass('dialog-loading');
            });
        },
        close: function(ev, ui){
            $('body').removeClass('wpsstm-popup-overlay');
        }

    });

});

//action popups
$(document).on('click', 'a.wpsstm-action-popup,li.wpsstm-action-popup>a', function(e) {
    e.preventDefault();

    var content_url = this.href;

    console.log("action popup");
    console.log(content_url);


    var loader_el = $('<p class="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
    var popup = $('<div></div>').append(loader_el);

    var popup_w = $(window).width() *.75;
    var popup_h = $(window).height() *.75;

    popup.dialog({
        width:popup_w,
        height:popup_h,
        modal: true,
        dialogClass: 'wpsstm-action-dialog wpsstm-dialog dialog-loading',

        open: function(ev, ui){
            $('body').addClass('wpsstm-popup-overlay');
            var dialog = $(this).closest('.ui-dialog');
            var dialog_content = dialog.find('.ui-dialog-content');
            var iframe = $('<iframe src="'+content_url+'"></iframe>');
            dialog_content.append(iframe);
            iframe.load(function(){
                dialog.removeClass('dialog-loading');
            });
        },
        close: function(ev, ui){
            $('body').removeClass('wpsstm-popup-overlay');
        }

    });

});

//supported importers URLs bt
$('#wpsstm-list-urls-bt').click(function(e) {
    e.preventDefault();
    $('#wpsstm-importer-urls').toggle();
});


