(function($){

    $(document).ready(function(){
        
    /*Tracklists manager*/

    $(document).on( "click",'#wpsstm-tracklist-chooser-list li input[type="checkbox"]', function(e){

        var checkbox =              $(this);
        var tracklistSelector =     $(this).closest('#wpsstm-tracklist-chooser-list');
        var track_id =              $(tracklistSelector).attr('data-wpsstm-track-id');
        
        var is_checked =            $(checkbox).is(':checked');
        var tracklist_id =          $(this).val();
        var li_el =                 $(checkbox).closest('li');

        var ajax_data = {
            action:         (is_checked ? 'wpsstm_add_tracklist_track' : 'wpsstm_remove_tracklist_track'),
            track_id:       track_id,
            tracklist_id:   tracklist_id,
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
                if (data.success === false) {
                    console.log(data);
                    checkbox.prop("checked", !checkbox.prop("checked")); //restore previous state
                }else if(data.success) {
                }
            },
            complete: function() {
                $(li_el).removeClass('loading');
            }
        })

    });

    //create new playlist
    $(document).on( "click",'#wpsstm-tracklist-chooser-list #wpsstm-new-playlist-add input[type="submit"]', function(e){

        e.preventDefault();
        var bt =                        $(this);
        var tracklistSelector =         bt.closest('#wpsstm-tracklist-chooser-list');
        var track_id =                  $(tracklistSelector).attr('data-wpsstm-track-id');
        
        var existingPlaylists_el =      $(tracklistSelector).find('ul');
        var newPlaylistTitle_el =       $(tracklistSelector).find('#wpsstm-playlists-filter');

        var playlistAddWrapper = $(tracklistSelector).find('#wpsstm-playlists-filter');

        if (!newPlaylistTitle_el.val()){
            $(newPlaylistTitle_el).focus();
        }
        
        var ajax_data = {
            action:         'wpsstm_append_to_new_tracklist',
            playlist_title: newPlaylistTitle_el.val(),
            track_id:       track_id,
        };

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(tracklistSelector).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else if(data.new_html) {
                    existingPlaylists_el.replaceWith( data.new_html );
                    $( "#wpsstm-playlists-filter" ).trigger("keyup");
                }
            },
            complete: function() {
                $(tracklistSelector).removeClass('loading');
            }
        })

    });
        
    });

})(jQuery);

