<?php
class WP_SoundSystem_SomaFM_Stations extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://somafm.com/';

    var $station_slug;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->station_slug = $this->get_station_slug();
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        );   
    }
    
    function can_handle_url(){
        if (!$this->station_slug ) return;
        return true;
    }

    function get_remote_url(){
        
        return sprintf('http://somafm.com/songs/%s.xml',$this->station_slug );
    }  

    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title(){
        return sprintf( __('%s on SomaFM','wppstm'),$this->station_slug );
    }

}

//register preset
function register_somafm_preset($presets){
    $presets[] = 'WP_SoundSystem_SomaFM_Stations';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_somafm_preset');