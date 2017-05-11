var bottom_block;
var bottom_notice_refresh;
var wpsstm_player;
var wpsstm_player_do_play;
var wpsstm_current_media;
var wpsstm_current_bt = null;
var wpsstm_countdown_s = wpsstmPlayer.autoredirect; //seconds for the redirection notice
var wpsstm_countdown_timer; //redirection timer
var wpsstm_preload_max = 5;

var wpsstm_page_tracks = [];
var wpsstm_current_track_idx = -1;
var wpsstm_source_requests = [];

(function($){

    $(document).ready(function(){
        
        /*
        bottom block
        */

        bottom_block = $('#wpsstm-bottom');
        bt_prev_track = $('#wpsstm-player-nav-previous-track');
        bt_next_track = $('#wpsstm-player-nav-next-track');
        bottom_notice_refresh = $('#wpsstm-bottom-notice-redirection');
        
        //timer notice
        if (wpsstm_countdown_timer > 0){
            bottom_notice_refresh.addClass('active');
            $(this).find('i.fa').toggleClass('fa-spin');
        }

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
        
        /* tracklist */
        
        //prepare tracks queue
        $( ".wpsstm-play-track" ).each(function(i, source_attr) {
            
            var track_el = $(this).closest('tr');
            var track_bt_wrapper = track_el.find('.trackitem_play_bt');
            var track_bt = track_bt_wrapper.find('a.wpsstm-play-track');
            track_bt.attr('data-wpsstm-track-idx',i);

            var track = {
                row:        track_el.get(0),
                artist:     track_el.find('.trackitem_artist').text(),
                title:      track_el.find('.trackitem_track').text(),
                album:      track_el.find('.trackitem_album').text(),
                sources:    null
            }
            
            //get sources from HTML if any
            var sources = $(this).attr('data-wpsstm-sources');
            if (sources) {
                track.sources = JSON.parse(sources);
            }

            wpsstm_page_tracks.push(track);
            
        });
        
        //autoplay first track
        if ( wpsstmPlayer.autoplay ){
            var first_track = wpsstm_page_tracks[0];
            console.log("autoplay first track");
            wpsstm_init_track(0);
        }

        //track buttons
        $( ".wpsstm-play-track" ).live( "click", function(e) {
            e.preventDefault();
            var track_bt = this;
            var track_idx = $(track_bt).attr('data-wpsstm-track-idx');
            wpsstm_init_track(track_idx);
        });
        
        bt_prev_track.click(function(e) {
            e.preventDefault();
            wpsstm_play_previous_track();
        });
        
        bt_next_track.click(function(e) {
            e.preventDefault();
            wpsstm_play_next_track();
        });

    });
    
    /*
    Play or preload + play track
    */
    
    function wpsstm_init_track(track_idx) {
        
        track_idx = Number(track_idx); //cast to number
        
        //we already did try to init that track
        if ( wpsstm_current_track_idx && ( wpsstm_current_track_idx == track_idx ) ) return;
        
        console.log("wpsstm_init_track #" + track_idx);
        
        //skip the current track if any
        wpsstm_skip_current_track();

        //new track
        wpsstm_current_track_idx = track_idx;
        
        var track_obj = wpsstm_page_tracks[track_idx];
        var track_el = $(track_obj.row);
        var track_bt_wrapper = track_el.find('.trackitem_play_bt');
        var track_bt = track_bt_wrapper.find('a.wpsstm-play-track');
        track_bt.addClass('active');
        
        //current track
        if (track_obj.sources){
            wpsstm_switch_player(track_idx);
        }else{
            wpsstm_source_requests.getTrackSourceRequest(track_idx);
        }
        
        //get sources for following tracks if needed
        wpsstm_source_requests.cancelTrackSourceRequests(track_idx); //abord current requests
        
        var following_tracks = $(wpsstm_page_tracks).slice(track_idx+1,track_idx+5);
        following_tracks.each(function(i, val) {
            var following_track_idx = track_idx + 1 + i;
            var track_obj = wpsstm_page_tracks[following_track_idx];
            if (!track_obj.sources){
                wpsstm_source_requests.getTrackSourceRequest(following_track_idx);
            }
        });
    }
    
    //http://stackoverflow.com/questions/42271167/break-out-of-ajax-loop
    wpsstm_source_requests.getTrackSourceRequest = function(track_idx) {
        var xhr = wpsstm_get_track_sources(track_idx);
        wpsstm_source_requests.push(xhr);

        xhr.fail(function(data, textStatus, jqXHR) {
            wpsstm_source_requests.handleError(data, textStatus, jqXHR);
        })
        .done(function(data) {
            //track could have been switched since, so check if this is still the track to play
            if (wpsstm_current_track_idx == track_idx){
                wpsstm_current_track_idx = -1;
                wpsstm_init_track(track_idx);
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
    
    wpsstm_source_requests.handleError = function(data, textStatus, jqXHR) {
        console.log("error");
        console.log(data);
    };
    
    function wpsstm_skip_current_track(){

        if (wpsstm_current_track_idx == -1) return;
        
        console.log("wpsstm_skip_current_track() #" + wpsstm_current_track_idx);

        var old_track_obj = wpsstm_page_tracks[wpsstm_current_track_idx];
        var old_track_el = $(old_track_obj.row);
        var old_track_bt_wrapper = old_track_el.find('.trackitem_play_bt');
        var old_track_bt = old_track_bt_wrapper.find('a.wpsstm-play-track');
        old_track_bt.removeClass('active');
        old_track_bt.addClass('has-played');
        
        //mediaElement
        if (wpsstm_current_media){
            wpsstm_current_media.pause();
        }


    }

    function wpsstm_get_track_sources(track_idx) {
        
        console.log("wpsstm_get_track_sources(): " + track_idx);
        
        var track_obj = wpsstm_page_tracks[track_idx];
        var track_el = $(track_obj.row);
        var track_bt_wrapper = track_el.find('.trackitem_play_bt');
        var track_bt = track_bt_wrapper.find('a.wpsstm-play-track');
        
        var track = {
            artist: track_obj.artist,
            title:  track_obj.title,
            album:  track_obj.album
        }
        
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
                track_bt.addClass('buffering');
            },
            success: function(data){
                if (data.success === false) {
                    track_bt.addClass('error');
                    console.log("error getting sources for track#" + track_idx);
                    console.log(data);
                }else{
                    if ( data.sources ){
                        console.log("found sources for track#" + track_idx);
                        console.log(data.sources);
                        wpsstm_page_tracks[track_idx].sources = data.sources;
                        track_bt.attr('data-wpsstm-sources',JSON.stringify(data.sources));
                    }
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(thrownError);
            },
            complete: function() {
                track_bt.removeClass('buffering');
            }
        })
        
        
    }

    function wpsstm_switch_player(track_idx){
        console.log("wpsstm_switch_player()  #" + track_idx);
        
        var track_obj = wpsstm_page_tracks[track_idx];
        var track_el = $(track_obj.row);
        var track_bt_wrapper = track_el.find('.trackitem_play_bt');
        var track_bt = track_bt_wrapper.find('a.wpsstm-play-track');

        track_bt.addClass('buffering');
        $('.wpsstm-tracklist-list').shortenTable(3);
        
        //shortenTable
        /*
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
            id:    'wpsstm-player-audio',
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
            var trackinfo_link_el = $('<a />');
            trackinfo_link_el.html(source_attr.title);
            trackinfo_link_el.attr({
                href:    source_attr.src
            });
            trackinfo_el.append(trackinfo_link_el);
            //trackinfo_wrapper.append(trackinfo_el.clone());
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
            features: ['playpause','loop','progress','current','duration','volume','sourcechooser'],
            loop: false,
            success: function(media, node, player) {
                    console.log("MediaElementPlayer ready");
                
                    wpsstm_player = player;
                    wpsstm_current_media = media;

                    $(wpsstm_current_media).on('error', function(error) {
                        
                        track_bt.addClass('error');
                        
                        console.log('Player error: ');
                        console.log(error);

                        console.log("do_play status: "+wpsstm_player_do_play);
                        
                        if (wpsstm_player_do_play){
                            wpsstm_play_next_source(media, node, player);
                        }

                    });

                    $(wpsstm_current_media).on('loadeddata', function() {
                        console.log('player - loadeddata');
                        player.play();
                    });

                    $(wpsstm_current_media).on('play', function() {
                        console.log('player - play');
                        track_bt.addClass('playing');
                        track_bt.removeClass('error buffering ended');
                    });

                    $(wpsstm_current_media).on('pause', function() {
                        console.log('player - pause');
                        track_bt.removeClass('playing');
                    });

                    $(wpsstm_current_media).on('ended', function() {

                        console.log('MediaElement.js event - ended');
                        track_bt.removeClass('playing');
                        track_bt.addClass('has-played');

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
    
    function wpsstm_play_next_source(media, node, player){
        console.log("try to get next source or next media");
        
       //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067

        var mediaFiles = node.childNodes;
        media.addEventListener('error', function (e) {
            for (var i = 0, total = mediaFiles.length; i < total; i++) {
                if (mediaFiles[i].nodeType !== Node.TEXT_NODE &&
                    mediaFiles[i].tagName.toLowerCase() === 'source' && media.getSrc() !== mediaFiles[i].getAttribute('src')) {
                    media.pause();
                    media.setSrc(mediaFiles[i].getAttribute('src'));
                    media.load();
                    media.play();
                    mediaFiles[i].remove();
                    break;
                }
            }
        });

        //No more sources - Play next song if any
        wpsstm_play_next_track();
        
    }
    
    function wpsstm_play_previous_track(){
        var previous_idx = wpsstm_current_track_idx - 1;
        wpsstm_init_track(previous_idx);
    }

    function wpsstm_play_next_track(){
        var next_idx = wpsstm_current_track_idx + 1;
        wpsstm_init_track(next_idx);
    }

    function wpsstm_toggle_playpause(media){

        if (media.paused !== null) {
            wpsstm_player_do_play = media.paused;
        }else{
            wpsstm_player_do_play = true;
        }

        console.log("wpsstm_toggle_playpause - doplay: " + wpsstm_player_do_play);

        if ( wpsstm_player_do_play ){
            wpsstm_player.play();
        }else{
            wpsstm_player.pause();
        }

    }

    function wpsstm_redirection_countdown(){

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