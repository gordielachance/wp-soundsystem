<?php

class WPSSTM_Souncloud{
    static $mimetype = 'video/soundcloud';
    function __construct(){
        add_filter('wpsstm_get_source_mimetype',array(__class__,'get_soundcloud_source_type'),10,2);
        add_filter('wpsstm_get_source_stream_url',array(__class__,'get_soundcloud_stream_url'),10,2);
        if ( wpsstm()->get_options('soundcloud_client_id') ){
            add_filter('wpsstm_wizard_services_links',array($this,'register_soundcloud_service_links'));
            add_action('wpsstm_before_remote_response',array($this,'register_soundcloud_preset'));
        }
    }
    //register preset
    function register_soundcloud_preset($tracklist){
        new WPSSTM_Soundcloud_User_Api_Preset($tracklist);
    }
    function register_soundcloud_service_links($links){
        $links[] = array(
            'slug'      => 'soundcloud',
            'name'      => 'SoundCloud',
            'url'       => 'https://soundcloud.com'
        );
        return $links;
    }

    public static function get_soundcloud_source_type($type,WPSSTM_Source $source){
        if ( self::get_sc_track_id($source->permalink_url) ){
            $type = self::$mimetype;
        }
        return $type;
    }
    public static function get_sc_track_id($url){

        /*
        check for souncloud API track URL
        
        https://api.soundcloud.com/tracks/9017297
        */

        $pattern = '~https?://api.soundcloud.com/tracks/([^/]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches[1]) ){
            return $url_matches[1];
        }
        
        /*
        check for souncloud widget URL
        
        https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/282715465&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&visual=true
        */
        
        $pattern = '~https?://w.soundcloud.com/player/.*tracks/([^&]+)~';
        preg_match($pattern, $url, $url_matches);

        if ( isset($url_matches[1]) ){
            return $url_matches[1];
        }

        /*
        check for souncloud track URL
        
        https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou
        */

        $pattern = '~https?://(?:www.)?soundcloud.com/([^/]+)/([^/]+)~';
        preg_match($pattern, $url, $url_matches);
        
        if ( isset($url_matches[1]) && isset($url_matches[2]) ){
            return self::request_sc_track_id($url);
        }

    }
    
    public static function get_soundcloud_stream_url($url,WPSSTM_Source $source){

        $client_id = wpsstm()->get_options('soundcloud_client_id');
        $sc_track_id = self::get_sc_track_id($url);

        if ($sc_track_id){
            //widget URL
            $widget_url = 'https://w.soundcloud.com/player/';
            $track_url = sprintf('http://api.soundcloud.com/tracks/%s',$sc_track_id);
            $widget_args = array(
                'url' =>        urlencode ($track_url),
                'autoplay' =>   false
            );
            $url = add_query_arg($widget_args,$widget_url);
            
            if ( $client_id ){ //stream url
                $url = sprintf('https://api.soundcloud.com/tracks/%s/stream?client_id=%s',$sc_track_id,$client_id);
            }
            
        }
        


        return $url;

    }
    /*
    Scripts/Styles to load
    */
    public function provider_scripts_styles(){
        if (!$this->client_id){ //soundcloud renderer (required for soundcloud widget)
            wp_enqueue_script('wp-mediaelement-renderer-soundcloud',includes_url('js/mediaelement/renderers/soundcloud.min.js'), array('wp-mediaelement'), '4.0.6');    
        }
    }

    /*
    Get the ID of a Soundcloud track URL (eg. https://soundcloud.com/phasescachees/jai-toujours-reve-detre-un-gangster-feat-hippocampe-fou)
    Requires a Soundcloud Client ID.
    Store result in a transient to speed up page load.
    //TO FIX IMPORTANT slows down the website on page load.  Rather should run when source is saved ?
    */
    
    private static function request_sc_track_id($url){
        
        $client_id = wpsstm()->get_options('soundcloud_client_id');
        if ( !$client_id ) return;
        
        $transient_name = 'wpsstm_sc_track_id_' . md5($url);

        if ( false === ( $sc_id = get_transient($transient_name ) ) ) {

            $api_args = array(
                'url' =>        urlencode ($url),
                'client_id' =>  $client_id
            );

            $api_url = 'https://api.soundcloud.com/resolve.json';
            $api_url = add_query_arg($api_args,$api_url);

            $response = wp_remote_get( $api_url );
            $json = wp_remote_retrieve_body( $response );
            if ( is_wp_error($json) ) return;
            $data = json_decode($json,true);
            if ( isset($data['id']) ) {
                $sc_id = $data['id'];
                set_transient( $transient_name, $sc_id, 7 * DAY_IN_SECONDS );
            }
        }
        return $sc_id;
    }
}

class WPSSTM_Soundcloud_User_Api_Preset{

    function __construct($remote){
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );

    }

    function can_handle_url($url){
        $user_slug = $this->get_user_slug($url);
        if ( !$user_slug ) return;
        return true;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url($url) ){

            if ( !$user_id = $this->get_user_id($url) ){
                return new WP_Error( 'wpsstm_soundcloud_missing_user_id', __('Required user ID missing.','wpsstm') );
            }

            $api_page = null;
            $page_slug = $this->get_user_page($url);
            $client_id = wpsstm()->get_options('soundcloud_client_id');

            switch($page_slug){
                case 'likes':
                    $this->api_page = 'favorites';
                break;
                default:
                    $this->api_page = 'tracks';
                break;
            }

            $url = sprintf('http://api.soundcloud.com/users/%s/%s?client_id=%s',$user_id,$this->api_page,$client_id);
        }
        
        return $url;

    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'element'),
            'track_artist'      => array('path'=>'user username'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'artwork_url')
        );
    }

    function get_user_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?soundcloud.com/([^/]+)~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?soundcloud.com/[^/]+/([^/]+)~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_id($url){

        $user_slug = $this->get_user_slug($url);
        if (!$user_slug) return;
        
        $transient_name = 'wpsstm-soundcloud-' . $user_slug . '-userid';

        if ( false === ( $user_id = get_transient($transient_name ) ) ) {

            $client_id = wpsstm()->get_options('soundcloud_client_id');

            $api_url = sprintf('http://api.soundcloud.com/resolve.json?url=http://soundcloud.com/%s&client_id=%s',$user_slug,$client_id);
            $response = wp_remote_get( $api_url );

            if ( is_wp_error($response) ) return;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );
            if ( is_wp_error($content) ) return;
            $content = json_decode($content);

            if ( $user_id = $content->id ){
                set_transient( $transient_name, $user_id );
            }

        }
        
        return $user_id;

    }
    
    function get_remote_title($title,$remote){
        
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
            
            $user_slug = $this->get_user_slug($remote->feed_url_no_filters);
            $page_slug = $this->get_user_page($remote->feed_url_no_filters);
            $title = sprintf(__('%s on Soundcloud','wpsstm'),$user_slug);
            $subtitle = null;

            switch($page_slug){
                case 'likes':
                    $subtitle = __('Favorite tracks','wpsstm');
                break;
                default: //tracks
                    $subtitle = __('Tracks','wpsstm');
                break;
            }

            if ($subtitle){
                $title = $title . ' - ' . $subtitle;
            }

        }

        return $title;
    }

}

function wpsstm_souncloud_init(){
    new WPSSTM_Souncloud();
}

add_action('wpsstm_init','wpsstm_souncloud_init');