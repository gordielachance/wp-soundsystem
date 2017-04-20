//https://developer.mozilla.org/fr/docs/Web/JavaScript/Introduction_%C3%A0_JavaScript_orient%C3%A9_objet

//provider constructor
function Player_Provider(slug) {
    this.slug = slug;
    this.iframe_id = 'wpsstm-player-'+this.slug+'-iframe';
    this.player = null;
    this.time_total = null;

    if (wpsstm.debug) console.log("Player_Provider init: " + this.slug);
    
};

Player_Provider.prototype.loadUrl = function(url) {
    if (wpsstm.debug) console.log("provider "+this.slug+" - load URL: " + url);
};

Player_Provider.prototype.play = function() {
    if (wpsstm.debug) console.log("provider "+this.slug+" : play");
}

Player_Provider.prototype.pause = function(url) {
    if (wpsstm.debug) console.log("provider "+this.slug+" : pause");
}

Player_Provider.prototype.jumpTo = function(time) {
    if (wpsstm.debug) console.log("provider "+this.slug+" : jumpTo: "+time);
}

Player_Provider.prototype.mute = function() {
    if (wpsstm.debug) console.log("provider "+this.slug+" : mute");
}
Player_Provider.prototype.unMute = function() {
    if (wpsstm.debug) console.log("provider "+this.slug+" : unMute");
}