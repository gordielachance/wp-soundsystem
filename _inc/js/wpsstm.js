(function($){

    //modals
    $(document).on('click', 'body:not(.wpsstm-popup) a.wpsstm-link-popup,body:not(.wpsstm-popup) li.wpsstm-link-popup>a', function(e) {
        e.preventDefault();

        var content_url = this.href;

        //append popup arg
        if( content_url.indexOf("?") >= 0 ) {
            content_url = content_url+"&wpsstm-popup=true";
        }else{
            content_url = content_url+"?wpsstm-popup=true";
        }

        console.log(content_url);


        var loader_el = $('<p id="wpsstm-dialog-loader" class="wpsstm-loading-icon"></p>');
        var popup = $('<div></div>').append(loader_el);

        popup.dialog({
            width:800,
            height:500,
            modal: true,
            dialogClass: 'wpsstm-dialog-iframe wpsstm-dialog dialog-loading',

            open: function(ev, ui){
                $('html').addClass('wpsstm-is-dialog');
                var dialog = $(this).closest('.ui-dialog');
                var dialog_content = dialog.find('.ui-dialog-content');
                var iframe = $('<iframe id="wpsstm-dialog-iframe" src="'+content_url+'"></iframe>');
                dialog_content.append(iframe);
                iframe.load(function(){
                    dialog.removeClass('dialog-loading');
                });
            },
            close: function(ev, ui){
                $('html').removeClass('wpsstm-is-dialog');
            }

        });

    });

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
    
    $('iframe.wpsstm-iframe-autoheight').load(function(e){
        var iframe = $(this).get(0);
        var content = $(iframe.contentWindow.document.body);
        //var height = $(e.target).find('html').get(0).scrollHeight;
        $(this).css('height',content.innerHeight());
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
            $('html').addClass('wpsstm-is-dialog');
            var dialog = $(this).closest('.ui-dialog');
            var dialog_content = dialog.find('.ui-dialog-content');
            var iframe = $('<iframe id="wpsstm-dialog-iframe" src="'+content_url+'"></iframe>');
            dialog_content.append(iframe);
            iframe.load(function(){
                dialog.removeClass('dialog-loading');
            });
        },
        close: function(ev, ui){
            $('html').removeClass('wpsstm-is-dialog');
        }

    });
 
}