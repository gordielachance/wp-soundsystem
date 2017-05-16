<?php

//https://github.com/matt-oakes/PHP-Last.fm-API/

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WP_SoundSytem_Core_LastFM{
    
    var $user_option_name = '_wpsstm_lastfm_username';
    var $user_session_name = '_wpsstm_lastfm_session';
    var $qvar_after_app_auth = 'wpsstm_lastfm_after_app_auth';
    
    private $api_key = null;
    private $api_secret = null;
    
    //user
    private $username = null;
    private $session_key = null;
    private $subscriber = null;

    private $token = null;
    private $auth = null;
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_LastFM;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        require_once(wpsstm()->plugin_dir . 'lastfm/_inc/php/autoload.php');
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
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

        add_filter( 'query_vars', array($this,'add_query_var_token'));
        add_action( 'wp', array($this,'after_app_auth_set_user_token') );
        add_action( 'wp', array($this,'check_is_user_auth') );
        add_action( 'wp', array($this,'tests') );

    }
    
    function tests(){
        
        $track = new WP_SoundSystem_Track(array('artist'=>'u2','title'=>'Sunday Bloody Sunday'));
        $loved = $this->love_track($track);
        print_r($loved);
        die();
        
        /*
        $bio = $this->get_artist_bio('u2');
        print_r($bio);
        die();
        */

        /*
        $track = new WP_SoundSystem_Track(array('artist'=>'u2','title'=>'Sunday Bloody Sunday'));
        $track_results = $this->search_track($track);
        print_r($track_results);
        */
        /*
        $url = $this->get_user_auth_url();
        print_r($url);die();
        
        $session = $this->request_user_session();
        print_r($session);die();

        die();
        */
    }
    
    /**
    *   Add the 'xspf' query variable so Wordpress
    *   won't mangle it.
    */
    function add_query_var_token($vars){
        $vars[] = $this->qvar_after_app_auth;
        return $vars;
    }
    
    public function get_lastfm_username($user_id = null){
        
        //TO FIX 
        return 'grosbouff';
        
        if (!$user_id) $user_id = get_current_user_id();
        
        if ($user_id){
            return get_user_option( $this->user_option_name, $user_id );
        }
    }

    /*
    After user has authorized app on Last.FM; detect callback and set token transient.
    */
    
    function after_app_auth_set_user_token(){
        $query_var = get_query_var($this->qvar_after_app_auth);
        if (!$query_var) return;
        $token = ( isset($_GET['token']) ) ? $_GET['token'] : null;
        if (!$token) return;
        $this->set_user_token($token);

    }
    
    function check_is_user_auth(){

        $auth = $this->get_auth(true);
        
        if ( is_wp_error($auth) ){
            $code = $auth->get_error_code();
            $api_code = $auth->get_error_data($code);
            
            switch ($api_code){
                case 4: //'Unauthorized Token - This token has not been issued' - probably expired
                    $token_name = $this->get_transient_token_name();
                    delete_transient( $token_name );
                    $this->check_is_user_auth(); //redo
                break;
                case 14: //'Unauthorized Token - This token has not been authorized'
                    
                    $redirect_url = home_url();

                    $args = array(
                        $this->qvar_after_app_auth => true
                    );

                    $redirect_url = add_query_arg($args,$redirect_url);

                    $redirect_url = $this->get_user_auth_url($redirect_url);
                    wp_redirect($redirect_url);
                    
                break;
            }

        }

        
    }
    
    function get_transient_token_name(){
        if ( !$user_id = get_current_user_id() ) return false;
        return sprintf( 'wpsstm_lastfm_usertoken_%s',$user_id );
    }
    
    public function get_user_token(){
        
        if ( !$token_name = $this->get_transient_token_name() ) return;

        if ( false === ( $token = get_transient( $token_name ) ) ) {
            
            $token = $this->request_token();

            //TO FIX needed ?
            $this->set_user_token($token);

        }

        return $token;
    }
    
    private function set_user_token($token){
        if ( !$token_name = $this->get_transient_token_name() ) return;
        set_transient( $token_name, (string)$token, 1 * HOUR_IN_SECONDS );
    }

    public function request_token(){
        
        if ($this->token === null){

            $this->token = false;
            
            if ( !$this->api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );
            if ( !$this->api_secret ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API secret missing", "wpsstm" ) );

            try {
                $authentication = new AuthApi('gettoken', array(
                    'apiKey' =>     $this->api_key,
                    'apiSecret' =>  $this->api_secret
                ));
            }catch(Exception $e){
                return $this->handle_exception($e);
            }

            $this->token = $authentication->token;
        }
        
        return $this->token;

    }
    
    public function get_user_auth_url($callback_url = null){
        $url = 'http://www.last.fm/api/auth/';
        
        $args = array(
            'api_key'   => $this->api_key,
            'cb'        => $callback_url
        );
        
        $args = array_filter($args);
        
        $url = add_query_arg($args,$url);
        return $url;
    }

    public function get_auth($logged=false){

        if ( !$this->api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );

        $auth_args = array(
            'apiKey' => $this->api_key
        );

        if ($logged){

            if ( !$this->api_secret ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API secret missing", "wpsstm" ) );
            
            $user_session = $this->get_user_session();
            
            if ( is_wp_error($user_session) ) return $user_session;

            $advanced_auth_args = array(
                'apiSecret' =>  $this->api_secret,
                'sessionKey' => $user_session,
                'username' =>   $this->get_lastfm_username(),
                'subscriber' => 0
            );

            $auth_args = array_merge($auth_args,$advanced_auth_args);

        }

        try{
            $this->auth = new AuthApi('setsession', $auth_args);
        }catch(Exception $e){
            return $this->handle_exception($e);
        }

        return $this->auth;
    }
    
    //TO FIX handle when session is no mor evalid (eg. app has been revoked by user)
    
    public function get_user_session($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        if ( !$user_session = get_user_option( $this->user_session_name, $user_id ) ){
            $user_session = $this->request_user_session();
            if ( !is_wp_error($user_session) ){
                update_user_option( $user_id, $this->user_session_name, $user_session );
            }
        }

        return $user_session;
    }

    public function request_user_session(){
        //TO FIX store/get session in a transient ?
        
        if ($this->session_key === null) {
            
            $this->session_key = false;
            
            $token = $this->get_user_token();

            if ( is_wp_error($token) ) return $token;
            if ( !$token ) return new WP_Error( 'lastfm_php_api', __('Last.FM PHP Api Error: You must provilde a valid api token','wpsstm') );

            try {

                $auth_args = array(
                    'apiKey' =>     $this->api_key,
                    'apiSecret' =>  $this->api_secret,
                    'token' =>      $token
                );

                $authorization = new AuthApi('getsession', $auth_args);

                $this->username = $authorization->username;
                $this->subscriber = $authorization->subscriber;
                $this->session_key = $authorization->sessionKey;

            }catch(Exception $e){
                return $this->handle_exception($e);
            }
        }
        
        return $this->session_key;


        
    }

    public function get_artist_bio($artist){
        
        $auth = $this->get_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        $results = null;
        
        try {
            $artist_api = new ArtistApi($auth);
            $artistInfo = $artist_api->getInfo(array("artist" => $artist));
            $results = $artistInfo['bio'];
        }catch(Exception $e){
            return $this->handle_exception($e);
        }
        
        return $results;
    }
    
    public function search_track(WP_SoundSystem_Track $track,$limit=1,$page=null){
        
        $auth = $this->get_auth();
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
            return $this->handle_exception($e);
        }
        
        return $results;
    }
    
    public function love_track(WP_SoundSystem_Track $track){
        
        $auth = $this->get_auth(true);
        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        $results = null;
        
        try {
            $track_api = new TrackApi($auth);
            $results = $track_api->love(array(
                'artist' => $track->artist,
                'track' =>  $track->title
            ));
        }catch(Exception $e){
            return $this->handle_exception($e);
        }
        
        return $results;
    }
    
    private function handle_exception($e){
        return new WP_Error( 'lastfm_php_api', sprintf(__('Last.FM PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage()),$e->getCode() );
    }
    
}


function wpsstm_lastfm() {
	return WP_SoundSytem_Core_LastFM::instance();
}

wpsstm_lastfm();