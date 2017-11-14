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
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'somafm') return;
        
        if ( !$station_slug = $this->get_station_slug() ){
            return new WP_Error( 'wpsstm_somafm_missing_station_slug', __('Required station slug missing.','wpsstm') );
        }

        return sprintf('http://somafm.com/songs/%s.xml',$station_slug);

    }
    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title(){
        return sprintf( __('Somafm : %s','wppstm'),$this->get_station_slug() );
    }

}

//register preset

function register_somafm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_SomaFM_Stations';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_somafm_preset');