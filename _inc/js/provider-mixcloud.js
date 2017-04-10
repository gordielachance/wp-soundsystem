var iframe = document.getElementById('wpsstm-iframe-mixcloud');
var mixcloud_player = Mixcloud.PlayerWidget(iframe);

mixcloud_player.ready.then(function() {
    providerReady();
    mixcloud_player.events.pause.on(pauseListener);
    function pauseListener() {
        // This will be called whenever the widget is paused
        alert("pause!");
    }

    // To stop listening for events:
    mixcloud_player.events.pause.off(pauseListener);
});


function providerReady() {
    mixcloud_player.getDuration().then(function(value) {
        player_item_time_total = value;
        console.log("WP SoundSystem - Mixcloud - providerReady()");
        console.log(player_item_time_total);
    });

}

