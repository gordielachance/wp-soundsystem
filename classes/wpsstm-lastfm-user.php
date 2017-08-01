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

            wpsstm()->debug_log(json_encode($user_api_metas),"WP_SoundSystem_LastFM_User::request_lastfm_user_api_metas()");

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
        
        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');
        $debug_args['success'] = $results;
        
        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"WP_SoundSystem_LastFM_User::lastfm_love_track()");
        
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
        
        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"WP_SoundSystem_LastFM_User::now_playing_lastfm_track()'");
        
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
        );
        
        if ($track->duration){ //we NEED a duration to set this argument; or scrobble won't work.
            $api_args['duration'] = $track->duration;
        }
        
        $debug_args = $api_args;
        $debug_args['lastfm_username'] = $this->get_lastfm_user_api_metas('username');

        wpsstm()->debug_log(json_encode($debug_args,JSON_UNESCAPED_UNICODE),"WP_SoundSystem_LastFM_User::scrobble_lastfm_track()");
        
        try {
            $track_api = new TrackApi($this->user_auth);
            $results = $track_api->scrobble($api_args);
        }catch(Exception $e){
            return wpsstm_lastfm()->handle_api_exception($e);
        }
        
        return $results;
    }
    
}