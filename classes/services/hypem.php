<?php
class WPSSTM_Hypem{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_hypem_preset'));
        add_filter('wpsstm_wizard_service_links',array($this,'register_hypem_service_links'), 9);
    }
    //register preset
    function register_hypem_preset($presets){
        $presets[] = new WPSSTM_Hypem_Preset();
        return $presets;
    }

    function register_hypem_service_links($links){
        $item = sprintf('<a href="https://www.hypem.com" target="_blank" title="%s"><img src="%s" /></a>','Hypem',wpsstm()->plugin_url . '_inc/img/hypem-icon.png');
        $links[] = $item;
        return $links;
    }
}

class WPSSTM_Hypem_Preset extends WPSSTM_Remote_Tracklist{

    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'.section-track'),
                'track_artist'      => array('path'=>'.track_name .artist'),
                'track_title'       => array('path'=>'.track_name .track'),
                //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
            )
        );
        
        parent::__construct($url,$options);
    }
    
    public function init_url($url){
        $domain = wpsstm_get_url_domain( $url );
        return ( $domain == 'hypem.com');
    }


}


function wpsstm_hypem(){
    new WPSSTM_Hypem();
}

add_action('wpsstm_load_services','wpsstm_hypem');