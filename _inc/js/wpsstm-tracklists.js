(function($){
    
    $.fn.extend({ 
        toggleTracklist: function(options){
            // OPTIONS
            var defaults = {
                childrenShowCount:  true,
                childrenMax:        3,
                childrenSelector:   '.wpsstm-tracklist-entries > *',
                moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
            };
            var options =  $.extend(defaults, options);
            
            $(this).each(function() {
                if ( $(this).attr("data-tracks-count") > 0 ) {
                    return $(this).toggleChildren(options);
                }
            });


        }
    });
    
    $(document).ready(function(){
        $('.wpsstm-tracklist').toggleTracklist();    
        $('#wpsstm-subtracks-list table').toggleChildren({
                childrenShowCount:  true,
                childrenMax:        3,
                childrenSelector:   'tbody > *',
                moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
            });
    });

    $(document).on( "wpsstmTrackInit", function( event, track_obj ) {
        
        var track_el = track_obj.track_el;
        if ( track_el.is(":visible") ) return;
        
        var tracklist_el = track_obj.get_tracklist_el();
        var visibleTracksCount = tracklist_el.find('[itemprop="track"]:visible').length;
        var newTracksCount = track_obj.track_idx + 1;
        
        if ( newTracksCount <= visibleTracksCount ) return;
        
        tracklist_el.toggleTracklist({
            childrenMax:newTracksCount
        });
        
    });
    
    /*
    Playlists manager popup
    */
    
    //filter playlists
    $(document).on("keyup", '#wpsstm-filter-playlists input[type="text"]', function(e){
        e.preventDefault();
        var playlistFilterWrapper = $(this).closest('#wpsstm-filter-playlists');
        var value = $(this).val().toLowerCase();
        var li_items = playlistFilterWrapper.find('ul li');
        
        $(li_items).each(function() {
            if ($(this).text().toLowerCase().search(value) > -1) {
                $(this).show();
            }
            else {
                $(this).hide();
            }
        });
        
    });
    
    //show playlist adder
    $(document).on("click", '#wpsstm-new-playlist-adder a', function(e){
        e.preventDefault();
        var popupContent = $(this).parents('.wpsstm-popup-content');
        var playlistAddWrapper = $(popupContent).find('#wpsstm-new-playlist-add');
        $(playlistAddWrapper).toggle();
        
        //scroll to it
        //TO FIX NOT WORKING
        $(popupContent).scrollTop( $(playlistAddWrapper).offset().top ); 
    });
    
    //create new playlist
    $(document).on("click", '#wpsstm-new-playlist-adder input[type="submit"]', function(e){
        e.preventDefault();
        var popupContent = $(this).parents('.wpsstm-popup-content');
        var playlistAddWrapper = $(popupContent).find('#wpsstm-new-playlist-add');
        var newPlaylistTitle = $(playlistAddWrapper).find('input[type="text"]').val();
        
        if (!newPlaylistTitle) return;
        
        //get track obj from HTML
        var track_html = $(popupContent).find('[itemprop="track"]').first();
        var track_obj = new WpsstmTrack(track_html);
        
        console.log(track_obj);

        var ajax_data = {
            action:         'wpsstm_add_track_to_new_tracklist',
            track:          track_obj.build_request_obj(),
            playlist_title: newPlaylistTitle,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(popupContent).addClass('loading');
            },
            success: function(data){
                console.log(data);
                if (data.success === false) {
                    console.log(data);
                }else if(data.new_html) {
                    popupContent.find('ul').replaceWith(data.new_html);
                    $(playlistAddWrapper).toggle();
                }
            },
            complete: function() {
                $(popupContent).removeClass('loading');
            }
        })
        
        
    });
    
})(jQuery);

/*
Displays a box with a text the user can copy.
http://stackoverflow.com/questions/400212/how-do-i-copy-to-the-clipboard-in-javascript
*/
function wpsstm_clipboard_box(text) {
    window.prompt(wpsstmL10n.clipboardtext, text);
}
