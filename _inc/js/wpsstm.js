var bottomPlayer = undefined;

(function($){

    //artist autocomplete
    $('.wpsstm-artist-autocomplete').each(function() {
        var input = $(this);
        input.autocomplete({
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
                        input.addClass('input-loading');
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
                        input.removeClass('input-loading');
                    }
                });
            },
            delay: 500,
            minLength: 2,
        });
    });
    
    $( document ).ready(function() {
        wpsstm_debug("init","wpsstm");
        var bottomPlayerEl = $('#wpsstm-player');
        bottomPlayer = new WpsstmPlayer(bottomPlayerEl);
        
    });
    
    $('iframe').load(function(e){
        
        console.log("IFRAME LOADED");
        
        var iframe = $(this).get(0);
        
        $(this).parents('.wpsstm-iframe-container').removeClass('wpsstm-iframe-loading');
        
        var content = $(iframe.contentWindow.document.body);
        var tracklist_els = $(content).find( ".wpsstm-tracklist" );

        $.each(tracklist_els, function( index, playlist_html ) {
            var tracklist_obj = new WpsstmTracklist(playlist_html);
            bottomPlayer.queue_tracklist(tracklist_obj);
        });
        
        bottomPlayer.start_player();
        
    });
    
    $('iframe.wpsstm-iframe-autoheight').load(function(e){
        console.log("resize iframe");
        var iframe = $(this).get(0);
        var content = $(iframe.contentWindow.document);
        //var height = $(e.target).find('html').get(0).scrollHeight;
        $(this).css('height',content.outerHeight());
    });
    

})(jQuery);

function wpsstm_debug(msg,prefix){
    if (!wpsstmL10n.debug) return;
    if (typeof msg === 'object'){
        console.log(msg);
    }else{
        if (prefix) prefix = prefix + ': ';
        console.log(prefix + msg);
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