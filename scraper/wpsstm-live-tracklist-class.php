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

class WP_SoundSytem_Remote_Tracklist extends WP_SoundSytem_Tracklist{
    
    //preset infos
    var $preset_slug = 'default';
    var $preset_name = null;

    //url stuff
    var $pattern = null; //pattern used to check if the scraper URL matches the preset.
    var $variables = array(); //list of variables that matches the regex groups from $pattern
    var $redirect_url = null; //if needed, a redirect URL.  Can use variables extracted from the pattern using the %variable% format.
    
    public $feed_url;
    public $id;
    public $transient_name_cache; 
    
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
    
    static $meta_key_scraper_url = '_wpsstm_scraper_url';
    static $live_playlist_options_meta_name = '_wpsstm_scraper_options';

    public $datas_cache = null;
    public $datas_remote = null;
    public $datas = null;

    public $notices = array();
    
    //request
    static $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'UTF-8', //our input is always UTF8 - look for fixUTF8() in code
        //'convert_to_encoding'       => 'UTF-8' //match WP database (or transients won't save)
    );
    
    public function __construct($post_id_or_feed_url = null) {

        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/autoload.php');
        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/class-array2xml.php');

        parent::__construct();

        $this->preset_name = __('HTML Scraper','wpsstm');
        
        //default options
        $options_default = array(
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
        
        //options
        $options_parent = $this->options_default;
        $this->options_default = $this->options = array_replace_recursive((array)$options_parent,(array)$options_default,(array)$this->options_default); //last one has priority

        $post_id = $feed_url = null;

        if ( $post_id_or_feed_url ){
            $post_id = ( ctype_digit(strval($post_id_or_feed_url)) ) ? $post_id_or_feed_url : null;
            $feed_url = filter_var($post_id_or_feed_url, FILTER_SANITIZE_URL);
        }
        
        if ($post_id){
            parent::__construct($post_id);
            $feed_url = get_post_meta( $this->post_id, self::$meta_key_scraper_url, true );
            
            $this->options = $this->get_options();

        }

        if ($feed_url){
            //set feed url
            $this->feed_url = $feed_url;
            $this->id = md5( $this->feed_url ); //unique ID based on URL
            $this->transient_name_cache = 'wpsstm_ltracks_'.$this->id; //172 characters or less
        }

    }
    
    function get_options($keys=null){
        $options = array();
        $db_options = get_post_meta($this->post_id,self::$live_playlist_options_meta_name ,true);
        $options = array_replace_recursive((array)$this->options_default,(array)$db_options); //last one has priority

        if ($keys){
            return wpsstm_get_array_value($keys, $options);
        }else{
            return $options;
        }
    }

    function load_remote_tracks($remote_request = false){

        if (!$this->feed_url) return;

        //try to get cache first

        $cache_tracks = $remote_tracks = array();
        $this->datas = $this->datas_cache = $this->get_cache();
        
        if ($this->datas_cache){
            $transient_timeout_name = '_transient_timeout_' . $this->transient_name_cache;
            if ( $cache_expire_time = get_option( $transient_timeout_name ) ){
                $this->expire_time = $cache_expire_time;
            }
            
            $cache_tracks = $this->datas_cache['tracks'];

            $this->add($cache_tracks); //populate cache tracks

            //we got cached track
            if ( $cached_total_items = count($this->tracks)  ){

                $this->add_notice( 'wizard-header', 'cache_tracks_loaded', sprintf(__('A cache entry with %s tracks was found (%s).','wpsstm'),$cached_total_items,gmdate(DATE_ISO8601,$this->datas_cache['timestamp'])) );
            }

        }

        //get remote tracks
        if ( !$this->tracks && $remote_request ){

            $this->datas_remote = false; // so we can detect that we ran a remote request
            if ( $remote_tracks = $this->get_all_raw_tracks() ){

                if ( !is_wp_error($remote_tracks) ) {

                    if ( current_user_can('administrator') ){ //this could reveal 'secret' urls (API keys, etc.) So limit the notice display.
                        if ( $this->feed_url != $this->redirect_url ){
                            $this->add_notice( 'wizard-header-advanced', 'scrapped_from', sprintf(__('Scraped from : %s','wpsstm'),'<em>'.$this->redirect_url.'</em>') );
                        }
                    }

                    $this->add($remote_tracks);

                    //Musicbrainz lookup
                    //TO FIX quite slow for big playlists. Think about a way to handle this.
                    /*
                    if ( $this->get_options('musicbrainz') == 'on'  ){
                        foreach ($this->tracks as $track){
                            $track->musicbrainz();
                        }
                    }
                    */

                    //populate page notices
                    foreach($this->notices as $notice){
                        $this->notices[] = $notice;
                    }

                    $tracks_arr = $this->array_export();

                    //format response
                    $title =    $this->get_tracklist_title();
                    $author =   $this->get_tracklist_author();

                    $this->datas = $this->datas_remote = array(
                        'title'         => $title,
                        'author'        => $author,
                        'tracks'        => $remote_tracks,
                        'timestamp'     => current_time( 'timestamp', true ) //UTC
                    );

                    //set cache if there is none
                    if ( !$this->datas_cache ){
                        $this->set_cache();
                    }

                }else{
                    $this->add_notice( 'wizard-header', 'remote-tracks', $remote_tracks->get_error_message(),true );
                }
            }

        }
        
        wpsstm()->debug_log(json_encode(array('remote_request'=>$remote_request,'cache_tracks'=>count($cache_tracks),'has_remote_tracks'=>count($remote_tracks))),'load_remote_tracks()' );

        //get options back from page (a preset could have changed them)
        $this->options = $this->options; 
    
        /*
        Build Tracklist
        */
        
        //tracklist informations
        //set only if not already defined (eg. by a post ID); except for timestamp
        
        $this->updated_time = wpsstm_get_array_value('timestamp', $this->datas);

        if ( !$this->title ){
            $this->title = wpsstm_get_array_value('title', $this->datas);
        }
        if ( !$this->author ){
            $this->author = wpsstm_get_array_value('author', $this->datas);
        }

        if ( !$this->location ){
            $this->location = $this->feed_url;
        }

        //stats
        if ( $this->datas_remote !==null ){ //we made a remote request
            new WP_SoundSytem_Live_Playlist_Stats($this);
        }

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
                    $dom = WP_SoundSytem_Array2XML::createXML($data,'root','element');
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
        
        //sort
        if ($this->get_options('tracks_order') == 'asc'){
            $tracks_arr = array_reverse($tracks_arr);
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

    private function get_track_node_content($track_node,$selectors,$single_value=true){
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
        
        wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSytem_Remote_Tracklist notice]: ' . $message ); 
        
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
    
    public function get_cache(){

        if ( $this->ignore_cache ){
            wpsstm()->debug_log("ignore_cache is set","WP_SoundSytem_Remote_Tracklist::get_cache()"); 
            return;
        }
        
        if ( !$this->get_options('datas_cache_min') ){

            $this->add_notice( 'wizard-header-advanced', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Options tab).",'wpsstm') );

            return false;
        }

        if ( $cache = get_transient( $this->transient_name_cache ) ){
            $cache_debug = $cache;
            $cache_debug['tracks_count'] = ( isset($cache['tracks']) ) ? count($cache['tracks']) : null;
            unset($cache_debug['tracks']);
            wpsstm()->debug_log(array('transient'=>$this->transient_name_cache,'cache'=>json_encode($cache_debug)),"WP_SoundSytem_Remote_Tracklist::get_cache()"); 
        }
        
        return $cache;

    }
    
    function set_cache(){

        if ( !$duration_min = $this->get_options('datas_cache_min') ) return;

        $duration = $duration_min * MINUTE_IN_SECONDS;
        $success = set_transient( $this->transient_name_cache, $this->datas_remote, $duration );

        $debug_cache = $this->datas_remote;
        $debug_cache['tracks_count'] = ( isset($debug_cache['tracks']) ) ? count($debug_cache['tracks']) : null;
        unset($debug_cache['tracks']);
        
    $this->expire_time = current_time( 'timestamp', true ) + $duration; //UTC
            
        wpsstm()->debug_log(array('success'=>$success,'transient'=>$this->transient_name_cache,'duration_min'=>$duration_min,'cache'=>json_encode($debug_cache)),"WP_SoundSytem_Remote_Tracklist::set_cache()"); 
        
    }

    function delete_cache(){
        delete_transient( $this->transient_name_cache );
    }
    
    function get_refresh_link(){
        $refresh_icon = '<i class="fa fa-rss" aria-hidden="true"></i>';
        $error_icon = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
        $refresh_text = __('Refresh Playlist','wpsstm');
        return sprintf('<a class="wpsstm-refresh-playlist" href="#">%s %s %s</a>',$refresh_icon,$error_icon,$refresh_text);
    }

}
