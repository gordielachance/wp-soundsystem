<?php

class WPSSTM_SomaFM{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_somafm_service_link'));
        add_filter('wpsstm_remote_presets',array($this,'register_somafm_preset'));
    }
    //register preset
    function register_somafm_preset($presets){
        $presets[] = new WPSSTM_SomaFM_Preset();
        return $presets;
    }
    
    function register_somafm_service_link($links){
        $links[] = array(
            'slug'      => 'somafm',
            'name'      => 'SomaFM',
            'url'       => 'https://somafm.com',
            'pages'     => array(
                array(
                    'slug'      => 'stations',
                    'name'      => __('stations','wpsstm'),
                    'example'   => 'https://somafm.com/STATION_SLUG',
                ),
            )
        );
        return $links;
    }
}

class WPSSTM_SomaFM_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;

    function __construct($url = null,$options = null) {
        
        $this->default_options['selectors'] = array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        );
        
        parent::__construct($url,$options);

    }
    
    function init_url($url){
        $this->station_slug = $this->get_station_slug($url);
        return $this->station_slug;
    }

    function get_remote_request_url(){
        return sprintf('http://somafm.com/songs/%s.xml',$this->station_slug );
    }  

    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title(){
        return sprintf( __('%s on SomaFM','wpsstm'),$this->station_slug );
    }

}

function wpsstm_somafm_init(){
    new WPSSTM_SomaFM();
}

add_action('wpsstm_init','wpsstm_somafm_init');