<?php

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WPSSTM_Core_LastFM{
    
    static $lastfm_user_api_metas_name = '_wpsstm_lastfm_api';
    static $qvar_after_app_auth = 'wpsstm_lastfm_after_app_auth';
    
    public $lastfm_user = null;

    public function __construct() {
        
        add_action( 'wp', array($this,'after_app_auth') );
        add_action( 'init', array($this,'setup_lastfm_user') ); //TO FIX only if player is loaded ?
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles_shared'));
        add_action( 'admin_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles_shared'));
        add_filter('wpsstm_get_player_actions', array($this,'get_lastfm_actions'));
        
        /*
        AJAX
        */
        
        //enable scrobbler
        add_action('wp_ajax_wpsstm_lastfm_enable_scrobbler',array($this,'ajax_lastm_enable_scrobbler') );
        add_action('wp_ajax_nopriv_wpsstm_lastfm_enable_scrobbler', array($this,'ajax_lastm_enable_scrobbler')); //so we can output the non-logged user notice

        //love & unlove
        add_action('wp_ajax_wpsstm_lastfm_user_toggle_love_track',array($this,'ajax_lastm_toggle_love_track') );
        
        //updateNowPlaying
        add_action('wp_ajax_wpsstm_user_update_now_playing_lastfm_track', array($this,'ajax_lastfm_now_playing_track'));
        
        //scrobble user
        add_action('wp_ajax_wpsstm_lastfm_scrobble_user_track', array($this,'ajax_lastfm_scrobble_track'));
        
        //scrobble community
        add_action('wp_ajax_wpsstm_lastfm_scrobble_community_track', array($this,'ajax_lastfm_scrobble_community_track'));
        add_action('wp_ajax_nopriv_wpsstm_lastfm_scrobble_community_track', array($this,'ajax_lastfm_scrobble_community_track'));
        
        
        
    }

    function setup_lastfm_user(){
        $this->lastfm_user = new WPSSTM_LastFM_User();
    }
    
    function enqueue_lastfm_scripts_styles_shared(){

        //CSS
        //wp_enqueue_style( 'wpsstm-lastfm',  wpsstm()->plugin_url . '_inc/css/wpsstm-lastfm.css', null, wpsstm()->version );
        
        //JS
        wp_enqueue_script( 'wpsstm-lastfm', wpsstm()->plugin_url . '_inc/js/wpsstm-lastfm.js', array('jquery'),wpsstm()->version);
        
        //localize vars
        $localize_vars=array(
            'lastfm_scrobble_along'     => ( self::can_community_scrobble() && ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' ) ),
        );

        wp_localize_script('wpsstm-lastfm','wpsstmLastFM', $localize_vars);
    }

    /*
    After user has authorized app on Last.fm; detect callback and set token transient.
    */
    
    public function after_app_auth(){
        if ( !isset($_GET[self::$qvar_after_app_auth]) ) return;
        $token = ( isset($_GET['token']) ) ? $_GET['token'] : null;
        if (!$token) return;
        $this->lastfm_user->set_user_token($token);

    }

                            
    /*
    Api request for a token
    */

    public static function request_auth_token(){
        
        $api_key = wpsstm()->get_options('lastfm_client_id');
        $api_secret = wpsstm()->get_options('lastfm_client_secret');

        if ( !$api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.fm API key missing", "wpsstm" ) );
        if ( !$api_secret ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.fm API secret missing", "wpsstm" ) );

        try {
            $authentication = new AuthApi('gettoken', array(
                'apiKey' =>     $api_key,
                'apiSecret' =>  $api_secret
            ));
        }catch(Exception $e){
            return self::handle_api_exception($e);
        }

        return $authentication->token;
    }
                            
    /*
    Get the URL of the app authentification at last.fm.
    */
    
    private static function get_app_auth_url(){
        $url = 'http://www.last.fm/api/auth/';
        
        if ( !$callback_url = get_permalink() ){
            $callback_url = home_url();
        }
        
        //add qvar_after_app_auth variable so we can intercept the token when returning to our website
        $callback_args = array(
            self::$qvar_after_app_auth => true
        );

        $callback_url = add_query_arg($callback_args,$callback_url);
        
        $args = array(
            'api_key'   => wpsstm()->get_options('lastfm_client_id'),
            'cb'        => $callback_url
        );
        
        $args = array_filter($args);
        
        $url = add_query_arg($args,$url);
        return $url;
    }
    
    /*
    Get basic API authentification
    */
    
    private static function get_basic_api_auth(){
        
        //TO FIX store temporary ?
        $basic_auth = null;
        
        $api_key = wpsstm()->get_options('lastfm_client_id');
        if ( !$api_key ) return new WP_Error( 'lastfm_missing_credentials', __( "Required Last.fm API key missing", "wpsstm" ) );

        $auth_args = array(
            'apiKey' => $api_key
        );

        try{
            $basic_auth = new AuthApi('setsession', $auth_args);
        }catch(Exception $e){
            $basic_auth = self::handle_api_exception($e);
        }

        return $basic_auth;

    }

    public static function handle_api_exception($e){
        $message = sprintf(__('Last.fm PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage());
        wpsstm()->debug_log($message);
        return new WP_Error( 'lastfm_php_api', new WP_Error( 'lastfm_php_api',$message,$e->getCode() ) );
    }
    
    public static function search_artists($input){
        $auth = self::get_basic_api_auth();

        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        $results = null;

        try {
            $artist_api = new ArtistApi($auth);
            $results = $artist_api->search(array("artist" => $input));
        }catch(Exception $e){
            return self::handle_api_exception($e);
        }
        
        return $results;
    }

    public static function get_artist_bio($artist){
        
        $auth = self::get_basic_api_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        $results = null;
        
        try {
            $artist_api = new ArtistApi($auth);
            $artistInfo = $artist_api->getInfo(array("artist" => $artist));
            $results = $artistInfo['bio'];
        }catch(Exception $e){
            return self::handle_api_exception($e);
        }
        
        return $results;
    }
    
    public static function search_track(WPSSTM_Track $track,$limit=1,$page=null){
        
        $auth = self::get_basic_api_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        $results = null;

        try {
            $track_api = new TrackApi($auth);
            $results = $track_api->search(array(
                    'artist' => $track->artist,
                    'track' =>  $track->title,
                    'limit' =>  $limit
                )
            );
        }catch(Exception $e){
            return self::handle_api_exception($e);
        }
        
        return $results;
    }
    
    function ajax_lastm_enable_scrobbler(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'message'   => null
        );
        
        if ( !get_current_user_id() ){
            $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
            $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
            $result['notice'] = sprintf('<p id="wpsstm-dialog-auth-notice">%s</p>',$wp_auth_text);
        }else{
            $is_lastfm_auth = (int)$this->lastfm_user->is_user_api_logged();
            
            if (!$is_lastfm_auth){
                $lastfm_auth_url = self::get_app_auth_url();
                $lastfm_auth_link = sprintf('<a href="%s">%s</a>',$lastfm_auth_url,__('here','wpsstm'));
                $lastfm_auth_text = sprintf(__('You need to authorize this website on Last.fm: click %s.','wpsstm'),$lastfm_auth_link);
                $result['notice'] = sprintf('<p id="wpsstm-dialog-lastfm-auth-notice">%s</p>',$lastfm_auth_text);
            }else{
                $result['success'] = true;
            }
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_lastm_toggle_love_track(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'message'   => null
        );
        
        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        $do_love = $result['do_love'] = filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN); //ajax do send strings
        $success = $this->lastfm_user->love_lastfm_track($track,$do_love);
        $result['track'] = $track;
        
        if ( $success ){
            if ( is_wp_error($success) ){
                $code = $success->get_error_code();
                $result['message'] = $success->get_error_message($code); 
            }else{
                $result['success'] = true;
            }
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_lastfm_now_playing_track(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        
        $start_timestamp = $result['playback_start'] = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
        $success = $this->lastfm_user->now_playing_lastfm_track($track,$start_timestamp);
        $result['track'] = $track;

        if ( $success ){
            if ( is_wp_error($success) ){
                $code = $success->get_error_code();
                $result['message'] = $success->get_error_message($code); 
            }else{
                $result['success'] = true;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_lastfm_scrobble_track(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
        );
        
        $start_timestamp = $result['playback_start'] = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
        
        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);

        $success = $this->lastfm_user->scrobble_lastfm_track($track,$start_timestamp);
        $result['track'] = $track;

        if ( $success ){
            if ( is_wp_error($success) ){
                $code = $success->get_error_code();
                $result['message'] = $success->get_error_message($code); 
            }else{
                $result['success'] = true;
            }
        }
        
        header('Content-type: application/json');
        wp_send_json( $result );
        
    }

    function ajax_lastfm_scrobble_community_track(){
        
        $enabled = ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' );
        $ajax_data = wp_unslash($_POST);
        $community_user_id = wpsstm()->get_options('community_user_id');

        $result = array(
            'input' =>              $ajax_data,
            'message' =>            null,
            'success' =>            false,
            'community_user_id' =>  $community_user_id
        );
        
        if ( $community_user_id && $enabled ){
            
            

            $start_timestamp = $result['playback_start'] = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
            
            $track = new WPSSTM_Track();
            $track->from_array($ajax_data['track']);
            $result['track'] = $track;

            //check that the new submission has not been sent just before
            $last_scrobble_meta_key = 'wpsstm_last_scrobble';
            $track_arr = $track->to_ajax();
            $last_scrobble = get_user_meta($community_user_id, $last_scrobble_meta_key, true);
            
            if ( $last_scrobble == $track_arr ){
                
                $result['message'] = 'This track has already been scrobbled by the bot: ' . json_encode($track_arr,JSON_UNESCAPED_UNICODE); 
                
            }else{

                $community_user = new WPSSTM_LastFM_User($community_user_id);
                $success = $community_user->scrobble_lastfm_track($track,$start_timestamp);

                if ( $success ){
                    if ( is_wp_error($success) ){
                        $code = $success->get_error_code();
                        $result['message'] = $success->get_error_message($code); 
                    }else{
                        $result['success'] = true;
                        
                        //update last bot scrobble
                        update_user_meta( $community_user_id, $last_scrobble_meta_key, $track_arr );
                        
                    }
                }
                
            }

        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
        
    }
    
    function get_lastfm_actions($actions = null){
        
        //enable scrobbler
        if ( wpsstm()->get_options('lastfm_scrobbling') ){
            $actions['scrobbler'] = array(
                'text' =>       __('Last.fm scrobble', 'wpsstm'),
            );
        }
        
        return $actions;
    }
    
    public static function can_community_scrobble(){
        $community_user_id = wpsstm()->get_options('community_user_id');
        if (!$community_user_id) return;
        $community_user = new WPSSTM_LastFM_User($community_user_id);
        return $community_user->is_user_api_logged();
    }

}