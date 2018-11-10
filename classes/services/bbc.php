<?php

class WPSSTM_BBC{
    function __construct(){
        add_action('wpsstm_before_remote_response',array(__class__,'register_bbc_presets'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_bbc_service_links'));
    }
    static function register_bbc_presets($remote){
        new WPSSTM_BBC_Station_Preset($remote);
        new WPSSTM_BBC_Playlist_Preset($remote);
    }

    static function register_bbc_service_links($links){
        $links[] = array(
            'slug'      => 'bbc',
            'name'      => 'BBC',
            'url'       => 'https://www.bbc.co.uk',
            'pages'     => array(
                array(
                    'slug'          => 'stations',
                    'name'          => __('stations','wpsstm'),
                    'example'       => 'https://www.bbc.co.uk/STATION',
                ),
                array(
                    'slug'          => 'playlists',
                    'name'          => __('playlists','wpsstm'),
                    'example'       => 'http://www.bbc.co.uk/music/playlists/PLAYLIST_ID',
                ),
            )
        );

        return $links;
    }
}

class WPSSTM_BBC_Station_Preset{
    private $this;

    function __construct($remote){
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        
    }
    
    function can_handle_url($url){
        $station_slug = $this->get_station_slug($url);
        if ( $station_slug ) return true;
    }
    
    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $station_slug = $this->get_station_slug($url);
            $url = sprintf( 'https://www.bbc.co.uk/music/tracks/find/%s',$station_slug );
        }
        return $url;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->url) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'.music-track'),
            'track_artist'      => array('path'=>'.music-track__artist'),
            'track_title'       => array('path'=>'.music-track__title'),
            'track_image'       => array('path'=>'.music-track__image','attr'=>'src')
        );
    }
    

}
class WPSSTM_BBC_Playlist_Preset{
    
    function __construct($remote){
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function can_handle_url($url){
        $playlist_id = $this->get_playlist_id($url);
        if ( !$playlist_id ) return;
        return true;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->url) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
            'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
            'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
        );
    }
    

    function get_playlist_id($url){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)~i';
        preg_match($pattern,$url,$matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

function wpsstm_BBC_init(){
    new WPSSTM_BBC();
}

add_action('wpsstm_init','wpsstm_BBC_init');