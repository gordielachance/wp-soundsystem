<?php
class WP_SoundSystem_Preset_SomaFM_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'somafm';
    var $preset_url =       'http://somafm.com/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Soma FM Stations','wpsstm');

    }
    
    static function can_handle_url($url){
        if (!$station_slug = self::get_station_slug($url) ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('http://somafm.com/songs/%s.xml',self::get_station_slug($this->feed_url) );
    }
    
    static function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title(){
        return sprintf( __('%s on SomaFM','wppstm'),self::get_station_slug($this->feed_url) );
    }

}

//register preset

function register_somafm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_SomaFM_Stations';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_somafm_preset');