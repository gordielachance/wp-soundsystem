(function($){

  $(document).ready(function(){
      console.log("soundcloud provider loaded");

  });  
})(jQuery);

var soundcloud_iframe = document.getElementById('wpsstm-iframe-soundcloud');
var soundcloud_player = SC.Widget(soundcloud_iframe);

soundcloud_player.bind(SC.Widget.Events.READY, function() {
    console.log("WP SoundSystem - Soundcloud player - ready");
    soundcloud_player.play();
});

soundcloud_player.bind(SC.Widget.Events.FINISH, function() {        
    console.log("WP SoundSystem - Soundcloud player - finished");
    wpsstm_nav_previous();
});
