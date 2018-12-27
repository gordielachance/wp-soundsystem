<?php

class WPSSTM_IndieShuffle{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_indieshuffle_preset'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_indieshuffle_service_links'));
    }
    //register preset
    function register_hypem_preset($presets){
        $presets[] = new WPSSTM_IndieShuffle_Preset();
        return $presets;
    }

    static function register_indieshuffle_service_links($links){
        $links[] = array(
            'slug'      => 'indieshuffle',
            'name'      => 'indie shuffle',
            'url'       => 'https://www.indieshuffle.com/',
        );
        return $links;
    }
}

/*
Should try to support playlists and songs, eg.
https://www.indieshuffle.com/playlists/best-songs-of-april-2017/
https://www.indieshuffle.com/songs/hip-hop/
*/

class WPSSTM_IndieShuffle_Preset extends WPSSTM_Remote_Tracklist{

    function __construct($url = null,$options = null) {
        
        $this->default_options['selectors'] = array(
            'tracks'           => array('path'=>'#mainContainer .commontrack'),
            'track_artist'     => array('attr'=>'data-track-artist'),
            'track_title'      => array('attr'=>'data-track-title'),
            'track_image'      => array('path'=>'img','attr'=>'src'),
            'track_source_urls' => array('attr'=>'data-source'),
        );
        
        parent::__construct($url,$options);
    }
    
    function init_url($url){
        $domain = wpsstm_get_url_domain( $url );
        return ( $domain == 'indieshuffle.com');
    }


}

function wpsstm_indieshuffle(){
    new WPSSTM_IndieShuffle();
}

add_action('wpsstm_init','wpsstm_hypem');