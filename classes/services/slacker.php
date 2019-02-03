<?php
class WPSSTM_Slacker{
    function __construct(){
        add_filter('wpsstm_wizard_service_links',array($this,'register_slacker_service_links'));
        add_filter('wpsstm_remote_presets',array($this,'register_slacker_preset'));
    }
    //register preset
    function register_hypem_preset($presets){
        $presets[] = new WPSSTM_Slacker_Preset();
        return $presets;
    }
    
    static function register_deezer_service_links($links){
        $item = sprintf('<a href="https://www.slacker.com" target="_blank" title="%s"><img src="%s" /></a>','Slacker',wpsstm()->plugin_url . '_inc/img/slacker-icon.png');
        $links[] = $item;
        return $links;
    }
}
class WPSSTM_Slacker_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
                'track_artist'      => array('path'=>'span.artist'),
                'track_title'       => array('path'=>'span.title')
            )
        );
        
        parent::__construct($url,$options);
        
    }
    
    function init_url($url){
        $this->station_slug = $this->get_station_slug($url);
        return $this->station_slug;
    }


    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }


}


function wpsstm_slacker_init(){
    new WPSSTM_Slacker();
}

//TO FIX TO REPAIR add_action('wpsstm_load_services','wpsstm_reddit_init');