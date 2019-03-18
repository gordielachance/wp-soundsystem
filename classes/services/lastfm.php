<?php

///TO FIX use last.fm API instead of scraping ? Only downside is that we don't get the youbube sources with that.
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
        
        ///
        add_filter('wpsstm_feed_url', array($this, 'lastfm_artist_bang_to_url'));
        add_filter('wpsstm_feed_url', array($this, 'lastfm_station_artist_bang_to_url'));
        add_filter('wpsstm_feed_url', array($this, 'lastfm_station_user_bang_to_url'));
        add_filter('wpsstm_remote_presets',array($this,'register_lastfm_presets'));
        

        add_filter( 'wpsstm_wizard_input',array(__class__,'wizard_no_url_input'));
        add_filter('wpsstm_wizard_service_links',array($this,'register_lastfm_service_links'), 5);
        add_filter('wpsstm_wizard_bang_links',array($this,'register_lastfm_bang_links'));
        
        add_action( 'init', array($this,'setup_lastfm_user') ); //TO FIX only if player is loaded ?
        add_action( 'wp', array($this,'after_app_auth') );
        add_action('wp_head',array($this,'app_auth_notice'),11);
        add_action( 'wp_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles'));
        
        add_filter('wpsstm_get_player_actions', array($this,'get_lastfm_actions'));
        
        /*backend*/
        add_action( 'admin_init', array( $this, 'lastfm_settings_init' ) );
        add_action( 'admin_enqueue_scripts', array($this,'enqueue_lastfm_scripts_styles'));
        
        /*
        AJAX
        */
        
        //enable scrobbler
        add_action('wp_ajax_wpsstm_lastfm_toggle_user_scrobbler',array($this,'ajax_lastm_toggle_user_scrobbler') );
        add_action('wp_ajax_nopriv_wpsstm_lastfm_toggle_user_scrobbler', array($this,'ajax_lastm_toggle_user_scrobbler')); //for call to action

        //love & unlove
        add_action('wpsstm_love_track',array($this,'user_love_track'), 10, 2 );
        
        
        //updateNowPlaying
        add_action('wp_ajax_wpsstm_user_update_now_playing_lastfm_track', array($this,'ajax_lastfm_now_playing_track'));
        
        //scrobble user
        add_action('wp_ajax_wpsstm_lastfm_scrobble_user_track', array($this,'ajax_lastfm_scrobble_track'));
        
        //scrobble community
        add_action('wp_ajax_wpsstm_lastfm_scrobble_community_track', array($this,'ajax_lastfm_scrobble_community_track'));
        add_action('wp_ajax_nopriv_wpsstm_lastfm_scrobble_community_track', array($this,'ajax_lastfm_scrobble_community_track'));

    }
    
    function lastfm_artist_bang_to_url($url){
        $pattern = '~^artist:([\w\d]+)(?::([\w\d]+))?~i';
        preg_match($pattern, $url, $matches);
        $artist = isset($matches[1]) ? $matches[1] : null;
        $subpage = isset($matches[2]) ? $matches[2] : 'tracks';

        if ( $artist ){
            $url = sprintf('https://www.last.fm/music/%s',urlencode($artist));
            if ($subpage){
                switch($subpage){
                    
                    case 'similar':
                        $url = sprintf('https://www.last.fm/player/station/music/%s',$artist);
                    break;
                        
                    default:
                        $url = trailingslashit($url) . '+' . $subpage;
                    break;
                }
                
            }
        }
        return $url;
    }
    function lastfm_station_artist_bang_to_url($url){
        $pattern = '~^lastfm:station:([\w\d]+):([\w\d]+)(?::([\w\d]+))?~i';
        preg_match($pattern, $url, $matches);
        $page = isset($matches[1]) ? $matches[1] : null;
        $artist = isset($matches[2]) ? $matches[2] : null;

        if ( ( $page == 'music' )  && $artist ){
            $url = sprintf('https://www.last.fm/player/station/music/%s',urlencode($artist));
        }
        return $url;
    }
    function lastfm_station_user_bang_to_url($url){
        $pattern = '~^lastfm:station:([\w\d]+):([\w\d]+)(?::([\w\d]+))?~i';
        preg_match($pattern, $url, $matches);
        $page = isset($matches[1]) ? $matches[1] : null;
        $user = isset($matches[2]) ? $matches[2] : null;
        $subpage = isset($matches[3]) ? $matches[3] : 'library';

        if ( ( $page == 'user' )  && $user ){
            $url = sprintf('https://www.last.fm/player/station/user/%s',$user);
            if ($subpage){
                $url = trailingslashit($url) . $subpage;
            }
        }
        return $url;
    }

    function register_lastfm_presets($presets){

        $presets[] = new WPSSTM_LastFM_Music_URL_Preset();
        $presets[] = new WPSSTM_LastFM_User_URL_Preset();
        $presets[] = new WPSSTM_LastFM_User_Station_Preset();
        $presets[] = new WPSSTM_LastFM_Music_Station_Preset();

        return $presets;
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
        printf(__('Required for the Last.fm preset and Last.fm features.  Get an API account %s.','wpsstm'),$api_link );
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
        $help[]= __("Each time a user scrobbles a song to Last.fm, do scrobble it along with the community user.","wpsstm");
        $help[]= sprintf('<small>%s</small>',__("To enable this option, you have to login with the community user, activate the scrobbler and follow the instructions.","wpsstm"));
        
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
        wp_enqueue_script( 'wpsstm-lastfm', wpsstm()->plugin_url . '_inc/js/wpsstm-lastfm.js', array('jquery'),wpsstm()->version);
        
        $scrobble_along = ( $this->can_scrobble_along() === true );
        
        //localize vars
        $localize_vars=array(
            'lastfm_scrobble_along'     => $scrobble_along,
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

            wpsstm_notice( $notice );
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
        wpsstm_notice(sprintf(__('Your Last.fm account is now connected, %s.','wpsstm'),$username));
    }
                      
    /*
    Get the URL of the app authentification at last.fm.
    */
    
    private function get_app_auth_url($redirect_url = null){

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
            $basic_auth = self::handle_api_exception($e);
        }

        return $basic_auth;

    }

    public static function handle_api_exception($e){
        $message = sprintf(__('Last.fm PHP Api Error [%s]: %s','wpsstm'),$e->getCode(),$e->getMessage());
        wpsstm()->debug_log($message);
        return new WP_Error( 'lastfm_php_api', new WP_Error( 'lastfm_php_api',$message,$e->getCode() ) );
    }
    
    public function search_artists($input){
        $auth = $this->get_basic_api_auth();

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

    public function get_artist_bio($artist){
        
        $auth = $this->get_basic_api_auth();
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
            return self::handle_api_exception($e);
        }
        
        return $results;
    }

    function ajax_lastm_toggle_user_scrobbler(){
        $ajax_data = wp_unslash($_POST);
        $do_enable = wpsstm_get_array_value('do_enable',$ajax_data);
        $do_enable = filter_var($do_enable, FILTER_VALIDATE_BOOLEAN); //cast ajax string to bool
        
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
            
            $result['user_id'] = $user_id;
            
            if ($do_enable){
                delete_user_option( $user_id, WPSSTM_LastFM_User::$lastfm_user_scrobbler_disabled_meta_name );
                $connected = ( $this->lastfm_user->is_user_connected() === true );
                if (!$connected){
                    $result['success'] = false;
                }else{
                    $result['success'] = true;
                }
               
            }else{
                $result['success'] = true;
                delete_user_option( $user_id, WPSSTM_LastFM_User::$lastfm_user_scrobbler_disabled_meta_name );
            }
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function user_love_track($track,$do_love){
        return $this->lastfm_user->love_lastfm_track($track,$do_love);
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
        
        $start_timestamp = $result['playback_start'] = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
        
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

    function ajax_lastfm_scrobble_community_track(){
        
        $ajax_data = wp_unslash($_POST);
        $community_user_id = wpsstm()->get_options('community_user_id');

        $result = array(
            'input' =>              $ajax_data,
            'message' =>            null,
            'success' =>            false,
            'community_user_id' =>  $community_user_id
        );
        
        if ( $community_user_id ){

            $start_timestamp = $result['playback_start'] = ( isset($ajax_data['playback_start']) ) ? $ajax_data['playback_start'] : null;
            
            $track = new WPSSTM_Track();
            $track->from_array($ajax_data['track']);
            $result['track'] = $track->to_array();

            //check that the new submission has not been sent just before
            $last_scrobble_meta_key = 'wpsstm_last_scrobble';
            $track_arr = $track->to_array();
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

        if ( get_current_user_id() ){
            
            $connected = ( $this->lastfm_user->is_user_connected() === true );
            
            $actions['scrobbler'] = array(
                'text' =>       __('Last.fm scrobble', 'wpsstm'),
                'href' =>       '#',
                'classes' =>    array(
                    ( $connected ) ? 'active' : null
                ),
            );
        }else{
            $actions['scrobbler'] = array(
                'text' =>       __('Last.fm scrobble', 'wpsstm'),
                'href' =>       '#',
                'desc' =>       __('This action requires you to be logged.','wpsstm'),
                'classes' =>    array('wpsstm-tooltip','wpsstm-requires-login'),
            );
        }

        return $actions;
    }
    
    public function can_lastfm_api(){
        
        $api_key = $this->get_options('client_id');
        $api_secret = $this->get_options('client_secret');
        
        return ($api_key && $api_secret);
        
    }
    
    public function can_scrobble_along(){

        $community_user_id = wpsstm()->get_options('community_user_id');
        if (!$community_user_id){
            return new WP_Error( 'wpsstm_lastfm_community_scrobble',__('A community user is required.','wpsstm') );   
        }

        $community_user = new WPSSTM_LastFM_User($community_user_id);
        return ( $community_user->is_user_connected() === true);
    }

    static function register_lastfm_service_links($links){
        $item = sprintf('<a href="https://www.last.fm" target="_blank" title="%s"><img src="%s" /></a>','Last.fm',wpsstm()->plugin_url . '_inc/img/lastfm-icon.png');
        $links[] = $item;
        return $links;
    }
    
    function register_lastfm_bang_links($links){
        $bang_artist = '<label for="wpsstm-lastfm-artist-bang"><code>artist:NAME</code></label>';
        $desc = sprintf(__('Will fetch the top tracks by %s, while %s will load a station based on that artist.','wpsstm'),'<code>NAME</code>','<code>artist:NAME:similar</code>');
        $desc.= '  '.__('Powered by Last.fm.','wpsstm');
        $bang_artist .= sprintf('<div id="wpsstm-lastfm-artist-bang" class="wpsstm-bang-desc">%s</div>',$desc);
        $links[] = $bang_artist;

        $bang_user_station = '<label for="wpsstm-lastfm-user-station-bang"><code>lastfm:station</code></label>';
        $desc = __('Will load a Last.fm station based on an artist or a user.','wpsstm');
        $examples = array(
            '<li><code>lastfm:station:music:ARTIST:similar</code></li>',
            '<li><code>lastfm:station:user:USERNAME:library</code></li>',
            '<li><code>lastfm:station:user:USERNAME:recommended</code></li>',
            '<li><code>lastfm:station:user:USERNAME:mix</code></li>'
        );'<ul></ul>';
        $desc.= sprintf('<ul>%s</ul>',implode("\n",$examples));
        $bang_user_station .= sprintf('<div id="wpsstm-lastfm-user-station-bang" class="wpsstm-bang-desc">%s</div>',$desc);
        $links[] = $bang_user_station;
        
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
//https://github.com/matt-oakes/PHP-Last.fm-API/
//TO FIX handle when session is no mor evalid (eg. app has been revoked by user)

class WPSSTM_LastFM_User{
    var $user_id = null;
    var $user_api_metas = null;
    var $token = null;
    private $is_user_api_logged = null;
    var $user_auth = null;
    
    static $lastfm_user_scrobbler_disabled_meta_name = 'wpsstm_scrobbler_disabled';
    
    function __construct($user_id = null){

        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;
        
        $this->user_id = $user_id;
    }
    
    function debug_log($data,$title = null){

        $title = sprintf('[lastfm user: %s] ',$this->user_id) . $title;
        wpsstm()->debug_log($data,$title);

    }
    
    function is_user_enabled(){
        global $wpsstm_lastfm;
        
        if (!$this->user_id) return false;
        
        $can_api = $wpsstm_lastfm->can_lastfm_api();
        if ( !$can_api ) return false;
        
        $service_disabled = get_user_option( self::$lastfm_user_scrobbler_disabled_meta_name, $this->user_id );

        return !$service_disabled;
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

        $this->debug_log($auth_args,"lastfm - set_lastfm_user_api_metas()"); 

        try {

            $session = new AuthApi('getsession', $auth_args);

            $usermetas = array(
                'username'      => $session->username,
                'subscriber'    => $session->subscriber,
                'sessionkey'    => $session->sessionKey,

            );

            $this->debug_log($usermetas,"WPSSTM_LastFM_User::set_lastfm_user_api_metas()");

        }catch(Exception $e){
            return WPSSTM_LastFM::handle_api_exception($e);
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
                WPSSTM_LastFM::handle_api_exception($e);
            }
        }

        if (!$user_auth){
            //TOUFIX is this at the right place ?
            delete_user_meta( $this->user_id, WPSSTM_LastFM::$lastfm_user_api_metas_name );
            $this->debug_log("***deleted lastfm user api metas");
        }else{
            return $user_auth;
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
            
            //$this->debug_log($debug,"lastfm - is_user_api_logged()");
            
        }

        return $this->is_user_api_logged;

    }

    public function love_lastfm_track(WPSSTM_Track $track,$do_love = null){
        
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
            return WPSSTM_LastFM::handle_api_exception($e);
        }
        
        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_metas('username');
        $debug_args['success'] = $results;
        $debug_args['do_love'] = $do_love;
        
        $this->debug_log($debug_args,"lastfm love track");
        
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
        
        $this->debug_log($debug_args,"WPSSTM_LastFM_User::now_playing_lastfm_track()'");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->updateNowPlaying($api_args);
        }catch(Exception $e){
            return WPSSTM_LastFM::handle_api_exception($e);
        }
        
        return $results;
    }
    
    public function scrobble_lastfm_track(WPSSTM_Track $track, $timestamp){
        
        $connected = $this->is_user_connected();
        if ( is_wp_error($connected) || !$connected ) return $connected;

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
        $debug_args['lastfm_username'] = $this->get_lastfm_user_metas('username');

        $this->debug_log($debug_args,"scrobble last.fm track");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->scrobble($api_args);
        }catch(Exception $e){
            return WPSSTM_LastFM::handle_api_exception($e);
        }
        
        return $results;
    }
    
}

abstract class WPSSTM_LastFM_URL_Preset extends WPSSTM_Remote_Tracklist{
    
    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'           => array('path'=>'table.chartlist tbody tr'),
                'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
                'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
                'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
                'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
            )
        );
        
        parent::__construct($url,$options);

    }
    
}

class WPSSTM_LastFM_Music_URL_Preset extends WPSSTM_LastFM_URL_Preset{
    
    var $artist_slug;
    var $artist_page;
    var $album_slug;

    function init_url($url){
        $this->artist_slug = self::get_artist_slug($url);
        
        if ($this->artist_slug){
            
            $this->artist_page = self::get_artist_page($url);
            $this->album_slug = self::get_album_slug($url);
            
            //update track artist selector
            if($this->album_slug){
                $this->preset_options['selectors']['track_artist'] = array('path'=>'/ [itemtype="http://schema.org/MusicGroup"] [itemprop="name"]');
            }else{
                $this->preset_options['selectors']['track_artist'] = array('path'=>'/ [data-page-resource-type="artist"]','attr'=>'data-page-resource-name');
            }
            $this->options['selectors']['track_artist'] = $this->preset_options['selectors']['track_artist'];
        }

        return $this->artist_slug;
    }

    function get_remote_request_url(){
        $url = parent::get_remote_request_url();
        if ( $this->artist_slug && !$this->artist_page ) { //force artist top tracks
            $url = sprintf('https://www.last.fm/music/%s/+tracks',$this->artist_slug);
        }
        return $url;
    }
             
    static function get_artist_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    static function get_artist_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    static function get_album_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/(?:.*/)?music/[^/]+/(?!\+)([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

class WPSSTM_LastFM_User_URL_Preset extends WPSSTM_LastFM_URL_Preset{

    var $user_slug;
    var $user_page;

    function init_url($url){
        $this->user_slug = self::get_user_slug($url);
        $this->user_page = self::get_user_page($url);
        return (bool)$this->user_slug;
    }

    static function get_user_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/user/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    static function get_user_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/user/[^/]+/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }


}

abstract class WPSSTM_LastFM_Station_Preset extends WPSSTM_Remote_Tracklist{
  
    var $station_type;
    
    function __construct($url = null,$options = null) {

        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'>playlist'),
                'track_artist'      => array('path'=>'artists > name'),
                'track_title'       => array('path'=>'playlist > name'),
                'track_source_urls' => array('path'=>'playlinks url'),
            )
        );
        
        parent::__construct($url,$options);
    }
    
    static function get_station_type($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/player/station/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

class WPSSTM_LastFM_User_Station_Preset extends WPSSTM_LastFM_Station_Preset{

    var $user_slug;
    var $user_page;

    function init_url($url){
        
        $this->station_type = self::get_station_type($url);
        $this->user_slug = self::get_user_slug($url);
        $this->user_page = self::get_user_page($url);
        
        return ( ($this->station_type == 'user') && $this->user_slug );

    }

    static function get_user_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/player/station/user/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : 'library';
    }
    
    static function get_user_page($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/player/station/user/[^/]+/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : 'library';
    }

    function get_remote_title(){
        return sprintf( __('Last.fm station for %s - %s','wpsstm'),$this->user_slug,$this->user_page );
    }
}

class WPSSTM_LastFM_Music_Station_Preset extends WPSSTM_LastFM_Station_Preset{

    var $artist_slug;

    function init_url($url){
        
        $this->station_type = self::get_station_type($url);
        $this->artist_slug = self::get_artist_slug($url);
        
        return ( ($this->station_type == 'music') & $this->artist_slug );

    }

    static function get_artist_slug($url){
        $pattern = '~^http(?:s)?://(?:www\.)?last.fm/player/station/music/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_title(){
        
        $title = sprintf( __('Last.fm stations (similar artist): %s','wpsstm'),$this->artist_slug );

        return $title;
    }
}

function wpsstm_lastfm_init(){
    global $wpsstm_lastfm;
    $wpsstm_lastfm = new WPSSTM_LastFM();
}

add_action('wpsstm_load_services','wpsstm_lastfm_init');