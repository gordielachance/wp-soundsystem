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

//notices

$(document).on('click', 'a.wpsstm-close-notice', function(e) {
    var notice = this.closest('.wpsstm-block-notice');
    notice.remove();
});


$( document ).ready(function() {
    wpsstm_debug("DOM READY!");
    
    //registration notice
    if ( registration_notice = wpsstmL10n.registration_notice){
        wpsstm_js_notice(registration_notice);
    }
    
    var bottomPlayer = $('wpsstm-player#wpsstm-bottom-player').get(0);
    var trackContainers = $('wpsstm-tracklist');
    
    //queue on tracklist init/refresh
    $(document).on( "wpsstmTracklistReady", function( event,tracklist ) {
        bottomPlayer.queueContainer(tracklist);

    });

    trackContainers.each(function(index,tracklist) {
        
        var autoplay = ( index === 0 ); //autoplay if this is the first page tracklist
        tracklist.setAttribute('id','wpsstm-tracklist-'+index);

        if (tracklist.isExpired){
            tracklist.reload_tracklist(autoplay);
        }else{
            
            if (autoplay){
                $(tracklist).find('wpsstm-track').first().addClass('track-autoplay');
            }
            
            /*
            Since wpsstmTracklistReady is fired when the tracklist is inserted, it will be fired before document.ready.
            So fire it once more at init.
            */
            $(document).trigger("wpsstmTracklistReady",[tracklist]);
        }

    });

    
});


//https://developers.google.com/web/fundamentals/web-components/customelements
