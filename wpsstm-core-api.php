<?php

define('WPSSTM_API_URL','https://api.spiff-radio.org/wp-json/');
define('WPSSTM_API_REGISTER_URL','https://api.spiff-radio.org/?p=10');
define('WPSSTM_API_NAMESPACE','wpsstmapi/v1');

class WPSSTM_Core_API {
    
    public static $auth_transient_name = 'wpsstmapi_is_auth';

    function __construct(){
        add_filter( 'wpsstm_autolink_input',array($this,'api_track_autolink'), 5, 2 );
    }

    public static function can_wpsstmapi(){
        
        $token = wpsstm()->get_options('wpsstmapi_token');
        $auth = self::check_auth();

        if ( $token && $auth && !is_wp_error($auth) ) return true;

        return new WP_Error('wpsstmapi_required',__("A valid WPSSTM API key is required.",'wpsstm'));
        
    }
    
    private static function check_auth(){
        
        $is_auth = false;
        
        
        if ( false === ( $is_auth = get_transient(self::$auth_transient_name ) ) ) {

            $api_results = self::api_request('token/validate','simple-jwt-authentication/v1','POST');
            
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
    
    function api_track_autolink($links_auto,$track){
        
        if ($this->can_wpsstmapi() === true){

            $spotify_engine = new WPSSTM_Spotify_Data();
            if ( !$music_id = $spotify_engine->get_post_music_id($track->post_id) ){

                $music_id = $spotify_engine->auto_music_id($track->post_id); //we need a post ID here
                
                if ( is_wp_error($music_id) ) $music_id = null;

            }

            if ( !$music_id ){
                return new WP_Error( 'missing_spotify_id',__( 'Missing Spotify ID.', 'wpsstmapi' ));
            }

            $api_url = sprintf('track/autolink/spotify/%s',$music_id);
            $links_auto = WPSSTM_Core_API::api_request($api_url);
            if ( is_wp_error($links_auto) ) return $links_auto;

        }
        return $links_auto;
    }

}

function wpsstm_api_init(){
    new WPSSTM_Core_API();
}
add_action('wpsstm_init','wpsstm_api_init');