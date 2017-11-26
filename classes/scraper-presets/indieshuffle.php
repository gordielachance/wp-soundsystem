<?php

/*
Should try to support playlists and songs, eg.
https://www.indieshuffle.com/playlists/best-songs-of-april-2017/
https://www.indieshuffle.com/songs/hip-hop/
*/

class WP_SoundSystem_IndieShuffle_Scraper extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'https://www.indieshuffle.com';

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->options['selectors'] = array(
            'tracks'           => array('path'=>'#mainContainer .commontrack'),
            'track_artist'     => array('attr'=>'data-track-artist'),
            'track_title'      => array('attr'=>'data-track-title'),
            'track_image'      => array('path'=>'img','attr'=>'src'),
            'track_source_urls' => array('attr'=>'data-source'),
        );
    }
    
    function can_handle_url(){
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $domain != 'indieshuffle') return;
        return true;
    }

}

//register preset
function register_indieshuffle_preset($presets){
    $presets[] = 'WP_SoundSystem_IndieShuffle_Scraper';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_indieshuffle_preset');