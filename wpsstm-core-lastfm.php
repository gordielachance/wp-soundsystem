<?php

//https://github.com/matt-oakes/PHP-Last.fm-API/

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WP_SoundSytem_Core_LastFM{
    
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
        
        /*
        $bio = $this->get_artist_bio('u2');
        print_r($bio);
        die();
        */
        
        $session = $this->get_session();
        print_r($session);die();
        
        $track = new WP_SoundSystem_Track(array('artist'=>'u2','title'=>'Sunday Bloody Sunday'));
        $track_results = $this->search_track($track);
        print_r($track_results);
        die();
    }
    
    function setup_actions(){
    }
    
    public function auth($username=null){

        if ( !$this->api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );

        $auth_args = array(
            'apiKey' => $this->apiKey
        );

        if ($username){

            $this->username = $username;
            if ( !$this->api_secret ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API secret missing", "wpsstm" ) );

            $advanced_auth_args = array(
                'apiSecret' => $this->apiSecret,
                'sessionKey' => $this->sessionKey,
                'username' => $this->username,
                'subscriber' => 0
            );

            $auth_args = array_merge($auth_args,$advanced_auth_args);

        }

        try{
            $this->auth = new AuthApi('setsession', $auth_args);
        }catch(Exception $e){
            return new WP_Error( 'lastfm_php_api', sprintf(__('Last.FM PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage()) );
        }

        return $this->auth;
    }
    
    public function get_token(){
        
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
                return new WP_Error( 'lastfm_php_api', sprintf(__('Last.FM PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage()) );
            }

            $this->token = $authentication->token;
        }
        
        return $this->token;

        
    }
    
    public function get_session(){
        
        $token = $this->get_token();
        
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

            $ok = $username !== null && $subscriber !== null && $sessionKey !== null;
            $this->assertTrue($ok);
            die("cocora");
            
        }catch(Exception $e){
            return new WP_Error( 'lastfm_php_api', sprintf(__('Last.FM PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage()) );
        }
        
    }


    public function get_artist_bio($artist){
        
        $auth = $this->auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;
        
        print_r($auth);die();
        
        $artist_api = new ArtistApi($auth);
        $artistInfo = $artist_api->getInfo(array("artist" => $artist));
        return $artistInfo['bio'];
    }
    
    public function search_track(WP_SoundSystem_Track $track,$limit=1,$page=null){
        
        $auth = $this->auth('grosbouff');
        if ( !$auth || is_wp_error($auth) ) return $auth;

        $track_api = new TrackApi($auth);
        $result = $track_api->search(array(
                'artist' => $track->artist,
                'track' =>  $track->title,
                'limit' =>  $limit
            )
        );
        return $result;
    }
    
}


function wpsstm_lastfm() {
	return WP_SoundSytem_Core_LastFM::instance();
}

wpsstm_lastfm();