var wpsstm = {};
var isInIframe = (window.location != window.parent.location) ? true : false;
wpsstm.tracklists = [];

(function($){

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
        
        var bottomPlayer = new WpsstmPlayer('wpsstm-bottom-player');
        var iframes = $('iframe.wpsstm-tracklist-iframe');

        /*
        load tracklists
        */
        
        $('body.wpsstm-iframe .wpsstm-tracklist').each(function(index,tracklist_html) {
            
            if (isInIframe){
                /*get tracklist index related to the parent document, so it matches the dom order */
                index = wpsstm_get_iframe_index();
            }
            
            var tracklist_obj = new WpsstmTracklist(tracklist_html,index);
            wpsstm.tracklists.push(tracklist_obj);
            
            if (isInIframe){
                //send to parent
                window.top.wpsstm.tracklists.push(tracklist_obj);
            }
            
        });
        
        /*
        wait for all iframes before initializing player
        */
  
        var iframesLoaded = $.Deferred();
        var loadedIFramesCount = 0;

        iframes.one( "load", function() {

            ++loadedIFramesCount;//increment
            var iframe_el = $(this).get(0);

            $(this).parents('.wpsstm-iframe-container').removeClass('wpsstm-iframe-loading');

            var content = $(iframe_el.contentWindow.document.body);

            //all frames is loaded
            if ( loadedIFramesCount == iframes.length ){
                iframesLoaded.resolve();
            }

        });
        
        /*
        init player
        */

        iframesLoaded.done(function(v) {
            bottomPlayer.debug('all iframes have been loaded, init player');

            //sort tracklists by tracklist index
            function compare_tracklist_idx(a,b) {
                if (a.index > b.index) return 1;
                if (b.index > a.index) return -1;
                return 0;
            }

            wpsstm.tracklists.sort(compare_tracklist_idx);

            var allTracks = [];
            $(wpsstm.tracklists).each(function(index,tracklist) {
                allTracks = allTracks.concat(tracklist.tracks);
            });

            bottomPlayer.append_tracks(allTracks);
            bottomPlayer.autoplay();

        });
        
        /*
        resize iframes
        */
        //iframes.iFrameResize();

        iframes.on( "load", function() {
            var iframe = $(this);
            var iframe_el = iframe.get(0);
            var content = $(iframe_el.contentWindow.document);
            //var height = $(e.target).find('html').get(0).scrollHeight;
            iframe.css('height',content.outerHeight());
            console.log("resized iframe");
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

//https://www.webdeveloper.com/forum/d/251166-resolved-how-to-get-the-iframe-index-using-javascript-from-inside-the-iframe-window/2
function wpsstm_get_iframe_index(){
    var iframe_index = -1;
    var parent_iframes = $(parent.document).find('iframe.wpsstm-tracklist-iframe');
    parent_iframes.each(function(index,iframe_el) {
        if ( iframe_el.contentWindow === self ){
            iframe_index = index;
            return false;
        }
    });
    return iframe_index;
}