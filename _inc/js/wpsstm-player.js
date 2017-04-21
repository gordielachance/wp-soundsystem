var wpsstm_active_provider; //provider slug
var wpsstm_active_bt; //bt playing song
var wpsstm_active_row; //row playing song
var wpsstm_active_source; //source url
var wpsstm_current_state; //player current state

var wpsstm_player;
var wpsstm_player_toggle_play_bt;
var wpsstm_player_toggle_sound_bt;
var wpsstm_player_progress_wrapper;
var wpsstm_player_progress_bar;
var player_item_time_total; //duration time
var player_item_time_current; //time already played
var player_item_time_percent; //percent of item played

(function($){

    $(document).ready(function(){

        //bottom player
        wpsstm_player = $('#wpsstm-bottom-player');
        wpsstm_player.find('#wpsstm-player-widgets').tabs();
        
        var tabs = wpsstm_player.find('#wpsstm-player-tabs li');
        
        $(tabs).click(function() {
            var new_provider = $(this).attr('data-provider');
            console.log("switch to provider: " + new_provider);
            wpsstm_active_bt.initTrack(new_provider);
        });

        //progress
        /*
        wpsstm_player_progress_wrapper = wpsstm_player.find('.wpsstm-player-progress');
        wpsstm_player_progress_bar = wpsstm_player_progress_wrapper.find('.wpsstm-player-progress-bar');

        wpsstm_player_progress_wrapper.click(function(e) {
            var percent = e.offsetX/ $(this).width() * 100;
            wpsstm_player_jump_to(percent);
        });
        */

        //player play bt
        wpsstm_player_play_bt = wpsstm_player.find('.wpsstm-player-control .wpsstm-player-icon-play');
        wpsstm_player_play_bt.click(function() {
            
            if (!wpsstm_active_bt){
                var first_page_button = $('.wpsstm-play-track').first();
                first_page_button.initTrack();
            }
            
            wpsstm_providers[wpsstm_active_provider].play();
            
        });
        
        //player pause bt
        wpsstm_player_pause_bt = wpsstm_player.find('.wpsstm-player-control .wpsstm-player-icon-pause');
        wpsstm_player_pause_bt.click(function() {
            wpsstm_providers[wpsstm_active_provider].pause();
        });

        //player toggle sound on-off
        wpsstm_player_toggle_sound_bt = wpsstm_player.find('.wpsstm-player-control-togglesound');
        wpsstm_player_toggle_sound_bt.click(function() {
            var is_on = $(this).hasClass('wpsstm-player-control-toggle-on');

            if (!is_on){
                providerMute();
                wpsstm_player_do_toggle_sound(true);
            }else{
                providerUnMute();
                wpsstm_player_do_toggle_sound(false);
            }
        });
        
        //page play buttons
        $( "a.wpsstm-play-track" ).live( "click", function(e) {
            e.preventDefault();
            $(this).initTrack();
        });
      
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


function wpsstm_player_jump_to(percent) {
    console.log("wpsstm_player_jump_to() : " + percent);
    
    player_item_time_percent = percent;
    wpsstm_player_animate_progress_bar();
    
    var jumpTo = player_item_time_total /100 * percent;
    providerJumpTo(jumpTo);
}

function wpsstm_player_update_time(){
    player_item_time_percent = (player_item_time_current / player_item_time_total) * 100;
    wpsstm_player_animate_progress_bar();
}

function wpsstm_player_animate_progress_bar() {
    wpsstm_player_progress_bar.animate({ width: player_item_time_percent + '%' });
}


(function( $ ){
    
    $.fn.getProvider = function(provider_slug) {

        //get link sources
        var sources_json = $(this).attr('data-wpsstm-sources');
        var sources = JSON.parse(sources_json);

        var new_provider = null;
        
        if(typeof provider_slug !== 'undefined'){
            new_provider = provider_slug;
        }else{
            //get first provider that has as source
            console.log("choose best provider");
            if (wpsstm_active_provider){
                new_provider = wpsstm_active_provider;
            }else{
                $.each( wpsstm_providers, function( i, provider ) {
                    if (typeof sources[provider.slug] !== 'undefined') {
                        new_provider = provider.slug;
                        return false; //break
                    }
                });
            }
        }
        
        console.log("new provider: " + new_provider);
        
        return new_provider;
    }
    
    $.fn.initTrack = function(provider_slug) {

        //pause current provider if any
        if (wpsstm_active_bt){
            wpsstm_providers[wpsstm_active_provider].pause();
            wpsstm_current_state = null;
        }

        //set global
        wpsstm_active_bt = $(this);
        wpsstm_active_row = wpsstm_active_bt.closest('tr');

        if (wpsstm_current_state != 'playing'){ //is not playing yet
            
            //get provider
            var new_provider = wpsstm_active_bt.getProvider(provider_slug);
            if (!new_provider) return false;
            wpsstm_active_provider = new_provider;
            console.log("active provider :" + wpsstm_active_provider);

            //get source
            var sources_json = wpsstm_active_bt.attr('data-wpsstm-sources');
            var sources = JSON.parse(sources_json);
            console.log("sources");
            console.log(sources);

            if (typeof sources[wpsstm_active_provider] === 'undefined') return;
            var new_source = sources[wpsstm_active_provider];
            if (!new_source) return false;

            if ( wpsstm_active_source != new_source ){
                wpsstm_providers[wpsstm_active_provider].loadUrl(new_source);
                wpsstm_active_source = new_source;
                console.log("active source :" + wpsstm_active_source);;
            }
            
        }


        if (wpsstm_current_state == 'playing'){ // pause
        }else{ //play

            //stop old provider if it is playing

            //start new provider

            if (wpsstm_active_provider){
                wpsstm_providers[wpsstm_active_provider].play();
            }else{

            }

        }

       }; 
})( jQuery );
