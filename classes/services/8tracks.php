<?php

class WPSSTM_8tracks{
    function __construct(){
        add_action('wpsstm_before_remote_response',array(__class__,'register_8tracks_playlists_preset'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_8tracks_service_link'));
    }
    
    //register preset
    static function register_8tracks_playlists_preset($remote){
        new WPSSTM_8Tracks_Preset($remote);
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

class WPSSTM_8Tracks_Preset{
    
    function __construct($remote){
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );
    }
    
    function can_handle_url($url){
        $user_slug = $this->get_user_slug($url);
        $playlist_slug = $this->get_tracklist_slug($url);
        if ( !$user_slug ) return;
        if ( !$playlist_slugg ) return;
        return true;
    }

    function get_user_slug($url){
        $pattern = '~^https?://(?:www.)?8tracks.com/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_tracklist_slug($url){
        $pattern = '~^https?://(?:www.)?8tracks.com/[^/]+/([[\w\d-]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url($url) ){
            $mix_data = $this->get_mix_data($url);
            if ( is_wp_error($mix_data) ) return $mix_data;

            //populate mix ID
            if ( !$mix_id = wpsstm_get_array_value(array('id'),  $mix_data) ) {
                return new WP_Error( 'wpsstm_8tracks_missing_mix_id', __('Required mix ID missing.','wpsstm') );
            }

            $url = sprintf('https://8tracks.com/mixes/%s/tracks_for_international.jsonh',$mix_id);
        }

        return $url;

    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'>tracks'),
            'track_artist'      => array('path'=>'performer'),
            'track_title'       => array('path'=>'name')
        );
    }
    
    function get_mix_data($url){
        
        $user_slug = $this->get_user_slug($url);
        $playlist_slug = $this->get_tracklist_slug($url);

        //can request ?
        if ( !$user_slug ){
            return new WP_Error( 'wpsstm_8tracks_missing_user_slug', __('Required user slug missing.','wpsstm') );
        }
        if ( !$playlist_slug ){
            return new WP_Error( 'wpsstm_8tracks_missing_tracklist_slug', __('Required tracklist slug missing.','wpsstm') );
        }
        
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
    
    function get_remote_title($title,$remote){
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
            $mix_data = $this->get_mix_data($remote->feed_url_no_filters);
            if ( !is_wp_error( $mix_data ) ){
                $title = wpsstm_get_array_value('name', $mix_data);
            }
        }
        return $title;
    }
}

function wpsstm_8tracks_init(){
    new WPSSTM_8tracks();
}

add_action('wpsstm_init','wpsstm_8tracks_init');