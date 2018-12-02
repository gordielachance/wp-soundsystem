<?php

class WPSSTM_SomaFM{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_somafm_service_link'));
        add_action('wpsstm_before_remote_response',array($this,'register_somafm_preset'));
    }
    //register preset
    function register_somafm_preset($tracklist){
        new WPSSTM_SomaFM_Preset($tracklist);
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

class WPSSTM_SomaFM_Preset{

    function __construct($remote){
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );
 
    }
    
    function can_handle_url($url){
        $station_slug = $this->get_station_slug($url);
        if (!$station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $station_slug = $this->get_station_slug($url);
            $url = sprintf('http://somafm.com/songs/%s.xml',$station_slug );
        }
        return $url;
    }  
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->redirect_url) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        );
    }

    
    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_title($title,$remote){
        if ( $this->can_handle_url($remote->redirect_url) ){
            $station_slug = $this->get_station_slug($remote->url);
            $title = sprintf( __('%s on SomaFM','wpsstm'),$station_slug );
        }
        return $title;
    }

}

function wpsstm_somafm_init(){
    new WPSSTM_SomaFM();
}

add_action('wpsstm_init','wpsstm_somafm_init');