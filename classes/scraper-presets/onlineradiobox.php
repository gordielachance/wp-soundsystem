<?php
class WP_SoundSystem_OnlineRadioBox_Scraper extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://onlineradiobox.com/';
    var $station_slug;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->station_slug = $this->get_station_slug();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'.tablelist-schedule tr'),
            'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
            'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
        );
    }
    
    function can_handle_url(){
        if ( !$this->station_slug ) return;
        return true;
    }
    
    function get_remote_url(){
        
        return sprintf('http://onlineradiobox.com/ma/%s/playlist',$this->station_slug);
    }

    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?onlineradiobox.com/[^/]+/([^/]+)/~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

//register preset
function register_onlineradiobox_preset($presets){
    $presets[] = 'WP_SoundSystem_OnlineRadioBox_Scraper';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_onlineradiobox_preset');