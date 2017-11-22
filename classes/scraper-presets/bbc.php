<?php
class WP_SoundSystem_Preset_BBC_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'bbc-station';
    var $preset_url =       'http://www.bbc.co.uk/radio';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'.music-track'),
            'track_artist'      => array('path'=>'.music-track__artist'),
            'track_title'       => array('path'=>'.music-track__title'),
            'track_image'       => array('path'=>'.music-track__image','attr'=>'src')
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name =    __('BBC stations','wpsstm');
    }
    
    static function can_handle_url($url){
        if ( !$station_slug = self::get_station_slug($url) ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf( 'https://www.bbc.co.uk/music/tracks/find/%s',$this->get_station_slug() );
    }

    static function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

class WP_SoundSystem_Preset_BBC_Playlists extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'bbc-playlist';
    var $preset_url =       'http://www.bbc.co.uk/music/playlists/'; 
    var $pattern =          '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)/?$~i';
    var $variables =        array(
        'bbc-playlist-id' => null
    );
    
    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
            'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
            'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name =    __('BBC playlists','wpsstm');
    }
    
    static function can_handle_url($url){
        if ( !$playlist_id = self::get_playlist_id($url) ) return;
        return true;
    }

    static function get_playlist_id($url){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    

}

//register preset

function register_bbc_presets($presets){
    $presets[] = 'WP_SoundSystem_Preset_BBC_Stations';
    $presets[] = 'WP_SoundSystem_Preset_BBC_Playlists';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_bbc_presets');