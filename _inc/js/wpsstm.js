(function($){

    $(document).ready(function(){
        //user is not logged for action
        $(document).on( "click",'.wpsstm-requires-auth', function(e) {
            if ( !wpsstm_get_current_user_id() ){
                e.preventDefault();
                wpsstm_wp_auth_notice();
            }
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
                            search:              request.term,
                        },
                        beforeSend: function() {
                            input.addClass('wpsstm-loading');
                        },
                        success: function( data ) {
                            if(data.success){
                                console.log("YO");
                                console.log(data);
                                console.log(data.count);
                                console.log(data.artists);
                                response($.map(data.artists, function(artist){
                                    return artist.name; // on retourne cette forme de suggestion
                                }));
                            }else{
                                console.log(data);
                            }

                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) { 
                            console.log("status: " + textStatus + ", error: " + errorThrown); 
                        },
                        complete: function() {
                            input.removeClass('wpsstm-loading');
                        }
                    });
                },
                minLength: 1
            });
        });
    });  


})(jQuery);

function wpsstm_debug(msg,prefix){
    if (!wpsstmL10n.debug) return;
    if (typeof msg === 'object'){
        console.log(msg);
    }else{
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

function wpsstm_get_current_user_id(){
    return parseInt(wpsstmL10n.logged_user_id);
}

function wpsstm_bottom_notice(slug,msg,can_close = true){
    var notice_id =  'wpsstm-bottom-notice-' + slug;
        
    var existing_notice_el = $('#' + notice_id);
    if (existing_notice_el.length > 0) return;
   
    var notice = $('<div class="wpsstm-notice wpsstm-bottom-notice"></div>');
    var notice_msg = $('<p></p>');
    notice_msg.html(msg);
    notice.attr({
        id:     notice_id
    })

    if(can_close){
        var close_bt = $('<i class="wpsstm-close-notice fa fa-times" aria-hidden="true"></i>');
        notice.append(close_bt);
        
        close_bt.click(function(event){
            event.preventDefault();
            notice.remove();
        });
        
    }
    
    notice.append(notice_msg);
    $( "#wpsstm-bottom-wrapper" ).prepend( notice );
}

function wpsstm_wp_auth_notice(){
    var self = this;
    wpsstm_bottom_notice('wp-auth',wpsstmL10n.wp_auth_notice);
}