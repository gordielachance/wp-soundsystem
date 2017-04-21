/*
Init Provider
*/
function Player_Provider_Soundcloud() {
  Player_Provider.call(this, 'soundcloud');
}

Player_Provider_Soundcloud.prototype = Object.create(Player_Provider.prototype); //inherit methods
Player_Provider_Soundcloud.prototype.constructor = Player_Provider_Soundcloud; //fix constructor

wpsstm_providers.soundcloud = new Player_Provider_Soundcloud();

var soundcloud_iframe = document.getElementById(wpsstm_providers.soundcloud.soundcloud_iframe);
wpsstm_providers.soundcloud.player = SC.Widget(soundcloud_iframe);
console.log(wpsstm_providers.soundcloud.player);




/*(function($){

  $(document).ready(function(){
      console.log("soundcloud provider loaded");

  });  
})(jQuery);

var soundcloud_iframe = document.getElementById('wpsstm-player-soundcloud-iframe');
var wpsstm_player_soundcloud = SC.Widget(soundcloud_iframe);

wpsstm_player_soundcloud.bind(SC.Widget.Events.READY, function() {
    console.log("WP SoundSystem - Soundcloud player - ready");
    wpsstm_player_soundcloud.play();
});

wpsstm_player_soundcloud.bind(SC.Widget.Events.FINISH, function() {        
    console.log("WP SoundSystem - Soundcloud player - finished");
    wpsstm_nav_previous();
});
*/
