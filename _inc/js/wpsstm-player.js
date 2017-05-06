var wpsstm_player;
var wpsstm_player_do_play;
var wpsstm_current_media;
var wpsstm_current_source;
var wpsstm_current_bt;
var page_buttons;

(function($){
    
    //tests
    /*
    $(document).ready(function(){
        console.log("docready");
        track_players = $('audio.wpsstm-track-player');
        
        $(track_players).mediaelementplayer({
            pauseOtherPlayers: false,
            features: ['playpause','sourcechooser'],
            success: function (player, node) {
                $(player).removeClass('buffering');
              player.pause();
             }
        });
        
        
        
    });
    */
    $(document).ready(function(){
        
        track_players = $('audio.wpsstm-track-player');
        console.log(track_players);

        
        page_buttons = $( "[data-wpsstm-sources]" );

        page_buttons.live( "click", function(e) {
            
            e.preventDefault();
            
            //new button clicked
            if ( ( !wpsstm_current_bt ) || ( ( wpsstm_current_bt ) && ( !$(wpsstm_current_bt).is(this) ) ) ){
                
                if (wpsstm_current_bt){
                    $(wpsstm_current_media).trigger('pause');
                    page_buttons.removeClass('active buffering playing');
                }
                
                /*
                if (wpsstm_player){
                    if (!wpsstm_player.paused) {
                            wpsstm_player.pause();	
                    }

                    wpsstm_player.remove();
                    wpsstm_player = null;
                }
                */
                

                //define as new bt
                wpsstm_current_bt = $(this);
                wpsstm_current_bt.addClass('active buffering');

                console.log("clicked a new track button, create new player");

                //sources
                var sources_json = $(this).attr('data-wpsstm-sources');
                var sources = JSON.parse(sources_json);

                //create media
                var media = $('<audio />');
                media.attr({
                    id:    'wpsstm-bottom-player-audio',
                });
                
                $(sources).each(function(i, source_attr) {
                    var source_el = $('<source />');
                    var source_url = source_attr.src; //TO FIX problems with special chars here
                    source_el.attr({
                        src:    source_url,
                        type:   source_attr.type
                    });
                    media.append(source_el);
                    console.log(source_el[0]);
                });

                $('#wpsstm-bottom-player').html(media);

                new MediaElementPlayer('wpsstm-bottom-player-audio', {
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
                            console.log("player ready");
                            wpsstm_player = player;
                            
                            console.log("media ready");
                            wpsstm_current_media = media;
                            console.log(wpsstm_current_media);
                            
                        
                            $(wpsstm_current_media).on('error', function(error) {
                                console.log('MediaElement.js event - error: ');
                                console.log(error);
                                $(wpsstm_current_bt).addClass('error');
                                
                                console.log("do_play status: "+wpsstm_player_do_play);
                                if (wpsstm_player_do_play){
                                    console.log("try to get next source or next media");
                                    
                                    //https://github.com/mediaelement/mediaelement/issues/2179#issuecomment-297090067
                                    var mediaFiles = node.childNodes;
                                    for (var i = 0, total = mediaFiles.length; i < total; i++) {
                                        if (mediaFiles[i].nodeType !== Node.TEXT_NODE &&
                                            mediaFiles[i].tagName.toLowerCase() === 'source' && media.getSrc() !== mediaFiles[i].getAttribute('src')) {
                                            media.setSrc(mediaFiles[i].getAttribute('src'));
                                            media.load();
                                            media.play();
                                            break;
                                        }
                                    }
                                    
                                }
                                
                            });

                            $(wpsstm_current_media).on('loadeddata', function() {
                                console.log('MediaElement.js event - loadeddata');
                                wpsstm_current_source = wpsstm_player.media.getSrc();
                                console.log(wpsstm_current_source);
                                
                            });

                            $(wpsstm_current_media).on('play', function() {
                                console.log('MediaElement.js event - play');
                                $(wpsstm_current_bt).addClass('playing');
                                $(wpsstm_current_bt).removeClass('error buffering ended');
                            });

                            $(wpsstm_current_media).on('pause', function() {
                                console.log('MediaElement.js event - pause');
                                $(wpsstm_current_bt).removeClass('playing');
                            });

                            $(wpsstm_current_media).on('ended', function() {

                                console.log('MediaElement.js event - ended');
                                $(wpsstm_current_bt).removeClass('playing active');

                                //Play next song if any

                                var bt_index = $( "[data-wpsstm-sources]" ).index( wpsstm_current_bt );
                                var bt_new_index = bt_index + 1;
                                var next_bt = $(page_buttons).get(bt_new_index);

                                if ( $(next_bt).length ){ //there is more tracks
                                    console.log('MediaElement.js - simulate click on button #' + bt_new_index);
                                    //fire
                                    $(next_bt).trigger( "click" );
                                }else{
                                    console.log('MediaElement.js - playlist finished');
                                }

                            });

                        },error(media) {
                            // Your action when media had an error loading
                            //TO FIX is this required ?
                            console.log("player error");
                        }
                });
                
            }
            
            if ( wpsstm_current_media ){
                wpsstm_toggle_playpause(wpsstm_current_media);
            }


        });
        
        //autoplay first track
        console.log("autoplay first track");
        var first_button = $(page_buttons).first();
        first_button.trigger('click');
        
      
    });
})(jQuery);

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

function wpsstm_nav_previous(){
    console.log('wpsstm_nav_previous()');
    var previous_track_link = jQuery('#wpsstm-player .nav-previous a');
    if ( previous_track_link.length ){
        var url = previous_track_link.attr('href');
        window.location.replace(url);
    }
}
