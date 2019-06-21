var $ = jQuery.noConflict();

//artist autocomplete
$('.wpsstm-artist-autocomplete').each(function() {
    var input_el = $(this);
    input_el.autocomplete({
        source: function( request, response ) {
            $.ajax({
                type: "post",
                dataType: "json",
                url: wpsstmL10n.ajaxurl,
                data: {
                    action:             'wpsstm_search_artists',
                    search:              request.term + '*', //wildcard!
                },
                beforeSend: function() {
                    input_el.addClass('input-loading');
                },
                success: function( ajax ) {
                    if(ajax.success){
                        var artists = ajax.data.artists;
                        response($.map(artists, function(artist){
                            return artist.name;
                        }));
                    }else{
                        console.log(ajax);
                    }

                },
                error: function(XMLHttpRequest, textStatus, errorThrown) { 
                    console.log("status: " + textStatus + ", error: " + errorThrown); 
                },
                complete: function() {
                    input_el.removeClass('input-loading');
                }
            });
        },
        delay: 500,
        minLength: 2,
    });
});

//registration notice
if ( registration_notice = wpsstmL10n.registration_notice){
    wpsstm_js_notice(registration_notice);
}

//notices

$(document).on('click', 'a.wpsstm-close-notice', function(e) {
    var notice = this.closest('.wpsstm-block-notice');
    notice.remove();
});

//queue tracks in player on tracklist init/refresh
var bottomPlayer = $('wpsstm-player#wpsstm-bottom-player').get(0);
if (bottomPlayer){
    $(document).on( "wpsstmTracklistReady", function( event,tracklist ) {
        bottomPlayer.queueContainer(tracklist);

    });
}

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

$('wpsstm-tracklist,.wpsstm-standalone-track').each(function(index,tracklist) {

    tracklist.setAttribute('id','wpsstm-tracklist-'+index);

    if (wpsstmL10n.ajax_tracks && tracklist.isExpired){
        tracklist.reload_tracklist();
    }else{


        /*
        Since wpsstmTracklistReady is fired when the tracklist is inserted, it will be fired before document.ready.
        So fire it once more at init.
        */
        $(document).trigger("wpsstmTracklistReady",[tracklist]);
    }

});


//https://developers.google.com/web/fundamentals/web-components/customelements
