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
    Single Track
    */
    
    //click on source link
    $(document).on( "click",'[itemprop="track"].active .wpsstm-player-sources-list li', function(e) {
        e.preventDefault();

        var track_el = $(this).closest('[itemprop="track"]');
        
        var tracklist_el = track_el.closest('[data-wpsstm-tracklist-idx]');
        var tracklist_idx = tracklist_el.attr('data-wpsstm-tracklist-idx');
        var track_idx = track_el.attr('data-wpsstm-track-idx');
        var track_obj = wpsstm_page_player.get_tracklist_track_obj(tracklist_idx,track_idx);
        
        var source_el = $(this).closest('li');
        var source_idx = Number( source_el.attr('data-wpsstm-source-idx') );
        var source_obj = track_obj.get_track_source(source_idx);
        source_obj.select_player_source();
        

    });
    
    /*
    Single Track Playlists manager popup
    */
    
    //filter playlists
    $(document).on("keyup", '#wpsstm-playlists-filter', function(e){
        e.preventDefault();
        var playlistFilterWrapper = $(this).closest('#wpsstm-filter-playlists');
        var playlistAddWrapper = $(playlistFilterWrapper).find('#wpsstm-new-playlist-add');
        var value = $(this).val().toLowerCase();
        var li_items = playlistFilterWrapper.find('ul li');
        
        var has_results = false;
        $(li_items).each(function() {
            if ($(this).text().toLowerCase().search(value) > -1) {
                $(this).show();
                has_results = true;
            }
            else {
                $(this).hide();
            }
        });
        
        if (has_results){
            playlistAddWrapper.hide();
        }else{
            playlistAddWrapper.show();
        }

    });

    //create new playlist
    $(document).on("click", '#wpsstm-new-playlist-add input[type="submit"]', function(e){
        e.preventDefault();
        var bt =                        $(this);
        var popupContent =              $(bt).closest('.wpsstm-popup-content');
        var playlistFilterWrapper =     $(popupContent).find('#wpsstm-filter-playlists');
        var existingPlaylists_el =      $(playlistFilterWrapper).find('ul');
        var newPlaylistTitle_el =       $(playlistFilterWrapper).find('#wpsstm-playlists-filter');
        var newPlaylistTitle =          newPlaylistTitle_el.val();
        
        var playlistAddWrapper = $(playlistFilterWrapper).find('#wpsstm-new-playlist-add');
        

        
        if (!newPlaylistTitle){
            $(newPlaylistTitle_el).focus();
        }

        var ajax_data = {
            action:         'wpsstm_create_playlist',
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
                if (data.success === false) {
                    console.log(data);
                }else if(data.new_html) {
                    
                    $(existingPlaylists_el).remove();
                    $(data.new_html).insertBefore(playlistAddWrapper);
                    $( "#wpsstm-playlists-filter" ).trigger("keyup");
                    $(playlistAddWrapper).toggle();
                }
            },
            complete: function() {
                $(popupContent).removeClass('loading');
            }
        })

    });
    
    //attach to playlist
    $(document).on("click", '#wpsstm-filter-playlists ul li input[type="checkbox"]', function(e){
        
        var checkbox =      $(this);
        var is_checked =    $(checkbox).is(':checked');
        var playlist_id =   $(this).val();
        var li_el =         $(checkbox).closest('li');
        var popupContent =  $(checkbox).closest('.wpsstm-popup-content');

        //get track obj from HTML
        var track_html = $(popupContent).find('[itemprop="track"]').first();
        var track_obj = new WpsstmTrack(track_html);

        var ajax_data = {
            action:         (is_checked ? 'wpsstm_add_playlist_track' : 'wpsstm_remove_playlist_track'),
            track:          track_obj.build_request_obj(),
            playlist_id:    playlist_id,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(li_el).addClass('loading');
            },
            success: function(data){
                console.log(data);
                if (data.success === false) {
                    console.log(data);
                }else if(data.success) {
                    checkbox.prop("checked", !checkBoxes.prop("checked"));
                }
            },
            complete: function() {
                $(li_el).removeClass('loading');
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
