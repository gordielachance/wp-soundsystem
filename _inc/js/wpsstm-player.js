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

    //play buttons
    $( "a.wpsstm-play-track" ).live( "click", function(e) {
        e.preventDefault();
        console.log("play!");
        var sources_json = $(this).attr('data-wpsstm-sources');
        var sources = JSON.parse(sources_json);
        console.log(sources);
    });
      
      
      wpsstm_player = $('#wpsstm-bottom-player');

      wpsstm_player.find('#wpsstm-player-widgets').tabs();

      //progress
      wpsstm_player_progress_wrapper = wpsstm_player.find('.wpsstm-player-progress');
      wpsstm_player_progress_bar = wpsstm_player_progress_wrapper.find('.wpsstm-player-progress-bar');
      
        wpsstm_player_progress_wrapper.click(function(e) {
            var percent = e.offsetX/ $(this).width() * 100;
            wpsstm_player_jump_to(percent);
        });
      

        //toggle play-pause
        wpsstm_player_toggle_play_bt = wpsstm_player.find('.wpsstm-player-control-toggleplay');
        wpsstm_player_toggle_play_bt.click(function() {
            var is_on = $(this).hasClass('wpsstm-player-control-toggle-on');
            
            if (!is_on){
                providerPlay();
                wpsstm_player_do_toggle_play(true);
            }else{
                providerPause();
                wpsstm_player_do_toggle_play(false);
            }
        });
      
        //toggle sound on-off
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

function wpsstm_player_do_toggle_play(enable){
    console.log("wpsstm_player_do_toggle_play : " + enable);
    wpsstm_player_toggle_play_bt.toggleClass( 'wpsstm-player-control-toggle-on', enable );
}

function wpsstm_player_do_toggle_sound(enable){
    console.log("wpsstm_player_do_toggle_sound : " + enable);
    wpsstm_player_toggle_sound_bt.toggleClass( 'wpsstm-player-control-toggle-on', enable );
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

function wpsstm_player_ended() {
    console.log("wpsstm_player_ended");
    wpsstm_player_do_toggle_play(false);
    
    player_item_time_percent = 0;
    wpsstm_player_animate_progress_bar();
    
    //wpsstm_nav_previous();
}
