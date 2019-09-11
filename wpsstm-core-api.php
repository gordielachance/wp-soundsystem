<?php
//define('WPSSTM_API_URL','http://localhost:8888/la-bonne-toune/');
define('WPSSTM_API_URL','https://api.spiff-radio.org/');
define('WPSSTM_API_REST',WPSSTM_API_URL . 'wp-json/');
define('WPSSTM_API_CACHE',WPSSTM_API_URL . 'wordpress/wp-content/uploads/wpsstmapi/');

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

            $url = WPSSTM_API_REST . 'simple-jwt-authentication/v1/token/validate';
            
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
            
            WP_SoundSystem::debug_log('check is premium...');
            
            $datas = WPSSTM_Core_API::api_request('premium/get');

            if ( is_wp_error($datas) ){
                $datas = false;
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
    
    /*
    When receiving an error from the API, convert error arrays to WP_Error.
    //TOUFIX TOUCHECK this is quite quirky.  Should be done in another way ?  
    But we cannot use WP_REST_Request here since it is remote API... :/
    */
    
    private static function convert_json_response_errors(&$response){
        if ( isset($response['code']) && isset($response['message']) && isset($response['data']) ){

            $error = new WP_Error( $response['code'],$response['message'],$response['data'] );
            
            if( isset($response['additional_errors']) ){
                foreach ($response['additional_errors'] as $add_error){
                    $error->add($add_error['code'], $add_error['message'], $add_error['data']);
                }
            }
            
            return $error;

        }
        
        return $response;
    }

    static function api_request($endpoint = null, $params=null,$method = 'GET'){

        if (!$endpoint){
            return new WP_Error('wpsstmapi_no_api_url',__("Missing API endpoint",'wpsstm'));
        }
        
        $rest_url = WPSSTM_API_REST . WPSSTM_API_NAMESPACE . '/' .$endpoint;

        //parameters
        if ($params){
            $url_params = rawurlencode_deep( $params );
            $rest_url = add_query_arg($url_params,$rest_url);
        }

        //Create request
        //we can't use WP_REST_Request here since it is remote API
        
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
        
        WP_SoundSystem::debug_log(array('endpoint'=>$endpoint,'params'=>$params,'args'=>$api_args,'method'=>$method,'url'=>$rest_url),'remote API query...');

        $request = wp_remote_get($rest_url,$api_args);

        if ( is_wp_error($request) ){
            WP_SoundSystem::debug_log($request->get_error_message());
            return $request;
        }
        
        $headers = wp_remote_retrieve_headers($request);
        $response_code = wp_remote_retrieve_response_code($request);
        $response = wp_remote_retrieve_body( $request );

        if( $response_code > 400){
            $response_msg = wp_remote_retrieve_response_message($request);
            $error_msg = sprintf('[%s] %s',$response_code,$response_msg);
            $error_msg = sprintf( __('Unable to query API: %s','wpsstm'),$error_msg );
            
            WP_SoundSystem::debug_log($error_msg);
            return new WP_Error( 'query_api',$error_msg, $rest_url );
            
        }

        if ( is_wp_error($response) ){
            WP_SoundSystem::debug_log($response->get_error_message());
            return $response;
        }

        $response = json_decode($response, true);
        $response = self::convert_json_response_errors($response);

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