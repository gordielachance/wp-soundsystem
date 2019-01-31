var $ = jQuery.noConflict();
var wpsstm = {};
var isInIframe = (window.location != window.parent.location) ? true : false;

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

    var iframes = $('iframe.wpsstm-tracklist-iframe');

    iframes.one( "load", function() {
        $(this).parents('.wpsstm-iframe-container').removeClass('wpsstm-iframe-loading');
    });

});


/*
resize iframes with iframeResizer
*/

if (typeof iFrameResize === "function") { //check that fn is available
    $('.wpsstm-iframe-autoheight').iFrameResize({
        //log:true,
    });
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
function wpsstm_get_tracklist_iframe(){
    var return_el = undefined;
    var parent_iframes = $(parent.document).find('iframe.wpsstm-tracklist-iframe');
    parent_iframes.each(function(index,iframe_el) {
        if ( iframe_el.contentWindow === self ){
            return_el = iframe_el;
            return false;
        }
    });
    return return_el;
}

function wpsstm_get_index_tracklist_iframe(){
    var iframe_index = -1;
    var iframe_el = wpsstm_get_tracklist_iframe();
    var parent_iframes = $(parent.document).find('iframe.wpsstm-tracklist-iframe');
    return $(parent_iframes).index( $(iframe_el) );
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