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
        var bottomPlayer = new WpsstmPlayer('wpsstm-bottom-player');
        var iframes = $('iframe.wpsstm-tracklist-iframe');
        var player_tracklists = [];
        
        var allLoaded = $.Deferred();
        var countLoaded = 0;
        
        //TOUFIX remove event once done ?

        iframes.load(function(e){
            
            ++countLoaded;//increment
            var iframe = $(this);
            var iframe_el = $(this).get(0);
            var iframe_index = iframes.index( iframe );

            $(this).parents('.wpsstm-iframe-container').removeClass('wpsstm-iframe-loading');

            var content = $(iframe_el.contentWindow.document.body);
            var tracklist_html = $(content).find( ".wpsstm-tracklist" ).get(0);

            var tracklist_obj = new WpsstmTracklist(tracklist_html,iframe_index);
            player_tracklists.push(tracklist_obj);

            wpsstm_debug("iframe tracklist #"+iframe_index+" populated");

            //everything is loaded
            if ( countLoaded == iframes.length ){
                allLoaded.resolve();
            }

            //resize iframe
            var content = $(iframe_el.contentWindow.document);
            //var height = $(e.target).find('html').get(0).scrollHeight;
            iframe.css('height',content.outerHeight());
            console.log("resized iframe");

        });
        
        allLoaded.done(function(v) {
            bottomPlayer.debug('all iframes have been loaded');

            //sort tracklists by tracklist index
            function compare_tracklist_idx(a,b) {
                if (a.index > b.index) return 1;
                if (b.index > a.index) return -1;
                return 0;
            }

            player_tracklists.sort(compare_tracklist_idx);
            
            var allTracks = [];
            $(player_tracklists).each(function(index,tracklist) {
                allTracks = allTracks.concat(tracklist.tracks);
            });
            
            bottomPlayer.append_tracks(allTracks);
            bottomPlayer.autoplay();
            
        });

        
    });


})(jQuery);

function wpsstm_debug(msg,prefix){
    if (!wpsstmL10n.debug) return;
    if (typeof msg === 'object'){
        console.log(msg);
    }else{
        if (!prefix) prefix = 'wpsstm';
        console.log(prefix + ': ' + msg);
    }
}

function wpsstm_arr_diff (a1, a2) {

    var a = [], diff = [];

    for (var i = 0; i < a1.length; i++) {
        a[a1[i]] = true;
    }

    for (var i = 0; i < a2.length; i++) {
        if (a[a2[i]]) {
            delete a[a2[i]];
        } else {
            a[a2[i]] = true;
        }
    }

    for (var k in a) {
        diff.push(k);
    }

    return diff;
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