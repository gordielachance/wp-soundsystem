(function($){

  $(document).ready(function(){
      console.log("youtube provider loaded");

  });  
})(jQuery);

var yt_player;
function onYouTubeIframeAPIReady() {
  yt_player = new YT.Player('wpsstm-iframe-youtube', {
    events: {
      'onReady': onPlayerReady,
      'onStateChange': onPlayerStateChange
    }
  });
}

function onPlayerReady(event) {
    console.log("WP SoundSystem - Youtube player - ready; force autoplay");
    // autoplay video
        event.target.playVideo();
}

// when video ends
function onPlayerStateChange(event) {        
    if(event.data === 0) {          
        console.log("WP SoundSystem - Youtube player - video finished");
        wpsstm_nav_previous();
    }
}

