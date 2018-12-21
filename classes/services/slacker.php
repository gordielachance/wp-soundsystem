<?php
class WPSSTM_Slacker{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_slacker_service_links'));
        add_filter('wpsstm_remote_presets',array($this,'register_slacker_preset'));
    }
    //register preset
    function register_hypem_preset($presets){
        $presets[] = new WPSSTM_Slacker_Preset();
        return $presets;
    }
    
    function register_slacker_service_links($links){
        $links[] = array(
            'slug'      => 'slacker',
            'name'      => 'Slacker',
            'url'       => 'http://www.slacker.com',
            'pages'     => array(
                array(
                    'slug'      => 'stations',
                    'name'      => __('stations','wpsstm'),
                    'example'   => 'http://www.slacker.com/station/STATION_SLUG',
                ),
            )
        );
        return $links;
    }
}
class WPSSTM_Slacker_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;

    function __construct(){
        
        parent::__construct();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        );
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

//TO FIX TO REPAIR add_action('wpsstm_init','wpsstm_reddit_init');