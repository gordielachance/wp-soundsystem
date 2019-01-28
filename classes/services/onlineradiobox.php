<?php

class WPSSTM_OnlineRadioBox{
    function __construct(){
        add_filter('wpsstm_wizard_service_links',array(__class__,'register_onlineradiobox_service_link'));
        add_filter('wpsstm_remote_presets',array($this,'register_onlineradiobox_preset'));
    }
    //register preset
    function register_onlineradiobox_preset($presets){
        $presets[] = new WPSSTM_OnlineRadioBox_Preset();
        return $presets;
    }
    static function register_onlineradiobox_service_link($links){
        $item = sprintf('<a href="http://onlineradiobox.com" target="_blank" title="%s"><img src="%s" /></a>',__('Online Radio Box playlists','wpsstm'),wpsstm()->plugin_url . '_inc/img/onlineradiobox-icon.jpg');
        $links[] = $item;
        return $links;
    }
}

class WPSSTM_OnlineRadioBox_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'.tablelist-schedule tr'),
                'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
                'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
            )
        );
        
        parent::__construct($url,$options);

    }
    
    function init_url($url){
        $this->station_slug = $this->get_station_slug($url);
        return $this->station_slug;
    }
    
    function get_remote_request_url(){
        return sprintf('http://onlineradiobox.com/gr/%s/playlist',$this->station_slug);
    }

    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?onlineradiobox.com/[^/]+/([^/]+)/~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}


function wpsstm_onlineradiobox_init(){
    new WPSSTM_OnlineRadioBox();
}

add_action('wpsstm_init','wpsstm_onlineradiobox_init');