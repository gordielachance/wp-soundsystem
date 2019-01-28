<?php

class WPSSTM_SoundsGood{
    function __construct(){
        if ( self::get_client_id() ){
            add_filter('wpsstm_remote_presets',array($this,'register_soundsgood_preset'));
            add_filter('wpsstm_wizard_service_links',array($this,'register_soundsgood_service_links'));
        }
    }
    //register preset
    function register_soundsgood_preset($presets){
        $presets[] = new WPSSTM_Soundsgood_Api_Preset();
        return $presets;
    }
    function register_soundsgood_service_links($links){
        $item = sprintf('<a href="https://www.soundsgood.co" target="_blank" title="%s"><img src="%s" /></a>','Soundsgood',wpsstm()->plugin_url . '_inc/img/soundsgood-icon.jpg');
        $links[] = $item;
        return $links;
    }
    static function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
}

class WPSSTM_Soundsgood_Api_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;
    var $client_id;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'root > element'),
                'track_artist'      => array('path'=>'artist'),
                'track_title'       => array('path'=>'title'),
                'track_source_urls' => array('path'=>'sources permalink')
            )
        );
        
        parent::__construct($url,$options);

    }

    function init_url($url){
        $this->station_slug = $this->get_station_slug($url);
        $this->client_id = WPSSTM_SoundsGood::get_client_id();
        return ($this->station_slug && $this->client_id);
    }

    function get_remote_request_url(){
        return sprintf('https://api.soundsgood.co/playlists/%s/tracks',$this->station_slug);
    }

    function get_station_slug($url){
        $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_request_args(){
        $args = parent::get_remote_request_args();
        $args['headers']['client'] = $this->client_id;
        return $args;
    }
    
    function get_remote_title(){
        return sprintf(__('%s on Soundsgood','wpsstm'),$this->station_slug);
    }
    
}

function wpsstm_soundsgood_init(){
    new WPSSTM_SoundsGood();
}

add_action('wpsstm_init','wpsstm_soundsgood_init');
