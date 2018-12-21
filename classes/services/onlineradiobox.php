<?php

class WPSSTM_OnlineRadioBox{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_onlineradiobox_service_link'));
        add_filter('wpsstm_remote_presets',array($this,'register_onlineradiobox_preset'));
    }
    //register preset
    function register_onlineradiobox_preset($presets){
        $presets[] = new WPSSTM_OnlineRadioBox_Preset();
        return $presets;
    }
    static function register_onlineradiobox_service_link($links){
        $links[] = array(
            'slug'      => 'onlineradiobox',
            'name'      => 'Online Radio Box',
            'url'       => 'http://onlineradiobox.com/',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'http://onlineradiobox.com/COUNTRY/RADIO_SLUG',
                ),
            )
        );
        return $links;
    }
}

class WPSSTM_OnlineRadioBox_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;

    function __construct(){
        
        parent::__construct();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'.tablelist-schedule tr'),
            'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
            'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
        );

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