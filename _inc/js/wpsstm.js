var $ = jQuery.noConflict();
var wpsstm = {};

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

function wpsstm_dialog_notice(notice){

    var popup = $('<div></div>').append(notice);

    popup.dialog({
        autoOpen: true,
        width:800,
        height:500,
        modal: true,
        dialogClass: 'wpsstm-dialog',

        open: function(ev, ui){
            var dialog = $(this).closest('.ui-dialog');
            var dialog_content = dialog.find('.ui-dialog-content');
            var iframe = $('<iframe id="wpsstm-dialog-iframe" src="'+content_url+'"></iframe>');
            dialog_content.append(iframe);
            iframe.load(function(){
                dialog.removeClass('dialog-loading');
            });
        },
        close: function(ev, ui){
        }

    });

}

function wpsstm_debug(msg,prefix){
    if (!wpsstmL10n.debug) return;
    if (typeof msg === 'object'){
        console.log(msg);
    }else{
        if (!prefix) prefix = 'wpsstm';
        console.log(prefix + ': ' + msg);
    }
}

function wpsstm_shuffle(array) {
  var currentIndex = array.length, temporaryValue, randomIndex;

  // While there remain elements to shuffle...
  while (0 !== currentIndex) {

    // Pick a remaining element...
    randomIndex = Math.floor(Math.random() * currentIndex);
    currentIndex -= 1;

    // And swap it with the current element.
    temporaryValue = array[currentIndex];
    array[currentIndex] = array[randomIndex];
    array[randomIndex] = temporaryValue;
  }

  return array;
}

var bottomPlayer = new WpsstmPlayer('wpsstm-bottom-player');
var trackContainers = $('wpsstm-tracklist');

/*
Reload expired tracklists at init
*/
trackContainers.each(function(index,tracklist) {

    var index = trackContainers.index( $(tracklist) );
    var autoplay = ( index === 0 ); //autoplay if this is the first page tracklist

    $(tracklist).toggleClass('tracklist-autoplay',autoplay);

    if (tracklist.isExpired){
        alert("tracklist is expired");
        tracklist.reload_tracklist(autoplay);
    }

});

$(document).on( "wpsstmTracklistReady", function( event,tracklist ) {
    console.log("***wpsstmTracklistReady");
    bottomPlayer.queueContainer(tracklist);

});

$(document).on( "wpsstmTracklistBeforeReload", function( event,tracklist ) {
    console.log("***wpsstmTracklistBeforeReload");
    bottomPlayer.unQueueContainer(tracklist);
});


//https://developers.google.com/web/fundamentals/web-components/customelements
