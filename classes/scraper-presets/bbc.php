<?php
class WP_SoundSystem_BBC_Stations{
    var $preset_slug =      'bbc-station';
    var $preset_url =       'http://www.bbc.co.uk/radio';
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

class WP_SoundSystem_BBC_Playlists{
    var $preset_slug =      'bbc-playlist';
    var $preset_url =       'http://www.bbc.co.uk/music/playlists/';
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

function register_bbc_presets($tracklist){
    new WP_SoundSystem_BBC_Stations($tracklist);
    new WP_SoundSystem_BBC_Playlists($tracklist);
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

add_action('wpsstm_get_remote_tracks','register_bbc_presets');
add_filter('wpsstm_wizard_services_links','register_bbc_service_links');