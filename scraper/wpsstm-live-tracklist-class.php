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
    var $ignore_cache = false; //eg. when advanced wizard

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
    
    public $can_remote_request = false; // by default, only allow to query cache for tracks; update this property to allow querying remote tracks.
    
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
                $this->id = md5( $this->feed_url ); //unique ID based on URL
                $this->time_updated_meta_name = sprintf('wpsstm_ltracks_%s',$this->id); //172 characters or less
            }
            
            //title
            if ( $title = get_post_meta($this->post_id,$this->remote_title_meta_name) ){
                $this->title = $title;
            }
            
            //author
            if ( $author = get_post_meta($this->post_id,$this->remote_author_meta_name) ){
                $this->author = $author;
            }
            
        }

    }
    
    protected function get_default_options(){
        return array(
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
    }
    
    function get_options($keys=null){
        $options = array();

        if ($keys){
            return wpsstm_get_array_value($keys, $this->options);
        }else{
            return $this->options;
        }
    }

    function load_subtracks(){
        
        if ( $this->did_query_tracks ) return;
        
        $current_cache_time = get_transient( $this->time_updated_meta_name );

        //cache has expired
        //TO FIX TO MOVE? flush should be done with a CRON job ?
        if ($current_cache_time === false){
            $this->flush_subtracks(); //flush orphan tracks if any
            $this->set_subtrack_ids(); //remove all tracks from playlist
            $this->tracks = array();
        }else{
            //TO FIX populate cached title & author
            $this->updated_time = $current_cache_time;
        }

        $can_refresh = ($this->can_remote_request && !$this->did_query_tracks) ? $this->can_refresh() : false;
        
        $can_refresh = true; //TO FIX TOUOTU

        if ( !$can_refresh ){
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
                }

                update_post_meta($this->post_id,$this->remote_title_meta_name,$this->title);
                
                //set tracklist author
                if ( $author = $this->get_tracklist_author() ){
                    //TO FIX force bad encoding (eg. last.fm)
                    $this->author = $author;
                }
                update_post_meta($this->post_id,$this->remote_author_meta_name,$this->author);

            }else{
                $this->add_notice( 'wizard-header', 'remote-tracks', $remote_tracks->get_error_message(),true );
            }
        }

        $this->did_query_tracks = true;

        new WP_SoundSystem_Live_Playlist_Stats($this); //remote request stats

        wpsstm()->debug_log(json_encode(array('post_id'=>$this->post_id,'did_request'=>$this->did_query_tracks,'remote_tracks_count'=>count($this->tracks))),'WP_SoundSystem_Remote_Tracklist::load_subtracks()' );

        //get options back from page (a preset could have changed them)
        //TO FIX TO CHECK maybe move ?
        $this->options = $this->options; 
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

        $cache_duration = $this->get_options('datas_cache_min');
        $current_cache_time = get_transient( $this->time_updated_meta_name );
        $text_time = null;

        if (!$this->ignore_cache){
            $can = ( $current_cache_time === false); //cache expired
        }else{
            $can = true;
        }

        if ( !$cache_duration ){
            $this->add_notice( 'wizard-header-advanced', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Options tab).",'wpsstm') );
        }

        if ($this->updated_time){
            $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->updated_time ), get_option( 'date_format' ) );
            $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $this->updated_time ), get_option( 'time_format' ) );
            $text_time = sprintf(__('on %s - %s','wpsstm'),$date,$time);
        }

        wpsstm()->debug_log(
            array(
                'transient' =>      $this->time_updated_meta_name,
                'cache_duration' => $cache_duration,
                'ignore_cache' =>   (bool)$this->ignore_cache,
                'last_request' =>   $text_time,
                'can_request' =>    (bool)$can
            ),
            "WP_SoundSystem_Remote_Tracklist::can_refresh()"
        ); 

        return $can;

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
    //TO FIX should be done with a cron job (with all tracks) ?
    function flush_subtracks(){
        $force_delete = false;
        
        $subtrack_ids = $this->get_subtrack_ids();
        if (!$subtrack_ids) return;
        
        $flush_track_ids = array();

        //get tracks to flush
        foreach ((array)$subtrack_ids as $track_id){
            
            $track = new WP_SoundSystem_Track($track_id);

            //ignore if post is attached to any (other than this one) playlist
            $tracklist_ids = $track->get_parent_ids();
            if(($key = array_search($this->post_id, $tracklist_ids)) !== false) unset($tracklist_ids[$key]);
            if ( !empty($tracklist_ids) ) continue;
            
            //ignore if post is favorited by any user
            $loved_by = $track->get_track_loved_by();
            if ( !empty($loved_by) ) continue;
            
            $flush_track_ids[] = $track_id;
        }
        
        foreach ((array)$flush_track_ids as $track_id){
            wp_delete_post( $track_id, $force_delete );
        }

        wpsstm()->debug_log(array('subtracks'=>count($subtrack_ids),'flushed'=>count($flush_track_ids)),"WP_SoundSystem_Remote_Tracklist::flush_subtracks()"); 

    }
}
