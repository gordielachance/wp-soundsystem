<?php

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WP_SoundSystem_Core_LastFM{
    
    var $lastfm_user_api_metas_name = '_wpsstm_lastfm_api';
    var $qvar_after_app_auth = 'wpsstm_lastfm_after_app_auth';
    
    public $api_key = null;
    public $api_secret = null;
    
    private $basic_auth = null;
    
    public $lastfm_user = null;

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_LastFM;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
        
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles_shared'));
        add_action( 'admin_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles_shared'));

        //ajax : love & unlove
        add_action('wp_ajax_wpsstm_lastfm_user_toggle_love_track',array($this,'ajax_lastm_toggle_love_track') );
        
        //ajax : updateNowPlaying
        add_action('wp_ajax_wpsstm_user_update_now_playing_lastfm_track', array($this,'ajax_lastfm_now_playing_track'));
        
        //ajax : scrobble
        add_action('wp_ajax_wpsstm_lastfm_scrobble_user_track', array($this,'ajax_lastfm_scrobble_track'));
        
        add_action('wp_ajax_wpsstm_lastfm_scrobble_community_track', array($this,'ajax_lastfm_scrobble_community_track'));
        add_action('wp_ajax_nopriv_wpsstm_lastfm_scrobble_community_track', array($this,'ajax_lastfm_scrobble_community_track'));
        
    }
    
    function setup_globals(){

        if ( $api_key = wpsstm()->get_options('lastfm_client_id') ){
            $this->api_key = $api_key;
        }

        if ( $api_secret = wpsstm()->get_options('lastfm_client_secret') ){
            $this->api_secret = $api_secret;
        }
    }
    
    function setup_actions(){
        add_action( 'init', array($this,'setup_lastfm_user') );
        add_action( 'wp', array($this,'after_app_auth') );
    }

    function setup_lastfm_user(){
        $this->lastfm_user = new WP_SoundSystem_LastFM_User();
    }
    
    function enqueue_lastfm_scripts_styles_shared(){

        //CSS
        //wp_enqueue_style( 'wpsstm-lastfm',  wpsstm()->plugin_url . '_inc/css/wpsstm-lastfm.css', null, wpsstm()->version );
        
        //JS
        wp_enqueue_script( 'wpsstm-lastfm', wpsstm()->plugin_url . '_inc/js/wpsstm-lastfm.js', array('jquery'),wpsstm()->version);
        
        $lastfm_auth_icon = '<i class="fa fa-lastfm" aria-hidden="true"></i>';
        $lastfm_auth_url = wpsstm_lastfm()->get_app_auth_url();
        $lastfm_auth_link = sprintf('<a href="%s">%s</a>',$lastfm_auth_url,__('here','wpsstm'));
        $lastfm_auth_text = sprintf(__('You need to authorize this website on Last.fm to enable its features: click %s.','wpsstm'),$lastfm_auth_link);
        $lastfm_auth_notice = $lastfm_auth_icon . ' ' . $lastfm_auth_text;
        
        //localize vars
        $localize_vars=array(
            'lastfm_scrobble_along'     => ( $this->can_community_scrobble() && ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' ) ),
            'is_user_api_logged'        => (int)$this->lastfm_user->is_user_api_logged(),
            'lastfm_auth_notice'        => $lastfm_auth_notice
        );

        wp_localize_script('wpsstm-lastfm','wpsstmLastFM', $localize_vars);
    }

    /*
    After user has authorized app on Last.FM; detect callback and set token transient.
    */
    
    public function after_app_auth(){
        if ( !isset($_GET[$this->qvar_after_app_auth]) ) return;
        $token = ( isset($_GET['token']) ) ? $_GET['token'] : null;
        if (!$token) return;
        $this->lastfm_user->set_user_token($token);

    }

                            
    /*
    Api request for a token
    */

    public function request_auth_token(){

        if ( !$this->api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );
        if ( !$this->api_secret ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API secret missing", "wpsstm" ) );

        try {
            $authentication = new AuthApi('gettoken', array(
                'apiKey' =>     $this->api_key,
                'apiSecret' =>  $this->api_secret
            ));
        }catch(Exception $e){
            return $this->handle_api_exception($e);
        }

        return $authentication->token;
    }
                            
    /*
    Get the URL of the app authentification at last.fm.
    */
    
    public function get_app_auth_url(){
        $url = 'http://www.last.fm/api/auth/';
        
        if ( !$callback_url = get_permalink() ){
            $callback_url = home_url();
        }
        
        //add qvar_after_app_auth variable so we can intercept the token when returning to our website
        $callback_args = array(
            $this->qvar_after_app_auth => true
        );

        $callback_url = add_query_arg($callback_args,$callback_url);
        
        $args = array(
            'api_key'   => $this->api_key,
            'cb'        => $callback_url
        );
        
        $args = array_filter($args);
        
        $url = add_query_arg($args,$url);
        return $url;
    }
    
    /*
    Get basic API authentification
    */
    
    private function get_basic_api_auth(){
        
        if ( !$this->api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );
        
        if ($this->basic_auth === null){
            
            $basic_auth = false;

            $auth_args = array(
                'apiKey' => $this->api_key
            );

            try{
                $basic_auth = new AuthApi('setsession', $auth_args);
            }catch(Exception $e){
                $basic_auth = $this->handle_api_exception($e);
            }
            
            $this->basic_auth = $basic_auth;
            
        }
        
        return $this->basic_auth;

    }
    


    public function handle_api_exception($e){
        return new WP_Error( 'lastfm_php_api', sprintf(__('Last.FM PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage()),$e->getCode() );
    }

    public function get_artist_bio($artist){
        
        $auth = $this->get_basic_api_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        $results = null;
        
        try {
            $artist_api = new ArtistApi($auth);
            $artistInfo = $artist_api->getInfo(array("artist" => $artist));
            $results = $artistInfo['bio'];
        }catch(Exception $e){
            return $this->handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function search_track(WP_SoundSystem_Track $track,$limit=1,$page=null){
        
        $auth = $this->get_basic_api_auth();
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
            return $this->handle_api_exception($e);
        }
        
        return $results;
    }
    
    function ajax_lastm_toggle_love_track(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'message'   => null
        );
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        $track = $result['track'] = new WP_SoundSystem_Track($post_id);
        $do_love = $result['do_love'] = filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN); //ajax do send strings
        //$success = $this->lastfm_user->love_lastfm_track($track,$do_love);
        
        $community_user_id = wpsstm()->get_options('community_user_id');
        $community_user = new WP_SoundSystem_LastFM_User($community_user_id);
        $success = $community_user->love_lastfm_track($track,$do_love);
        
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
        
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        $track = $result['track'] = new WP_SoundSystem_Track($post_id);
        
        $success = $this->lastfm_user->now_playing_lastfm_track($track);

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
            'success'   => false
        );
        
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        $start_timestamp = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
        
        $track = $result['track'] = new WP_SoundSystem_Track($post_id);
        $success = $this->lastfm_user->scrobble_lastfm_track($track,$start_timestamp);

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
            
            $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
            $start_timestamp = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
            
            $track = $result['track'] = new WP_SoundSystem_Track($post_id);

            //check that the new submission has not been sent just before
            $last_scrobble_meta_key = 'wpsstm_last_scrobble';
            $track_arr = $track->array_export();
            $last_scrobble = get_user_meta($community_user_id, $last_scrobble_meta_key, true);
            
            if ( $last_scrobble == $track_arr ){
                
                $result['message'] = 'This track has already been scrobbled by the bot: ' . json_encode($track_arr,JSON_UNESCAPED_UNICODE); 
                
            }else{

                $community_user = new WP_SoundSystem_LastFM_User($community_user_id);
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
    
    function get_scrobbler_icons(){
        $scrobbling_classes = array();

        $scrobbling_classes_str = wpsstm_get_classes_attr($scrobbling_classes);

        $loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';
        $icon_scrobbler =  '<i class="fa fa-lastfm" aria-hidden="true"></i>';
        $enabled_link = sprintf('<a id="wpsstm-enable-scrobbling" href="#" title="%s" class="wpsstm-requires-auth wpsstm-requires-lastfm-auth wpsstm-player-action wpsstm-player-enable-scrobbling">%s</a>',__('Enable Last.fm scrobbling','wpsstm'),$icon_scrobbler);
        $disabled_link = sprintf('<a id="wpsstm-disable-scrobbling" href="#" title="%s" class="wpsstm-requires-auth wpsstm-requires-lastfm-auth wpsstm-player-action wpsstm-player-disable-scrobbling">%s</a>',__('Disable Last.fm scrobbling','wpsstm'),$icon_scrobbler);
        return sprintf('<span id="wpsstm-player-toggle-scrobble" %s>%s%s%s</span>',$scrobbling_classes_str,$loading,$disabled_link,$enabled_link);
    }
    
    function can_community_scrobble(){
        $community_user_id = wpsstm()->get_options('community_user_id');
        if (!$community_user_id) return;
        $community_user = new WP_SoundSystem_LastFM_User($community_user_id);
        return $community_user->is_user_api_logged();
    }

}


function wpsstm_lastfm() {
	return WP_SoundSystem_Core_LastFM::instance();
}

wpsstm_lastfm();