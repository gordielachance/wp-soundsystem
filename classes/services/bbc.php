<?php

class WPSSTM_BBC{
    function __construct(){
        add_action('wpsstm_init_presets',array(__class__,'register_bbc_presets'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_bbc_service_links'));
    }
    static function register_bbc_presets($tracklist){
        new WPSSTM_BBC_Station_Preset($tracklist);
        new WPSSTM_BBC_Playlist_Preset($tracklist);
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
    private $station_slug;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        
    }
    
    function can_handle_url(){
        if ( $this->station_slug ) return true;
    }
    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf( 'https://www.bbc.co.uk/music/tracks/find/%s',$this->station_slug );
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'.music-track'),
                'track_artist'      => array('path'=>'.music-track__artist'),
                'track_title'       => array('path'=>'.music-track__title'),
                'track_image'       => array('path'=>'.music-track__image','attr'=>'src')
            );
        }
        return $options;
    }
    

}
class WPSSTM_BBC_Playlist_Preset{
    private $playlist_id;
    
    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->playlist_id = $this->get_playlist_id();
        
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        if ( !$this->playlist_id ) return;
        return true;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
                'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
                'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
            );
        }
        return $options;
    }
    

    function get_playlist_id(){
        global $wpsstm_tracklist;
        $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)~i';
        preg_match($pattern, $wpsstm_tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

function wpsstm_BBC_init(){
    new WPSSTM_BBC();
}

add_action('wpsstm_init','wpsstm_BBC_init');