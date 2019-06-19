<?php

class WPSSTM_Importer{
    function __construct(){

        $this->options = array(

        );
        
        if ($this->can_importer() === true){
            add_action( 'rest_api_init', array($this,'register_endpoints') );
        }

    }
    
    public function can_importer(){
        return true;
        
    }
    
    public static function auth_request_free( $request ) {
        return true;
    }
    
    function register_endpoints() {
        //TRACK
		$controller = new WPSSTM_Importer_Endpoints();
		$controller->register_routes();
    }

}

class WPSSTM_Importer_Endpoints extends WP_REST_Controller {
    /**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'wpsstm/v1';//TOUFIX WP_SoundSystem_API::$namespace;
		$this->rest_base = 'import/url';
	}
    /**
     * Register the component routes.
     */

    public function register_routes() {
        
        //identify a track
        // .../wp-json/wpsstmapi/v1/services/spotify/search/U2/_/Sunday Bloody Sunday
        register_rest_route( $this->namespace, '/' . $this->rest_base . '(?:/?url=(?P<url>\d+))?', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'import_url' ),
				'args' => array(
		            'url' => array(
		                'validate_callback' => array($this, 'validateImportUrl')
		            ),
                ),
                'permission_callback' => array( 'WPSSTM_Importer', 'auth_request_free' ),
            )
        ) );

    }
    
    function validateImportUrl($param){
        return (bool)filter_var($param, FILTER_VALIDATE_URL);
    }
    
    function import_url( WP_REST_Request $request ) {
        $parameters = $request->get_url_params();
        $url = $_GET['url'];
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if( !$url ){
            return new WP_Error('wpsstm_importer_bad_url',sprintf(__("invalid URL: %s",'wpsstmapi'),$url));
        }
        
        /*
        CACHE, permissions, etc.
        */
        
        /*
        Start Import
        */
        
        
        $importer = new WPSSTM_Imported_Tracklist($url);
        $render = $importer->render_xspf();
        print_r($render);die();
        $tracklist->render_xspf();

        print_r($tracks);die();

    }


}

class WPSSTM_Imported_Tracklist{
    
    var $url = null;
    var $preset = null;
    
    function __construct($url){
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if(!$url) return;
        $this->url = $url;
    }
    
    public function populate_preset(){

        /*
        Build presets.
        The default preset, WPSSTM_Remote_Tracklist, should be hooked with the lowest priority
        */
        $presets = array();
        $presets = apply_filters('wpsstm_remote_presets',$presets,$this);

        /*
        Select a preset based on the tracklist URL, or use the default preset
        */
        foreach((array)$presets as $test_preset){
            
            $test_preset->__construct($this->url,$this->options);
            
            if ( ( $ready = $test_preset->init_url($this->url) ) && !is_wp_error($ready) ){
                $this->preset = $test_preset;
                break;
            }
        }
        
        //default presset
        if (!$this->preset){
            $this->preset = new WPSSTM_Remote_Tracklist($this->url,$this->options);
        }

        $this->importer_log($this->preset->get_preset_name(),'preset found');
        return $this->preset;
    }
    
    function render_xspf(){
        
        $preset = $this->populate_preset();
        $tracks = $preset->populate_remote_tracks();
        
        print_r($tracks);die();
        
        global $wpsstm_tracklist;
        $wpsstm_tracklist->populate_subtracks();

        $tracklist = $wpsstm_tracklist;

        $is_download = wpsstm_get_array_value('dl',$_REQUEST);
        $is_download = filter_var($is_download, FILTER_VALIDATE_BOOLEAN);

        if ( $is_download ){
            $now = current_time( 'timestamp', true );
            $filename = $post->post_name;
            $filename = sprintf('%s-%s.xspf',$filename,$now);
            header("Content-Type: application/xspf+xml");
            header('Content-disposition: attachment; filename="'.$filename.'"');
        }else{
            header("Content-Type: text/xml");
        }

        require wpsstm()->plugin_dir . 'classes/wpsstm-playlist-xspf.php';

        $xspf = new mptre\Xspf();

        //playlist
        if ( $title = get_the_title() ){
            $xspf->addPlaylistInfo('title', $title);
        }

        if ( $author = $tracklist->author ){
            $xspf->addPlaylistInfo('creator', $author);
        }

        if ( $timestamp = $tracklist->updated_time ){
            $date = gmdate(DATE_ISO8601,$timestamp);
            $xspf->addPlaylistInfo('date', $date);
        }

        if ( $location = $tracklist->location ){
            $xspf->addPlaylistInfo('location', $location);
        }

        $annotation = sprintf( __('Station generated with the %s plugin — %s','wpsstm'),'WP SoundSystem','https://wordpress.org/plugins/wp-soundsystem/');
        $xspf->addPlaylistInfo('annotation', $annotation);


        //subtracks
        if ( $tracklist->have_subtracks() ) {
            while ( $tracklist->have_subtracks() ) {
                $tracklist->the_subtrack();
                global $wpsstm_track;
                $arr = $wpsstm_track->to_xspf_array();
                $xspf->addTrack($arr);
            }
        }

        echo $xspf->output();
    }
    
    function importer_log($data,$title = null){

        //global log
        if ($this->post_id){
            $title = sprintf('[importer:%s] ',$this->url) . $title;
        }
        wpsstm()->debug_log($data,$title);
    }
    
}

new WPSSTM_Importer();