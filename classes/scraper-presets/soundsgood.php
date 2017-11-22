<?php
class WP_SoundSystem_Preset_Soundsgood_Playlists_Api extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'soundsgood';
    var $preset_url =       'https://soundsgood.co/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'root > element'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_source_urls' => array('path'=>'sources permalink')
        )
    );
    
    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Soundsgood playlists','wpsstm');

    }
    
    static function can_handle_url($url){
        if ( !$client_id = self::get_client_id() ) return;
        if ( !$station_slug = self::get_station_slug($url) ) return;
        
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://api.soundsgood.co/playlists/%s/tracks',self::get_station_slug($this->feed_url));
    }
    
    static function get_station_slug($url){
        $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    

    function get_request_args(){
        $args = parent::get_request_args();

        if ( $client_id = self::get_client_id() ){
            $args['headers']['client'] = $client_id;
        }

        return $args;
    }

    static function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
    
    function get_remote_title(){
        $station_slug = self::get_station_slug($this->feed_url);
        return sprintf(__('%s on Soundsgood','wpsstm'),$station_slug);
    }
    
}

//register preset

function register_soundsgood_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Soundsgood_Playlists_Api';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_soundsgood_preset');