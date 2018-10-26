<?php

use \ForceUTF8\Encoding;

class WPSSTM_Remote_Tracklist extends WPSSTM_Static_Tracklist{
    
    var $tracklist_type = 'live';

    //url stuff
    public $feed_url = null;
    public $feed_url_no_filters = null;
    var $scraper_options = array();
    
    public $is_expired = true; //if option 'datas_cache_min' is defined; we'll compare the current time to check if the tracklist is expired or not with check_has_expired()
    public $cache_source_key = null;
    public $cache_source_url = null;

    //response
    var $request_pagination = array(
        'total_pages'       => 1,
        'page_items_limit'  => -1, //When possible (eg. APIs), set the limit of tracks each request can get
        'current_page'      => 1
    );
    public $response = null;
    public $response_type = null;
    public $response_body = null;
    public $body_node = null;
    public $track_nodes = array();
    public $tracks = array();

    public $datas = null;

    public $notices = array();

    //request
    static $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'UTF-8', //our input is always UTF8 - look for fixUTF8() in code
        //'convert_to_encoding'       => 'UTF-8' //match WP database (or transients won't save)
    );

    public function __construct($post_id = null) {
        
        parent::__construct($post_id);
        
        require_once(wpsstm()->plugin_dir . '_inc/php/class-array2xml.php');
        $this->preset_name = __('HTML Scraper','wpsstm');
        
        $this->scraper_options = $this->get_default_scraper_options();

        if ($this->post_id){

            $this->feed_url = $this->feed_url_no_filters = wpsstm_get_live_tracklist_url($this->post_id);
            
            $db_options = get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$scraper_meta_name,true);
            $this->scraper_options = array_replace_recursive($this->scraper_options,(array)$db_options); //last one has priority

            if ($this->feed_url){
                $this->location = $this->feed_url;
            }
            
            if ( $meta = get_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name,true) ){
                $this->updated_time = $meta;
            }
            
            $this->cache_source_key = sprintf('wpsstm_cached_source_%s',$this->post_id);
            $this->cache_source_url = get_transient( $this->cache_source_key );

        }

    }
    
    protected function get_default_options(){
        $options = parent::get_default_options();
        
        $live_options = array(
            'is_expired' => $this->check_has_expired(),
            'ajax_tracklist' => $this->check_has_expired(),
        );
        
        return wp_parse_args($live_options,$options);
    }

    protected function get_default_scraper_options(){
        $live_options = array(
            'selectors' => array(
                'tracklist_title'   => array('path'=>'title','regex'=>null,'attr'=>null),
                'tracks'            => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="track"]'
                'track_artist'      => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="byArtist"]'
                'track_title'       => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="name"]'
                'track_album'       => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="inAlbum"]'
                'track_source_urls' => array('path'=>null,'regex'=>null,'attr'=>null),
                'track_image'       => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="thumbnailUrl"]'
            ),
            'tracks_order'              => 'desc',
            /*
            TRACKLIST CACHE
            time (in minutes) a tracklist is cached.  
            If enabled, a post will be temporary stored for each track fetched.
            It will be deleted at next refresh; if the track is no more part of the tracklist; and does not belong to any user tracklist or likes.
            */
            'datas_cache_min'           => 0, 
        );
        
        return $live_options;
        
    }

    /*
    Compare Tracks / Tracks Details wizard options to check if the user settings match the default preset settings.
    */
    function get_user_edited_scraper_options(){

        $default_options = $this->get_default_scraper_options();
        $options = $this->get_scraper_options();

        //compare multi-dimensionnal array
        $diff = wpsstm_array_recursive_diff($options,$default_options);
        
        //keep only scraper options
        $check_keys = array('selectors', 'tracks_order');
        $diff = array_intersect_key($diff, array_flip($check_keys));

        return $diff;
    }

    function populate_subtracks($args = null){

        if ( $this->did_query_tracks || !$this->is_expired ){
            return parent::populate_subtracks($args);
        }
        
        $is_cached = false;

        //try cache
        if ( $this->get_options('cache_source') && $this->cache_source_url ){
            $this->feed_url = $this->cache_source_url;
            $is_cached = true;
            $this->tracklist_log('found HTML cache' );
        }else{ //allow plugins to filter the URL
            $this->feed_url = apply_filters('wpsstm_live_tracklist_url',$this->feed_url);//presets can overwrite this with filters
        }
        
        if ( !$is_cached && $this->get_options('ajax_tracklist') && $this->wait_for_ajax() ){
            $url = $this->get_tracklist_action_url('refresh');
            $link = sprintf( '<a class="wpsstm-refresh-tracklist" href="%s">%s</a>',$url,__('Click to load the tracklist.','wpsstm') );
            $error = new WP_Error( 'requires-refresh', $link );
            $this->tracks_error = $error;
            return $error;
        }
        
        
        if ( $this->feed_url != $this->feed_url_no_filters){
            $this->tracklist_log($this->feed_url_no_filters,'original URL' );
        }
        
        /* POPULATE PAGE */
        $response = $this->populate_remote_response($this->feed_url);
        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( $is_cached ) {
            if ( is_wp_error($response) || ($response_code != 200) ){ //if cached file has been deleted or something
                $this->tracklist_log( 'Unable to get cached file,deleting transient, retrying...' );
                delete_transient( $this->cache_source_key );
                $this->cache_source_url = null;
                $response = $this->populate_subtracks();
            }
        }
        
        if ( is_wp_error($response) ) return $response;
        
        $response_type = $this->populate_response_type();
        if ( is_wp_error($response_type) ) return $response_type;
        
        $response_body = $this->populate_response_body();
        if ( is_wp_error($response_body) ) return $response_body;
        
        $body_node = $this->populate_body_node();
        if ( is_wp_error($body_node) ) return $body_node;
        
        if ( !$is_cached && $this->get_options('cache_source') ) {
           $this->cache_tracklist_source($response); 
        }

        $tracks = $this->get_remote_tracks();

        $this->did_query_tracks = true;

        if ( is_wp_error($tracks) ){
            $this->tracks_error = $tracks;
            return $tracks;
        }
        
        //sort
        if ($this->get_scraper_options('tracks_order') == 'asc'){
            $tracks = array_reverse($tracks);
        }

        $this->tracks = $this->add_tracks($tracks);
        $this->track_count = count($this->tracks);

        /*
        UPDATE TRACKLIST
        */
        $post_id = $this->update_live_tracklist();
        return $post_id;
    }

    /*
    Update WP post and eventually update subtracks.
    */
    
    function update_live_tracklist($save_subtracks = null){

        if (!$this->post_id){
            $this->tracklist_log('wpsstm_missing_post_id','WPSSTM_Remote_Tracklist::update_live_tracklist' );
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        //capability check
        if ( !WPSSTM_Core_Live_Playlists::can_live_playlists() ){
            $this->tracklist_log('wpsstm_missing_cap','WPSSTM_Remote_Tracklist::update_live_tracklist' );
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        new WPSSTM_Live_Playlist_Stats($this); //remote request stats
        
        $this->updated_time = current_time( 'timestamp', true );//set the time tracklist has been updated

        $meta_input = array(
            WPSSTM_Core_Live_Playlists::$remote_title_meta_name =>  $this->get_remote_title(),
            WPSSTM_Core_Live_Playlists::$remote_author_meta_name => $this->get_remote_author(),
            WPSSTM_Core_Live_Playlists::$time_updated_meta_name =>  $this->updated_time,
        );
        
        //should we save subtracks too ? By default, only if cache is enabled.
        if ($save_subtracks === null) $save_subtracks = (bool)$this->get_scraper_options('datas_cache_min');

        if ($this->tracks && $save_subtracks){

            //save new subtracks as community tracks
            $subtracks_args = array(
                'post_author'   => wpsstm()->get_options('community_user_id'),
            );
            $success = $this->save_subtracks($subtracks_args);
            
            if( is_wp_error($success) ){
                $this->tracklist_log($success->get_error_code(),'WPSSTM_Remote_Tracklist::update_live_tracklist' );
                return $success;
            }

        }
        
        //update tracklist post
        $tracklist_post = array(
            'ID' =>         $this->post_id,
            'meta_input' => $meta_input,
        );

        $success = wp_update_post( $tracklist_post, true );
        
        if( is_wp_error($success) ){
            $this->tracklist_log($success->get_error_code(),'WPSSTM_Remote_Tracklist::update_live_tracklist' );
            return $success;
        }
        
       //repopulate post
        $this->populate_tracklist_post();
        
        return $this->post_id;
    }

    protected function get_remote_tracks(){

        $raw_tracks = array();

        //count total pages
        $this->request_pagination = apply_filters('wppstm_live_tracklist_pagination',$this->request_pagination);
        
        if ( $this->request_pagination['page_items_limit'] > 0 ){
            $this->request_pagination['total_pages'] = ceil( $this->track_count / $this->request_pagination['page_items_limit'] );
        }

        while ( $this->request_pagination['current_page'] <= $this->request_pagination['total_pages'] ) {
            if ( $page_raw_tracks = $this->get_remote_page_tracks() ) {
                if ( is_wp_error($page_raw_tracks) ) return $page_raw_tracks;
                $raw_tracks = array_merge($raw_tracks,(array)$page_raw_tracks);
                
            }
            $this->request_pagination['current_page']++;
        }
        
        return $raw_tracks;
        
    }

    private function get_remote_page_tracks(){

        $this->tracklist_log(json_encode($this->request_pagination),'get_remote_page_tracks request_pagination' );

        do_action('wpsstm_after_get_remote_body',$this);

        //tracks HTML
        $track_nodes = $this->get_track_nodes($this->body_node);
        if ( is_wp_error($track_nodes) ) return $track_nodes;
        $this->track_nodes = $track_nodes;

        //tracks
        $tracks = $this->parse_track_nodes($track_nodes);
        
        $this->tracklist_log(count($tracks),'get_remote_page_tracks request_url - found track nodes' );

        return $tracks;
    }

    /*
    Arguments for the remote request.  (Could be overriden for presets).
    https://codex.wordpress.org/Function_Reference/wp_remote_get
    */
    
    public function get_request_args(){
        $defaults = array(
            'headers'   => array(
                'User-Agent'        => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36'
            )
        );
        return apply_filters('wpsstm_live_tracklist_request_args',$defaults);
    }
    
    function remote_type_to_ext($content_type) {
        $content_type = strtolower($content_type);

        $mimes = array( 
            'txt' => 'text/plain',
            'html' => 'text/html',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'xspf' => 'application/xspf+xml',
        );
        return array_search($content_type, $mimes);
     }
    
    /*
    Allow to upload files with new extensions when caching playlists
    */

    function uploads_custom_mimes($existing_mimes) {
        $existing_mimes['xspf'] = 'application/xspf+xml';
        $existing_mimes['json'] = 'application/javascript';
        return $existing_mimes;
    }
    
    /*
    Upload cached playlists to our custom 'uploads/wpsstm' path
    */
    
    function uploads_custom_dir( $upload ) {
        $log_dir = wpsstm_get_uploads_dir();
        $upload['subdir'] = '/' . basename($log_dir);
        $upload['path'] = $upload['basedir'] . $upload['subdir'];
        $upload['url']  = $upload['baseurl'] . $upload['subdir'];
        return $upload;
    }

    function cache_tracklist_source($response){

        $cache_seconds = ( $cache_min = $this->get_scraper_options('datas_cache_min') ) ? $cache_min * MINUTE_IN_SECONDS : false;
        
        if ( !$this->post_id ) return;
        if ( !$cache_seconds ) return;
        if ( is_wp_error($response) ) return;

        $ext = $this->remote_type_to_ext($response['headers']['content-type']);
        $filename = sprintf('%s-source.%s',$this->post_id,$ext);

        add_filter( 'upload_mimes', array($this,'uploads_custom_mimes') );
        add_filter( 'upload_dir', array($this,'uploads_custom_dir') );

        $ghost = wp_upload_bits($filename, null, wp_remote_retrieve_body($response));

        remove_filter( 'upload_mimes', array($this,'uploads_custom_mimes') );
        remove_filter( 'upload_dir', array($this,'uploads_custom_dir') );

        if ( $ghost['error'] ){
            $error_msg = sprintf('Error while creating cache file "%s": %s',$filename,$ghost['error']);
            $this->tracklist_log( $error_msg );
            return new WP_Error( 'cannot_cache_tracklist', $error_msg );
        }else{
            $this->tracklist_log( sprintf('Created HTML file: %s',$ghost['url']) );
            set_transient( $this->cache_source_key, $ghost['url'], $cache_seconds );
            return $ghost;
        }

    }
    
    function delete_cached_file(){
        
    }
    
    private function populate_remote_response($url){

        if( $this->response !== null ) return $this->response; //already populated

        $response = null;
        $cached_url = null;
        
        $this->tracklist_log($url,'get page' );
        $response = wp_remote_get( $url, $this->get_request_args() );

        //errors
        if ( !is_wp_error($response) ){

            $response_code = wp_remote_retrieve_response_code( $response );

            if ($response_code && $response_code != 200){
                $response_message = wp_remote_retrieve_response_message( $response );
                $response = new WP_Error( 'http_response_code', sprintf('[%1$s] %2$s',$response_code,$response_message ) );
            }

        }

        $this->response = $response;
        return $this->response;

    }

    private function populate_response_type(){
        
        if ( $this->response_type !== null ) return $this->response_type; //already populated

        $type = null;
        $response = $this->response;
        
        if ( $response && !is_wp_error($response) ){
            $content_type = wp_remote_retrieve_header( $response, 'content-type' );
            
            //JSON
            if ( substr(trim(wp_remote_retrieve_body( $response )), 0, 1) === '{' ){ // is JSON
                $content_type = 'application/json';
            }
            
            //XML to XSPF
            $split = explode('/',$content_type);
            $is_xml = ( ( isset($split[1]) ) && ($split[1]=='xml') );
            
            if ( $is_xml ){
                
                $content = wp_remote_retrieve_body($response);

                //QueryPath
                try{
                    if ( qp( $content, 'playlist trackList track', self::$querypath_options )->length > 0 ){
                        $content_type = sprintf('%s/xspf+xml',$split[0]);
                    }
                }catch(Exception $e){

                }
            }

            //remove charset if any
            $split = explode(';',$content_type);

            if ( !isset($split[0]) ){
                $type = new WP_Error( 'response_type', __('No response type found','wpsstm') );
            }else{
                $type = $split[0];
            }
        }

        $this->response_type = $type;
        return $this->response_type;

    }
    
    protected function populate_response_body(){
        
        if ( $this->response_body !== null ) return $this->response_body; //already populated
        
        $content = null;

        //response
        $response = $this->response;
        if ( is_wp_error($response) ) return $response;
        
        //response type
        $response_type = $this->response_type;
        if ( is_wp_error($response_type) ) return $response_type;

        //response body
        $content = wp_remote_retrieve_body( $response ); 
        if ( is_wp_error($content) ) return $content;
        
        $content = Encoding::fixUTF8($content);//fix mixed encoding //TO FIX TO CHECK at the right place?
        
        $this->response_body = $content;
        return $this->response_body;
    }
    
    protected function populate_body_node(){
        
        if ( $this->body_node !== null ) return $this->body_node; //already populated

        $result = null;

        $response_type = $this->response_type;
        $response_body = $this->response_body;
        
        if ( is_wp_error($response_body) ) return $response_body;

        libxml_use_internal_errors(true); //TO FIX TO CHECK should be in the XML part only ?
        
        switch ($response_type){
                
            case 'application/json':
                
                $xml = null;

                try{
                    $data = json_decode($response_body, true);
                    $dom = WPSSTM_Array2XML::createXML($data,'root','element');
                    $xml = $dom->saveXML($dom);
                    

                }catch(Exception $e){
                    return WP_Error( 'XML2Array', sprintf(__('XML2Array Error [%1$s] : %2$s','wpsstm'),$e->getCode(),$e->getMessage()) );
                }
                
                if ($xml){
                    $this->tracklist_log("The json input has been converted to XML.");
                    
                    //reload this functions with our updated type/body
                    $this->response_type = 'text/xml';
                    $this->response_body = $xml;
                    return $this->populate_body_node();
                }
            break;

            case 'text/xspf+xml':
            case 'application/xspf+xml':
            case 'application/xml':
            case 'text/xml':

                $xml = simplexml_load_string($response_body);
                
                //maybe libxml will output error but will work; do not abord here.
                $xml_errors = libxml_get_errors();
                
                if ($xml_errors){
                    $this->tracklist_log("There has been some errors while parsing the input XML.");
                    
                    /*
                    foreach( $xml_errors as $xml_error_obj ) {
                        $this->tracklist_log(sprintf(__('simplexml Error [%1$s] : %2$s','wpsstm'),$xml_error_obj->code,$xml_error_obj->message) );
                    }
                    */
                }

                //QueryPath
                try{
                    $result = qp( $xml, null, self::$querypath_options );
                }catch(Exception $e){
                    return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','wpsstm'),$e->getCode(),$e->getMessage()) );
                }

            break;

            case 'text/html': 

                //QueryPath
                try{
                    $result = htmlqp( $response_body, null, self::$querypath_options );
                }catch(Exception $e){
                    return WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;
        
            //TO FIX seems to put a wrapper around our content + bad content type
        
            default: //text/plain
                //QueryPath
                try{
                    $result = qp( $response_body, 'body', self::$querypath_options );
                }catch(Exception $e){
                    return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }
                
            break;
        
        }
        
        libxml_clear_errors(); //TO FIX TO CHECK should be in the XML part only ?

        if ( (!$result || ($result->length == 0)) ){
            return new WP_Error( 'querypath', __('We were unable to populate the page node') );
        }
        
        return $this->body_node = $result;

    }
    
    function get_scraper_options($keys=null){

        $options = apply_filters('wpsstm_live_tracklist_scraper_options',$this->scraper_options,$this);
        $default_options = $this->get_default_scraper_options();
        $options = array_replace_recursive($default_options,(array)$options); //last one has priority

        if ($keys){
            return wpsstm_get_array_value($keys, $options);
        }else{
            return $options;
        }
    }
    
    public function get_selectors($keys=null){
        $keys = (array)$keys;
        array_unshift($keys, 'selectors'); //add at beginning
        $selectors = $this->get_scraper_options($keys);
        return $selectors;
    }
    
    /*
    Get the title tag of the page as playlist title.  Could be overriden in presets.
    */
    
    public function get_remote_title(){
        $title = null;
        if ( $selector_title = $this->get_selectors( array('tracklist_title') ) ){
            $title = $this->parse_node($this->body_node,$selector_title);
        }
        return apply_filters('wpsstm_live_tracklist_title',$title,$this);
    }
    
    /*
    Get the playlist author.  Could be overriden in presets.
    */
    
    public function get_remote_author(){
        $author = null;
        return apply_filters('wpsstm_live_tracklist_author',$author,$this);
    }

    protected function get_track_nodes($body_node){

        $selector = $this->get_selectors( array('tracks','path') );
        if (!$selector) return new WP_Error( 'no_track_selector', __('Required tracks selector is missing.','spiff') );

        //QueryPath
        try{
            $track_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }
        
        if ( $track_nodes->length == 0 ){
            return new WP_Error( 'no_track_nodes', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist.','spiff') );
        }

        return $track_nodes;

    }

    protected function parse_track_nodes($track_nodes){

        $selector_artist = $this->get_selectors( array('track_artist') );
        if (!$selector_artist) return new WP_Error( 'no_track_selector', __('Required track artist selector is missing.','wpsstm') );
        
        $selector_title = $this->get_selectors( array('track_title') );
        if (!$selector_title) return new WP_Error( 'no_track_selector', __('Required track title selector is missing.','wpsstm') );

        $tracks_arr = array();
        
        foreach($track_nodes as $key=>$single_track_node) {

            //format response
            $artist =   $this->get_track_artist($single_track_node);
            $title =    $this->get_track_title($single_track_node);
            $album =    $this->get_track_album($single_track_node);

            $args = array(
                'artist'        => $artist,
                'title'         => $title,
                'album'         => $album,
                'image_url'     => $this->get_track_image($single_track_node),
                'source_urls'   => $this->get_track_sources($single_track_node),
            );

            $tracks_arr[] = array_filter($args);

        }

        return $tracks_arr;

    }
    
    protected function get_track_artist($track_node){
        $selectors = $this->get_selectors( array('track_artist'));
        $artist = $this->parse_node($track_node,$selectors);
        return apply_filters('wpsstm_live_tracklist_track_artist',$artist,$track_node,$this);
    }
    
    protected function get_track_title($track_node){
        $selectors = $this->get_selectors( array('track_title'));
        $title = $this->parse_node($track_node,$selectors);
        return apply_filters('wpsstm_live_tracklist_track_title',$title,$track_node,$this);
    }
    
    protected function get_track_album($track_node){
        $selectors = $this->get_selectors( array('track_album'));
        $album = $this->parse_node($track_node,$selectors);
        return apply_filters('wpsstm_live_tracklist_track_album',$album,$track_node,$this);
    }
    
    protected function get_track_image($track_node){
        $selectors = $this->get_selectors( array('track_image'));
        $image = $this->parse_node($track_node,$selectors);
        $image = apply_filters('wpsstm_live_tracklist_track_image',$image,$track_node,$this);
        
        if (filter_var((string)$image, FILTER_VALIDATE_URL) === false) return false;
        
        return $image;
    }
    
    protected function get_track_source_urls($track_node){
        $selectors = $this->get_selectors( array('track_source_urls'));
        $source_urls = $this->parse_node($track_node,$selectors,false);
        $source_urls = apply_filters('wpsstm_live_tracklist_source_urls',$source_urls,$track_node,$this);

        foreach ((array)$source_urls as $key=>$url){
            if (filter_var((string)$url, FILTER_VALIDATE_URL) === false) {
                unset($source_urls[$key]);
            }
        }

        return $source_urls;
        
    }
    
    protected function get_track_sources($track_node){
        $sources = array();
        $source_urls = $this->get_track_source_urls($track_node);
        
        foreach((array)$source_urls as $source_url){
            $source = new WPSSTM_Source();
            $source_args = array('url'=>$source_url);
            $source->from_array($source_args);
            $sources[] = $source;
        }
        
        return $sources;
    }

    public function parse_node($track_node,$selectors,$single_value=true){
        $pattern = null;
        $strings = array();
        $result = array();

        $selector_css   = wpsstm_get_array_value('path',$selectors);
        $selector_regex = wpsstm_get_array_value('regex',$selectors);
        $selector_attr  = wpsstm_get_array_value('attr',$selectors);

        //abord
        if ( !$selector_css && !$selector_regex && !$selector_attr ){
            return false;
        }

        //QueryPath
        try{

            if ($selector_css){
                $nodes = $track_node->find($selector_css);
            }else{
                $nodes = $track_node;
            }

            //get the first tag found only
            if ($single_value){
                $nodes = $nodes->eq(0);
            }

            foreach ($nodes as $node){
                if ($selector_attr){
                    $strings[] = $node->attr($selector_attr);
                }else{
                    $strings[] = $node->innerHTML();
                }
            }

        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }

        foreach($strings as $key=>$string){
            
            if (!$string = trim($string)) continue;

            //CDATA fix
            $string = $this->sanitize_cdata_string($string);
            
            //regex pattern
            if ( $selector_regex ){
                $pattern = $selector_regex;
            }

            if($pattern) {

                $pattern = sprintf('~%s~m',$pattern);
                preg_match($pattern, $string, $matches);
                
                $matches = array_filter($matches);
                $matches = array_values($matches);

                if (isset($matches[1])){
                    $string = strip_tags($matches[1]);
                }

            }

            $result[] = $this->sanitize_remote_string($string);
            
        }
        
        if ($result){
            if ($single_value){
                return $result[0];
            }else{
                return $result;
            }
            
        }
        
    }
    
    protected function sanitize_cdata_string($string){
        $string = str_replace("//<![CDATA[","",$string);
        $string = str_replace("//]]>","",$string);

        $string = str_replace("<![CDATA[","",$string);
        $string = str_replace("]]>","",$string);

        return trim($string);
    }
    
    function sanitize_remote_string($string){
        //sanitize result
        $string = strip_tags($string);
        $string = urldecode($string);
        $string = htmlspecialchars_decode($string);
        $string = trim($string);
        return $string;
    }

    function convert_to_static_playlist(){
        
        if ( get_post_type($this->post_id) != wpsstm()->post_type_live_playlist ){
            return new WP_Error( 'wpsstm_wrong_post_type', __("This is not a live tracklist.",'wpsstm') );
        }

        //capability check
        if ( !$this->user_can_lock_tracklist() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }

        //force PHP tracks refresh
        $this->options['ajax_tracklist'] = false;
        
        //populate remote tracklist if not done yet
        $populated = $this->populate_subtracks();
        
        $updated = $this->update_live_tracklist(true);

        if ( is_wp_error($updated) ){
            return $updated;
        }
        
        $args = array(
            'ID'            => $this->post_id,
            'post_title'    => $this->title,
            'post_type'     => wpsstm()->post_type_playlist,
        );

        $success = wp_update_post( $args, true );

        if ( is_wp_error($success) ) {
            return new WP_Error( 'wpsstm_convert_to_static', __("Error while converting the live tracklist status",'wpsstm') );
        }
        return $success;

    }

    function save_feed_url(){

        if (!$this->feed_url){
            return delete_post_meta( $this->post_id, WPSSTM_Core_Live_Playlists::$feed_url_meta_name );
        }else{
            return update_post_meta( $this->post_id, WPSSTM_Core_Live_Playlists::$feed_url_meta_name, $this->feed_url );
        }
    }
    
    function save_wizard($wizard_data = null){
        
        $post_type = get_post_type($this->post_id);

        if( !in_array($post_type,wpsstm()->tracklist_post_types ) ) return;
        if (!$wizard_data) return;

        return $this->save_wizard_settings($wizard_data);

    }
    
    function save_wizard_settings($wizard_settings){

        if ( !$wizard_settings ) return;
        if ( !$this->post_id ) return;

        $default_settings = $this->get_default_scraper_options();
        $old_settings = get_post_meta($this->post_id, WPSSTM_Core_Live_Playlists::$scraper_meta_name,true);
        
        $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

        //remove all default values so we store only user-edited stuff
        $wizard_settings = wpsstm_array_recursive_diff($wizard_settings,$default_settings);
        
        //settings have been updated, clear tracklist cache
        if ($old_settings != $wizard_settings){
            $this->tracklist_log('scraper settings have been updated, clear tracklist cache','WPSSTM_Remote_Tracklist::save_wizard_settings' );
            delete_post_meta($this->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name);
        }

        if (!$wizard_settings){
            delete_post_meta($this->post_id, WPSSTM_Core_Live_Playlists::$scraper_meta_name);
        }else{
            update_post_meta($this->post_id, WPSSTM_Core_Live_Playlists::$scraper_meta_name, $wizard_settings);
        }

    }
    
    /*
     * Sanitize wizard data
     */
    
    function sanitize_wizard_settings($input){

        $new_input = array();

        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //cache
        if ( isset($input['datas_cache_min']) && ctype_digit($input['datas_cache_min']) ){
            $new_input['datas_cache_min'] = $input['datas_cache_min'];
        }

        //selectors 
        if ( isset($input['selectors']) && !empty($input['selectors']) ){
            
            foreach ($input['selectors'] as $selector_slug=>$value){

                //path
                if ( isset($value['path']) ) {
                    $value['path'] = trim($value['path']);
                }

                //attr
                if ( isset($value['attr']) ) {
                    $value['attr'] = trim($value['attr']);
                }

                //regex
                if ( isset($value['regex']) ) {
                    $value['regex'] = trim($value['regex']);
                }

                $new_input['selectors'][$selector_slug] = array_filter($value);

            }
        }

        //order
        if ( isset($input['tracks_order']) ){
            $new_input['tracks_order'] = $input['tracks_order'];
        }

        $default_args = $this->get_default_scraper_options();
        $new_input = array_replace_recursive($default_args,$new_input); //last one has priority

        return $new_input;
    }
    
    //UTC
    function get_expiration_time(){
        $cache_seconds = ( $cache_min = $this->get_scraper_options('datas_cache_min') ) ? $cache_min * MINUTE_IN_SECONDS : false;
        return $this->updated_time + $cache_seconds;
    }

    
    // checks if the playlist has expired (and thus should be refreshed)
    // set 'expiration_time'
    private function check_has_expired(){
        
        $cache_duration_min = $this->get_scraper_options('datas_cache_min');
        $has_cache = (bool)$cache_duration_min;
        
        if (!$has_cache){
            return true;
        }else{
            $now = current_time( 'timestamp', true );
            $expiration_time = $this->get_expiration_time(); //set expiration time
            return ( $now >= $expiration_time );
        }

    }
    
    function get_time_before_refresh(){

        $cache_seconds = ( $cache_min = $this->get_scraper_options('datas_cache_min') ) ? $cache_min * MINUTE_IN_SECONDS : false;
        
        if ( !$cache_seconds ) return false;
        if ( $this->is_expired ) return false;
        
        $time_refreshed = $this->updated_time;
        $time_before = $time_refreshed + $cache_seconds;
        $now = current_time( 'timestamp', true );

        return $refresh_time_human = human_time_diff( $now, $time_before );
    }

    function get_tracklist_attr($values_attr=null){
        
        $values_default = array(
            'data-wpsstm-domain' => wpsstm_get_url_domain( $this->feed_url )
        );

        $values_attr = array_merge($values_default,(array)$values_attr);

        return parent::get_tracklist_attr($values_attr);
    }
    
    function get_html_metas(){
        $metas = parent::get_html_metas();
        
        /*
        expiration time
        */
        //if no real cache is set; let's say tracklist is already expired at load!
        $expiration_time = ($expiration = $this->get_expiration_time() ) ? $expiration : current_time( 'timestamp', true );
        
        $metas['wpsstmExpiration'] = $expiration_time;
        
        return $metas;
    }
    

    
}