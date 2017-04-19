var yt_player;
var progressBarTimer;

function onYouTubeIframeAPIReady() {
  yt_player = new YT.Player('wpsstm-player-iframe-youtube', {
    events: {
      'onReady': providerReady,
      'onStateChange': onPlayerStateChange
    }
  });
}

function providerReady(event) {
    if (wpsstm.debug) console.log("WP SoundSystem - Youtube - providerReady()");
    player_item_time_total = yt_player.getDuration();
}

function providerPlay() {
    if (wpsstm.debug) console.log("WP SoundSystem - Youtube - providerPlay()");
    yt_player.playVideo();
}

function providerPause() {
    if (wpsstm.debug) console.log("WP SoundSystem - Youtube - providerPause()");
    yt_player.pauseVideo();
}

function providerJumpTo(time){
    if (wpsstm.debug) console.log("WP SoundSystem - Youtube - providerJumpTo():" + time);
    yt_player.seekTo(time,true);
}

function providerMute(){
    if (wpsstm.debug) console.log("WP SoundSystem - Youtube - providerMute()");
    yt_player.mute();
}
function providerUnMute(){
    if (wpsstm.debug) console.log("WP SoundSystem - Youtube - providerUnMute()");
    yt_player.unMute();
}

function progress(enabled){
    
    if(typeof enabled === 'undefined') enabled = true;
    
    if (enabled){
        progressBarTimer = setInterval(function() {
            player_item_time_current = yt_player.getCurrentTime();
            player_item_time_percent = (player_item_time_current / player_item_time_total) * 100;
            wpsstm_player_update_time();
        }, 1000);   
    }else{
        clearTimeout(progressBarTimer);
    }


}

// when video ends
function onPlayerStateChange(event) {
    
    //progress bar
    if (event.data == YT.PlayerState.PLAYING) {
        progress(true);
    } else {
        progress(false);
    }

    if(event.data == YT.PlayerState.ENDED) {          
        if (wpsstm.debug) console.log("WP SoundSystem - Youtube player - video finished");
        wpsstm_player_ended();
    }
}

