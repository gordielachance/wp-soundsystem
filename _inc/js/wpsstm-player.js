var wpsstm_player;
var wpsstm_current_media;
var wpsstm_current_source;
var wpsstm_current_bt;
var page_buttons;

(function($){

    $(document).ready(function(){
        
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
                    source_el.attr({
                        src:    source_attr.src,
                        type:   source_attr.type
                    });
                    media.append(source_el);
                    console.log(source_el[0]);
                });

                $('#wpsstm-bottom-player').html(media);

                new MediaElementPlayer('wpsstm-bottom-player-audio', {
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
                            wpsstm_current_media = media;
                            wpsstm_player = player;

                            $(wpsstm_current_media).on('loadeddata', function() {
                                console.log('MediaElement.js event - loadeddata');
                                wpsstm_current_source = wpsstm_player.media.getSrc();
                                console.log(wpsstm_current_source);
                            });
                        
                            $(wpsstm_current_media).on('canplay', function() {
                                console.log('MediaElement.js event - canplay');
                                wpsstm_current_media.play();
                            });

                            $(wpsstm_current_media).on('play', function() {
                                console.log('MediaElement.js event - play');
                                    $(wpsstm_current_bt).addClass('playing');
                                    $(wpsstm_current_bt).removeClass('buffering ended');
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
                            console.log("player error");
                        }
                });
                
            }else if ( wpsstm_current_media ){
                
                console.log("media toggle play/pause");

                if ( wpsstm_current_media.paused ){
                    wpsstm_player.play();
                }else{
                    wpsstm_player.pause();
                }
                
            }


        });
        
        //autoplay first track
        console.log("autoplay first track");
        var first_button = $(page_buttons).first();
        first_button.trigger('click');
        
      
    });  
})(jQuery);

function wpsstm_nav_previous(){
    console.log('wpsstm_nav_previous()');
    var previous_track_link = jQuery('#wpsstm-player .nav-previous a');
    if ( previous_track_link.length ){
        var url = previous_track_link.attr('href');
        window.location.replace(url);
    }
}
