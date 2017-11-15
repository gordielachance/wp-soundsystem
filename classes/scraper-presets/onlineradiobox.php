<?php
class WP_SoundSystem_Preset_OnlineRadioBox_Scraper extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'onlineradiobox';
    var $preset_url =       'http://onlineradiobox.com/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'.tablelist-schedule tr'),
            'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
            'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
        )
    );
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name =    'Online Radio Box';
    }
    
    function can_load_feed(){
        if ( !$station_slug = $this->get_station_slug() ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('http://onlineradiobox.com/ma/%s/playlist',$this->get_station_slug());
    }

    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?onlineradiobox.com/[^/]+/([^/]+)/~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

//register preset

function register_onlineradiobox_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_OnlineRadioBox_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_onlineradiobox_preset');