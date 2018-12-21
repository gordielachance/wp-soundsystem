<?php
class WPSSTM_Hypem{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_hypem_preset'));
        add_filter('wpsstm_wizard_services_links',array($this,'register_hypem_service_links'));
    }
    //register preset
    function register_hypem_preset($presets){
        $presets[] = new WPSSTM_Hypem_Preset();
        return $presets;
    }

    function register_hypem_service_links($links){
        $links[] = array(
            'slug'      => 'hypem',
            'name'      => 'Hypem',
            'url'       => 'https://www.hypem.com',
        );
        return $links;
    }
}

class WPSSTM_Hypem_Preset extends WPSSTM_Remote_Tracklist{

    function __construct(){
        
        parent::__construct();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        );
    }
    
    function init_url($url){
        $domain = wpsstm_get_url_domain( $url );
        return ( $domain == 'hypem.com');
    }


}


function wpsstm_hypem(){
    new WPSSTM_Hypem();
}

add_action('wpsstm_init','wpsstm_hypem');