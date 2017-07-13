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
        
        // sort rows
        $('.wpsstm-tracklist').find( '[itemprop="track"]' ).sortable({
            handle: '#track-admin-action-move a',

            update: function(event, ui) {
                wpsstm_tracklist_reorder();
                wpsstm_tracklist_order_update();
            }
        });
        
    });
    
    $(document).on( "wpsstmTracklistDomReady", function( event, self ) {
        /*
        Tracklist actions
        */
        
        //refresh
        self.tracklist_el.find("#tracklist-action-refresh a").click(function(e) {
            e.preventDefault();
            //unset request status
            self.debug("clicked 'refresh' link");
            self.get_tracklist_request(true); //initialize but do not set track to play
            
        });
        
        //share
        self.tracklist_el.find('#tracklist-action-share a').click(function(e) {
          e.preventDefault();
          var text = $(this).attr('href');
          wpsstm_clipboard_box(text);
        });

        //favorite
        self.tracklist_el.find('#tracklist-action-favorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var action_li = $(this).closest('li');
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = tracklist_wrapper.attr('data-wpsstm-tracklist-id');

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_love_unlove_tracklist',
                post_id:        tracklist_id,
                do_love:        true,
            };

            self.debug("favorite tracklist:" + tracklist_id);

            return $.ajax({
                type:       "post",
                url:        wpsstmL10n.ajaxurl,
                data:       ajax_data,
                dataType:   'json',
                beforeSend: function() {
                    action_li.addClass('loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var tracklist_instances = self.get_tracklist_instances()
                        tracklist_instances.find('#tracklist-action-favorite').removeClass('active');
                        tracklist_instances.find('#tracklist-action-unfavorite').addClass('active');
                    }
                },
                complete: function() {
                    action_li.removeClass('loading');
                }
            })
        });
        
        //unfavorite
        self.tracklist_el.find('#tracklist-action-unfavorite a').click(function(e) {
            e.preventDefault();
            
            if ( !wpsstm_get_current_user_id() ) return;

            var link = $(this);
            var action_li = $(this).closest('li');
            var tracklist_wrapper = link.closest('.wpsstm-tracklist');
            var tracklist_id = tracklist_wrapper.attr('data-wpsstm-tracklist-id');

            if (!tracklist_id) return;

            var ajax_data = {
                action:         'wpsstm_love_unlove_tracklist',
                post_id:        tracklist_id,
                do_love:        false,
            };

            self.debug("unfavorite tracklist:" + tracklist_id);

            return $.ajax({
                type:       "post",
                url:        wpsstmL10n.ajaxurl,
                data:       ajax_data,
                dataType:   'json',
                beforeSend: function() {
                    action_li.addClass('loading');
                },
                success: function(data){
                    if (data.success === false) {
                        console.log(data);
                    }else{
                        var tracklist_instances = self.get_tracklist_instances()
                        tracklist_instances.find('#tracklist-action-unfavorite').removeClass('active');
                        tracklist_instances.find('#tracklist-action-favorite').addClass('active');
                    }
                },
                complete: function() {
                    action_li.removeClass('loading');
                }
            })
        });
        
        //switch status
        self.tracklist_el.find("#tracklist-admin-action-status-switch a").click(function(e) {
            e.preventDefault();
            $(this).closest('li').toggleClass('expanded');
            
        });
        
        self.tracklist_el.toggleTracklist();
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
        var track_el = $(popupContent).find('[itemprop="track"]').first();
        var track_obj = new WpsstmTrack(track_el);

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
                if (data.success === false) {
                    console.log(data);
                    checkbox.prop("checked", !checkbox.prop("checked")); //restore previous state
                }else if(data.success) {
                    //TO FIX replace whole track el ?
                    $(track_el).attr('data-wpsstm-track-id',data.track_id); //set returned track ID (useful if track didn't exist before)
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
