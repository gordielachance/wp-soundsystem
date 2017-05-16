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

    public $user_api_metas = null;
    public $is_auth = null;
    private $token = null;

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
        
        //profile fields
        /*
        add_action( 'show_user_profile', array($this,'lastfm_username_profile_field') );
        add_action( 'edit_user_profile', array($this,'lastfm_username_profile_field') );

        add_action( 'personal_options_update', array($this,'lastfm_username_save_profile_field') );
        add_action( 'edit_user_profile_update', array($this,'lastfm_username_save_profile_field') );
        */

        add_filter( 'query_vars', array($this,'add_query_var_token'));
        add_action( 'wp', array($this,'after_app_auth_set_user_token') );
        add_action( 'wp', array($this,'tests') );

    }
    
    function lastfm_username_profile_field( $user ) {
        ?>
            <h3><?php _e('Last.FM account', 'wpsstm'); ?></h3>

            <table class="form-table">
                <tr>
                    <th>
                        <label for="address"><?php _e('Username'); ?>
                    </label></th>
                    <td>
                        <input type="text" name="address" id="address" value="<?php echo esc_attr( get_the_author_meta( 'address', $user->ID ) ); ?>" class="regular-text" /><br />
                        <span class="description"><?php _e('Please enter your address.', 'your_textdomain'); ?></span>
                    </td>
                </tr>
            </table>
        <?php 
    }
    
    function lastfm_username_save_profile_field( $user_id ) {

        if ( !current_user_can( 'edit_user', $user_id ) )
            return FALSE;

        update_user_meta( $user_id, 'address', $_POST['address'] );
    }
    
    function tests(){

        $track = new WP_SoundSystem_Track(array('artist'=>'u2','title'=>'Sunday Bloody Sunday'));
        $loved = $this->love_track($track);
        echo"LOVED";
        var_dump($loved);
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
        
        $session = $this->populate_api_metas();
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
        
        if ($this->is_auth === null) {
            
            $is_auth = false;
        
            $auth = $this->get_auth(true);

            if ( $auth && is_wp_error($auth) ){
                $code = $auth->get_error_code();
                $api_code = $auth->get_error_data($code);

                switch ($api_code){
                    case 4: //'Unauthorized Token - This token has not been issued' - probably expired
                        $token_name = $this->get_transient_token_name();
                        delete_transient( $token_name );
                        $is_auth = $this->check_is_user_auth(); //redo
                    break;
                    case 14: //'Unauthorized Token - This token has not been authorized'

                        $this->delete_lastfm_user_api_metas(); //TO FIX at the right place ?

                        $redirect_url = home_url();

                        $args = array(
                            $this->qvar_after_app_auth => true
                        );

                        $redirect_url = add_query_arg($args,$redirect_url);

                        $redirect_url = $this->get_user_auth_url($redirect_url);
                        //wp_redirect($redirect_url);
                        print_r($redirect_url);
                        die();

                    break;
                }

            }
            $this->is_auth = true;
        }
        
        return $this->is_auth;

    }

    function get_transient_token_name(){
        if ( !$user_id = get_current_user_id() ) return false;
        return sprintf( 'wpsstm_lastfm_usertoken_%s',$user_id );
    }
    
    public function get_user_token(){

        if ( $this->token === null ){
            $this->token = false;
            $token_name = $this->get_transient_token_name();
            

            if ( !$token_name = $this->get_transient_token_name() ) return;
            
            if ( $token = get_transient( $token_name ) ) {
                $this->token = $token;
            }else{

                $token = $this->request_token();
                if ( is_wp_error($token) ) return $token;
                $this->token = (string)$this->set_user_token($token);

            }
        }
        
        wpsstm()->debug_log($this->token,"lastfm - get_user_token()");
        return $this->token;
    }
    
    private function set_user_token($token){
        if ( !$token_name = $this->get_transient_token_name() ) return;
        wpsstm()->debug_log((string)$token,"lastfm - set_user_token()"); 
        if ( set_transient( $token_name, (string)$token, 1 * HOUR_IN_SECONDS ) ){
            return $token;
        }
    }

    public function request_token(){

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

        return $authentication->token;
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

        $auth = null;
        $auth_args = array(
            'apiKey' => $this->api_key
        );

        if ($logged){

            if ( !$this->api_secret ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.FM API secret missing", "wpsstm" ) );
            
            $api_metas = $this->get_lastfm_user_api_metas();
            if ( is_wp_error($api_metas) ) return $api_metas;

            $advanced_auth_args = array(
                'apiSecret' =>  $this->api_secret,
                'sessionKey' => ( isset($api_metas['sessionkey']) ) ? $api_metas['sessionkey'] : null,
                'username' =>   ( isset($api_metas['username']) ) ? $api_metas['username'] : null,
                'subscriber' => ( isset($api_metas['subscriber']) ) ? $api_metas['subscriber'] : null,
            );

            $auth_args = array_merge($auth_args,$advanced_auth_args);

        }

        wpsstm()->debug_log(json_encode($auth_args),"lastfm - get_auth()"); 

        try{
            return new AuthApi('setsession', $auth_args);
        }catch(Exception $e){
            return $this->handle_exception($e);
        }

    }
    
    public function get_lastfm_user_api_metas(){
        $user_id = get_current_user_id();
        if (!$user_id) return false;
        
        if ( $this->user_api_metas === null ) {
            $this->user_api_metas = false;

            if ( $api_metas = get_user_meta( $user_id, $this->lastfm_user_api_metas_name, true ) ){
                $this->user_api_metas = $api_metas;
            }else{
                //try to populate it
                $remote_api_metas = $this->populate_api_metas();
                if ( is_wp_error($remote_api_metas) ) return $remote_api_metas;
                 $this->user_api_metas = $remote_api_metas;
            }
        }

        return $this->user_api_metas;
    }

    private function populate_api_metas(){
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

        wpsstm()->debug_log($auth_args,"lastfm - populate_api_metas()"); 

        try {

            $session = new AuthApi('getsession', $auth_args);

            $user_api_metas = array(
                'username'      => $session->username,
                'subscriber'    => $session->subscriber,
                'sessionkey'    => $session->sessionKey,

            );

            wpsstm()->debug_log(json_encode($user_api_metas),"lastfm - populate_api_metas()");

            update_user_meta( $user_id, $this->lastfm_user_api_metas_name, $user_api_metas );


        }catch(Exception $e){
            return $this->handle_exception($e);
        }

        return $user_api_metas;

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

        $this->check_is_user_auth();

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
    
    function delete_lastfm_user_api_metas(){
        $user_id = get_current_user_id();
        if (!$user_id) return false;
        if ( delete_user_meta( $user_id, $this->lastfm_user_api_metas_name ) ){
            $this->user_api_metas = false;
        }
    }
    
}


function wpsstm_lastfm() {
	return WP_SoundSytem_Core_LastFM::instance();
}

wpsstm_lastfm();