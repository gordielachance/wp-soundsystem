<?php

class WPSSTM_8tracks{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_8tracks_presets'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_8tracks_service_link'));
    }
    
    //register preset
    function register_8tracks_presets($presets){
        $presets[] = new WPSSTM_8Tracks_Preset();
        return $presets;
    }

    static function register_8tracks_service_link($links){
        $links[] = array(
            'slug'      => '8tracks',
            'name'      => '8tracks',
            'url'       => 'https://8tracks.com',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'https://8tracks.com/USER/PLAYLIST',
                ),
            )
        );
        return $links;
    }
}

class WPSSTM_8Tracks_Preset extends WPSSTM_Remote_Tracklist{
    
    var $user_slug;
    var $playlist_slug;
    var $mix_data;
    var $mix_id;
    
    function __construct() {
        
        parent::__construct();

        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'>tracks'),
            'track_artist'      => array('path'=>'performer'),
            'track_title'       => array('path'=>'name')
        );

        //add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        //add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );
    }
    
    function init_url($url){
        $this->user_slug = self::get_user_slug($url);
        $this->playlist_slug = self::get_tracklist_slug($url);
        
        if ($this->user_slug && $this->playlist_slug){
            $this->mix_data = $this->get_mix_data($this->user_slug,$this->playlist_slug);
        }

        return $this->mix_data;
    }


    static function get_user_slug($url){
        $pattern = '~^https?://(?:www.)?8tracks.com/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    static function get_tracklist_slug($url){
        $pattern = '~^https?://(?:www.)?8tracks.com/[^/]+/([[\w\d-]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_request_url(){
        $mix_id = wpsstm_get_array_value(array('id'), $this->mix_data);
        return sprintf('https://8tracks.com/mixes/%s/tracks_for_international.jsonh',$mix_id);
    }

    function get_mix_data($user_slug,$playlist_slug){

        //TO FIX TO CHECK might be too long for a transient key ?
        $transient_name = sprintf('wpsstm-8tracks-%s-%s-data',sanitize_title($user_slug),sanitize_title($playlist_slug));

        //cache?
        if ( false === ( $data = get_transient($transient_name ) ) ) {
            $mix_data_url = sprintf('https://8tracks.com/%s/%s?format=jsonh',$user_slug,$playlist_slug);
            $response = wp_remote_get($mix_data_url);
            $json = wp_remote_retrieve_body($response);
            if ( is_wp_error($json) ) return $json;
            
            $api = json_decode($json,true);
            if ( $data = wpsstm_get_array_value(array('mix'), $api) ){
                set_transient( $transient_name, $data, 1 * DAY_IN_SECONDS );
            }
        }
        
        return $data;
        
    }
    
    function get_remote_title(){
        $title = wpsstm_get_array_value('name', $this->mix_data);
        return $title;
    }
}

function wpsstm_8tracks_init(){
    new WPSSTM_8tracks();
}

add_action('wpsstm_init','wpsstm_8tracks_init');