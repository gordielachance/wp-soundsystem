<?php

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

class WPSSTM_Rest{
  function __construct(){
    add_action( 'rest_api_init', array($this,'register_endpoints') );
  }
  function register_endpoints() {
      //TRACK
  $controller = new WPSSTM_Endpoints();
  $controller->register_routes();
  }
}

class WPSSTM_Endpoints extends WP_REST_Controller {
    /**
	 * Constructor.
	 */
	public function __construct() {
	}
  /**
   * Register the component routes.
   */

  public function register_routes() {
    //get radios
    register_rest_route( WPSSTM_REST_NAMESPACE, '/posts', array(
      'methods' => WP_REST_Server::READABLE,
      'callback' => array( __class__, 'rest_get_posts' ),
      'permission_callback' => '__return_true'
    ));
  }

  public static function rest_get_posts(WP_REST_Request $request){
    $params = $request->get_params();

    $args= array(
      'post_type'=>array(wpsstm()->post_type_playlist,wpsstm()->post_type_radio),
      'post_status'=>'any',
      'posts_per_page'=>50,
    );

    $query = new WP_Query( $args );

    return array_map(function($post) {
      $playlist = new WPSSTM_Post_Tracklist($post->ID);
      return json_decode($playlist->to_jspf(true));//get JSPF from old plugin
    },$query->posts);


  }

}

new WPSSTM_Rest();
