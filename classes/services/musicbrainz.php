<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPSSTM_MusicBrainz {
    
    static $mb_api_sleep = 1;

    function __construct(){

        //backend
        add_filter( 'wpsstm_get_music_detail_engines',array($this,'register_details_engine'), 5 );
        add_action( 'rest_api_init', array($this,'register_endpoints') );

    }
    
    function register_endpoints() {
        //TRACK
		$controller = new WPSSTM_MusicBrainz_Endpoints();
		$controller->register_routes();
    }

    public function register_details_engine($engines){
        $engines[] = new WPSSTM_Musicbrainz_Data();
        return $engines;
    }

    public static function get_search_results($type,$artist,$album='_',$track='null'){
        
        //sanitize input
        $allowed_types = array('artists', 'recordings', 'releases');
        $type_single = null;
        $search_str = null;
        
        if ( !in_array($type,$allowed_types) ) 
            return new WP_Error('musicbrainz_invalid_api_type',__("invalid item type",'wpsstmapi'));

        if ($album==='_') $album = null;
        $inc = array('url-rels'); //https://musicbrainz.org/doc/Development/XML_Web_Service/Version_2
        

        switch($type){
                
            case 'artists':
                
                if ( !$artist ) break;
                
                $type_single = 'artist';
                
                $api_query = array(
                    'artist' => $artist
                );

                $inc[] = 'tags';
                
            break;
                
            case 'releases': //album
                
                if ( !$artist || !$album ) break;
                
                $type_single = 'release';
                
                $api_query = array(
                    'artist' => $artist,
                    'release' => $album,
                );

                $inc[] = 'artist-credits';
                //$inc[] = 'collections';
                $inc[] = 'labels';
                $inc[] = 'recordings';
                $inc[] = 'release-groups';
                
                
            break;
                
            case 'recordings': //track
                
                if ( !$artist || !$track ) break;
                
                $type_single = 'recording';
                
                $api_query = array(
                    'artist' => $artist,
                    'recording' => $track,
                );
                
                /*
                TOU FIX seems that we get better results when we ignore albums, maybe we should do this is two passes ?
                One with the album, if no result, retry without ?
                
                if($album){
                    $api_query['release'] = $album;
                }
                
                */

                $inc[] = 'artist-credits';
                $inc[] = 'releases';
                
            break;

        }
        
        if (!$type_single){
            return new WP_Error('musicbrainz_missing_type',__("Missing search type",'wpsstmapi'));
        }
        
        if ($api_query){
            array_walk($api_query, function(&$value, $key) {
                $value  = sprintf('%s:%s',$key,rawurlencode($value));
            });

            $search_str = implode(' AND ',$api_query);
        }

        
        if (!$search_str){
            return new WP_Error('musicbrainz_missing_search_query',__("Missing search query",'wpsstmapi'));
        }

        $url = sprintf('http://musicbrainz.org/ws/2/%1s/',$type_single);
        $url = add_query_arg(array('query'=>$search_str),$url);

        if ($inc) $url = add_query_arg(array('inc'=>implode('+',$inc)),$url);
        $url = add_query_arg(array('fmt'=>'json'),$url);
        
        WP_SoundSystem::debug_log($url,'musicbrainz search');
   
        //TO FIX TO CHECK : delay API call ?
        /*
        if ($wait_api_time = self::$mb_api_sleep ){
            sleep($wait_api_time);
        }
        */

        //do request
        $request_args = array(
          'timeout' => 20,
          'User-Agent' => WPSSTM_REST_NAMESPACE,
        );

        $request = wp_remote_get($url,$request_args);
        if (is_wp_error($request)) return $request;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return $response;
        
        $api_results = json_decode($response, true);
        
        //check for errors
        if ( $error_msg = wpsstm_get_array_value('error',$api_results) ){
            return new WP_Error( 'musicbrainz_api_search',$error_msg);
        }

        $result_keys = array($type);
        return wpsstm_get_array_value($result_keys, $api_results);
    }
    
    public static function get_item_data($type,$id){
        
        //sanitize input
        $allowed_types = array('artists', 'recordings', 'releases');
        $type_single = null;
        
        if ( !in_array($type,$allowed_types) ) 
            return new WP_Error('musicbrainz_invalid_api_type',__("invalid item type",'wpsstmapi'));


        $inc = array('url-rels'); //https://musicbrainz.org/doc/Development/XML_Web_Service/Version_2

        switch($type){

            case 'artists':

                $type_single = 'artist';
                $inc[] = 'tags';
                
            break;
                
            case 'releases': //album
                
                $type_single = 'release';

                $inc[] = 'artist-credits';
                //$inc[] = 'collections';
                $inc[] = 'labels';
                $inc[] = 'recordings';
                $inc[] = 'release-groups';
                
                
            break;
                
            case 'recordings': //track

                $type_single = 'recording';
                $inc[] = 'artist-credits';
                $inc[] = 'releases';
                
            break;

        }

        if (!$type_single){
            return new WP_Error('musicbrainz_missing_type',__("Missing search type",'wpsstmapi'));
        }

        if (!$id){
            return new WP_Error('musicbrainz_missing_search_query',__("Missing search ID",'wpsstmapi'));
        }

        $url = sprintf('http://musicbrainz.org/ws/2/%1s/%2s',$type_single,$id);

        if ($inc) $url = add_query_arg(array('inc'=>implode('+',$inc)),$url);
        $url = add_query_arg(array('fmt'=>'json'),$url);

        //TO FIX TO CHECK : delay API call ?
        /*
        if ($wait_api_time = self::$mb_api_sleep ){
            sleep($wait_api_time);
        }
        */

        //do request
        $request_args = array(
          'timeout' => 20,
          'User-Agent' => WPSSTM_REST_NAMESPACE,
        );

        $request = wp_remote_get($url,$request_args);
        if (is_wp_error($request)) return $request;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return $response;
        
        $api_results = json_decode($response, true);

        //check for errors
        if ( $error_msg = wpsstm_get_array_value('error',$api_results) ){

            return new WP_Error( 'musicbrainz_api_search',$error_msg);
        }
        
        return $api_results;
    }

}

class WPSSTM_MusicBrainz_Endpoints extends WP_REST_Controller {
    /**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = WPSSTM_REST_NAMESPACE;
		$this->rest_base = 'services/musicbrainz';
	}
    /**
     * Register the component routes.
     */

    public function register_routes() {
        
        //identify a track
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
                //TOUFIX should be local request 'permission_callback' => array( 'WP_SoundSystem_API', 'auth_logged_user' ),
            )
        ) );
        
        //identify an album
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
                //TOUFIX should be local request 'permission_callback' => array( 'WP_SoundSystem_API', 'auth_logged_user' ),
            )
        ) );
        
        //identify an artist
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
                //TOUFIX should be local request 'permission_callback' => array( 'WP_SoundSystem_API', 'auth_logged_user' ),
            )
        ) );

        /* get datas based on ID */
        // .../wp-json/wpsstm/v1/services/musicbrainz/data/artists/a74b1b7f-71a5-4011-9441-d0b5e4122711
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/data/(?P<type>.*)/(?P<id>[A-Za-z0-9-]+)', array(
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
                //TOUFIX should be local request 'permission_callback' => array( 'WP_SoundSystem_API', 'auth_logged_user' ),
            )
        ) );

        
    }

    public function search_artists( $request ) {

        //get parameters from request
        $params = $request->get_params();

        $artist = urldecode(wpsstm_get_array_value('artist',$params));
        $data = WPSSTM_Musicbrainz::get_search_results('artists',$artist);

        
        return WP_SoundSystem::format_rest_response($data);
        
    }
    
    public function search_albums( $request ) {
        
        //get parameters from request
        $params = $request->get_params();

        $artist = urldecode(wpsstm_get_array_value('artist',$params));
        $album = urldecode(wpsstm_get_array_value('album',$params));

        $data = WPSSTM_Musicbrainz::get_search_results('releases',$artist,$album);

        return WP_SoundSystem::format_rest_response($data);
        
    }
    
    public function search_tracks( $request ) {

        //get parameters from request
        $params = $request->get_params();

        $artist = urldecode(wpsstm_get_array_value('artist',$params));
        $album = urldecode(wpsstm_get_array_value('album',$params));
        $track = urldecode(wpsstm_get_array_value('track',$params));

        $data = WPSSTM_Musicbrainz::get_search_results('recordings',$artist,$album,$track);

        return WP_SoundSystem::format_rest_response($data);
        
    }

    /**
     * Retrieve datas based on a spotify ID
     */
    public function get_id_data( $request ) {
        
        //get parameters from request
        $params = $request->get_params();

        $type = wpsstm_get_array_value('type',$params);
        $id = wpsstm_get_array_value('id',$params);

        $data = WPSSTM_Musicbrainz::get_item_data($type,$id);

        return WP_SoundSystem::format_rest_response($data);
    }

}

class WPSSTM_Musicbrainz_Data extends WPSSTM_Data_Engine{
    public $slug = 'musicbrainz';
    public $name = 'MusicBrainz';
    public $entries_table_classname = 'WPSSTM_MB_Entries';
            
    protected function get_supported_post_types(){
        return array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
    }
       
    /**
    Get the link of an item (artist/track/album).
    **/
    public function get_music_item_url($post_id = null){

        if ( !$music_id = $this->get_post_music_id($post_id) ) return;
            
        $remote_type = null;
        $post_type = get_post_type($post_id);
            
        switch( $post_type ){
            case wpsstm()->post_type_artist:
                $remote_type = 'artist';
            break;
            case wpsstm()->post_type_track:
                $remote_type = 'recording';
            break;
            case wpsstm()->post_type_album:
                $remote_type = 'release';
            break;
        }
            
        if (!$remote_type) return;
        return sprintf('https://musicbrainz.org/%s/%s',$remote_type,$music_id);

    }
            
    protected function query_music_entries( $artist,$album = '_',$track = null ){

        $endpoint = null;
        
        //url encode
        $artist = urlencode($artist);
        $album = urlencode($album);
        $track = urlencode($track);
        
        if($artist && $track){//track
            $endpoint = sprintf('services/musicbrainz/search/%s/%s/%s',$artist,$album,$track);
        }elseif($artist && ($album !== '_') ){//album
            $endpoint = sprintf('services/musicbrainz/search/%s/%s',$artist,$album);
        }elseif($artist){//artist
            $endpoint = sprintf('services/musicbrainz/search/%s',$artist);
        }

        if (!$endpoint){
            return new WP_Error('wpsstmapi_no_api_url',__("We were unable to build the API url",'wpsstm'));
        }

        return wpsstm()->local_rest_request($endpoint);
    }
            
    protected function get_item_auto_id($artist,$album=null,$track=null){

        $entries = $this->query_music_entries($artist,$album,$track);
        if ( is_wp_error($entries) || !$entries ) return $entries;
        
        $entry = wpsstm_get_array_value(array(0),$entries);

        $score = wpsstm_get_array_value(array('score'),$entry);
        $music_id = wpsstm_get_array_value(array('id'),$entry);

        if ($score < 90) return; //only if we got a minimum score
        return $music_id;
    }
    
    protected function get_music_data_for_post($post_id){
        
        if ( !$post_type = get_post_type($post_id) ) return false;
        
        if ( !$music_id = $this->get_post_music_id($post_id) ){
            return new WP_Error('wpsstm_missing_music_id',__("Missing music ID",'wpsstm'));
        }
        
        //remote API type
        $endpoint = null;
        switch($post_type){
            //artist
            case wpsstm()->post_type_artist:
                $endpoint = 'artists';
            break;
            //album
            case wpsstm()->post_type_album:
                $endpoint = 'releases';
            break;
            //track
            case wpsstm()->post_type_track:
                $endpoint = 'recordings';
            break;
        }
        
        $endpoint = sprintf('services/musicbrainz/data/%s/%s',$endpoint,$music_id);
        return wpsstm()->local_rest_request($endpoint);
    }
    
    protected function get_mapped_object_by_post($post_id){
        
        $item = array();
        $post_type = get_post_type($post_id);
        $datas = $this->get_post_music_data($post_id);

        switch ($post_type){
            case wpsstm()->post_type_artist:
                //$item['artist'] = wpsstm_get_array_value(array('name'), $datas);
            break;
            case wpsstm()->post_type_track:
                
                $item = new WPSSTM_Track();
                $item->title =     wpsstm_get_array_value(array('title'), $datas);
                $item->artist =    wpsstm_get_array_value(array('artist-credit',0,'name'), $datas);
                $item->album =     wpsstm_get_array_value(array('releases',0,'title'), $datas);
                $item->duration =  wpsstm_get_array_value(array('length'), $datas);
                //$item->image_url = 
                
            break;
            case wpsstm()->post_type_album:
                //$item['artist'] =   wpsstm_get_array_value(array('artist-credit',0,'name'), $datas);
                //$item['album'] =    wpsstm_get_array_value(array('title'), $datas);
            break;
        }
        return $item;
    }
  
}

class WPSSTM_MB_Entries extends WPSSTM_Music_Entries {
    
    var $engine_slug = 'musicbrainz';
    
    function display_tablenav($which){
        
    }
    
    function prepare_items() {
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
    }
    
    function get_columns(){
        global $post;
        
        $columns = array(
            'cb'            => '',//<input type="checkbox" />', //Render a checkbox instead of text
        );
        
        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $columns['mbitem_artist'] = __('Artist','wpsstm');
            break;
                
            case wpsstm()->post_type_track:
                $columns['mbitem_track'] = __('Track','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                $columns['mbitem_album'] = __('Album','wpsstm');
            break;
                
            case wpsstm()->post_type_album:
                $columns['mbitem_album'] = __('Album','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                
            break;

        }
        
        //mbid
        $columns['mbitem_mbid'] = __('ID','wpsstm');
        $columns['mbitem_score'] = __('Score','wpsstm');
        
        return $columns;
    }
    
	public function column_mbitem_artist( $item ) {
        global $post;
        
        $output = '—';
        $artist = null;

        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $artist = $item;
            break;
                
            case wpsstm()->post_type_track:
            case wpsstm()->post_type_album:
                $artist = $item['artist-credit'][0]['artist'];
            break;

        }
        
        if (!$artist) return $output;
        
        $output = $artist['name'];

        if ( isset($artist['disambiguation']) ){
            $output.=' '.sprintf('<small>%s</small>',$artist['disambiguation']);
        }

        return $output;
    }
    
	public function column_mbitem_track( $item ) {
        global $post;
        
        $output = '—';
        
        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                $output = $item['title'];
            break;

        }
        
        return $output;
    }
    
	public function column_mbitem_album( $item ) {
        global $post;
        
        $album = null;
        $output = '—';

        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                 $album = wpsstm_get_array_value(array('releases',0),$item);
            break;
                
            case wpsstm()->post_type_album:
                $album = $item;
            break;

        }
        
        if (!$album) return $output;

        $output = $album['title'];
        $small_title_arr = array();
        
        
        //date
        if ( isset($album['date']) ){
            $small_classes = array('item-info-title');
            $small_classes_str = wpsstm_get_classes_attr($small_classes);
            $small_title_arr[]=' '.sprintf('<small %s>%s</small>',$small_classes_str,$album['date']);
        }
        
        $output .= implode("",$small_title_arr);

        return $output;
    }

	public function column_mbitem_score( $item ) {
        echo wpsstm_get_percent_bar($item['score']);
	}
    
}

function wpsstm_musicbrainz_init(){
    global $wpsstm_musicbrainz;
    $wpsstm_musicbrainz = new WPSSTM_MusicBrainz();
}

add_action('wpsstm_load_services','wpsstm_musicbrainz_init');