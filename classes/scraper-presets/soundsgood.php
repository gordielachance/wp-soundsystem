<?php
class WP_SoundSystem_Soundsgood_Playlists_Api extends WP_SoundSystem_URL_Preset{
    
    var $preset_url =       'https://soundsgood.co/';

    var $client_id;
    var $station_slug;
    
    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->client_id = $this->get_client_id();
        $this->station_slug = $this->get_station_slug();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'root > element'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_source_urls' => array('path'=>'sources permalink')
        );
        
    }
    
    function can_use_preset(){
        if ( !$this->client_id ) return;
    }
    
    function can_handle_url(){
        if ( !$this->station_slug ) return;
        
        return true;
    }

    function get_remote_url(){
        if ( !$this->can_handle_url() ) return $url;
        return sprintf('https://api.soundsgood.co/playlists/%s/tracks',$this->station_slug);
    }  

    function get_station_slug(){
        $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_request_args(){
        $args['headers']['client'] = $this->client_id;
        return $args;
    }

    function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
    
    function get_remote_title(){
        return sprintf(__('%s on Soundsgood','wpsstm'),$this->station_slug);
    }
    
}

//register preset
function register_soundsgood_preset($presets){
    $presets[] = 'WP_SoundSystem_Soundsgood_Playlists_Api';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_soundsgood_preset');