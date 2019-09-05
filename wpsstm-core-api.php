<?php
define('WPSSTM_API_URL','https://api.spiff-radio.org/wp-json/');
define('WPSSTM_API_CACHE','https://api.spiff-radio.org/wordpress/wp-content/uploads/wpsstmapi/');
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

    static function api_request($endpoint = null, $namespace = null, $params=null,$method = 'GET'){

        if (!$namespace) $namespace = WPSSTM_API_NAMESPACE; 

        if (!$endpoint){
            return new WP_Error('wpsstmapi_no_api_url',__("Missing API endpoint",'wpsstm'));
        }
        
        $rest_url = WPSSTM_API_URL . $namespace . '/' .$endpoint;

        WP_SoundSystem::debug_log(array('url'=>$rest_url,'method'=>$method),'remote REST query...');

        //Create request
        $request = WP_REST_Request::from_url( $rest_url );
        
        //Method, headers, ...
        $request->set_method( $method );
        $request->set_header('Accept', 'application/json'); //TOUFIX TOUCHECK useful ?
        $token = self::has_valid_api_token() ? wpsstm()->get_options('wpsstmapi_token') : null;

        //token
        if ( $token ){
            $request->set_header( 'Authorization',sprintf('Bearer %s',$token) );
        }

        //TOUFIX add request timeout ? how ?
        
        //params
        switch($method){
            case 'GET':
                $request->set_query_params($params);
            break;
            case 'POST':
                $request->set_body_params($params);
            break;
        }

        //Get response
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            
            $error = $response->as_error();

            $error_message = $error->get_error_message();
            
            WP_SoundSystem::debug_log($error_message,'remote REST query error');

            return $error;
            
        }
        
        //Get datas
        $datas = $response->get_data();

        return $datas;

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