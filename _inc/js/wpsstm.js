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


$( document ).ready(function() {
    console.log("DOM READY!");
    
    var bottomPlayer = $('wpsstm-player#wpsstm-bottom-player').get(0);
    var trackContainers = $('wpsstm-tracklist');

    /*
    Handle page init
    */
    trackContainers.each(function(index,tracklist) {

        var index = trackContainers.index( $(tracklist) );
        var autoplay = ( index === 0 ); //autoplay if this is the first page tracklist

        $(tracklist).toggleClass('tracklist-autoplay',autoplay);

        if (tracklist.isExpired){
            tracklist.reload_tracklist(autoplay);
        }else{
            bottomPlayer.queueContainer(tracklist);   
        }

    });
    
    //queue on refresh
    $(document).on( "wpsstmTracklistReady", function( event,tracklist ) {
        console.log("***wpsstmTracklistReady");
        bottomPlayer.queueContainer(tracklist);

    });

    $(document).on( "wpsstmTracklistBeforeReload", function( event,tracklist ) {
        console.log("***wpsstmTracklistBeforeReload");
        bottomPlayer.unQueueContainer(tracklist);
    });
    
});


//https://developers.google.com/web/fundamentals/web-components/customelements
