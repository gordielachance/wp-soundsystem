<?php
define('WPSSTM_API_URL','http://localhost:3000/');
//define('WPSSTM_API_URL','https://api.spiff-radio.org/');
define('WPSSTM_API_CACHE',WPSSTM_API_URL . 'wordpress/wp-content/uploads/wpsstmapi/');
define('WPSSTM_API_REGISTER_URL','https://api.spiff-radio.org/?p=10');

class WPSSTM_Core_API {

  public static $token_transient_name = 'wpsstmapi_token';
  public static $premium_expiry_transient_name = 'wpsstmapi_premium_expiry';

  function __construct(){
      add_filter( 'wpsstm_autolink_input',array($this,'api_track_autolink'), 5, 2 );
  }

  public static function get_token(){

    $response = null;
    $valid = false;

    if ( !$api_key = wpsstm()->get_options('wpsstmapi_key') ){
        return false;
    }

    if ( false === ( $token = get_transient(self::$token_transient_name ) ) ) {

      $url = WPSSTM_API_URL . 'auth/token';

      //build headers
      $args = array(
        'body' => array(
          'api_key' => $api_key
        )
      );

      $request = wp_remote_post($url,$args);
      if ( is_wp_error($request) ) return $request;

      $response = wp_remote_retrieve_body( $request );
      if ( is_wp_error($response) ) return $response;
      $response = json_decode($response, true);

      //check error in JSON response
      if ( $error = wpsstm_get_array_value(array('error'),$response) ){
        $first_value = reset($error);
        $first_key = key($error);
        return new WP_Error($first_key,$first_value);
      }

      if ( !$token = wpsstm_get_array_value(array('token'),$response) ){
        return new WP_Error('no_token','Missing token');
      }

      set_transient( self::$token_transient_name, $token, 1 * DAY_IN_SECONDS );

    }

    return $token;
  }

  public static function get_api_userdatas(){

    if ( false === ( $datas = get_transient(self::$premium_expiry_transient_name ) ) ) {

        WP_SoundSystem::debug_log('get api user datas...');

        $datas = WPSSTM_Core_API::api_request('auth/userdata');

        if ( is_wp_error($datas) ){
            $datas = false;
        }

        set_transient( self::$premium_expiry_transient_name, $datas, 1 * DAY_IN_SECONDS );

    }

    return $datas;
  }

  public static function is_premium(){
    $token = self::get_token();
    return (bool)$token;
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

    $rest_url = WPSSTM_API_URL . $endpoint;

    //build headers
    $api_args = array(
        'method' =>     $method,
        'timeout' =>    wpsstm()->get_options('wpsstmapi_timeout'),
        'headers'=>     array(
            'Accept' =>         'application/json',
        )
    );

    //parameters
    if ($params){
        if ($method == 'GET'){
            $url_params = http_build_query($params);
            $rest_url .= '?' . $url_params;
        }elseif ($method == 'POST' || $method == 'PUT') {
            $api_args['body'] = $params;
        }
    }

    //token
    if ( $token = self::get_token() ){
        $api_args['headers']['Authorization'] = sprintf('Bearer %s',$token);
    }

    WP_SoundSystem::debug_log(array('endpoint'=>$endpoint,'params'=>$params,'args'=>$api_args,'method'=>$method,'url'=>$rest_url),'remote API query...');

    $request = wp_remote_request($rest_url,$api_args);

    if ( is_wp_error($request) ){
        WP_SoundSystem::debug_log($request->get_error_message());
        return $request;
    }

    $headers = wp_remote_retrieve_headers($request);
    $response_code = wp_remote_retrieve_response_code($request);
    $response = wp_remote_retrieve_body( $request );

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
add_action('plugins_loaded','wpsstm_api_init');
