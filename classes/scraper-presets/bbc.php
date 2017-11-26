<?php
class WP_SoundSystem_BBC_Stations extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://www.bbc.co.uk/radio';
    var $station_slug;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->station_slug = $this->get_station_slug();
        
        $this->options['selectors'] = array(
                'tracks'            => array('path'=>'.music-track'),
                'track_artist'      => array('path'=>'.music-track__artist'),
                'track_title'       => array('path'=>'.music-track__title'),
                'track_image'       => array('path'=>'.music-track__image','attr'=>'src')
        );
        
    }
    
    function can_handle_url(){
        if ( $this->station_slug ) return true;
    }
    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_url(){
        return sprintf( 'https://www.bbc.co.uk/music/tracks/find/%s',$this->station_slug );
    }

}

class WP_SoundSystem_BBC_Playlists extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://www.bbc.co.uk/music/playlists/';
    var $playlist_id;
    
    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->playlist_id = $this->get_playlist_id();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
            'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
            'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
        );
    }
    
    function can_handle_url(){
        if ( !$this->playlist_id ) return;
        return true;
    }

    function get_playlist_id(){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

function register_bbc_presets($presets){
    $presets[] = 'WP_SoundSystem_BBC_Stations';
    $presets[] = 'WP_SoundSystem_BBC_Playlists';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_bbc_presets');