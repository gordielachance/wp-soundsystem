var yt_player;
var progressBarTimer;

function onYouTubeIframeAPIReady() {
  yt_player = new YT.Player('wpsstm-iframe-youtube', {
    events: {
      'onReady': providerReady,
      'onStateChange': onPlayerStateChange
    }
  });
}

function providerReady(event) {
    player_item_time_total = yt_player.getDuration();
    console.log("WP SoundSystem - Youtube - providerReady()");
}

function providerPlay() {
    console.log("WP SoundSystem - Youtube - providerPlay()");
    yt_player.playVideo();
}

function providerPause() {
    console.log("WP SoundSystem - Youtube - providerPause()");
    yt_player.pauseVideo();
}

function providerJumpTo(time){
    yt_player.seekTo(time,true);
}

function providerMute(){
    yt_player.mute();
    console.log("WP SoundSystem - Youtube - providerMute()");
}
function providerUnMute(){
    yt_player.unMute();
    console.log("WP SoundSystem - Youtube - providerUnMute()");
}

// when video ends
function onPlayerStateChange(event) {
    
    //progress bar
    if (event.data == YT.PlayerState.PLAYING) {

      progressBarTimer = setInterval(function() {
          
        player_item_time_current = yt_player.getCurrentTime();
        player_item_time_percent = (player_item_time_current / player_item_time_total) * 100;
          
        wpsstm_player_progress_update_bar();
          
      }, 1000);   
        
        
    } else {
      clearTimeout(progressBarTimer);
    }

    if(event.data == YT.PlayerState.ENDED) {          
        console.log("WP SoundSystem - Youtube player - video finished");
        wpsstm_player_ended();
    }
}

