<?php

define('WPSSTM_API_URL','https://api.spiff-radio.org/wp-json/wpsstmapi/v1/');

class WPSSTM_Core_API {
    
    public static $wpsstmapi_token_name = 'wpsstmapi_token';
    
    function __construct(){
        
    }
    
    public static function can_wpsstmapi(){
        
        $token = self::get_token();
        if ( is_wp_error($token) ) return $token;

        if (!$token){
            return new WP_Error( 'wpsstmapi_missing_token', __('Missing WPSSTM API token','wpsstm') );
        }
        
        return true;

    }
    
    private static function get_token(){
        
        if ( $token = get_transient( self::$wpsstmapi_token_name ) ){
            return $token;
        }

        if ( !$client_secret = wpsstm()->get_options('wpsstmapi_client_secret') ){
            return new WP_Error( 'wpsstmapi_missing_client_secret', __('Missing WPSSTM API client secret','wpsstm') );
        }
        
        $api_url = sprintf('auth/get_token/%s',$client_secret);
        $api_results = self::api_request($api_url);
        if ( is_wp_error($api_results) ) return $api_results;

        $token = wpsstm_get_array_value('token',$api_results);
        $expiration = wpsstm_get_array_value('expiration',$api_results);

        //set local transient
        if ($token){
            wpsstm()->debug_log($api_results,'store WPSSTMAPI token as transient !');
            set_transient(self::$wpsstmapi_token_name,$api_results,1 * DAY_IN_SECONDS);
        }
        
        return $token;
    }

    static function api_request($url = null){
        
        if (!$url){
            return new WP_Error('wpsstmapi_no_api_url',__("Missing API URL",'wpsstm'));
        }
        
        //build URL with token
        
        $url = WPSSTM_API_URL . $url;

        if ( $tokendata = get_transient( self::$wpsstmapi_token_name ) ){
            $token = wpsstm_get_array_value('token',$tokendata);
            $url = add_query_arg(array('token'=>$token),$url);
        }
        
        if (!$token){
            return new WP_Error( 'wpsstm_mising_api_token', __("Missing API Token",'wpsstm') );
        }
        
        wpsstm()->debug_log($url,'query API...');

        $request = wp_remote_get($url);
        if (is_wp_error($request)) return $request;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return $response;
        
        $response = json_decode($response, true);
        
        //check for errors
        $code = wpsstm_get_array_value('code',$response);
        $message = wpsstm_get_array_value('message',$response);
        $data = wpsstm_get_array_value('data',$response);
        $status = wpsstm_get_array_value(array('data','status'),$response);

        if ( $code && $message && $status ){
            $error = new WP_Error($code,$message,$data );
            wpsstm()->debug_log($error,'query API error');
            return $error;
        }
        
        return $response;

    }

}

function wpsstm_api_init(){
    new WPSSTM_Core_API();
}
add_action('wpsstm_init','wpsstm_api_init');