<?php
define('WPSSTM_API_URL','https://api.spiff-radio.org/wordpress/wp-json/');
define('WPSSTM_API_NAMESPACE','wpsstmapi/v1');
define('WPSSTM_API_REGISTER_URL','https://api.spiff-radio.org/?p=10');

class WPSSTM_Core_API {
    
    public static $valid_token_transient_name = 'wpsstmapi_is_auth';
    public static $premium_expiry_transient_name = 'wpsstmapi_premium_expiry';

    function __construct(){
        add_filter( 'wpsstm_autolink_input',array($this,'api_track_autolink'), 5, 2 );
    }
    
    public static function has_valid_api_token(){
        
        $response = null;
        $valid = false;
        
        if ( !$token = wpsstm()->get_options('wpsstmapi_token') ){
            return false;
        }

        if ( false === ( $valid = get_transient(self::$valid_token_transient_name ) ) ) {

            $url = WPSSTM_API_URL . 'simple-jwt-authentication/v1/token/validate';
            
            //build headers
            $args = array(
                'headers'=>     array(
                    'Accept' =>         'application/json',
                    'Authorization' =>  sprintf('Bearer %s',$token),
                )
            );

            $request = wp_remote_post($url,$args);
            $response = wp_remote_retrieve_body( $request );
            if ( is_wp_error($response) ) return $response;

            $response = json_decode($response, true);

            //check for errors
            $code = wpsstm_get_array_value('code',$response);
            $message = wpsstm_get_array_value('message',$response);
            $data = wpsstm_get_array_value('data',$response);
            $status = wpsstm_get_array_value(array('data','status'),$response);
            $valid = ($status === 200);

            if ( $code && $message && ($status >= 400) ){
                $message = sprintf(__('TOKEN error: %s','wpsstm'),$message);
                $response = new WP_Error($code,$message,$data );
            }
            
            set_transient( self::$valid_token_transient_name, $valid, 1 * DAY_IN_SECONDS );
            
            if ( is_wp_error($response) ){
                return $response;
            }

        }

        return $valid;
    }
    
    public static function get_premium_datas(){
        
        $valid_token = self::has_valid_api_token();
        if ( !$valid_token || is_wp_error($valid_token) ) return $valid_token;
        
        if ( false === ( $datas = get_transient(self::$premium_expiry_transient_name ) ) ) {
            $datas = WPSSTM_Core_API::api_request('premium/get');
            if ( is_wp_error($datas) ){
                return $datas;
            }
            
            set_transient( self::$premium_expiry_transient_name, $datas, 1 * DAY_IN_SECONDS );
        }
        
        return $datas;
    }
    
    public static function is_premium(){
        $response = self::get_premium_datas();
        if ( is_wp_error($response) ) return false;
        return wpsstm_get_array_value('is_premium',$response);
    }

    static function api_request($endpoint = null, $namespace = null, $method = 'GET'){

        if (!$namespace) $namespace = WPSSTM_API_NAMESPACE; 

        if (!$endpoint){
            return new WP_Error('wpsstmapi_no_api_url',__("Missing API endpoint",'wpsstm'));
        }

        //build headers
        $api_args = array(
            'method' =>     $method, //TOUFIX TOUCHECK compatible with wp_remote_get ?
            'timeout' =>    wpsstm()->get_options('wpsstmapi_timeout'),
            'headers'=>     array(
                'Accept' =>         'application/json',
            )
        );
        
        $token = self::has_valid_api_token() ? wpsstm()->get_options('wpsstmapi_token') : null;
        
        //token
        if ( $token ){
            $api_args['headers']['Authorization'] = sprintf('Bearer %s',$token);
        }
        
        //build URL
        $url = WPSSTM_API_URL . $namespace . '/' .$endpoint;

        WP_SoundSystem::debug_log(array('url'=>$url,'token'=>$token),'query API...');

        $request = wp_remote_get($url,$api_args);
        if (is_wp_error($request)) return $request;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return $response;
        
        $response = json_decode($response, true);

        //check for errors
        $code = wpsstm_get_array_value('code',$response);
        $message = wpsstm_get_array_value('message',$response);
        $data = wpsstm_get_array_value('data',$response);
        $status = wpsstm_get_array_value(array('data','status'),$response);

        if ( $code && $message && ($status >= 400) ){

            $message = sprintf(__('WPSSTMAPI error: %s','wpsstm'),$message);
            $error = new WP_Error($code,$message,$data );
            
            WP_SoundSystem::debug_log($error,'api_request');

            return $error;
        }
        
        return $response;

    }
    
    function api_track_autolink($links_auto,$track){
        
        if (!$track->post_id){
            return new WP_Error( 'missing_track_id',__( 'Missing Track ID.', 'wpsstm' ));
        }

        $spotify_engine = new WPSSTM_Spotify_Data();
        if ( !$music_id = $spotify_engine->get_post_music_id($track->post_id) ){

            $music_id = $spotify_engine->auto_music_id($track->post_id); //we need a Spotify ID here
            if ( is_wp_error($music_id) ) $music_id = null;

        }

        if ( !$music_id ){
            return new WP_Error( 'missing_spotify_id',__( 'Missing Spotify ID.', 'wpsstmapi' ));
        }

        $api_url = sprintf('track/autolink/spotify/%s',$music_id);
        $links_auto = WPSSTM_Core_API::api_request($api_url);
        if ( is_wp_error($links_auto) ) return $links_auto;

        return $links_auto;
    }

}

function wpsstm_api_init(){
    new WPSSTM_Core_API();
}
add_action('wpsstm_init','wpsstm_api_init');