var bottom_block;
var bottom_notice_refresh;
var wpsstm_player;
var wpsstm_current_media;
var wpsstm_countdown_s = wpsstmPlayer.autoredirect; //seconds for the redirection notice
var wpsstm_countdown_timer; //redirection timer
var wpsstm_track_source_requests_limit = 5; //number of following tracks we want to populate the sources for when clicking a track

var wpsstm_had_tracks_played = false;
var wpsstm_page_tracks = [];
var wpsstm_current_track_idx = -1;
var wpsstm_source_requests = [];

(function($){

    $(document).ready(function(){

        bottom_block = $('#wpsstm-bottom');
        bt_prev_track = $('#wpsstm-player-nav-previous-track');
        bt_next_track = $('#wpsstm-player-nav-next-track');
        bottom_notice_refresh = $('#wpsstm-bottom-notice-redirection');
        
        /* tracklist */
        
        //prepare tracks queue
        $( ".wpsstm-play-track" ).each(function(i, source_attr) {
            
            var track_el = $(this).closest('tr');
            track_el.attr('data-wpsstm-track-idx',i);

            var track = {
                row:        track_el.get(0),
                artist:     track_el.find('.trackitem_artist').text(),
                title:      track_el.find('.trackitem_track').text(),
                album:      track_el.find('.trackitem_album').text(),
                sources:    null
            }
            
            //get sources from HTML if any
            var sources = track_el.attr('data-wpsstm-sources');
            if (sources) {
                track.sources = JSON.parse(sources);
            }

            wpsstm_page_tracks.push(track);
            
        });
        
        //autoplay first track
        if ( wpsstmPlayer.autoplay ){
            if(typeof wpsstm_page_tracks[0] === 'undefined') return; //track does not exists
            console.log("autoplay first track");
            wpsstm_init_track(0);
        }
        
        /*
        page buttons
        */
        
        //track buttons
        $( ".wpsstm-play-track" ).live( "click", function(e) {
            e.preventDefault();
            var track_el = $(this).closest('tr');
            var track_idx = $(track_el).attr('data-wpsstm-track-idx');
            
            if ( $(track_el).hasClass('active') ){
                if ( $(track_el).hasClass('playing') ){
                    wpsstm_current_media.pause();
                }else{
                    wpsstm_current_media.play();
                }
            }else{
                wpsstm_init_track(track_idx);
            }

        });
        
        /*
        bottom player
        */
        
        bt_prev_track.click(function(e) {
            e.preventDefault();
            wpsstm_play_previous_track();
        });
        
        bt_next_track.click(function(e) {
            e.preventDefault();
            wpsstm_play_next_track();
        });
        
        //sources block title
        $('#wpsstm-player-sources-header').click(function() {
            $('#wpsstm-player-sources-wrapper').toggleClass('expanded');
        });

        //source item
        $( "#wpsstm-player-sources-wrapper li span.wpsstm-trackinfo-title" ).live( "click", function(e) {
            e.preventDefault();
            
            var li_el = $(this).closest('li');
            
            if ( !li_el.hasClass('wpsstm-active-source') ){ //source switch
                var lis = li_el.closest('ul').find('li');
                var idx = lis.index(li_el);
                wpsstm_switch_track_source(idx);
            }
            
            $('#wpsstm-player-sources-wrapper').toggleClass('expanded');
        });
        
        /*
        timer notice
        */

        bottom_notice_refresh.click(function() {
            
            if ( wpsstm_countdown_s == 0 ) return;
            
            if ( $(this).hasClass('active') ){
                clearInterval(wpsstm_countdown_timer);
            }else{
                wpsstm_redirection_countdown();
            }
            
            $(this).toggleClass('active');
            $(this).find('i.fa').toggleClass('fa-spin');
        });
        
        /*
        track actions
        */
        var wp_auth_notice = $('#wpsstm-bottom-notice-wp-auth');
        
        
        $('.wpsstm-track-action').click(function(e) {
            if (wp_auth_notice.length == 0) return;
            e.preventDefault();
            wp_auth_notice.show();
        });
        
        var lastm_auth_notice = $('#wpsstm-bottom-notice-lastfm-auth');
        
        $('.wpsstm-track-action-lastfm').click(function(e) {
            if (lastm_auth_notice.length == 0) return;
            e.preventDefault();
            lastm_auth_notice.show();
        });
        

    });

    //Confirmation popup is a media is playing and that we leave the page
    
    $(window).bind('beforeunload', function(){
        if (!wpsstm_current_media.paused){
            return wpsstmPlayer.leave_page_text;
        }
    });
    
    /*
    Initialize a track : either play it if it has sources; or get the sources then call this function again (with after_ajax = true)
    */
    
    function wpsstm_init_track(track_idx,after_ajax = false) {
        
        track_idx = Number(track_idx); //cast to number
        if(typeof wpsstm_page_tracks[track_idx] === 'undefined') return; //track does not exists

        //wpsstm_init_track() is called a second time after tracks sources have been populated.  Do not run this code again.
        if (!after_ajax){
            if ( wpsstm_current_track_idx && ( wpsstm_current_track_idx == track_idx ) ) return;
            wpsstm_init_sources_request(track_idx);
        }

        console.log("wpsstm_init_track #" + track_idx);
        
        //skip the current track if any
        wpsstm_end_current_track();

        //new track
        wpsstm_current_track_idx = track_idx;
        
        var track_obj = wpsstm_page_tracks[track_idx];
        var track_el = $(track_obj.row);
        track_el.addClass('active');
        
        //play current track if it has sources
        if (track_obj.sources){
            wpsstm_switch_player(track_idx);
        }else if (track_obj.did_lookup){ //no sources and had lookup
            wpsstm_play_next_track();
        }

    }
    
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
    
    function wpsstm_end_current_track(){

        if (wpsstm_current_track_idx == -1) return;
        
        console.log("wpsstm_end_current_track() #" + wpsstm_current_track_idx);

        var old_track_obj = wpsstm_page_tracks[wpsstm_current_track_idx];
        var old_track_el = $(old_track_obj.row);
        old_track_el.removeClass('active');
        old_track_el.addClass('has-played');
        
        //mediaElement
        if (wpsstm_current_media){
            console.log("there is an active media, abord it");
            
            wpsstm_current_media.pause();
            wpsstm_update_track_button(old_track_obj,'ended');

        }

    }

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
                    if ( data.sources ){
                        console.log("found "+data.sources.length+" sources for track#" + track_idx);
                        wpsstm_page_tracks[track_idx].sources = data.sources;
                        track_el.attr('data-wpsstm-sources',JSON.stringify(data.sources));
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

    function wpsstm_switch_player(track_idx){
        console.log("wpsstm_switch_player()  #" + track_idx);
        
        var track_obj = wpsstm_page_tracks[track_idx];
        var track_el = $(track_obj.row);

        //shortenTable
        /*
        $('.wpsstm-tracklist-list').shortenTable(3);
        var tracklist = track_el.closest('.wpsstm-tracklist');
        var shortened_table = tracklist.find('.shortened-table');
        if ( shortened_table.length > 0){
            var visible_rows = shortened_table.attr('data-visible-rows');
            if (track_idx >= visible_rows){
                shortened_table.shortenTable(track_idx+1,'tbody tr');
            }
        }
        */

        var media_wrapper = $('<audio />');
        media_wrapper.attr({
            id:     'wpsstm-player-audio'
        });
        
        media_wrapper.prop({
            //autoplay:     true,
            //muted:        true
        });

        //create trackinfo
        var trackinfo_wrapper = $('<ul />');
        trackinfo_wrapper.attr({
            //id:    'wpsstm-player-audio',
        });

        $(track_obj.sources).each(function(i, source_attr) {
            //media
            var source_el = $('<source />');
            source_el.attr({
                src:    source_attr.src,
                type:   source_attr.type
            });
            
            media_wrapper.append(source_el);

            //trackinfo
            var trackinfo_el = $('<li />');
            
            if (i==0){
                trackinfo_el.addClass('wpsstm-active-source');
            }

            //source title
            var trackinfo_title_el = $('<span class="wpsstm-trackinfo-title">'+source_attr.title+'</span>');
            trackinfo_el.append(trackinfo_title_el);

            //provider icon
            var trackinfo_link_el = $('<a class="wpsstm-trackinfo-provider-link" href="'+source_attr.src+'" target="_blank">'+source_attr.icon+'</a>');
            trackinfo_el.append(trackinfo_link_el);
            

            
            trackinfo_wrapper.append(trackinfo_el);
        });

        $('#wpsstm-player').html(media_wrapper);
        $('#wpsstm-player-sources').html(trackinfo_wrapper);
        
        //display bottom block if not done yet
        bottom_block.show();
        
        new MediaElementPlayer('wpsstm-player-audio', {
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug: true,
                autoStartLoad: false
            },
            // Do not forget to put a final slash (/)
            pluginPath: 'https://cdnjs.com/libraries/mediaelement/',
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(media, node, player) {
                    console.log("MediaElementPlayer ready");
                
                    wpsstm_player = player;
                    wpsstm_current_media = media;

                    $(wpsstm_current_media).on('error', function(error) {
                        var current_source = $(wpsstm_current_media).find('audio').attr('src');
                        console.log('player event - source error: '+current_source);
                        wpsstm_update_track_button(track_obj,'loadeddata');
                        wpsstm_skip_bad_source(wpsstm_current_media);

                    });

                    $(wpsstm_current_media).on('loadeddata', function() {
                        console.log('player event - loadeddata');
                        wpsstm_update_track_button(track_obj,'loadeddata');
                        wpsstm_player.play();
                        
                    });

                    $(wpsstm_current_media).on('play', function() {
                        console.log('player event - play');
                        wpsstm_update_track_button(track_obj,'play');
                        wpsstm_had_tracks_played = true;
                    });

                    $(wpsstm_current_media).on('pause', function() {
                        console.log('player - pause');
                        wpsstm_update_track_button(track_obj,'pause');
                    });

                    $(wpsstm_current_media).on('ended', function() {
                        console.log('MediaElement.js event - ended');
                        wpsstm_update_track_button(track_obj,'ended');
                        wpsstm_current_media = null;
                        //Play next song if any
                        wpsstm_play_next_track();
                    });

                },error(media) {
                    // Your action when media had an error loading
                    //TO FIX is this required ?
                    console.log("player error");
                }
        });
        
    }
    
    function wpsstm_switch_track_source(idx){
        var new_source = $(wpsstm_current_media).find('audio source').eq(idx);
        
        console.log("wpsstm_switch_track_source() #" + idx);
        console.log(new_source.get(0));
        
        
        var player_url = $(wpsstm_current_media).find('audio').attr('src');
        var new_source_url = new_source.attr('src');

        if (player_url == new_source_url) return false;

        //player
        wpsstm_current_media.pause();
        wpsstm_current_media.setSrc(new_source);
        wpsstm_current_media.load();
        wpsstm_current_media.play();

        //trackinfo
        var trackinfo_sources = $('#wpsstm-player-sources-wrapper li');
        var trackinfo_new_source = trackinfo_sources.eq(idx);
        trackinfo_sources.removeClass('wpsstm-active-source');

        trackinfo_new_source.addClass('wpsstm-active-source');
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

    function wpsstm_skip_bad_source(media){
        console.log("try to get next source or next media");
        
       //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067
        
        var current_source_url = $(media).find('audio').attr('src');
        var source_els = $(media).find('source');
        var source_els_clone = $(media).find('source').clone();
        var new_source_idx = -1;

        source_els_clone.each(function(i, val) {

            var source = $(this);
            var source_url = source.attr('src');
            
            if (!source_url) return true; //continue
            if (source.hasClass('wpsstm-bad-source')) return true; //continue;

            if ( source_url == current_source_url ) {
                
                $(source_els_clone).eq(i).remove(); //remove from loop
                $(source_els).eq(i).addClass('wpsstm-bad-source'); //add class to source
                $('#wpsstm-player-sources-wrapper li').eq(i).addClass('wpsstm-bad-source');//add class to trackinfo source
                
                console.log("skip; is current source: "+source_url);
                return true; //continue
            }
            
            new_source_idx = i;
            return false;  //break

        });
        
        if (new_source_idx > -1){
            wpsstm_switch_track_source(new_source_idx);
        }else{
            
            //No valid source found
            var track_obj = wpsstm_page_tracks[wpsstm_current_track_idx];
            var track_el = $(track_obj.row);
            track_el.addClass('error');

            //No more sources - Play next song if any
            wpsstm_play_next_track();
        }
        
    }
    
    function wpsstm_play_previous_track(){
        var previous_idx = wpsstm_current_track_idx - 1;
        wpsstm_init_track(previous_idx);
    }

    function wpsstm_play_next_track(){
        var next_idx = wpsstm_current_track_idx + 1;
        
        if(typeof wpsstm_page_tracks[next_idx] === 'undefined'){
            console.log("tracklist end");
            wpsstm_redirection_countdown();
        }else{
            wpsstm_init_track(next_idx);
        }

    }

    function wpsstm_redirection_countdown(){
        
        // No tracks have been played on the page.  Avoid infinite redirection loop.
        if ( !wpsstm_had_tracks_played ) return;

        if ( bottom_notice_refresh.length == 0) return;

        var redirect_url = null;
        var redirect_link = bottom_notice_refresh.find('a#wpsstm-bottom-notice-link');

        if (redirect_link.length > 0){
            redirect_url = redirect_link.attr('href');
        }

        bottom_notice_refresh.show();

        var container = bottom_notice_refresh.find('strong');
        var message = "";
        var message_end = "";

        // Get reference to container, and set initial content
        container.html(wpsstm_countdown_s + message);

        if ( wpsstm_countdown_s <= 0) return;

        // Get reference to the interval doing the countdown
        wpsstm_countdown_timer = setInterval(function () {
            container.html(wpsstm_countdown_s + message);
            // If seconds remain
            if (--wpsstm_countdown_s) {
                // Update our container's message
                container.html(wpsstm_countdown_s + message);
            // Otherwise
            } else {
                wpsstm_countdown_s = 0;
                // Clear the countdown interval
                clearInterval(wpsstm_countdown_timer);
                // Update our container's message
                container.html(message_end);

                // And fire the callback passing our container as `this`
                console.log("redirect to:" + redirect_url);
                window.location = redirect_url;
            }
        }, 1000); // Run interval every 1000ms (1 second)
    }

    
})(jQuery);
