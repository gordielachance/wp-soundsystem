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

var bottomPlayer;
var trackContainers;
$( document ).ready(function() {
    
    bottomPlayer = new WpsstmPlayer('wpsstm-bottom-player');
    var trackContainers = $('wpsstm-tracklist');
    
    trackContainers.each(function(index,tracklist) {
        if ( index === 0 ){
            $(tracklist).addClass('tracklist-playing');
        }
        bottomPlayer.queueContainer(tracklist);
    });

    
    trackContainers.each(function(index,tracklist_html) {
        
        //TOUFIX URGENT
        /*
        var tracklist_obj = new WpsstmTracklist(tracklist_html);
        
        //should we refresh this playlist ?
        if (tracklist_obj.isExpired){
             var reloaded = tracklist_obj.reload();

              reloaded.done(function() {
                alert( "success" );
              })
              .fail(function() {
                alert( "error" );
              })
              .always(function() {
                alert( "complete" );
              });

        }
         */

    });
    
});

//TOUFIX
$(document).on( "wpsstmTracklistReload", function( event, tracklist_obj ) {
    console.log("wpsstmTracklistReload");
    bottomPlayer.unQueueContainer(tracklist_obj);
});


//https://developers.google.com/web/fundamentals/web-components/customelements
