/*
Init Provider
*/
function Player_Provider_Mixcloud() {
  Player_Provider.call(this, 'mixcloud');
}

Player_Provider_Mixcloud.prototype = Object.create(Player_Provider.prototype); //inherit methods
Player_Provider_Mixcloud.prototype.constructor = Player_Provider_Mixcloud; //fix constructor

wpsstm_providers.mixcloud = new Player_Provider_Mixcloud();
wpsstm_providers.mixcloud.player = SC.Widget(wpsstm_providers.mixcloud.iframe_id);

/*
var wpsstm_player_mixcloud = Mixcloud.PlayerWidget(iframe);
progressBarTimer = false;

wpsstm_player_mixcloud.ready.then(function() {
    providerReady();
});


function providerReady() {
    console.log("WP SoundSystem - Mixcloud - providerReady()");
    return;//TO FIX
    wpsstm_player_mixcloud.getDuration().then(function(value) {
        player_item_time_total = value;
        if (wpsstm.debug){
            console.log(player_item_time_total);
        }
    });
    
    wpsstm_player_mixcloud.events.progress.on(MixcloudEventProgress);
    wpsstm_player_mixcloud.events.play.on(providerPlay);
    wpsstm_player_mixcloud.events.pause.on(providerPause);
    wpsstm_player_mixcloud.events.ended.on(MixcloudEventEnded);

}

function providerLoadUrl(url){
    var urlSplit = url.split("mixcloud.com");
    console.log(urlSplit);
    
    if (wpsstm.debug) console.log("WP SoundSystem - Mixloud - providerLoadUrl()");
    wpsstm_player_mixcloud.load(cloudcastKey);
}

function MixcloudEventProgress(){
    console.log("MixcloudEventProgress()");
    progress(true);
}
function MixcloudEventEnded(){
    console.log("MixcloudEventEnded()");
    
    wpsstm_player_mixcloud.events.progress.off(MixcloudEventProgress);
    progress(false);
}

function providerPlay() {
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerPlay()");
    //wpsstm_player_mixcloud.events.play.off(providerPlay);
    wpsstm_player_mixcloud.play();
    
}

function providerPause() {
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerPause()");
    wpsstm_player_mixcloud.pause();
    
    progress(false);
}

function providerJumpTo(time){
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerJumpTo():" + time);
    wpsstm_player_mixcloud.seek(time,true);
    
}

function providerMute(){
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerMute()");
    wpsstm_player_mixcloud.mute();
}
function providerUnMute(){
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerUnMute()");
    wpsstm_player_mixcloud.unMute();
}

function progress(enabled){
    
    if(typeof enabled === 'undefined') enabled = true;
    
    if ( enabled ){

        progressBarTimer = setInterval(function() {
            wpsstm_player_mixcloud.getPosition().then(function(position) {
                player_item_time_current = position;
                wpsstm_player_update_time();

            });
        },1000);  

    }else{
        clearTimeout(progressBarTimer);
        progressBarTimer = false;
    }


}
*/