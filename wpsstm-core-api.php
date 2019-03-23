<?php

define('WPSSTM_API_URL','https://api.spiff-radio.org/wp-json/');
define('WPSSTM_API_REGISTER_URL','https://api.spiff-radio.org/?p=10');
define('WPSSTM_API_NAMESPACE','wpsstmapi/v1');

class WPSSTM_Core_API {
    
    public static $auth_transient_name = 'wpsstmapi_is_auth';

    function __construct(){
    }

    public static function can_wpsstmapi(){
        if ( !$token = wpsstm()->get_options('wpsstmapi_token') ){
            $link_el = sprintf('<a href="%s" target="_blank">%s</a>',WPSSTM_API_REGISTER_URL,__('here','wpsstm'));
            return new WP_Error('wpsstmapi_token_required',sprintf(__("An API key required.  Get one %s !",'wpsstm'),$link_el));
        }
        
        $is_auth = self::check_auth();
        if ( is_wp_error($is_auth) ) return $is_auth;
        
        if (!$is_auth){
            return new WP_Error('wpsstmapi_no_auth',__("Unable to authentificate API user.",'wpsstm'));
        }
        
        return true;
        
    }
    
    private static function check_auth(){
        
        $is_auth = false;
        
        
        if ( false === ( $is_auth = get_transient(self::$auth_transient_name ) ) ) {

            $api_results = self::api_request('token/validate','jwt-auth/v1','POST');
            
            if ( is_wp_error($api_results) ) {
                wpsstm()->debug_log($api_results,'check auth failed');
            }else{
                $data = wpsstm_get_array_value('data',$api_results);
                $status = wpsstm_get_array_value(array('data','status'),$api_results);

                if ($status === 200) {
                    $is_auth = true;
                }
            }

            set_transient( self::$auth_transient_name, $is_auth, 1 * DAY_IN_SECONDS );

        }
        
        return $is_auth;
    }

    static function api_request($endpoint = null, $namespace = null, $method = 'GET'){
        
        if (!$namespace) $namespace = WPSSTM_API_NAMESPACE; 

        if (!$endpoint){
            return new WP_Error('wpsstmapi_no_api_url',__("Missing API endpoint",'wpsstm'));
        }

        //build headers
        $auth_args = array(
            'method' =>     $method,
            'headers'=>     array(
                'Accept' =>         'application/json',
            )
        );
        
        //token
        if ( $token = wpsstm()->get_options('wpsstmapi_token') ){
            $auth_args['headers']['Authorization'] = sprintf('Bearer %s',$token);
        }
        
        //build URL
        $url = WPSSTM_API_URL . $namespace . '/' .$endpoint;

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