(function($){

  $(document).ready(function(){

    /*
    tracklists
    */

    $('.wpsstm-tracklist-table table').shortenTable(3,'tbody tr');

    $('a.wpsstm-tracklist-action-share').click(function(e) {
      e.preventDefault();
      var text = $(this).attr('href');
      wpsstm_clipboard_box(text);
    });
      
    //toggle love/unlove tracklist
    $('.wpsstm-love-unlove-playlist-links a').click(function(e) {
        e.preventDefault();
        
        var link = $(this);
        var link_wrapper = link.closest('.wpsstm-love-unlove-playlist-links');
        var tracklist_wrapper = link.closest('.wpsstm-tracklist-table');
        var tracklist_id = tracklist_wrapper.attr('data-tracklist-id');
        var do_love = !link_wrapper.hasClass('wpsstm-is-loved');
        
        if (!tracklist_id) return;

        var ajax_data = {
            action:         'wpsstm_love_unlove_tracklist',
            post_id:        tracklist_id,
            do_love:        do_love,
        };

        console.log("toggle_love_tracklist:" + do_love);

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                link_wrapper.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    if (do_love){
                        link_wrapper.addClass('wpsstm-is-loved');
                    }else{
                        link_wrapper.removeClass('wpsstm-is-loved');
                    }
                }
            },
            complete: function() {
                link_wrapper.removeClass('loading');
            }
        })


    });
      
      //toggle love/unlove track
    $('.wpsstm-love-unlove-track-links a').click(function(e) {
        e.preventDefault();
        
        var link = $(this);
        var link_wrapper = link.closest('.wpsstm-love-unlove-track-links');
        var track_wrapper = link.closest('tr');
        var track_id = track_wrapper.attr('data-wpsstm-track-id');
        var do_love = !link_wrapper.hasClass('wpsstm-is-loved');
        
        var track_el = link.closest('[itemprop="track"]');
        var artist = track_el.find('[itemprop="byArtist"]').text();
        var title = track_el.find('[itemprop="name"]').text();
        var album = track_el.find('[itemprop="name"]').text();
        
        var track = {
            artist: artist,
            title:  title,
            album:  album
        }

        var ajax_data = {
            action:         'wpsstm_love_unlove_track',
            post_id:        track_id,
            do_love:        do_love,
            track:          track
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                link_wrapper.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    if (do_love){
                        link_wrapper.addClass('wpsstm-is-loved');
                    }else{
                        link_wrapper.removeClass('wpsstm-is-loved');
                    }
                }
            },
            complete: function() {
                link_wrapper.removeClass('loading');
            }
        })


    });
      
      
  });  
})(jQuery);

/*
Displays a box with a text the user can copy.
http://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript
*/
function wpsstm_clipboard_box(text) {
    window.prompt(wpsstmL10n.clipboardtext, text);
}
