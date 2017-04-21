var wpsstm_providers = {};

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

    if (wpsstm_active_row){
        //page button
        jQuery('.wpsstm-play-track').find('.wpsstm-player-icon-play').show();
        jQuery('.wpsstm-play-track').find('.wpsstm-player-icon-pause').hide();
    }
};

Player_Provider.prototype.play = function() {
    jQuery('.wpsstm-tracklist-table tr').removeClass('wpsstm-row-current');
    jQuery(wpsstm_active_row).addClass('wpsstm-row-current');
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

Player_Provider.prototype.onStateChange = function(code) {
    if (wpsstm.debug) console.log("provider "+this.slug+" : onStateChange: " + code);
    
    wpsstm_current_state = code;
    
    var bottom_player = jQuery('#wpsstm-bottom-player');

    //hide row icons
    jQuery(wpsstm_active_row).find('.wpsstm-player-icon').hide();
    //hide player icons
    jQuery(bottom_player).find('.wpsstm-player-icon').hide();
    
    switch(code) {
        case 'buffering':
            //page button
            jQuery(wpsstm_active_row).find('.wpsstm-player-icon-buffering').show();
            //player icon
            jQuery(bottom_player).find('.wpsstm-player-icon-buffering').show();
        break;
        case 'playing':
            //page button
            jQuery(wpsstm_active_row).find('.wpsstm-player-icon-pause').show();
            //player icon
            jQuery(bottom_player).find('.wpsstm-player-icon-pause').show();
        break;
        case 'paused':
            //page button
            jQuery(wpsstm_active_row).find('.wpsstm-player-icon-play').show();
            //player icon
            jQuery(bottom_player).find('.wpsstm-player-icon-play').show();
        break;
        case 'ended':
            //page button
            jQuery(wpsstm_active_row).find('.wpsstm-player-icon-play').show();
            //player icon
            jQuery(bottom_player).find('.wpsstm-player-icon-play').show();
        break;
    }

}