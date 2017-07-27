<?php

/*
How the scraper works :

It would be too much queries to save the real tracks history for a live playlist : 
it would require to query each remote playlist URL every ~30sec to check if a new track has been played, 
which would use a LOT of resources; and create thousands and thousands of track posts.

Also, we want live playlists tracklists to be related to an URL rather than to a post ID, allowing us to cache a tracklist based on 
its URL and not on a post meta - which would require to create a post first.

Tracklist cache based on a URL is stored as WordPress Transients.
When the tracklist is displayed, we refresh the tracklist only if the transient is expired.
*/

use \ForceUTF8\Encoding;

class WP_SoundSystem_Remote_Tracklist extends WP_SoundSystem_Tracklist{
    
    //preset infos
    var $preset_slug = 'default';
    var $preset_name = null;

    //url stuff
    var $pattern = null; //pattern used to check if the scraper URL matches the preset.
    var $variables = array(); //list of variables that matches the regex groups from $pattern
    var $redirect_url = null; //if needed, a redirect URL.  Can use variables extracted from the pattern using the %variable% format.
    
    public $feed_url = null;
    public $id = null;
    public $time_updated_meta_name = null;
    public $remote_title_meta_name = 'wpsstm_remote_title';
    public $remote_author_meta_name = 'wpsstm_remote_author_name';
    
    var $expire_time = null;
    var $cache_only = true;//by default, for speedness, disabble remote request tracks.  We have to enable it manually.

    //response
    var $request_pagination = array(
        'total_items'       => null, //When possible (eg. APIs), return the count of total tracks so we know how much tracks we should request.  Override this in your preset.
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
    
    public $is_expired = null;
    
    
    //request
    static $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'UTF-8', //our input is always UTF8 - look for fixUTF8() in code
        //'convert_to_encoding'       => 'UTF-8' //match WP database (or transients won't save)
    );
    
    public function __construct($post_id = null) {

        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/autoload.php');
        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/class-array2xml.php');

        $this->preset_name = __('HTML Scraper','wpsstm');
        
        $this->options_default = $this->get_default_options();

        parent::__construct($post_id);
        
        if ($this->post_id){
            
            $this->feed_url = wpsstm_get_live_tracklist_url($this->post_id);

            if ( $options = get_post_meta($this->post_id,wpsstm_live_playlists()->scraper_meta_name ,true) ){
                
                $this->options = array_replace_recursive((array)$this->options_default,(array)$options); //last one has priority
            }

            if ($this->feed_url){
                $this->location = $this->feed_url;
                $this->time_updated_meta_name = sprintf('wpsstm_ltracks_%s',$this->post_id); //172 characters or less
            }

            //title
            if ( $title = get_post_meta($this->post_id,$this->remote_title_meta_name,true) ){
                $this->title = $title;
            }
            
            //author
            if ( $author = get_post_meta($this->post_id,$this->remote_author_meta_name,true) ){
                $this->author = $author;
            }
            
            //time
            $this->updated_time = get_transient( $this->time_updated_meta_name );
            $this->is_expired = ($this->updated_time === false);

            //set expiration time
            if ($this->updated_time){
                $duration_m = $this->get_options('datas_cache_min');
                $duration_s = $duration_m * MINUTE_IN_SECONDS;
                $this->expire_time = $this->updated_time + $duration_s; //UTC
            }

            $this->temporary_status_notice();
            $this->live_tracklist_notice();
        }

    }
    

    /*
    Return the (live) subtracks IDs for a tracklist.
    */

    function get_subtrack_ids(){
        $ordered_ids = get_post_meta($this->post_id,wpsstm_live_playlists()->subtracks_live_metaname,true);
        if ( empty($ordered_ids) ) return;
        
        //validate those IDs, we must be sure they are tracks.
        $filtered_ids = $this->filter_subtrack_ids($ordered_ids);

        return $filtered_ids;
        
    }
    
    /*
    Append (live) subtracks IDs to a tracklist.
    */
    
    function append_subtrack_ids($append_ids){
        //force array
        if ( !is_array($append_ids) ) $append_ids = array($append_ids);
        
        if ( empty($append_ids) ){
            return new WP_Error( 'wpsstm_tracks_no_post_ids', __('Required tracks IDs missing','wpsstm') );
        }
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($append_ids)), "WP_SoundSystem_Remote_Tracklist::append_subtrack_ids()");
        
        $subtrack_ids = (array)$this->get_subtrack_ids();
        $subtrack_ids = array_merge($subtrack_ids,$append_ids);
        return $this->set_subtrack_ids($subtrack_ids);
    }
    
    /*
    Remove (live) subtracks IDs from a tracklist.
    */
    
    function remove_subtrack_ids($remove_ids){
        //force array
        if ( !is_array($remove_ids) ) $remove_ids = array($remove_ids);
        
        if ( empty($remove_ids) ){
            return new WP_Error( 'wpsstm_tracks_no_post_ids', __('Required tracks IDs missing','wpsstm') );
        }
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($remove_ids)), "WP_SoundSystem_Remote_Tracklist::remove_subtrack_ids()");
        
        $subtrack_ids = (array)$this->get_subtrack_ids();
        $subtrack_ids = array_diff($subtrack_ids,$remove_ids);
        
        return $this->set_subtrack_ids($subtrack_ids);
    }
    
    /*
    Assign (live) subtracks IDs to a tracklist.
    */

    function set_subtrack_ids($ordered_ids = null){
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_tracklist_no_post_id', __('Required tracklist ID missing','wpsstm') );
        }

        //capability check
        $post_type = get_post_type($this->post_id);
        $tracklist_obj = get_post_type_object($post_type);
        if ( !current_user_can($tracklist_obj->cap->edit_post,$this->post_id) ){
            return new WP_Error( 'wpsstm_tracklist_no_edit_cap', __("You don't have the capability required to edit this tracklist.",'wpsstm') );
        }
        
        if ($ordered_ids){
            $ordered_ids = array_map('intval', $ordered_ids); //make sure every array item is an int - required for WP_SoundSystem_Track::get_parent_ids()
            $ordered_ids = array_filter($ordered_ids, function($var){return !is_null($var);} ); //remove nuls if any
            $ordered_ids = array_unique($ordered_ids);
        }

        //set post status to 'publish' if it is not done yet (it could be a temporary post)
        //TO FIX TO CHECK
        foreach((array)$ordered_ids as $track_id){
            $track_post_type = get_post_status($track_id);
            if ($track_post_type != 'publish'){
                wp_update_post(array(
                    'ID' =>             $track_id,
                    'post_status' =>    'publish'
                ));
            }
        }
        
        wpsstm()->debug_log( array('tracklist_id'=>$this->post_id, 'subtrack_ids'=>json_encode($ordered_ids)), "WP_SoundSystem_Remote_Tracklist::set_subtrack_ids()"); 
        
        if ($ordered_ids){
            return update_post_meta($this->post_id,wpsstm_live_playlists()->subtracks_live_metaname,$ordered_ids);
        }else{
            return delete_post_meta($this->post_id,wpsstm_live_playlists()->subtracks_live_metaname);
        }

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
            'datas_cache_min'           => (int)wpsstm()->get_options('live_playlists_cache_min'), //time tracklist is cached - if set to null, will take plugin value
            'musicbrainz'               => wpsstm()->get_options('mb_auto_id') //should we use musicbrainz to get the tracks data ? - if set to null, will take plugin value
        );
        
        return array_replace_recursive((array)parent::get_default_options(),$live_options); //last one has priority
        
    }

    function load_subtracks(){
        
        if ( $this->did_query_tracks ) return;
        
        if ( $this->is_expired && $this->get_subtrack_ids() ){
            $this->flush_live_subtracks();
        }

        if ( !$this->can_refresh() ){
            parent::load_subtracks();
            return;
        }

        $this->update_last_query_time();

        if ( $remote_tracks = $this->get_all_raw_tracks() ){

            if ( !is_wp_error($remote_tracks) ) {

                if ( current_user_can('administrator') ){ //this could reveal 'secret' urls (API keys, etc.) So limit the notice display.
                    if ( $this->feed_url != $this->redirect_url ){
                        $this->add_notice( 'wizard-header-advanced', 'scrapped_from', sprintf(__('Scraped from : %s','wpsstm'),'<em>'.$this->redirect_url.'</em>') );
                    }
                }

                $this->add($remote_tracks);
                $this->save_subtracks();
                
                //sort
                if ($this->get_options('tracks_order') == 'asc'){
                    $this->tracks = array_reverse($this->tracks);
                }

                //populate page notices
                foreach($this->notices as $notice){
                    $this->notices[] = $notice;
                }

                //set tracklist title
                if ( $title = $this->get_tracklist_title() ){
                    //TO FIX force bad encoding (eg. last.fm)
                    $this->title = $title;
                    update_post_meta($this->post_id,$this->remote_title_meta_name,$this->title);
                }else{
                    delete_post_meta($this->post_id,$this->remote_title_meta_name);
                }

                
                
                //set tracklist author
                if ( $author = $this->get_tracklist_author() ){
                    //TO FIX force bad encoding (eg. last.fm)
                    $this->author = $author;
                }else{
                    delete_post_meta($this->post_id,$this->remote_author_meta_name);
                }
                

            }else{
                $this->add_notice( 'wizard-header', 'remote-tracks', $remote_tracks->get_error_message(),true );
            }
        }

        $this->did_query_tracks = true;

        new WP_SoundSystem_Live_Playlist_Stats($this); //remote request stats

        wpsstm()->debug_log(json_encode(array('post_id'=>$this->post_id,'did_request'=>$this->did_query_tracks,'remote_tracks_count'=>count($this->tracks),'last_request_time'=>$this->updated_time)),'WP_SoundSystem_Remote_Tracklist::load_subtracks()' );

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
                foreach( $xml_errors as $xml_error_obj ) {
                    $this->add_notice( 'wizard-header-advanced', 'xml_error', sprintf(__('simplexml Error [%1$s] : %2$s','wpsstm'),$xml_error_obj->code,$xml_error_obj->message), true );
      
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

                    $this->add_notice( 'wizard-header-advanced', 'json2xml', __("The json input has been converted to XML.",'wpsstm') );
 
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
        if (!$selector) return new WP_Error( 'no_track_selector', __('Required tracks selector is missing','spiff') );

        //QueryPath
        try{
            $track_nodes = qp( $body_node, null, self::$querypath_options )->find($selector);
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }
        
        if ( $track_nodes->length == 0 ){
            return new WP_Error( 'no_track_nodes', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist – you may perhaps try again later.','spiff') );
        }

        return $track_nodes;

    }

    protected function parse_track_nodes($track_nodes){

        $selector_artist = $this->get_options( array('selectors','track_artist') );
        if (!$selector_artist) return new WP_Error( 'no_track_selector', __('Required track artist selector is missing','wpsstm') );
        
        $selector_title = $this->get_options( array('selectors','track_title') );
        if (!$selector_title) return new WP_Error( 'no_track_selector', __('Required track title selector is missing','wpsstm') );

        $tracks_arr = array();
        
        foreach($track_nodes as $key=>$single_track_node) {
            
            $sources = array();
            if ( $source_urls = $this->get_track_source_urls($single_track_node) ){
                foreach ((array)$source_urls as $source_url){
                    $sources[] = array('url'=>$source_url,'origin'=>'scraper');
                }
            }
            
            //format response
            $artist =   $this->get_track_artist($single_track_node);
            $title =    $this->get_track_title($single_track_node);
            $album =    $this->get_track_album($single_track_node);

            $args = array(
                'artist'        => $artist,
                'title'         => $title,
                'album'         => $album,
                'image'         => $this->get_track_image($single_track_node),
                'sources'       => $sources
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

    /*
    Use a custom function to display our notices since natice WP function add_settings_error() works only backend.
    */
    function add_notice($slug,$code,$message,$error = false){
        
        wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSystem_Remote_Tracklist notice]: ' . $message ); 
        
        $this->notices[] = array(
            'slug'      => $slug,
            'code'      => $code,
            'message'   => $message,
            'error'     => $error
        );
    }
    
    public function set_request_pagination( $args ) {

        $args = wp_parse_args( $args, $this->request_pagination );

        if ( $args['page_items_limit'] > 0 ){
            $args['total_pages'] = ceil( $args['total_items'] / $args['page_items_limit'] );
        }

        $this->request_pagination = $args;
    }
    
    public function can_refresh(){
        
        $do_refresh = false;
        $cache_duration = $this->get_options('datas_cache_min');
        $text_time = null;
        
        if (!$this->cache_only){
            
            if ( !$cache_duration ){

                $do_refresh = true;

                $this->add_notice( 'wizard-header-advanced', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Options tab).",'wpsstm') );

            }else{
                if ($this->is_expired){
                    $do_refresh = true;
                }
            }
            
        }

        if ($this->updated_time){
            $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->updated_time ), get_option( 'date_format' ) );
            $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->updated_time ), get_option( 'time_format' ) );
            $text_time = sprintf(__('on %s - %s','wpsstm'),$date,$time);
        }

        wpsstm()->debug_log(
            array(
                'do_refresh' =>     (bool)$do_refresh,
                'is_expired' =>     $this->is_expired,
                'last_request' =>   $text_time,
                'cache_duration' => $cache_duration,
                'cache_only' =>     (bool)$this->cache_only,
                'transient' =>      $this->time_updated_meta_name,
            ),
            "WP_SoundSystem_Remote_Tracklist::can_refresh()"
        ); 

        return $do_refresh;

    }
    
    function update_last_query_time(){
        
        if ( !$duration_min = $this->get_options('datas_cache_min') ) return;
        
        $now = current_time( 'timestamp', true );

        $duration = $duration_min * MINUTE_IN_SECONDS;
        if ( $success = (bool)set_transient( $this->time_updated_meta_name, $now, $duration ) ){
            $this->expire_time = $now + $duration; //UTC
        }

        wpsstm()->debug_log(array('success'=>$success,'transient'=>$this->time_updated_meta_name,'duration_min'=>$duration_min),"WP_SoundSystem_Remote_Tracklist::update_last_query_time()"); 
        
    }

    /*
    Flush temporary tracks
    */
    function flush_live_subtracks(){

        $force_delete = false;
        $flushed = 0;
        
        $orphan_ids = $this->get_orphan_track_ids();
        
        foreach ((array)$orphan_ids as $track_id){
            if ( wp_delete_post( $track_id, $force_delete ) ){
                $flushed += 1;
            }
        }

        wpsstm()->debug_log(array('subtracks'=>count($orphan_ids),'flushed'=>$flushed),"WP_SoundSystem_Remote_Tracklist::flush_live_subtracks()");

        return true;

    }
    
    function convert_to_static_playlist(){
        
        $this->move_wizard_tracks();

        if ( !set_post_type( $this->post_id, wpsstm()->post_type_playlist ) ) {
            return new WP_Error( 'switched_live_playlist_status', __("Error while converting the live tracklist status",'wpsstm') );
        }

        return $this->delete_wizard_datas();

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
        
        $allowed_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );
        
        if( !in_array($post_type,$allowed_post_types ) ) return;
        if (!$wizard_data) return;

        $wizard_url = ( isset($wizard_data['feed_url']) ) ? trim($wizard_data['feed_url']) : null;
        $reset = (bool)isset($wizard_data['reset']);
        $restore = (bool)isset($wizard_data['restore']);

        if($restore){
            return $this->restore_wizard_datas();
        }

        if( $reset || !$wizard_url ){
            return $this->delete_wizard_datas();
        }

        $this->save_feed_url($wizard_url);
        return $this->save_wizard_settings($wizard_data);
    }
    
    function save_wizard_settings($wizard_settings){

        if ( !$wizard_settings ) return;
        if ( !$this->post_id ) return;

        //while updating the live tracklist settings, ignore caching
        

        $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

        //keep only NOT default values
        $default_args = $this->options_default;
        $wizard_settings = wpsstm_array_recursive_diff($wizard_settings,$default_args);

        if (!$wizard_settings){
            delete_post_meta($this->post_id, wpsstm_live_playlists()->scraper_meta_name);
        }else{
            update_post_meta($this->post_id, wpsstm_live_playlists()->scraper_meta_name, $wizard_settings);
        }

        do_action('spiff_save_wizard_settings', $wizard_settings, $this->post_id);

    }
    
    /*
     * Sanitize wizard data
     */
    
    function sanitize_wizard_settings($input){

        $previous_values = $this->get_options();
        $new_input = $previous_values;
        
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
        $new_input['tracks_order'] = ( isset($input['tracks_order']) ) ? $input['tracks_order'] : null;

        //musicbrainz
        $new_input['musicbrainz'] = ( isset($input['musicbrainz']) ) ? $input['musicbrainz'] : null;
        
        $default_args = $default_args = $this->options_default;
        $new_input = array_replace_recursive($default_args,$new_input); //last one has priority

        return $new_input;
    }
    
    /*
    For posts that have the 'wpsstm-wizard' status, notice the author that it is a temporary playlist.
    //TO FIX switch to tracklist notices
    */
    
    function temporary_status_notice(){

        if ( get_post_status($this->post_id) != wpsstm()->temp_status ) return;
        
        $post_author = get_post_field( 'post_author', $this->post_id );        
        if ( get_current_user_id() != $post_author ) return;
        
        //TO FIX use correct option value
        $trash_time_secs = 1440 * MINUTE_IN_SECONDS;
        $trash_time_human = human_time_diff( 0, $trash_time_secs );
        
        $notice = sprintf(__('This is a tempory playlist.  Unless you change its status, it will be deleted in %s.','wpsstm'),$trash_time_human);
        $this->add_notice( 'tracklist-header', 'temporary_tracklist', $notice );
        
    }
    
    function live_tracklist_notice(){

        if (!$this->tracks) return;
        
        $post_author = get_post_field( 'post_author', $this->post_id );
        
        if ( get_current_user_id() != $post_author ) return;
        $notice = __("This tracklist is currently <em>live</em>, which means it remains synced with the remote source.  If you want to convert it to a static playlist, click the Lock link.",'wpsstm');
        $this->add_notice( 'tracklist-header', 'lock_live_tracklist', $notice );
        
    }
    
}
