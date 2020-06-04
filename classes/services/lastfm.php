<?php

///TO FIX use last.fm API instead of scraping ? Only downside is that we don't get the youbube links with that.
//TO FIX should have a way to deconnect (delete meta & transient) last.fm, since for now if the user revoke the access on his last.fm profile, there is now way to get the auth link again.

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WPSSTM_LastFM{

    static $lastfm_options_meta_name = 'wpsstm_lastfm_options';
    static $lastfm_user_api_metas_name = '_wpsstm_lastfm_api';

    public $lastfm_user = null;

    public $options = array();


    function __construct(){

        $options_default = array(
            'client_id' =>      null,
            'client_secret' =>  null,
            'favorites' =>      true,
        );

        $this->options = wp_parse_args(get_option( self::$lastfm_options_meta_name),$options_default);

        add_filter( 'wpsstm_importer_input',array(__class__,'wizard_no_url_input'));

        add_action( 'init', array($this,'setup_lastfm_user') ); //TO FIX only if player is loaded ?
        add_action( 'wp', array($this,'after_app_auth') );
        add_action( 'wp_head',array($this,'app_auth_notice'),11);

        add_action( 'wp_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles'));
        add_action( 'admin_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles'));

        add_filter('wpsstm_player_context_menu_items', array($this,'append_lastfm_player_context_menu_items'));

        /*backend*/
        add_action( 'admin_init', array( $this, 'lastfm_settings_init' ) );


        /*
        AJAX
        */

        //enable scrobbler
        add_action('wp_ajax_wpsstm_lastfm_toggle_user_scrobbler',array($this,'ajax_lastm_toggle_user_scrobbler') );
        add_action('wp_ajax_nopriv_wpsstm_lastfm_toggle_user_scrobbler', array($this,'ajax_lastm_toggle_user_scrobbler')); //for call to action

        //love & unlove
        add_action('wpsstm_love_track',array($this,'lastfm_track_love') );
        add_action('wpsstm_unlove_track',array($this,'lastfm_track_unlove') );


        //updateNowPlaying
        add_action('wp_ajax_wpsstm_user_update_now_playing_lastfm_track', array($this,'ajax_lastfm_now_playing_track'));

        //scrobble user
        add_action('wp_ajax_wpsstm_lastfm_scrobble_user_track', array($this,'ajax_lastfm_scrobble_track'));

        //scrobble bot
        add_action('wp_ajax_wpsstm_lastfm_scrobble_bot_track', array($this,'ajax_lastfm_scrobble_bot_track'));
        add_action('wp_ajax_nopriv_wpsstm_lastfm_scrobble_bot_track', array($this,'ajax_lastfm_scrobble_bot_track'));

    }

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }

    /*
    register backend settings
    */
    function lastfm_settings_init(){

        register_setting(
            'wpsstm_option_group', // Option group
            self::$lastfm_options_meta_name, // Option name
            array( $this, 'lastfm_settings_sanitize' ) // Sanitize
         );

        add_settings_section(
            'lastm_service', // ID
            'Last.fm', // Title
            array( $this, 'lastfm_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );

        add_settings_field(
            'lastfm_api',
            __('API','wpsstm'),
            array( $this, 'lastfm_auth_callback' ),
            'wpsstm-settings-page', // Page
            'lastm_service'//section
        );

        add_settings_field(
            'lastfm_scrobble_along',
            __('Scrobble Along','wpsstm'),
            array( $this, 'scrobble_along_callback' ),
            'wpsstm-settings-page', // Page
            'lastm_service'//section
        );

        add_settings_field(
            'favorites',
            __('Favorites','wpsstm'),
            array( $this, 'favorites_callback' ),
            'wpsstm-settings-page', // Page
            'lastm_service'//section
        );

    }

    function lastfm_settings_sanitize($input){

        if ( WPSSTM_Settings::is_settings_reset() ) return;

        //Last.fm
        $new_input['client_id'] = ( isset($input['client_id']) ) ? trim($input['client_id']) : null;
        $new_input['client_secret'] = ( isset($input['client_secret']) ) ? trim($input['client_secret']) : null;
        $new_input['favorites'] = isset($input['favorites']);

        return $new_input;
    }
    function lastfm_settings_desc(){
        $new_app_url = 'https://www.last.fm/api/account/create';

        $api_link = sprintf('<a href="%s" target="_blank">%s</a>',$new_app_url,__('here','wpsstm') );
        printf(__('Required for the Last.fm features.  Get an API account %s.','wpsstm'),$api_link );
    }


    function lastfm_auth_callback(){
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');

        //client ID
        $client_el = sprintf(
            '<p><label>%s</label> <input type="text" name="%s[client_id]" value="%s" /></p>',
            __('Api key:','wpsstm'),
            self::$lastfm_options_meta_name,
            $client_id
        );

        //client secret
        $secret_el = sprintf(
            '<p><label>%s</label> <input type="text" name="%s[client_secret]" value="%s" /></p>',
            __('Shared secret:','wpsstm'),
            self::$lastfm_options_meta_name,
            $client_secret
        );
        printf('<div>%s%s</div>',$client_el,$secret_el);
    }

    function favorites_callback(){
        $option = $this->get_options('favorites');

        $el = sprintf(
            '<input type="checkbox" name="%s[favorites]" value="on" %s /> %s',
            self::$lastfm_options_meta_name,
            checked( $option,true, false ),
            __("When a track is favorited/unfavorited, love/unlove it on Last.fm.","wpsstm")
        );

        printf('<p>%s</p>',$el);
    }

    function scrobble_along_callback(){

        $enabled = ( $this->can_scrobble_along() === true );

        /*
        form
        */

        $help = array();
        $help[]= __("Each time a user scrobbles a song to Last.fm, do scrobble it along with the bot user.","wpsstm");
        $help[]= sprintf('<br/><small>%s</small>',__("To enable this option, you have to login with the bot user, activate the scrobbler and follow the instructions.","wpsstm"));

        $el = sprintf(
            '<input type="checkbox" value="on" disabled="disabled" %s /> %s',
            checked( $enabled,true, false ),
            implode('  ',$help)
        );

        printf('<p>%s</p>',$el);

        //display settings errors
        settings_errors('lastfm_scrobble_along');
    }

    function setup_lastfm_user(){
        $this->lastfm_user = new WPSSTM_LastFM_User();
    }

    function enqueue_lastfm_scripts_styles(){

        //CSS
        //wp_enqueue_style( 'wpsstm-lastfm',  wpsstm()->plugin_url . '_inc/css/wpsstm-lastfm.css', null, wpsstm()->version );

        //JS
        wp_enqueue_script( 'wpsstm-lastfm', wpsstm()->plugin_url . '_inc/js/wpsstm-lastfm.js', array('jquery'),wpsstm()->version, true);

        //localize vars
        $localize_vars=array(
            'lastfm_scrobble_along'=>   (int)( $this->can_scrobble_along() === true ),
            'lastfm_scrobble_user' =>   (int)( get_current_user_id() && ( $this->lastfm_user->is_user_connected() === true ) ),
        );

        wp_localize_script('wpsstm-lastfm','wpsstmLastFM', $localize_vars);
    }

    /*
    TOU FIX should be hooked only when player is loaded ?
    */
    public function app_auth_notice(){

        $enabled = $this->lastfm_user->is_user_enabled();
        $connected = ( $this->lastfm_user->is_user_connected() == true);

        if ( $enabled && !$connected ){
            $lastfm_auth_url = self::get_app_auth_url();
            $lastfm_auth_link = sprintf('<a href="%s">%s</a>',$lastfm_auth_url,__('here','wpsstm'));
            $lastfm_auth_text = sprintf(__('You need to authorize this website on Last.fm: click %s.','wpsstm'),$lastfm_auth_link);
            $notice = sprintf('<p id="wpsstm-dialog-lastfm-auth-notice">%s</p>',$lastfm_auth_text);

            echo wpsstm_get_notice( $notice );
        }

    }

    /*
    After user has authorized app on Last.fm; detect callback and set token transient.
    */

    public function after_app_auth(){
        //FOR TESTS delete_user_meta( $this->lastfm_user->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name );
        if ( !isset($_GET['wpsstm_lastfm_after_app_auth']) ) return;

        $token = ( isset($_GET['token']) ) ? $_GET['token'] : null;
        $this->lastfm_user->set_lastfm_user_api_metas($token);

        add_action('wp_head',array($this,'after_app_auth_notice'));

    }

    public function after_app_auth_notice(){
        $username = $this->lastfm_user->get_lastfm_user_metas('username');
        echo wpsstm_get_notice(sprintf(__('Your Last.fm account is now connected, %s.','wpsstm'),$username));
    }

    /*
    Get the URL of the app authentification at last.fm.
    */

    public function get_app_auth_url($redirect_url = null){

        if ( !$redirect_url ) $redirect_url = home_url();

        //add variable so we can intercept the token when returning to our website
        $callback_args = array(
            'wpsstm_lastfm_after_app_auth' => true
        );

        $redirect_url = add_query_arg($callback_args,$redirect_url);

        $args = array(
            'api_key'   => $this->get_options('client_id'),
            'cb'        => $redirect_url
        );

        $args = array_filter($args);

        $url = add_query_arg($args,'http://www.last.fm/api/auth/');
        return $url;
    }

    /*
    Get basic API authentification
    */

    private function get_basic_api_auth(){

        $can_api = $this->can_lastfm_api();
        if ( !$can_api ) return false;

        //TO FIX store temporary ?
        $basic_auth = null;

        $auth_args = array(
            'apiKey' => $this->get_options('client_id'),
        );

        try{
            $basic_auth = new AuthApi('setsession', $auth_args);
        }catch(Exception $e){
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        return $basic_auth;

    }

    public function get_loved_tracks_count($username){

        $transient_name = sprintf('wpsstm_lastfm_%s_loved_count',$username);
        $username = 'grosbouff';
        $limit = 50;

        if ( false === ( $tracks_count = get_transient($transient_name ) ) ) {

            $args = array(
                'method' =>     'user.getlovedtracks',
                'user' =>       $username,
                'api_key' =>    $this->get_options('client_id'),
                'format' =>     'json'
            );

            $api_url = add_query_arg($args,'http://ws.audioscrobbler.com/2.0');

            $response = wp_remote_post( $api_url );
            if ( is_wp_error($response) ) return $response;

            $body = wp_remote_retrieve_body($response);
            $body = json_decode($body,true);
            $tracks_count = null;
            if ( $count_str =  wpsstm_get_array_value(array('lovedtracks','@attr','total'),$body) ){
                $tracks_count = (int)$count_str;
            }

            set_transient( $transient_name, $tracks_count, 1 * DAY_IN_SECONDS );
        }

        return $tracks_count;

    }

    public function search_artists($input){
        $auth = $this->get_basic_api_auth();

        if ( !$auth || is_wp_error($auth) ) return $auth;

        $results = null;

        try {
            $artist_api = new ArtistApi($auth);
            $results = $artist_api->search(array("artist" => $input));
        }catch(Exception $e){
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        return $results;
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
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        return $results;
    }

    public function search_track(WPSSTM_Track $track,$limit=1,$page=null){

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
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        return $results;
    }

    function ajax_lastm_toggle_user_scrobbler(){
        $ajax_data = wp_unslash($_POST);
        $do_enable = wpsstm_get_array_value('do_enable',$ajax_data);
        $do_enable = filter_var($do_enable, FILTER_VALIDATE_BOOLEAN); //cast to bool

        $result = array(
            'input'     => $ajax_data,
            'do_enable' => $do_enable,
            'success'   => false,
            'message'   => null
        );


        if ( !$user_id = get_current_user_id() ){
            $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
            $result['notice'] = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
        }else{

            if ($do_enable){
                update_user_option( $user_id, WPSSTM_LastFM_User::$lastfm_user_scrobbler_enabled_meta_name, true );
                $connected = ( $this->lastfm_user->is_user_connected() === true );
                if (!$connected){
                    $result['success'] = false;
                }else{
                    $result['success'] = true;
                }

            }else{
                $result['success'] = true;
                delete_user_option( $user_id, WPSSTM_LastFM_User::$lastfm_user_scrobbler_enabled_meta_name );
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result );
    }

    function lastfm_track_love($track){
        return $this->lastfm_user->toggle_lastfm_track_love($track,true);
    }
    function lastfm_track_unlove($track){
        return $this->lastfm_user->toggle_lastfm_track_love($track,false);
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
        $result['track'] = $track->to_array();

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

        $start_timestamp = $result['playback_start'] = wpsstm_get_array_value(array('playback_start'),$ajax_data);

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);

        $success = $this->lastfm_user->scrobble_lastfm_track($track,$start_timestamp);
        $result['track'] = $track->to_array();

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

    function ajax_lastfm_scrobble_bot_track(){

        $ajax_data = wp_unslash($_POST);
        $bot_id = wpsstm()->get_options('bot_user_id');

        $result = array(
            'input' =>              $ajax_data,
            'message' =>            null,
            'success' =>            false,
            'bot_user_id' =>  $bot_id
        );

        if ( $bot_id ){

            $start_timestamp = $result['playback_start'] = wpsstm_get_array_value(array('playback_start'),$ajax_data);


            $track = new WPSSTM_Track();
            $track->from_array($ajax_data['track']);
            $result['track'] = $track->to_array();

            $bot = new WPSSTM_LastFM_User($bot_id);
            $success = $bot->scrobble_lastfm_track($track,$start_timestamp);

            if ( $success ){
                if ( is_wp_error($success) ){
                    $code = $success->get_error_code();
                    $result['message'] = $success->get_error_message($code);
                }else{
                    $result['success'] = true;
                }
            }

        }

        header('Content-type: application/json');
        wp_send_json( $result );

    }

    function append_lastfm_player_context_menu_items($items = array()){

        $items['scrobbler'] = sprintf(
          '<a %s><span>%s</span></a>',
          wpsstm_get_html_attr(
            array(
              'href'=>       wp_login_url( get_permalink() ),
              'class'=>     implode(' ',array(
                'wpsstm-action',
                'wpsstm-player-action',
                'wpsstm-player-action-scrobbler'
              )),
              'title'=>     __('Last.fm scrobble', 'wpsstm'),
              'rel'=>       'nofollow',
            )
          ),
          __('Last.fm scrobble', 'wpsstm'),
        );

        return $items;
    }

    public function can_lastfm_api(){

        $api_key = $this->get_options('client_id');
        $api_secret = $this->get_options('client_secret');

        return ($api_key && $api_secret);

    }

    public function can_scrobble_along(){

        $bot_id = wpsstm()->get_options('bot_user_id');
        if (!$bot_id){
            return new WP_Error( 'wpsstm_lastfm_bot_scrobble',__('Scrobble Along requires a bot user.','wpsstm') );
        }

        $bot = new WPSSTM_LastFM_User($bot_id);

        return ( $bot->is_user_connected() === true);
    }

    /*
    When the wizard input is NOT an URL, redirect to Last.fm tracks search
    */
    static function wizard_no_url_input($input){
        if ($input){
            $url_parsed = parse_url($input);
            if ( empty($url_parsed['scheme']) ){
                $input = sprintf( 'https://www.last.fm/search/tracks?q=%s',urlencode($input) );
            }
        }
        return $input;
    }

}
//https://github.com/matt-oakes/PHP-Last.fm-API/
//TO FIX handle when session is no mor evalid (eg. app has been revoked by user)

class WPSSTM_LastFM_User{
    var $user_id = null;
    var $user_api_metas = null;
    var $token = null;
    private $is_user_api_logged = null;
    var $user_auth = null;

    static $lastfm_user_scrobbler_enabled_meta_name = 'wpsstm_scrobbler_enabled';

    function __construct($user_id = null){

        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        $this->user_id = $user_id;
    }

    function lastfm_log($data,$title = null){

        $title = sprintf('[lastfm user: %s] ',$this->user_id) . $title;
        WP_SoundSystem::debug_log($data,$title);

    }

    function is_user_enabled(){
        global $wpsstm_lastfm;

        if (!$this->user_id) return false;

        $can_api = $wpsstm_lastfm->can_lastfm_api();
        if ( !$can_api ) return false;

        $enabled = get_user_option( self::$lastfm_user_scrobbler_enabled_meta_name, $this->user_id );

        return $enabled;
    }

    public function is_user_connected(){
        global $wpsstm_lastfm;

        if ( !$this->user_id ){
            return new WP_Error( 'missing_user_id', __( "Missing user ID.", "wpsstm" ) );
        }

        if( !$this->is_user_enabled() ) return false;

        return $this->is_user_api_logged();

    }

    /*
    Get the user metas stored after a last.fm session has been initialized.
    */

    public function get_lastfm_user_metas($keys=null){
        if (!$this->user_id) return false;

        if ( $this->user_api_metas === null ) {
            $this->user_api_metas = get_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name, true );
        }

        if (!$this->user_api_metas) return;

        if ($keys){
            return wpsstm_get_array_value($keys, $this->user_api_metas);
        }else{
            return $this->user_api_metas;
        }
    }

    /*
    Request user informations (username and session key) from a token
    */

    public function set_lastfm_user_api_metas($token){
        global $wpsstm_lastfm;

        if (!$this->user_id) return false;

        $can_api = $wpsstm_lastfm->can_lastfm_api();
        if ( !$can_api ) return false;

        if ( !$token ) return new WP_Error( 'lastfm_missing_token', __('Missing last.fm user token','wpsstm') );

        $auth_args = array(
            'apiKey' =>     $wpsstm_lastfm->get_options('client_id'),
            'apiSecret' =>  $wpsstm_lastfm->get_options('client_secret'),
            'token' =>      $token
        );

        self::lastfm_log($auth_args,"lastfm - set_lastfm_user_api_metas()");

        try {

            $session = new AuthApi('getsession', $auth_args);

            $usermetas = array(
                'username'      => $session->username,
                'subscriber'    => $session->subscriber,
                'sessionkey'    => $session->sessionKey,

            );

            self::lastfm_log($usermetas,"WPSSTM_LastFM_User::set_lastfm_user_api_metas()");

        }catch(Exception $e){
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        if ( $usermetas && !is_wp_error($usermetas) ){
            return update_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name, $usermetas );
        }

    }

    /*
    Get API authentification for a user
    TO FIX could we cache this ?
    */

    private function get_user_api_auth(){
        global $wpsstm_lastfm;

        $can_api = $wpsstm_lastfm->can_lastfm_api();
        if ( !$can_api ) return false;

        $user_auth = null;
        $api_metas = $this->get_lastfm_user_metas();
        if ( is_wp_error($api_metas) ) return $api_metas;

        if ( $api_metas ) {
            $auth_args = array(
                'apiKey' =>     $wpsstm_lastfm->get_options('client_id'),
                'apiSecret' =>  $wpsstm_lastfm->get_options('client_secret'),
                'sessionKey' => ( isset($api_metas['sessionkey']) ) ? $api_metas['sessionkey'] : null,
                'username' =>   ( isset($api_metas['username']) ) ? $api_metas['username'] : null,
                'subscriber' => ( isset($api_metas['subscriber']) ) ? $api_metas['subscriber'] : null,
            );

            try{
                $user_auth = new AuthApi('setsession', $auth_args);
            }catch(Exception $e){
                return new WP_Error( $e->getCode(), $e->getMessage() );
            }
        }

        if ($user_auth){
            return $user_auth;
        }elseif ($api_metas){
            //TOUFIX is this at the right place ?
            delete_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name );
            self::lastfm_log("deleted lastfm user api metas");
        }
    }

    /*
    Checks if user can authentificate to last.fm
    If not, clean database and return false.
    //TO FIX run only if player is displayed
    */

    private function is_user_api_logged(){

        if ( !$this->user_id ){
            return new WP_Error( 'missing_user_id', __( "Missing user ID.", "wpsstm" ) );
        }

        if ($this->is_user_api_logged === null) {

            $auth = $this->get_user_api_auth();
            if ( is_wp_error($auth) ) return $auth;

            if ($auth){
                $this->user_auth = $auth;
                $this->is_user_api_logged = true;
            }

            $debug = array(
                'logged'    =>          $this->is_user_api_logged,
                'lastfm_username' =>    $this->get_lastfm_user_metas('username')
            );

            //self::lastfm_log($debug,"lastfm - is_user_api_logged()");

        }

        return $this->is_user_api_logged;

    }

    public function toggle_lastfm_track_love(WPSSTM_Track $track,$do_love = null){

        $connected = $this->is_user_connected();
        if ( is_wp_error($connected) || !$connected ) return $connected;

        if ($do_love === null) return;

        $results = null;

        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );

        try {
            $track_api = new TrackApi($this->user_auth);
            if ($do_love){
                $results = $track_api->love($api_args);
            }else{
                $results = $track_api->unlove($api_args);
            }
        }catch(Exception $e){
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_metas('username');
        $debug_args['success'] = $results;
        $debug_args['do_love'] = $do_love;

        self::lastfm_log($debug_args,"lastfm love track");

        return $results;
    }

    public function now_playing_lastfm_track(WPSSTM_Track $track){

        $connected = $this->is_user_connected();
        if ( is_wp_error($connected) || !$connected ) return $connected;

        $results = null;

        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );

        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_metas('username');

        self::lastfm_log($debug_args,"WPSSTM_LastFM_User::now_playing_lastfm_track()'");

        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->updateNowPlaying($api_args);
        }catch(Exception $e){
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        return $results;
    }

    public function scrobble_lastfm_track(WPSSTM_Track $track, $timestamp){

        $results = null;
        $timestamp = filter_var($timestamp, FILTER_VALIDATE_INT);

        $connected = $this->is_user_connected();
        if ( is_wp_error($connected) || !$connected ) return $connected;

        //http://www.last.fm/api/show/track.scrobble

        $api_args = array(
            'artist'        => $track->artist,
            'track'         => $track->title,
            'timestamp'     => $timestamp, //in seconds
            'album'         => $track->album,
            'chosenByUser'  => 0,
        );

        if ($track->duration){ //we NEED a duration to set this argument; or scrobble won't work.
            $api_args['duration'] = round($track->duration / 1000); //seconds
        }

        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_metas('username');

        self::lastfm_log($debug_args,"scrobble last.fm track");

        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->scrobble($api_args);
        }catch(Exception $e){
            return new WP_Error( $e->getCode(), $e->getMessage() );
        }

        return $results;
    }

}

function wpsstm_lastfm_init(){
    global $wpsstm_lastfm;
    $wpsstm_lastfm = new WPSSTM_LastFM();
}

add_action('wpsstm_load_services','wpsstm_lastfm_init');
