<?php

class WPSSTM_SoundsGood{
    function __construct(){
        if ( self::get_client_id() ){
            add_action('wpsstm_before_remote_response',array($this,'register_soundsgood_preset'));
            add_filter('wpsstm_wizard_services_links',array($this,'register_soundsgood_service_links'));
        }
    }
    //register preset
    function register_soundsgood_preset($tracklist){
        new WPSSTM_Soundsgood_Api_Preset($tracklist);
    }
    function register_soundsgood_service_links($links){
        $links[] = array(
            'slug'      => 'soundsgood',
            'name'      => 'Soundsgood',
            'url'       => 'https://soundsgood.co/',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'https://play.soundsgood.co/playlist/TRACKLIST_SLUG',
                ),
            )
        );
        return $links;
    }
    static function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
}

class WPSSTM_Soundsgood_Api_Preset{

    function __construct($remote){
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_request_args',array($this,'remote_request_args'),10,2 );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );
        
    }

    function can_handle_url($url){
        $station_slug = $this->get_station_slug($url);
        if ( !$station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $station_slug = $this->get_station_slug($url);
            $url = sprintf('https://api.soundsgood.co/playlists/%s/tracks',$station_slug);
        }
        return $url;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'root > element'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_source_urls' => array('path'=>'sources permalink')
        );
    }

    function get_station_slug($url){
        $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function remote_request_args($args,$remote){
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
            $client_id = WPSSTM_SoundsGood::get_client_id();
            $args['headers']['client'] = $client_id;
        }
        return $args;
    }
    
    function get_remote_title($title,$remote){
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
            $station_slug = $this->get_station_slug($remote->feed_url_no_filters);
            $url = sprintf(__('%s on Soundsgood','wpsstm'),$station_slug);
        }
        return $title;
    }
    
}

function wpsstm_soundsgood_init(){
    new WPSSTM_SoundsGood();
}

add_action('wpsstm_init','wpsstm_soundsgood_init');
