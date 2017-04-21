/*
Init Provider
*/
function Player_Provider_Youtube() {
  Player_Provider.call(this, 'youtube');
}

Player_Provider_Youtube.prototype = Object.create(Player_Provider.prototype); //inherit methods
Player_Provider_Youtube.prototype.constructor = Player_Provider_Youtube; //fix constructor

wpsstm_providers.youtube = new Player_Provider_Youtube();

/*
Extract video ID from Youtube URL
http://stackoverflow.com/questions/3452546/javascript-regex-how-do-i-get-the-youtube-video-id-from-a-url
*/
Player_Provider_Youtube.prototype.getVideoID = function(url) {
    var regExp = /.*(?:youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#\&\?]*).*/;
    var match = url.match(regExp);
    return (match&&match[1].length==11)? match[1] : false;
}

Player_Provider_Youtube.prototype.loadUrl = function(url) {
    
    Player_Provider.prototype.loadUrl.call(this, url);
    
    var video_id = this.getVideoID(url);
    if (wpsstm.debug) console.log("Youtube video ID: " + video_id);
    
    //Youtube API
    this.player.loadVideoById(video_id);
}

Player_Provider_Youtube.prototype.play = function() {
    Player_Provider.prototype.play.call(this);
    
    this.player.playVideo();
}

Player_Provider_Youtube.prototype.pause = function(url) {
    
    Player_Provider.prototype.pause.call(this);
    
    this.player.pauseVideo();
}

Player_Provider_Youtube.prototype.jumpTo = function(time) {
    
    Player_Provider.prototype.jumpTo.call(this, time);
    
    this.player.seekTo(time,true);
}

Player_Provider_Youtube.prototype.mute = function() {
    
    Player_Provider.prototype.mute.call(this);
    
    this.player.mute();
}

Player_Provider_Youtube.prototype.unMute = function() {
    
    Player_Provider.prototype.unMute.call(this);
    
    this.player.unMute();
}



/*
API init
*/

function onYouTubeIframeAPIReady() {
  wpsstm_providers.youtube.player = new YT.Player(wpsstm_providers.youtube.iframe_id, {
    events: {
      'onReady': Player_Provider_Youtube_onReady,
      'onStateChange': Player_Provider_Youtube_onStateChange
    }
  });
}

Player_Provider_Youtube_onReady = function(event) {
    var provider = wpsstm_providers.youtube;
    if (wpsstm.debug) console.log(provider.slug + " player is ready !");
    provider.time_total = provider.player.getDuration();
    if (wpsstm.debug) console.log("time_total: " + provider.time_total);
};

Player_Provider_Youtube_onStateChange = function(event) {
    
    var provider = wpsstm_providers.youtube;
    
    switch (event.data){
        case YT.PlayerState.ENDED:
            Player_Provider.prototype.onStateChange.call(provider,'ended');
        break;
        case YT.PlayerState.PLAYING:
            Player_Provider.prototype.onStateChange.call(provider,'playing');
        break;
        case YT.PlayerState.PAUSED:
            Player_Provider.prototype.onStateChange.call(provider,'paused');
        break;
        case YT.PlayerState.BUFFERING:
            Player_Provider.prototype.onStateChange.call(provider,'buffering');
        break;
        case YT.PlayerState.CUED:
            Player_Provider.prototype.onStateChange.call(provider,'cued');
        break;
    }
    /*
    //progress bar
    if (event.data == YT.PlayerState.PLAYING) {
        Player_Provider.prototype.onStateChange.call(provider,'playing');
        //progress(true);
    } else {
        //progress(false);
    }

    if(event.data == YT.PlayerState.ENDED) {          
        if (wpsstm.debug) console.log("WP SoundSystem - Youtube player - video finished");
        wpsstm_player_ended();
    }
    */
}

var wpsstm_player_youtube;
var progressBarTimer;

function progress(enabled){
    
    if(typeof enabled === 'undefined') enabled = true;
    
    if (enabled){
        progressBarTimer = setInterval(function() {
            player_item_time_current = wpsstm_providers.youtube.player.getCurrentTime();
            player_item_time_percent = (player_item_time_current / player_item_time_total) * 100;
            wpsstm_player_update_time();
        }, 1000);   
    }else{
        clearTimeout(progressBarTimer);
    }


}



