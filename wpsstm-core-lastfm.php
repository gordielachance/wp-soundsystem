<?php

//https://github.com/matt-oakes/PHP-Last.fm-API/
//TO FIX handle when session is no mor evalid (eg. app has been revoked by user)

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WP_SoundSytem_Core_LastFM{
    
    var $lastfm_user_api_metas_name = '_wpsstm_lastfm_api';
    var $qvar_after_app_auth = 'wpsstm_lastfm_after_app_auth';
    
    private $api_key = null;
    private $api_secret = null;

    private $user_api_metas = null;
    public $is_user_api_logged = null;
    private $token = null;
    
    private $basic_auth = null;
    private $user_auth = null;

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
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles'));

        //ajax : love & unlove
        add_action( 'wp_ajax_wpsstm_lastfm_love_unlove_track',array($this,'ajax_love_unlove_track') );
        
        //ajax : updateNowPlaying
        add_action('wp_ajax_wpsstm_lastfm_update_now_playing_track', array($this,'ajax_update_now_playing_track'));
        
        //ajax : scrobble
        add_action('wp_ajax_wpsstm_lastfm_scrobble_track', array($this,'ajax_scrobble_track'));
        
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
        add_action( 'wp', array($this,'after_app_auth_set_user_token') );
    }
    
    function enqueue_lastfm_scripts_styles(){

        //CSS
        //wp_enqueue_style( 'wpsstm-lastfm',  wpsstm()->plugin_url . 'lastfm/_inc/css/wpsstm-lastfm.css', null, wpsstm()->version );
        
        //JS
        wp_enqueue_script( 'wpsstm-lastfm', wpsstm()->plugin_url . 'lastfm/_inc/js/wpsstm-lastfm.js', array('jquery'),wpsstm()->version);
        
        //localize vars
        $localize_vars=array(
            'is_api_logged'             => (int)$this->is_user_api_logged(),
            //'lastfm_client_id'        => wpsstm()->get_options('lastfm_client_id'),
            //'lastfm_client_secret'    => wpsstm()->get_options('lastfm_client_secret'),
        );

        wp_localize_script('wpsstm-lastfm','wpsstmLastFM', $localize_vars);
    }

    /*
    After user has authorized app on Last.FM; detect callback and set token transient.
    */
    
    public function after_app_auth_set_user_token(){
        if ( !isset($_GET[$this->qvar_after_app_auth]) ) return;
        $token = ( isset($_GET['token']) ) ? $_GET['token'] : null;
        if (!$token) return;
        $this->set_user_token($token);

    }
    
    /*
    Checks if user can authentificate to last.fm 
    If not, clean database and return false.
    */

    public function is_user_api_logged(){

        if ($this->is_user_api_logged === null) {
            
            $is_user_api_logged = false;

            if ( $user_id = get_current_user_id() ) {

                $auth = $this->get_user_api_auth();

                if ( is_wp_error($auth) ){
                    $code = $auth->get_error_code();
                    $api_code = $auth->get_error_data($code);

                    switch ($api_code){
                        case 4: //'Unauthorized Token - This token has not been issued' - probably expired
                            $token_name = $this->get_transient_token_name();
                            delete_transient( $token_name );
                        break;
                        case 14: //'Unauthorized Token - This token has not been authorized'
                            $this->delete_lastfm_user_api_metas(); //TO FIX at the right place ?
                        break;
                    }

                }else{
                    $is_user_api_logged = true;
                }
            }

            $this->is_user_api_logged = $is_user_api_logged;
            
            wpsstm()->debug_log($is_user_api_logged,"lastfm - is_user_api_logged()");
            
        }
        
        return $this->is_user_api_logged;

    }
    
    /*
    Get the name of the transient that stores the token for a user ID
    */

    private function get_transient_token_name(){
        if ( !$user_id = get_current_user_id() ) return false;
        return sprintf( 'wpsstm_lastfm_usertoken_%s',$user_id );
    }
    
    /*
    Get the user token from a transient, or request it.
    */
    
    private function get_user_token(){

        if ( $this->token === null ){
            $this->token = false;
            $token = $token_transient = $token_request = null;
            $token_name = $this->get_transient_token_name();

            if ( !$token_name = $this->get_transient_token_name() ) return; //user not logged
            
            if ( $token = $token_transient = get_transient( $token_name ) ) {
                $this->token = $token;
            }else{
                $token = $token_request = $this->request_token();
                if ( is_wp_error($token) ) return $token;
                $this->token = (string)$this->set_user_token($token);
            }
        }
        
        wpsstm()->debug_log(json_encode(array('token'=>$this->token,'transient'=>(bool)$token_transient,'request'=>(bool)$token_request)),"lastfm - get_user_token()");
        return $this->token;
    }
                            
    /*
    Store the user token for 1 hour (last.fm token duration)
    */
    
    private function set_user_token($token){
        if ( !$token_name = $this->get_transient_token_name() ) return;
        wpsstm()->debug_log((string)$token,"lastfm - set_user_token()"); 
        if ( set_transient( $token_name, (string)$token, 1 * HOUR_IN_SECONDS ) ){
            return $token;
        }
    }
                            
    /*
    Api request for a token
    */

    private function request_token(){

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
    
    public function get_app_auth_url($callback_url = null){
        $url = 'http://www.last.fm/api/auth/';
        
        if (!$callback_url){ //default callblack
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
    
    /*
    Get API authentification for a user
    */

    private function get_user_api_auth(){
        
        if ( !$this->api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );
        if ( !$this->api_secret ) return new WP_Error( 'lastfm_no_api_secret', __( "Required Last.FM API secret missing", "wpsstm" ) );
        
        if ($this->user_auth === null){
            
            $user_auth = false;

            $api_metas = $this->get_lastfm_user_api_metas();
            if ( is_wp_error($api_metas) ) return $api_metas;

            $auth_args = array(
                'apiKey' => $this->api_key,
                'apiSecret' =>  $this->api_secret,
                'sessionKey' => ( isset($api_metas['sessionkey']) ) ? $api_metas['sessionkey'] : null,
                'username' =>   ( isset($api_metas['username']) ) ? $api_metas['username'] : null,
                'subscriber' => ( isset($api_metas['subscriber']) ) ? $api_metas['subscriber'] : null,
            );
            
            wpsstm()->debug_log(json_encode($auth_args),"lastfm - get_user_api_auth()"); 

            try{
                $user_auth = new AuthApi('setsession', $auth_args);
            }catch(Exception $e){
                $user_auth = $this->handle_api_exception($e);
            }
            
            $this->user_auth = $user_auth;
            
        }
        
        return $this->user_auth;

    }
                            
    /*
    Get the user metas stored after a last.fm session has been initialized.
    */
    
    private function get_lastfm_user_api_metas(){
        $user_id = get_current_user_id();
        if (!$user_id) return false;
        
        if ( $this->user_api_metas === null ) {
            $this->user_api_metas = false;

            if ( $api_metas = get_user_meta( $user_id, $this->lastfm_user_api_metas_name, true ) ){
                $this->user_api_metas = $api_metas;
            }else{
                //try to populate it
                $remote_api_metas = $this->request_lastfm_user_api_metas();
                if ( is_wp_error($remote_api_metas) ) return $remote_api_metas;
                $this->user_api_metas = $remote_api_metas;
            }
        }

        return $this->user_api_metas;
    }
                            
    /*
    Request user informations (username and session key) from a token and store it as user meta.
    */

    private function request_lastfm_user_api_metas(){
        $user_id = get_current_user_id();
        if (!$user_id) return false;

        $token = $this->get_user_token();

        if ( is_wp_error($token) ) return $token;
        if ( !$token ) return new WP_Error( 'lastfm_php_api', __('Last.FM PHP Api Error: You must provilde a valid api token','wpsstm') );

        $auth_args = array(
            'apiKey' =>     $this->api_key,
            'apiSecret' =>  $this->api_secret,
            'token' =>      $token
        );

        wpsstm()->debug_log($auth_args,"lastfm - request_lastfm_user_api_metas()"); 

        try {

            $session = new AuthApi('getsession', $auth_args);

            $user_api_metas = array(
                'username'      => $session->username,
                'subscriber'    => $session->subscriber,
                'sessionkey'    => $session->sessionKey,

            );

            wpsstm()->debug_log(json_encode($user_api_metas),"lastfm - request_lastfm_user_api_metas()");

            update_user_meta( $user_id, $this->lastfm_user_api_metas_name, $user_api_metas );


        }catch(Exception $e){
            return $this->handle_api_exception($e);
        }

        return $user_api_metas;

    }
    
    private function handle_api_exception($e){
        return new WP_Error( 'lastfm_php_api', sprintf(__('Last.FM PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage()),$e->getCode() );
    }
    
    private function delete_lastfm_user_api_metas(){
        $user_id = get_current_user_id();
        if (!$user_id) return false;
        if ( delete_user_meta( $user_id, $this->lastfm_user_api_metas_name ) ){
            $this->user_api_metas = false;
        }
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
    
    public function love_track(WP_SoundSystem_Track $track,$do_love = null){

        if ( !get_current_user_id() ) return false;
        if ( !$this->is_user_api_logged() ) return false;
        if ($do_love === null) return;

        $auth = $this->get_user_api_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;

        $results = null;
        
        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );
        
        wpsstm()->debug_log(json_encode($api_args),"lastfm - lastfm_love_track()");
        
        try {
            $track_api = new TrackApi($auth);
            if ($do_love){
                $results = $track_api->love($api_args);
            }else{
                $results = $track_api->unlove($api_args);
            }
        }catch(Exception $e){
            return $this->handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function now_playing_track(WP_SoundSystem_Track $track){

        if ( !get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));
        if ( !$this->is_user_api_logged() ) return new WP_Error('lastfm_not_api_logged',__("User is not logged onto Last.fm",'wpsstm'));

        $auth = $this->get_user_api_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;

        $results = null;
        
        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );
        
        wpsstm()->debug_log(json_encode($api_args),"lastfm - now_playing_track()");
        
        try {
            $track_api = new TrackApi($auth);
            $results = $track_api->updateNowPlaying($api_args);
        }catch(Exception $e){
            return $this->handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function scrobble_track(WP_SoundSystem_Track $track, $timestamp){

        if ( !get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));
        if ( !$this->is_user_api_logged() ) return new WP_Error('lastfm_not_api_logged',__("User is not logged onto Last.fm",'wpsstm'));

        $auth = $this->get_user_api_auth();
        if ( !$auth || is_wp_error($auth) ) return $auth;

        $results = null;
        
        //http://www.last.fm/api/show/track.scrobble
        
        $api_args = array(
            'artist'        => $track->artist,
            'track'         => $track->title,
            'timestamp'     => $timestamp, //in seconds
            'album'         => $track->album,
            'chosenByUser'  => 0,
            'duration'      => $track->duration
        );
        
        wpsstm()->debug_log(json_encode($api_args),"lastfm - scrobble_track()");
        
        try {
            $track_api = new TrackApi($auth);
            $results = $track_api->scrobble($api_args);
        }catch(Exception $e){
            return $this->handle_api_exception($e);
        }
        
        return $results;
    }
    
    function ajax_love_unlove_track(){
        $result = array(
            'input'     => $_POST,
            'success'   => false,
            'message'   => null
        );
        $track_args = array(
            'title'     => ( isset($_POST['track']['title']) ) ? $_POST['track']['title'] : null,
            'artist'    => ( isset($_POST['track']['artist']) ) ? $_POST['track']['artist'] : null,
            'album'     => ( isset($_POST['track']['album']) ) ? $_POST['track']['album'] : null
        );
        $track = $result['track'] = new WP_SoundSystem_Track($track_args);
        $do_love = $result['do_love'] = filter_var($_POST['do_love'], FILTER_VALIDATE_BOOLEAN); //ajax do send strings
        $success = $this->love_track($track,$do_love);
        
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
    
    function ajax_update_now_playing_track(){
        $result = array(
            'input'     => $_POST,
            'message'   => null,
            'success'   => false
        );
        
        $track_args = array(
            'title'     => ( isset($_POST['track']['title']) ) ? $_POST['track']['title'] : null,
            'artist'    => ( isset($_POST['track']['artist']) ) ? $_POST['track']['artist'] : null,
            'album'     => ( isset($_POST['track']['album']) ) ? $_POST['track']['album'] : null
        );

        $track = $result['track'] = new WP_SoundSystem_Track($track_args);
        
        $success = wpsstm_lastfm()->now_playing_track($track);

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
    
    function ajax_scrobble_track(){
        $result = array(
            'input'     => $_POST,
            'message'   => null,
            'success'   => false
        );
        
        $track_args = array(
            'title'     => ( isset($_POST['track']['title']) ) ? $_POST['track']['title'] : null,
            'artist'    => ( isset($_POST['track']['artist']) ) ? $_POST['track']['artist'] : null,
            'album'     => ( isset($_POST['track']['album']) ) ? $_POST['track']['album'] : null,
            'duration'  => ( isset($_POST['track']['duration']) ) ? $_POST['track']['duration'] : null
        );

        $track = $result['track'] = new WP_SoundSystem_Track($track_args);
        $start_timestamp = ( isset($_POST['playback_start']) ) ? $_POST['playback_start'] : null;
        
        $success = wpsstm_lastfm()->scrobble_track($track,$start_timestamp);

        if ( $success ){
            if ( is_wp_error() ){
                $code = $success->get_error_code();
                $result['message'] = $success->get_error_message($code); 
            }else{
                $result['success'] = true;
            }
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
}


function wpsstm_lastfm() {
	return WP_SoundSytem_Core_LastFM::instance();
}

wpsstm_lastfm();