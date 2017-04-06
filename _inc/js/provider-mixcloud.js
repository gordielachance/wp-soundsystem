(function($){

    $(document).ready(function(){
      console.log("mixcloud provider loaded");



    });  
    
    
})(jQuery);

var iframe = document.getElementById('wpsstm-iframe-mixcloud');
//var iframe = $('#wpsstm-iframe-mixcloud').get(0);

//mixcloud
//var widget = Mixcloud.PlayerWidget(iframe);
var mixcloud_player = Mixcloud.PlayerWidget(iframe);
mixcloud_player.ready.then(function() {
    mixcloud_player.events.pause.on(pauseListener);
    function pauseListener() {
        // This will be called whenever the widget is paused
        alert("pause!");
    }

    // To stop listening for events:
    mixcloud_player.events.pause.off(pauseListener);
});


