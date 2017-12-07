<?php

use \ForceUTF8\Encoding;

class WP_SoundSystem_Remote_Tracklist extends WP_SoundSystem_Tracklist{
    
    var $tracklist_type = 'live';

    //url stuff
    public $feed_url = null;
    public $redirect_url = null;
    var $scraper_options = array();
    
    public $is_expired = true; //if option 'datas_cache_min' is defined; we'll compare the current time to check if the tracklist is expired or not with check_has_expired()

    //response
    var $request_pagination = array(
        'total_pages'       => 1,
        'page_items_limit'  => -1, //When possible (eg. APIs), set the limit of tracks each request can get
        'current_page'      => 1
    );
    public $response = null;
    public $response_type = null;
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

            $this->feed_url = wpsstm_get_live_tracklist_url($this->post_id);
            
            $db_options = get_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name,true);
            $this->scraper_options = array_replace_recursive($this->scraper_options,(array)$db_options); //last one has priority

            if ($this->feed_url){
                $this->location = $this->feed_url;
            }
            
            if ( $meta = get_post_meta($this->post_id,wpsstm_live_playlists()->time_updated_meta_name,true) ){
                $this->updated_time = $meta;
            }

        }
        
        //set expiration bool & time
        $this->is_expired = $this->check_has_expired();
        $this->ajax_refresh = $this->is_expired; //should we load the subtracks through ajax ?   By default, only when tracklist is not cached. false = good for debug.
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
    
    function populate_tracks($args = null){

        if ( $this->did_query_tracks || $this->wait_for_ajax() || !$this->is_expired ){
            return parent::populate_tracks($args);
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
        $updated = $this->update_live_tracklist();
        if ( is_wp_error($updated) ) return $updated;
        
        //repopulate tracklist post to retrieve the updated datas
        $this->__construct($this->post_id);

        return true;
    }

    /*
    Update WP post and eventually update subtracks.
    */
    
    function update_live_tracklist($save_subtracks = null){

        if (!$this->post_id){
            wpsstm()->debug_log('wpsstm_missing_post_id','WP_SoundSystem_Remote_Tracklist::update_live_tracklist' );
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        //capability check
        if ( !wpsstm_live_playlists()->can_live_playlists() ){
            wpsstm()->debug_log('wpsstm_missing_cap','WP_SoundSystem_Remote_Tracklist::update_live_tracklist' );
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        new WP_SoundSystem_Live_Playlist_Stats($this); //remote request stats
        
        $this->updated_time = current_time( 'timestamp', true );//set the time tracklist has been updated

        $meta_input = array(
            wpsstm_live_playlists()->remote_title_meta_name =>  $this->get_remote_title(), //remote title
            wpsstm_live_playlists()->remote_author_meta_name => $this->get_remote_author(), //remote author
            wpsstm_live_playlists()->time_updated_meta_name =>  $this->updated_time,
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
                wpsstm()->debug_log($success->get_error_code(),'WP_SoundSystem_Remote_Tracklist::update_live_tracklist' );
                return $success;
            }

        }
        
        //update tracklist post
        $tracklist_post = array(
            'ID' =>         $this->post_id,
            'meta_input' => $meta_input,
        );

        $success = wp_update_post( $tracklist_post );
        if( is_wp_error($success) ){
            wpsstm()->debug_log($success->get_error_code(),'WP_SoundSystem_Remote_Tracklist::update_live_tracklist' );
            return $success;
        }
        
        return $this->post_id;
    }

    protected function get_remote_tracks(){

        $raw_tracks = array();
        
        do_action('wpsstm_get_remote_tracks',$this);

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
        
        

        $body_node = $this->get_body_node();
        if ( is_wp_error($body_node) ){
            wpsstm()->debug_log($body_node->get_error_message(),'get_remote_page_tracks' );
            return $body_node;
        }
        
        wpsstm()->debug_log(json_encode($this->request_pagination),'get_remote_page_tracks request_pagination' );

        $this->body_node = $body_node;
        
        do_action('wpsstm_after_get_remote_body',$this);

        //tracks HTML
        $track_nodes = $this->get_track_nodes($this->body_node);
        if ( is_wp_error($track_nodes) ) return $track_nodes;
        $this->track_nodes = $track_nodes;

        //tracks
        $tracks = $this->parse_track_nodes($track_nodes);
        
        wpsstm()->debug_log(count($tracks),'get_remote_page_tracks request_url - found track nodes' );

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
    
    protected function get_remote_response(){

        if( $this->response !== null ) return $this->response; //already populated

        $response = null;

        $this->redirect_url = apply_filters('wpsstm_live_tracklist_url',$this->feed_url); //override in your preset if you need to add args, etc. (eg. API) - in the URL to reach
        wpsstm()->debug_log($this->redirect_url,'get_remote_response url' );

        if ( is_wp_error($this->redirect_url) ){
            $response = $this->redirect_url;
        }else{

            $response = wp_remote_get( $this->redirect_url, $this->get_request_args() );

            if ( !is_wp_error($response) ){

                $response_code = wp_remote_retrieve_response_code( $response );

                if ($response_code && $response_code != 200){
                    $response_message = wp_remote_retrieve_response_message( $response );
                    $response = new WP_Error( 'http_response_code', sprintf('[%1$s] %2$s',$response_code,$response_message ) );
                }

            }
        }

        $this->response = $response;
        return $this->response;

    }

    public function get_response_type(){
        
        if ( $this->response_type !== null ) return $this->response_type; //already populated
        if ( $this->response === null ) return; //response not yet populated

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
    
    protected function get_body_node(){

        $result = null;

        //response
        $response = $this->get_remote_response();
        if ( is_wp_error($response) ) return $response;
        
        //response type
        $response_type = $this->get_response_type();
        if ( is_wp_error($response_type) ) return $response_type;

        //response body
        $content = wp_remote_retrieve_body( $response ); 
        if ( is_wp_error($content) ) return $content;

        $content = Encoding::fixUTF8($content);//fix mixed encoding //TO FIX TO CHECK at the right place?

        libxml_use_internal_errors(true); //TO FIX TO CHECK should be in the XML part only ?
        
        switch ($response_type){
                
            case 'application/json':
                
                $xml = null;

                try{
                    $data = json_decode($content, true);
                    $dom = WP_SoundSystem_Array2XML::createXML($data,'root','element');
                    $xml = $dom->saveXML($dom);
                    

                }catch(Exception $e){
                    return WP_Error( 'XML2Array', sprintf(__('XML2Array Error [%1$s] : %2$s','wpsstm'),$e->getCode(),$e->getMessage()) );
                }
                
                if ($xml){
                    wpsstm()->debug_log("The json input has been converted to XML.",'WP_SoundSystem_Remote_Tracklist::get_body_node' );
                    $this->response_type = 'text/xml';
                    $content = $xml;
                }
            
            //no break here!
            
            case 'text/xspf+xml':
            case 'application/xspf+xml':
            case 'application/xml':
            case 'text/xml':

                $xml = simplexml_load_string($content);
                
                //maybe libxml will output error but will work; do not abord here.
                $xml_errors = libxml_get_errors();
                
                if ($xml_errors){
                    $notice = __("There has been some errors while parsing the input XML.",'wpsstm');
                    $this->add_notice( 'wizard-header', 'xml_errors', $notice, true );
                    wpsstm()->debug_log($notice,'WP_SoundSystem_Remote_Tracklist::get_body_node' );
                    
                    /*
                    foreach( $xml_errors as $xml_error_obj ) {
                        wpsstm()->debug_log(sprintf(__('simplexml Error [%1$s] : %2$s','wpsstm'),$xml_error_obj->code,$xml_error_obj->message),'WP_SoundSystem_Remote_Tracklist::get_body_node' );
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
                    $result = htmlqp( $content, null, self::$querypath_options );
                }catch(Exception $e){
                    return WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;
        
            //TO FIX seems to put a wrapper around our content + bad content type
        
            default: //text/plain
                //QueryPath
                try{
                    $result = qp( $content, 'body', self::$querypath_options );
                }catch(Exception $e){
                    return WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }
                
            break;
        
        }
        
        libxml_clear_errors(); //TO FIX TO CHECK should be in the XML part only ?

        if ( (!$result || ($result->length == 0)) ){
            return new WP_Error( 'querypath', __('We were unable to populate the page node') );
        }

        return $result;

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
            $source = new WP_SoundSystem_Source();
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
        $this->ajax_refresh = false;
        
        //populate remote tracklist if not done yet
        $populated = $this->populate_tracks();
        $updated = $this->update_live_tracklist(true);

        if ( is_wp_error($updated) ){
            return $updated;
        }
        
        $moved_tracks = $this->move_live_tracks();
        
        if ( is_wp_error($moved_tracks) ){
            return $moved_tracks;
        }

        $args = array(
            'ID'            => $this->post_id,
            'post_title'    => $this->title,
            'post_type'     => wpsstm()->post_type_playlist,
        );

        $updated = wp_update_post( $args );

        if ( !$updated ) {
            return new WP_Error( 'wpsstm_convert_to_static', __("Error while converting the live tracklist status",'wpsstm') );
        }
        
        $this->toggle_enable_wizard(false);
        return $updated;

    }

    function save_feed_url($feed_url){

        //save feed url
        $feed_url = trim($feed_url);

        if (!$feed_url){
            return delete_post_meta( $this->post_id, wpsstm_live_playlists()->feed_url_meta_name );
        }else{
            return update_post_meta( $this->post_id, wpsstm_live_playlists()->feed_url_meta_name, $feed_url );
        }
    }
    
    function save_wizard($wizard_data = null){
        
        $post_type = get_post_type($this->post_id);

        if( !in_array($post_type,wpsstm_tracklists()->tracklist_post_types ) ) return;
        if (!$wizard_data) return;

        $disable = (bool)isset($wizard_data['disable']);
        $this->toggle_enable_wizard(!$disable);

        //is disabled
        if ( $this->is_wizard_disabled() ) return;

        return $this->save_wizard_settings($wizard_data);

    }
    
    function save_wizard_settings($wizard_settings){

        if ( !$wizard_settings ) return;
        if ( !$this->post_id ) return;

        $default_settings = $this->get_default_scraper_options();
        $old_settings = get_post_meta($this->post_id, wpsstm_live_playlists()->scraper_meta_name,true);
        
        $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

        //remove all default values so we store only user-edited stuff
        $wizard_settings = wpsstm_array_recursive_diff($wizard_settings,$default_settings);
        
        //settings have been updated, clear tracklist cache
        if ($old_settings != $wizard_settings){
            wpsstm()->debug_log('scraper settings have been updated, clear tracklist cache','WP_SoundSystem_Remote_Tracklist::save_wizard_settings' );
            delete_post_meta($this->post_id,wpsstm_live_playlists()->time_updated_meta_name);
        }

        if (!$wizard_settings){
            delete_post_meta($this->post_id, wpsstm_live_playlists()->scraper_meta_name);
        }else{
            update_post_meta($this->post_id, wpsstm_live_playlists()->scraper_meta_name, $wizard_settings);
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
        if ( !$cache_duration_min = $this->get_scraper_options('datas_cache_min') ) return false;
        $cache_duration_s = $cache_duration_min * MINUTE_IN_SECONDS;
        return $this->updated_time + $cache_duration_s;
    }

    
    // checks if the playlist has expired (and thus should be refreshed)
    // set 'expiration_time'
    function check_has_expired(){
        
        $cache_duration_min = $this->get_scraper_options('datas_cache_min');
        $has_cache = (bool)$cache_duration_min;
        
        if (!$has_cache){
            return true;
        }else{
            $now = current_time( 'timestamp', true );
            $cache_duration_s = $cache_duration_min * MINUTE_IN_SECONDS;
            $expiration_time = $this->get_expiration_time(); //set expiration time
            return ( $now >= $expiration_time );
        }

    }
    
    function get_time_before_refresh(){

        $cachemin = $this->get_scraper_options('datas_cache_min');
        
        if( !$cachemin ) return false;
        
        $time_refreshed = $this->updated_time;
        $time_before = $time_refreshed + ($cachemin * MINUTE_IN_SECONDS);
        $now = current_time( 'timestamp', true );

        return $refresh_time_human = human_time_diff( $now, $time_before );
    }

    function get_tracklist_class($extra_classes=null){
        
        $defaults = array(
            'wpsstm-live-tracklist'
        );

        $classes = array_merge($defaults,(array)$extra_classes);

        return parent::get_tracklist_class($classes);
    }

    function get_tracklist_attr($values_attr=null){
        
        $values_default = array();
        
        $expiration_time = $this->get_expiration_time();
        
        if (!$expiration_time){ 
            //no real cache is set; so let's say tracklist is already expired at load!
            $expiration_time = current_time( 'timestamp', true );
        }
        
        if ( $expiration_time ){
            $values_default['data-wpsstm-expire-time'] = $expiration_time;
        }
        
        $values_default['data-wpsstm-domain'] = wpsstm_get_url_domain( $this->feed_url );

        $values_attr = array_merge($values_default,(array)$values_attr);

        return parent::get_tracklist_attr($values_attr);
    }
    
}