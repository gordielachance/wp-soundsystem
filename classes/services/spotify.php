<?php
class WPSSTM_Spotify{
    
    static $spotify_options_meta_name = 'wpsstm_spotify_options';
    
    public $options = array();
    
    function __construct(){
        
        $options_default = array(
            'client_id' =>          null,
            'client_secret' =>      null,
        );
        
        $this->options = wp_parse_args(get_option( self::$spotify_options_meta_name),$options_default);
        
        /*backend*/
        add_action( 'admin_init', array( $this, 'spotify_settings_init' ) );
        add_action( 'rest_api_init', array($this,'register_endpoints') );
        
        if ( $this->can_spotify_api() === true ){
            //music details
            add_filter( 'wpsstm_get_music_detail_engines',array($this,'register_details_engine') );
            
            //presets
            //TOUFIX should be WPSSTMAPI stuff
            add_filter('wpsstm_feed_url', array($this, 'spotify_playlist_bang_to_url'));
            add_filter('wpsstm_importer_bang_links',array($this,'register_spotify_bang_links'));
            
        }
    }
    
    function register_endpoints() {
        //TRACK
		$controller = new WPSSTM_Spotify_Endpoints();
		$controller->register_routes();
    }

    function spotify_playlist_bang_to_url($url){
        $pattern = '~^spotify:user:([^:]+):playlist:([\w\d]+)~i';
        preg_match($pattern,$url, $matches);
        $user = isset($matches[1]) ? $matches[1] : null;
        $playlist = isset($matches[2]) ? $matches[2] : null;
        
        if ($user && $playlist){
            $url = sprintf('https://open.spotify.com/user/%s/playlist/%s',$user,$playlist);
        }
        
        return $url;
    }

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
        
    public function can_spotify_api(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        if ( !$client_id ) return new WP_Error( 'spotify_no_client_id', __( "Required Spotify client ID missing", "wpsstm" ) );
        if ( !$client_secret ) return new WP_Error( 'spotify_no_client_secret', __( "Required Spotify client secret missing", "wpsstm" ) );
        
        return true;
        
    }
    
    function spotify_settings_init(){
        register_setting(
            'wpsstm_option_group', // Option group
            self::$spotify_options_meta_name, // Option name
            array( $this, 'spotify_settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'spotify_service', // ID
            'Spotify', // Title
            array( $this, 'spotify_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'spotify_client', 
            __('API','wpsstm'), 
            array( $this, 'spotify_api_settings' ), 
            'wpsstm-settings-page', // Page
            'spotify_service'//section
        );
        
    }

    function spotify_settings_sanitize($input){
        if ( WPSSTM_Settings::is_settings_reset() ) return;

        $new_input['client_id'] = ( isset($input['client_id']) ) ? trim($input['client_id']) : null;
        $new_input['client_secret'] = ( isset($input['client_secret']) ) ? trim($input['client_secret']) : null;

        return $new_input;
    }
    
    function spotify_settings_desc(){
        $new_app_link = 'https://developer.spotify.com/my-applications/#!/applications/create';
        
        printf(__('Get an API key %s.','wpsstm'),sprintf('<a href="%s" target="_blank">%s</a>',$new_app_link,__('here','wpsstm') ) );
    }

    function spotify_api_settings(){
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');
        
        //client ID
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_id]" value="%s" /></p>',
            __('Client ID:','wpsstm'),
            self::$spotify_options_meta_name,
            $client_id
        );
        
        //client secret
        printf(
            '<p><label>%s</label> <input type="text" name="%s[client_secret]" value="%s" /></p>',
            __('Client Secret:','wpsstm'),
            self::$spotify_options_meta_name,
            $client_secret
        );
    }

    function register_spotify_bang_links($links){
        $bang_playlist = '<label><code>spotify:user:USER:playlist:PLAYLIST_ID</code></label>';
        //$bang_playlist .= sprintf('<div id="wpsstm-spotify-playlist-bang" class="wpsstm-bang-desc">%s</div>',$desc);
        $links[] = $bang_playlist;
        return $links;
    }
    
    private function get_access_token(){
        
        $can_api = $this->can_spotify_api();
        if ( is_wp_error($can_api) ) return $can_api;
        
        $client_id = $this->get_options('client_id');
        $client_secret = $this->get_options('client_secret');


        $token = false;

        $args = array(
            'headers'   => array(
                'Authorization' => 'Basic '.base64_encode($client_id.':'.$client_secret)
            ),
            'body'      => array(
                'grant_type'    => 'client_credentials'
            )
        );

        $response = wp_remote_post( 'https://accounts.spotify.com/api/token', $args );

        if ( is_wp_error($response) ){
            return new WP_Error('spotify_missing_token',$response->get_error_message());
        }
            
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);

        if ( property_exists($body, 'access_token') ){
            return $body->access_token;
        }elseif ( property_exists($body, 'error') ){
            return new WP_Error('spotify_missing_token',$body->error);
        }else{
            return new WP_Error('spotify_missing_token','Error getting Spotify Token');
        }

    }
    
    public function get_spotify_request_args(){
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) return $token;
        
        $request_args = array(
            'headers'=>array(
                'Authorization' =>  'Bearer ' . $token,
                'Accept' =>         'application/json',
            )
        );
        return $request_args;
        
    }
    
    public function get_search_results($type,$artist,$album='_',$track='null'){
        
        //sanitize input
        $allowed_types = array('artists', 'albums', 'tracks','playlists');

        if ( !in_array($type,$allowed_types) ) 
            return new WP_Error('spotify_invalid_api_type',__("invalid item type",'wpsstmapi'));

        if ($album==='_') $album = null;
        $search_type = null;

        switch($type){
                
            case 'artists':
                
                if ( !$artist ) break;
                
                $search_str = sprintf('artist:%s',$artist);
                $search_type = 'artist';
                
            break;
                
            case 'tracks':
                
                if ( !$artist || !$track ) break;
                
                $search_str = sprintf('artist:%s track:%s',$artist,$track);

                /*
                TOU FIX seems that we get better results when we ignore albums, maybe we should do this is two passes ?
                One with the album, if no result, retry without ?
                if($album){
                    $search_str = sprintf('artist:%s album:%s track:%s',$artist,$album,$track);
                }
                */
                
                $search_type = 'track';
                
            break;
                
            case 'albums':
                
                if ( !$artist || !$album ) break;
                
                $search_str = sprintf('artist:%s album:%s',$artist,$album);
                $search_type = 'album';
                
                
            break;

        }
        
        if (!$search_str){
            return new WP_Error('spotify_missing_search_query',__("Missing search query",'wpsstmapi'));
        }
        
        ///
        
        $url_args = array(
            'q' =>      rawurlencode($search_str),
            'type' =>   $search_type,
            'limit' =>  10,
        );

        $url = add_query_arg($url_args,'https://api.spotify.com/v1/search');

        $spotify_args = $this->get_spotify_request_args();
        if (is_wp_error($spotify_args) ) return $spotify_args;

        $request = wp_remote_get($url,$spotify_args);
        if (is_wp_error($request)) return $request;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return $response;

        $api_results = json_decode($response, true);
        if ( is_wp_error($api_results) ) return $api_results;
        
        //check for errors
        if ( $error_msg = wpsstm_get_array_value('error',$api_results) ){

            return new WP_Error( 'spotify_api_search',$error_msg);
        }
        
        $result_keys = array($type,'items');
        $results = wpsstm_get_array_value($result_keys, $api_results);
        
        if ( empty($results) ){
            $results = false;
            WP_SoundSystem::debug_log(array('search_str'=>$search_str,'type'=>$type),'no Spotify search results');
        }
        
        return $results;

    }
    
    public function get_item_data($type,$id){
        $allowed_types = array('artists', 'albums', 'tracks','playlists');

        if ( !in_array($type,$allowed_types) ) 
            return new WP_Error('spotify_invalid_api_type',__("invalid item type",'wpsstmapi'));

        $url = sprintf('https://api.spotify.com/v1/%s/%s',$type,$id);
        
        $spotify_args = $this->get_spotify_request_args();
        if (is_wp_error($spotify_args) ) return $spotify_args;

        $request = wp_remote_get($url,$spotify_args);
        if (is_wp_error($request)) return $request;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return $response;

        $api_results = json_decode($response, true);
        
        return $api_results;
        
    }
    
    public function register_details_engine($engines){
        $engines[] = new WPSSTM_Spotify_Data();
        return $engines;
    }
    
    

}

class WPSSTM_Spotify_Data extends WPSSTM_Music_Data{
    public $slug = 'spotify';
    public $name = 'Spotify';
    public $entries_table_classname = 'WPSSTM_MB_Entries';
            
    protected function get_supported_post_types(){
        return array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
    }

    public function get_music_item_url($post_id = null){
        $id = null;
        $link = null;
        if (!$id = self::get_post_music_id($post_id) ) return;

        $post_type = get_post_type($post_id);
        $remote_type = null;

        switch($post_type){

            case wpsstm()->post_type_artist:
                $remote_type = 'artist';
            break;

            case wpsstm()->post_type_track:
                $remote_type = 'track';
            break;

            case wpsstm()->post_type_album:
                $remote_type = 'album';
            break;

        }
        
        if (!$remote_type) return;
        return sprintf('https://open.spotify.com/%s/%s',$remote_type,$id);
    }

    function get_item_auto_id( $artist,$album = null,$track = null ){

        $entries = $this->query_music_entries($artist,$album,$track);
        if ( is_wp_error($entries) || !$entries ) return $entries;

        $id = wpsstm_get_array_value(array(0,'id'),$entries);
        return $id;

    }
    
    protected function get_music_data_for_post($post_id){

        if ( !$post_type = get_post_type($post_id) ) return false;
        
        if ( !$music_id = $this->get_post_music_id($post_id) ){
            return new WP_Error('wpsstm_missing_music_id',__("Missing music ID",'wpsstm'));
        }

        //remote API type
        $endpoint = null;
        switch($post_type){
            case wpsstm()->post_type_artist:
                $endpoint = 'artists';
            break;

            case wpsstm()->post_type_track:
                $endpoint = 'tracks';
            break;

            case wpsstm()->post_type_album:
                $endpoint = 'albums';
            break;
        }
        
        $endpoint = sprintf('services/spotify/data/%s/%s',$endpoint,$music_id);
        return wpsstm()->local_rest_request($endpoint);
    }
    
    protected function query_music_entries( $artist,$album = null,$track = null ){

        $endpoint = null;
        $artist = urlencode($artist);
        $album = ($album === '_') ? null : $album;
        $album = urlencode($album);
        $track = urlencode($track);
        
        if($artist && $track){//track
            $endpoint = sprintf('services/spotify/search/%s/%s/%s',$artist,$album,$track);
        }elseif($artist && $album){//album
            $endpoint = sprintf('services/spotify/search/%s/%s',$artist,$album);
        }elseif($artist){//artist
            $endpoint = sprintf('services/spotify/search/%s',$artist);
        }

        return wpsstm()->local_rest_request($endpoint);
        
    }

    protected function artistdata_get_artist($data){
        return wpsstm_get_array_value(array('name'), $data);
    }
    protected function trackdata_get_artist($data){
        return wpsstm_get_array_value(array('artists',0,'name'), $data);
    }
    protected function trackdata_get_track($data){
        return wpsstm_get_array_value(array('name'), $data);
    }
    protected function trackdata_get_album($data){
        return wpsstm_get_array_value(array('album','name'), $data);
    }
    protected function trackdata_get_length($data){
        return wpsstm_get_array_value(array('duration_ms'), $data);
    }
    protected function albumdata_get_artist($data){
        return wpsstm_get_array_value(array('artists',0,'name'), $data);
    }
    protected function albumdata_get_album($data){
        return wpsstm_get_array_value(array('name'), $data);
    }  
            
}

class WPSSTM_Spotify_Endpoints extends WP_REST_Controller {
    /**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = WPSSTM_REST_NAMESPACE;
		$this->rest_base = 'services/spotify';
	}
    /**
     * Register the component routes.
     */

    public function register_routes() {
        global $wpsstm_spotify;
        
        //identify a track
        // .../wp-json/wpsstm/v1/services/spotify/search/U2/_/Sunday Bloody Sunday
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/search/(?P<artist>.*)/(?P<album>.*)/(?P<track>.*)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_tracks' ),
				'args' => array(
		            'artist' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
		            'album' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
		            'track' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
                ),
                'permission_callback' => array($wpsstm_spotify, 'can_spotify_api' ), //TOUFIX TOUCHECK + should be local request only
            )
        ) );
        
        //identify an album
        // .../wp-json/wpsstm/v1/services/spotify/search/Radiohead/Kid A
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/search/(?P<artist>.*)/(?P<album>.*)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_albums' ),
				'args' => array(
		            'artist' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
		            'album' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
                ),
                'permission_callback' => array($wpsstm_spotify, 'can_spotify_api' ), //TOUFIX TOUCHECK + should be local request only
            )
        ) );
        
        //identify an artist
        // .../wp-json/wpsstm/v1/services/spotify/search/Radiohead
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/search/(?P<artist>.*)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'search_artists' ),
				'args' => array(
		            'artist' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
                ),
                'permission_callback' => array($wpsstm_spotify, 'can_spotify_api' ), //TOUFIX TOUCHECK + should be local request only
            )
        ) );

        /* get datas based on ID */
        // .../wp-json/wpsstm/v1/services/spotify/data/playlists/37i9dQZF1DX4JAvHpjipBk
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/data/(?P<type>.*)/(?P<id>[0-9A-Za-z]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_id_data' ),
				'args' => array(
		            'type' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
		            'id' => array(
                        'required' => true,
		                //'validate_callback' => array($this, 'validatePhone')
		            ),
                ),
                'permission_callback' => array($wpsstm_spotify, 'can_spotify_api' ), //TOUFIX TOUCHECK + should be local request only
            )
        ) );

        
    }

    public function search_artists( $request ) {
        global $wpsstm_spotify;
        
        //get parameters from request
        $params = $request->get_params();

        $artist = urldecode(wpsstm_get_array_value('artist',$params));
        $data = $wpsstm_spotify->get_search_results('artists',$artist);

        return WP_SoundSystem::format_rest_response($data);
        
    }
    
    public function search_albums( $request ) {
        global $wpsstm_spotify;
        
        //get parameters from request
        $params = $request->get_params();

        $artist = urldecode(wpsstm_get_array_value('artist',$params));
        $album = urldecode(wpsstm_get_array_value('album',$params));
        $data = $wpsstm_spotify->get_search_results('albums',$artist,$album);

        return WP_SoundSystem::format_rest_response($data);
        
    }
    
    public function search_tracks( $request ) {
        global $wpsstm_spotify;
        
        //get parameters from request
        $params = $request->get_params();

        $artist = urldecode(wpsstm_get_array_value('artist',$params));
        $album = urldecode(wpsstm_get_array_value('album',$params));
        $track = urldecode(wpsstm_get_array_value('track',$params));

        $data = $wpsstm_spotify->get_search_results('tracks',$artist,$album,$track);

        return WP_SoundSystem::format_rest_response($data);
        
    }

    /**
     * Retrieve datas based on a spotify ID
     */
    public function get_id_data( $request ) {
        global $wpsstm_spotify;
        
        //get parameters from request
        $params = $request->get_params();

        $type = wpsstm_get_array_value('type',$params);
        $id = wpsstm_get_array_value('id',$params);

        $data = $wpsstm_spotify->get_item_data($type,$id);

        return WP_SoundSystem::format_rest_response($data);
    }

}

class WPSSTM_Spotify_Entries extends WPSSTM_Music_Entries {
    //TOUFIX
}

function wpsstm_spotify_init(){
    global $wpsstm_spotify;
    $wpsstm_spotify = new WPSSTM_Spotify();
}

add_action('wpsstm_load_services','wpsstm_spotify_init');