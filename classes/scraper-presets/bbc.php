<?php
class WP_SoundSystem_BBC_Stations extends WP_SoundSystem_URL_Preset{
    var $preset_slug =      'bbc-station';
    var $preset_url =       'http://www.bbc.co.uk/radio';
    private $station_slug;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->station_slug = $this->get_station_slug();
        
        $this->scraper_options['selectors'] = array(
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
    var $preset_slug =      'bbc-playlist';
    var $preset_url =       'http://www.bbc.co.uk/music/playlists/';
    private $playlist_id;
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->playlist_id = $this->get_playlist_id();
        
        $this->scraper_options['selectors'] = array(
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

function register_bbc_service_links($links){
    $links[] = array(
        'slug'      => 'bbc',
        'name'      => 'BBC',
        'url'       => 'https://www.bbc.co.uk'
    );
    
    $links[] = array(
        'slug'          => 'bbc-stations',
        'parent_slug'   => 'bbc',
        'name'          => __('stations','wpsstm'),
        'example'       => 'https://www.bbc.co.uk/STATION',
    );
    
    $links[] = array(
        'slug'          => 'bbc-playlists',
        'parent_slug'   => 'bbc',
        'name'          => __('playlists','wpsstm'),
        'example'       => 'http://www.bbc.co.uk/music/playlists/PLAYLIST_ID',
    );
    
    return $links;
}

add_action('wpsstm_get_scraper_presets','register_bbc_presets');
add_filter('wpsstm_wizard_services_links','register_bbc_service_links');