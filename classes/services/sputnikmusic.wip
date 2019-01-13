<?php

class WPSSTM_sputnik{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_sputnik_presets'));
        add_filter('wpsstm_wizard_service_links',array(__class__,'register_sputnik_service_link'));
    }
    
    //register preset
    function register_sputnik_presets($presets){
        $presets[] = new WPSSTM_Sputnik_Preset();
        return $presets;
    }

    static function register_sputnik_service_link($links){
        $item = sprintf('<a href="https://www.sputnik.com" target="_blank" title="%s"><img src="%s" /></a>',__('Sputnik music playlists','wpsstm'),wpsstm()->plugin_url . '_inc/img/sputnik-icon.png');
        $links[] = $item;
        return $links;
    }
}

class WPSSTM_Sputnik_Preset extends WPSSTM_Remote_Tracklist{

    function __construct($url = null,$options = null) {
        
        $this->default_options['selectors'] = array(
            //'tracklist_title'   => array('path'=>'title','regex'=>null,'attr'=>null),
            'tracks'           => array('path'=>'table[cellpadding="8"] td.alt1'),
            'track_artist'     => array('path'=>'a:nth-child(2) b'),
            'track_title'      => array('path'=>'.mediumtext','regex'=>'(.*?)(?:<br/>|<br>)'),
        );
        
        parent::__construct($url,$options);

    }
    
    function init_url($url){
        $domain = wpsstm_get_url_domain( $url );
        return ( $domain == 'sputnikmusic.com');
    }
    

}

function wpsstm_sputnik_init(){
    new WPSSTM_sputnik();
}

add_action('wpsstm_init','wpsstm_sputnik_init');