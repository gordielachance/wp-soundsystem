<?php

//https://github.com/matt-oakes/PHP-Last.fm-API/
//TO FIX handle when session is no mor evalid (eg. app has been revoked by user)

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WP_SoundSystem_LastFM_User{
    var $user_id = null;
    var $user_token_transient_name = null;
    var $user_api_metas = null;
    var $token = null;
    var $is_user_api_logged = null;
    var $user_auth = null;
    
    function __construct($user_id = null){

        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;
        
        $this->user_id = $user_id;
        $this->user_token_transient_name = sprintf( 'wpsstm_lastfm_usertoken_%s',$user_id ); //name of the transient that stores the token for a user ID
    }
    
    /*
    Get the user token from a transient, or request it.
    */
    
    private function get_user_token(){

        if ( $this->token === null ){
            $this->token = false;
            $token = $token_transient = $token_request = null;
            
            if ( !$token_name = $this->user_token_transient_name ) return;
            
            if ( $token = $token_transient = get_transient( $token_name ) ) {
                $this->token = $token;
            }else{
                $token = $token_request = wpsstm_lastfm()->request_auth_token();
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
    
    public function set_user_token($token){
        if ( !$token_name = $this->user_token_transient_name ) return;
        wpsstm()->debug_log((string)$token,"lastfm - set_user_token()"); 
        if ( set_transient( $token_name, (string)$token, 1 * HOUR_IN_SECONDS ) ){
            return $token;
        }
    }
    
    /*
    Get the user metas stored after a last.fm session has been initialized.
    */
    
    private function get_lastfm_user_api_metas($keys=null){
        if (!$this->user_id) return false;
        
        if ( $this->user_api_metas === null ) {
            $this->user_api_metas = false;

            if ( $api_metas = get_user_meta( $this->user_id, wpsstm_lastfm()->lastfm_user_api_metas_name, true ) ){
                $this->user_api_metas = $api_metas;
            }else{
                //try to populate it
                $remote_api_metas = $this->request_lastfm_user_api_metas();
                if ( is_wp_error($remote_api_metas) ) return $remote_api_metas;
                $this->user_api_metas = $remote_api_metas;
            }
        }
        
        if ($keys){
            return wpsstm_get_array_value($keys, $this->user_api_metas);
        }else{
            return $this->user_api_metas;
        }
    }
    
    /*
    Request user informations (username and session key) from a token and store it as user meta.
    */

    private function request_lastfm_user_api_metas(){
        if (!$this->user_id) return false;

        $token = $this->get_user_token();

        if ( is_wp_error($token) ) return $token;
        if ( !$token ) return new WP_Error( 'lastfm_php_api', __('Last.FM PHP Api Error: You must provilde a valid api token','wpsstm') );

        $auth_args = array(
            'apiKey' =>     wpsstm_lastfm()->api_key,
            'apiSecret' =>  wpsstm_lastfm()->api_secret,
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

            update_user_meta( $this->user_id, wpsstm_lastfm()->lastfm_user_api_metas_name, $user_api_metas );


        }catch(Exception $e){
            return wpsstm_lastfm()->handle_api_exception($e);
        }

        return $user_api_metas;

    }
    
    /*
    Get API authentification for a user
    TO FIX could we cache this ?
    */

    private function get_user_api_auth(){
        
        $api_key = wpsstm_lastfm()->api_key;
        $api_secret = wpsstm_lastfm()->api_secret;
        
        if ( !$api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API key missing", "wpsstm" ) );
        if ( !$api_secret ) return new WP_Error( 'lastfm_no_api_secret', __( "Required Last.FM API secret missing", "wpsstm" ) );

            
        $user_auth = null;

        $api_metas = $this->get_lastfm_user_api_metas();
        if ( is_wp_error($api_metas) ) return $api_metas;

        if ( $api_metas ) {
            $auth_args = array(
                'apiKey' =>     $api_key,
                'apiSecret' =>  $api_secret,
                'sessionKey' => ( isset($api_metas['sessionkey']) ) ? $api_metas['sessionkey'] : null,
                'username' =>   ( isset($api_metas['username']) ) ? $api_metas['username'] : null,
                'subscriber' => ( isset($api_metas['subscriber']) ) ? $api_metas['subscriber'] : null,
            );

            //wpsstm()->debug_log(json_encode($auth_args),"lastfm - get_user_api_auth()"); 

            try{
                $user_auth = new AuthApi('setsession', $auth_args);
            }catch(Exception $e){
                $user_auth = wpsstm_lastfm()->handle_api_exception($e);
            }
        }

        return $user_auth;

    }
    
    /*
    Checks if user can authentificate to last.fm 
    If not, clean database and return false.
    //TO FIX run only if player is displayed
    */

    public function is_user_api_logged(){

        if ($this->is_user_api_logged === null) {

            $is_user_api_logged = false;

            if ( $this->user_id ) {

                $this->user_auth = $this->get_user_api_auth();

                if ( is_wp_error($this->user_auth) ){
                    $code = $this->user_auth->get_error_code();
                    $api_code = $this->user_auth->get_error_data($code);
                    $this->user_auth = null;

                    switch ($api_code){
                        case 4: //'Unauthorized Token - This token has not been issued' - probably expired
                            delete_transient( $this->user_token_transient_name );
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
            
            $debug = array(
                'logged'    =>          $this->is_user_api_logged,
                'lastfm_username' =>    $this->get_lastfm_user_api_metas('username')
            );
            
            //wpsstm()->debug_log(json_encode($debug),"lastfm - is_user_api_logged()");
            
        }
        
        return $this->is_user_api_logged;

    }
    
    private function delete_lastfm_user_api_metas(){
        if (!$this->user_id) return false;
        if ( delete_user_meta( $this->user_id, wpsstm_lastfm()->lastfm_user_api_metas_name ) ){
            delete_transient( $this->user_token_transient_name );
            $this->user_api_metas = false;
        }
    }
    
    public function love_lastfm_track(WP_SoundSystem_Track $track,$do_love = null){

        if ( !$this->is_user_api_logged() ) return false;
        if ($do_love === null) return;

        $results = null;
        
        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );
        
        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');
        
        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"lastfm - lastfm_love_track()");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            if ($do_love){
                $results = $track_api->love($api_args);
            }else{
                $results = $track_api->unlove($api_args);
            }
        }catch(Exception $e){
            return wpsstm_lastfm()->handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function now_playing_lastfm_track(WP_SoundSystem_Track $track){

        if ( !$this->is_user_api_logged() ) return new WP_Error('lastfm_not_api_logged',__("User is not logged onto Last.fm",'wpsstm'));

        $results = null;
        
        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );

        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');
        
        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"lastfm - now_playing_lastfm_track()'");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->updateNowPlaying($api_args);
        }catch(Exception $e){
            return wpsstm_lastfm()->handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function scrobble_lastfm_track(WP_SoundSystem_Track $track, $timestamp){

        if ( !$this->is_user_api_logged() ) return new WP_Error('lastfm_not_api_logged',__("User is not logged onto Last.fm",'wpsstm'));

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
        
        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');

        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"lastfm - scrobble_lastfm_track()");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->scrobble($api_args);
        }catch(Exception $e){
            return wpsstm_lastfm()->handle_api_exception($e);
        }
        
        return $results;
    }
    
}

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
        add_action('wp_ajax_wpsstm_user_love_unlove_lastfm_track',array($this,'ajax_love_unlove_lastfm_track') );
        
        //ajax : updateNowPlaying
        add_action('wp_ajax_wpsstm_user_update_now_playing_lastfm_track', array($this,'ajax_user_update_now_playing_lastfm_track'));
        
        //ajax : scrobble
        add_action('wp_ajax_wpsstm_user_scrobble_lastfm_track', array($this,'ajax_user_scrobble_lastfm_track'));
        
        add_action('wp_ajax_wpsstm_lastfm_scrobble_along_track', array($this,'ajax_lastfm_scrobble_along_track'));
        add_action('wp_ajax_nopriv_wpsstm_lastfm_scrobble_along_track', array($this,'ajax_lastfm_scrobble_along_track'));
        
        //add_action( 'wp',array($this,'test_community_scrobble') );
        
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
    
    function test_community_scrobble(){
        if ( !$community_user_id = wpsstm()->get_options('community_user_id') ) return;
        
        $community_user = new WP_SoundSystem_LastFM_User($community_user_id);
        $track = new WP_SoundSystem_Track('28916');
        $success = $community_user->scrobble_lastfm_track($track,'1501481576');
        print_r($success);die();
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
            'lastfm_scrobble_along'     => ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' ),
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
    
    function ajax_love_unlove_lastfm_track(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'message'   => null
        );
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        $track = $result['track'] = new WP_SoundSystem_Track($post_id);
        $do_love = $result['do_love'] = filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN); //ajax do send strings
        $success = $this->lastfm_user->love_lastfm_track($track,$do_love);
        
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
    
    function ajax_user_update_now_playing_lastfm_track(){
        
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
    
    function ajax_user_scrobble_lastfm_track(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        $track = $result['track'] = new WP_SoundSystem_Track($post_id);
        $start_timestamp = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
        
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
    
    function ajax_lastfm_scrobble_along_track(){
        
        $ajax_data = wp_unslash($_POST);
        $community_user_id = wpsstm()->get_options('community_user_id');
        $scrobble_along = ( wpsstm()->get_options('lastfm_community_scrobble') == 'on' );

        $result = array(
            'input' =>              $ajax_data,
            'message' =>            null,
            'success' =>            false,
            'community_user_id' =>  $community_user_id,
            'scrobble_along' =>     $scrobble_along,
        );
        
        if ( $community_user_id && $scrobble_along ){
            
            $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
            $track = $result['track'] = new WP_SoundSystem_Track($post_id);

            //check that the new submission has not been sent just before
            $last_scrobble_meta_key = 'wpsstm_last_scrobble';
            $track_arr = $track->array_export();
            $last_scrobble = get_user_meta($community_user_id, $last_scrobble_meta_key, true);
            
            if ( $last_scrobble == $track_arr ){
                
                $result['message'] = 'This track has already been scrobbled by the bot: ' . json_encode($track_arr,JSON_UNESCAPED_UNICODE); 
                
            }else{
                
                $start_timestamp = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
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

}


function wpsstm_lastfm() {
	return WP_SoundSystem_Core_LastFM::instance();
}

wpsstm_lastfm();