<?php

define('WPSSTM_API_URL','https://api.spiff-radio.org/wp-json/');
define('WPSSTM_API_NAMESPACE','wpsstmapi/v1/');

class WPSSTM_Core_API {

    function __construct(){
    }

    public static function can_wpsstmapi(){
        
        $token = wpsstm()->get_options('wpsstmapi_token');
        if (!$token){
            return new WP_Error( 'wpsstmapi_missing_token', __('Missing WPSSTM API token','wpsstm') );
        }
        
        return true;

    }

    static function api_request($url = null){
        
        if (!$url){
            return new WP_Error('wpsstmapi_no_api_url',__("Missing API URL",'wpsstm'));
        }
        
        $token = wpsstm()->get_options('wpsstmapi_token');
        if (!$token){
            return new WP_Error( 'wpsstmapi_missing_token', __('Missing WPSSTM API token','wpsstm') );
        }

        //build headers
        $auth_args = array(
            'headers'=>array(
                'Authorization' =>  'Bearer ' . $token,
                'Accept' =>         'application/json',
            )
        );
        
        //build URL
        $url = WPSSTM_API_URL . WPSSTM_API_NAMESPACE . $url;

        wpsstm()->debug_log(array('url'=>$url,'token'=>$token),'query API...');

        $request = wp_remote_get($url,$auth_args);
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