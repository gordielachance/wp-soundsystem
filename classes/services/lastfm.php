<?php

use LastFmApi\Api\AuthApi;
use LastFmApi\Api\ArtistApi;
use LastFmApi\Api\TrackApi;

class WPSSTM_LastFM{
    
    static $lastfm_user_api_metas_name = '_wpsstm_lastfm_api';
    static $qvar_after_app_auth = 'wpsstm_lastfm_after_app_auth';
    
    public $lastfm_user = null;

    function __construct(){
        add_filter( 'wpsstm_wizard_input',array(__class__,'wizard_no_url_input'));
        add_action('wpsstm_before_remote_response',array(__class__,'register_lastfm_preset'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_lastfm_service_links'));
        
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

    
    //register presets
    static function register_lastfm_preset($remote){
        new WPSSTM_LastFM_URL_Preset($remote);
        new WPSSTM_LastFM_User_Station_Preset($remote);
        new WPSSTM_LastFM_Artist_Station_Preset($remote);
    }

    static function register_lastfm_service_links($links){
        $links[] = array(
            'slug'      => 'lastfm',
            'name'      => 'Last.fm',
            'url'       => 'https://www.last.fm/',
            'pages'     => array(
                array(
                    'slug'          => 'stations',
                    'name'          => __('stations','wpsstm'),
                    'example'       => 'lastfm:user:USERNAME:station:STATION_TYPE',
                )
            )
        );

        return $links;
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

class WPSSTM_LastFM_URL_Preset{

    function __construct($remote){

        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        add_filter( 'wpsstm_live_tracklist_track_artist',array($this,'artist_header_track_artist'), 10, 3 );

    }
    
    function can_handle_url($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm~i';
        preg_match($pattern, $url, $matches);
        return ( !empty($matches) );
    }

    function get_user_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?(?:user/([^/]+))~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
                   
    function get_artist_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?user/[^/]+/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : 'library';
    }
    
    function get_artist_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : '+tracks';
    }
    
    function get_album_name($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/(?!\+)([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function is_station($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?player/station~i';
        preg_match($pattern,$url, $matches);
        if ( !empty($matches) ) return true;
    }
                   
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;

        $remote->options['selectors'] = array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        );
        
    }
    
    //on artists and album pages; artist is displayed in a header on the top of the page
    function artist_header_track_artist($artist,$track_node,$remote){
        
        if ( $this->get_artist_slug($remote->feed_url_no_filters) && !$this->is_station($remote->feed_url_no_filters) ){

            if ( $album_slug = $this->get_album_name($remote->feed_url_no_filters) ){
                $selector = array('path'=>'[itemtype="http://schema.org/MusicGroup"] [itemprop="name"]');
            }else{
                $selector = array('path'=>'[data-page-resource-type="artist"]','regex'=>null,'attr'=>'data-page-resource-name');
            }

            $artist = $remote->parse_node($remote->body_node,$selector);
            
        }
 
        return $artist;
    }


}

abstract class WPSSTM_LastFM_Station_Preset extends WPSSTM_LastFM_URL_Preset{

    function __construct($remote){
        parent::__construct($remote);
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;

        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'>playlist'),
            'track_artist'      => array('path'=>'artists > name'),
            'track_title'       => array('path'=>'playlist > name'),
            'track_source_urls' => array('path'=>'playlinks url'),
        );
            
    }
    

}

class WPSSTM_LastFM_User_Station_Preset extends WPSSTM_LastFM_Station_Preset{
    private $user_slug;
    private $page_slug;

    function __construct($remote){
        parent::__construct($remote);

        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter('wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );

    }
    
    function can_handle_url($url){
        
        $user_slug = $this->get_user_slug($url);
        $page_slug = $this->get_station_page($url);
        
        if ( !$user_slug ) return;
        if ( !$page_slug ) return;
        return true;
    }

    function get_user_slug($url){
        $pattern = '~^lastfm:user:([^:]+):station~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_station_page($url){
        $pattern = '~^lastfm:user:[^:]+:station:([^:]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_remote_url($url){
        
        if ( $this->can_handle_url($url) ){
            $user_slug = $this->get_user_slug($url);
            $page_slug = $this->get_station_page($url);
            $url = sprintf('https://www.last.fm/player/station/user/%s/%s?ajax=1',$user_slug,$page_slug );
        }
        return $url;
    }
    
    function get_remote_title($title,$remote){
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
            $user_slug = $this->get_user_slug($remote->feed_url_no_filters);
            $page_slug = $this->get_station_page($remote->feed_url_no_filters);
            $title = sprintf( __('Last.fm station for %s - %s','wpsstm'),$user_slug,$page_slug );
        }
        return $title;
    }
}

class WPSSTM_LastFM_Artist_Station_Preset extends WPSSTM_LastFM_Station_Preset{
    private $artist_slug;
    private $page_slug;
    
    function __construct($remote){
        parent::__construct($remote);
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );
    }
    
    function can_handle_url($url){
        $artist_slug = $this->get_artist_slug($url);
        $page_slug = $this->get_artist_page($url);
        if ( !$artist_slug ) return;
        if ( !$page_slug == '+similar' ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $artist_slug = $this->get_artist_slug($url);
            $url = sprintf('https://www.last.fm/player/station/music/%s?ajax=1',$artist_slug);
        }
        return $url;
    }

    function get_remote_title($title,$remote){
        if ( $this->can_handle_url($remote->feed_url_no_filters) ){
            $artist_slug = $this->get_artist_slug($remote->feed_url_no_filters);
            $title = sprintf( __('Last.fm stations (similar artist): %s','wpsstm'),$artist_slug );
        }
        return $title;
    }

}

//https://github.com/matt-oakes/PHP-Last.fm-API/
//TO FIX handle when session is no mor evalid (eg. app has been revoked by user)

class WPSSTM_LastFM_User{
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
                $token = $token_request = WPSSTM_LastFM::request_auth_token();
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

            if ( $api_metas = get_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name, true ) ){
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
        
        $api_key = wpsstm()->get_options('lastfm_client_id');
        $api_secret = wpsstm()->get_options('lastfm_client_secret');
        
        if (!$api_key || !$api_secret){
            return new WP_Error( 'lastfm_php_api', __('WPSSTM_LastFM_User: Missing Last.fm credentials','wpsstm') );
        }

        $token = $this->get_user_token();

        if ( is_wp_error($token) ) return $token;
        if ( !$token ) return new WP_Error( 'lastfm_php_api', __('Last.fm PHP Api Error: You must provilde a valid api token','wpsstm') );

        $auth_args = array(
            'apiKey' =>     $api_key,
            'apiSecret' =>  $api_secret,
            'token' =>      $token
        );

        //wpsstm()->debug_log($auth_args,"lastfm - request_lastfm_user_api_metas()"); 

        try {

            $session = new AuthApi('getsession', $auth_args);

            $user_api_metas = array(
                'username'      => $session->username,
                'subscriber'    => $session->subscriber,
                'sessionkey'    => $session->sessionKey,

            );

            wpsstm()->debug_log(json_encode($user_api_metas),"WPSSTM_LastFM_User::request_lastfm_user_api_metas()");

            update_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name, $user_api_metas );


        }catch(Exception $e){
            return WPSSTM_LastFM::handle_api_exception($e);
        }

        return $user_api_metas;

    }
    
    /*
    Get API authentification for a user
    TO FIX could we cache this ?
    */

    private function get_user_api_auth(){
        
        $api_key = wpsstm()->get_options('lastfm_client_id');
        $api_secret = wpsstm()->get_options('lastfm_client_secret');
        
        if ( !$api_key ) return new WP_Error( 'lastfm_no_api_key', __( "Required Last.fm API key missing", "wpsstm" ) );
        if ( !$api_secret ) return new WP_Error( 'lastfm_no_api_secret', __( "Required Last.fm API secret missing", "wpsstm" ) );

            
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
                $user_auth = WPSSTM_LastFM::handle_api_exception($e);
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
        if ( delete_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name ) ){
            delete_transient( $this->user_token_transient_name );
            $this->user_api_metas = false;
        }
    }
    
    public function love_lastfm_track(WPSSTM_Track $track,$do_love = null){

        if ( !$this->is_user_api_logged() ) return false;
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
            return WPSSTM_LastFM::handle_api_exception($e);
        }
        
        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');
        $debug_args['success'] = $results;
        
        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"WPSSTM_LastFM_User::lastfm_love_track()");
        
        return $results;
    }
    
    public function now_playing_lastfm_track(WPSSTM_Track $track){

        if ( !$this->is_user_api_logged() ) return new WP_Error('lastfm_not_api_logged',__("User is not logged onto Last.fm",'wpsstm'));

        $results = null;
        
        $api_args = array(
            'artist' => $track->artist,
            'track' =>  $track->title
        );

        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');
        
        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"WPSSTM_LastFM_User::now_playing_lastfm_track()'");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->updateNowPlaying($api_args);
        }catch(Exception $e){
            return WPSSTM_LastFM::handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function scrobble_lastfm_track(WPSSTM_Track $track, $timestamp){

        if ( !$this->is_user_api_logged() ) return new WP_Error('lastfm_not_api_logged',__("User is not logged onto Last.fm",'wpsstm'));

        $results = null;
        
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
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');

        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"WPSSTM_LastFM_User::scrobble_lastfm_track()");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->scrobble($api_args);
        }catch(Exception $e){
            return WPSSTM_LastFM::handle_api_exception($e);
        }
        
        return $results;
    }
    
}

function wpsstm_lastfm_init(){
    new WPSSTM_LastFM();
}

add_action('wpsstm_init','wpsstm_lastfm_init');