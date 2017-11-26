<?php
class WP_SoundSystem_Slacker_Stations extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://slacker.com/';

    var $station_slug;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->station_slug = $this->get_station_slug();
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        );
    }
    
    function can_handle_url(){
        if (!$this->station_slug ) return;
        return true;
    }

    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }


}

//register preset
function register_slacker_preset($presets){
    $presets[] = 'WP_SoundSystem_Slacker_Stations';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_slacker_preset');