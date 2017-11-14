<?php
class WP_SoundSystem_Preset_Slacker_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'slacker-station-tops';
    var $preset_url =       'http://slacker.com/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Slacker.com station tops','wpsstm');

    }
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'slacker') return;
        
        $station_slug = $this->get_station_slug();
        if ( is_wp_error($station_slug) ) return $station_slug;

        return $this->feed_url;

    }
    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    

}

//register preset

function register_slacker_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Slacker_Stations';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_slacker_preset');