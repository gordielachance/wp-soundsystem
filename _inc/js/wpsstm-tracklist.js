
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track
var wpsstm_page_tracks = [];
var wpsstm_source_requests = [];

(function($){

    $(document).ready(function(){

        bt_prev_track = $('#wpsstm-player-nav-previous-track');
        bt_next_track = $('#wpsstm-player-nav-next-track');
        
        /* tracklist */
        
        //display tracklists
        $( ".wpsstm-tracklist-table" ).each(function(i, source_attr) {
            var tracklist_wrapper = $(this);
            wpsstm_load_tracklist(tracklist_wrapper);
        });
        
        //prepare tracks queue
        $( ".wpsstm-play-track" ).each(function(i, source_attr) {
            
            var track_el = $(this).closest('tr');
            track_el.attr('data-wpsstm-track-idx',i);

            var track_obj = {
                row:        track_el.get(0),
                post_id:    track_el.attr('data-wpsstm-track-id'),
                artist:     track_el.find('.trackitem_artist').text(),
                title:      track_el.find('.trackitem_track').text(),
                album:      track_el.find('.trackitem_album').text(),
                sources:    null
            }
            
            wpsstm_populate_track_sources(track_obj); //get sources from HTML if any
            wpsstm_page_tracks.push(track_obj);

        });

        /*
        track actions
        */

        //user is not logged
        $('.wpsstm-requires-auth').click(function(e) {
            if ( !wpsstm_get_current_user_id() ){
                e.preventDefault();
                $('#wpsstm-bottom-notice-wp-auth').show();
            }

        });

    });
    
    /*
    Init a sources request for this track and the X following ones (if not populated yet)
    */
    
    function wpsstm_init_sources_request(track_idx) {
        
        wpsstm_source_requests.cancelTrackSourceRequests(track_idx); //abord current requests

        var request_tracks = [];
        var max_items = wpsstm_track_source_requests_limit;
        var rtrack_count = 0;
        var page_tracks_slice = $(wpsstm_page_tracks).slice(track_idx,track_idx+max_items);

        $.each(page_tracks_slice, function( index, rtrack_obj ) {
            rtrack_count++;
            if ( rtrack_obj.did_lookup ) return true; //continue
            if (rtrack_count > max_items){
                return false;//break
            }else{
                request_tracks.push(rtrack_obj);
            }
        });

        $(request_tracks).each(function(i, val) {
            var request_track_idx = track_idx + i;
            var track_obj = wpsstm_page_tracks[request_track_idx];
            if (!track_obj.sources){
                wpsstm_source_requests.getTrackSourceRequest(request_track_idx);
            }
        });
    }
    
    //http://stackoverflow.com/questions/42271167/break-out-of-ajax-loop
    wpsstm_source_requests.getTrackSourceRequest = function(track_idx) {

        var xhr = wpsstm_get_track_sources(track_idx);
        wpsstm_source_requests.push(xhr);

        xhr.fail(function(jqXHR, textStatus, errorThrown) {
            /*
            if (jqXHR.status === 0 || jqXHR.readyState === 0) { //http://stackoverflow.com/a/5958734/782013
                return;
            }
            */
            
            console.log("getTrackSourceRequest() failed for track #"+track_idx);
            console.log({jqXHR:jqXHR,textStatus:textStatus,errorThrown:errorThrown});
        })
        .done(function(data) {
            //track could have been switched since, so check if this is still the track to play
            if (wpsstm_current_track_idx == track_idx){
                wpsstm_init_track(track_idx,true);
            }
        })
        .then(function(data, textStatus, jqXHR) {})
        .always(function(data, textStatus, jqXHR) {
            //item.statusText = null;
            //wpsstm_source_requests.$apply();
        })
    }
    
    wpsstm_source_requests.cancelTrackSourceRequests = function() {
        for (var i = 0; i < wpsstm_source_requests.length; i++) {
            wpsstm_source_requests[i].abort();
        }

        wpsstm_source_requests.length = 0;
    };

    function wpsstm_get_track_sources(track_idx) {

        var track_obj = wpsstm_page_tracks[track_idx];
        var track_el = $(track_obj.row);
        
        var track = {
            artist: track_obj.artist,
            title:  track_obj.title,
            album:  track_obj.album
        }
        
        //console.log("wpsstm_get_track_sources(): #" + track_idx);
        //console.log(track);
        
        var ajax_data = {
            'action':           'wpsstm_player_get_provider_sources',
            'track':            track
        };
        
        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                track_el.addClass('buffering');
            },
            success: function(data){
                if (data.success === false) {
                    track_el.addClass('error');
                    console.log("error getting sources for track#" + track_idx);
                    console.log(data);
                }else{
                    if ( data.new_html ){
                        var new_sources_list = $(data.new_html);
                        var new_sources_items = new_sources_list.find('li');
                        console.log("found "+new_sources_items.length +" sources for track#" + track_idx);
                        
                        track_el.find('.trackitem_sources').html(new_sources_list); //append new sources
                        wpsstm_populate_track_sources(track_obj); //get sources from HTML if any
                        
                    }
                }
            },
            complete: function() {
                track_obj.did_lookup = true;
                track_el.addClass('did-source-lookup');
                track_el.removeClass('buffering');
            }
        })
        
        
    }
    
    function wpsstm_load_tracklist(tracklist_wrapper){
        
        var tracklist_id = tracklist_wrapper.attr('data-tracklist-id');
        
        var ajax_data = {
            'action':           'wpsstm_load_tracklist',
            'post_id':          tracklist_id
        };
        
        console.log(ajax_data);
        
        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                tracklist_wrapper.addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    tracklist_wrapper.addClass('error');
                    console.log(data);
                }else{
                    if ( data.new_html ){
                        console.log(data);
                        tracklist_wrapper.replaceWith(data.new_html);
                    }
                }
            },
            complete: function() {
                tracklist_wrapper.removeClass('loading');
            }
        })
    }
    
    function wpsstm_populate_track_sources(track_obj){
        var track_el = $(track_obj.row);
        
        //append sources to track obj
        var source_lis = track_el.find('.trackitem_sources li');
        var sources = [];
        $.each(source_lis, function( index, source_li ) {
            var new_source = {
                type:   $(source_li).attr('data-wpsstm-source-type'),
                src:    $(source_li).find('a').attr('href')
            }
            sources.push(new_source);
        });
        
        if (sources.length > 0){
            track_obj.sources = sources;
        }
        track_el.attr('data-wpsstm-sources-count',sources.length);
    }

    /*
    Update the track button after a media event.
    */
    
    function wpsstm_update_track_button(track_obj,event){
        
        var track_el = $(track_obj.row);
        
        switch(event) {
            case 'loadeddata':
            break;
            case 'error':
                track_el.addClass('error');
            break;
            case 'play':
                track_el.addClass('playing');
                track_el.removeClass('error buffering ended');
            break;
            case 'pause':
                track_el.removeClass('playing');
            break;
            case 'ended':
                track_el.removeClass('playing');
                track_el.addClass('has-played');
            break;
        }
        
    }

})(jQuery);
