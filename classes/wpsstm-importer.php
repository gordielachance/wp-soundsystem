<?php
class WPSSTM_Importer{
    
    var $uploads_dir = null;
    
    function __construct(){
        global $wpsstm_importer;
        $wpsstm_importer = $this;

        $this->options = array(
            'cache_time_min' => 1,
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
    
    /*
    Get path where are written pinim files
    */
    static function get_uploads_dir(){
        
        $dir = WP_CONTENT_DIR . '/uploads/xspf';
        
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }else{
            return $dir;
        }

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
        return $importer->get_xspf_url();
    }


}

class WPSSTM_Imported_Tracklist{
    
    var $url = null;
    var $id = null;
    var $preset = null;
    var $cachetime_trans_name = null;
    
    function __construct($url){
        $url = filter_var($url, FILTER_VALIDATE_URL);
        if(!$url) return;
        $this->url = $url;
        $this->id = md5($this->url);
        $this->cachetime_trans_name = sprintf('wpsttm-cache-%s',$this->id);
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
            
            $test_preset->__construct($this->url);
            
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
    
    private function render_xspf(){
        
        $tracklist = new WPSSTM_Tracklist();
        $preset = $this->populate_preset();
        
        $tracks = $preset->populate_remote_tracks();
        if ( is_wp_error($tracks) ) return $tracks;
        
        $tracklist->add_tracks($tracks);

        require wpsstm()->plugin_dir . 'classes/wpsstm-playlist-xspf.php';

        $xspf = new mptre\Xspf();
        $xspf->addPlaylistInfo('location', $this->url);
        $xspf->addPlaylistInfo('title', $preset->get_remote_title() );
        $xspf->addPlaylistInfo('creator', $preset->get_remote_author() );

        $timestamp = current_time( 'timestamp', true );
        $date = gmdate(DATE_ISO8601,$timestamp);
        $xspf->addPlaylistInfo('date', $date);

        /*
        $annotation = sprintf( __('Station generated with the %s plugin — %s','wpsstm'),'WP SoundSystem','https://wordpress.org/plugins/wp-soundsystem/');
        $xspf->addPlaylistInfo('annotation', $annotation);
        */

        //subtracks
        if ( $tracklist->have_subtracks() ) {
            while ( $tracklist->have_subtracks() ) {
                $tracklist->the_subtrack();
                global $wpsstm_track;
                $arr = $wpsstm_track->to_xspf_array();
                $xspf->addTrack($arr);
            }
        }

        return $xspf->output();
    }

    function get_xspf_url(){
        global $wpsstm_importer;
        
        $file = get_transient( $this->cachetime_trans_name );
        $cache_url = null;
        $transient_name = $this->cachetime_trans_name;

        if ( ( false === ( $file ) ) || ( !file_exists($file) ) ) {
            
            $this->importer_log('write cache...');

            $file = $this->write_xspf();
            if ( is_wp_error($file) ) return $file;
            
            $lock_time = $wpsstm_importer->options['cache_time_min'] * MINUTE_IN_SECONDS;

            set_transient( $transient_name, $file, $lock_time );
            
        }else{
            $this->importer_log($file,'...FROM cache');
        }

        return $file;
    }
    
    function get_xspf_path(){
        
        if ( !$this->id ){
            return new WP_Error('wpsstm_missing_import_id',__('Missing import ID','wpsstmapi'));
        }

        $log_dir = WPSSTM_Importer::get_uploads_dir();
        return $log_dir . sprintf('/%s.xspf',$this->id);
    }
    
    private function write_xspf(){
        
        $file = $this->get_xspf_path();
        if ( is_wp_error($file) ) return $file;

        $content = $this->render_xspf();
        if ( is_wp_error($content) ) return $content;

        try{
            $handle = fopen($file, 'w');
            flock($handle, LOCK_EX);
            fwrite($handle, $content);
            flock($handle, LOCK_UN);
            fclose($handle);

        } catch ( Exception $e ) {
            $error_msg = sprintf(__("Unable to write file: %s",'wpsstmapi'),$file);
            return new WP_Error('wpsstmapi_write_xspf',$error_msg);
        }
        
        $this->importer_log($file,'...HAS written cache');
        
        return $file;

    }

    function seconds_before_refresh(){

        $updated_time = (int)get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true);
        if(!$updated_time) return 0;//never imported

        if (!$this->cache_min) return 0; //no delay

        $expiration_time = $updated_time + ($this->cache_min * MINUTE_IN_SECONDS);
        $now = current_time( 'timestamp', true );

        return $expiration_time - $now;
    }

    function importer_log($data,$title = null){
        $title = sprintf('[importer:%s] ',$this->url) . $title;
        wpsstm()->debug_log($data,$title);
    }
    
}

new WPSSTM_Importer();