<?php

use \ForceUTF8\Encoding;

class WP_SoundSystem_Remote_Tracklist extends WP_SoundSystem_Tracklist{
    
    var $tracklist_type = 'live';
    
    //preset infos
    var $preset_slug = 'default';
    var $preset_name = null;

    //url stuff
    var $pattern = null; //pattern used to check if the scraper URL matches the preset.
    var $variables = array(); //list of variables that matches the regex groups from $pattern
    var $redirect_url = null; //if needed, a redirect URL.  Can use variables extracted from the pattern using the %variable% format.
    
    public $feed_url = null;
    
    public $is_expired = true; //if option 'datas_cache_min' is defined; we'll compare the current time to check if the tracklist is expired or not with check_has_expired()
    public $ajax_refresh = true; //by default, only ajax requests will fetch remote tracks. Set to false to request remote tracks through PHP.

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
        
        $this->options = $this->options_default = $this->get_default_options();

        if ($this->post_id){

            $this->feed_url = wpsstm_get_live_tracklist_url($this->post_id);

            if ( $options = get_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name ,true) ){
                $this->options = array_replace_recursive((array)$this->options_default,(array)$options); //last one has priority
            }

            if ($this->feed_url){
                $this->location = $this->feed_url;
            }

            //set remote title if no title already set
            if ( !$this->title && ($meta_title = $this->get_cached_remote_title() ) ){
                $this->title = $meta_title;
            }
            
            //author
            /*
            if ($meta_author = $this->get_cached_remote_author() ){
                $this->author = $meta_author;
            }
            */

        }

        $this->is_expired = $this->check_has_expired(); //set expiration bool & time

    }

    protected function get_default_options(){
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
        
        return array_replace_recursive((array)parent::get_default_options(),$live_options); //last one has priority
        
    }
    
    /*
    Compare Tracks / Tracks Details wizard options to check if the user settings match the default preset settings.
    */
    function get_user_edited_scraper_options(){

        $default_options = $this->options_default;
        $options = $this->options;

        //compare multi-dimensionnal array
        $diff = wpsstm_array_recursive_diff($options,$default_options);
        
        //keep only scraper options
        $check_keys = array('selectors', 'tracks_order');
        $diff = array_intersect_key($diff, array_flip($check_keys));

        return $diff;
    }

    function populate_remote_tracklist(){
        
        if ($this->did_query_tracks) return; //we already did it

        $now = current_time( 'timestamp', true );
        $this->updated_time = $now;

        //get remote stuff
        $raw_tracks = $this->get_all_raw_tracks();

        if ( !$raw_tracks ){
            $raw_tracks = new WP_Error( 'remote-tracks',__('No remote tracks found','wpsstm'),true );
        }
        
        if ( is_wp_error($raw_tracks) ) {
            return new WP_Error( 'remote-tracks', $raw_tracks->get_error_message(),true );
        }

        //sort
        if ($this->get_options('tracks_order') == 'asc'){
            $raw_tracks = array_reverse($raw_tracks);
        }
        
        //reset tracks
        $this->tracks = array();
        $this->add_tracks($raw_tracks);

        //set tracklist title
        $remote_title = $this->get_tracklist_title(); //TO FIX force bad encoding (eg. last.fm)
        $this->title = ($remote_title) ? $remote_title : __('Tracklist Wizard','wpsstm');

        //set tracklist author
        $remote_author = $this->get_tracklist_author(); //TO FIX force bad encoding (eg. last.fm)
        $this->author = ($remote_author) ? $remote_author : __('Wizard','wpsstm'); //TO FIX community user name ?

        $this->expired = $this->check_has_expired(); //set expiration bool & time
        
        $this->did_query_tracks = true; //so we don't run this function multiple times
    }
    
    /*
    Populate remote tracklist, save WP post, flush old subtracks and update subtracks IDs.
    */
    
    function populate_and_save_tracks(){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        //capability check
        if ( !wpsstm_live_playlists()->can_live_playlists() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }

        //populate remote tracklist if not done yet
        $populated = $this->populate_remote_tracklist();

        if ( is_wp_error($populated) ) {
            return $populated;
        }
        
        new WP_SoundSystem_Live_Playlist_Stats($this); //remote request stats

        //update tracklist post (remote title, etc.)
        $tracklist_post = array(
            'ID'    => $this->post_id,
            'meta_input'    => array(
                wpsstm_live_playlists()->remote_title_meta_name => $this->title,
                wpsstm_live_playlists()->remote_author_meta_name => $this->author,
            )
        );
        
        $success = wp_update_post( $tracklist_post );
        if( is_wp_error($success) ) return $success;

        //save new subtracks as community tracks
        $subtracks_args = array(
            'post_author'   => wpsstm()->get_options('community_user_id'),
        );

        $new_ids = $this->save_new_subtracks($subtracks_args);
        if( is_wp_error($new_ids) ) return $new_ids;
        
        $this->flush_update_subtrack_ids($new_ids);

        wpsstm()->debug_log(json_encode(array('post_id'=>$this->post_id,'remote_tracks_count'=>count($this->tracks))),'WP_SoundSystem_Remote_Tracklist::populate_and_save_tracks()' );

        return true;
    }
    
    function flush_update_subtrack_ids($new_ids){

        $existing_ids = array(); //array of existing posts IDs
        $subtrack_ids = array();
        foreach((array)$this->tracks as $track){
            $subtrack_ids[] = $track->post_id;
            if ( $track->post_id ){
                $existing_ids[] = $track->post_id;
            }
        }

        //flush orphan subtracks that do not belong to the current tracklist
        $flushed_ids = $this->flush_subtracks($existing_ids);

        //set new subtracks
        $this->set_subtrack_ids($subtrack_ids);

        wpsstm()->debug_log( json_encode(array('tracklist_id'=>$this->post_id,'current_ids'=>$existing_ids,'new_ids'=>$new_ids,'flushed_ids'=>$flushed_ids)), "WP_SoundSystem_Core_Live_Playlists::update_live_playlist()");
    }

    public function get_all_raw_tracks(){

        $raw_tracks = array();

        while ($this->request_pagination['current_page'] <= $this->request_pagination['total_pages']) {
            if ( $page_raw_tracks = $this->get_page_raw_tracks() ) {
                if ( is_wp_error($page_raw_tracks) ) return $page_raw_tracks;
                $raw_tracks = array_merge($raw_tracks,(array)$page_raw_tracks);
                
            }
            $this->request_pagination['current_page']++;
        }
        
        return $raw_tracks;
        
    }

    private function get_page_raw_tracks(){

        wpsstm()->debug_log(json_encode($this->request_pagination),'get_page_raw_tracks() request_pagination' );

        //url
        $url = $this->redirect_url = $this->get_request_url();

        if ( is_wp_error($url) ) return $url;
        
        wpsstm()->debug_log($url,'get_page_raw_tracks() request_url' );

        //response
        $response = $this->get_remote_response($url);
        if ( is_wp_error($response) ) return $response;
        $this->response = $response;

        //response type
        $response_type = $this->get_response_type($this->response);
        if ( is_wp_error($response_type) ) return $response_type;
        $this->response_type = $response_type;

        //response body
        $content = wp_remote_retrieve_body( $this->response ); 
        if ( is_wp_error($content) ) return $content;
        
        //fixes mixed encoding
        $content = Encoding::fixUTF8($content);

        $body_node = $this->get_body_node($content);
        if ( is_wp_error($body_node) ) return $body_node;
        $this->body_node = $body_node;

        //tracks HTML
        $track_nodes = $this->get_track_nodes($this->body_node);
        if ( is_wp_error($track_nodes) ) return $track_nodes;
        $this->track_nodes = $track_nodes;

        //tracks
        $tracks = $this->parse_track_nodes($track_nodes);

        return $tracks;
    }

    protected function get_request_url(){
        
        $domain = wpsstm_get_url_domain($this->feed_url);

        //dropbox : convert to raw link
        if ($domain=='dropbox'){
            $url_no_args = strtok($this->feed_url, '?');
            $this->redirect_url = add_query_arg(array('raw'=>1),$url_no_args); //http://stackoverflow.com/a/11846251/782013
        }

        if ($this->redirect_url){
            return $this->redirect_url;
        }else{
            return $this->feed_url;
        }

    }
    
    /*
    Arguments for the remote request.  (Could be overriden for presets).
    https://codex.wordpress.org/Function_Reference/wp_remote_get
    */
    
    protected function get_request_args(){
        return array(
            'headers'   => array(
                'User-Agent'        => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36'
            )
        );
    }
    
    protected function get_remote_response($url){

        $error = $remote_body = $source_content = null;

        $response = wp_remote_get( $url, $this->get_request_args() );

        if ( !is_wp_error($response) ){

            $response_code = wp_remote_retrieve_response_code( $response );

            if ($response_code && $response_code != 200){
                $response_message = wp_remote_retrieve_response_message( $response );
                return new WP_Error( 'http_response_code', sprintf('[%1$s] %2$s',$response_code,$response_message ) );
            }else{ //ok
                return $response;
            }
            
        }else{
            return $response;
        }

    }

    protected function get_response_type($response){

        $type = wp_remote_retrieve_header( $response, 'content-type' );

        //is JSON
        if ( substr(trim(wp_remote_retrieve_body( $response )), 0, 1) === '{' ){ // is JSON
            $type = 'application/json';
        }

        //remove charset if any
        $split = explode(';',$type);

        if ( !isset($split[0]) ){
            return new WP_Error( 'response_type', __('No response type found','wpsstm') );
        }
        
        return $split[0];

    }
    
    protected function get_body_node($content){

        $result = null;

        libxml_use_internal_errors(true);

        switch ($this->response_type){
            
            case 'text/xspf+xml':
            case 'application/xspf+xml':
                $xspf_options = array(
                    'selectors' => array(
                        'tracklist_title'   => array('path'=>'title'),
                        'tracks'            => array('path'=>'trackList track'),
                        'track_artist'      => array('path'=>'creator'),
                        'track_title'       => array('path'=>'title'),
                        'track_album'       => array('path'=>'album'),
                        'track_source_urls' => array('path'=>'location'),
                        'track_image'       => array('path'=>'image')
                    )
                );

                $this->options = array_replace_recursive($this->options, $xspf_options);
            
            case 'application/xml':
            case 'text/xml':
                
                //check for XSPF
                if ($this->response_type=='application/xml' || $this->response_type=='text/xml'){
                    
                    $is_xspf = false;
                    
                    //QueryPath
                    try{
                        if ( qp( $content, 'playlist trackList track', self::$querypath_options )->length > 0 ){
                            $is_xspf = true;
                        }
                    }catch(Exception $e){}
                    
                    if ($is_xspf){
                        $this->response_type = 'text/xspf+xml';
                        $this->get_body_node($content);
                    }
                }

                $xml = simplexml_load_string($content);
                
                //maybe libxml will output error but will work; do not abord here.
                $xml_errors = libxml_get_errors();
                
                if ($xml_errors){
                    $notice = __("There has been some errors while parsing the input XML.",'wpsstm');
                    $this->add_notice( 'wizard-header', 'xml_errors', $notice, true );
                    wpsstm()->debug_log($notice,'WP_SoundSystem_Remote_Tracklist::get_body_node()' );
                    
                    /*
                    foreach( $xml_errors as $xml_error_obj ) {
                        wpsstm()->debug_log(sprintf(__('simplexml Error [%1$s] : %2$s','wpsstm'),$xml_error_obj->code,$xml_error_obj->message),'WP_SoundSystem_Remote_Tracklist::get_body_node()' );
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
                    
                    //log
                    wpsstm()->debug_log("The json input has been converted to XML.",'WP_SoundSystem_Remote_Tracklist::get_body_node()' );

                    $this->response_type = 'text/xml';
                    return $this->get_body_node($xml);
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
        
        libxml_clear_errors();

        if ( (!$result || ($result->length == 0)) ){
            return new WP_Error( 'querypath', __('We were unable to populate the page node') );
        }

        return $result;

    }
    
    /*
    Get the title tag of the page as playlist title.  Could be overriden in presets.
    */
    
    public function get_tracklist_title(){

        if ( !$selector_title = $this->get_options( array('selectors','tracklist_title', 'path') ) ) return;
        
        $title = null;

        //QueryPath
        try{
            $title_node = qp( $this->body_node, null, self::$querypath_options )->find($selector_title);
            $title = $title_node->innerHTML();
        }catch(Exception $e){
            return;
        }
        
        return $title;
    }
    
    /*
    Get the playlist author.  Could be overriden in presets.
    */
    
    public function get_tracklist_author(){

    }

    protected function get_track_nodes($body_node){

        $selector = $this->get_options( array('selectors','tracks','path') );
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

        $selector_artist = $this->get_options( array('selectors','track_artist') );
        if (!$selector_artist) return new WP_Error( 'no_track_selector', __('Required track artist selector is missing.','wpsstm') );
        
        $selector_title = $this->get_options( array('selectors','track_title') );
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
        $selectors = $this->get_options(array('selectors','track_artist'));
        return $this->get_track_node_content($track_node,$selectors);
    }
    
    protected function get_track_title($track_node){
        $selectors = $this->get_options(array('selectors','track_title'));
        return $this->get_track_node_content($track_node,$selectors);
    }
    
    protected function get_track_album($track_node){
        $selectors = $this->get_options(array('selectors','track_album'));
        return $this->get_track_node_content($track_node,$selectors);
    }
    
    protected function get_track_image($track_node){
        $selectors = $this->get_options(array('selectors','track_image'));
        $image = $this->get_track_node_content($track_node,$selectors);
        
        if (filter_var((string)$image, FILTER_VALIDATE_URL) === false) return false;
        
        return $image;
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
    
    protected function get_track_source_urls($track_node){
        $selectors = $this->get_options(array('selectors','track_source_urls'));
        $source_urls = $this->get_track_node_content($track_node,$selectors,false);

        foreach ((array)$source_urls as $key=>$url){
            if (filter_var((string)$url, FILTER_VALIDATE_URL) === false) {
                unset($source_urls[$key]);
            }
        }

        return $source_urls;
        
    }

    protected function get_track_node_content($track_node,$selectors,$single_value=true){
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
            
            //sanitize result
            $string = strip_tags($string);
            $string = urldecode($string);
            $string = htmlspecialchars_decode($string);
            $string = trim($string);
            
            $result[] = $string;
            
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

    public function set_request_pagination( $args ) {

        $args = wp_parse_args( $args, $this->request_pagination );

        if ( $args['page_items_limit'] > 0 ){
            $args['total_pages'] = ceil( $this->track_count / $args['page_items_limit'] );
        }

        $this->request_pagination = $args;
    }
    
    function convert_to_static_playlist(){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Required tracklist ID missing.','wpsstm') );
        }
        
        if ($this->is_community){
            $got_autorship = $this->get_autorship();
            if ( is_wp_error($got_autorship) ) return $got_autorship;
        }
        
        $updated = $this->populate_and_save_tracks();

        if ( is_wp_error($updated) ) return $updated;
        
        $moved_tracks = $this->move_live_tracks();
        if ( is_wp_error($moved_tracks) ) return $moved_tracks;
        
        $current_post_type = get_post_type($this->post_id);
        
        if ( $current_post_type != wpsstm()->post_type_playlist ){
            
            //get autorship if this is a community tracklist
            
            $args = array(
                'ID'            => $this->post_id,
                'post_title'    => $this->title,
                'post_type'     => wpsstm()->post_type_playlist,
            );

            $updated = wp_update_post( $args );
            
            if ( !$updated ) {
                return new WP_Error( 'wpsstm_convert_to_static', __("Error while converting the live tracklist status",'wpsstm') );
            }else{
                
            }
        }

        $this->toggle_enable_wizard(false);
        
        return true;

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
        
        $search = isset($wizard_data['search']) ? $wizard_data['search'] : null;
        $this->save_feed_url($search);
        return $this->save_wizard_settings($wizard_data);

    }
    
    function save_wizard_settings($wizard_settings){

        if ( !$wizard_settings ) return;
        if ( !$this->post_id ) return;

        $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

        //remove all default values so we store only user-edited stuff
        $default_args = $this->options_default;
        $wizard_settings = wpsstm_array_recursive_diff($wizard_settings,$default_args);

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

        $default_args = $default_args = $this->options_default;
        $new_input = array_replace_recursive($default_args,$new_input); //last one has priority

        return $new_input;
    }
    
    //UTC
    function get_expiration_time(){
        if ( !$cache_duration_min = $this->get_options('datas_cache_min') ) return false;
        
        $now = current_time( 'timestamp', true );
        $cache_duration_s = $cache_duration_min * MINUTE_IN_SECONDS;
        return $this->updated_time + $cache_duration_s;
    }

    
    // checks if the playlist has expired (and thus should be refreshed)
    // set 'expiration_time'
    function check_has_expired(){
        
        $cache_duration_min = $this->get_options('datas_cache_min');
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
    
    function get_refresh_rate(){

        $freq = $this->get_options('datas_cache_min');
        
        if (!$freq) return false;

        $freq_secs = $freq * MINUTE_IN_SECONDS;

        return $refresh_time_human = human_time_diff( 0, $freq_secs );
    }
    
    function get_cached_remote_title(){
        return get_post_meta($this->post_id,wpsstm_live_playlists()->remote_title_meta_name,true);
    }

    function get_cached_remote_author(){
        return get_post_meta($this->post_id,wpsstm_live_playlists()->remote_author_meta_name,true);
    }
    
    /*
    Populate remote tracklist tracks
    Fallback on WP tracks if post is not expired.
    */
    
    function populate_tracks($args = null){
        
        if ( $this->did_query_tracks ) return; //we already did it
        
        $cache_duration = $this->get_options('datas_cache_min');
        $has_cache = (bool)$cache_duration;

        //check we should request remote tracks
        if ( $this->is_expired ){
            
             $can_remote_request = ( ( $this->ajax_refresh && wpsstm_is_ajax() ) || !$this->ajax_refresh );
            
            if ( $can_remote_request ){
                /*
                fetch remote tracks
                */

                $populated = $this->populate_remote_tracklist();
                if ( is_wp_error($populated) ){
                    $this->add_notice( 'tracklist-header', 'populate-tracks', $populated->get_error_message());
                    wpsstm()->debug_log($populated->get_error_message(),'WP_SoundSystem_Remote_Tracklist::populate_tracks()' );
                }

                /*
                update playlist (only if cache is enabled)
                */
                
                if ($has_cache){ //populate & save tracks
                    $updated = $this->populate_and_save_tracks();
                    if ( is_wp_error($updated) ){
                        $this->add_notice( 'tracklist-header', 'populate-tracks', $updated->get_error_message(),true );
                        wpsstm()->debug_log($updated->get_error_message(),'WP_SoundSystem_Remote_Tracklist::populate_tracks()' );
                    }
                }
            }
            
        }else{
            parent::populate_tracks($args);
        }

    }
    
    function empty_tracks_msg(){
        if ( !$this->is_expired ) return parent::empty_tracks_msg();
        
        $actions = $this->get_tracklist_actions();
        $refresh = wpsstm_get_array_value('refresh',$actions);
        if ($refresh){
            //use same ID than original link or JS won't work
            $refresh_link = sprintf('<span id="%s"><a href=""%s>%s</a><span>','wpsstm-tracklist-action-refresh',$refresh['href'],__('Refresh tracklist','wpsstm'));
            $refresh_notice = $refresh_link;
        }
        return $refresh_notice;
    }
    
    function get_tracklist_class($extra_classes = null){

        $default = array(
            'wpsstm-live-tracklist',
        );
        
        $classes = array_merge($default,(array)$extra_classes);

        if ( $this->is_expired ){
            $classes[] = 'wpsstm-expired-tracklist';
        }

        return parent::get_tracklist_class($classes);
    }
    
}
