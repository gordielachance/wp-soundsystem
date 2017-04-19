var iframe = document.getElementById('wpsstm-player-iframe-mixcloud');
var mixcloud_player = Mixcloud.PlayerWidget(iframe);
progressBarTimer = false;

mixcloud_player.ready.then(function() {
    providerReady();
});


function providerReady() {
    console.log("WP SoundSystem - Mixcloud - providerReady()");
    return;//TO FIX
    mixcloud_player.getDuration().then(function(value) {
        player_item_time_total = value;
        if (wpsstm.debug){
            console.log(player_item_time_total);
        }
    });
    
    mixcloud_player.events.progress.on(MixcloudEventProgress);
    mixcloud_player.events.play.on(providerPlay);
    mixcloud_player.events.pause.on(providerPause);
    mixcloud_player.events.ended.on(MixcloudEventEnded);

}

function MixcloudEventProgress(){
    console.log("MixcloudEventProgress()");
    progress(true);
}
function MixcloudEventEnded(){
    console.log("MixcloudEventEnded()");
    
    mixcloud_player.events.progress.off(MixcloudEventProgress);
    progress(false);
}

function providerPlay() {
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerPlay()");
    //mixcloud_player.events.play.off(providerPlay);
    mixcloud_player.play();
    
}

function providerPause() {
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerPause()");
    mixcloud_player.pause();
    
    progress(false);
}

function providerJumpTo(time){
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerJumpTo():" + time);
    mixcloud_player.seek(time,true);
    
}

function providerMute(){
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerMute()");
    mixcloud_player.mute();
}
function providerUnMute(){
    if (wpsstm.debug) console.log("WP SoundSystem - Mixcloud - providerUnMute()");
    mixcloud_player.unMute();
}

function progress(enabled){
    
    if(typeof enabled === 'undefined') enabled = true;
    
    if ( enabled ){

        progressBarTimer = setInterval(function() {
            mixcloud_player.getPosition().then(function(position) {
                player_item_time_current = position;
                wpsstm_player_update_time();

            });
        },1000);  

    }else{
        clearTimeout(progressBarTimer);
        progressBarTimer = false;
    }


}
