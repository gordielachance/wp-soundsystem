<?php
class WP_SoundSystem_Preset_BBC_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'bbc-station';
    var $preset_url =       'http://www.bbc.co.uk/radio';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'.pll-playlist-item-wrapper'),
            'track_artist'      => array('path'=>'.pll-playlist-item-details .pll-playlist-item-artist'),
            'track_title'       => array('path'=>'.pll-playlist-item-details .pll-playlist-item-title'),
            'track_image'       => array('path'=>'img.pll-playlist-item-image','attr'=>'src')
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name =    __('BBC stations','wpsstm');
    }
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'bbc') return;
        
        if ( !$station_slug = $this->get_station_slug() ){
            return new WP_Error( 'wpsstm_bbc_missing_station_slug', __('Required station slug missing.','wpsstm') );
        }
        
        return $this->feed_url;
    }
    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
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
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'bbc') return;
        
        if ( !$playlist_id = $this->get_playlist_id() ){
            return new WP_Error( 'wpsstm_bbc_missing_playlist_id', __('Required playlist ID missing.','wpsstm') );
        }
        
        return sprintf('http://www.bbc.co.uk/music/playlists/%s',$playlist_id);
    }
    function get_playlist_id(){
        $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
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